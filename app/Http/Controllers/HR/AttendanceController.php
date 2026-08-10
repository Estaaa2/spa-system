<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Models\StaffAttendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    private function getSpaAndBranch()
    {
        $user     = Auth::user();
        $spa      = $user->spa;
        $branchId = $user->currentBranchId();
        return [$spa, $branchId];
    }

    /** The Staff record tied to the logged-in user, if they have one. */
    private function myStaffRecord(): ?Staff
    {
        $user = Auth::user();

        return Staff::where('user_id', $user->id)
            ->where('spa_id', $user->spa_id)
            ->where('employment_status', 'active')
            ->first();
    }

    public function index(Request $request)
    {
        [$spa, $branchId] = $this->getSpaAndBranch();
        $user = Auth::user();

        $canViewRoster   = $user->hasBranchPermission('view attendance');
        $canEditRoster   = $user->hasBranchPermission('edit attendance');
        $canApproveLeave = $user->hasBranchPermission('edit leave requests');

        $date = $request->date ? Carbon::parse($request->date) : today();

        // ── Team roster — only for roles with view attendance ──────────────
        $staffList = collect();
        if ($canViewRoster) {
            $staffList = Staff::with(['user', 'attendance' => function ($query) use ($date) {
                $query->whereDate('date', $date);
            }])
                ->where('spa_id', $spa->id)
                ->where('branch_id', $branchId)
                ->where('employment_status', 'active')
                ->get();
        }

        // ── Attendance summary for the selected date (present/absent/late) ─
        $summary = [
            'present' => 0,
            'absent'  => 0,
            'late'    => 0,
        ];

        foreach ($staffList as $staff) {
            $record = $staff->attendance->first();

            if ($record && isset($summary[$record->status])) {
                $summary[$record->status]++;
            }
        }

        // ── My own attendance — every staff member, regardless of role ─────
        $myStaff   = $this->myStaffRecord();
        $myToday   = null;
        $myHistory = collect();

        if ($myStaff) {
            $myToday = StaffAttendance::where('staff_id', $myStaff->id)
                ->whereDate('date', today())
                ->first();

            $myHistory = StaffAttendance::where('staff_id', $myStaff->id)
                ->where('date', '>=', now()->subDays(13)->toDateString())
                ->orderByDesc('date')
                ->get();
        }

        // ── My leave request history ────────────────────────────────────
        $myLeaveRequests = LeaveRequest::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('hr.attendance.index', compact(
            'staffList', 'date', 'summary', 'canViewRoster', 'canEditRoster', 'canApproveLeave',
            'myStaff', 'myToday', 'myHistory', 'myLeaveRequests'
        ));
    }

    // =====================================================
    // SELF-SERVICE: clock in / clock out
    // Identity-based, not permission-gated — every staff member manages
    // their own record regardless of role.
    // =====================================================

    public function clockIn(Request $request)
    {
        $staff = $this->myStaffRecord();

        if (!$staff) {
            return back()->with('error', 'No active staff profile is linked to your account.');
        }

        $today    = today()->toDateString();
        $existing = StaffAttendance::where('staff_id', $staff->id)->whereDate('date', $today)->first();

        if ($existing && $existing->time_in) {
            return back()->with('error', 'You are already clocked in for today.');
        }

        $now    = now()->format('H:i:s');
        $status = StaffAttendance::resolveStatusForClockIn($staff->branch_id, $today, $now);

        StaffAttendance::updateOrCreate(
            ['staff_id' => $staff->id, 'date' => $today],
            [
                'spa_id'    => $staff->spa_id,
                'branch_id' => $staff->branch_id,
                'status'    => $status,
                'time_in'   => $now,
                'source'    => 'self',
            ]
        );

        return back()->with('success', $status === 'late'
            ? 'Clocked in — marked late.'
            : 'Clocked in. Have a great shift!');
    }

    public function clockOut(Request $request)
    {
        $staff = $this->myStaffRecord();

        if (!$staff) {
            return back()->with('error', 'No active staff profile is linked to your account.');
        }

        $today  = today()->toDateString();
        $record = StaffAttendance::where('staff_id', $staff->id)->whereDate('date', $today)->first();

        if (!$record || !$record->time_in) {
            return back()->with('error', 'You need to clock in before you can clock out.');
        }

        if ($record->time_out) {
            return back()->with('error', 'You are already clocked out for today.');
        }

        $record->update(['time_out' => now()->format('H:i:s')]);

        return back()->with('success', 'Clocked out. See you next shift!');
    }

    // =====================================================
    // MANAGER / RECEPTION / HR / OWNER: record or correct ONE staff
    // member's day at a time. Deliberately not a bulk "save the whole
    // roster" action — that was the old page's silent-default-to-present
    // bug. Every save here is an explicit, reviewed action on one person.
    // =====================================================

    public function recordFor(Request $request, Staff $staff)
    {
        $user = Auth::user();
        abort_unless($user->hasBranchPermission('edit attendance'), 403);
        abort_unless(
            $staff->spa_id === $user->spa_id && $staff->branch_id === ($user->currentBranchId() ?? $user->branch_id),
            403
        );

        $validated = $request->validate([
            'date'     => ['required', 'date'],
            'status'   => ['required', Rule::in(['present', 'late', 'absent', 'on_leave'])],
            'time_in'  => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i', 'after:time_in'],
            'remarks'  => ['nullable', 'string', 'max:500'],
        ]);

        StaffAttendance::updateOrCreate(
            ['staff_id' => $staff->id, 'date' => $validated['date']],
            [
                'spa_id'      => $staff->spa_id,
                'branch_id'   => $staff->branch_id,
                'status'      => $validated['status'],
                'time_in'     => $validated['time_in'] ?? null,
                'time_out'    => $validated['time_out'] ?? null,
                'remarks'     => $validated['remarks'] ?? null,
                'marked_by'   => $user->id,
                'source'      => 'manual',
                'auto_closed' => false,
            ]
        );

        return back()->with('success', 'Attendance record saved for ' . ($staff->user->name ?? 'staff member') . '.');
    }
}
