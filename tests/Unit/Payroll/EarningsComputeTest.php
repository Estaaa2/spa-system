<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Exceptions\PayrollSetupException;
use App\Services\Payroll\AttendanceEarnings;
use App\Services\Payroll\MinimumWageTopUp;
use App\Services\Payroll\RateResolver;
use App\Services\Payroll\RecurringEarnings;
use App\Services\Payroll\StatutoryCalculator;
use App\Services\Payroll\ValueObjects\AttendanceDay;
use App\Services\Payroll\ValueObjects\AttendanceResult;
use App\Services\Payroll\ValueObjects\CutoffContext;
use App\Services\Payroll\ValueObjects\EarningLine;
use App\Services\Payroll\ValueObjects\PayProfileSnapshot;
use App\Services\Payroll\ValueObjects\ProfileTimeline;
use App\Services\Payroll\ValueObjects\RecurringItemSnapshot;
use App\Services\Payroll\ValueObjects\WorkedDay;
use PHPUnit\Framework\TestCase;

/**
 * Pure earnings math (no DB, no app boot) — Unit 3.
 * Expected values are worked by hand from config/payroll.php (rates sheet §6, §10):
 * daily ₱600 → hourly ₱75; ordinary OT 1.25; rest day 1.30 / OT 1.69; special 1.30;
 * special on rest day 1.50; regular holiday 2.00; regular on rest day 2.60; ND 0.10;
 * unpaid meal 60 min; monthly daily = base × 12 ÷ 365.
 *
 * Calendar used (2026): Aug 16/23/30 Sun · Aug 21 Fri special non-working ·
 * Aug 29 Sat · Aug 31 Mon regular holiday · Nov 1 Sun special non-working ·
 * Feb 25 Wed special working.
 */
final class EarningsComputeTest extends TestCase
{
    private const HOME = 1;
    private const DEPLOYED = 2;

    private StatutoryCalculator $calc;
    private RateResolver $rates;
    private AttendanceEarnings $attendance;
    private int $rowId = 100;

    protected function setUp(): void
    {
        $this->calc = new StatutoryCalculator(require dirname(__DIR__, 3).'/config/payroll.php');
        $this->rates = new RateResolver($this->calc);
        $this->attendance = new AttendanceEarnings($this->calc, $this->rates);
    }

    // ── Fixtures ──────────────────────────────────────────────────────────

    private function daily(string $rate = '600', array $rest = ['Sunday'], string $from = '2026-01-01', ?string $to = null, bool $commission = false): PayProfileSnapshot
    {
        return new PayProfileSnapshot(1, $from, $to, 'daily', bcadd($rate, '0', 6), $commission, $rest);
    }

    private function monthly(string $rate = '18250', array $rest = ['Sunday'], string $from = '2026-01-01', ?string $to = null): PayProfileSnapshot
    {
        return new PayProfileSnapshot(2, $from, $to, 'monthly', bcadd($rate, '0', 6), false, $rest);
    }

    /**
     * @param list<PayProfileSnapshot> $profiles
     * @param list<string>             $closed   weekdays the home branch is closed
     */
    private function ctx(array $profiles, string $start = '2026-08-16', string $end = '2026-08-31', int $cutoff = 2, array $closed = []): CutoffContext
    {
        $from = (new \DateTimeImmutable($start))->modify('-'.AttendanceEarnings::LOOKBACK_DAYS.' days')->format('Y-m-d');
        $holidays = [];
        foreach ($this->calc->holidaysBetween($from, $end) as $h) {
            $holidays[$h->date] = $h;
        }

        return new CutoffContext(1, 1, self::HOME, $start, $end, $cutoff, 99, new ProfileTimeline($profiles), $holidays, $closed);
    }

    private function row(string $date, string $status = 'present', ?string $in = '09:00:00', ?string $out = '18:00:00', int $branch = self::HOME, bool $autoClosed = false): AttendanceDay
    {
        return new AttendanceDay($this->rowId++, $date, $branch, $status, $in, $out, $autoClosed);
    }

    /** @param list<AttendanceDay> $rows */
    private function run(array $rows, CutoffContext $ctx): AttendanceResult
    {
        return $this->attendance->compute($rows, $ctx);
    }

