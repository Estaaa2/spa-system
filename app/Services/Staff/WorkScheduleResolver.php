<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Models\LeaveRequest;
use App\Models\OperatingHours;
use App\Models\Staff;
use App\Models\StaffPayProfile;
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
 *  6. No pattern in effect: the pay profile's rest days → REST_DAY (Phase 4),
 *     every other open day → WORKING on branch hours (D8)
 *
 * One-off changes (Phase 4, Q2 A) carry a kind: an 'unpaid' day off, or a
 * 'swap' pair (a day off on a workday + a shift on that week's rest day). A
 * swap shift with no times works the branch's hours.
 *
 * For pay, scheduledRestDay() answers "is this date a rest day?" (pattern, then
 * swaps), and patternRestDays() gives the weekly rest days for the daily-paid
 * DOLE factor. Both return null when no schedule governs the date, so payroll
 * keeps using the pay profile there (Phase 4, D3 B).
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

    /** @var array<int, list<StaffPayProfile>> staff → pay profiles */
    private array $profiles = [];

    /** $ignoreOverrides: the day as the weekly pattern (and leave) alone has it. */
    public function forDate(Staff $staff, string $date, ?int $branchId = null, bool $ignoreOverrides = false): ScheduledDay
    {
        $date = (new \DateTimeImmutable($date))->format('Y-m-d');
        $branchId ??= (int) $staff->branch_id;
        $weekday = (new \DateTimeImmutable($date))->format('l');

        $schedule = $this->scheduleOn($staff, $date);
        $has = $schedule !== null;
        $make = fn (string $status, string $source, ?string $s = null, ?string $e = null, ?string $note = null, ?string $kind = null)
            => new ScheduledDay($date, $branchId, $status, $source, $s, $e, $has, $note, $kind);

        // 1. Branch closed
        $hours = $this->branchHours($branchId)[$weekday] ?? null;
        if ($hours === null || $hours->is_closed || ! $hours->opening_time || ! $hours->closing_time) {
            return $make(ScheduledDay::CLOSED, ScheduledDay::SOURCE_BRANCH);
        }

        // 2. One-off day off
        $override = $ignoreOverrides ? null : $this->overrideOn($staff, $date);
        if ($override?->isDayOff()) {
            return $make(ScheduledDay::DAY_OFF, ScheduledDay::SOURCE_OVERRIDE, note: $override->reason, kind: $override->kind ?? StaffScheduleOverride::KIND_UNPAID);
        }

        $day = $schedule?->dayFor($weekday);
        $profileRest = $schedule === null && in_array($weekday, $this->profileRestDays($staff, $date), true);
        $baseRest = ($day !== null && $day->is_rest_day) || $profileRest;
        $wouldWork = $override !== null || ! $baseRest;

        // 3. Approved leave on a day they would work
        if ($wouldWork && $this->onLeave($staff, $date)) {
            return $make(ScheduledDay::ON_LEAVE, ScheduledDay::SOURCE_LEAVE);
        }

        $open = self::hm($hours->opening_time);
        $close = self::hm($hours->closing_time);

        // 4. One-off shift (a swap shift without times works the branch's hours)
        if ($override !== null) {
            return $make(ScheduledDay::WORKING, ScheduledDay::SOURCE_OVERRIDE,
                self::hm($override->start_time) ?? $open, self::hm($override->end_time) ?? $close,
                $override->reason, $override->kind);
        }

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

        // 6. No schedule: pay-profile rest days (Phase 4), else branch hours (D8)
        if ($profileRest) {
            return $make(ScheduledDay::REST_DAY, ScheduledDay::SOURCE_PAY_PROFILE);
        }

        return $make(ScheduledDay::WORKING, ScheduledDay::SOURCE_BRANCH_HOURS, $open, $close);
    }

    /**
     * For pay: is $date a scheduled rest day? Weekly pattern, adjusted by a
     * rest-day swap on that date (Phase 4, Q2 A). An unpaid day off or an extra
     * shift does not change it: an extra shift on a rest day still earns the
     * rest-day premium. Null when no pattern is in effect and no swap applies:
     * payroll then keeps the pay profile's rest days (D3 B fallback).
     */
    public function scheduledRestDay(Staff $staff, string $date): ?bool
    {
        $date = (new \DateTimeImmutable($date))->format('Y-m-d');
        $override = $this->overrideOn($staff, $date);
        if ($override?->isSwap()) {
            return $override->isDayOff();
        }

        $schedule = $this->scheduleOn($staff, $date);
        if ($schedule === null) {
            return null;
        }

        return (bool) $schedule->dayFor((new \DateTimeImmutable($date))->format('l'))?->is_rest_day;
    }

    /**
     * The weekly rest days of the pattern in effect on $date (for the daily-paid
     * DOLE factor: 1 → 313, 2 → 261), or null with no pattern.
     *
     * @return list<string>|null
     */
    public function patternRestDays(Staff $staff, string $date): ?array
    {
        return $this->scheduleOn($staff, (new \DateTimeImmutable($date))->format('Y-m-d'))?->restDays();
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

    /**
     * Preload schedules, one-off changes and approved leave for several staff over
     * a date range in 3 queries, so forDate() over that range runs in memory
     * (the booking date list checks 30 days × every therapist).
     *
     * @param iterable<Staff> $staff
     */
    public function prime(iterable $staff, string $from, string $to): void
    {
        $byId = [];
        foreach ($staff as $s) {
            $byId[(int) $s->id] = $s;
        }
        if ($byId === []) {
            return;
        }
        $ids = array_keys($byId);

        $schedules = StaffWorkSchedule::query()->with('days')->whereIn('staff_id', $ids)
            ->orderByDesc('effective_from')->get()->groupBy('staff_id');
        foreach ($ids as $id) {
            $this->schedules[$id] = ($schedules[$id] ?? collect())->all();
        }

        $profiles = StaffPayProfile::query()->whereIn('staff_id', $ids)
            ->orderByDesc('effective_from')->get()->groupBy('staff_id');
        foreach ($ids as $id) {
            $this->profiles[$id] = ($profiles[$id] ?? collect())->all();
        }

        $overrides = StaffScheduleOverride::query()->whereIn('staff_id', $ids)
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->get()
            ->keyBy(fn (StaffScheduleOverride $o) => $o->staff_id.'|'.$o->date->toDateString());

        $userIds = [];
        foreach ($byId as $s) {
            if ($s->user_id) {
                $userIds[(int) $s->user_id] = (int) $s->spa_id;
            }
        }
        $leave = LeaveRequest::query()
            ->whereIn('user_id', array_keys($userIds))
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->get(['user_id', 'spa_id', 'start_date', 'end_date']);

        for ($d = new \DateTimeImmutable($from), $end = new \DateTimeImmutable($to); $d <= $end; $d = $d->modify('+1 day')) {
            $date = $d->format('Y-m-d');
            foreach ($byId as $id => $s) {
                $this->overrides[$id.'|'.$date] = $overrides[$id.'|'.$date] ?? null;
                if ($s->user_id) {
                    $this->leave[$s->user_id.'|'.$date] = $leave->contains(fn ($l) => (int) $l->user_id === (int) $s->user_id
                        && (int) $l->spa_id === (int) $s->spa_id
                        && \Illuminate\Support\Carbon::parse($l->start_date)->toDateString() <= $date
                        && \Illuminate\Support\Carbon::parse($l->end_date)->toDateString() >= $date);
                }
            }
        }
    }

    /** Has this branch any opening-hours rows at all? (Real branches always get 7.) */
    public function branchHasHours(int $branchId): bool
    {
        return $this->branchHours($branchId) !== [];
    }

    public function flush(): void
    {
        $this->hours = $this->schedules = $this->overrides = $this->leave = $this->profiles = [];
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

    /** @return list<string> rest days of the pay profile in effect on $date (none without a profile) */
    private function profileRestDays(Staff $staff, string $date): array
    {
        $this->profiles[(int) $staff->id] ??= StaffPayProfile::query()
            ->where('staff_id', $staff->id)
            ->orderByDesc('effective_from')
            ->get()
            ->all();

        foreach ($this->profiles[(int) $staff->id] as $p) {
            if ($p->effective_from->toDateString() <= $date
                && ($p->effective_to === null || $p->effective_to->toDateString() >= $date)) {
                return array_values($p->rest_days ?? []);
            }
        }

        return [];
    }

    private static function hm(?string $time): ?string
    {
        return $time === null ? null : substr((string) $time, 0, 5);
    }
}
