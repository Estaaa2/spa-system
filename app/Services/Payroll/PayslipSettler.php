<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Exceptions\PayrollStateException;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Services\Payroll\Support\Decimal;
use App\Services\Payroll\ValueObjects\PayProfileSnapshot;
use App\Services\Payroll\ValueObjects\RunWarning;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Settles ONE payslip of a DRAFT run from the lines already on it:
 * contributions (regular cutoff 2), WTAX (regular runs), the net-pay floor, and the
 * payslip totals. Called after generation and after every manual-line change, so a
 * manual change and a regeneration always produce the same numbers.
 *
 * Owned codes: every non-manual SSS_*, PHIC_*, HDMF_* and WTAX line is deleted and
 * rebuilt on each call. All other lines (earnings, recurring deductions, manual
 * lines) are inputs.
 *
 * DEDUCTION LINE CONVENTION: quantity × rate = the SCHEDULED amount; `amount` = the
 * amount actually APPLIED after the net-pay floor. This keeps a manual deduction's
 * entered value intact (in `rate`) while `amount` always sums to total_deductions.
 *
 * Rules (locked data model v3.1 unless noted):
 *  - Contributions on cutoff 2 only, full month. SSS and Pag-IBIG bases = sum of the
 *    month's earning lines (cutoff 1 payslip + this payslip) flagged
 *    in_sss_compensation / in_pagibig_fund_salary. PhilHealth base = MBS from the pay
 *    RATE (EEMR), never summed lines (Advisory 2025-0002; rates sheet §2 Rev. 2).
 *  - WTAX every regular cutoff: Annex E semi-monthly on (taxable earnings, minus
 *    MWE-exempt components when is_mwe, minus employee contributions APPLIED in this
 *    cutoff). No annualization. KNOWN DEVIATION (decision E, open): RR 11-2018
 *    Sec. 2.79(B)(5)(a) says the employer "shall" use the cumulative average method
 *    when regular pay is below the withholding level but supplementary pay is paid,
 *    or supplementary ≥ regular — typical for commission therapists. Such payslips are
 *    flagged with a CUMULATIVE_AVERAGE warning; the amount stays per-cutoff.
 *  - Net pay never below 0: SSS_EE, PHIC_EE, HDMF_EE, WTAX, LOAN_DEDUCTION,
 *    OTHER_DEDUCTION, ADJ_DEDUCTION applied in that order until net = 0; the rest is
 *    a warning (no carry field).
 *  - A month with no earnings has no contributions ("no pay, no contribution" — §13).
 *  - Unapplied employee contributions are still due to the agency (§13): warned, not carried.
 *  - 13th-month runs: no contributions and no WTAX line; taxable_compensation = the
 *    THIRTEENTH_MONTH amount above the annual exclusion (§5; §13 reads it in RR 11-2018).
 *    Tax on it is settled in the year-end annualization (CUT → bookkeeper).
 */
final class PayslipSettler
{
    /** Net-pay floor application order (locked). */
    public const DEDUCTION_ORDER = [
        'SSS_EE', 'PHIC_EE', 'HDMF_EE', 'WTAX', 'LOAN_DEDUCTION', 'OTHER_DEDUCTION', 'ADJ_DEDUCTION',
    ];

    public const EMPLOYEE_CONTRIBUTION_CODES = ['SSS_EE', 'PHIC_EE', 'HDMF_EE'];

    /** Lines this class deletes and rebuilds on every settle. */
    public const OWNED_CODES = ['SSS_EE', 'SSS_ER', 'SSS_EC', 'PHIC_EE', 'PHIC_ER', 'HDMF_EE', 'HDMF_ER', 'WTAX'];

    /** Marker for the floor suffix added to non-manual notes, so re-settling never stacks it. */
    private const FLOOR_MARK = ' · Net-pay floor: ';

    /**
     * "Regular compensation" for RR 11-2018 Sec. 2.79(B)(3) Step 1: "basic salary, fixed
     * allowances … paid to an employee per payroll period" (OFFICIAL text). Every other
     * taxable earning (commission, OT, premiums, ND, holiday pay, adjustments) is
     * "supplementary". The code mapping is Levictas's — DESIGN/UNVERIFIED. Used ONLY to
     * flag the cumulative-average condition (decision E); it never changes an amount.
     */
    private const REGULAR_COMPENSATION_CODES = ['BASIC', 'MINWAGE_TOPUP', 'ALLOWANCE'];

