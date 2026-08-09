<?php

namespace App\Http\Controllers;

use App\Mail\LeaveApprovedMail;
use App\Mail\LeaveRejectedMail;
use App\Models\Booking;
use App\Models\LeaveRequest;
use App\Models\ReassignmentRequest;
use App\Models\Staff;
use App\Models\StaffAttendance;
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

        return response()->json([
            'message' => 'Leave request submitted. Please wait for approval.',
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
    // OWNER/MANAGER/HR: Approve
    // =====================================================
    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        if (!$leaveRequest->isPending()) {
            return response()->json(['message' => 'This request has already been reviewed.'], 422);
        }

        DB::transaction(function () use ($leaveRequest) {
            $leaveRequest->update([
                'status'      => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            $this->syncAttendanceForLeave($leaveRequest);
            $this->autoFlagAffectedBookings($leaveRequest);
        });

        $leaveUser = $leaveRequest->user;
        if ($leaveUser?->email) {
            try {
                Mail::to($leaveUser->email)->send(new LeaveApprovedMail($leaveRequest));
            } catch (\Exception $e) {
                Log::error('Failed to send leave approved email: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Leave request approved.']);
    }

    // =====================================================
    // OWNER/MANAGER/HR: Reject
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
    // Side effect 1: mark the covered dates on_leave in attendance so
    // nobody has to double-enter the same absence twice.
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
    // Side effect 2: if the person is a therapist, auto-create a pending
    // ReassignmentRequest for every upcoming booking of theirs inside the
    // leave window, so it appears in the EXISTING "Reassignment Requested"
    // panel on Appointments — no separate leave-side reassignment UI.
    //
    // NOTE ON UNCERTAINTY: I have not seen ReassignmentRequest.php or its
    // migration, so I'm inferring its fillable fields (booking_id,
    // requested_by, old_therapist_id, reason, status) purely from how
    // ReassignmentRequestController::store() already uses them. That part
    // matches an existing, working code path, so it should be safe. The
    // leave_request_id assignment below is done via direct property + save()
    // specifically so it works even if you haven't added it to $fillable yet
    // (see setup step 5 in the apply plan).
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

            $rr = ReassignmentRequest::create([
                'booking_id'       => $booking->id,
                'requested_by'     => $leave->user_id,
                'old_therapist_id' => $booking->therapist_id,
                'reason'           => 'Auto-generated from approved leave (' . $leave->leave_type . '): ' . $leave->reason,
                'status'           => 'pending',
            ]);

            $rr->leave_request_id = $leave->id;
            $rr->save();
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