<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Exceptions\PayrollSetupException;
use App\Exceptions\PayrollStateException;
use App\Models\Branch;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Models\Spa;
use App\Models\Staff;
use App\Models\StaffRecurringItem;
use App\Models\User;
use App\Services\Payroll\Support\Decimal;
use App\Services\Payroll\ValueObjects\EarningLine;
use App\Services\Payroll\ValueObjects\PayPeriod;
use App\Services\Payroll\ValueObjects\PayProfileSnapshot;
use App\Services\Payroll\ValueObjects\PayslipSettlement;
use App\Services\Payroll\ValueObjects\ProfileTimeline;
use App\Services\Payroll\ValueObjects\RunResult;
use App\Services\Payroll\ValueObjects\RunWarning;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Pay run service (Unit 4) — locked data model v3.1.
 *
 * Generates consolidated semi-monthly runs and the 13th-month run for a spa,
 * persists payslips and lines, supports manual adjustment lines on drafts, moves
 * runs through draft → approved → finalized → released (each step logged), and
 * reads the monthly government summary and the 13th-month balance.
 *
 * Statutory timing (Art. 103 interval, 13th-month deadline) comes from
 * config/payroll.php `payment_timing` (rates sheet §12); contribution/withholding
 * practice follows rates sheet §13. Settlement math
 * (contributions, WTAX, net-pay floor, totals) lives in PayslipSettler so
 * generation and manual changes share one code path.
 *
 * No controllers, routes, pages, RBAC. Callers pass the acting User; this class
 * never reads Auth.
 *
 * Immutability: PayrollRun / Payslip / PayslipLine model guards (Unit 1) refuse
 * changes once a run is not draft. Every write here happens inside a transaction
 * that has locked the run row and re-checked it is draft; bulk query-builder
 * deletes (which skip model events) are only issued after that check.
 */
final class PayrollRunService
{
    public const MANUAL_CODES = ['ADJ_EARNING', 'ADJ_EARNING_NONTAX', 'ADJ_DEDUCTION'];

    /** Recurring-deduction component codes (locked model: staff_recurring_items.component_code). */
    public const RECURRING_DEDUCTION_CODES = ['LOAN_DEDUCTION', 'OTHER_DEDUCTION'];

    /** Codes read by monthlyContributionSummary(), in display order. */
    public const SUMMARY_CODES = ['SSS_EE', 'SSS_ER', 'SSS_EC', 'PHIC_EE', 'PHIC_ER', 'HDMF_EE', 'HDMF_ER', 'WTAX'];

    private const PAYABLE_STATUSES = [PayrollRun::STATUS_FINALIZED, PayrollRun::STATUS_RELEASED];

    public function __construct(
        private readonly StatutoryCalculator $calc,
        private readonly EarningsCalculator $earnings,
        private readonly RateResolver $rates,
        private readonly PayslipSettler $settler,
    ) {
    }

    // =====================================================================
    // 1. Period builder
    // =====================================================================

    /**
     * Locked rule: cutoff 1 = day 1..payroll_first_cutoff_day; cutoff 2 = next day..month
     * end; pay_date = period_end + payroll_pay_day_offset. With a constant offset the gap
     * between consecutive pay dates equals the length of the later period, so the
     * Art. 103 check (≤ 16 days) is applied to each period's length.
     *
     * @param string|null $startOverride cutoff 2 only: start the period here (the day after
     *                                   the actual cutoff-1 run's period_end)
     *
     * @throws PayrollSetupException when the spa settings cannot produce a lawful period
     */
    public function buildPeriod(Spa $spa, int $year, int $month, int $cutoffNo, ?string $startOverride = null): PayPeriod
    {
        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            throw new InvalidArgumentException("Invalid payroll month {$year}-{$month}.");
        }
        if (! in_array($cutoffNo, [1, 2], true)) {
            throw new InvalidArgumentException("Cutoff must be 1 or 2, got {$cutoffNo}.");
        }

        $first = (int) $spa->payroll_first_cutoff_day;
        $offset = (int) $spa->payroll_pay_day_offset;
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $days = $monthStart->daysInMonth;

        if ($first < 1 || $first >= $days) {
            throw new PayrollSetupException(sprintf(
                'Spa "%s": the first-cutoff day (%d) must be between 1 and %d for %s.',
                $spa->name, $first, $days - 1, $monthStart->format('F Y'),
            ));
        }
        if ($offset < 0) {
            throw new PayrollSetupException("Spa \"{$spa->name}\": the pay-day offset cannot be negative.");
        }

        if ($cutoffNo === 1) {
            $start = $monthStart->toDateString();
            $end = $monthStart->copy()->day($first)->toDateString();
        } else {
            $start = $startOverride ?? $monthStart->copy()->day($first + 1)->toDateString();
            $end = $monthStart->copy()->endOfMonth()->toDateString();
        }

        $period = new PayPeriod($start, $end, Carbon::parse($end)->addDays($offset)->toDateString(), $cutoffNo);

        if ($period->start > $period->end) {
            throw new PayrollSetupException("Cutoff {$cutoffNo} of {$monthStart->format('F Y')} would start after it ends ({$start} to {$end}).");
        }
        if ($period->lengthInDays() > $this->maxPayIntervalDays()) {
            throw new PayrollSetupException(sprintf(
                'Cutoff %d of %s runs %d days (%s to %s). Labor Code Art. 103 requires wages at intervals not exceeding %d days — change the spa\'s first-cutoff day.',
                $cutoffNo, $monthStart->format('F Y'), $period->lengthInDays(), $start, $end, $this->maxPayIntervalDays(),
            ));
        }

