<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BookingController extends Controller
{
    // ── Helper: format a booking into Flutter-friendly shape ──────────────
    private function formatBooking(Booking $booking): array
    {
        $therapistName = 'Not Assigned';
        if ($booking->therapist_id) {
            $staff = Staff::with('user')
                ->where('user_id', $booking->therapist_id)
                ->first();
            if ($staff?->user) {
                $therapistName = $staff->user->name;
            }
        }

        // Format service type for display
        $serviceTypeDisplay = match($booking->service_type) {
            'in_branch' => 'In-Branch',
            'in_home' => 'Home Service',
            default => $booking->service_type ?? 'In-Branch'
        };

        // Get spa name
        $spaName = $booking->spa?->name ?? $booking->branch?->name ?? 'Unknown Spa';
        $hasRating = $booking->rating()->exists();
        $ratingValue = $hasRating ? $booking->rating->rating : null;

        // Get branch location (address/city)
        $branchLocation = null;
        if ($booking->branch) {
            // Try to get location from branch - use whichever field exists
            $branchLocation = $booking->branch->location ??
                             $booking->branch->address ??
                             $booking->branch->city ??
                             $booking->branch->name;
        }

        // Get the actual treatment name
        $treatmentName = $this->getTreatmentName($booking->treatment);

        // Convert UTC to Asia/Manila timezone for display
        $appointmentDate = $booking->appointment_date;
        if ($appointmentDate) {
            try {
                $appointmentDate = Carbon::parse($appointmentDate)
                    ->timezone('Asia/Manila')
                    ->toDateString();
            } catch (\Exception $e) {
                $appointmentDate = $booking->appointment_date;
            }
        }

        return [
            'id'               => $booking->id,
            'spa_name'         => $spaName,
            'customer_name'    => $booking->customer_name ?? '',
            'customer_email'   => $booking->customer_email ?? '',
            'customer_phone'   => $booking->customer_phone ?? '',
            'customer_address' => $booking->customer_address ?? '',
            'service_type'     => $serviceTypeDisplay,
            'treatment'        => $treatmentName,
            'treatment_code'   => $booking->treatment,
            'therapist'        => $therapistName,
            'appointment_date' => $appointmentDate,
            'has_rating' => $hasRating,
            'rating_value' => $ratingValue,
            'start_time'       => $booking->start_time ?? '',
            'end_time'         => $booking->end_time ?? '',
            'status'           => $booking->status ?? 'reserved',
            'branch_name'      => $booking->branch?->name ?? 'Unknown Branch',
            'branch_location' => $booking->branch?->location ?? $booking->branch?->address ?? '', // ← ADDED: branch location
            'branch_id'        => $booking->branch_id,
            'spa_id'           => $booking->spa_id,
            'amount_paid'      => (float) ($booking->amount_paid ?? 0),
            'total_amount'     => (float) ($booking->total_amount ?? 0),
            'balance_amount'   => (float) ($booking->balance_amount ?? 0),
            'payment_status'   => $booking->payment_status ?? 'unpaid',
            'booking_source'   => $booking->booking_source ?? 'mobile',
        ];
    }

    private function getTreatmentName(string $treatmentCode): string
    {
        try {
            if (str_starts_with($treatmentCode, 'treatment_')) {
                $id = (int) str_replace('treatment_', '', $treatmentCode);

                // Direct database query using DB facade
                $treatment = DB::table('treatments')->where('id', $id)->first();

                if ($treatment) {
                    return $treatment->name;
                }
            }

            if (str_starts_with($treatmentCode, 'package_')) {
                $id = (int) str_replace('package_', '', $treatmentCode);
                $package = DB::table('packages')->where('id', $id)->first();
                if ($package) {
                    return $package->name;
                }
            }
        } catch (\Exception $e) {
            // Log error but don't break the app
            \Log::error('getTreatmentName error: ' . $e->getMessage());
        }

        return 'Spa Treatment';
    }

    // ── GET /api/my-bookings ──────────────────────────────────────────────
    public function myBookings(Request $request)
    {
        try {
            $user = $request->user();

            $bookings = Booking::with('branch', 'spa')
                ->where('customer_user_id', $user->id)
                ->orderByDesc('appointment_date')
                ->get();

            return response()->json([
                'success'  => true,
                'bookings' => $bookings->map(fn($b) => $this->formatBooking($b))->values(),
            ]);
        } catch (\Exception $e) {
            \Log::error('myBookings error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load bookings: ' . $e->getMessage()
            ], 500);
        }
    }

    // ── GET /api/assigned-bookings ────────────────────────────────────────
    public function assignedBookings(Request $request)
    {
        try {
            $user = $request->user();

            $bookings = Booking::with('branch', 'spa')
                ->where('therapist_id', $user->id)
                ->orderByDesc('appointment_date')
                ->get();

            return response()->json([
                'success'  => true,
                'bookings' => $bookings->map(fn($b) => $this->formatBooking($b))->values(),
            ]);
        } catch (\Exception $e) {
            \Log::error('assignedBookings error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load assigned bookings'
            ], 500);
        }
    }

    // ── GET /api/therapist/schedule ───────────────────────────────────────
    public function therapistSchedule(Request $request)
    {
        try {
            $user  = $request->user();
            $start = $request->query('week_start', now()->startOfWeek()->toDateString());
            $end   = $request->query('week_end', now()->endOfWeek()->toDateString());

            $bookings = Booking::with('branch', 'spa')
                ->where('therapist_id', $user->id)
                ->whereNotIn('status', ['cancelled'])
                ->whereBetween('appointment_date', [$start, $end])
                ->orderBy('appointment_date')
                ->orderBy('start_time')
                ->get();

            return response()->json([
                'success'  => true,
                'bookings' => $bookings->map(fn($b) => $this->formatBooking($b))->values(),
            ]);
        } catch (\Exception $e) {
            \Log::error('therapistSchedule error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load schedule'
            ], 500);
        }
    }

    // ── POST /api/bookings ────────────────────────────────────────────────
    public function store(Request $request)
    {
        try {
            $request->validate([
                'spa_id'           => 'required|exists:spas,id',
                'branch_id'        => 'required|exists:branches,id',
                'treatment'        => 'required|string',
                'service_type'     => 'required|in:In-Branch,Home Service',
                'appointment_date' => 'required|date|after:today',
                'start_time'       => 'required|string',
                'customer_name'    => 'required|string',
                'customer_email'   => 'required|email',
                'customer_phone'   => 'required|string',
                'customer_address' => 'nullable|string',
                'total_amount'     => 'nullable|numeric',
            ]);

            $user = $request->user();

            $booking = Booking::create([
                'spa_id'            => $request->spa_id,
                'branch_id'         => $request->branch_id,
                'customer_user_id'  => $user->id,
                'created_by_user_id' => $user->id,
                'booking_source'    => 'mobile',
                'status'            => 'reserved',
                'payment_status'    => 'unpaid',
                'amount_paid'       => 0,
                'total_amount'      => $request->total_amount ?? 0,
                'balance_amount'    => $request->total_amount ?? 0,
                'service_type'      => $request->service_type,
                'treatment'         => $request->treatment,
                'customer_name'     => $request->customer_name,
                'customer_email'    => $request->customer_email,
                'customer_phone'    => $request->customer_phone,
                'customer_address'  => $request->customer_address ?? '',
                'appointment_date'  => $request->appointment_date,
                'start_time'        => $request->start_time,
                'end_time'          => $request->end_time ?? '',
            ]);

            $booking->load('branch', 'spa');

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully.',
                'booking' => $this->formatBooking($booking),
            ], 201);
        } catch (\Exception $e) {
            \Log::error('store booking error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create booking: ' . $e->getMessage()
            ], 500);
        }
    }

    // ── PATCH /api/bookings/{id}/status ───────────────────────────────────
    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:reserved,confirmed,in_progress,completed,cancelled',
            ]);

            $booking = Booking::findOrFail($id);
            $booking->update(['status' => $request->status]);

            return response()->json([
                'success' => true,
                'message' => 'Status updated.',
                'booking' => $this->formatBooking($booking->fresh('branch', 'spa')),
            ]);
        } catch (\Exception $e) {
            \Log::error('updateStatus error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status'
            ], 500);
        }
    }
}
