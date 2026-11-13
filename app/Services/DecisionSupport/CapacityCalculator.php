<?php

declare(strict_types=1);

namespace App\Services\DecisionSupport;

use App\Models\Booking;
use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Services\Bookings\TherapistAssigner;
use App\Services\Staff\WorkScheduleResolver;
use Carbon\CarbonImmutable;

/**
 * Therapist capacity vs booked time, per branch, per date, per hour (DSS revamp).
 *
 * Scheduled minutes: every therapist in the booking pool (TherapistAssigner::pool —
 * therapist role or "performs treatments", active staff at the branch) whose day is
 * WORKING per WorkScheduleResolver (closed days, one-off days off, leave, one-off
 * shifts and rest days already applied there), minus approved leave, split into
 * clock hours.
 *
 * Booked minutes: every booking that held a therapist slot (all statuses except
 * cancelled; a no-show still blocked the slot), split into clock hours by its
 * start/end time. Hour grouping is done in PHP, so it works on MariaDB and SQLite.
 *
 * Known limit (stated on the page): past dates use today's staff list, so a
 * therapist who left is not counted on the days they used to work.
 */
final class CapacityCalculator
{
    /** Statuses that never occupied a therapist. */
    public const NOT_DEMAND = ['cancelled'];

    public function __construct(
        private readonly TherapistAssigner $assigner,
        private readonly WorkScheduleResolver $schedules,
    ) {
    }

    /**
     * @return array<string, array{date: string, open: bool, open_start: ?string, open_end: ?string, therapists: int, scheduled: int, booked: int, count: int,
     *               hours: array<int, array{scheduled: int, booked: int}>}>
     *         keyed by Y-m-d, every date in [$from, $to]
     */
    public function range(int $spaId, int $branchId, string $from, string $to): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end   = CarbonImmutable::parse($to)->startOfDay();

        $pool  = $this->assigner->pool($spaId, $branchId);
        $staff = Staff::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->where('employment_status', 'active')
            ->whereIn('user_id', $pool->pluck('id'))
            ->get()
            ->keyBy('user_id');

        if ($staff->isNotEmpty()) {
            $this->schedules->prime($staff->values(), $start->toDateString(), $end->toDateString());
        }

        $leave = LeaveRequest::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get(['user_id', 'start_date', 'end_date']);

        $hours = $this->schedules->branchHours($branchId);

        $out = [];
        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            $date = $d->toDateString();
            $row  = ['date' => $date, 'open' => false, 'open_start' => null, 'open_end' => null,
                'therapists' => 0, 'scheduled' => 0, 'booked' => 0, 'count' => 0, 'hours' => []];

            $h = $hours[$d->format('l')] ?? null;
            $row['open'] = $h !== null && ! $h->is_closed && $h->opening_time && $h->closing_time;
            if ($row['open']) {
                $row['open_start'] = substr((string) $h->opening_time, 0, 5);
                $row['open_end']   = substr((string) $h->closing_time, 0, 5);
            }

            foreach ($staff as $userId => $s) {
                $onLeave = $leave->contains(fn ($l) => (int) $l->user_id === (int) $userId
                    && (string) $this->dateOf($l->start_date) <= $date && (string) $this->dateOf($l->end_date) >= $date);
                if ($onLeave) {
                    continue;
                }
                $day = $this->schedules->forDate($s, $date, $branchId);
                if (! $day->isWorking() || ! $day->start || ! $day->end) {
                    continue;
                }
                $row['therapists']++;
                $this->spread($row['hours'], 'scheduled', $day->start, $day->end);
            }

            $out[$date] = $row;
        }

        $bookings = Booking::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('status', self::NOT_DEMAND)
            ->get(['appointment_date', 'start_time', 'end_time']);

        foreach ($bookings as $b) {
            $date = $b->appointment_date->toDateString();
            if (! isset($out[$date]) || blank($b->start_time)) {
                continue;
            }
            $endTime = filled($b->end_time)
                ? (string) $b->end_time
                : CarbonImmutable::parse((string) $b->start_time)->addMinutes(60)->format('H:i:s');
            $out[$date]['count']++;
            $this->spread($out[$date]['hours'], 'booked', (string) $b->start_time, $endTime);
        }

        foreach ($out as &$row) {
            ksort($row['hours']);
            foreach ($row['hours'] as $hr => &$v) {
                $v += ['scheduled' => 0, 'booked' => 0];
                $row['scheduled'] += $v['scheduled'];
                $row['booked']    += $v['booked'];
            }
            unset($v);
        }
        unset($row);

        return $out;
    }

    /** Add the minutes of [$from, $to) to each clock-hour bucket they touch. */
    private function spread(array &$hours, string $key, string $from, string $to): void
    {
        $a = $this->minutes($from);
        $b = $this->minutes($to);
        if ($b <= $a) {
            return;
        }
        for ($h = intdiv($a, 60); $h * 60 < $b && $h < 24; $h++) {
            $overlap = min($b, ($h + 1) * 60) - max($a, $h * 60);
            if ($overlap > 0) {
                $hours[$h][$key] = ($hours[$h][$key] ?? 0) + $overlap;
            }
        }
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', array_pad(explode(':', $time), 2, 0));

        return $h * 60 + $m;
    }

    private function dateOf(mixed $v): string
    {
        return $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : substr((string) $v, 0, 10);
    }
}
