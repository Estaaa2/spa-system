<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Services\Payroll\ValueObjects\ComponentFlags;
use App\Services\Payroll\ValueObjects\Holiday;
use App\Services\Payroll\ValueObjects\PagIbigContribution;
use App\Services\Payroll\ValueObjects\PhilHealthContribution;
use App\Services\Payroll\ValueObjects\SssContribution;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use OutOfRangeException;
use UnexpectedValueException;

/**
 * All government payroll math for Levictas, in one pure class.
 *
 * Pure: no DB, no Auth, no clock. Amounts and dates in, value objects out.
 * Every number comes from config/payroll.php (transcribed from the rates sheet).
 *
 * MONEY: bcmath decimal strings. Inputs are numeric strings or ints (floats are
 * rejected by the signatures under strict_types). Intermediate scale is 6;
 * outputs are 2-decimal strings.
 *
 * ROUNDING (none of the rates-sheet sources state a rounding rule — all UNVERIFIED):
 *  - SSS:        exact by construction (5% / 10% of multiples of ₱500) — no rounding.
 *  - PhilHealth: total = MBS × rate rounded half-up to the centavo; ee = total ×
 *                employee_share rounded DOWN to the centavo; er = total − ee.
 *                (Odd centavo goes to the employer; ee + er always equals total.)
 *  - Pag-IBIG:   ee and er each rounded half-up to the centavo.
 *  - BIR:        tax rounded half-up to the centavo.
 *  - EEMR:       equivalent monthly rate and derived daily rate rounded half-up to
 *                the centavo.
 */
final class StatutoryCalculator
{
    private const SCALE = 6;

    private const FREQUENCIES = ['semi_monthly', 'monthly'];

    private const PAY_BASES = ['monthly', 'daily'];

    /** @var array<string, mixed> */
    private readonly array $config;

    /**
     * @param array<string, mixed>|null $config the payroll config array; null = config('payroll').
     *                                          Tests pass the array directly (no app boot).
     */
    public function __construct(?array $config = null)
    {
        $config ??= config('payroll');

        if (! is_array($config) || ! isset($config['version'], $config['components'])) {
            throw new UnexpectedValueException('Payroll config is missing or malformed (config/payroll.php).');
        }

        $this->config = $config;
    }

    /** Config version string — record it on payroll_runs.config_version. */
    public function version(): string
    {
        return (string) $this->config['version'];
    }

    // ------------------------------------------------------------------
    // SSS — rates sheet §1
    // ------------------------------------------------------------------

    /**
     * @param string|int $monthlyCompensation sum of in_sss_compensation lines for the month
     * @param DateTimeInterface|string $month  any date in the contribution month
     */
    public function sss(string|int $monthlyCompensation, DateTimeInterface|string $month): SssContribution
    {
        $comp = $this->amount($monthlyCompensation, 'monthlyCompensation');
        $p = $this->effective('sss', $this->monthStart($month));

        return $this->sssForMsc($this->sssMsc($comp, $p), $p);
    }

    /**
     * The full generated MSC schedule (61 rows for the 2025 parameters), for
     * checking against the SSS Circular 2024-006 table image (§1 is VERIFY).
     *
     * @return list<array{from: ?string, to: ?string, contribution: SssContribution}>
     *         from = null on the first row, to = null on the last row (open-ended).
     */
    public function sssSchedule(DateTimeInterface|string $month): array
    {
        $p = $this->effective('sss', $this->monthStart($month));

        $rows = [[
            'from' => null,
            'to' => bcsub($p['first_range_below'], '0.01', 2),
            'contribution' => $this->sssForMsc($this->fmt($p['min_msc']), $p),
        ]];

        $from = $p['first_range_below'];
        $msc = bcadd($p['min_msc'], $p['msc_step'], 2);

        while (bccomp($msc, $p['max_msc'], 2) < 0) {
            $next = bcadd($from, $p['range_width'], 2);
            $rows[] = [
                'from' => $this->fmt($from),
                'to' => bcsub($next, '0.01', 2),
                'contribution' => $this->sssForMsc($msc, $p),
            ];
            $from = $next;
            $msc = bcadd($msc, $p['msc_step'], 2);
        }

        // Self-check: the generated last range must start exactly where §1 says.
        if (bccomp($from, $p['last_range_from'], 2) !== 0 || bccomp($msc, $p['max_msc'], 2) !== 0) {
            throw new UnexpectedValueException(sprintf(
                'SSS parameters are inconsistent: generated last range starts at %s, config says %s.',
                $this->fmt($from),
                $this->fmt($p['last_range_from'])
            ));
        }

        $rows[] = [
            'from' => $this->fmt($p['last_range_from']),
            'to' => null,
            'contribution' => $this->sssForMsc($this->fmt($p['max_msc']), $p),
        ];

        return $rows;
    }