    public function __construct(private readonly StatutoryCalculator $calc)
    {
    }

    /**
     * @param string|null $staffName display name for warnings (not stored)
     * @return list<RunWarning>
     */
    public function settle(PayrollRun $run, Payslip $payslip, ?string $staffName = null): array
    {
        if (! $run->isDraft()) {
            throw new PayrollStateException("Payroll run #{$run->id} is {$run->status}; payslips can only be recomputed on a draft run.");
        }

        $warnings = [];
        $staffId = (int) $payslip->staff_id;
        $who = $staffName !== null && $staffName !== '' ? "{$staffName} (staff #{$staffId})" : "Staff #{$staffId}";
        $payDate = $run->pay_date->toDateString();
        $snapshot = $payslip->snapshot ?? [];
        $eligible = (bool) ($snapshot['eligibility']['eligible'] ?? true);

        // 1. Drop owned lines (query-builder delete: the run is draft and locked by the caller).
        PayslipLine::query()
            ->where('payslip_id', $payslip->id)
            ->where('is_manual', false)
            ->whereIn('component_code', self::OWNED_CODES)
            ->delete();

        /** @var Collection<int, PayslipLine> $lines */
        $lines = $payslip->lines()->orderBy('id')->get();
        $earnings = $lines->where('kind', PayslipLine::KIND_EARNING);

        $gross = '0';
        foreach ($earnings as $l) {
            $gross = Decimal::add($gross, Decimal::fromDb($l->amount));
        }
        $gross = Decimal::round2($gross);

        if (Decimal::cmp($gross, '0') < 0) {
            // Unit 3 caps monthly BASIC at 0 and manual earnings must be > 0, so this is a bug, not data.
            throw new LogicException("Payslip #{$payslip->id} has negative gross pay ({$gross}).");
        }

        // 2. Contributions (regular cutoff 2, eligible payslips only).
        $isRegular = $run->run_type === PayrollRun::TYPE_REGULAR;
        if ($isRegular && (int) $run->cutoff_no === 2) {
            if ($eligible) {
                $this->createContributions($run, $payslip, $earnings, $snapshot, $who, $warnings);
            } else {
                $warnings[] = new RunWarning(RunWarning::CONTRIBUTIONS_SKIPPED,
                    "{$who}: no SSS/PhilHealth/Pag-IBIG computed — the payslip only holds manual lines (staff not eligible).", $staffId);
            }
            $lines = $payslip->lines()->orderBy('id')->get();
        }

        // 3. Net-pay floor, with WTAX computed after the employee contributions are applied.
        $remaining = $gross;
        $applied = '0';
        $deductions = $lines->where('kind', PayslipLine::KIND_DEDUCTION);

        foreach ($this->ordered($deductions, self::EMPLOYEE_CONTRIBUTION_CODES) as $line) {
            $applied = Decimal::add($applied, $this->apply($line, $remaining, $who, $staffId, $warnings));
        }
        $eeApplied = $applied;

        $taxable = '0';
        if ($isRegular) {
            $taxable = $this->taxableEarnings($earnings, (bool) $payslip->is_mwe, $payDate);
            $taxable = Decimal::max('0', Decimal::sub($taxable, $eeApplied));
            $wtax = $this->calc->withholding(Decimal::round2($taxable), 'semi_monthly', $payDate);

            if (Decimal::isPositive($wtax)) {
                $line = $this->createLine($payslip, 'WTAX', $wtax, sprintf(
                    'Annex E semi-monthly on ₱%s taxable%s',
                    Decimal::peso($taxable),
                    $payslip->is_mwe ? ' (MWE: exempt components excluded)' : '',
                ));
                $applied = Decimal::add($applied, $this->apply($line, $remaining, $who, $staffId, $warnings));
            }

            if ($this->meetsCumulativeAverageCondition($earnings, (bool) $payslip->is_mwe, $payDate)) {
                $warnings[] = new RunWarning(RunWarning::CUMULATIVE_AVERAGE, sprintf(
                    '%s: supplementary pay (e.g. commission) with regular pay below the withholding level, or supplementary ≥ regular. RR 11-2018 Sec. 2.79(B)(5)(a) prescribes the cumulative average method; Levictas withholds per cutoff (locked rule) and the year-end annualization settles the difference.',
                    $who,
                ), $staffId);
            }
        } else {
            $taxable = $this->thirteenthTaxable($earnings, $payDate, $who, $staffId, $warnings);
        }

        $rest = $deductions->reject(fn (PayslipLine $l) => in_array($l->component_code, self::EMPLOYEE_CONTRIBUTION_CODES, true)
            || $l->component_code === 'WTAX');
        foreach ($this->ordered($rest, array_slice(self::DEDUCTION_ORDER, 4)) as $line) {
            $applied = Decimal::add($applied, $this->apply($line, $remaining, $who, $staffId, $warnings));
        }
        foreach ($rest->reject(fn (PayslipLine $l) => in_array($l->component_code, self::DEDUCTION_ORDER, true)) as $line) {
            // Not reachable with the configured codes; applied last rather than ignored.
            $applied = Decimal::add($applied, $this->apply($line, $remaining, $who, $staffId, $warnings));
        }

        // 4. Totals.
        $applied = Decimal::round2($applied);
        $payslip->forceFill([
            'gross_pay'            => $gross,
            'total_deductions'     => $applied,
            'net_pay'              => Decimal::round2(Decimal::sub($gross, $applied)),
            'taxable_compensation' => Decimal::round2($taxable),
        ])->save();

        return $warnings;
    }