    /** @return list<EarningLine> */
    private function of(AttendanceResult|array $r, string $code): array
    {
        $lines = $r instanceof AttendanceResult ? $r->lines : $r;

        return array_values(array_filter($lines, static fn (EarningLine $l): bool => $l->componentCode === $code));
    }

    private function assertLine(EarningLine $l, string $qty, string $rate, string $amount): void
    {
        $this->assertSame([$qty, $rate, $amount], [$l->quantity, $l->rate, $l->amount], $l->componentCode.' '.$l->note);
    }

    private function total(array $lines): string
    {
        return array_reduce($lines, static fn (string $c, EarningLine $l): string => bcadd($c, $l->amount, 2), '0.00');
    }

    // ── RateResolver ──────────────────────────────────────────────────────

    public function test_rates_daily_paid(): void
    {
        $this->assertSame('600.00', $this->rates->dailyRate($this->daily(), '2026-08-18'));
        $this->assertSame('75.0000', $this->rates->hourlyRate($this->daily(), '2026-08-18'));
    }

    public function test_rates_monthly_paid_use_factor_365(): void
    {
        // §10: 18,250 × 12 ÷ 365 = 600.00; 18,000 × 12 ÷ 365 = 591.7808… → 591.78; ÷ 8 = 73.9725
        $this->assertSame('600.00', $this->rates->dailyRate($this->monthly('18250'), '2026-08-18'));
        $this->assertSame('591.78', $this->rates->dailyRate($this->monthly('18000'), '2026-08-18'));
        $this->assertSame('73.9725', $this->rates->hourlyRate($this->monthly('18000'), '2026-08-18'));
    }

    // ── Ordinary day, OT, meal break ──────────────────────────────────────

    public function test_ordinary_day_nine_hour_span_has_no_ot(): void
    {
        $r = $this->run([$this->row('2026-08-18')], $this->ctx([$this->daily()]));

        $this->assertCount(1, $r->lines);
        $this->assertLine($this->of($r, 'BASIC')[0], '1.00', '600.0000', '600.00');
        $this->assertSame('attendance', $r->lines[0]->sourceType);
        $this->assertCount(1, $r->workedDays);
    }

    public function test_ordinary_ot_after_meal_break(): void
    {
        // 09:00–20:00 = 11 h − 1 h meal − 8 h = 2 h × 75 × 1.25
        $r = $this->run([$this->row('2026-08-18', out: '20:00:00')], $this->ctx([$this->daily()]));
        $this->assertLine($this->of($r, 'OT')[0], '2.00', '93.7500', '187.50');
    }

    public function test_partial_ot_hour_rounds_half_up(): void
    {
        // 30 min → 0.50 h × 93.75 = 46.875 → 46.88
        $r = $this->run([$this->row('2026-08-18', out: '18:30:00')], $this->ctx([$this->daily()]));
        $this->assertLine($this->of($r, 'OT')[0], '0.50', '93.7500', '46.88');
    }

    public function test_late_counts_as_present(): void
    {
        $r = $this->run([$this->row('2026-08-18', 'late', '09:40:00')], $this->ctx([$this->daily()]));
        $this->assertSame('600.00', $this->of($r, 'BASIC')[0]->amount);
    }

    public function test_auto_closed_row_pays_basic_but_no_ot(): void
    {
        $r = $this->run([$this->row('2026-08-18', out: '23:59:00', autoClosed: true)], $this->ctx([$this->daily()]));
        $this->assertCount(1, $this->of($r, 'BASIC'));
        $this->assertSame([], $this->of($r, 'OT'));
        $this->assertSame([], $this->of($r, 'NIGHT_DIFF'));
        $this->assertStringContainsString('auto-closed', implode(' ', $r->warnings));
    }

    // ── Rest day ──────────────────────────────────────────────────────────

    public function test_rest_day(): void
    {
        $r = $this->run([$this->row('2026-08-23', out: '19:00:00')], $this->ctx([$this->daily()]));

        $this->assertLine($this->of($r, 'BASIC')[0], '1.00', '600.0000', '600.00');
        $this->assertLine($this->of($r, 'RESTDAY_PREM')[0], '1.00', '180.0000', '180.00'); // 0.30 × 600
        $this->assertLine($this->of($r, 'OT')[0], '1.00', '126.7500', '126.75');           // 75 × 1.69
        $this->assertSame([], $this->of($r, 'HOLIDAY_PREM'));
    }

