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

        return response()->json([
            'has_staff' => true,
            'today'     => $this->formatRecord($this->todayFor($staff)),
            'history'   => $history->map(fn ($r) => $this->formatRecord($r))->values(),
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

        $window = StaffAttendance::evaluateClockInWindow($staff->branch_id, $today, $now);
        if (! $window['allowed']) {
            return response()->json(['message' => $window['reason']], 422);
        }

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

        $now  = now()->format('H:i:s');
        $flag = StaffAttendance::flagIfOutsideOperatingHours($staff->branch_id, $today, $now);

        $update = ['time_out' => $now];
        if ($flag) {
            $update['remarks'] = trim(($record->remarks ? $record->remarks . ' | ' : '') . $flag);
        }

        $record->update($update);

        return response()->json([
            'message' => 'Clocked out. See you next shift!',
            'today'   => $this->formatRecord($record->fresh()),
        ]);
    }
}
