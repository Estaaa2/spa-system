<?php

namespace App\Http\Controllers;

use App\Mail\RescheduleApprovedMail;
use App\Mail\RescheduleRejectedMail;
use App\Models\Booking;
use App\Models\OperatingHours;
use App\Models\RescheduleRequest;
use App\Services\Bookings\StaleRequestExpiry;
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

        // Only before the appointment starts (Booking::canRequestReschedule).
        // The app API posts here too, so this covers both.
        if (! $booking->canRequestReschedule()) {
            return response()->json([
                'message' => 'This appointment has already started or ended, so it can no longer be rescheduled. Please call the branch.'
            ], 422);
        }

        // A time earlier today is not a valid new slot.
        if (Carbon::parse($validated['requested_date'].' '.$validated['requested_time'])->lte(now())) {
            return response()->json([
                'message' => 'Please choose a time that hasn\'t passed yet.'
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
            ->open()   // past the original start = expired, never listed (Phase 3, D5)
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
        $this->abortUnlessReviewerScope($rescheduleRequest);

        if (!$rescheduleRequest->isPending()) {
            return response()->json(['message' => 'This request has already been reviewed.'], 422);
        }

        if ($stale = $this->expireIfStale($rescheduleRequest)) {
            return $stale;
        }

        $booking = $rescheduleRequest->booking;

        // The requested slot itself may have passed while the request waited.
        $requestedStart = Carbon::parse($rescheduleRequest->requested_date->toDateString().' '.$rescheduleRequest->requested_time);
        if ($requestedStart->lte(now())) {
            return response()->json([
                'message' => 'The requested date and time have already passed. Please reject and ask the customer to pick a new time.',
            ], 422);
        }

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

        // Re-check the assigned therapist at approval time with the shared rule
        // (TherapistAssigner): on shift for the whole new time, not on leave, no
        // other booking. Off shift or on leave → refuse; staff reassign first
        // (staff scheduling Phase 3, Q4 A). Unassigned bookings skip this.
        if ($booking->therapist_id) {
            $problem = app(\App\Services\Bookings\TherapistAssigner::class)->rejectionFor(
                (int) $booking->therapist_id,
                $booking,
                $rescheduleRequest->requested_date->toDateString(),
                $newStart->format('H:i'),
                $newEnd->format('H:i')
            );

            if ($problem) {
                return response()->json([
                    'message' => $problem.' Reassign the booking to another therapist first, or reject and ask the customer to pick a different time.',
                ], 422);
            }
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

        $this->abortUnlessReviewerScope($rescheduleRequest);

        if (!$rescheduleRequest->isPending()) {
            return response()->json(['message' => 'This request has already been reviewed.'], 422);
        }

        if ($stale = $this->expireIfStale($rescheduleRequest)) {
            return $stale;
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
    /**
     * Two routes reach this: the web one binds {booking}, the app's binds
     * {bookingId}. It used to call authorize('view') with no BookingPolicy
     * defined, so it always answered 403 (found in the lifecycle fix, Phase 3).
     * Now: the customer's own booking only, otherwise 404.
     */
    public function status(Request $request)
    {
        // Untyped on purpose: no implicit binding, so both route shapes give the raw id.
        $id = $request->route('booking') ?? $request->route('bookingId');
        $id = $id instanceof Booking ? $id->id : $id;

        $booking = Booking::whereKey((int) $id)
            ->where('customer_user_id', Auth::id())
            ->firstOrFail();

        $latest = RescheduleRequest::where('booking_id', $booking->id)
            ->latest()
            ->latest('id')
            ->first();

        if (!$latest) {
            return response()->json(['status' => null]);
        }

        // Q1-C: the app is sent `rejected` for an expired request until it knows
        // `expired`; the web reads the real value. `expired` is new and additive.
        $isApi = $request->is('api/*') || $request->routeIs('reschedule.flutter.status');

        return response()->json([
            'status'           => $isApi ? $latest->legacyStatus() : $latest->status,
            'expired'          => $latest->isExpired(),
            'requested_date'   => $latest->requested_date->format('F j, Y'),
            'requested_time'   => Carbon::parse($latest->requested_time)->format('g:i A'),
            'reason'           => $latest->reason,
            'rejection_reason' => $latest->rejection_reason,
        ]);
    }

    // =====================================================
    // Helpers
    // =====================================================

    /**
     * Reviewers act only on requests for bookings of their own spa and
     * current branch (same scope as index()). The permission middleware
     * checks the permission, not the request (lifecycle fix, Phase 3).
     */
    private function abortUnlessReviewerScope(RescheduleRequest $r): void
    {
        $user = Auth::user();
        $branchId = $user->currentBranchId() ?? $user->branch_id;
        $booking = $r->booking;

        abort_unless(
            $booking
            && (int) $booking->spa_id === (int) $user->spa_id
            && (int) $booking->branch_id === (int) $branchId,
            404
        );
    }

    /** D5: too late to answer → expire it now (customer emailed) and say so. */
    private function expireIfStale(RescheduleRequest $r): ?\Illuminate\Http\JsonResponse
    {
        if (! $r->isPastDeadline()) {
            return null;
        }

        app(StaleRequestExpiry::class)->expireReschedule($r);

        return response()->json([
            'message' => 'This request expired: the appointment has already started or is closed, so the original schedule stands.',
        ], 422);
    }

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