    // ── Regular holiday ───────────────────────────────────────────────────

    public function test_regular_holiday_worked(): void
    {
        $r = $this->run([$this->row('2026-08-31')], $this->ctx([$this->daily()]));

        $this->assertSame('600.00', $this->of($r, 'BASIC')[0]->amount);
        $this->assertLine($this->of($r, 'HOLIDAY_PREM')[0], '1.00', '600.0000', '600.00'); // (2.00 − 1) × 600
        $this->assertSame([], $this->of($r, 'HOLIDAY_PAY'));
    }

    public function test_regular_holiday_on_rest_day_worked(): void
    {
        $r = $this->run([$this->row('2026-08-31', out: '19:00:00')], $this->ctx([$this->daily(rest: ['Monday'])]));

        $this->assertSame('960.00', $this->of($r, 'HOLIDAY_PREM')[0]->amount); // (2.60 − 1) × 600
        $this->assertSame('253.50', $this->of($r, 'OT')[0]->amount);           // 75 × 3.38
        $this->assertSame([], $this->of($r, 'RESTDAY_PREM'));
    }

    public function test_regular_holiday_unworked_paid_when_present_on_preceding_workday(): void
    {
        // Aug 30 is Sunday (rest day) → preceding workday is Sat Aug 29.
        $r = $this->run([$this->row('2026-08-29')], $this->ctx([$this->daily()]));

        $pay = $this->of($r, 'HOLIDAY_PAY');
        $this->assertCount(1, $pay);
        $this->assertLine($pay[0], '1.00', '600.0000', '600.00');
        $this->assertSame('2026-08-31', $pay[0]->date);
        $this->assertSame(self::HOME, $pay[0]->branchId);
        $this->assertNull($pay[0]->sourceType);
        $this->assertStringContainsString('2026-08-29', $pay[0]->note);
    }

    public function test_regular_holiday_unworked_not_paid_when_absent_on_leave_or_no_record(): void
    {
        foreach (['absent', 'on_leave'] as $status) {
            $r = $this->run([$this->row('2026-08-29', $status, null, null)], $this->ctx([$this->daily()]));
            $this->assertSame([], $this->of($r, 'HOLIDAY_PAY'), $status);
            $this->assertStringContainsString("2026-08-29 ({$status})", implode(' ', $r->warnings));
        }

        $r = $this->run([$this->row('2026-08-28')], $this->ctx([$this->daily()])); // Fri present, Sat missing
        $this->assertSame([], $this->of($r, 'HOLIDAY_PAY'));
        $this->assertStringContainsString('2026-08-29 (no attendance record)', implode(' ', $r->warnings));
    }

    public function test_preceding_workday_lookback_crosses_into_previous_cutoff(): void
    {
        // One-day period (Aug 31): the preceding workday, Sat Aug 29, is outside the
        // period and must still be read from the lookback rows.
        $r = $this->run([$this->row('2026-08-29')], $this->ctx([$this->daily()], '2026-08-31', '2026-08-31'));
        $this->assertCount(1, $this->of($r, 'HOLIDAY_PAY'));
    }

    /** @return array<string, EarningLine> HOLIDAY_PAY lines keyed by date */
    private function holidayPay(AttendanceResult $r): array
    {
        $out = [];
        foreach ($this->of($r, 'HOLIDAY_PAY') as $l) {
            $out[$l->date] = $l;
        }

        return $out;
    }

    public function test_successive_holidays_present_before_first_pays_both(): void
    {
        // Omnibus Rules Rule IV Sec. 10 — Maundy Thu Apr 2 + Good Fri Apr 3, 2026
        $r = $this->run([$this->row('2026-04-01')], $this->ctx([$this->daily()], '2026-04-01', '2026-04-15', 1));
        $pay = $this->holidayPay($r);

        $this->assertArrayHasKeys(['2026-04-02', '2026-04-03'], $pay);
        $this->assertStringContainsString('present on preceding workday 2026-04-01', $pay['2026-04-03']->note);
    }