    // ── Contributions ────────────────────────────────────────────────────────

    /**
     * @param Collection<int, PayslipLine> $earnings this payslip's earning lines
     * @param array<string, mixed>         $snapshot
     * @param list<RunWarning>             $warnings
     */
    private function createContributions(PayrollRun $run, Payslip $payslip, Collection $earnings, array $snapshot, string $who, array &$warnings): void
    {
        $staffId = (int) $payslip->staff_id;
        $month = $run->period_start->format('Y-m');

        // Cutoff 1 of the same month (generateRegular refuses cutoff 2 unless it is finalized/released).
        $c1Lines = PayslipLine::query()
            ->join('payslips', 'payslips.id', '=', 'payslip_lines.payslip_id')
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payslips.payroll_run_id')
            ->where('payroll_runs.spa_id', $run->spa_id)
            ->where('payroll_runs.run_type', PayrollRun::TYPE_REGULAR)
            ->where('payroll_runs.cutoff_no', 1)
            ->whereIn('payroll_runs.status', [PayrollRun::STATUS_FINALIZED, PayrollRun::STATUS_RELEASED])
            ->whereBetween('payroll_runs.period_start', [$month.'-01', $run->period_end->toDateString()])
            ->where('payslips.staff_id', $staffId)
            ->where('payslip_lines.kind', PayslipLine::KIND_EARNING)
            ->get(['payslip_lines.component_code', 'payslip_lines.amount']);

        $monthGross = '0';
        $sssBase = '0';
        $hdmfBase = '0';
        foreach ([...$c1Lines->all(), ...$earnings->all()] as $l) {
            $amt = Decimal::fromDb($l->amount);
            $c = $this->calc->component((string) $l->component_code);
            $monthGross = Decimal::add($monthGross, $amt);
            if ($c->inSssCompensation) {
                $sssBase = Decimal::add($sssBase, $amt);
            }
            if ($c->inPagibigFundSalary) {
                $hdmfBase = Decimal::add($hdmfBase, $amt);
            }
        }

        // "No pay, no contribution" (rates sheet §13): a month with no earnings has no
        // SSS / PhilHealth / Pag-IBIG; the employee is still reported with zero (SSS) or
        // "NE — No Earning" (PhilHealth). PhilHealth Employer's Quick Guide — OFFICIAL;
        // SSS — VERIFY (secondary). Without this check the calculator would charge the
        // minimum SSS MSC and the PhilHealth floor on ₱0 pay.
        if (! Decimal::isPositive(Decimal::round2($monthGross))) {
            $warnings[] = new RunWarning(RunWarning::CONTRIBUTIONS_SKIPPED,
                "{$who}: no earnings in {$month}, so no SSS/PhilHealth/Pag-IBIG is due. Report them as \"no earnings\" for the month when remitting; PhilHealth lets them pay as a voluntary member.", $staffId);

            return;
        }

        $sssBase = Decimal::round2(Decimal::max('0', $sssBase));
        $hdmfBase = Decimal::round2(Decimal::max('0', $hdmfBase));
        $monthDate = $run->period_start->toDateString();
        $c1Note = $c1Lines->isEmpty() ? 'this cutoff only (no cutoff-1 payslip)' : 'cutoffs 1 + 2';

        // SSS — §1. Base = month's in_sss_compensation lines.
        $sss = $this->calc->sss($sssBase, $monthDate);
        $sssNote = sprintf('MSC ₱%s on ₱%s compensation (%s)', Decimal::peso($sss->msc), Decimal::peso($sssBase), $c1Note);
        $this->createLine($payslip, 'SSS_EE', $sss->eeTotal(), $sssNote.sprintf(' · SS ₱%s + MPF ₱%s', Decimal::peso($sss->eeSs), Decimal::peso($sss->eeMpf)));
        $this->createLine($payslip, 'SSS_ER', $sss->erTotal(), $sssNote.sprintf(' · SS ₱%s + MPF ₱%s', Decimal::peso($sss->erSs), Decimal::peso($sss->erMpf)));
        $this->createLine($payslip, 'SSS_EC', $sss->ec, $sssNote);

        // PhilHealth — §2. MBS from the pay rate (EEMR), not from lines.
        [$mbs, $mbsNote] = $this->philhealthMbs($run, $snapshot, $who, $staffId, $warnings);
        $phic = $this->calc->philhealth($mbs, $monthDate);
        $this->createLine($payslip, 'PHIC_EE', $phic->ee, $mbsNote);
        $this->createLine($payslip, 'PHIC_ER', $phic->er, $mbsNote);

        // Pag-IBIG — §3. Base = month's in_pagibig_fund_salary lines.
        $hdmf = $this->calc->pagibig($hdmfBase, $monthDate);
        $hdmfNote = sprintf('Fund salary ₱%s (%s)', Decimal::peso($hdmfBase), $c1Note);
        $this->createLine($payslip, 'HDMF_EE', $hdmf->ee, $hdmfNote);
        $this->createLine($payslip, 'HDMF_ER', $hdmf->er, $hdmfNote);
    }

