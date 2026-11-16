<?php

namespace App\Services\Attendance;

use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Services\Staff\ScheduledDay;
use App\Services\Staff\WorkScheduleResolver;
use Carbon\Carbon;

/**
 * Self clock-ins made long after the shift start (clock-in guard, Nov 16):
 * the person was late, or forgot to clock in. Roles with "edit attendance"
 * review them on the Dashboard and the Attendance roster. Saving the record
 * through Record / Review (source = manual) clears the flag, so no new column
 * is needed.
 */
class LateClockInReview
{
    public function __construct(private readonly WorkScheduleResolver $schedules)
    {
    }

    public static function threshold(): int
    {
        return max(1, (int) config('attendance.review_after_minutes', 30));
    }

    /** Minutes after shift start when this record needs review, else null. */
    public static function minutesLate(?StaffAttendance $record, ?ScheduledDay $shift): ?int
    {
        if (! $record || $record->source !== 'self' || ! $record->time_in) {
            return null;
        }
        if (! $shift || ! $shift->isWorking() || $shift->start === null) {
            return null;
        }

        $diff = self::minutes((string) $record->time_in) - self::minutes($shift->start);

        return $diff >= self::threshold() ? $diff : null;
    }

    /** Today's records at this branch that still need review. */
    public function countToday(int $spaId, int $branchId): int
    {
        $today = today()->toDateString();

        $rows = StaffAttendance::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->whereDate('date', $today)
            ->whereNull('deleted_at')
            ->where('source', 'self')
            ->whereNotNull('time_in')
            ->get();
        if ($rows->isEmpty()) {
            return 0;
        }

        $staff = Staff::whereIn('id', $rows->pluck('staff_id'))->get()->keyBy('id');
        $this->schedules->prime($staff, $today, $today);

        return $rows->filter(fn (StaffAttendance $r) => $staff->has($r->staff_id)
            && self::minutesLate($r, $this->schedules->forDate($staff[$r->staff_id], $today, $branchId)) !== null
        )->count();
    }

    private static function minutes(string $time): int
    {
        $c = Carbon::parse($time);

        return $c->hour * 60 + $c->minute;
    }
}