    public function test_successive_holidays_absent_before_but_worked_first_pays_second(): void
    {
        // Sec. 10: "unless he works on the first holiday, in which case he is entitled to his holiday pay on the second"
        $rows = [$this->row('2026-04-01', 'absent', null, null), $this->row('2026-04-02')];
        $r = $this->run($rows, $this->ctx([$this->daily()], '2026-04-01', '2026-04-15', 1));
        $pay = $this->holidayPay($r);

        $this->assertArrayNotHasKeyPair('2026-04-02', $pay);           // worked → BASIC + premium instead
        $this->assertArrayHasKeys(['2026-04-03'], $pay);
        $this->assertStringContainsString('worked on 2026-04-02 (holiday)', $pay['2026-04-03']->note);
        $this->assertSame('600.00', $this->of($r, 'HOLIDAY_PREM')[0]->amount);
    }

    public function test_successive_holidays_absent_before_and_not_worked_pays_neither(): void
    {
        $r = $this->run([$this->row('2026-04-01', 'absent', null, null)], $this->ctx([$this->daily()], '2026-04-01', '2026-04-15', 1));
        $pay = $this->holidayPay($r);

        $this->assertArrayNotHasKeyPair('2026-04-02', $pay);
        $this->assertArrayNotHasKeyPair('2026-04-03', $pay);
    }

    public function test_branch_closed_day_is_skipped_like_a_rest_day(): void
    {
        // Sec. 6(c): Sat Aug 29 is a non-working day of the establishment → look at Fri Aug 28
        $r = $this->run([$this->row('2026-08-28')], $this->ctx([$this->daily()], closed: ['Saturday']));
        $this->assertStringContainsString('present on preceding workday 2026-08-28', $this->holidayPay($r)['2026-08-31']->note);

        $open = $this->run([$this->row('2026-08-28')], $this->ctx([$this->daily()]));
        $this->assertSame([], $this->holidayPay($open)); // branch open Saturday → Sat is the workday, no record
    }

    public function test_working_the_rest_day_before_the_holiday_counts_as_present(): void
    {
        // Sat Aug 29 absent, but worked Sun Aug 30 (rest day) — DESIGN: presence
        $rows = [$this->row('2026-08-29', 'absent', null, null), $this->row('2026-08-30')];
        $r = $this->run($rows, $this->ctx([$this->daily()]));
        $this->assertStringContainsString('worked on 2026-08-30 (rest day)', $this->holidayPay($r)['2026-08-31']->note);
    }

    public function test_on_leave_before_holiday_warning_explains_paid_leave(): void
    {
        $r = $this->run([$this->row('2026-08-29', 'on_leave', null, null)], $this->ctx([$this->daily()]));
        $this->assertStringContainsString('If that leave was paid, add the holiday pay as an adjustment', implode(' ', $r->warnings));
    }

    /** @param list<string> $keys */
    private function assertArrayHasKeys(array $keys, array $arr): void
    {
        foreach ($keys as $k) {
            $this->assertTrue(array_key_exists($k, $arr), "missing {$k}");
        }
    }

    private function assertArrayNotHasKeyPair(string $key, array $arr): void
    {
        $this->assertFalse(array_key_exists($key, $arr), "unexpected {$key}");
    }

    public function test_monthly_paid_gets_no_holiday_pay_line(): void
    {
        $r = $this->run([$this->row('2026-08-29')], $this->ctx([$this->monthly()]));
        $this->assertSame([], $this->of($r, 'HOLIDAY_PAY'));
    }

    // ── Special days ──────────────────────────────────────────────────────

    public function test_special_non_working_day_worked(): void
    {
        $r = $this->run([$this->row('2026-08-21')], $this->ctx([$this->daily()]));
        $this->assertLine($this->of($r, 'HOLIDAY_PREM')[0], '1.00', '180.0000', '180.00'); // 0.30 × 600
    }

    public function test_special_non_working_day_unworked_pays_nothing(): void
    {
        $r = $this->run([], $this->ctx([$this->daily()]));
        $this->assertSame([], $r->lines);
    }

    public function test_special_day_on_rest_day(): void
    {
        $r = $this->run([$this->row('2026-11-01')], $this->ctx([$this->daily()], '2026-11-01', '2026-11-15', 1));
        $this->assertLine($this->of($r, 'HOLIDAY_PREM')[0], '1.00', '300.0000', '300.00'); // 0.50 × 600
        $this->assertSame([], $this->of($r, 'RESTDAY_PREM'));
    }