    // ------------------------------------------------------------------
    // PhilHealth — rates sheet §2
    // ------------------------------------------------------------------

    /**
     * @param string|int $monthlyBasicSalary MBS: fixed basic rate, NOT reduced by absences (§2 OFFICIAL).
     *                                       Daily-paid: pass equivalentMonthlyRate(dailyRate, factor)
     *                                       (§2 Rev. 2 — OFFICIAL). Never pass summed payslip lines.
     */
    public function philhealth(string|int $monthlyBasicSalary, DateTimeInterface|string $month): PhilHealthContribution
    {
        $mbs = $this->amount($monthlyBasicSalary, 'monthlyBasicSalary');
        $p = $this->effective('philhealth', $this->monthStart($month));

        $base = $this->max($mbs, $p['income_floor']);
        $base = $this->min($base, $p['income_ceiling']);

        $total = $this->roundHalfUp(bcmul($base, $p['rate'], self::SCALE));
        $ee = bcmul($total, $p['employee_share'], 2); // bcmath truncates = round down (UNVERIFIED)
        $er = bcsub($total, $ee, 2);

        return new PhilHealthContribution($total, $ee, $er);
    }

    // ------------------------------------------------------------------
    // Days-per-year factors (EEMR) — rates sheet §10
    // ------------------------------------------------------------------

    /**
     * DOLE days-per-year factor for a pay profile.
     *
     * @param string $payBasis          'monthly' | 'daily' (staff_pay_profiles.pay_basis)
     * @param int    $restDaysPerWeek   count(staff_pay_profiles.rest_days); ignored for monthly-paid
     *
     * @throws InvalidArgumentException for a daily-paid schedule with no configured factor
     *                                  (e.g. 0 rest days — Labor Code Art. 91 requires one)
     */
    public function eemrFactor(string $payBasis, int $restDaysPerWeek, DateTimeInterface|string $date): string
    {
        if (! in_array($payBasis, self::PAY_BASES, true)) {
            throw new InvalidArgumentException("Unknown pay basis [{$payBasis}].");
        }

        $f = $this->effective('eemr_factors', $this->ymd($date));

        if ($payBasis === 'monthly') {
            return (string) $f['monthly'];
        }

        if (! isset($f['daily_by_rest_days'][$restDaysPerWeek])) {
            throw new InvalidArgumentException(
                "No DOLE days-per-year factor for daily-paid staff with {$restDaysPerWeek} rest day(s) per week."
            );
        }

        return (string) $f['daily_by_rest_days'][$restDaysPerWeek];
    }

    /** Estimated Equivalent Monthly Rate: daily rate × factor ÷ 12 (§10). */
    public function equivalentMonthlyRate(string|int $dailyRate, string|int $factor): string
    {
        $daily = $this->amount($dailyRate, 'dailyRate');
        $factor = $this->positive($factor, 'factor');

        return $this->roundHalfUp(bcdiv(bcmul($daily, $factor, self::SCALE), '12', self::SCALE));
    }

    /** Daily rate from a monthly rate: monthly rate × 12 ÷ factor (§10). */
    public function dailyRateFromMonthly(string|int $monthlyRate, string|int $factor): string
    {
        $monthly = $this->amount($monthlyRate, 'monthlyRate');
        $factor = $this->positive($factor, 'factor');

        return $this->roundHalfUp(bcdiv(bcmul($monthly, '12', self::SCALE), $factor, self::SCALE));
    }

    // ------------------------------------------------------------------
    // Pag-IBIG / HDMF — rates sheet §3
    // ------------------------------------------------------------------

    /**
     * @param string|int $monthlyFundSalary sum of in_pagibig_fund_salary lines for the month
     */
    public function pagibig(string|int $monthlyFundSalary, DateTimeInterface|string $month): PagIbigContribution
    {
        $fund = $this->amount($monthlyFundSalary, 'monthlyFundSalary');
        $p = $this->effective('pagibig', $this->monthStart($month));

        // Rate tier is chosen on the actual fund salary; the cap limits the base.
        $tier = bccomp($fund, $p['low_salary_max'], self::SCALE) <= 0 ? $p['low'] : $p['high'];
        $base = $this->min($fund, $p['max_fund_salary']);

        return new PagIbigContribution(
            $this->roundHalfUp(bcmul($base, $tier['ee_rate'], self::SCALE)),
            $this->roundHalfUp(bcmul($base, $tier['er_rate'], self::SCALE)),
        );
    }

