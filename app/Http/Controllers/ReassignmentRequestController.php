<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\LeaveRequest;
use App\Models\Package;
use App\Models\ReassignmentRequest;
use App\Models\Treatment;
use Carbon\Carbon;
use App\Services\Bookings\TherapistAssigner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReassignmentRequestController extends Controller
{
    // =====================================================
    // THERAPIST: Submit reassignment request ("I can't make this")
    // Optionally files a same-day leave request in the same submission
    // (request_leave_too checkbox) so attendance reflects the absence
    // without a second form — linked via leave_request_id.
    // =====================================================
    public function store(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'reason'            => ['required', 'string', 'min:10', 'max:1000'],
            'request_leave_too' => ['nullable', 'boolean'],
            'leave_type'        => ['nullable', 'in:sick,emergency,vacation,personal,other'],
        ]);

        // Only the therapist currently assigned to this booking may flag it —
        // without this check, any therapist could request reassignment on
        // someone else's appointment.
        abort_unless($booking->therapist_id === Auth::id(), 403);

        // "Also request leave" files a leave request, so it needs the same
        // permission as the leave route (Phase 6.1; web and mobile alike).
        if (! empty($validated['request_leave_too']) && ! Auth::user()->hasBranchPermission('create leave requests')) {
            return response()->json([
                'message' => "Your role can't file leave requests. Send the reassignment without leave, or ask your manager.",
            ], 403);
        }

        // Also once it has ended: a pending booking can stay open past its end
        // time now (lifecycle fix), and nobody can reassign a session that's over.
        if (!in_array($booking->status, ['reserved', 'pending']) || $booking->isOverdue()) {
            return response()->json([
                'message' => 'This appointment can no longer be reassigned.',
            ], 422);
        }

        $existing = ReassignmentRequest::where('booking_id', $booking->id)
            ->where('status', 'pending')
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'A reassignment request for this appointment is already pending.',
            ], 422);
        }

        $reassignment = DB::transaction(function () use ($booking, $validated) {
            $leaveRequestId = null;

            if (!empty($validated['request_leave_too'])) {
                $alreadyPendingLeave = LeaveRequest::where('user_id', Auth::id())
                    ->where('status', 'pending')
                    ->exists();

                if (!$alreadyPendingLeave) {
                    $user  = Auth::user();
                    $leave = LeaveRequest::create([
                        'user_id'    => Auth::id(),
                        'spa_id'     => $user->spa_id,
                        'branch_id'  => $booking->branch_id,
                        'leave_type' => $validated['leave_type'] ?? 'sick',
                        'start_date' => $booking->appointment_date,
                        'end_date'   => $booking->appointment_date,
                        'reason'     => $validated['reason'],
                        'status'     => 'pending',
                    ]);

                    $leaveRequestId = $leave->id;
                }
            }

            return ReassignmentRequest::create([
                'booking_id'       => $booking->id,
                'requested_by'     => Auth::id(),
                'old_therapist_id' => $booking->therapist_id,
                'reason'           => $validated['reason'],
                'status'           => 'pending',
                'leave_request_id' => $leaveRequestId,
            ]);
        });

        return response()->json([
            'message' => 'Reassignment request submitted. The front desk has been notified.',
            'data'    => $reassignment,
        ]);
    }

    // =====================================================
    // OWNER / MANAGER / RECEPTIONIST: List pending requests for this branch
    // =====================================================
    public function index()
    {
        $user     = Auth::user();
        $branchId = $user->currentBranchId() ?? $user->branch_id;

        $requests = ReassignmentRequest::with(['booking.spa', 'booking.branch', 'requestedBy', 'oldTherapist', 'leaveRequest'])
            ->whereHas('booking', function ($q) use ($user, $branchId) {
                $q->where('spa_id', $user->spa_id);
                if ($branchId) {
                    $q->where('branch_id', $branchId);
                }
            })
            ->open()   // ended bookings = expired, never listed (Phase 3, D6)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($r) => $this->formatRequest($r));

        return response()->json($requests);
    }

    // =====================================================
    // OWNER / MANAGER / RECEPTIONIST: Approve
    // Accepts either the system-suggested therapist or a manual override —
    // both arrive the same way, as new_therapist_id in the request body.
    // =====================================================
    public function approve(Request $request, ReassignmentRequest $reassignmentRequest)
    {
        $this->abortUnlessReviewerScope($reassignmentRequest);

        if (!$reassignmentRequest->isPending()) {
            return response()->json(['message' => 'This request has already been reviewed.'], 422);
        }

        if ($stale = $this->expireIfStale($reassignmentRequest)) {
            return $stale;
        }

        $booking = $reassignmentRequest->booking;

        $validated = $request->validate([
            'new_therapist_id' => [
                'required',
                Rule::exists('users', 'id')->where(function ($q) use ($booking) {
                    $q->where('branch_id', $booking->branch_id);
                }),
            ],
        ]);

        $newTherapistId = (int) $validated['new_therapist_id'];

        if ($newTherapistId === (int) $reassignmentRequest->old_therapist_id) {
            return response()->json(['message' => 'Choose a different therapist, or reject the request to keep this one.'], 422);
        }

        // Re-check at approval time with the shared rule: in the pool, free at
        // that time, and identity-verified for online home service (D2). The
        // slot may have filled in the gap between submission and review.
        if ($problem = app(TherapistAssigner::class)->rejectionFor($newTherapistId, $booking)) {
            return response()->json(['message' => $problem], 422);
        }

        $booking->update(['therapist_id' => $newTherapistId]);

        $reassignmentRequest->update([
            'new_therapist_id' => $newTherapistId,
            'status'           => 'approved',
            'reviewed_by'      => Auth::id(),
            'reviewed_at'      => now(),
        ]);

        return response()->json(['message' => 'Appointment reassigned successfully.']);
    }

    // =====================================================
    // OWNER / MANAGER / RECEPTIONIST: Reject
    // =====================================================
    public function reject(Request $request, ReassignmentRequest $reassignmentRequest)
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->abortUnlessReviewerScope($reassignmentRequest);

        if (!$reassignmentRequest->isPending()) {
            return response()->json(['message' => 'This request has already been reviewed.'], 422);
        }

        if ($stale = $this->expireIfStale($reassignmentRequest)) {
            return $stale;
        }

        $reassignmentRequest->update([
            'status'           => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'reviewed_by'      => Auth::id(),
            'reviewed_at'      => now(),
        ]);

        return response()->json(['message' => 'Reassignment request rejected.']);
    }

    // =====================================================
    // Helpers
    // =====================================================

    /** Own spa and current branch only (same scope as index(); lifecycle fix, Phase 3). */
    private function abortUnlessReviewerScope(ReassignmentRequest $r): void
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

    /**
     * D6: a booking that ended or is closed can't be reassigned — that would
     * change who it's credited to (commission). Expire it now and say so.
     */
    private function expireIfStale(ReassignmentRequest $r): ?\Illuminate\Http\JsonResponse
    {
        if (! $r->isPastDeadline()) {
            return null;
        }

        app(\App\Services\Bookings\StaleRequestExpiry::class)->expireReassignment($r);

        return response()->json([
            'message' => 'This request expired: the appointment has already ended or is closed, so its therapist stays as recorded.',
        ], 422);
    }

    private function formatRequest(ReassignmentRequest $r): array
    {
        $booking = $r->booking;

        $treatmentName = 'Unknown';
        if (str_starts_with($booking->treatment, 'treatment_')) {
            $id = (int) str_replace('treatment_', '', $booking->treatment);
            $treatment = Treatment::withoutGlobalScopes()->find($id);
            $treatmentName = $treatment?->name ?? 'Unknown Treatment';
        } elseif (str_starts_with($booking->treatment, 'package_')) {
            $id = (int) str_replace('package_', '', $booking->treatment);
            $package = Package::withoutGlobalScopes()->find($id);
            $treatmentName = $package ? $package->name . ' (Package)' : 'Unknown Package';
        }

        return [
            'id'                    => $r->id,
            'booking_id'            => $booking->id,
            'customer_name'         => $booking->customer_name,
            'treatment'             => $treatmentName,
            'treatment_code'        => $booking->treatment, // raw "treatment_5" / "package_2" — needed by the availability endpoint
            'appointment_date'      => $booking->appointment_date->format('F j, Y'),
            'appointment_date_raw'  => $booking->appointment_date->format('Y-m-d'),
            'start_time'            => $booking->start_time,
            'start_time_fmt'        => Carbon::parse($booking->start_time)->format('g:i A'),
            'old_therapist_id'      => $r->old_therapist_id,
            'old_therapist'         => trim(($r->oldTherapist->first_name ?? '') . ' ' . ($r->oldTherapist->last_name ?? '')),
            'reason'                => $r->reason,
            'submitted_at'          => $r->created_at->format('F j, Y g:i A'),
            'leave_request_id'      => $r->leave_request_id,
            'leave_is_approved'     => $r->leaveRequest?->isApproved() ?? false,
            'source'                => $r->source,
            // Online home service: the replacement must be identity-verified.
            'requires_verified'     => TherapistAssigner::requiresVerified($booking->booking_source, $booking->service_type),
        ];
    }
}