    public function test_special_working_day_is_ordinary(): void
    {
        $r = $this->run([$this->row('2026-02-25', out: '19:00:00')], $this->ctx([$this->daily()], '2026-02-16', '2026-02-28'));

        $this->assertCount(1, $this->of($r, 'BASIC'));
        $this->assertSame([], $this->of($r, 'HOLIDAY_PREM'));
        $this->assertSame('93.75', $this->of($r, 'OT')[0]->amount); // ordinary OT rate
    }

    // ── Night differential ────────────────────────────────────────────────

    public function test_night_diff_regular_hours(): void
    {
        // 14:00–23:00 = 9 h, no OT; 22:00–23:00 at 10% of 75
        $r = $this->run([$this->row('2026-08-18', in: '14:00:00', out: '23:00:00')], $this->ctx([$this->daily()]));
        $this->assertSame([], $this->of($r, 'OT'));
        $this->assertLine($this->of($r, 'NIGHT_DIFF')[0], '1.00', '7.5000', '7.50');
    }

    public function test_night_diff_on_ot_hours_uses_ot_rate(): void
    {
        // 13:00–23:00 = 10 h → 1 h OT (22–23); ND on that hour = 10% of 93.75 = 9.375 → 9.38
        $r = $this->run([$this->row('2026-08-18', in: '13:00:00', out: '23:00:00')], $this->ctx([$this->daily()]));
        $this->assertSame('93.75', $this->of($r, 'OT')[0]->amount);
        $this->assertSame('9.38', $this->of($r, 'NIGHT_DIFF')[0]->amount);
    }

    public function test_night_diff_on_holiday_uses_premium_rate(): void
    {
        // Regular holiday: 10% of (75 × 2.00) — §6 UNVERIFIED reading
        $r = $this->run([$this->row('2026-08-31', in: '14:00:00', out: '23:00:00')], $this->ctx([$this->daily()]));
        $this->assertSame('15.00', $this->of($r, 'NIGHT_DIFF')[0]->amount);
    }

    public function test_night_diff_early_morning(): void
    {
        // 05:00–14:00: 05:00–06:00 is night work (Art. 86: 22:00–06:00)
        $r = $this->run([$this->row('2026-08-18', in: '05:00:00', out: '14:00:00')], $this->ctx([$this->daily()]));
        $this->assertSame('7.50', $this->of($r, 'NIGHT_DIFF')[0]->amount);
    }

    public function test_cross_midnight_row_is_flagged_not_paid_ot(): void
    {
        $r = $this->run([$this->row('2026-08-18', in: '20:00:00', out: '02:00:00')], $this->ctx([$this->daily()]));
        $this->assertCount(1, $this->of($r, 'BASIC'));
        $this->assertSame([], $this->of($r, 'NIGHT_DIFF'));
        $this->assertStringContainsString('cross-midnight', implode(' ', $r->warnings));
    }

    // ── Deployment branch attribution ─────────────────────────────────────

    public function test_lines_carry_the_attendance_branch(): void
    {
        $r = $this->run([$this->row('2026-08-23', out: '20:00:00', branch: self::DEPLOYED)], $this->ctx([$this->daily()]));

        foreach ($r->lines as $line) {
            $this->assertSame(self::DEPLOYED, $line->branchId, $line->componentCode);
        }
        $this->assertSame(self::DEPLOYED, $r->workedDays[0]->branchId);
    }

    // ── Monthly-paid ──────────────────────────────────────────────────────

    public function test_monthly_paid_half_month_minus_absences(): void
    {
        $rows = [
            $this->row('2026-08-18', 'absent', null, null),
            $this->row('2026-08-19', 'on_leave', null, null),
            $this->row('2026-08-23', 'absent', null, null),  // Sunday rest day → not deducted
            $this->row('2026-08-31', 'absent', null, null),  // regular holiday → not deducted
        ];
        $r = $this->run($rows, $this->ctx([$this->monthly('18250')]));

        $basic = $this->of($r, 'BASIC');
        $this->assertCount(3, $basic);
        $this->assertLine($basic[0], '1.00', '9125.0000', '9125.00');
        $this->assertLine($basic[1], '-1.00', '600.0000', '-600.00');
        $this->assertLine($basic[2], '-1.00', '600.0000', '-600.00');
        $this->assertSame('7925.00', $this->total($basic));
        $this->assertSame([], $this->of($r, 'HOLIDAY_PAY'));

        // Working days with no row: 17, 20, 22, 24–29 (21 special, 23/30 Sunday, 31 holiday)
        $this->assertStringContainsString('9 scheduled working day(s) have no attendance record', implode(' ', $r->warnings));
    }