    // ------------------------------------------------------------------
    // BIR withholding — rates sheet §4 (Annex E, RR 11-2018)
    // ------------------------------------------------------------------

    /**
     * @param string|int $taxableCompensation taxable earnings − employee contributions for the
     *                                        period. Zero or negative → 0.00.
     * @param string     $frequency           'semi_monthly' | 'monthly'
     */
    public function withholding(
        string|int $taxableCompensation,
        string $frequency,
        DateTimeInterface|string $payDate,
    ): string {
        if (! in_array($frequency, self::FREQUENCIES, true)) {
            throw new InvalidArgumentException("Unknown withholding frequency [{$frequency}].");
        }

        $comp = $this->amount($taxableCompensation, 'taxableCompensation', allowNegative: true);
        $brackets = $this->effective('bir_withholding', $this->ymd($payDate))[$frequency];

        if (bccomp($comp, '0', self::SCALE) <= 0) {
            return '0.00';
        }

        // Highest bracket whose `over` the compensation strictly exceeds; else bracket 1.
        // Choosing by `over` closes the centavo gaps between the official whole-peso
        // ranges (§4 note). The schedule is continuous at each `over`.
        $bracket = $brackets[0];
        foreach ($brackets as $b) {
            if ($b['over'] !== null && bccomp($comp, $b['over'], self::SCALE) > 0) {
                $bracket = $b;
            }
        }

        $excess = $bracket['over'] === null ? '0' : bcsub($comp, $bracket['over'], self::SCALE);
        $tax = bcadd($bracket['base_tax'], bcmul($bracket['rate'], $excess, self::SCALE), self::SCALE);

        return $this->roundHalfUp($tax);
    }

    // ------------------------------------------------------------------
    // Exemptions — rates sheet §5 (UNVERIFIED)
    // ------------------------------------------------------------------

    /** Annual 13th-month + other benefits exclusion cap effective on $date. */
    public function thirteenthMonthExemptionCap(DateTimeInterface|string $date): string
    {
        return $this->fmt($this->effective('thirteenth_month_exemption_cap', $this->ymd($date))['annual_cap']);
    }

    /** @return list<string> component codes exempt from WTAX when the payslip is_mwe */
    public function mweExemptComponents(DateTimeInterface|string $date): array
    {
        return array_values($this->effective('mwe_exempt_components', $this->ymd($date))['codes']);
    }

    // ------------------------------------------------------------------
    // Labor multipliers — rates sheet §6
    // ------------------------------------------------------------------

    /** Decimal-string multiplier, e.g. '1.69'. See the config block for how each key applies. */
    public function multiplier(string $key, DateTimeInterface|string $date): string
    {
        $rates = $this->effective('multipliers', $this->ymd($date))['rates'];

        if (! array_key_exists($key, $rates)) {
            throw new InvalidArgumentException("Unknown multiplier [{$key}].");
        }

        return (string) $rates[$key];
    }

    // ------------------------------------------------------------------
    // Holidays — rates sheet §7
    // ------------------------------------------------------------------

    /**
     * National holidays in [from, to], both inclusive, sorted by date.
     * Includes special_working days (the engine treats them as ordinary days).
     *
     * @return list<Holiday>
     *
     * @throws OutOfRangeException if any year in the range has no configured list
     */
    public function holidaysBetween(DateTimeInterface|string $from, DateTimeInterface|string $to): array
    {
        $from = $this->ymd($from);
        $to = $this->ymd($to);

        if ($from > $to) {
            throw new InvalidArgumentException("holidaysBetween: from [{$from}] is after to [{$to}].");
        }

        $result = [];
        for ($year = (int) substr($from, 0, 4); $year <= (int) substr($to, 0, 4); $year++) {
            if (! isset($this->config['holidays'][$year])) {
                throw new OutOfRangeException(
                    "No national holiday list configured for {$year}. Add it to config/payroll.php and bump the version."
                );
            }

            foreach ($this->config['holidays'][$year] as $h) {
                if ($h['date'] >= $from && $h['date'] <= $to) {
                    $result[] = new Holiday($h['date'], $h['name'], $h['type']);
                }
            }
        }

        usort($result, static fn (Holiday $a, Holiday $b): int => strcmp($a->date, $b->date));

        return $result;
    }

    // ------------------------------------------------------------------
    // Components
    // ------------------------------------------------------------------