    /**
     * PhilHealth MBS (data model v3.1 DERIVED; rates sheet §2 Rev. 2, §10), from the pay
     * profile SNAPSHOT effective on the cutoff-2 period_end:
     *  - monthly-paid → base_rate;
     *  - daily-paid   → base_rate × factor ÷ 12 (EEMR; factor 313 / 261 by rest days).
     *                   §2 Rev. 2 marks this OFFICIAL (PhilHealth Circular 2018-0001).
     *  - base_rate 0  → home-branch min_daily_wage × factor ÷ 12 — UNVERIFIED design default.
     *
     * @param array<string, mixed> $snapshot
     * @param list<RunWarning>     $warnings
     * @return array{0: string, 1: string} [MBS, note]
     */
    private function philhealthMbs(PayrollRun $run, array $snapshot, string $who, int $staffId, array &$warnings): array
    {
        $end = $run->period_end->toDateString();
        $profiles = array_map(static fn (array $p): PayProfileSnapshot => new PayProfileSnapshot(
            id: (int) $p['id'],
            effectiveFrom: (string) $p['effective_from'],
            effectiveTo: $p['effective_to'] ?? null,
            payBasis: (string) $p['pay_basis'],
            baseRate: Decimal::of((string) $p['base_rate']),
            commissionEnabled: (bool) $p['commission_enabled'],
            restDays: array_values($p['rest_days'] ?? []),
        ), $snapshot['pay_profiles'] ?? []);

        if ($profiles === []) {
            throw new LogicException("Payslip snapshot has no pay profile; cannot derive the PhilHealth MBS for {$who}.");
        }

        $profile = null;
        foreach ($profiles as $p) {
            if ($p->covers($end)) {
                $profile = $p;
            }
        }
        if ($profile === null) {
            // The locked rule names the profile ON period_end; when it ended earlier in the
            // cutoff, the latest profile in the period is used — UNVERIFIED fallback.
            usort($profiles, static fn (PayProfileSnapshot $a, PayProfileSnapshot $b): int => strcmp($a->effectiveFrom, $b->effectiveFrom));
            $profile = $profiles[array_key_last($profiles)];
            $warnings[] = new RunWarning(RunWarning::UNVERIFIED_RULE,
                "{$who}: no pay profile on {$end}; PhilHealth MBS used the latest profile in the period (#{$profile->id}, UNVERIFIED fallback).", $staffId);
        }

        $factor = $this->calc->eemrFactor($profile->payBasis, count($profile->restDays), $end);

        if (Decimal::isPositive($profile->baseRate)) {
            if ($profile->isDaily()) {
                $mbs = $this->calc->equivalentMonthlyRate(Decimal::round2($profile->baseRate), $factor);

                return [$mbs, sprintf('MBS ₱%s = daily ₱%s × %s ÷ 12 (profile #%d)', Decimal::peso($mbs), Decimal::peso($profile->baseRate), $factor, $profile->id)];
            }
            $mbs = Decimal::round2($profile->baseRate);

            return [$mbs, sprintf('MBS ₱%s = monthly base (profile #%d)', Decimal::peso($mbs), $profile->id)];
        }

        $wage = $snapshot['home_branch']['min_daily_wage'] ?? null;
        if ($wage === null) {
            throw new LogicException("Payslip snapshot has no home-branch min_daily_wage; cannot derive the PhilHealth MBS for {$who}.");
        }
        $mbs = $this->calc->equivalentMonthlyRate(Decimal::round2(Decimal::of((string) $wage)), $factor);
        $warnings[] = new RunWarning(RunWarning::UNVERIFIED_RULE,
            "{$who}: base rate is 0, so PhilHealth MBS = branch minimum wage ₱".Decimal::peso(Decimal::of((string) $wage))." × {$factor} ÷ 12 (rates sheet §2 — UNVERIFIED design default).", $staffId);

        return [$mbs, sprintf('MBS ₱%s = branch min. wage ₱%s × %s ÷ 12 (base rate 0 — UNVERIFIED)', Decimal::peso($mbs), Decimal::peso(Decimal::of((string) $wage)), $factor)];
    }