        return $period;
    }

    // =====================================================================
    // 2–3. Regular run
    // =====================================================================

    /**
     * Generate (or regenerate a draft of) the consolidated regular run for one cutoff.
     *
     * @throws PayrollStateException a non-draft run exists, or cutoff 1 is not finalized/released
     * @throws PayrollSetupException the spa settings cannot produce a lawful period
     */
    public function generateRegular(Spa $spa, int $year, int $month, int $cutoffNo, User $by): RunResult
    {
        return DB::transaction(function () use ($spa, $year, $month, $cutoffNo, $by): RunResult {
            $spa = Spa::query()->whereKey($spa->getKey())->lockForUpdate()->firstOrFail();
            $warnings = [];

            $monthStart = sprintf('%04d-%02d-01', $year, $month);
            $monthEnd = Carbon::parse($monthStart)->endOfMonth()->toDateString();

            $startOverride = null;
            if ($cutoffNo === 2) {
                $c1 = $this->regularRunInMonth($spa, 1, $monthStart, $monthEnd);
                if ($c1 === null || ! in_array($c1->status, self::PAYABLE_STATUSES, true)) {
                    throw new PayrollStateException(sprintf(
                        'Cutoff 2 of %s cannot be generated until cutoff 1 of the same month is finalized%s.',
                        Carbon::parse($monthStart)->format('F Y'),
                        $c1 ? " (it is {$c1->status})" : ' (it has not been generated)',
                    ));
                }
                // "cutoff 2 = next day": the day after the ACTUAL cutoff-1 run, so a
                // settings change between cutoffs can never leave a gap or overlap.
                $startOverride = $c1->period_end->copy()->addDay()->toDateString();
                $fromSettings = Carbon::parse($monthStart)->day((int) $spa->payroll_first_cutoff_day + 1)->toDateString();
                if ($startOverride !== $fromSettings) {
                    $warnings[] = new RunWarning(RunWarning::PERIOD_ADJUSTED,
                        "Cutoff 2 starts {$startOverride} (the day after cutoff 1 ended), not {$fromSettings} from the current spa setting.");
                }
            }

            $period = $this->buildPeriod($spa, $year, $month, $cutoffNo, $startOverride);
            [$run, $regenerated] = $this->openRun($spa, PayrollRun::TYPE_REGULAR, $period,
                $this->regularRunInMonth($spa, $cutoffNo, $monthStart, $monthEnd), $by);

            array_push($warnings, ...$this->periodWarnings($spa, $run));

            $existing = $this->clearComputedLines($run);
            $names = $this->staffNames($spa);
            $processed = [];
            $mweCount = 0;

            foreach ($this->candidates($spa) as $staff) {
                $who = $this->who($staff, $names);
                $check = $this->regularEligibility($staff, $run, $who);

                if ($check['warning'] !== null) {
                    $warnings[] = $check['warning'];
                    continue;   // existing payslip (if any) handled as leftover below
                }

                /** @var Branch $home */
                $home = $check['home'];
                /** @var ProfileTimeline $timeline */
                $timeline = $check['timeline'];

                try {
                    $result = $this->earnings->calculate($staff, $run);
                } catch (PayrollSetupException $e) {
                    $warnings[] = new RunWarning(RunWarning::SETUP_ERROR, "{$who}: not paid — {$e->getMessage()}", (int) $staff->id);
                    continue;
                }

                foreach ($result->warnings as $w) {
                    $warnings[] = new RunWarning(RunWarning::EARNINGS, "{$who}: {$w}", (int) $staff->id);
                }

                $deductionLines = $this->recurringDeductionLines($staff, $run, $timeline, (int) $home->id, $who, $warnings);
                array_push($warnings, ...$this->statutoryIdWarnings($staff, $who));

                $payslip = $this->upsertPayslip($run, $staff, $existing[(int) $staff->id] ?? null, [
                    'home_branch_id' => $home->id,
                    'days_worked'    => $result->daysWorked,
                    'is_mwe'         => $result->isMwe,
                    'snapshot'       => $this->regularSnapshot($spa, $run, $home, $timeline, $result->lines),
                ]);

                foreach ($result->lines as $line) {
                    PayslipLine::create($line->toPayslipLineAttributes() + ['payslip_id' => $payslip->id, 'is_manual' => false]);
                }
                foreach ($deductionLines as $attrs) {
                    PayslipLine::create($attrs + ['payslip_id' => $payslip->id, 'is_manual' => false]);
                }

                array_push($warnings, ...$this->settler->settle($run, $payslip, $names[(int) $staff->id] ?? null));

                $mweCount += $result->isMwe ? 1 : 0;
                $processed[(int) $staff->id] = true;
            }

            array_push($warnings, ...$this->handleLeftovers($run, $existing, $processed, $names));
            array_push($warnings, ...$this->inactiveWithAttendance($spa, $run, $names));

            if ($cutoffNo === 2) {
                array_push($warnings, ...$this->missingFromCutoffTwo($run, $processed, $names));
            }
            if ($mweCount > 0) {
                $warnings[] = new RunWarning(RunWarning::UNVERIFIED_RULE,
                    "{$mweCount} payslip(s) are treated as minimum wage earners: daily rate ≤ home-branch minimum wage (rates sheet §5 — UNVERIFIED test; exempt-component mapping UNVERIFIED).");
            }

            return new RunResult($run->refresh(), $regenerated, $this->totals($run), $this->dedupe($this->foldCumulativeAverage($warnings, $names)));
        });
    }

    // =====================================================================
    // 4. 13th-month run
    // =====================================================================

    /**
     * One payslip per eligible staff member (active, suite home branch, at least one
     * month of service in the year by hire_date). Amount = sum of in_13th_month_basis
     * lines in finalized/released REGULAR runs of the calendar year ÷ 12 (locked rule;
     * includes COMMISSION per config). No contributions (UNVERIFIED — see warning).
     */
    public function generateThirteenthMonth(Spa $spa, int $year, Carbon|string $payDate, User $by): RunResult
    {
        $payDate = Carbon::parse($payDate)->toDateString();
        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException("Invalid year {$year}.");
        }
        if ($payDate < "{$year}-01-01") {
            throw new InvalidArgumentException("The 13th-month pay date ({$payDate}) cannot be before {$year}.");
        }

        return DB::transaction(function () use ($spa, $year, $payDate, $by): RunResult {
            $spa = Spa::query()->whereKey($spa->getKey())->lockForUpdate()->firstOrFail();
            $warnings = [];
            $period = new PayPeriod("{$year}-01-01", "{$year}-12-31", $payDate, null);

            $existingRun = PayrollRun::query()
                ->where('spa_id', $spa->id)
                ->where('run_type', PayrollRun::TYPE_THIRTEENTH_MONTH)
                ->whereDate('period_start', $period->start)
                ->lockForUpdate()
                ->first();
            [$run, $regenerated] = $this->openRun($spa, PayrollRun::TYPE_THIRTEENTH_MONTH, $period, $existingRun, $by);

            $deadline = "{$year}-".$this->timing('thirteenth_month_deadline');
            if ($payDate > $deadline) {
                $warnings[] = new RunWarning(RunWarning::PAY_DATE_LATE,
                    "Pay date {$payDate} is after {$deadline}. PD 851 and DOLE Labor Advisory 16-25 require 13th-month pay on or before December 24, with no deferment.");
            }
            $warnings[] = new RunWarning(RunWarning::UNVERIFIED_RULE,
                'No SSS, PhilHealth or Pag-IBIG is computed on the 13th-month run (UNVERIFIED — SSS excludes it per §1 VERIFY, PhilHealth MBS excludes it per §2, Pag-IBIG treatment not confirmed).');
            array_push($warnings, ...$this->unfinalizedCutoffWarnings($spa, $year));

            $existing = $this->clearComputedLines($run);
            $names = $this->staffNames($spa);
            $basis = $this->thirteenthBasis($spa, $year);
            $basisCodes = $this->basisCodes();
            $processed = [];

            // Staff with a basis who are NOT candidates (inactive / removed) — owed via final pay (CUT → manual).
            $candidates = $this->candidates($spa)->keyBy('id');
            foreach ($basis as $staffId => $b) {
                if (! $candidates->has($staffId) && Decimal::isPositive($b['sum'])) {
                    $warnings[] = new RunWarning(RunWarning::NOT_ELIGIBLE_13TH, sprintf(
                        '%s: not active, but earned a 13th-month basis of ₱%s in %d. Pro-rated 13th-month pay is part of final pay, which is handled manually.',
                        ($names[$staffId] ?? "Staff #{$staffId}"), Decimal::peso($b['sum']), $year,
                    ), $staffId);
                }
            }

            foreach ($candidates as $staff) {
                $who = $this->who($staff, $names);
                $staffId = (int) $staff->id;
                $b = $basis[$staffId] ?? ['sum' => '0', 'runs' => 0];

                $home = $staff->branch_id ? Branch::withTrashed()->find($staff->branch_id) : null;
                if ($home === null || $home->trashed()) {
                    $warnings[] = new RunWarning(RunWarning::HOME_BRANCH_MISSING, "{$who}: home branch is missing or deleted — no 13th-month payslip.", $staffId);
                    continue;
                }
                if (! $home->has_workforce_finance_suite) {
                    if (Decimal::isPositive($b['sum'])) {
                        $warnings[] = new RunWarning(RunWarning::NOT_IN_SUITE_BRANCH,
                            "{$who}: home branch \"{$home->name}\" does not have the Workforce & Finance Suite — no 13th-month payslip.", $staffId);
                    }
                    continue;
                }

                // "At least one month of service in the year" (PD 851 IRR) — hire on or before Dec 1.
                $hire = $staff->hire_date ? Carbon::parse($staff->hire_date)->toDateString() : null;
                if ($hire !== null && $hire > "{$year}-12-01") {
                    $warnings[] = new RunWarning(RunWarning::NOT_ELIGIBLE_13TH, "{$who}: hired {$hire}, less than one month of service in {$year}.", $staffId);
                    continue;
                }
                if ($hire === null) {
                    $warnings[] = new RunWarning(RunWarning::NOT_ELIGIBLE_13TH,
                        "{$who}: no hire date on file, so one month of service could not be checked — included based on payroll history.", $staffId);
                }

                if (! Decimal::isPositive($b['sum'])) {
                    $warnings[] = new RunWarning(RunWarning::NOT_ELIGIBLE_13TH,
                        "{$who}: no 13th-month basis in finalized/released {$year} runs — no payslip.", $staffId);
                    continue;
                }

                $amount = Decimal::round2(Decimal::div($b['sum'], '12'));   // rounding half-up — UNVERIFIED (no source)
                $payslip = $this->upsertPayslip($run, $staff, $existing[$staffId] ?? null, [
                    'home_branch_id' => $home->id,
                    'days_worked'    => '0.00',
                    'is_mwe'         => false,   // not used on this run (no WTAX line)
                    'snapshot'       => [
                        'eligibility'  => ['eligible' => true, 'reason' => null],
                        'home_branch'  => $this->branchSnapshot($home),
                        'spa_settings' => $this->spaSnapshot($spa),
                        'basis'        => ['codes' => $basisCodes, 'run_ids' => $b['run_ids'] ?? [], 'hire_date' => $hire],
                    ],
                ]);

                PayslipLine::create([
                    'payslip_id'     => $payslip->id,
                    'component_code' => 'THIRTEENTH_MONTH',
                    'label'          => $this->calc->component('THIRTEENTH_MONTH')->label.' '.$year,
                    'kind'           => PayslipLine::KIND_EARNING,
                    'branch_id'      => $home->id,
                    'quantity'       => '1.00',
                    'rate'           => $amount,
                    'amount'         => $amount,
                    'is_manual'      => false,
                    'note'           => mb_substr(sprintf('₱%s ÷ 12 — %s lines in %d finalized/released run(s) of %d',
                        Decimal::peso($b['sum']), implode(', ', $basisCodes), $b['runs'], $year), 0, 255),
                ]);

                array_push($warnings, ...$this->settler->settle($run, $payslip, $names[$staffId] ?? null));
                $processed[$staffId] = true;
            }

            array_push($warnings, ...$this->handleLeftovers($run, $existing, $processed, $names));

            return new RunResult($run->refresh(), $regenerated, $this->totals($run), $this->dedupe($warnings));
        });
    }

    // =====================================================================
    // 5. Manual adjustment lines
    // =====================================================================

    /**
     * Add ADJ_EARNING / ADJ_EARNING_NONTAX / ADJ_DEDUCTION to a payslip of a draft run,
     * then recompute that payslip (contributions on cutoff 2, WTAX, floor, totals).
     *
     * @param string|int $amount decimal string > 0 (floats rejected)
     */
    public function addManualLine(Payslip $payslip, string $code, string $label, string|int $amount, ?string $note, User $by): PayslipSettlement
    {
        $label = trim($label);
        $note = $note === null ? null : trim($note);
        $errors = [];

        if (! in_array($code, self::MANUAL_CODES, true)) {
            $errors['component_code'] = 'Manual lines must be ADJ_EARNING, ADJ_EARNING_NONTAX or ADJ_DEDUCTION.';
        }
        if ($label === '' || mb_strlen($label) > 100) {
            $errors['label'] = 'A label of 1 to 100 characters is required.';
        }
        if ($note !== null && mb_strlen($note) > 255) {
            $errors['note'] = 'The note cannot exceed 255 characters.';
        }
        try {
            $amount = Decimal::round2(Decimal::of($amount, 'amount'));
            if (! Decimal::isPositive($amount) || Decimal::cmp($amount, '9999999999.99') > 0) {
                $errors['amount'] = 'Amount must be more than zero (use ADJ_DEDUCTION to take money off).';
            }
        } catch (InvalidArgumentException) {
            $errors['amount'] = 'Amount must be a plain number, e.g. 1500.00.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($payslip, $code, $label, $amount, $note, $by): PayslipSettlement {
            $run = $this->lockDraftRunForEdit((int) $payslip->payroll_run_id);

            if ($run->run_type === PayrollRun::TYPE_THIRTEENTH_MONTH && $code === 'ADJ_EARNING') {
                // No withholding rule exists for a stand-alone 13th-month run (see PayslipSettler).
                throw ValidationException::withMessages([
                    'component_code' => 'Taxable adjustments cannot go on the 13th-month run; add them to a regular run.',
                ]);
            }

            $payslip = Payslip::query()->whereKey($payslip->getKey())->firstOrFail();
            $c = $this->calc->component($code);

            PayslipLine::create([
                'payslip_id'     => $payslip->id,
                'component_code' => $code,
                'label'          => $label,
                'kind'           => $c->kind,
                'branch_id'      => $payslip->home_branch_id,
                'quantity'       => '1.00',
                'rate'           => $amount,
                'amount'         => $amount,
                'is_manual'      => true,
                'created_by'     => $by->getKey(),
                'note'           => $note === '' ? null : $note,
            ]);

            $warnings = $this->settler->settle($run, $payslip, $this->staffName((int) $payslip->staff_id));

            return new PayslipSettlement($payslip->refresh(), $warnings);
        });
    }

    /**
     * Remove a manual line from a draft run and recompute the payslip. An orphaned
     * payslip (kept only for its manual lines) is deleted when its last line goes.
     */
    public function removeManualLine(PayslipLine $line): PayslipSettlement
    {
        return DB::transaction(function () use ($line): PayslipSettlement {
            $line = PayslipLine::query()->whereKey($line->getKey())->firstOrFail();
            if (! $line->is_manual) {
                throw new PayrollStateException('Only manual adjustment lines can be removed; computed lines change by regenerating the draft.');
            }

            $payslip = Payslip::query()->whereKey($line->payslip_id)->firstOrFail();
            $run = $this->lockDraftRunForEdit((int) $payslip->payroll_run_id);

            $line->delete();

            $orphan = ! (bool) ($payslip->snapshot['eligibility']['eligible'] ?? true);
            if ($orphan && ! $payslip->lines()->where('is_manual', true)->exists()) {
                $payslip->lines()->delete();   // leftover owned lines (e.g. WTAX); run is draft and locked
                $payslip->delete();

                return new PayslipSettlement(null, []);
            }

            $warnings = $this->settler->settle($run, $payslip, $this->staffName((int) $payslip->staff_id));

            return new PayslipSettlement($payslip->refresh(), $warnings);
        });
    }

    // =====================================================================
    // 6. Transitions (guards: PayrollRun::TRANSITIONS, Unit 1)
    // =====================================================================

    public function approve(PayrollRun $run, User $by): PayrollRun
    {
        return $this->transition($run, PayrollRun::STATUS_APPROVED, $by, fn (PayrollRun $r) => $r->forceFill([
            'approved_by' => $by->getKey(), 'approved_at' => now(),
        ]));
    }

    /**
     * approved → draft. The model clears approved_by / approved_at; the locked model has
     * no column for who sent it back, so the actor is written to the application log.
     */
    public function sendBackToDraft(PayrollRun $run, User $by): PayrollRun
    {
        return $this->transition($run, PayrollRun::STATUS_DRAFT, $by, fn (PayrollRun $r) => null);
    }

    public function finalize(PayrollRun $run, User $by): PayrollRun
    {
        return $this->transition($run, PayrollRun::STATUS_FINALIZED, $by, fn (PayrollRun $r) => $r->forceFill([
            'finalized_by' => $by->getKey(), 'finalized_at' => now(),
        ]));
    }

    public function release(PayrollRun $run, User $by): PayrollRun
    {
        return $this->transition($run, PayrollRun::STATUS_RELEASED, $by, fn (PayrollRun $r) => $r->forceFill([
            'released_by' => $by->getKey(), 'released_at' => now(),
        ]));
    }

    // =====================================================================
    // 7. Monthly contribution summary
    // =====================================================================

    /**
     * One month's government figures, from FINALIZED/RELEASED runs only, per staff and
     * totals. Two groupings, because the agencies group differently (rates sheet §13):
     *
     *  - Contributions (SSS EE/ER/EC, PhilHealth EE/ER, Pag-IBIG EE/ER): by APPLICABLE
     *    month = regular runs whose period starts in the month (they sit on cutoff 2).
     *  - WTAX: by the month tax was WITHHELD = runs whose PAY DATE is in the month, any
     *    run type (BIR Form 1601-C). Cutoff 2 of September is paid in October, so its
     *    WTAX is in October's figure.
     *
     * For each employee-share code and WTAX, `<CODE>` is the amount DUE (what must be
     * remitted) and `<CODE>_withheld` is what was actually deducted; they differ only
     * when the net-pay floor cut a deduction short — the employer advances the gap.
     * Employer-share codes have only `<CODE>` (never floored). Branch filter = payslip
     * home branch. Staff paid in the month's runs appear even with all zeros (they are
     * reported as "no earnings" to the agencies).
     *
     * @return array{
     *   year: int, month: int, branch_id: ?int,
     *   contribution_runs: list<array{id: int, cutoff_no: int, status: string, included: bool}>,
     *   wtax_runs: list<array{id: int, run_type: string, cutoff_no: ?int, pay_date: string, status: string, included: bool}>,
     *   complete: bool,
     *   rows: list<array<string, mixed>>,
     *   totals: array<string, string>
     * }
     */
    public function monthlyContributionSummary(Spa $spa, int $year, int $month, ?int $branchId = null): array
    {
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd = Carbon::parse($monthStart)->endOfMonth()->toDateString();

        $contribRuns = PayrollRun::query()
            ->where('spa_id', $spa->id)
            ->where('run_type', PayrollRun::TYPE_REGULAR)
            ->whereDate('period_start', '>=', $monthStart)
            ->whereDate('period_start', '<=', $monthEnd)
            ->orderBy('cutoff_no')
            ->get(['id', 'cutoff_no', 'status']);

        $wtaxRuns = PayrollRun::query()
            ->where('spa_id', $spa->id)
            ->whereDate('pay_date', '>=', $monthStart)
            ->whereDate('pay_date', '<=', $monthEnd)
            ->orderBy('pay_date')
            ->get(['id', 'run_type', 'cutoff_no', 'pay_date', 'status']);

        $payable = fn (PayrollRun $r): bool => in_array($r->status, self::PAYABLE_STATUSES, true);
        $contribIds = $contribRuns->filter($payable)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $wtaxIds = $wtaxRuns->filter($payable)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $withheldCodes = ['SSS_EE', 'PHIC_EE', 'HDMF_EE', 'WTAX'];
        $zero = [];
        foreach (self::SUMMARY_CODES as $code) {
            $zero[$code] = '0.00';
            if (in_array($code, $withheldCodes, true)) {
                $zero[$code.'_withheld'] = '0.00';
            }
        }

        $byStaff = [];
        $totals = $zero;
        $addRow = function (int $id, ?string $first, ?string $last) use (&$byStaff, $zero): void {
            $byStaff[$id] ??= ['staff_id' => $id, 'name' => trim(($first ?? '').' '.($last ?? '')) ?: "Staff #{$id}"] + $zero;
        };
        $add = function (int $id, string $key, mixed $value) use (&$byStaff, &$totals): void {
            $amt = Decimal::round2(Decimal::fromDb($value));
            $byStaff[$id][$key] = Decimal::round2(Decimal::add(Decimal::of($byStaff[$id][$key]), $amt));
            $totals[$key] = Decimal::round2(Decimal::add(Decimal::of($totals[$key]), $amt));
        };

        // Everyone paid in the month's contribution runs gets a row (zeros included).
        DB::table('payslips')
            ->join('staff', 'staff.id', '=', 'payslips.staff_id')
            ->leftJoin('users', 'users.id', '=', 'staff.user_id')
            ->whereIn('payslips.payroll_run_id', $contribIds === [] ? [0] : $contribIds)
            ->when($branchId !== null, fn ($q) => $q->where('payslips.home_branch_id', $branchId))
            ->distinct()
            ->get(['payslips.staff_id', 'users.first_name', 'users.last_name'])
            ->each(fn ($r) => $addRow((int) $r->staff_id, $r->first_name, $r->last_name));

        $sums = function (array $runIds, array $codes) use ($branchId) {
            return DB::table('payslip_lines')
                ->join('payslips', 'payslips.id', '=', 'payslip_lines.payslip_id')
                ->join('staff', 'staff.id', '=', 'payslips.staff_id')
                ->leftJoin('users', 'users.id', '=', 'staff.user_id')
                ->whereIn('payslips.payroll_run_id', $runIds === [] ? [0] : $runIds)
                ->whereIn('payslip_lines.component_code', $codes)
                ->when($branchId !== null, fn ($q) => $q->where('payslips.home_branch_id', $branchId))
                ->groupBy('payslips.staff_id', 'users.first_name', 'users.last_name', 'payslip_lines.component_code')
                ->get([
                    'payslips.staff_id', 'users.first_name', 'users.last_name', 'payslip_lines.component_code',
                    // DUE = quantity × rate (the scheduled amount); WITHHELD = amount (after the floor).
                    DB::raw('SUM(payslip_lines.quantity * payslip_lines.rate) as due'),
                    DB::raw('SUM(payslip_lines.amount) as withheld'),
                ]);
        };

        $contribCodes = array_values(array_diff(self::SUMMARY_CODES, ['WTAX']));
        foreach ([[$contribIds, $contribCodes], [$wtaxIds, ['WTAX']]] as [$ids, $codes]) {
            foreach ($sums($ids, $codes) as $r) {
                $id = (int) $r->staff_id;
                $addRow($id, $r->first_name, $r->last_name);
                $add($id, $r->component_code, $r->due);
                if (in_array($r->component_code, $withheldCodes, true)) {
                    $add($id, $r->component_code.'_withheld', $r->withheld);
                }
            }
        }

        uasort($byStaff, fn (array $a, array $b) => [$a['name'], $a['staff_id']] <=> [$b['name'], $b['staff_id']]);
        $c2 = $contribRuns->firstWhere('cutoff_no', 2);

        return [
            'year'              => $year,
            'month'             => $month,
            'branch_id'         => $branchId,
            'contribution_runs' => $contribRuns->map(fn (PayrollRun $r) => [
                'id' => (int) $r->id, 'cutoff_no' => (int) $r->cutoff_no, 'status' => $r->status,
                'included' => in_array((int) $r->id, $contribIds, true),
            ])->values()->all(),
            'wtax_runs'         => $wtaxRuns->map(fn (PayrollRun $r) => [
                'id' => (int) $r->id, 'run_type' => $r->run_type, 'cutoff_no' => $r->cutoff_no === null ? null : (int) $r->cutoff_no,
                'pay_date' => $r->pay_date->toDateString(), 'status' => $r->status,
                'included' => in_array((int) $r->id, $wtaxIds, true),
            ])->values()->all(),
            // Contributions exist only on cutoff 2; the month is complete once it is finalized.
            'complete'          => $c2 !== null && $payable($c2),
            'rows'              => array_values($byStaff),
            'totals'            => $totals,
        ];
    }

    // =====================================================================
    // 8. 13th-month balance (read-only)
    // =====================================================================

    /**
     * The 13th-month run must be paid by December 24, before December cutoff 2 is
     * finalized, so its basis misses late-December earnings. After those runs are
     * finalized, this returns, per staff member paid on the year's finalized/released
     * 13th-month run: owed = full-year basis ÷ 12, paid, and balance = owed − paid.
     * The spa pays a positive balance as an ADJ_EARNING_NONTAX line in the next regular
     * run (label it e.g. "13th month 2026 balance"). Paying the earned-by-then amount on
     * time and truing up after is a common-practice DESIGN choice — no DOLE text on the
     * December true-up was found.
     *
     * @return array{year: int, thirteenth_run_id: ?int, pending_cutoffs: list<string>,
     *               rows: list<array{staff_id: int, name: string, basis: string, owed: string, paid: string, balance: string}>}
     */
    public function thirteenthMonthBalance(Spa $spa, int $year): array
    {
        $run = PayrollRun::query()
            ->where('spa_id', $spa->id)
            ->where('run_type', PayrollRun::TYPE_THIRTEENTH_MONTH)
            ->whereDate('period_start', "{$year}-01-01")
            ->whereIn('status', self::PAYABLE_STATUSES)
            ->first();

        $out = ['year' => $year, 'thirteenth_run_id' => $run ? (int) $run->id : null, 'pending_cutoffs' => $this->unfinalizedCutoffs($spa, $year) ?? [], 'rows' => []];
        if ($run === null) {
            return $out;
        }

        $paid = DB::table('payslip_lines')
            ->join('payslips', 'payslips.id', '=', 'payslip_lines.payslip_id')
            ->where('payslips.payroll_run_id', $run->id)
            ->where('payslip_lines.component_code', 'THIRTEENTH_MONTH')
            ->groupBy('payslips.staff_id')
            ->get(['payslips.staff_id', DB::raw('SUM(payslip_lines.amount) as paid')])
            ->keyBy(fn ($r) => (int) $r->staff_id);

        $basis = $this->thirteenthBasis($spa, $year);
        $names = $this->staffNames($spa);

        foreach ($paid as $staffId => $r) {
            $sum = $basis[$staffId]['sum'] ?? '0';
            $owed = Decimal::round2(Decimal::div($sum, '12'));
            $paidAmt = Decimal::round2(Decimal::fromDb($r->paid));
            $out['rows'][] = [
                'staff_id' => $staffId,
                'name'     => ($names[$staffId] ?? '') ?: "Staff #{$staffId}",
                'basis'    => Decimal::round2($sum),
                'owed'     => $owed,
                'paid'     => $paidAmt,
                'balance'  => Decimal::round2(Decimal::max('0', Decimal::sub($owed, $paidAmt))),
            ];
        }

        return $out;
    }

    // =====================================================================
    // Internals — runs
    // =====================================================================

    private function regularRunInMonth(Spa $spa, int $cutoffNo, string $monthStart, string $monthEnd): ?PayrollRun
    {
        return PayrollRun::query()
            ->where('spa_id', $spa->id)
            ->where('run_type', PayrollRun::TYPE_REGULAR)
            ->where('cutoff_no', $cutoffNo)
            ->whereBetween('period_start', [$monthStart, $monthEnd])
            ->lockForUpdate()
            ->first();
    }

    /**
     * Create the run, or refresh a draft's header for regeneration.
     *
     * @return array{0: PayrollRun, 1: bool} [run, regenerated]
     */
    private function openRun(Spa $spa, string $type, PayPeriod $period, ?PayrollRun $run, User $by): array
    {
        $header = [
            'period_start'   => $period->start,
            'period_end'     => $period->end,
            'pay_date'       => $period->payDate,
            'config_version' => $this->calc->version(),
            'generated_by'   => $by->getKey(),
        ];

        if ($run !== null) {
            if (! $run->canRegenerate()) {
                throw new PayrollStateException(sprintf(
                    'This %s run (%s to %s) is %s and cannot be regenerated. Put corrections in a later run as manual adjustment lines.',
                    str_replace('_', '-', $run->run_type), $run->period_start->toDateString(), $run->period_end->toDateString(), $run->status,
                ));
            }
            $run->forceFill($header)->save();

            return [$run, true];
        }

        return [PayrollRun::create($header + [
            'spa_id'    => $spa->id,
            'run_type'  => $type,
            'cutoff_no' => $period->cutoffNo,
            'status'    => PayrollRun::STATUS_DRAFT,
        ]), false];
    }

    /** Lock a run for a manual-line change and check it is draft and on the current config. */
    private function lockDraftRunForEdit(int $runId): PayrollRun
    {
        $run = PayrollRun::query()->whereKey($runId)->lockForUpdate()->firstOrFail();

        if (! $run->isDraft()) {
            throw new PayrollStateException("Payroll run #{$run->id} is {$run->status}; manual lines can only change on a draft run.");
        }
        if ($run->config_version !== $this->calc->version()) {
            // Recomputing with a newer config than the run records would mix rule sets.
            throw new PayrollStateException(sprintf(
                'Payroll rates changed since this draft was generated (run uses %s, current is %s). Regenerate the draft first.',
                $run->config_version, $this->calc->version(),
            ));
        }

        return $run;
    }

    private function transition(PayrollRun $run, string $to, User $by, callable $stamp): PayrollRun
    {
        return DB::transaction(function () use ($run, $to, $by, $stamp): PayrollRun {
            $fresh = PayrollRun::query()->whereKey($run->getKey())->lockForUpdate()->firstOrFail();
            $from = $fresh->status;

            if (! $fresh->canTransitionTo($to)) {
                throw new PayrollStateException("Payroll run #{$fresh->id} is {$from} and cannot move to {$to}.");
            }

            $stamp($fresh);
            $fresh->status = $to;
            $fresh->save();   // model re-validates the transition and actor columns

            // Audit trail for every step, including send-back (no column for its actor).
            // self_approved: the generator approved their own run. Not blocked — most target
            // spas have ~10 staff and one owner-admin; owner review is the usual compensating
            // control for small teams (decision C). Unit 6 shows it as a badge.
            Log::info('payroll.run.transition', [
                'run_id' => (int) $fresh->id, 'spa_id' => (int) $fresh->spa_id,
                'from' => $from, 'to' => $to, 'user_id' => (int) $by->getKey(),
                'self_approved' => $to === PayrollRun::STATUS_APPROVED && (int) $fresh->generated_by === (int) $by->getKey(),
            ]);

            return $fresh;
        });
    }

    /**
     * Deletes every computed line of the run (manual lines stay) and returns the
     * existing payslips keyed by staff_id.
     *
     * @return array<int, Payslip>
     */
    private function clearComputedLines(PayrollRun $run): array
    {
        $payslips = $run->payslips()->get()->keyBy('staff_id')->all();

        if ($payslips !== []) {
            PayslipLine::query()
                ->whereIn('payslip_id', array_map(fn (Payslip $p) => $p->id, $payslips))
                ->where('is_manual', false)
                ->delete();
        }

        return array_combine(array_map('intval', array_keys($payslips)), array_values($payslips)) ?: [];
    }

    /** @param array<string, mixed> $attrs */
    private function upsertPayslip(PayrollRun $run, Staff $staff, ?Payslip $existing, array $attrs): Payslip
    {
        $zero = ['gross_pay' => '0.00', 'total_deductions' => '0.00', 'net_pay' => '0.00', 'taxable_compensation' => '0.00'];

        if ($existing !== null) {
            // Rebuilt in place, so manual lines keep their payslip_id (the "re-attach").
            $existing->forceFill($attrs + $zero)->save();

            return $existing;
        }

        return Payslip::create($attrs + $zero + ['payroll_run_id' => $run->id, 'staff_id' => $staff->id]);
    }

    /**
     * Existing payslips whose staff member was not paid this time: delete them, unless
     * they hold manual lines — then keep them with only those lines and warn.
     *
     * @param array<int, Payslip> $existing
     * @param array<int, bool>    $processed
     * @param array<int, string>  $names
     * @return list<RunWarning>
     */
    private function handleLeftovers(PayrollRun $run, array $existing, array $processed, array $names): array
    {
        $warnings = [];

        foreach ($existing as $staffId => $payslip) {
            if (isset($processed[$staffId])) {
                continue;
            }

            $manual = $payslip->lines()->where('is_manual', true)->count();
            if ($manual === 0) {
                $payslip->delete();
                continue;
            }

            $snapshot = $payslip->snapshot ?? [];
            $snapshot['eligibility'] = ['eligible' => false, 'reason' => 'Not eligible at regeneration; kept for manual lines.'];
            $payslip->forceFill([
                'is_mwe'      => false,
                'days_worked' => '0.00',
                'snapshot'    => $snapshot,
            ])->save();

            $who = ($names[$staffId] ?? "Staff #{$staffId}")." (staff #{$staffId})";
            $warnings[] = new RunWarning(RunWarning::ORPHANED_MANUAL_LINES,
                "{$who}: no longer eligible for this run (see the other warnings for why), but the payslip has {$manual} manual line(s). It was kept with only those lines — remove them if they should not be paid.", $staffId);

            array_push($warnings, ...$this->settler->settle($run, $payslip, $names[$staffId] ?? null));
        }

        return $warnings;
    }

    /** @return array{payslips: int, gross: string, deductions: string, net: string, employer_share: string} */
    private function totals(PayrollRun $run): array
    {
        $p = DB::table('payslips')->where('payroll_run_id', $run->id)
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(gross_pay),0) as g, COALESCE(SUM(total_deductions),0) as d, COALESCE(SUM(net_pay),0) as net')
            ->first();
        $er = DB::table('payslip_lines')
            ->join('payslips', 'payslips.id', '=', 'payslip_lines.payslip_id')
            ->where('payslips.payroll_run_id', $run->id)
            ->where('payslip_lines.kind', PayslipLine::KIND_EMPLOYER_SHARE)
            ->sum('payslip_lines.amount');

        return [
            'payslips'       => (int) $p->n,
            'gross'          => Decimal::round2(Decimal::fromDb($p->g)),
            'deductions'     => Decimal::round2(Decimal::fromDb($p->d)),
            'net'            => Decimal::round2(Decimal::fromDb($p->net)),
            'employer_share' => Decimal::round2(Decimal::fromDb($er)),
        ];
    }

    /** @return list<RunWarning> */
    private function periodWarnings(Spa $spa, PayrollRun $run): array
    {
        $warnings = [];
        $end = $run->period_end->toDateString();

        if ($end > now()->toDateString()) {
            $warnings[] = new RunWarning(RunWarning::PERIOD_NOT_ENDED,
                "The period ends {$end}, which is after today — attendance and bookings may be incomplete. Regenerate after it ends.");
        }

        // Art. 103 across runs: the pay-date gap to the previous regular run (catches an
        // offset change between runs; the per-period check is in buildPeriod()).
        $prev = PayrollRun::query()
            ->where('spa_id', $spa->id)
            ->where('run_type', PayrollRun::TYPE_REGULAR)
            ->where('period_start', '<', $run->period_start->toDateString())
            ->orderByDesc('period_start')
            ->first(['id', 'pay_date', 'period_end']);

        if ($prev !== null && $prev->period_end->copy()->addDay()->isSameDay($run->period_start)) {
            $gap = (int) $prev->pay_date->diffInDays($run->pay_date);
            if ($gap > $this->maxPayIntervalDays()) {
                $warnings[] = new RunWarning(RunWarning::PAY_INTERVAL, sprintf(
                    'Pay date %s is %d days after the previous run\'s pay date (%s). Labor Code Art. 103 allows at most %d days — the pay-day offset may have changed.',
                    $run->pay_date->toDateString(), $gap, $prev->pay_date->toDateString(), $this->maxPayIntervalDays(),
                ));
            }
        }

        return $warnings;
    }

    // =====================================================================
    // Internals — staff
    // =====================================================================

    /** Active, non-deleted staff of the spa (the locked "active" test). @return Collection<int, Staff> */
    private function candidates(Spa $spa): Collection
    {
        return Staff::query()
            ->where('spa_id', $spa->id)
            ->where('employment_status', 'active')
            ->orderBy('id')
            ->get();
    }

    /**
     * Locked eligibility: active (already filtered), home branch has the suite, and a pay
     * profile effective in the period. v3.1: a daily-paid profile with no DOLE factor
     * (0 or 3+ rest days) is skipped. Also: home branch min_daily_wage must be set.
     *
     * @return array{warning: ?RunWarning, home?: Branch, timeline?: ProfileTimeline}
     */
    private function regularEligibility(Staff $staff, PayrollRun $run, string $who): array
    {
        $id = (int) $staff->id;
        $start = $run->period_start->toDateString();
        $end = $run->period_end->toDateString();

        $home = $staff->branch_id ? Branch::withTrashed()->find($staff->branch_id) : null;
        if ($home === null || $home->trashed()) {
            return ['warning' => new RunWarning(RunWarning::HOME_BRANCH_MISSING, "{$who}: home branch is missing or deleted — not paid.", $id)];
        }
        if (! $home->has_workforce_finance_suite) {
            return ['warning' => new RunWarning(RunWarning::NOT_IN_SUITE_BRANCH,
                "{$who}: home branch \"{$home->name}\" does not have the Workforce & Finance Suite — not paid by this run.", $id)];
        }

        $timeline = $this->rates->timelineFor($staff, $end);
        $inPeriod = $timeline->overlapping($start, $end);
        if ($inPeriod === []) {
            return ['warning' => new RunWarning(RunWarning::MISSING_PROFILE, "{$who}: no pay profile in effect between {$start} and {$end} — not paid.", $id)];
        }

        foreach ($inPeriod as $p) {
            if ($p->isDaily()) {
                try {
                    $this->calc->eemrFactor($p->payBasis, count($p->restDays), $end);
                } catch (InvalidArgumentException) {
                    return ['warning' => new RunWarning(RunWarning::MISSING_EEMR_FACTOR, sprintf(
                        '%s: daily-paid pay profile #%d has %d rest day(s) per week; only 1 or 2 have a DOLE days-per-year factor (rates sheet §10). Fix the profile — not paid.',
                        $who, $p->id, count($p->restDays),
                    ), $id)];
                }
            }
        }

        if ($home->min_daily_wage === null) {
            return ['warning' => new RunWarning(RunWarning::MISSING_MIN_WAGE,
                "{$who}: home branch \"{$home->name}\" has no minimum daily wage set — not paid. Enter the wage-order rate for the branch.", $id)];
        }

        return ['warning' => null, 'home' => $home, 'timeline' => $timeline];
    }

    /**
     * LOAN_DEDUCTION / OTHER_DEDUCTION recurring items for the cutoff.
     *  - per_cutoff: once; second_cutoff_only: cutoff 2 only; per_day_worked: amount ×
     *    days worked (present/late, with a pay profile) inside the item's active range.
     *  - authorization_ref is required (Labor Code Art. 113). The model blocks saving
     *    without it; rows that reach the DB anyway are skipped here with a warning.
     *
     * @param list<RunWarning> $warnings
     * @return list<array<string, mixed>> PayslipLine attributes (payslip_id added by caller)
     */
    private function recurringDeductionLines(Staff $staff, PayrollRun $run, ProfileTimeline $timeline, int $homeBranchId, string $who, array &$warnings): array
    {
        $start = $run->period_start->toDateString();
        $end = $run->period_end->toDateString();
        $id = (int) $staff->id;

        $items = StaffRecurringItem::query()
            ->where('staff_id', $staff->id)
            ->where('kind', StaffRecurringItem::KIND_DEDUCTION)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $end)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $start))
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($items as $item) {
            if (blank($item->authorization_ref)) {
                $warnings[] = new RunWarning(RunWarning::DEDUCTION_UNAUTHORIZED,
                    "{$who}: recurring deduction #{$item->id} ({$item->label}) has no written authorization reference (Labor Code Art. 113) — not deducted.", $id);
                continue;
            }
            if (! in_array($item->component_code, self::RECURRING_DEDUCTION_CODES, true)) {
                $warnings[] = new RunWarning(RunWarning::DEDUCTION_INVALID,
                    "{$who}: recurring deduction #{$item->id} ({$item->label}) uses component [{$item->component_code}]; only LOAN_DEDUCTION or OTHER_DEDUCTION are allowed — not deducted.", $id);
                continue;
            }

            $from = max($start, $item->start_date->toDateString());
            $to = $item->end_date ? min($end, $item->end_date->toDateString()) : $end;

            $qty = match ($item->frequency) {
                StaffRecurringItem::FREQ_PER_CUTOFF         => '1',
                StaffRecurringItem::FREQ_SECOND_CUTOFF_ONLY => (int) $run->cutoff_no === 2 ? '1' : '0',
                StaffRecurringItem::FREQ_PER_DAY_WORKED     => (string) $this->workedDaysBetween($staff, $timeline, $from, $to),
                default                                     => null,
            };
            if ($qty === null) {
                $warnings[] = new RunWarning(RunWarning::DEDUCTION_INVALID,
                    "{$who}: recurring deduction #{$item->id} ({$item->label}) has unknown frequency [{$item->frequency}] — not deducted.", $id);
                continue;
            }

            $rate = Decimal::fromDb($item->amount, 'amount');
            if (! Decimal::isPositive($qty) || ! Decimal::isPositive($rate)) {
                continue;
            }

            $c = $this->calc->component($item->component_code);
            $note = match ($item->frequency) {
                StaffRecurringItem::FREQ_PER_DAY_WORKED     => '₱'.Decimal::peso($rate)." × {$qty} day(s) worked",
                StaffRecurringItem::FREQ_SECOND_CUTOFF_ONLY => 'Second cutoff only',
                default                                     => 'Per cutoff',
            };

            $out[] = [
                'component_code' => $c->code,
                'label'          => mb_substr($c->label.' — '.$item->label, 0, 100),
                'kind'           => $c->kind,
                'branch_id'      => $homeBranchId,
                'quantity'       => Decimal::round2($qty),
                'rate'           => Decimal::round4($rate),
                'amount'         => Decimal::round2(Decimal::mul($qty, $rate)),   // scheduled; the floor rewrites it
                'source_type'    => PayslipLine::SOURCE_RECURRING_ITEM,
                'source_id'      => $item->id,
                'note'           => mb_substr($note.' · auth. ref. '.$item->authorization_ref, 0, 255),
            ];
        }

        return $out;
    }

    /** Distinct present/late dates in [from, to] that a pay profile covers (same test as Unit 3). */
    private function workedDaysBetween(Staff $staff, ProfileTimeline $timeline, string $from, string $to): int
    {
        if ($from > $to) {
            return 0;
        }

        return DB::table('staff_attendance')
            ->where('staff_id', $staff->id)
            ->whereNull('deleted_at')
            ->whereIn('status', ['present', 'late'])
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->pluck('date')
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->unique()
            ->filter(fn (string $d) => $timeline->on($d) !== null)
            ->count();
    }

    /** @return list<RunWarning> */
    private function statutoryIdWarnings(Staff $staff, string $who): array
    {
        $labels = ['sss_no' => 'SSS', 'philhealth_no' => 'PhilHealth', 'pagibig_no' => 'Pag-IBIG', 'tin' => 'TIN'];
        $missing = [];

        foreach ($labels as $attr => $label) {
            try {
                if (blank($staff->{$attr})) {
                    $missing[] = $label;
                }
            } catch (DecryptException) {
                $missing[] = "{$label} (unreadable)";
            }
        }

        return $missing === [] ? [] : [new RunWarning(RunWarning::MISSING_STATUTORY_ID,
            "{$who}: missing ".implode(', ', $missing).' number — contributions and tax are still computed, but remittance needs them.', (int) $staff->id)];
    }

    /**
     * Inactive staff are not candidates (locked "active" test), but if they worked in the
     * period they are owed wages through final pay, which is CUT → handled manually.
     *
     * @param array<int, string> $names
     * @return list<RunWarning>
     */
    private function inactiveWithAttendance(Spa $spa, PayrollRun $run, array $names): array
    {
        $ids = DB::table('staff')
            ->join('staff_attendance', 'staff_attendance.staff_id', '=', 'staff.id')
            ->where('staff.spa_id', $spa->id)
            ->where('staff.employment_status', '!=', 'active')
            ->whereNull('staff.deleted_at')
            ->whereNull('staff_attendance.deleted_at')
            ->whereIn('staff_attendance.status', ['present', 'late'])
            ->whereDate('staff_attendance.date', '>=', $run->period_start->toDateString())
            ->whereDate('staff_attendance.date', '<=', $run->period_end->toDateString())
            ->distinct()
            ->pluck('staff.id');

        return $ids->map(fn ($sid) => new RunWarning(RunWarning::INACTIVE_WITH_ATTENDANCE, sprintf(
            '%s (staff #%d): marked inactive but has attendance in this period — not paid here; final pay is handled manually.',
            ($names[(int) $sid] ?? '') ?: 'Staff', (int) $sid,
        ), (int) $sid))->values()->all();
    }

    /**
     * Staff paid in cutoff 1 who have no cutoff-2 payslip: contributions are computed on
     * cutoff 2 only, so their month has none.
     *
     * @param array<int, bool>   $processed
     * @param array<int, string> $names
     * @return list<RunWarning>
     */
    private function missingFromCutoffTwo(PayrollRun $run, array $processed, array $names): array
    {
        $c1StaffIds = DB::table('payslips')
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payslips.payroll_run_id')
            ->where('payroll_runs.spa_id', $run->spa_id)
            ->where('payroll_runs.run_type', PayrollRun::TYPE_REGULAR)
            ->where('payroll_runs.cutoff_no', 1)
            ->whereBetween('payroll_runs.period_start', [$run->period_start->format('Y-m-01'), $run->period_end->toDateString()])
            ->pluck('payslips.staff_id');

        $warnings = [];
        foreach ($c1StaffIds as $sid) {
            $sid = (int) $sid;
            if (! isset($processed[$sid])) {
                $warnings[] = new RunWarning(RunWarning::CONTRIBUTIONS_SKIPPED, sprintf(
                    '%s (staff #%d): paid in cutoff 1 but not in this cutoff. Contributions are computed on cutoff 2 only, so none were computed for %s — yet the full month is due whenever there was pay in the month (rates sheet §13). Settle them manually.',
                    $names[$sid] ?? "Staff #{$sid}", $sid, $run->period_start->format('F Y'),
                ), $sid);
            }
        }

        return $warnings;
    }

    // =====================================================================
    // Internals — 13th month
    // =====================================================================

    /** @return list<string> component codes with in_13th_month_basis = true (config) */
    private function basisCodes(): array
    {
        return array_values(array_filter(
            array_keys((array) config('payroll.components')),
            fn (string $code): bool => $this->calc->component($code)->in13thMonthBasis,
        ));
    }

    /** @return array<int, array{sum: string, runs: int, run_ids: list<int>}> keyed by staff_id */
    private function thirteenthBasis(Spa $spa, int $year): array
    {
        $rows = DB::table('payslip_lines')
            ->join('payslips', 'payslips.id', '=', 'payslip_lines.payslip_id')
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payslips.payroll_run_id')
            ->where('payroll_runs.spa_id', $spa->id)
            ->where('payroll_runs.run_type', PayrollRun::TYPE_REGULAR)
            ->whereIn('payroll_runs.status', self::PAYABLE_STATUSES)
            ->whereBetween('payroll_runs.period_start', ["{$year}-01-01", "{$year}-12-31"])
            ->whereIn('payslip_lines.component_code', $this->basisCodes())
            ->where('payslip_lines.kind', PayslipLine::KIND_EARNING)
            ->get(['payslips.staff_id', 'payroll_runs.id as run_id', 'payslip_lines.amount']);

        $out = [];
        foreach ($rows as $r) {
            $sid = (int) $r->staff_id;
            $out[$sid] ??= ['sum' => '0', 'runs' => 0, 'run_ids' => []];
            $out[$sid]['sum'] = Decimal::add($out[$sid]['sum'], Decimal::fromDb($r->amount));
            $out[$sid]['run_ids'][(int) $r->run_id] = (int) $r->run_id;
        }
        foreach ($out as $sid => $b) {
            $out[$sid]['run_ids'] = array_values($b['run_ids']);
            $out[$sid]['runs'] = count($b['run_ids']);
        }

        return $out;
    }

    /**
     * Cutoffs of the year (from the spa's first regular run that year through December
     * cutoff 2) with no finalized/released run: their earnings are missing from the basis.
     *
     * @return list<RunWarning>
     */
    private function unfinalizedCutoffWarnings(Spa $spa, int $year): array
    {
        $missing = $this->unfinalizedCutoffs($spa, $year);

        if ($missing === null) {
            return [new RunWarning(RunWarning::UNFINALIZED_RUNS, "No regular runs exist for {$year}, so every basis is ₱0.")];
        }

        return $missing === [] ? [] : [new RunWarning(RunWarning::UNFINALIZED_RUNS, sprintf(
            'Not in this basis (no finalized run yet): %s. This is expected for late December, since 13th-month pay is due by December 24. After those runs are finalized, use the 13th-month balance to pay the difference as an ADJ_EARNING_NONTAX line in the next regular run.',
            implode(', ', $missing),
        ))];
    }

    /** @return list<string>|null e.g. ['Dec cutoff 2']; null when the year has no regular runs */
    private function unfinalizedCutoffs(Spa $spa, int $year): ?array
    {
        $runs = PayrollRun::query()
            ->where('spa_id', $spa->id)
            ->where('run_type', PayrollRun::TYPE_REGULAR)
            ->whereBetween('period_start', ["{$year}-01-01", "{$year}-12-31"])
            ->get(['period_start', 'cutoff_no', 'status']);

        if ($runs->isEmpty()) {
            return null;
        }

        $done = [];
        foreach ($runs as $r) {
            if (in_array($r->status, self::PAYABLE_STATUSES, true)) {
                $done[$r->period_start->format('n').'-'.$r->cutoff_no] = true;
            }
        }

        $missing = [];
        for ($m = (int) $runs->min(fn ($r) => (int) $r->period_start->format('n')); $m <= 12; $m++) {
            foreach ([1, 2] as $c) {
                if (! isset($done["{$m}-{$c}"])) {
                    $missing[] = Carbon::create($year, $m, 1)->format('M').' cutoff '.$c;
                }
            }
        }

        return $missing;
    }

    // =====================================================================
    // Internals — statutory timing (config/payroll.php `payment_timing`, rates sheet §12)
    // =====================================================================

    private function maxPayIntervalDays(): int
    {
        return (int) $this->timing('max_pay_interval_days');
    }

    private function timing(string $key): string
    {
        $value = config("payroll.payment_timing.{$key}");

        if ($value === null || $value === '') {
            throw new UnexpectedValueException("config/payroll.php is missing payment_timing.{$key} (rates sheet §12). Update the config to version 2026.4 or later.");
        }

        return (string) $value;
    }

    // =====================================================================
    // Internals — snapshots and names
    // =====================================================================

    /**
     * Locked snapshot contents: pay profile, branch min wage, holidays used, spa
     * settings, rule ids used. `eligibility` is added so a payslip kept only for its
     * manual lines can be recognised (see delivery notes).
     *
     * @param list<EarningLine> $lines
     * @return array<string, mixed>
     */
    private function regularSnapshot(Spa $spa, PayrollRun $run, Branch $home, ProfileTimeline $timeline, array $lines): array
    {
        $start = $run->period_start->toDateString();
        $end = $run->period_end->toDateString();

        $ruleIds = [];
        $recurringIds = [];
        foreach ($lines as $l) {
            // CommissionEarnings (Unit 3) writes "rule #<id>" into each COMMISSION note;
            // EarningLine has no rule-id field. Coupled to that format — see delivery notes.
            if ($l->componentCode === 'COMMISSION' && $l->note !== null && preg_match('/rule #(\d+)/', $l->note, $m) === 1) {
                $ruleIds[(int) $m[1]] = (int) $m[1];
            }
            if ($l->sourceType === PayslipLine::SOURCE_RECURRING_ITEM && $l->sourceId !== null) {
                $recurringIds[$l->sourceId] = $l->sourceId;
            }
        }

        return [
            'eligibility'  => ['eligible' => true, 'reason' => null],
            'pay_profiles' => array_map(static fn (PayProfileSnapshot $p): array => [
                'id'                 => $p->id,
                'effective_from'     => $p->effectiveFrom,
                'effective_to'       => $p->effectiveTo,
                'pay_basis'          => $p->payBasis,
                'base_rate'          => Decimal::round2($p->baseRate),
                'commission_enabled' => $p->commissionEnabled,
                'rest_days'          => $p->restDays,
            ], $timeline->overlapping($start, $end)),
            'home_branch'  => $this->branchSnapshot($home),
            'spa_settings' => $this->spaSnapshot($spa),
            'holidays'     => array_map(static fn ($h) => $h->toArray(), $this->calc->holidaysBetween($start, $end)),
            'rule_ids'     => [
                'commission_rules' => array_values($ruleIds),
                'recurring_items'  => array_values($recurringIds),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function branchSnapshot(Branch $b): array
    {
        return [
            'id'                          => (int) $b->id,
            'name'                        => $b->name,
            'min_daily_wage'              => $b->min_daily_wage === null ? null : Decimal::round2(Decimal::fromDb($b->min_daily_wage)),
            'wage_order_ref'              => $b->wage_order_ref,
            'min_wage_effective_from'     => $b->min_wage_effective_from?->toDateString(),
            'has_workforce_finance_suite' => (bool) $b->has_workforce_finance_suite,
        ];
    }

    /** @return array<string, int> */
    private function spaSnapshot(Spa $spa): array
    {
        return [
            'payroll_first_cutoff_day' => (int) $spa->payroll_first_cutoff_day,
            'payroll_pay_day_offset'   => (int) $spa->payroll_pay_day_offset,
        ];
    }

    /** @return array<int, string> staff_id => "First Last" (users table; staff has no name column) */
    private function staffNames(Spa $spa): array
    {
        return DB::table('staff')
            ->leftJoin('users', 'users.id', '=', 'staff.user_id')
            ->where('staff.spa_id', $spa->id)
            ->get(['staff.id', 'users.first_name', 'users.last_name'])
            ->mapWithKeys(fn ($r) => [(int) $r->id => trim(($r->first_name ?? '').' '.($r->last_name ?? ''))])
            ->all();
    }

    private function staffName(int $staffId): ?string
    {
        $r = DB::table('staff')->leftJoin('users', 'users.id', '=', 'staff.user_id')
            ->where('staff.id', $staffId)->first(['users.first_name', 'users.last_name']);

        return $r ? trim(($r->first_name ?? '').' '.($r->last_name ?? '')) : null;
    }

    /** @param array<int, string> $names */
    private function who(Staff $staff, array $names): string
    {
        $name = $names[(int) $staff->id] ?? '';

        return $name !== '' ? "{$name} (staff #{$staff->id})" : "Staff #{$staff->id}";
    }

    /**
     * The settler flags each payslip that meets the RR 11-2018 cumulative-average
     * condition. On a whole run that would repeat the same sentence for most therapists
     * every cutoff, so the per-staff flags are folded into one run-level warning.
     *
     * @param list<RunWarning>   $warnings
     * @param array<int, string> $names
     * @return list<RunWarning>
     */
    private function foldCumulativeAverage(array $warnings, array $names): array
    {
        $ids = [];
        $rest = [];
        foreach ($warnings as $w) {
            if ($w->code === RunWarning::CUMULATIVE_AVERAGE && $w->staffId !== null) {
                $ids[$w->staffId] = $w->staffId;
            } else {
                $rest[] = $w;
            }
        }

        if ($ids === []) {
            return $rest;
        }

        $who = array_map(fn (int $id): string => (($names[$id] ?? '') ?: 'Staff')." (#{$id})", array_values($ids));
        $rest[] = new RunWarning(RunWarning::CUMULATIVE_AVERAGE, sprintf(
            '%d payslip(s) receive supplementary pay (e.g. commission) while regular pay is below the withholding level, or supplementary ≥ regular: %s. RR 11-2018 Sec. 2.79(B)(5)(a) prescribes the cumulative average method for them; Levictas withholds per cutoff (locked rule), so a peak-commission cutoff can over-withhold until the year-end annualization refunds it. Decision E is open.',
            count($ids), implode(', ', $who),
        ));

        return $rest;
    }

    /**
     * @param list<RunWarning> $warnings
     * @return list<RunWarning>
     */
    private function dedupe(array $warnings): array
    {
        $seen = [];

        return array_values(array_filter($warnings, function (RunWarning $w) use (&$seen): bool {
            $key = $w->code.'|'.$w->staffId.'|'.$w->message;
            if (isset($seen[$key])) {
                return false;
            }

            return $seen[$key] = true;
        }));
    }
}
