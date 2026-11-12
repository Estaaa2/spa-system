<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffAttendance;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The web's "My Attendance" for mobile. Same rules as
 * HR\AttendanceController (clockIn / clockOut), using the same
 * StaffAttendance helpers, but answering in JSON instead of redirects.
 */
class AttendanceController extends Controller
{
    /** Same lookup as HR\AttendanceController::myStaffRecord(). */
    private function myStaffRecord(Request $request): ?Staff
    {
        $user = $request->user();

        return Staff::where('user_id', $user->id)
            ->where('spa_id', $user->spa_id)
            ->where('employment_status', 'active')
            ->first();
    }

    private function formatRecord(?StaffAttendance $r): ?array
    {
        if (! $r) {
            return null;
        }

        return [
            'id'           => $r->id,
            'date'         => $r->date->toDateString(),
            'date_label'   => $r->date->format('D, M j'),
            'status'       => $r->status,
            'status_label' => ucfirst(str_replace('_', ' ', $r->status)),
            'time_in'      => $r->time_in ? Carbon::parse($r->time_in)->format('h:i A') : null,
            'time_out'     => $r->time_out ? Carbon::parse($r->time_out)->format('h:i A') : null,
            'auto_closed'  => (bool) $r->auto_closed,
            'auto_flag'    => $r->remarks && str_contains($r->remarks, 'Auto-flag'),
            'remarks'      => $r->remarks,
        ];
    }

    private function todayFor(Staff $staff): ?StaffAttendance
    {
        return StaffAttendance::where('staff_id', $staff->id)
            ->whereDate('date', today())
            ->first();
    }

    /**
     * GET /api/therapist/attendance
     * Today's record and the last 14 days, like the web page.
     */
    public function myAttendance(Request $request)
    {
        $staff = $this->myStaffRecord($request);

        if (! $staff) {
            return response()->json(['has_staff' => false, 'today' => null, 'history' => []]);
        }

        $history = StaffAttendance::where('staff_id', $staff->id)
            ->where('date', '>=', now()->subDays(13)->toDateString())
            ->orderByDesc('date')
            ->get();

        // Staff scheduling Phase 2 (additive, D7): my next 7 days and rest days.
        $resolver = app(\App\Services\Staff\WorkScheduleResolver::class);

        return response()->json([
            'has_staff' => true,
            'today'     => $this->formatRecord($this->todayFor($staff)),
            'history'   => $history->map(fn ($r) => $this->formatRecord($r))->values(),
            'schedule'  => [
                'has_schedule' => $resolver->scheduleOn($staff, today()->toDateString()) !== null,
                'rest_days'    => $resolver->scheduleOn($staff, today()->toDateString())?->restDays() ?? [],
                'week'         => array_map(
                    fn ($d) => $d->toArray(),
                    $resolver->forRange($staff, today()->toDateString(), today()->addDays(6)->toDateString())
                ),
            ],
        ]);
    }

    /** POST /api/therapist/attendance/clock-in: same checks and messages as the web. */
    public function clockIn(Request $request)
    {
        $staff = $this->myStaffRecord($request);

        if (! $staff) {
            return response()->json(['message' => 'No active staff profile is linked to your account.'], 422);
        }

        $today    = today()->toDateString();
        $existing = $this->todayFor($staff);

        if ($existing && $existing->time_in) {
            return response()->json(['message' => 'You are already clocked in for today.'], 422);
        }

        $now = now()->format('H:i:s');

        // Shift-based rules (staff scheduling Phase 4): refused with no shift today;
        // window = shift start − grace … shift end; late after start + grace.
        $window = app(\App\Services\Attendance\ClockInRules::class)->clockIn($staff, $today, $now);
        if (! $window['allowed']) {
            return response()->json(['message' => $window['reason']], 422);
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

        return response()->json([
            'message' => $status === 'late'
                ? 'Clocked in — marked late.'
                : 'Clocked in. Have a great shift!',
            'today' => $this->formatRecord($this->todayFor($staff)),
        ]);
    }

    /** POST /api/therapist/attendance/clock-out: same checks and messages as the web. */
    public function clockOut(Request $request)
    {
        $staff = $this->myStaffRecord($request);

        if (! $staff) {
            return response()->json(['message' => 'No active staff profile is linked to your account.'], 422);
        }

        $today  = today()->toDateString();
        $record = $this->todayFor($staff);

        if (! $record || ! $record->time_in) {
            return response()->json(['message' => 'You need to clock in before you can clock out.'], 422);
        }

        if ($record->time_out) {
            return response()->json(['message' => 'You are already clocked out for today.'], 422);
        }

        $now   = now()->format('H:i:s');
        $rules = app(\App\Services\Attendance\ClockInRules::class);

        // Phase 2, Q3 A: `reason` is optional on the API (older app versions don't
        // send it); an early clock-out without one is remarked "no reason given, mobile".
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $remarks = array_filter([
            $record->remarks,
            $rules->clockOutFlag($staff, $today, $now),
            $rules->earlyClockOutRemark($staff, $today, $now, $request->input('reason'), true),
        ]);

        $record->update(['time_out' => $now, 'remarks' => $remarks ? mb_substr(implode(' | ', $remarks), 0, 500) : null]);

        // D4: open bookings left today go to Reassignment Requests.
        $flagged = app(\App\Services\Bookings\AttendanceBookingFlags::class)->sync($staff, $today);

        return response()->json([
            'message' => 'Clocked out. ' . \App\Http\Controllers\HR\AttendanceController::flaggedSentence($flagged) . 'See you next shift!',
            'today'   => $this->formatRecord($record->fresh()),
            'flagged' => $flagged,
        ]);
    }

    /**
     * GET /api/therapist/attendance/clock-preview?action=in|out
     * Same data as the web confirmation modals (Phase 2, D9). Display only.
     */
    public function clockPreview(Request $request)
    {
        $request->validate(['action' => ['required', 'in:in,out']]);
        $staff = $this->myStaffRecord($request);

        if (! $staff) {
            return response()->json(['message' => 'No active staff profile is linked to your account.'], 422);
        }

        return response()->json(app(\App\Services\Attendance\ClockPreview::class)->self($staff, $request->input('action')));
    }
}
