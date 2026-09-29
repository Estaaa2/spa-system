<?php

declare(strict_types=1);

namespace App\Services\Payroll\Display;

use App\Models\PayrollRun;
use App\Models\PayslipLine;
use App\Services\Payroll\Support\Decimal;
use App\Services\Payroll\ValueObjects\RunWarning;
use Illuminate\Support\Carbon;

/**
 * Unit 6 — display-only helpers for the payroll pages. ONE PHP source for every
 * label/colour map the pages use, and all money/quantity formatting, so Blade only
 * echoes strings (no arithmetic in views). Nothing here changes a stored number.
 */
final class PayrollDisplay
{
    /** Run status badges — staff-facing neutral palette (same tones as the Unit 5 pages). */
    public const STATUS = [
        PayrollRun::STATUS_DRAFT     => ['label' => 'Draft',     'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',       'icon' => 'fa-pen-ruler'],
        PayrollRun::STATUS_APPROVED  => ['label' => 'Approved',  'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',           'icon' => 'fa-circle-check'],
        PayrollRun::STATUS_FINALIZED => ['label' => 'Finalized', 'class' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',   'icon' => 'fa-lock'],
        PayrollRun::STATUS_RELEASED  => ['label' => 'Released',  'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300', 'icon' => 'fa-paper-plane'],
    ];

    public const TYPE = [
        PayrollRun::TYPE_REGULAR          => 'Regular',
        PayrollRun::TYPE_THIRTEENTH_MONTH => '13th Month',
    ];

    /**
     * Warning groups for the run page. Codes come from RunWarning (Unit 4); anything
     * not listed falls into "check".
     */
    public const WARNING_GROUPS = [
        'not_paid' => [
            'label' => 'Not paid by this run',
            'hint'  => 'These staff have no payslip here. Fix the setup and regenerate, or pay them manually.',
            'class' => 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/10',
            'text'  => 'text-red-800 dark:text-red-300',
            'icon'  => 'fa-user-xmark',
            'codes' => [
                RunWarning::MISSING_PROFILE, RunWarning::NOT_IN_SUITE_BRANCH, RunWarning::HOME_BRANCH_MISSING,
                RunWarning::INACTIVE_WITH_ATTENDANCE, RunWarning::MISSING_MIN_WAGE, RunWarning::MISSING_EEMR_FACTOR,
                RunWarning::SETUP_ERROR, RunWarning::NOT_ELIGIBLE_13TH,
            ],
        ],
        'check' => [
            'label' => 'Paid — check before approving',
            'hint'  => 'Payslips were computed, but a person should review these.',
            'class' => 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/10',
            'text'  => 'text-amber-800 dark:text-amber-300',
            'icon'  => 'fa-triangle-exclamation',
            'codes' => [
                RunWarning::EARNINGS, RunWarning::UNAPPLIED_DEDUCTION, RunWarning::DEDUCTION_UNAUTHORIZED,
                RunWarning::DEDUCTION_INVALID, RunWarning::MISSING_STATUTORY_ID, RunWarning::ORPHANED_MANUAL_LINES,
                RunWarning::CONTRIBUTIONS_SKIPPED, RunWarning::CUMULATIVE_AVERAGE,
            ],
        ],
        'run' => [
            'label' => 'Run-level notes',
            'hint'  => 'Dates, deadlines and rules marked UNVERIFIED in the rates sheet.',
            'class' => 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/10',
            'text'  => 'text-blue-800 dark:text-blue-300',
            'icon'  => 'fa-circle-info',
            'codes' => [
                RunWarning::PERIOD_ADJUSTED, RunWarning::PERIOD_NOT_ENDED, RunWarning::PAY_INTERVAL,
                RunWarning::PAY_DATE_LATE, RunWarning::UNFINALIZED_RUNS, RunWarning::UNVERIFIED_RULE,
            ],
        ],
    ];

    /** Line groups shown when a register row is expanded. */
    public const KIND = [
        PayslipLine::KIND_EARNING        => 'Earnings',
        PayslipLine::KIND_DEDUCTION      => 'Deductions',
        PayslipLine::KIND_EMPLOYER_SHARE => 'Employer shares (not deducted from pay)',
    ];

    /** Codes whose quantity is in hours (Unit 3: OT / night differential). */
    private const HOUR_CODES = ['OT', 'NIGHT_DIFF'];

    public const EMPLOYEE_CONTRIBUTION_CODES = ['SSS_EE', 'PHIC_EE', 'HDMF_EE'];

    // ── Money / numbers ──────────────────────────────────────────────────────

    /** '₱1,234.56' (2 decimals, half-up via Decimal); null → '—'. */
    public static function peso(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $s = Decimal::peso(Decimal::fromDb($value));

        return str_starts_with($s, '-') ? '-₱'.substr($s, 1) : '₱'.$s;
    }

    /** Rate with 2 decimals, or 4 when the stored rate needs them (e.g. 695.4545). */
    public static function rate(mixed $value): string
    {
        $r = Decimal::round4(Decimal::fromDb($value));
        $two = Decimal::round2($r);

        if (Decimal::cmp($r, $two) === 0) {
            return self::peso($two);
        }
        [$int, $frac] = explode('.', ltrim($r, '-'));
        $int = strrev(implode(',', str_split(strrev($int), 3)));

        return (str_starts_with($r, '-') ? '-₱' : '₱').$int.'.'.$frac;
    }

    /** '2', '1.5', '-1' — trailing zeros trimmed. */
    public static function qty(mixed $value): string
    {
        $q = Decimal::round2(Decimal::fromDb($value));
        $q = rtrim(rtrim($q, '0'), '.');

        return $q === '-0' || $q === '' ? '0' : $q;
    }

    public static function date(mixed $value, string $format = 'M j, Y'): string
    {
        return $value ? Carbon::parse($value)->format($format) : '—';
    }

    public static function periodLabel(PayrollRun $run): string
    {
        if ($run->run_type === PayrollRun::TYPE_THIRTEENTH_MONTH) {
            return '13th Month '.$run->period_start->format('Y');
        }

        return $run->period_start->isSameMonth($run->period_end)
            ? $run->period_start->format('M j').'–'.$run->period_end->format('j, Y')
            : $run->period_start->format('M j').'–'.$run->period_end->format('M j, Y');
    }

    public static function cutoffLabel(PayrollRun $run): string
    {
        return $run->cutoff_no ? 'Cutoff '.$run->cutoff_no : '—';
    }

    public static function warningGroup(string $code): string
    {
        foreach (self::WARNING_GROUPS as $key => $g) {
            if (in_array($code, $g['codes'], true)) {
                return $key;
            }
        }

        return 'check';
    }

    // ── Line detail ──────────────────────────────────────────────────────────

    /**
     * The "quantity × rate" text beside a line, built from the stored numbers only.
     *
     *  - Commission, percent rule: quantity = base, rate = percent ÷ 100 (Unit 3,
     *    CommissionEarnings::line) → "10% of ₱1,500.00". Recognised from the note,
     *    which Unit 3 writes as "…% of base…"; the flat form has quantity 1.
     *  - Deductions: quantity × rate is the SCHEDULED amount and `amount` the APPLIED
     *    one (PayslipSettler convention); a short deduction says so.
     *  - Single-quantity lines whose amount equals the rate show no multiplication.
     */
    public static function lineDetail(PayslipLine $l): string
    {
        $qty = Decimal::fromDb($l->quantity);
        $rate = Decimal::fromDb($l->rate);
        $amount = Decimal::round2(Decimal::fromDb($l->amount));
        $scheduled = Decimal::round2(Decimal::mul($qty, $rate));

        if ($l->kind === PayslipLine::KIND_DEDUCTION) {
            if (Decimal::cmp($amount, $scheduled) < 0) {
                return self::peso($amount).' of '.self::peso($scheduled).' deducted — net pay reached ₱0';
            }

            return Decimal::cmp($qty, '1') === 0 ? '' : self::qty($qty).' × '.self::rate($rate);
        }

        if ($l->kind === PayslipLine::KIND_EMPLOYER_SHARE) {
            return '';
        }

        if ($l->component_code === 'COMMISSION' && is_string($l->note) && str_contains($l->note, '% of')) {
            $pct = rtrim(rtrim(Decimal::round(Decimal::mul($rate, '100'), 2), '0'), '.');

            return $pct.'% of '.self::peso($qty);
        }

        if (Decimal::cmp($qty, '1') === 0 && Decimal::cmp($amount, Decimal::round2($rate)) === 0) {
            return '';
        }

        $unit = in_array($l->component_code, self::HOUR_CODES, true) ? ' hr' : '';

        return self::qty($qty).$unit.' × '.self::rate($rate);
    }

    public static function isShort(PayslipLine $l): bool
    {
        if ($l->kind !== PayslipLine::KIND_DEDUCTION) {
            return false;
        }
        $scheduled = Decimal::round2(Decimal::mul(Decimal::fromDb($l->quantity), Decimal::fromDb($l->rate)));

        return Decimal::cmp(Decimal::round2(Decimal::fromDb($l->amount)), $scheduled) < 0;
    }

    /** Config order of components (earnings first), used to order payslip lines. */
    public static function componentRank(): array
    {
        return array_flip(array_keys((array) config('payroll.components')));
    }

    public static function componentLabel(string $code): string
    {
        return (string) (config("payroll.components.{$code}.label") ?? $code);
    }
}
