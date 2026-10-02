<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Exceptions\PayrollSetupException;
use App\Models\Branch;
use App\Models\PayslipLine;
use App\Services\Payroll\Support\Decimal;
use App\Services\Payroll\ValueObjects\EarningLine;
use App\Services\Payroll\ValueObjects\WorkedDay;

/**
 * MINWAGE_TOPUP per worked day (locked rule): if the day's counts_toward_min_wage
 * lines sum below the attendance branch's min_daily_wage, top up the difference.
 *  - Lines are matched to the day by EarningLine::$date: BASIC by work date,
 *    COMMISSION by appointment date.
 *  - Monthly-paid staff have no per-day BASIC line; their daily rate
 *    (monthly × 12 ÷ 365) is counted as that day's basic — UNVERIFIED default.
 *  - A branch with no min_daily_wage throws: guessing a wage is worse than stopping.
 */
final class MinimumWageTopUp
{
    public function __construct(private readonly StatutoryCalculator $calc)
    {
    }

    /**
     * @param list<EarningLine> $lines      all other earning lines of the cutoff
     * @param list<WorkedDay>   $workedDays
     * @return array{lines: list<EarningLine>, warnings: list<string>}
     */
    public function forPeriod(array $lines, array $workedDays): array
    {
        $ids = array_values(array_unique(array_map(static fn (WorkedDay $d): int => $d->branchId, $workedDays)));
        $branches = $ids === [] ? [] : Branch::withTrashed()->whereIn('id', $ids)->get()->keyBy('id')->all();

        $wages = [];
        foreach ($ids as $id) {
            $b = $branches[$id] ?? null;
            $wages[$id] = [
                'name' => $b?->name ?? "#{$id}",
                'wage' => $b?->min_daily_wage !== null ? Decimal::fromDb($b->min_daily_wage, 'min_daily_wage') : null,
                'from' => $b?->min_wage_effective_from ? \Illuminate\Support\Carbon::parse($b->min_wage_effective_from)->toDateString() : null,
            ];
        }

        return $this->compute($lines, $workedDays, $wages);
    }

    /**
     * Pure computation.
     *
     * @param list<EarningLine> $lines
     * @param list<WorkedDay>   $workedDays
     * @param array<int, array{name: string, wage: ?string, from: ?string}> $wages keyed by branch id
     * @return array{lines: list<EarningLine>, warnings: list<string>}
     */
    public function compute(array $lines, array $workedDays, array $wages): array
    {
        $counted = [];
        foreach ($lines as $line) {
            if ($line->date !== null && $this->calc->component($line->componentCode)->countsTowardMinWage) {
                $counted[$line->date][$line->componentCode] = Decimal::add($counted[$line->date][$line->componentCode] ?? '0', $line->amount);
            }
        }

        $out = [];
        $warnings = [];
        $topUp = $this->calc->component('MINWAGE_TOPUP');

        foreach ($workedDays as $day) {
            $branch = $wages[$day->branchId] ?? ['name' => "#{$day->branchId}", 'wage' => null, 'from' => null];
            if ($branch['wage'] === null) {
                throw new PayrollSetupException(sprintf(
                    'Branch "%s" (#%d) has no minimum daily wage set (branches.min_daily_wage). Enter the applicable wage-order rate for this branch before running payroll.',
                    $branch['name'], $day->branchId,
                ));
            }
            if ($branch['from'] !== null && $branch['from'] > $day->date) {
                $warnings[] = "{$day->date}: branch \"{$branch['name']}\" minimum wage is effective from {$branch['from']}, after this day — the current rate was used; check the wage order that applied.";
            }

            $parts = $counted[$day->date] ?? [];
            if (! $day->profile->isDaily()) {
                $parts['BASIC'] = Decimal::add($parts['BASIC'] ?? '0', $day->dailyRate);
            }
            $sum = array_reduce($parts, static fn (string $c, string $v): string => Decimal::add($c, $v), '0');
            $diff = Decimal::sub($branch['wage'], $sum);

            if (Decimal::isPositive(Decimal::round2($diff))) {
                $breakdown = implode(' + ', array_map(
                    static fn (string $code, string $v): string => strtolower($code).' ₱'.Decimal::peso($v),
                    array_keys($parts), $parts,
                )) ?: 'nothing';
                $out[] = EarningLine::priced($topUp, '1', $diff, $day->branchId, PayslipLine::SOURCE_ATTENDANCE, $day->attendanceId,
                    sprintf('Min. daily wage ₱%s (%s) − counted %s', Decimal::peso($branch['wage']), $branch['name'], $breakdown),
                    $day->date, (new \DateTimeImmutable($day->date))->format('M j'));
            }
        }

        return ['lines' => $out, 'warnings' => $warnings];
    }
}
