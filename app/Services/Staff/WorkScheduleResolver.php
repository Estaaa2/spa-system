<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Models\LeaveRequest;
use App\Models\OperatingHours;
use App\Models\Staff;
use App\Models\StaffScheduleOverride;
use App\Models\StaffWorkSchedule;

/**
 * The ONLY place that answers "what is this staff member's schedule on date X?"
 * (staff scheduling Phase 2). Booking (Phase 3) and attendance/payroll (Phase 4)
 * read it; nothing else should query schedules directly.
 *
 * Precedence (Phase 2, Q3 A):
 *  1. Branch closed that weekday (or no hours row)        → CLOSED
 *  2. One-off day off                                      → DAY_OFF
 *  3. Approved leave, on a day they would otherwise work   → ON_LEAVE
 *     (a rest day stays a rest day: leave only covers workdays, Phase 1 Q2)
 *  4. One-off shift                                        → WORKING (its own times)
 *  5. Weekly pattern: rest day → REST_DAY; workday → WORKING
 *     (its own times, or the branch's hours when it has none, Q2 A)
 *  6. No pattern in effect                                 → WORKING on branch hours (D8)
 *
 * $branchId is the branch where the work happens that day (the booking's branch,
 * the attendance row's branch); it defaults to the staff member's current branch.
 * The weekly pattern follows the person across branches (Q9 A).
 *
 * Scoped per request/job (AppServiceProvider), like BranchPermissionCache. Call
 * flush() after changing schedules, overrides, leave or branch hours mid-request.
 */
final class WorkScheduleResolver
{
    /** @var array<int, array<string, OperatingHours>> branch → weekday → hours */
    private array $hours = [];

    /** @var array<int, list<StaffWorkSchedule>> staff → schedules (with days) */
    private array $schedules = [];

    /** @var array<string, ?StaffScheduleOverride> "staffId|date" */
    private array $overrides = [];

    /** @var array<string, bool> "userId|date" */
    private array $leave = [];

    public function forDate(Staff $staff, string $date, ?int $branchId = null): ScheduledDay
    {
        $date = (new \DateTimeImmutable($date))->format('Y-m-d');
        $branchId ??= (int) $staff->branch_id;
        $weekday = (new \DateTimeImmutable($date))->format('l');

        $schedule = $this->scheduleOn($staff, $date);
        $has = $schedule !== null;
        $make = fn (string $status, string $source, ?string $s = null, ?string $e = null, ?string $note = null)
            => new ScheduledDay($date, $branchId, $status, $source, $s, $e, $has, $note);

        // 1. Branch closed
        $hours = $this->branchHours($branchId)[$weekday] ?? null;
        if ($hours === null || $hours->is_closed || ! $hours->opening_time || ! $hours->closing_time) {
            return $make(ScheduledDay::CLOSED, ScheduledDay::SOURCE_BRANCH);
        }

        // 2. One-off day off
        $override = $this->overrideOn($staff, $date);
        if ($override?->isDayOff()) {
            return $make(ScheduledDay::DAY_OFF, ScheduledDay::SOURCE_OVERRIDE, note: $override->reason);
        }

        $day = $schedule?->dayFor($weekday);
        $wouldWork = $override !== null || $day === null || ! $day->is_rest_day;

        // 3. Approved leave on a day they would work
        if ($wouldWork && $this->onLeave($staff, $date)) {
            return $make(ScheduledDay::ON_LEAVE, ScheduledDay::SOURCE_LEAVE);
        }

        // 4. One-off shift
        if ($override !== null) {
            return $make(ScheduledDay::WORKING, ScheduledDay::SOURCE_OVERRIDE,
                self::hm($override->start_time), self::hm($override->end_time), $override->reason);
        }

        $open = self::hm($hours->opening_time);
        $close = self::hm($hours->closing_time);

        // 5. Weekly pattern
        if ($schedule !== null) {
            if ($day === null) {
                // A pattern missing a weekday row (shouldn't happen): treat as branch hours.
                return $make(ScheduledDay::WORKING, ScheduledDay::SOURCE_BRANCH_HOURS, $open, $close);
            }
            if ($day->is_rest_day) {
                return $make(ScheduledDay::REST_DAY, ScheduledDay::SOURCE_SCHEDULE);
            }
            if ($day->followsBranchHours()) {
                return $make(ScheduledDay::WORKING, ScheduledDay::SOURCE_BRANCH_HOURS, $open, $close);
            }

            return $make(ScheduledDay::WORKING, ScheduledDay::SOURCE_SCHEDULE,
                self::hm($day->start_time), self::hm($day->end_time));
        }

        // 6. No schedule: branch hours on open days (D8)
        return $make(ScheduledDay::WORKING, ScheduledDay::SOURCE_BRANCH_HOURS, $open, $close);
    }

    /**
     * One ScheduledDay per date from $from to $to inclusive.
     *
     * @return list<ScheduledDay>
     */
    public function forRange(Staff $staff, string $from, string $to, ?int $branchId = null): array
    {
        $out = [];
        $d = new \DateTimeImmutable($from);
        $end = new \DateTimeImmutable($to);
        for (; $d <= $end; $d = $d->modify('+1 day')) {
            $out[] = $this->forDate($staff, $d->format('Y-m-d'), $branchId);
        }

        return $out;
    }

    /** The weekly pattern in effect on $date, or null (D8 default). */
    public function scheduleOn(Staff $staff, string $date): ?StaffWorkSchedule
    {
        foreach ($this->schedulesFor($staff) as $s) {
            if ($s->effective_from->toDateString() <= $date
                && ($s->effective_to === null || $s->effective_to->toDateString() >= $date)) {
                return $s;
            }
        }

        return null;
    }

    /** @return array<string, OperatingHours> weekday → hours */
    public function branchHours(int $branchId): array
    {
        return $this->hours[$branchId] ??= OperatingHours::query()
            ->where('branch_id', $branchId)
            ->get()
            ->keyBy(fn (OperatingHours $h) => ucfirst(strtolower(trim((string) $h->day_of_week))))
            ->all();
    }

    public function flush(): void
    {
        $this->hours = $this->schedules = $this->overrides = $this->leave = [];
    }

    /** @return list<StaffWorkSchedule> newest first */
    private function schedulesFor(Staff $staff): array
    {
        return $this->schedules[(int) $staff->id] ??= StaffWorkSchedule::query()
            ->with('days')
            ->where('staff_id', $staff->id)
            ->orderByDesc('effective_from')
            ->get()
            ->all();
    }

    private function overrideOn(Staff $staff, string $date): ?StaffScheduleOverride
    {
        $key = $staff->id.'|'.$date;
        if (! array_key_exists($key, $this->overrides)) {
            $this->overrides[$key] = StaffScheduleOverride::query()
                ->where('staff_id', $staff->id)
                ->whereDate('date', $date)
                ->first();
        }

        return $this->overrides[$key];
    }

    /** Approved leave covering $date, at any branch of the staff member's spa. */
    private function onLeave(Staff $staff, string $date): bool
    {
        if (! $staff->user_id) {
            return false;
        }

        return $this->leave[$staff->user_id.'|'.$date] ??= LeaveRequest::query()
            ->where('user_id', $staff->user_id)
            ->where('spa_id', $staff->spa_id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    private static function hm(?string $time): ?string
    {
        return $time === null ? null : substr((string) $time, 0, 5);
    }
}