    public function test_monthly_paid_rest_day_work_gets_premium_only(): void
    {
        $r = $this->run([$this->row('2026-08-23')], $this->ctx([$this->monthly('18250')]));

        $this->assertCount(1, $this->of($r, 'BASIC')); // the semi-monthly line only
        $this->assertSame('180.00', $this->of($r, 'RESTDAY_PREM')[0]->amount);
        $this->assertSame('600.00', $r->workedDays[0]->dailyRate);
    }

    public function test_monthly_paid_absences_are_capped_at_basic(): void
    {
        // No rest days, Jan 16–31 (no holidays): daily 36,500 × 12 ÷ 365 = 1,200; half = 18,250.
        // 15 absences = 18,000; the 16th is capped at 250; basic nets to 0.
        $rows = array_map(fn (string $d) => $this->row($d, 'absent', null, null), CutoffContext::range('2026-01-16', '2026-01-31'));
        $r = $this->run($rows, $this->ctx([$this->monthly('36500', [])], '2026-01-16', '2026-01-31'));

        $basic = $this->of($r, 'BASIC');
        $this->assertSame('0.00', $this->total($basic));
        $this->assertSame('-250.00', $basic[16]->amount);
    }

    public function test_monthly_paid_prorated_when_profile_starts_mid_cutoff(): void
    {
        // 8 of 16 days: 9,125 × 8 ÷ 16 = 4,562.50
        $r = $this->run([], $this->ctx([$this->monthly('18250', from: '2026-08-24')]));
        $this->assertSame('4562.50', $this->of($r, 'BASIC')[0]->amount);
        $this->assertStringContainsString('prorated', implode(' ', $r->warnings));
    }

    public function test_worked_day_without_profile_is_warned_not_paid(): void
    {
        $r = $this->run([$this->row('2026-08-18')], $this->ctx([$this->daily(from: '2026-08-20')]));
        $this->assertSame([], $this->of($r, 'BASIC'));
        $this->assertStringContainsString('no pay profile', implode(' ', $r->warnings));
    }

    // ── Recurring items ───────────────────────────────────────────────────

    public function test_recurring_items(): void
    {
        $rec = new RecurringEarnings($this->calc);
        $p = $this->daily();
        $days = [
            new WorkedDay('2026-08-18', 1, self::HOME, $p, '600.00'),
            new WorkedDay('2026-08-19', 2, self::HOME, $p, '600.00'),
            new WorkedDay('2026-08-25', 3, self::HOME, $p, '600.00'),
        ];
        $items = [
            new RecurringItemSnapshot(11, 'earning', 'ALLOWANCE', 'Transport', '500.000000', 'per_cutoff', '2026-01-01', null, true),
            new RecurringItemSnapshot(12, 'earning', 'ALLOWANCE', 'Meal', '50.000000', 'per_day_worked', '2026-08-19', null, true),
            new RecurringItemSnapshot(13, 'earning', 'ALLOWANCE', 'Rice', '1000.000000', 'second_cutoff_only', '2026-01-01', null, true),
            new RecurringItemSnapshot(14, 'earning', 'ALLOWANCE', 'Ended', '999.000000', 'per_cutoff', '2026-01-01', '2026-08-15', true),
            new RecurringItemSnapshot(15, 'earning', 'ALLOWANCE', 'Inactive', '999.000000', 'per_cutoff', '2026-01-01', null, false),
        ];

        $out = $rec->compute($items, $this->ctx([$p]), $days);
        $byId = [];
        foreach ($out['lines'] as $l) {
            $byId[$l->sourceId] = $l;
        }

        $this->assertSame([11, 12, 13], array_keys($byId));
        $this->assertLine($byId[11], '1.00', '500.0000', '500.00');
        $this->assertLine($byId[12], '2.00', '50.0000', '100.00'); // only days from Aug 19
        $this->assertSame('1000.00', $byId[13]->amount);
        $this->assertSame('recurring_item', $byId[11]->sourceType);

        $cutoff1 = $rec->compute($items, $this->ctx([$p], '2026-08-01', '2026-08-15', 1), []);
        $this->assertNotContains(13, array_map(static fn (EarningLine $l) => $l->sourceId, $cutoff1['lines']));
    }

