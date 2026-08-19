<?php

namespace App\Http\Controllers;

use App\Mail\LeaveApprovedMail;
use App\Mail\LeaveRejectedMail;
use App\Models\Booking;
use App\Models\LeaveRequest;
use App\Models\ReassignmentRequest;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LeaveRequestController extends Controller
{
    // =====================================================
    // STAFF: Submit a leave request
    // =====================================================
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'leave_type' => ['required', 'in:sick,emergency,vacation,personal,other'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:' . now()->addDays(60)->toDateString()],
            'reason'     => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $existing = LeaveRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'You already have a pending leave request. Please wait for it to be reviewed.',
            ], 422);
        }

        $leave = LeaveRequest::create([
            'user_id'    => $user->id,
            'spa_id'     => $user->spa_id,
            'branch_id'  => $user->currentBranchId() ?? $user->branch_id,
            'leave_type' => $validated['leave_type'],
            'start_date' => $validated['start_date'],
            'end_date'   => $validated['end_date'],
            'reason'     => $validated['reason'],
            'status'     => 'pending',
        ]);

        $message = 'Leave request submitted. Please wait for approval.';

        if ($user->hasRole('therapist')) {
            $message .= ' Any of your appointments that fall within these dates will be automatically flagged for reassignment once this leave is approved — the front desk will handle finding a replacement.';
        }

        return response()->json([
            'message' => $message,
            'data'    => $leave,
        ]);
    }

    // =====================================================
    // STAFF: My own leave request history (poll target)
    // =====================================================
    public function mine()
    {
        $requests = LeaveRequest::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($r) => $this->formatRequest($r));

        return response()->json($requests);
    }

    // =====================================================
    // OWNER/MANAGER/HR: Pending queue for this branch (poll target)
    // =====================================================
    public function index()
    {
        $user     = Auth::user();
        $branchId = $user->currentBranchId() ?? $user->branch_id;

        $requests = LeaveRequest::with('user')
            ->where('spa_id', $user->spa_id)
            ->where('branch_id', $branchId)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($r) => $this->formatRequest($r));

        return response()->json($requests);
    }

    // =====================================================
    // OWNER/MANAGER/HR: Get all bookings affected by a leave request.
    // =====================================================
    public function affectedBookings(LeaveRequest $leaveRequest)
    {
        $user        = Auth::user();
        $canReassign = $user->hasBranchPermission('edit leave requests');

        abort_unless($leaveRequest->user_id === $user->id || $canReassign, 403);

        if (!$leaveRequest->user?->hasRole('therapist')) {
            return response()->json([]);
        }

        $bookings = Booking::where('spa_id', $leaveRequest->spa_id)
            ->where('branch_id', $leaveRequest->branch_id)
            ->where('therapist_id', $leaveRequest->user_id)
            ->whereIn('appointment_date', $leaveRequest->dateRange())
            ->whereIn('status', ['reserved', 'pending'])
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get();

        return response()->json($bookings->map(function ($b) use ($canReassign) {
            $data = [
                'id'               => $b->id,
                'customer_name'    => $b->customer_name ?? 'Walk-in Customer',
                'appointment_date' => $b->appointment_date->format('M j, Y'),
                'start_time_fmt'   => \Carbon\Carbon::parse($b->start_time)->format('g:i A'),
            ];

            if (!$canReassign) {
                return $data;
            }

            $therapists = User::role('therapist')
                ->whereHas('staff', fn ($q) => $q
                    ->where('spa_id', $b->spa_id)
                    ->where('branch_id', $b->branch_id)
                    ->where('employment_status', 'active'))
                ->where('id', '!=', $b->therapist_id)
                ->orderBy('first_name')
                ->get();

            $busyIds = Booking::where('id', '!=', $b->id)
                ->where('branch_id', $b->branch_id)
                ->where('appointment_date', $b->appointment_date)
                ->whereIn('therapist_id', $therapists->pluck('id'))
                ->whereIn('status', ['reserved', 'pending', 'ongoing'])
                ->where(fn ($q) => $q->where('start_time', '<', $b->end_time)->where('end_time', '>', $b->start_time))
                ->pluck('therapist_id');

            $onLeaveIds = LeaveRequest::approvedUserIdsOnDate($b->spa_id, $b->branch_id, $b->appointment_date->format('Y-m-d'));

            $available = $therapists
                ->reject(fn ($t) => $busyIds->contains($t->id) || in_array($t->id, $onLeaveIds))
                ->values();

            $data['available_therapists'] = $available->map(fn ($t) => [
                'id'   => $t->id,
                'name' => trim($t->first_name . ' ' . $t->last_name),
            ]);
            $data['recommended_id'] = $available->first()?->id;

            return $data;
        }));
    }

    // =====================================================
    // OWNER/MANAGER/HR: Approve and optionally reassign affected bookings.
    // =====================================================
    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        if (!$leaveRequest->isPending()) {
            return response()->json(['message' => 'This request has already been reviewed.'], 422);
        }

        $validated = $request->validate([
            'reassignments'                    => ['nullable', 'array'],
            'reassignments.*.booking_id'       => ['required_with:reassignments', 'integer'],
            'reassignments.*.new_therapist_id' => ['required_with:reassignments', 'integer'],
        ]);

        $resolvedCount = 0;

        DB::transaction(function () use ($leaveRequest, $validated, &$resolvedCount) {
            $leaveRequest->update([
                'status'      => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            $this->syncAttendanceForLeave($leaveRequest);
            $this->autoFlagAffectedBookings($leaveRequest);

            foreach ($validated['reassignments'] ?? [] as $pick) {
                $reassignment = ReassignmentRequest::where('booking_id', $pick['booking_id'])
                    ->where('status', 'pending')
                    ->first();

                if (!$reassignment) {
                    continue; // already resolved another way, or not actually flagged
                }

                $booking        = $reassignment->booking;
                $newTherapistId = (int) $pick['new_therapist_id'];

                $conflict = Booking::where('id', '!=', $booking->id)
                    ->where('branch_id', $booking->branch_id)
                    ->where('appointment_date', $booking->appointment_date)
                    ->where('therapist_id', $newTherapistId)
                    ->whereIn('status', ['reserved', 'pending', 'ongoing'])
                    ->where(fn ($q) => $q->where('start_time', '<', $booking->end_time)->where('end_time', '>', $booking->start_time))
                    ->exists();

                if ($conflict) {
                    continue; // leave it pending — approver resolves manually on Appointments
                }

                $booking->update(['therapist_id' => $newTherapistId]);
                $reassignment->update([
                    'new_therapist_id' => $newTherapistId,
                    'status'           => 'approved',
                    'reviewed_by'      => Auth::id(),
                    'reviewed_at'      => now(),
                ]);
                $resolvedCount++;
            }
        });

        $leaveUser = $leaveRequest->user;
        if ($leaveUser?->email) {
            try {
                Mail::to($leaveUser->email)->send(new LeaveApprovedMail($leaveRequest));
            } catch (\Exception $e) {
                Log::error('Failed to send leave approved email: ' . $e->getMessage());
            }
        }

        $message = 'Leave request approved.';
        if ($resolvedCount > 0) {
            $message .= " {$resolvedCount} affected appointment(s) reassigned automatically.";
        }

        return response()->json(['message' => $message]);
    }

    // =====================================================
    // OWNER/MANAGER/HR: Reject.
    // =====================================================
    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if (!$leaveRequest->isPending()) {
            return response()->json(['message' => 'This request has already been reviewed.'], 422);
        }

        $leaveRequest->update([
            'status'           => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'reviewed_by'      => Auth::id(),
            'reviewed_at'      => now(),
        ]);

        $leaveUser = $leaveRequest->user;
        if ($leaveUser?->email) {
            try {
                Mail::to($leaveUser->email)->send(new LeaveRejectedMail($leaveRequest));
            } catch (\Exception $e) {
                Log::error('Failed to send leave rejected email: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Leave request rejected.']);
    }

    // =====================================================
    // OWNER/MANAGER/HR: Sync attendance records for approved leave.
    // =====================================================
    private function syncAttendanceForLeave(LeaveRequest $leave): void
    {
        $staff = Staff::where('user_id', $leave->user_id)
            ->where('spa_id', $leave->spa_id)
            ->where('branch_id', $leave->branch_id)
            ->first();

        if (!$staff) {
            return;
        }

        foreach ($leave->dateRange() as $date) {
            StaffAttendance::updateOrCreate(
                ['staff_id' => $staff->id, 'date' => $date],
                [
                    'spa_id'    => $leave->spa_id,
                    'branch_id' => $leave->branch_id,
                    'status'    => 'on_leave',
                    'source'    => 'system',
                    'marked_by' => Auth::id(),
                ]
            );
        }
    }

    // =====================================================
    // OWNER/MANAGER/HR: Auto-flag affected bookings for reassignment.
    // =====================================================
    private function autoFlagAffectedBookings(LeaveRequest $leave): void
    {
        if (!$leave->user?->hasRole('therapist')) {
            return;
        }

        $bookings = Booking::where('spa_id', $leave->spa_id)
            ->where('branch_id', $leave->branch_id)
            ->where('therapist_id', $leave->user_id)
            ->whereIn('appointment_date', $leave->dateRange())
            ->whereIn('status', ['reserved', 'pending'])
            ->get();

        foreach ($bookings as $booking) {
            $alreadyFlagged = ReassignmentRequest::where('booking_id', $booking->id)
                ->whereIn('status', ['pending', 'approved'])
                ->exists();

            if ($alreadyFlagged) {
                continue;
            }

            ReassignmentRequest::create([
                'booking_id'       => $booking->id,
                'requested_by'     => $leave->user_id,
                'old_therapist_id' => $booking->therapist_id,
                'reason'           => 'Auto-generated from approved leave (' . $leave->leave_type . '): ' . $leave->reason,
                'status'           => 'pending',
                'leave_request_id' => $leave->id,
            ]);
        }
    }

    // =====================================================
    // Helper
    // =====================================================
    private function formatRequest(LeaveRequest $r): array
    {
        return [
            'id'               => $r->id,
            'user_name'        => trim(($r->user->first_name ?? '') . ' ' . ($r->user->last_name ?? '')),
            'role'             => $r->user?->getRoleNames()->first() ?? 'N/A',
            'leave_type'       => $r->leave_type,
            'start_date'       => $r->start_date->format('M j, Y'),
            'end_date'         => $r->end_date->format('M j, Y'),
            'days'             => count($r->dateRange()),
            'reason'           => $r->reason,
            'status'           => $r->status,
            'rejection_reason' => $r->rejection_reason,
            'submitted_at'     => $r->created_at->format('M j, Y g:i A'),
        ];
    }
}