<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Services\Payroll\StatutoryCalculator;
use App\Services\Payroll\ValueObjects\ComponentFlags;
use App\Services\Payroll\ValueObjects\Holiday;
use InvalidArgumentException;
use OutOfRangeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Expected values are derived by hand from levictas-payroll-rates-sheet.md;
 * each data-provider key cites the sheet section. Plain PHPUnit TestCase: the
 * calculator is pure, so the app is not booted and the config file is required
 * directly.
 */
final class StatutoryCalculatorTest extends TestCase
{
    private const MONTH = '2026-09-01';

    private StatutoryCalculator $calc;

    /** @var array<string, mixed> */
    private array $config;

    protected function setUp(): void
    {
        $this->config = require dirname(__DIR__, 3).'/config/payroll.php';
        $this->calc = new StatutoryCalculator($this->config);
    }

    // =================================================================
    // SSS — §1
    // =================================================================

    /** @return array<string, array{string, string, string, string, string, string, string}> */
    public static function sssProvider(): array
    {
        // [compensation, msc, ee_ss, ee_mpf, er_ss, er_mpf, ec]
        return [
            '§1 check row MSC 5,000'                => ['5000.00',   '5000.00',  '250.00',  '0.00',   '500.00',  '0.00',    '10.00'],
            '§1 check row MSC 20,000'               => ['20000.00',  '20000.00', '1000.00', '0.00',   '2000.00', '0.00',    '30.00'],
            '§1 check row MSC 35,000'               => ['35000.00',  '35000.00', '1000.00', '750.00', '2000.00', '1500.00', '30.00'],
            '§1 below-min proviso: 0.00'            => ['0.00',      '5000.00',  '250.00',  '0.00',   '500.00',  '0.00',    '10.00'],
            '§1 first range edge 5,249.99'          => ['5249.99',   '5000.00',  '250.00',  '0.00',   '500.00',  '0.00',    '10.00'],
            '§1 first range edge 5,250.00'          => ['5250.00',   '5500.00',  '275.00',  '0.00',   '550.00',  '0.00',    '10.00'],
            '§1 EC edge 14,749.99 (MSC 14,500)'     => ['14749.99',  '14500.00', '725.00',  '0.00',   '1450.00', '0.00',    '10.00'],
            '§1 EC edge 14,750.00 (MSC 15,000)'     => ['14750.00',  '15000.00', '750.00',  '0.00',   '1500.00', '0.00',    '30.00'],
            '§1 MPF starts: 20,250.00 (MSC 20,500)' => ['20250.00',  '20500.00', '1000.00', '25.00',  '2000.00', '50.00',   '30.00'],
            '§1 last range edge 34,749.99'          => ['34749.99',  '34500.00', '1000.00', '725.00', '2000.00', '1450.00', '30.00'],
            '§1 last range edge 34,750.00'          => ['34750.00',  '35000.00', '1000.00', '750.00', '2000.00', '1500.00', '30.00'],
            '§1 above-max proviso: 120,000'         => ['120000.00', '35000.00', '1000.00', '750.00', '2000.00', '1500.00', '30.00'],
        ];
    }

    #[DataProvider('sssProvider')]
    public function test_sss(string $comp, string $msc, string $eeSs, string $eeMpf, string $erSs, string $erMpf, string $ec): void
    {
        $r = $this->calc->sss($comp, self::MONTH);

        $this->assertSame($msc, $r->msc, 'msc');
        $this->assertSame($eeSs, $r->eeSs, 'ee_ss');
        $this->assertSame($eeMpf, $r->eeMpf, 'ee_mpf');
        $this->assertSame($erSs, $r->erSs, 'er_ss');
        $this->assertSame($erMpf, $r->erMpf, 'er_mpf');
        $this->assertSame($ec, $r->ec, 'ec');
    }

    public function test_sss_check_row_totals_match_sheet(): void
    {
        // §1: MSC 35,000 → EE 1,750 / ER 3,500
        $r = $this->calc->sss('35000.00', self::MONTH);
        $this->assertSame('1750.00', $r->eeTotal());
        $this->assertSame('3500.00', $r->erTotal());
    }