    public function component(string $code): ComponentFlags
    {
        $c = $this->config['components'][$code] ?? null;

        if ($c === null) {
            throw new InvalidArgumentException("Unknown pay component [{$code}].");
        }

        return new ComponentFlags(
            code: $code,
            kind: $c['kind'],
            label: $c['label'],
            isTaxable: (bool) $c['is_taxable'],
            in13thMonthBasis: (bool) $c['in_13th_month_basis'],
            countsTowardMinWage: (bool) $c['counts_toward_min_wage'],
            inSssCompensation: (bool) $c['in_sss_compensation'],
            inPhilhealthMbs: (bool) $c['in_philhealth_mbs'],
            inPagibigFundSalary: (bool) $c['in_pagibig_fund_salary'],
        );
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    private function sssMsc(string $comp, array $p): string
    {
        if (bccomp($comp, $p['first_range_below'], self::SCALE) < 0) {
            return $this->fmt($p['min_msc']);
        }

        if (bccomp($comp, $p['last_range_from'], self::SCALE) >= 0) {
            return $this->fmt($p['max_msc']);
        }

        // Range index: floor((comp − first_range_below) / width) + 1. bcdiv at scale 0
        // truncates, which is floor for the non-negative value here.
        $steps = bcadd(bcdiv(bcsub($comp, $p['first_range_below'], self::SCALE), $p['range_width'], 0), '1', 0);

        return bcadd($p['min_msc'], bcmul($steps, $p['msc_step'], 2), 2);
    }

    /** @param array<string, mixed> $p */
    private function sssForMsc(string $msc, array $p): SssContribution
    {
        $ssBase = $this->min($msc, $p['mpf_msc_above']);
        $mpfBase = $this->max(bcsub($msc, $p['mpf_msc_above'], 2), '0');

        $ec = bccomp($msc, $p['ec']['msc_threshold'], 2) < 0 ? $p['ec']['below'] : $p['ec']['at_or_above'];

        return new SssContribution(
            msc: $this->fmt($msc),
            eeSs: $this->roundHalfUp(bcmul($ssBase, $p['ee_rate'], self::SCALE)),
            eeMpf: $this->roundHalfUp(bcmul($mpfBase, $p['ee_rate'], self::SCALE)),
            erSs: $this->roundHalfUp(bcmul($ssBase, $p['er_rate'], self::SCALE)),
            erMpf: $this->roundHalfUp(bcmul($mpfBase, $p['er_rate'], self::SCALE)),
            ec: $this->fmt($ec),
        );
    }

    /**
     * The block entry with the latest effective_from <= $ymd.
     *
     * @return array<string, mixed>
     */
    private function effective(string $block, string $ymd): array
    {
        $chosen = null;

        foreach ($this->config[$block] ?? [] as $entry) {
            if ($entry['effective_from'] <= $ymd
                && ($chosen === null || $entry['effective_from'] > $chosen['effective_from'])) {
                $chosen = $entry;
            }
        }

        if ($chosen === null) {
            throw new OutOfRangeException("No [{$block}] schedule is effective on {$ymd} in config/payroll.php.");
        }

        return $chosen;
    }

    private function amount(string|int $value, string $name, bool $allowNegative = false): string
    {
        $value = (string) $value;

        if (preg_match('/^-?\d+(\.\d+)?$/', $value) !== 1) {
            throw new InvalidArgumentException("{$name} must be a plain decimal string, got [{$value}].");
        }

        if (! $allowNegative && str_starts_with($value, '-') && bccomp($value, '0', self::SCALE) !== 0) {
            throw new InvalidArgumentException("{$name} must not be negative, got [{$value}].");
        }

        return bcadd($value, '0', self::SCALE);
    }

    private function positive(string|int $value, string $name): string
    {
        $v = $this->amount($value, $name);

        if (bccomp($v, '0', self::SCALE) <= 0) {
            throw new InvalidArgumentException("{$name} must be greater than zero, got [{$value}].");
        }

        return $v;
    }

    private function ymd(DateTimeInterface|string $date): string
    {
        if ($date instanceof DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Expected a Y-m-d date, got [{$date}].");
        }

        return $date;
    }

    private function monthStart(DateTimeInterface|string $date): string
    {
        return substr($this->ymd($date), 0, 8).'01';
    }

    /** Half-up (away from zero) to 2 decimals. bcmath truncates, so add ±0.005 first. */
    private function roundHalfUp(string $value): string
    {
        $half = str_starts_with($value, '-') ? '-0.005' : '0.005';

        return bcadd($value, $half, 2);
    }

    private function fmt(string $value): string
    {
        return bcadd($value, '0', 2);
    }

    private function min(string $a, string $b): string
    {
        return bccomp($a, $b, self::SCALE) <= 0 ? $a : $b;
    }

    private function max(string $a, string $b): string
    {
        return bccomp($a, $b, self::SCALE) >= 0 ? $a : $b;
    }
}
