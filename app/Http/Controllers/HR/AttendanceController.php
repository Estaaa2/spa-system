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
        $canRequestLeave = $user->hasBranchPermission('create leave requests');

        // The roster is for today or the past; a future date has nothing to show or record.
        try {
            $date = $request->date ? Carbon::parse($request->date)->startOfDay() : today();
        } catch (\Throwable) {
            $date = today();
        }
        if ($date->isFuture()) {
            $date = today();
        }

        // ── Team roster — only for roles with view attendance ──────────────
        $staffList = collect();
        $scheduledByStaff = [];
        if ($canViewRoster) {
            $staffList = Staff::with(['user.roles', 'attendance' => function ($query) use ($date) {
                $query->whereDate('date', $date)->with('markedBy:id,first_name,middle_name,last_name,suffix');
            }])
                ->where('spa_id', $spa->id)
                ->where('branch_id', $branchId)
                ->where('employment_status', 'active')
                ->get();

            // Scheduled vs actual (staff scheduling Phase 4): each person's
            // schedule for the roster date at the branch of their record (or this one).
            $resolver = app(\App\Services\Staff\WorkScheduleResolver::class);
            $resolver->prime($staffList, $date->toDateString(), $date->toDateString());
            foreach ($staffList as $member) {
                $rowBranch = (int) ($member->attendance->first()?->branch_id ?? $branchId);
                $scheduledByStaff[$member->id] = $resolver->forDate($member, $date->toDateString(), $rowBranch);
            }
        }

        // ── My own attendance — every staff member, regardless of role ─────
        $myStaff   = $this->myStaffRecord();
        $myToday   = null;
        $myHistory = collect();
        $myWeek    = [];   // staff scheduling Phase 2: my next 7 days (Q7 A)
        $myRestDays = null;
        $myShiftToday = null;   // Phase 4: today's shift on the clock-in card

        if ($myStaff) {
            $myToday = StaffAttendance::where('staff_id', $myStaff->id)
                ->whereDate('date', today())
                ->first();

            $myHistory = StaffAttendance::where('staff_id', $myStaff->id)
                ->where('date', '>=', now()->subDays(13)->toDateString())
                ->orderByDesc('date')
                ->get();

            $resolver = app(\App\Services\Staff\WorkScheduleResolver::class);
            $myWeek = $resolver->forRange($myStaff, today()->toDateString(), today()->addDays(6)->toDateString());
            $myRestDays = $resolver->scheduleOn($myStaff, today()->toDateString())?->restDays();
            $myShiftToday = $myWeek[0] ?? null;
        }

        // ── My leave request history ────────────────────────────────────
        $myLeaveRequests = LeaveRequest::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('hr.attendance.index', compact(
            'staffList', 'date', 'canViewRoster', 'canEditRoster', 'canApproveLeave', 'canRequestLeave',
            'myStaff', 'myToday', 'myHistory', 'myLeaveRequests', 'myWeek', 'myRestDays',
            'scheduledByStaff', 'myShiftToday'
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

        $now = now()->format('H:i:s');

        // Shift-based rules (staff scheduling Phase 4): refused with no shift today;
        // window = shift start − grace … shift end; late after start + grace.
        $window = app(\App\Services\Attendance\ClockInRules::class)->clockIn($staff, $today, $now);
        if (!$window['allowed']) {
            return back()->with('error', $window['reason']);
        }

        $status = $window['status'];

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

        $now   = now()->format('H:i:s');
        $rules = app(\App\Services\Attendance\ClockInRules::class);

        // Phase 2, Q2 A: leaving more than the grace before the shift ends needs a
        // reason (re-checked here; the modal asks first).
        $reason = trim((string) $request->input('reason', ''));
        if ($rules->clockOutTiming($staff, $today, $now)['kind'] === 'early' && $reason === '') {
            return back()->with('error', 'Please give a reason for clocking out early.');
        }
        if (mb_strlen($reason) > 255) {
            return back()->with('error', 'Keep the reason under 255 characters.');
        }

        $remarks = array_filter([
            $record->remarks,
            $rules->clockOutFlag($staff, $today, $now),
            $rules->earlyClockOutRemark($staff, $today, $now, $reason),
        ]);

        $record->update(['time_out' => $now, 'remarks' => $remarks ? mb_substr(implode(' | ', $remarks), 0, 500) : null]);

        // D4: open bookings left today go to Reassignment Requests.
        $flagged = app(\App\Services\Bookings\AttendanceBookingFlags::class)->sync($staff, $today);

        return back()->with('success', 'Clocked out. ' . self::flaggedSentence($flagged) . 'See you next shift!');
    }

    /** "2 bookings were sent to the front desk for reassignment. " or "". */
    public static function flaggedSentence(int $flagged): string
    {
        return $flagged > 0
            ? $flagged . ' ' . ($flagged === 1 ? 'booking was' : 'bookings were') . ' sent to the front desk for reassignment. '
            : '';
    }

    /**
     * GET /attendance/clock-preview?action=in|out — what clocking in / out now
     * would record (Phase 2, D9). Display only; the submit re-checks.
     */
    public function clockPreview(Request $request)
    {
        $request->validate(['action' => ['required', Rule::in(['in', 'out'])]]);
        $staff = $this->myStaffRecord();

        if (!$staff) {
            return response()->json(['message' => 'No active staff profile is linked to your account.'], 422);
        }

        return response()->json(app(\App\Services\Attendance\ClockPreview::class)->self($staff, $request->string('action')->toString()));
    }

    /**
     * GET /attendance/{staff}/record-preview — the manual record's warnings for
     * the entered values (Phase 2, D9): shift, early/late, bookings handed off.
     */
    public function recordPreview(Request $request, Staff $staff)
    {
        $this->authorizeRecord($staff);

        $v = $request->validate([
            'date'     => ['required', 'date', 'before_or_equal:today'],
            'status'   => ['required', Rule::in(['present', 'late', 'absent', 'on_leave'])],
            'time_in'  => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i'],
        ]);

        return response()->json(app(\App\Services\Attendance\ClockPreview::class)->manual(
            $staff,
            Carbon::parse($v['date'])->toDateString(),
            $v['status'],
            $v['time_in'] ?? null,
            $v['time_out'] ?? null,
        ));
    }

    private function authorizeRecord(Staff $staff): void
    {
        $user = Auth::user();
        abort_unless($user->hasBranchPermission('edit attendance'), 403);
        abort_unless(
            $staff->spa_id === $user->spa_id && $staff->branch_id === ($user->currentBranchId() ?? $user->branch_id),
            403
        );
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
        $this->authorizeRecord($staff);

        // Shown as a toast: this page displays flash messages, not field errors
        // (Phase 1 report, proposed item 1).
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'date'     => ['required', 'date', 'before_or_equal:today'],
            'status'   => ['required', Rule::in(['present', 'late', 'absent', 'on_leave'])],
            'time_in'  => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i', 'after:time_in'],
            'remarks'  => ['nullable', 'string', 'max:500'],
        ], [
            'date.before_or_equal' => 'Attendance can only be recorded for today or an earlier day.',
            'time_out.after'       => 'Time out must be later than time in.',
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('error', $validator->errors()->first());
        }
        $validated = $validator->validated();

        // Absent and on-leave days have no clock times; drop any that were sent.
        if (in_array($validated['status'], ['absent', 'on_leave'], true)) {
            $validated['time_in'] = null;
            $validated['time_out'] = null;
        }

        // Today: no time later than now (attendance-aware booking Phase 1, F5).
        // A future time out would hide the person from bookings before they leave.
        // Minute precision, so typing the current minute is accepted.
        if (\Carbon\Carbon::parse($validated['date'])->isToday()) {
            $nowMinute = now()->format('H:i');
            $future = array_filter([
                'time_in'  => 'Time in',
                'time_out' => 'Time out',
            ], fn ($label, $field) => ! empty($validated[$field]) && $validated[$field] > $nowMinute, ARRAY_FILTER_USE_BOTH);

            if ($future) {
                // A toast: this page shows flash messages, not field errors.
                return back()->with('error', implode(' and ', $future)
                    . " can't be later than the current time (" . now()->format('g:i A') . ').');
            }
        }

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

        // D4: today's open bookings go to Reassignment Requests when this record
        // says the person left (time out) or isn't here (absent / on leave); a
        // correction that makes them available again closes those requests.
        $date = Carbon::parse($validated['date'])->toDateString();
        $flagged = app(\App\Services\Bookings\AttendanceBookingFlags::class)->sync($staff, $date);

        return back()->with('success', trim('Attendance record saved for ' . ($staff->user->name ?? 'staff member') . '. '
            . self::flaggedSentence($flagged)));
    }
}