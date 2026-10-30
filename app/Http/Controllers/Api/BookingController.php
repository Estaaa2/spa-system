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
        $reassignment = \App\Models\ReassignmentRequest::where('booking_id', $booking->id)
            ->latest()
            ->first();

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
            'date_raw'         => $booking->appointment_date?->format('Y-m-d'),
            // The therapist's latest "can't make it" request for this booking.
            'reassignment_status'  => $reassignment?->status,
            'reassignment_pending' => $reassignment?->status === 'pending',
            'reassignment_rejection_reason' => $reassignment?->status === 'rejected'
                ? $reassignment->rejection_reason
                : null,
            // Home visits (staff verification Phase 6). The arrival code itself is
            // never sent to staff: the customer tells it at the door.
            'is_home_service'         => $booking->requiresArrivalCode(),
            'customer_email_verified' => (bool) $booking->customer?->email_verified_at,
            'arrival'                 => app(\App\Services\Bookings\ArrivalService::class)->stateFor($booking, request()->user()),
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

    // ── GET /api/assigned-bookings ────────────────────────────────────────
    public function assignedBookings(Request $request)
    {
        try {
            $user = $request->user();

            $bookings = Booking::with('branch', 'spa', 'customer')
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

            $bookings = Booking::with('branch', 'spa', 'customer')
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
}