    public function test_sss_rates_sum_to_total_rate(): void
    {
        // §1: total 15% = 10% ER + 5% EE
        foreach ($this->config['sss'] as $p) {
            $this->assertSame(0, bccomp(bcadd($p['ee_rate'], $p['er_rate'], 4), $p['total_rate'], 4));
        }
    }

    public function test_sss_schedule_has_61_rows_and_every_row_agrees_with_lookup(): void
    {
        // §1: "do not type 61 rows by hand"
        $rows = $this->calc->sssSchedule(self::MONTH);

        $this->assertCount(61, $rows);
        $this->assertNull($rows[0]['from']);
        $this->assertSame('5249.99', $rows[0]['to']);
        $this->assertSame('34750.00', $rows[60]['from']);
        $this->assertNull($rows[60]['to']);

        foreach ($rows as $i => $row) {
            $this->assertSame(bcadd('5000', (string) ($i * 500), 2), $row['contribution']->msc, "row {$i} msc");

            foreach (array_filter([$row['from'], $row['to']]) as $edge) {
                $this->assertSame($row['contribution']->msc, $this->calc->sss($edge, self::MONTH)->msc, "edge {$edge}");
            }
        }
    }

    public function test_sss_before_effective_date_throws(): void
    {
        // §1 effective January 2025
        $this->expectException(OutOfRangeException::class);
        $this->calc->sss('20000.00', '2024-12-31');
    }

