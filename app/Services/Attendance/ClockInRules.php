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

    /**
     * How a clock-out time compares with the shift end (attendance-aware booking
     * Phase 2). 'early' only beyond the grace (Q2 A: same grace as clock-in);
     * null kind when there is no shift to compare with.
     *
     * @return array{kind: ?string, minutes: int}  kind: early | on_time | after_shift | null
     */
    public function clockOutTiming(Staff $staff, string $date, string $time): array
    {
        return self::outTiming($this->today($staff, $date), $time);
    }

    /** @return array{kind: ?string, minutes: int} */
    public static function outTiming(ScheduledDay $day, string $time): array
    {
        if (! $day->isWorking() || $day->end === null) {
            return ['kind' => null, 'minutes' => 0];
        }
        $diff = self::minutesOf($time) - self::minutesOf($day->end);

        return match (true) {
            $diff > 0                       => ['kind' => 'after_shift', 'minutes' => $diff],
            -$diff > self::graceMinutes()   => ['kind' => 'early', 'minutes' => -$diff],
            default                         => ['kind' => 'on_time', 'minutes' => -$diff],
        };
    }

    /**
     * How a clock-in time compares with the shift start: 'late' after start + grace.
     *
     * @return array{kind: ?string, minutes: int}  kind: late | on_time | null
     */
    public static function inTiming(ScheduledDay $day, string $time): array
    {
        if (! $day->isWorking() || $day->start === null) {
            return ['kind' => null, 'minutes' => 0];
        }
        $diff = self::minutesOf($time) - self::minutesOf($day->start);

        return $diff > self::graceMinutes()
            ? ['kind' => 'late', 'minutes' => $diff]
            : ['kind' => 'on_time', 'minutes' => max($diff, 0)];
    }

    /** "1 h 30 min", "45 min", "2 h". */
    public static function duration(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return trim(($h ? "{$h} h " : '').($m || ! $h ? "{$m} min" : ''));
    }

    /**
     * Remark for an early clock-out (Phase 2, Q2 A), or null when not early.
     * $reason is what the person typed; null from an older app (Q3 A).
     */
    public function earlyClockOutRemark(Staff $staff, string $date, string $time, ?string $reason, bool $fromApp = false): ?string
    {
        $timing = $this->clockOutTiming($staff, $date, $time);
        if ($timing['kind'] !== 'early') {
            return null;
        }
        $end = Carbon::parse($this->today($staff, $date)->end)->format('g:i A');
        $reason = trim((string) $reason);

        return 'Early clock-out ('.self::duration($timing['minutes'])." before shift end, {$end}): "
            .($reason !== '' ? $reason : 'no reason given'.($fromApp ? ', mobile' : ''));
    }

    private static function minutesOf(string $time): int
    {
        $c = Carbon::parse($time);

        return $c->hour * 60 + $c->minute;
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
