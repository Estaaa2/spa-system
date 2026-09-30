<?php

namespace App\Http\Controllers;

use App\Mail\RescheduleApprovedMail;
use App\Mail\RescheduleRejectedMail;
use App\Models\Booking;
use App\Models\OperatingHours;
use App\Models\RescheduleRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class RescheduleRequestController extends Controller
{
    // =====================================================
    // CUSTOMER: Submit reschedule request
    // =====================================================
    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id'      => ['required', 'exists:bookings,id'],
            'requested_date'  => ['required', 'date', 'after_or_equal:today'],
            'requested_time'  => ['required'],
            'reason'          => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $booking = Booking::where('id', $validated['booking_id'])
            ->where('customer_user_id', Auth::id())
            ->firstOrFail();

        // Only allow reschedule if booking is reserved or pending
        if (!in_array($booking->status, ['reserved', 'pending'])) {
            return response()->json([
                'message' => 'This booking can no longer be rescheduled.'
            ], 422);
        }

        // Block if there's already a pending request
        $existing = RescheduleRequest::where('booking_id', $booking->id)
            ->where('status', 'pending')
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'You already have a pending reschedule request for this booking.'
            ], 422);
        }

        // Validate operating hours
        $dayOfWeek = Carbon::parse($validated['requested_date'])->format('l');
        $hours = OperatingHours::where('branch_id', $booking->branch_id)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (!$hours || $hours->is_closed) {
            return response()->json([
                'message' => 'The spa is closed on the selected day.'
            ], 422);
        }

        $start   = Carbon::parse($validated['requested_time']);
        $opening = Carbon::parse($hours->opening_time);
        $closing = Carbon::parse($hours->closing_time);

        if ($start->lt($opening) || $start->gte($closing)) {
            return response()->json([
                'message' => "Please select a time within operating hours: {$hours->opening_time} - {$hours->closing_time}"
            ], 422);
        }

        $reschedule = RescheduleRequest::create([
            'booking_id'     => $booking->id,
            'requested_by'   => Auth::id(),
            'requested_date' => $validated['requested_date'],
            'requested_time' => $validated['requested_time'],
            'reason'         => $validated['reason'],
            'status'         => 'pending',
        ]);

        return response()->json([
            'message' => 'Reschedule request submitted successfully. Please wait for approval.',
            'data'    => $reschedule,
        ]);
    }

    // =====================================================
    // OWNER/MANAGER: List all pending reschedule requests
    // =====================================================
    public function index()
    {
        $user     = Auth::user();
        $branchId = $user->currentBranchId() ?? $user->branch_id;

        $requests = RescheduleRequest::with(['booking.spa', 'booking.branch', 'requestedBy'])
            ->whereHas('booking', function ($q) use ($user, $branchId) {
                $q->where('spa_id', $user->spa_id);
                if ($branchId) {
                    $q->where('branch_id', $branchId);
                }
            })
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($r) => $this->formatRequest($r));

        return response()->json($requests);
    }

    // =====================================================
    // OWNER/MANAGER: Approve reschedule request
    // =====================================================
    public function approve(Request $request, RescheduleRequest $rescheduleRequest)
    {
        if (!$rescheduleRequest->isPending()) {
            return response()->json(['message' => 'This request has already been reviewed.'], 422);
        }

        $booking = $rescheduleRequest->booking;

        // Preserve original treatment duration so end_time is always correct
        // after the booking is moved to the new date/time
        $originalStart   = Carbon::parse($booking->start_time);
        $originalEnd     = Carbon::parse($booking->end_time);
        $durationMinutes = $originalStart->diffInMinutes($originalEnd);

        // Build new start and end from the customer's requested time
        $newStart = Carbon::parse($rescheduleRequest->requested_time);
        $newEnd   = $newStart->copy()->addMinutes($durationMinutes);

        // Re-check operating hours at approval time — the requested slot was
        // validated at submission, but re-confirm here too since hours can
        // change between request and review.
        $dayOfWeek = Carbon::parse($rescheduleRequest->requested_date)->format('l');
        $hours = OperatingHours::where('branch_id', $booking->branch_id)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (!$hours || $hours->is_closed) {
            return response()->json([
                'message' => 'The branch is closed on the requested day. Please reject and ask the customer to resubmit.',
            ], 422);
        }

        $closing = Carbon::parse($hours->closing_time);
        if ($newEnd->gt($closing)) {
            return response()->json([
                'message' => 'The requested time would end after closing hours. Please reject and ask the customer to resubmit.',
            ], 422);
        }

        // Re-check therapist availability at approval time — the slot may
        // have filled in the gap between submission and review. Same pattern
        // used in ReassignmentRequestController::approve().
        $conflict = Booking::query()
            ->where('id', '!=', $booking->id)
            ->where('branch_id', $booking->branch_id)
            ->where('appointment_date', $rescheduleRequest->requested_date)
            ->where('therapist_id', $booking->therapist_id)
            ->whereIn('status', ['reserved', 'pending', 'ongoing'])
            ->where(function ($q) use ($newStart, $newEnd) {
                $q->where('start_time', '<', $newEnd->format('H:i:s'))
                  ->where('end_time', '>', $newStart->format('H:i:s'));
            })
            ->exists();

        if ($conflict) {
            return response()->json([
                'message' => 'The assigned therapist already has another appointment at the requested time. Please reject and ask the customer to pick a different time, or reassign the therapist first.',
            ], 422);
        }

        // Update the booking — appointment_date, start_time, AND end_time
        $booking->update([
            'appointment_date' => $rescheduleRequest->requested_date,
            'start_time'       => $newStart->format('H:i:s'),
            'end_time'         => $newEnd->format('H:i:s'),
        ]);

        // Mark the reschedule request as approved
        $rescheduleRequest->update([
            'status'      => 'approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        // Notify the customer by email
        $customer = $rescheduleRequest->requestedBy;
        if ($customer?->email) {
            try {
                Mail::to($customer->email)->send(new RescheduleApprovedMail($rescheduleRequest));
            } catch (\Exception $e) {
                \Log::error('Failed to send reschedule approved email: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Reschedule request approved successfully.']);
    }

    // =====================================================
    // OWNER/MANAGER: Reject reschedule request
    // =====================================================
    public function reject(Request $request, RescheduleRequest $rescheduleRequest)
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if (!$rescheduleRequest->isPending()) {
            return response()->json(['message' => 'This request has already been reviewed.'], 422);
        }

        $rescheduleRequest->update([
            'status'           => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'reviewed_by'      => Auth::id(),
            'reviewed_at'      => now(),
        ]);

        // Send email to customer
        $customer = $rescheduleRequest->requestedBy;
        if ($customer?->email) {
            try {
                Mail::to($customer->email)->send(new RescheduleRejectedMail($rescheduleRequest));
            } catch (\Exception $e) {
                \Log::error('Failed to send reschedule rejected email: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Reschedule request rejected.']);
    }

    // =====================================================
    // CUSTOMER: Get reschedule status for a booking
    // =====================================================
    public function status(Booking $booking)
    {
        $this->authorize('view', $booking);

        $latest = RescheduleRequest::where('booking_id', $booking->id)
            ->latest()
            ->first();

        if (!$latest) {
            return response()->json(['status' => null]);
        }

        return response()->json([
            'status'           => $latest->status,
            'requested_date'   => $latest->requested_date->format('F j, Y'),
            'requested_time'   => Carbon::parse($latest->requested_time)->format('g:i A'),
            'reason'           => $latest->reason,
            'rejection_reason' => $latest->rejection_reason,
        ]);
    }

    // =====================================================
    // Helper
    // =====================================================
    private function formatRequest(RescheduleRequest $r): array
    {
        $booking = $r->booking;

        $treatmentName = $booking->packageRecord
        ? $booking->packageRecord->name . ' (Package)'
        : ($booking->treatmentRecord->name ?? 'Unknown');

        return [
            'id'                  => $r->id,
            'booking_id'          => $booking->id,
            'customer_name'       => $r->requestedBy->name ?? $booking->customer_name,
            'customer_email'      => $r->requestedBy->email ?? $booking->customer_email,
            'spa_name'            => $booking->spa?->name ?? 'N/A',
            'branch_name'         => $booking->branch?->name ?? 'N/A',
            'treatment'           => $treatmentName,
            'original_date'       => $booking->appointment_date->format('F j, Y'),
            'original_time'       => Carbon::parse($booking->start_time)->format('g:i A'),
            'requested_date'      => $r->requested_date->format('F j, Y'),
            'requested_date_raw'  => $r->requested_date->format('Y-m-d'),
            'requested_time'      => Carbon::parse($r->requested_time)->format('g:i A'),
            'reason'              => $r->reason,
            'submitted_at'        => $r->created_at->format('F j, Y g:i A'),
        ];
    }
}