    public function test_sss_rejects_negative_compensation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->sss('-1.00', self::MONTH);
    }

    // =================================================================
    // PhilHealth — §2
    // =================================================================

    /** @return array<string, array{string, string, string, string}> */
    public static function philhealthProvider(): array
    {
        // [MBS, total, ee, er]
        return [
            '§2 below floor 5,000 → floor'                    => ['5000.00',   '500.00',  '250.00',  '250.00'],
            '§2 floor edge 9,999.99 → floor'                  => ['9999.99',   '500.00',  '250.00',  '250.00'],
            '§2 floor edge 10,000.00'                         => ['10000.00',  '500.00',  '250.00',  '250.00'],
            '§2 floor edge 10,000.01 (rounding UNVERIFIED)'   => ['10000.01',  '500.00',  '250.00',  '250.00'],
            '§2 mid 25,000'                                   => ['25000.00',  '1250.00', '625.00',  '625.00'],
            '§2 odd centavo 10,000.20 (split UNVERIFIED)'     => ['10000.20',  '500.01',  '250.00',  '250.01'],
            '§2 ceiling edge 99,999.99 (rounding UNVERIFIED)' => ['99999.99',  '5000.00', '2500.00', '2500.00'],
            '§2 ceiling edge 100,000.00'                      => ['100000.00', '5000.00', '2500.00', '2500.00'],
            '§2 ceiling edge 100,000.01 → ceiling'            => ['100000.01', '5000.00', '2500.00', '2500.00'],
            '§2 above ceiling 150,000 → ceiling'              => ['150000.00', '5000.00', '2500.00', '2500.00'],
        ];
    }

    #[DataProvider('philhealthProvider')]
    public function test_philhealth(string $mbs, string $total, string $ee, string $er): void
    {
        $r = $this->calc->philhealth($mbs, self::MONTH);

        $this->assertSame($total, $r->total, 'total');
        $this->assertSame($ee, $r->ee, 'ee');
        $this->assertSame($er, $r->er, 'er');
        $this->assertSame($r->total, bcadd($r->ee, $r->er, 2), 'ee + er = total');
    }

    public function test_philhealth_effective_from_january_2025(): void
    {
        // §2 Rev. 2: "applicable period of January 2025"
        $this->assertSame('500.00', $this->calc->philhealth('10000.00', '2025-01-15')->total);

        $this->expectException(OutOfRangeException::class);
        $this->calc->philhealth('10000.00', '2024-12-31');
    }

    // =================================================================
    // EEMR factors — §10
    // =================================================================

    /** @return array<string, array{string, int, string}> */
    public static function eemrFactorProvider(): array
    {
        return [
            '§10 monthly-paid → 365'                => ['monthly', 1, '365'],
            '§10 monthly-paid ignores rest days'    => ['monthly', 2, '365'],
            '§10 daily-paid, 1 rest day → 313'      => ['daily', 1, '313'],
            '§10 daily-paid, 2 rest days → 261'     => ['daily', 2, '261'],
        ];
    }

    #[DataProvider('eemrFactorProvider')]
    public function test_eemr_factor(string $basis, int $restDays, string $expected): void
    {
        $this->assertSame($expected, $this->calc->eemrFactor($basis, $restDays, '2026-09-20'));
    }

    /** @return array<string, array{string, int}> */
    public static function eemrFactorRejectProvider(): array
    {
        return [
            '§10 daily, 0 rest days (393.5 not implemented)' => ['daily', 0],
            '§10 daily, 3 rest days (no DOLE factor)'        => ['daily', 3],
            'unknown pay basis'                               => ['weekly', 1],
        ];
    }

    #[DataProvider('eemrFactorRejectProvider')]
    public function test_eemr_factor_rejects_unsupported_schedules(string $basis, int $restDays): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->eemrFactor($basis, $restDays, '2026-09-20');
    }

    /** @return array<string, array{string, string, string}> */
    public static function eemrProvider(): array
    {
        // [daily rate, factor, EEMR]  EEMR = daily × factor ÷ 12, half-up (rounding UNVERIFIED)
        return [
            '§10 ₱600 × 313 ÷ 12 (Calabarzon EMA rate, §8)'     => ['600.00', '313', '15650.00'],
            '§10 ₱508 × 313 ÷ 12 (≤10-worker rate, §8)'         => ['508.00', '313', '13250.33'],  // 13250.333…
            '§10 ₱650 × 261 ÷ 12'                               => ['650.00', '261', '14137.50'],
            '§10 ₱600 × 365 ÷ 12 (monthly min. wage, Rule IV)'  => ['600.00', '365', '18250.00'],
        ];
    }

    #[DataProvider('eemrProvider')]
    public function test_equivalent_monthly_rate(string $daily, string $factor, string $expected): void
    {
        $this->assertSame($expected, $this->calc->equivalentMonthlyRate($daily, $factor));
    }

    /** @return array<string, array{string, string, string}> */
    public static function dailyFromMonthlyProvider(): array
    {
        return [
            '§10 ₱18,000 × 12 ÷ 365'  => ['18000.00', '365', '591.78'],  // 591.7808…
            '§10 ₱15,650 × 12 ÷ 313'  => ['15650.00', '313', '600.00'],
            '§10 ₱18,250 × 12 ÷ 365'  => ['18250.00', '365', '600.00'],
        ];
    }

    #[DataProvider('dailyFromMonthlyProvider')]
    public function test_daily_rate_from_monthly(string $monthly, string $factor, string $expected): void
    {
        $this->assertSame($expected, $this->calc->dailyRateFromMonthly($monthly, $factor));
    }

    public function test_eemr_rejects_zero_factor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->equivalentMonthlyRate('600.00', '0');
    }

    // =================================================================
    // Pag-IBIG — §3
    // =================================================================

    /** @return array<string, array{string, string, string}> */
    public static function pagibigProvider(): array
    {
        // [fund salary, ee, er]
        return [
            '§3 low tier 1,000'                      => ['1000.00',  '10.00',  '20.00'],
            '§3 ₱1,500 edge: 1,500.00 (low tier)'    => ['1500.00',  '15.00',  '30.00'],
            '§3 ₱1,500 edge: 1,500.01 (high tier)'   => ['1500.01',  '30.00',  '30.00'],
            '§3 mid 5,000'                           => ['5000.00',  '100.00', '100.00'],
            '§3 ₱10,000 cap edge: 9,999.99'          => ['9999.99',  '200.00', '200.00'],
            '§3 ₱10,000 cap edge: 10,000.00'         => ['10000.00', '200.00', '200.00'],
            '§3 ₱10,000 cap edge: 10,000.01'         => ['10000.01', '200.00', '200.00'],
            '§3 above cap 25,000'                    => ['25000.00', '200.00', '200.00'],
        ];
    }

    #[DataProvider('pagibigProvider')]
    public function test_pagibig(string $fund, string $ee, string $er): void
    {
        $r = $this->calc->pagibig($fund, self::MONTH);

        $this->assertSame($ee, $r->ee, 'ee');
        $this->assertSame($er, $r->er, 'er');
    }

    // =================================================================
    // BIR withholding — §4 (Annex E, RR 11-2018)
    // =================================================================

    /** @return array<string, array{string, string}> */
    public static function semiMonthlyProvider(): array
    {
        // [taxable compensation, tax]  tax = base + rate × (comp − over); half-up to centavo
        return [
            '§4 SM zero'                           => ['0.00',      '0.00'],
            '§4 SM negative → 0'                   => ['-250.00',   '0.00'],

            '§4 SM over 10,417: just below'        => ['10416.99',  '0.00'],
            '§4 SM over 10,417: at'                => ['10417.00',  '0.00'],
            '§4 SM over 10,417: just above'        => ['10417.01',  '0.00'],      // 0.0015
            '§4 SM over 10,417: +₱1'               => ['10418.00',  '0.15'],

            '§4 SM over 16,667: just below'        => ['16666.99',  '937.50'],    // 937.4985
            '§4 SM over 16,667: at'                => ['16667.00',  '937.50'],
            '§4 SM over 16,667: just above'        => ['16667.01',  '937.50'],    // 937.502
            '§4 SM over 16,667: +₱1'               => ['16668.00',  '937.70'],
            '§4 SM bracket 3 mid 20,000'           => ['20000.00',  '1604.10'],

            '§4 SM over 33,333: just below'        => ['33332.99',  '4270.70'],   // 4270.698
            '§4 SM over 33,333: at'                => ['33333.00',  '4270.70'],
            '§4 SM over 33,333: just above'        => ['33333.01',  '4270.70'],   // 4270.7025
            '§4 SM over 33,333: +₱1'               => ['33334.00',  '4270.95'],

            '§4 SM over 83,333: just below'        => ['83332.99',  '16770.70'],  // 16770.6975
            '§4 SM over 83,333: at'                => ['83333.00',  '16770.70'],
            '§4 SM over 83,333: just above'        => ['83333.01',  '16770.70'],  // 16770.703
            '§4 SM over 83,333: +₱1'               => ['83334.00',  '16771.00'],

            '§4 SM over 333,333: just below'       => ['333332.99', '91770.70'],  // 91770.697
            '§4 SM over 333,333: at'               => ['333333.00', '91770.70'],
            '§4 SM over 333,333: just above'       => ['333333.01', '91770.70'],  // 91770.7035
            '§4 SM over 333,333: +₱1'              => ['333334.00', '91771.05'],
        ];
    }

    #[DataProvider('semiMonthlyProvider')]
    public function test_withholding_semi_monthly(string $comp, string $tax): void
    {
        $this->assertSame($tax, $this->calc->withholding($comp, 'semi_monthly', '2026-09-20'));
    }

    /** @return array<string, array{string, string}> */
    public static function monthlyProvider(): array
    {
        return [
            '§4 M at 20,833'        => ['20833.00',  '0.00'],
            '§4 M bracket 2 25,000' => ['25000.00',  '625.05'],    // 15% × 4,167
            '§4 M bracket 3 40,000' => ['40000.00',  '3208.40'],   // 1,875 + 20% × 6,667
            '§4 M at 666,667'       => ['666667.00', '183541.80'],
        ];
    }

    #[DataProvider('monthlyProvider')]
    public function test_withholding_monthly(string $comp, string $tax): void
    {
        $this->assertSame($tax, $this->calc->withholding($comp, 'monthly', '2026-09-30'));
    }

    public function test_bir_tables_are_continuous_at_every_over(): void
    {
        // §4: base_tax of each bracket must equal the previous bracket's formula at that
        // bracket's `over`. Catches transcription typos in base_tax / rate / over.
        foreach ($this->config['bir_withholding'] as $entry) {
            foreach (['semi_monthly', 'monthly'] as $freq) {
                $b = $entry[$freq];
                for ($i = 1; $i < count($b); $i++) {
                    $prev = $b[$i - 1];
                    $prevOver = $prev['over'] ?? $b[$i]['over'];
                    $expected = bcadd($prev['base_tax'], bcmul($prev['rate'], bcsub($b[$i]['over'], $prevOver, 2), 4), 2);
                    $this->assertSame(0, bccomp($expected, $b[$i]['base_tax'], 2), "{$freq} bracket ".($i + 1));
                }
            }
        }
    }

    public function test_withholding_before_effective_date_throws(): void
    {
        $this->expectException(OutOfRangeException::class);
        $this->calc->withholding('20000.00', 'semi_monthly', '2022-12-31');
    }

    public function test_withholding_rejects_unknown_frequency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->withholding('20000.00', 'weekly', '2026-09-20');
    }

    // =================================================================
    // Exemptions — §5 (UNVERIFIED)
    // =================================================================

    public function test_thirteenth_month_cap(): void
    {
        $this->assertSame('90000.00', $this->calc->thirteenthMonthExemptionCap('2026-12-20'));
    }

    public function test_mwe_exempt_codes_are_known_earnings_and_exclude_commission_and_allowance(): void
    {
        $codes = $this->calc->mweExemptComponents('2026-09-20');

        foreach ($codes as $code) {
            $this->assertSame(ComponentFlags::EARNING, $this->calc->component($code)->kind, $code);
        }

        // §5: commission and allowances stay taxable
        $this->assertNotContains('COMMISSION', $codes);
        $this->assertNotContains('ALLOWANCE', $codes);
    }

    // =================================================================
    // Multipliers — §6
    // =================================================================

    /** @return array<string, array{string, string}> */
    public static function multiplierProvider(): array
    {
        return [
            '§6 ordinary OT 125%'                    => ['ordinary_ot', '1.25'],
            '§6 rest day 130%'                       => ['rest_day', '1.30'],
            '§6 rest day OT 130%×130%'               => ['rest_day_ot', '1.69'],
            '§6 special day 130%'                    => ['special_day', '1.30'],
            '§6 special day OT 130%×130%'            => ['special_day_ot', '1.69'],
            '§6 special on rest day 150%'            => ['special_rest_day', '1.50'],
            '§6 special on rest day OT 150%×130%'    => ['special_rest_day_ot', '1.95'],
            '§6 regular holiday worked 200%'         => ['regular_holiday_worked', '2.00'],
            '§6 regular holiday OT 200%×130%'        => ['regular_holiday_ot', '2.60'],
            '§6 regular holiday on rest day 260%'    => ['regular_holiday_rest_day', '2.60'],
            '§6 reg. hol. rest day OT 200%×130%×130%'=> ['regular_holiday_rest_day_ot', '3.38'],
            '§6 regular holiday unworked 100%'       => ['regular_holiday_unworked', '1.00'],
            '§6 night differential +10%'             => ['night_diff', '0.10'],
        ];
    }

    #[DataProvider('multiplierProvider')]
    public function test_multiplier(string $key, string $expected): void
    {
        $this->assertSame($expected, $this->calc->multiplier($key, '2026-09-20'));
    }

    public function test_ot_multipliers_are_their_base_times_130_percent(): void
    {
        // §6: "each hour beyond 8" on premium days = that day's rate × 130%
        $d = '2026-09-20';
        $pairs = [
            'rest_day_ot' => 'rest_day',
            'special_day_ot' => 'special_day',
            'special_rest_day_ot' => 'special_rest_day',
            'regular_holiday_ot' => 'regular_holiday_worked',
            'regular_holiday_rest_day' => 'regular_holiday_worked',
            'regular_holiday_rest_day_ot' => 'regular_holiday_rest_day',
        ];

        foreach ($pairs as $ot => $base) {
            $this->assertSame(
                0,
                bccomp(bcmul($this->calc->multiplier($base, $d), '1.30', 4), $this->calc->multiplier($ot, $d), 4),
                "{$ot} = {$base} × 1.30"
            );
        }
    }

    public function test_unknown_multiplier_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->multiplier('double_holiday', '2026-09-20');
    }

    // =================================================================
    // Holidays — §7
    // =================================================================

    public function test_2026_holiday_counts_match_sheet(): void
    {
        // §7: 12 regular, 8 special non-working, 1 special working
        $all = $this->calc->holidaysBetween('2026-01-01', '2026-12-31');
        $byType = array_count_values(array_map(static fn (Holiday $h): string => $h->type, $all));

        $this->assertCount(21, $all);
        $this->assertSame(12, $byType[Holiday::REGULAR]);
        $this->assertSame(8, $byType[Holiday::SPECIAL_NON_WORKING]);
        $this->assertSame(1, $byType[Holiday::SPECIAL_WORKING]);
    }

    public function test_holidays_between_is_inclusive_and_sorted(): void
    {
        // §7: Holy Week 2026 and Araw ng Kagitingan, bounded exactly on holiday dates
        $h = $this->calc->holidaysBetween('2026-04-02', '2026-04-09');

        $this->assertSame(['2026-04-02', '2026-04-03', '2026-04-04', '2026-04-09'], array_map(static fn (Holiday $x): string => $x->date, $h));
        $this->assertSame(Holiday::SPECIAL_NON_WORKING, $h[2]->type);
        $this->assertSame(['date' => '2026-04-09', 'name' => 'Araw ng Kagitingan', 'type' => 'regular'], $h[3]->toArray());
    }

    public function test_holidays_second_cutoff_of_december(): void
    {
        // §7: Dec 16–31 cutoff
        $dates = array_map(static fn (Holiday $x): string => $x->date, $this->calc->holidaysBetween('2026-12-16', '2026-12-31'));
        $this->assertSame(['2026-12-24', '2026-12-25', '2026-12-30', '2026-12-31'], $dates);
    }

    public function test_2027_holiday_counts_match_sheet(): void
    {
        // §7b: 10 regular, 8 special non-working (4 + 4 additional), 1 special working; Eid not yet dated
        $all = $this->calc->holidaysBetween('2027-01-01', '2027-12-31');
        $byType = array_count_values(array_map(static fn (Holiday $h): string => $h->type, $all));

        $this->assertCount(19, $all);
        $this->assertSame(10, $byType[Holiday::REGULAR]);
        $this->assertSame(8, $byType[Holiday::SPECIAL_NON_WORKING]);
        $this->assertSame(1, $byType[Holiday::SPECIAL_WORKING]);
    }

    public function test_holidays_across_year_boundary(): void
    {
        // §7 + §7b: a range spanning Dec 2026 → Jan 2027
        $dates = array_map(static fn (Holiday $x): string => $x->date, $this->calc->holidaysBetween('2026-12-16', '2027-01-15'));
        $this->assertSame(['2026-12-24', '2026-12-25', '2026-12-30', '2026-12-31', '2027-01-01'], $dates);
    }

    public function test_holidays_for_unconfigured_year_throw(): void
    {
        $this->expectException(OutOfRangeException::class);
        $this->calc->holidaysBetween('2027-12-16', '2028-01-15');
    }

    public function test_holidays_reject_reversed_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->holidaysBetween('2026-05-02', '2026-05-01');
    }

    public function test_invalid_date_string_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->holidaysBetween('2026-02-30', '2026-03-01');
    }

    // =================================================================
    // Components — locked data model v3
    // =================================================================

    public function test_every_locked_model_code_is_configured(): void
    {
        $locked = [
            'BASIC', 'COMMISSION', 'ALLOWANCE', 'OT', 'RESTDAY_PREM', 'HOLIDAY_PAY', 'HOLIDAY_PREM',
            'NIGHT_DIFF', 'MINWAGE_TOPUP', 'THIRTEENTH_MONTH', 'ADJ_EARNING', 'ADJ_EARNING_NONTAX',
            'SSS_EE', 'PHIC_EE', 'HDMF_EE', 'WTAX', 'LOAN_DEDUCTION', 'OTHER_DEDUCTION', 'ADJ_DEDUCTION',
            'SSS_ER', 'SSS_EC', 'PHIC_ER', 'HDMF_ER',
        ];

        $this->assertEqualsCanonicalizing($locked, array_keys($this->config['components']));
    }

    /** @return array<string, array{string, string, bool, bool, bool, bool, bool, bool}> */
    public static function componentProvider(): array
    {
        // [code, kind, taxable, 13th, min_wage, sss, philhealth_mbs, pagibig]
        return [
            'BASIC'              => ['BASIC',              'earning',        true,  true,  true,  true,  true,  true],
            'COMMISSION'         => ['COMMISSION',         'earning',        true,  true,  true,  true,  false, false],
            'ALLOWANCE'          => ['ALLOWANCE',          'earning',        true,  false, false, true,  false, true],
            'OT'                 => ['OT',                 'earning',        true,  false, false, true,  false, false],
            'RESTDAY_PREM'       => ['RESTDAY_PREM',       'earning',        true,  false, false, true,  false, false],
            'HOLIDAY_PAY'        => ['HOLIDAY_PAY',        'earning',        true,  false, false, true,  false, false],
            'HOLIDAY_PREM'       => ['HOLIDAY_PREM',       'earning',        true,  false, false, true,  false, false],
            'NIGHT_DIFF'         => ['NIGHT_DIFF',         'earning',        true,  false, false, true,  false, false],
            'MINWAGE_TOPUP'      => ['MINWAGE_TOPUP',      'earning',        true,  true,  false, true,  false, false],
            'THIRTEENTH_MONTH'   => ['THIRTEENTH_MONTH',   'earning',        false, false, false, false, false, false],
            'ADJ_EARNING'        => ['ADJ_EARNING',        'earning',        true,  false, false, true,  false, false],
            'ADJ_EARNING_NONTAX' => ['ADJ_EARNING_NONTAX', 'earning',        false, false, false, false, false, false],
            'SSS_EE'             => ['SSS_EE',             'deduction',      false, false, false, false, false, false],
            'PHIC_EE'            => ['PHIC_EE',            'deduction',      false, false, false, false, false, false],
            'HDMF_EE'            => ['HDMF_EE',            'deduction',      false, false, false, false, false, false],
            'WTAX'               => ['WTAX',               'deduction',      false, false, false, false, false, false],
            'LOAN_DEDUCTION'     => ['LOAN_DEDUCTION',     'deduction',      false, false, false, false, false, false],
            'OTHER_DEDUCTION'    => ['OTHER_DEDUCTION',    'deduction',      false, false, false, false, false, false],
            'ADJ_DEDUCTION'      => ['ADJ_DEDUCTION',      'deduction',      false, false, false, false, false, false],
            'SSS_ER'             => ['SSS_ER',             'employer_share', false, false, false, false, false, false],
            'SSS_EC'             => ['SSS_EC',             'employer_share', false, false, false, false, false, false],
            'PHIC_ER'            => ['PHIC_ER',            'employer_share', false, false, false, false, false, false],
            'HDMF_ER'            => ['HDMF_ER',            'employer_share', false, false, false, false, false, false],
        ];
    }

    #[DataProvider('componentProvider')]
    public function test_component_flags(
        string $code, string $kind, bool $tax, bool $thirteenth, bool $minWage, bool $sss, bool $phic, bool $hdmf,
    ): void {
        $c = $this->calc->component($code);

        $this->assertSame($code, $c->code);
        $this->assertSame($kind, $c->kind);
        $this->assertNotSame('', $c->label);
        $this->assertSame($tax, $c->isTaxable, 'is_taxable');
        $this->assertSame($thirteenth, $c->in13thMonthBasis, 'in_13th_month_basis');
        $this->assertSame($minWage, $c->countsTowardMinWage, 'counts_toward_min_wage');
        $this->assertSame($sss, $c->inSssCompensation, 'in_sss_compensation');
        $this->assertSame($phic, $c->inPhilhealthMbs, 'in_philhealth_mbs');
        $this->assertSame($hdmf, $c->inPagibigFundSalary, 'in_pagibig_fund_salary');
    }

    public function test_unknown_component_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->component('HAZARD_PAY');
    }

    // =================================================================
    // Config hygiene
    // =================================================================

    public function test_config_has_version_and_only_scalar_values(): void
    {
        $this->assertNotSame('', $this->calc->version());

        // `php artisan config:cache` var_exports the config: closures/objects would break it.
        array_walk_recursive($this->config, function (mixed $v, int|string $k): void {
            $this->assertTrue($v === null || is_scalar($v), "config key [{$k}] must be scalar");
        });
    }
}
