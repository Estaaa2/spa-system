<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Exceptions\PayrollSetupException;
use App\Models\Branch;
use App\Models\OperatingHours;
use App\Models\PayrollRun;
use App\Models\Staff;
use App\Services\Payroll\Support\Decimal;
use App\Services\Payroll\ValueObjects\CutoffContext;
use App\Services\Payroll\ValueObjects\EarningLine;
use App\Services\Payroll\ValueObjects\EarningsResult;
use App\Services\Payroll\ValueObjects\PayProfileSnapshot;
use InvalidArgumentException;

/**
 * Earnings engine facade (Unit 3). Computes; never writes.
 *
 * calculate() returns every earning line for one staff member in one REGULAR run's
 * cutoff, plus is_mwe, days worked, and warnings. Eligibility (active staff, home
 * branch has the Workforce & Finance Suite, profile in period) is the run
 * generator's job (Unit 4); this class computes for whoever it is given.
 */
final class EarningsCalculator
{
    /** Display order within a date; undated lines (semi-monthly basic, recurring items) come first. */
    private const ORDER = ['BASIC', 'OT', 'RESTDAY_PREM', 'HOLIDAY_PAY', 'HOLIDAY_PREM', 'NIGHT_DIFF', 'COMMISSION', 'ALLOWANCE', 'MINWAGE_TOPUP'];

    public function __construct(
        private readonly StatutoryCalculator $calc,
        private readonly RateResolver $rates,
        private readonly AttendanceEarnings $attendance,
        private readonly RecurringEarnings $recurring,
        private readonly CommissionEarnings $commission,
        private readonly MinimumWageTopUp $minWage,
        private readonly HolidayCalendar $holidays,
    ) {
    }

    public function calculate(Staff $staff, PayrollRun $run): EarningsResult
    {
        if ($run->run_type !== PayrollRun::TYPE_REGULAR) {
            throw new InvalidArgumentException("EarningsCalculator handles regular runs only; run #{$run->id} is {$run->run_type}.");
        }
        if ((int) $staff->spa_id !== (int) $run->spa_id) {
            throw new InvalidArgumentException("Staff #{$staff->id} does not belong to the spa of payroll run #{$run->id}.");
        }

        $home = $staff->branch_id ? Branch::withTrashed()->find($staff->branch_id) : null;
        if ($home === null) {
            throw new PayrollSetupException("Staff #{$staff->id} has no home branch (staff.branch_id).");
        }

        $start = $run->period_start->toDateString();
        $end = $run->period_end->toDateString();
        $warnings = [];

        $timeline = $this->rates->timelineFor($staff, $end);
        if ($timeline->overlapping($start, $end) === []) {
            return new EarningsResult([], false, '0.00', ["Staff #{$staff->id} has no pay profile in effect between {$start} and {$end}."]);
        }

        $ctx = new CutoffContext(
            staffId: (int) $staff->id,
            spaId: (int) $staff->spa_id,
            homeBranchId: (int) $home->id,
            periodStart: $start,
            periodEnd: $end,
            cutoffNo: (int) $run->cutoff_no,
            payrollRunId: $run->exists ? (int) $run->id : null,
            timeline: $timeline,
            holidays: $this->holidays->forCutoff($start, $end, AttendanceEarnings::LOOKBACK_DAYS, $warnings),
            closedWeekdays: OperatingHours::query()
                ->where('branch_id', $home->id)
                ->where('is_closed', true)
                ->pluck('day_of_week')
                ->map(fn ($d) => (string) $d)
                ->all(),
        );

        $att = $this->attendance->forPeriod($staff, $ctx);
        $rec = $this->recurring->forPeriod($staff, $ctx, $att->workedDays);
        $com = $this->commission->forPeriod($staff, $ctx);

        $lines = [...$att->lines, ...$rec['lines'], ...$com['lines']];
        $top = $this->minWage->forPeriod($lines, $att->workedDays);
        $lines = [...$lines, ...$top['lines']];

        array_push($warnings, ...$att->warnings, ...$rec['warnings'], ...$com['warnings'], ...$top['warnings']);

        $inPeriod = $timeline->overlapping($start, $end);
        $mweProfile = $timeline->on($end) ?? $inPeriod[array_key_last($inPeriod)];

        return new EarningsResult(
            lines: $this->sorted($lines),
            isMwe: $this->isMwe($home, $mweProfile, $end),
            daysWorked: bcadd((string) count($att->workedDays), '0', 2),
            warnings: array_values(array_unique($warnings)),
        );
    }

    /**
     * MWE test (rates sheet §5 — UNVERIFIED design default): daily rate ≤ home-branch
     * min_daily_wage, using the profile in effect at period end (or the last one in the
     * period). Base rate 0 (commission + top-up) is therefore always MWE.
     */
    private function isMwe(Branch $home, PayProfileSnapshot $profile, string $end): bool
    {
        if ($home->min_daily_wage === null) {
            throw new PayrollSetupException(sprintf(
                'Branch "%s" (#%d) has no minimum daily wage set (branches.min_daily_wage); it is needed for the minimum-wage-earner test.',
                $home->name, $home->id,
            ));
        }

        $date = min($end, $profile->effectiveTo ?? $end);

        return Decimal::cmp($this->rates->dailyRate($profile, $date), Decimal::fromDb($home->min_daily_wage, 'min_daily_wage')) <= 0;
    }

    /**
     * @param list<EarningLine> $lines
     * @return list<EarningLine>
     */
    private function sorted(array $lines): array
    {
        $rank = array_flip(self::ORDER);
        usort($lines, static fn (EarningLine $a, EarningLine $b): int =>
            [$a->date ?? '', $rank[$a->componentCode] ?? 99, $a->sourceId ?? 0]
            <=> [$b->date ?? '', $rank[$b->componentCode] ?? 99, $b->sourceId ?? 0]);

        return $lines;
    }
}