    // ── Minimum-wage top-up ───────────────────────────────────────────────

    private function wages(string $home = '600', ?string $deployed = '650'): array
    {
        return [
            self::HOME => ['name' => 'Home', 'wage' => $home === null ? null : bcadd($home, '0', 6), 'from' => null],
            self::DEPLOYED => ['name' => 'Deployed', 'wage' => $deployed === null ? null : bcadd($deployed, '0', 6), 'from' => null],
        ];
    }

    private function commissionLine(string $date, string $amount): EarningLine
    {
        return EarningLine::priced($this->calc->component('COMMISSION'), '1', $amount, self::HOME, 'booking', 500, null, $date);
    }

    public function test_top_up_without_commission(): void
    {
        $r = $this->run([$this->row('2026-08-18')], $this->ctx([$this->daily('500')]));
        $top = (new MinimumWageTopUp($this->calc))->compute($r->lines, $r->workedDays, $this->wages());

        $this->assertCount(1, $top['lines']);
        $this->assertLine($top['lines'][0], '1.00', '100.0000', '100.00');
        $this->assertSame('attendance', $top['lines'][0]->sourceType);
    }

    public function test_top_up_reduced_or_removed_by_same_day_commission(): void
    {
        $r = $this->run([$this->row('2026-08-18')], $this->ctx([$this->daily('500')]));
        $mw = new MinimumWageTopUp($this->calc);

        $partial = $mw->compute([...$r->lines, $this->commissionLine('2026-08-18', '80')], $r->workedDays, $this->wages());
        $this->assertSame('20.00', $partial['lines'][0]->amount);

        $none = $mw->compute([...$r->lines, $this->commissionLine('2026-08-18', '150')], $r->workedDays, $this->wages());
        $this->assertSame([], $none['lines']);

        $otherDay = $mw->compute([...$r->lines, $this->commissionLine('2026-08-19', '150')], $r->workedDays, $this->wages());
        $this->assertSame('100.00', $otherDay['lines'][0]->amount); // commission counts on its appointment date only
    }

    public function test_top_up_ignores_premiums_and_uses_attendance_branch_wage(): void
    {
        // Rest day at a ₱650 branch: basic 600 counts, 180 premium does not → top-up 50 at the deployed branch
        $r = $this->run([$this->row('2026-08-23', branch: self::DEPLOYED)], $this->ctx([$this->daily('600')]));
        $top = (new MinimumWageTopUp($this->calc))->compute($r->lines, $r->workedDays, $this->wages());

        $this->assertSame('50.00', $top['lines'][0]->amount);
        $this->assertSame(self::DEPLOYED, $top['lines'][0]->branchId);
    }

    public function test_top_up_for_monthly_paid_counts_daily_rate(): void
    {
        $r = $this->run([$this->row('2026-08-18', branch: self::DEPLOYED)], $this->ctx([$this->monthly('18250')]));
        $top = (new MinimumWageTopUp($this->calc))->compute($r->lines, $r->workedDays, $this->wages());
        $this->assertSame('50.00', $top['lines'][0]->amount); // 650 − 600
    }

    public function test_monthly_semi_monthly_line_does_not_count_toward_a_single_day(): void
    {
        // Regression: the ½-month BASIC line is not a per-day line, so working the
        // first day of the cutoff at a ₱650 branch still tops up ₱50 (650 − 600 daily).
        $r = $this->run([$this->row('2026-09-01', branch: self::DEPLOYED)], $this->ctx([$this->monthly('18250')], '2026-09-01', '2026-09-15', 1));
        $this->assertNull($this->of($r, 'BASIC')[0]->date);

        $top = (new MinimumWageTopUp($this->calc))->compute($r->lines, $r->workedDays, $this->wages());
        $this->assertSame('50.00', $top['lines'][0]->amount);
    }

    public function test_top_up_throws_when_branch_has_no_minimum_wage(): void
    {
        $r = $this->run([$this->row('2026-08-18', branch: self::DEPLOYED)], $this->ctx([$this->daily()]));

        $this->expectException(PayrollSetupException::class);
        $this->expectExceptionMessage('Branch "Deployed" (#2) has no minimum daily wage');
        (new MinimumWageTopUp($this->calc))->compute($r->lines, $r->workedDays, $this->wages('600', null));
    }
}