    // ── Tax ──────────────────────────────────────────────────────────────────

    /** @param Collection<int, PayslipLine> $earnings */
    private function taxableEarnings(Collection $earnings, bool $isMwe, string $payDate): string
    {
        // §5: MWE-exempt list VERIFY; code mapping UNVERIFIED (config comment).
        $exempt = $isMwe ? $this->calc->mweExemptComponents($payDate) : [];
        $sum = '0';

        foreach ($earnings as $l) {
            $c = $this->calc->component((string) $l->component_code);
            if ($c->isTaxable && ! in_array($c->code, $exempt, true)) {
                $sum = Decimal::add($sum, Decimal::fromDb($l->amount));
            }
        }

        return $sum;
    }

    /**
     * RR 11-2018 Sec. 2.79(B)(5)(a) conditions (OFFICIAL text, BIR digest): the regular
     * compensation is exempt from withholding because it is below the compensation
     * level and supplementary compensation is paid, OR supplementary ≥ regular. The third
     * condition (a previous employer this year) needs data Levictas does not hold.
     * "Below the level" = the Annex E semi-monthly table withholds ₱0 on it. After the
     * MWE exclusion, an MWE's regular pay is ₱0, so an MWE with commission qualifies.
     *
     * @param Collection<int, PayslipLine> $earnings
     */
    private function meetsCumulativeAverageCondition(Collection $earnings, bool $isMwe, string $payDate): bool
    {
        $exempt = $isMwe ? $this->calc->mweExemptComponents($payDate) : [];
        $regular = '0';
        $supplementary = '0';

        foreach ($earnings as $l) {
            $c = $this->calc->component((string) $l->component_code);
            if (! $c->isTaxable || in_array($c->code, $exempt, true)) {
                continue;
            }
            $amt = Decimal::fromDb($l->amount);
            if (in_array($c->code, self::REGULAR_COMPENSATION_CODES, true)) {
                $regular = Decimal::add($regular, $amt);
            } else {
                $supplementary = Decimal::add($supplementary, $amt);
            }
        }

        if (! Decimal::isPositive(Decimal::round2($supplementary))) {
            return false;
        }

        $regularWithholds = Decimal::isPositive(
            $this->calc->withholding(Decimal::round2(Decimal::max('0', $regular)), 'semi_monthly', $payDate)
        );

        return ! $regularWithholds || Decimal::cmp($supplementary, $regular) >= 0;
    }

