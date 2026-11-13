<?php

declare(strict_types=1);

namespace App\Services\DecisionSupport;

use App\Models\Booking;
use Carbon\CarbonImmutable;

/**
 * 7-day demand forecast per branch (DSS revamp, D8 / Q8 B / Q9 A).
 *
 * Method, per future date and clock hour:
 *   history  = average booked therapist-minutes for the same weekday and hour over
 *              the last N weeks (N = 8, or fewer when the branch is newer, never
 *              below 4: under 4 weeks the forecast reports "not enough data");
 *   expected = the higher of (minutes already booked) and (history average).
 * Expected bookings for the day use the same rule on booking counts.
 * Capacity use = expected minutes ÷ scheduled therapist-minutes (CapacityCalculator).
 *
 * No smoothing, trend or ML: every number can be recomputed by hand.
 */
final class DemandForecaster
{
    public const HORIZON_DAYS   = 7;
    public const LOOKBACK_WEEKS = 8;
    public const MIN_WEEKS      = 4;

    public function __construct(private readonly CapacityCalculator $capacity)
    {
    }

    /** Whole weeks of booking history the branch has (capped at LOOKBACK_WEEKS). */
    public function weeksOfHistory(int $spaId, int $branchId, ?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $first = Booking::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->whereNotIn('status', CapacityCalculator::NOT_DEMAND)
            ->where('appointment_date', '<', $today->toDateString())
            ->min('appointment_date');

        if (! $first) {
            return 0;
        }

        $days = CarbonImmutable::parse(substr((string) $first, 0, 10))->diffInDays($today);

        return (int) min(self::LOOKBACK_WEEKS, intdiv((int) $days, 7));
    }

    /**
     * @return array{enough_data: bool, weeks: int, avg_duration: float, days: list<array>}
     */
    public function forecast(int $spaId, int $branchId, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $weeks = $this->weeksOfHistory($spaId, $branchId, $today);
        $enough = $weeks >= self::MIN_WEEKS;

        // History by weekday → hour → minutes, and weekday → booking count.
        $histMinutes = [];
        $histCount = [];
        $histTotalMinutes = 0;
        $histTotalCount = 0;
        if ($weeks > 0) {
            $past = $this->capacity->range($spaId, $branchId,
                $today->subDays($weeks * 7)->toDateString(), $today->subDay()->toDateString());
            foreach ($past as $date => $row) {
                $dow = CarbonImmutable::parse($date)->dayOfWeekIso;
                $histCount[$dow] = ($histCount[$dow] ?? 0) + $row['count'];
                $histTotalCount += $row['count'];
                foreach ($row['hours'] as $h => $v) {
                    $histMinutes[$dow][$h] = ($histMinutes[$dow][$h] ?? 0) + $v['booked'];
                    $histTotalMinutes += $v['booked'];
                }
            }
        }
        $avgDuration = $histTotalCount > 0 ? round($histTotalMinutes / $histTotalCount, 1) : 60.0;

        $future = $this->capacity->range($spaId, $branchId,
            $today->toDateString(), $today->addDays(self::HORIZON_DAYS - 1)->toDateString());

        $days = [];
        foreach ($future as $date => $row) {
            $d = CarbonImmutable::parse($date);
            $dow = $d->dayOfWeekIso;
            $hourKeys = array_unique(array_merge(array_keys($row['hours']), array_keys($histMinutes[$dow] ?? [])));
            sort($hourKeys);

            $hours = [];
            $expected = 0;
            $shortfall = 0;
            $shortHours = [];
            $fullHours = 0;
            foreach ($hourKeys as $h) {
                $sched  = (int) ($row['hours'][$h]['scheduled'] ?? 0);
                $booked = (int) ($row['hours'][$h]['booked'] ?? 0);
                // A closed day expects only what is (wrongly) already booked on it.
                $avg    = $enough && $row['open'] ? round(($histMinutes[$dow][$h] ?? 0) / $weeks, 1) : 0.0;
                $exp    = max((float) $booked, $avg);
                $expected += $exp;

                if ($sched > 0 && $exp >= $sched) {
                    $fullHours++;
                }
                if ($exp - $sched >= 1) {
                    $shortfall += $exp - $sched;
                    $shortHours[] = $h;
                }
                $hours[] = ['h' => $h, 'scheduled' => $sched, 'booked' => $booked, 'history' => $avg, 'expected' => round($exp, 1)];
            }

            $avgCount = $enough && $row['open'] ? round(($histCount[$dow] ?? 0) / $weeks, 1) : 0.0;

            $days[] = [
                'date'              => $date,
                'label'             => $d->format('D, M j'),
                'weekday'           => $d->format('l'),
                'open'              => $row['open'],
                'open_start'        => $row['open_start'],
                'open_end'          => $row['open_end'],
                'therapists'        => $row['therapists'],
                'scheduled'         => $row['scheduled'],
                'booked'            => $row['booked'],
                'booked_count'      => $row['count'],
                'history_count'     => $avgCount,
                'expected_count'    => round(max((float) $row['count'], $avgCount), 1),
                'expected'          => round($expected, 1),
                'use'               => $row['scheduled'] > 0 ? round($expected / $row['scheduled'] * 100, 1) : null,
                'shortfall'         => round($shortfall, 1),
                'unserved'          => round($shortfall / max($avgDuration, 1), 1),
                'short_hours'       => $shortHours,
                'full_hours'        => $fullHours,
                'hours'             => $hours,
            ];
        }

        return ['enough_data' => $enough, 'weeks' => $weeks, 'avg_duration' => $avgDuration, 'days' => $days];
    }

    /** "1 PM–4 PM" style ranges from a list of clock hours. */
    public static function hourRanges(array $hours): string
    {
        sort($hours);
        $ranges = [];
        $startH = null;
        $prev = null;
        foreach ($hours as $h) {
            if ($startH === null) {
                $startH = $prev = $h;
                continue;
            }
            if ($h === $prev + 1) {
                $prev = $h;
                continue;
            }
            $ranges[] = [$startH, $prev];
            $startH = $prev = $h;
        }
        if ($startH !== null) {
            $ranges[] = [$startH, $prev];
        }

        $fmt = fn (int $h) => CarbonImmutable::today()->setTime($h % 24, 0)->format('g A');

        return implode(', ', array_map(fn ($r) => $fmt($r[0]).'–'.$fmt($r[1] + 1), $ranges));
    }
}
