<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Models\Staff;
use App\Services\Staff\ScheduledDay;
use App\Services\Staff\WorkScheduleResolver;
use Carbon\Carbon;

/**
 * Self-service clock-in / clock-out rules from the work schedule (staff
 * scheduling Phase 4). Web (HR\AttendanceController) and mobile
 * (Api\AttendanceController) both use it, so the rules match.
 *
 * - No shift today (rest day, day off, leave, branch closed): clock-in refused
 *   with the reason (Q3 A). A manager can still record attendance by hand.
 * - Shift: allowed from start − grace until the shift ends; "late" after
 *   start + grace (Q4 A: config('attendance.grace_minutes'), default 15).
 * - Staff without a schedule work the branch's hours (D8), so for them this is
 *   the old branch-hours rule.
 */
final class ClockInRules
{
    public function __construct(private readonly WorkScheduleResolver $schedules)
    {
    }

    public static function graceMinutes(): int
    {
        return max(0, (int) config('attendance.grace_minutes', 15));
    }

    public function today(Staff $staff, string $date): ScheduledDay
    {
        return $this->schedules->forDate($staff, $date, (int) $staff->branch_id);
    }

    /** @return array{allowed: bool, reason: ?string, status: ?string, shift: ScheduledDay} */
    public function clockIn(Staff $staff, string $date, string $time): array
    {
        $day = $this->today($staff, $date);
        $refuse = fn (string $reason) => ['allowed' => false, 'reason' => $reason, 'status' => null, 'shift' => $day];

        // No opening hours configured at all (only test data or a broken import:
        // setup and Add Branch always create 7 rows): keep the old permissive rule.
        if ($day->status === ScheduledDay::CLOSED && ! $this->schedules->branchHasHours((int) $staff->branch_id)) {
            return ['allowed' => true, 'reason' => null, 'status' => 'present', 'shift' => $day];
        }

        if (! $day->isWorking()) {
            return $refuse(match ($day->status) {
                ScheduledDay::CLOSED   => 'The branch is closed today. If this is wrong, ask a manager to log your attendance manually.',
                ScheduledDay::ON_LEAVE => 'You are on approved leave today. If you came in anyway, ask a manager to log your attendance manually.',
                ScheduledDay::DAY_OFF  => "You have a day off today, so you're not scheduled. Ask HR or your manager to add a one-off shift if you're working.",
                default                => "Today is your rest day, so you're not scheduled. Ask HR or your manager to add a one-off shift if you're working.",
            });
        }

        $grace = self::graceMinutes();
        $start = Carbon::parse($day->start);
        $end = Carbon::parse($day->end);
        $at = Carbon::parse($time);

        if ($at->lt($start->copy()->subMinutes($grace))) {
            return $refuse('Too early to clock in — your shift starts at '.$start->format('g:i A')
                .'. You can clock in from '.$start->copy()->subMinutes($grace)->format('g:i A').'.');
        }
        if ($at->gt($end)) {
            return $refuse('Your shift ended at '.$end->format('g:i A').'. Ask a manager to log this manually if you need to.');
        }

        return [
            'allowed' => true,
            'reason'  => null,
            'status'  => $at->gt($start->copy()->addMinutes($grace)) ? 'late' : 'present',
            'shift'   => $day,
        ];
    }

    /** Remark for a clock-out after the shift ended, or null. Display only; the time is saved. */
    public function clockOutFlag(Staff $staff, string $date, string $time): ?string
    {
        $day = $this->today($staff, $date);
        if (! $day->isWorking() || $day->end === null) {
            return null;
        }
        $end = Carbon::parse($day->end);

        return Carbon::parse($time)->gt($end)
            ? 'Auto-flag: clocked out after shift end ('.$end->format('g:i A').').'
            : null;
    }
}