    /**
     * 13th month: taxable only above the annual cap (rates sheet §5; §13 reads it in RR 11-2018).
     * No withholding table is defined for a stand-alone 13th-month run, so no WTAX line
     * is created; the reviewer is told when a taxable portion exists.
     *
     * @param Collection<int, PayslipLine> $earnings
     * @param list<RunWarning>             $warnings
     */
    private function thirteenthTaxable(Collection $earnings, string $payDate, string $who, int $staffId, array &$warnings): string
    {
        $thirteenth = '0';
        foreach ($earnings as $l) {
            if ($l->component_code === 'THIRTEENTH_MONTH') {
                $thirteenth = Decimal::add($thirteenth, Decimal::fromDb($l->amount));
            }
        }

        $cap = $this->calc->thirteenthMonthExemptionCap($payDate);
        $excess = Decimal::max('0', Decimal::sub($thirteenth, $cap));

        if (Decimal::isPositive(Decimal::round2($excess))) {
            $warnings[] = new RunWarning(RunWarning::UNVERIFIED_RULE, sprintf(
                '%s: ₱%s of 13th-month pay is above the ₱%s exclusion and is taxable (RR 11-2018). No tax is withheld on the 13th-month run; include it in the year-end annualization, where the shortfall is withheld from the last pay of the year.',
                $who, Decimal::peso($excess), Decimal::peso($cap),
            ), $staffId);
        }

        return $excess;
    }

    // ── Floor ────────────────────────────────────────────────────────────────

    /**
     * Applies one deduction line against the remaining net and rewrites its `amount`.
     *
     * @param list<RunWarning> $warnings
     * @return string the applied amount
     */
    private function apply(PayslipLine $line, string &$remaining, string $who, int $staffId, array &$warnings): string
    {
        $scheduled = Decimal::round2(Decimal::mul(Decimal::fromDb($line->quantity), Decimal::fromDb($line->rate)));
        $applied = Decimal::round2(Decimal::max('0', Decimal::min($scheduled, $remaining)));
        $short = Decimal::round2(Decimal::sub($scheduled, $applied));
        $remaining = Decimal::sub($remaining, $applied);

        $attrs = ['amount' => $applied];

        if (! $line->is_manual) {
            $base = (string) $line->note;
            $pos = mb_strpos($base, self::FLOOR_MARK);
            $base = $pos === false ? $base : mb_substr($base, 0, $pos);
            $attrs['note'] = Decimal::isPositive($short)
                ? mb_substr($base.self::FLOOR_MARK.'₱'.Decimal::peso($short).' of ₱'.Decimal::peso($scheduled).' not deducted', 0, 255)
                : ($base === '' ? null : $base);
        }

        if (Decimal::isPositive($short)) {
            $warnings[] = new RunWarning(RunWarning::UNAPPLIED_DEDUCTION, sprintf(
                '%s: ₱%s of %s (₱%s scheduled) was not deducted because net pay reached ₱0.%s',
                $who, Decimal::peso($short), $line->label, Decimal::peso($scheduled),
                match (true) {
                    // Rates sheet §13 (VERIFY): the full monthly contribution is still due.
                    in_array($line->component_code, self::EMPLOYEE_CONTRIBUTION_CODES, true)
                        => ' The full employee share must still be remitted (the employer advances it); recover it only with the employee\'s written consent, as an ADJ_DEDUCTION in a later run.',
                    // RR 11-2018 Sec. 2.79(B)(5)(b): the employer is liable for tax it could not withhold.
                    $line->component_code === 'WTAX'
                        => ' The employer remains liable for the unwithheld tax — flag it for the year-end annualization.',
                    default => '',
                },
            ), $staffId);
        }

        $line->forceFill($attrs);
        if ($line->isDirty()) {
            $line->save();
        }

        return $applied;
    }

    /**
     * @param Collection<int, PayslipLine> $lines
     * @param list<string>                 $codes in application order
     * @return list<PayslipLine>
     */
    private function ordered(Collection $lines, array $codes): array
    {
        $rank = array_flip($codes);

        return $lines
            ->filter(fn (PayslipLine $l) => isset($rank[$l->component_code]))
            ->sortBy(fn (PayslipLine $l) => sprintf('%02d-%012d', $rank[$l->component_code], $l->id))
            ->values()
            ->all();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createLine(Payslip $payslip, string $code, string $amount, string $note): PayslipLine
    {
        $c = $this->calc->component($code);
        $amount = Decimal::round2(Decimal::of($amount));

        return PayslipLine::create([
            'payslip_id'     => $payslip->id,
            'component_code' => $code,
            'label'          => $c->label,
            'kind'           => $c->kind,
            'branch_id'      => $payslip->home_branch_id,
            'quantity'       => '1.00',
            'rate'           => $amount,
            'amount'         => $amount,
            'source_type'    => null,
            'source_id'      => null,
            'is_manual'      => false,
            'note'           => mb_substr($note, 0, 255),
        ]);
    }
}
