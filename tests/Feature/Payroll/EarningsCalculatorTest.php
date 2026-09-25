<?php

namespace Tests\Feature\Payroll;

use App\Exceptions\PayrollSetupException;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Services\Payroll\ValueObjects\EarningLine;
use App\Services\Payroll\ValueObjects\EarningsResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Payroll\Concerns\PayrollFixtures;
use Tests\TestCase;

/**
 * Unit 3 — Earnings Engine, end to end against the database.
 * Line-level math is covered exhaustively in tests/Unit/Payroll/EarningsComputeTest;
 * these tests prove the loaders, queries, and wiring produce the same numbers.
 *
 * Calendar (2026): Aug 16/23/30 Sun · Aug 21 Fri special non-working · Aug 29 Sat ·
 * Aug 31 Mon regular holiday (National Heroes Day) · Nov 1 Sun special non-working ·
 * Feb 25 Wed special working. Daily rate ₱600 → hourly ₱75.
 */
class EarningsCalculatorTest extends TestCase
{
    use RefreshDatabase;
    use PayrollFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeWorld();
    }

    /** @return list<EarningLine> */
    private function lines(EarningsResult $r, string $code): array
    {
        return $r->linesFor($code);
    }

    // ── Attendance ────────────────────────────────────────────────────────

    public function test_ordinary_day(): void
    {
        $this->profile();
        $att = $this->attend('2026-08-18');

        $r = $this->calculate();

        $this->assertSame('600.00', $r->totalFor('BASIC'));
        $this->assertSame('600.00', $r->gross());
        $this->assertSame('1.00', $r->daysWorked);
        $this->assertSame($att->id, $r->lines[0]->sourceId);
        $this->assertSame('attendance', $r->lines[0]->sourceType);
        $this->assertSame($this->branchA->id, $r->lines[0]->branchId);
    }

    public function test_overtime_after_one_hour_meal_break(): void
    {
        $this->profile();
        $this->attend('2026-08-18', out: '20:00:00'); // 11 h − 1 h meal − 8 h = 2 h

        $this->assertSame('187.50', $this->calculate()->totalFor('OT')); // 2 × 75 × 1.25
    }

    public function test_rest_day(): void
    {
        $this->profile();
        $this->attend('2026-08-23');

        $r = $this->calculate();
        $this->assertSame('600.00', $r->totalFor('BASIC'));
        $this->assertSame('180.00', $r->totalFor('RESTDAY_PREM'));
    }

    public function test_regular_holiday_worked(): void
    {
        $this->profile();
        $this->attend('2026-08-31');

        $r = $this->calculate();
        $this->assertSame('600.00', $r->totalFor('BASIC'));
        $this->assertSame('600.00', $r->totalFor('HOLIDAY_PREM'));
        $this->assertSame([], $this->lines($r, 'HOLIDAY_PAY'));
    }

    public function test_regular_holiday_unworked_with_preceding_day_present(): void
    {
        $this->profile();
        $this->attend('2026-08-29'); // Sat; Sun Aug 30 is the rest day

        $this->assertSame('600.00', $this->calculate()->totalFor('HOLIDAY_PAY'));
    }

    public function test_regular_holiday_unworked_without_preceding_day(): void
    {
        $this->profile();
        $this->attend('2026-08-29', 'absent', null, null);

        $r = $this->calculate();
        $this->assertSame([], $this->lines($r, 'HOLIDAY_PAY'));
        $this->assertStringContainsString('preceding workday 2026-08-29 (absent)', implode(' ', $r->warnings));
    }

    public function test_special_non_working_day_worked(): void
    {
        $this->profile();
        $this->attend('2026-08-21');

        $this->assertSame('180.00', $this->calculate()->totalFor('HOLIDAY_PREM'));
    }

    public function test_special_non_working_day_on_rest_day(): void
    {
        $this->profile();
        $this->attend('2026-11-01');

        $r = $this->calculate($this->payrollRun('2026-11-01', '2026-11-15', 1));
        $this->assertSame('300.00', $r->totalFor('HOLIDAY_PREM'));
        $this->assertSame([], $this->lines($r, 'RESTDAY_PREM'));
    }

    public function test_special_working_day_is_ordinary(): void
    {
        $this->profile();
        $this->attend('2026-02-25');

        $r = $this->calculate($this->payrollRun('2026-02-16', '2026-02-28', 2));
        $this->assertSame('600.00', $r->gross());
        $this->assertSame([], $this->lines($r, 'HOLIDAY_PREM'));
    }

    public function test_night_differential(): void
    {
        $this->profile();
        $this->attend('2026-08-18', in: '14:00:00', out: '23:00:00'); // 22:00–23:00, no OT

        $this->assertSame('7.50', $this->calculate()->totalFor('NIGHT_DIFF'));
    }

    public function test_deployment_branch_attribution(): void
    {
        $this->profile();
        $this->attend('2026-08-18', branch: $this->branchB);

        $r = $this->calculate();
        foreach ($r->lines as $line) {
            $this->assertSame($this->branchB->id, $line->branchId, $line->componentCode);
        }
        // Branch B minimum is ₱650 → top-up ₱50 charged to branch B
        $this->assertSame('50.00', $r->totalFor('MINWAGE_TOPUP'));
    }

    public function test_monthly_paid_absence(): void
    {
        $this->profile(['pay_basis' => 'monthly', 'base_rate' => '18250.00']); // daily 18,250 × 12 ÷ 365 = 600
        $this->attend('2026-08-18', 'absent', null, null);
        $this->attend('2026-08-19', 'on_leave', null, null);

        $r = $this->calculate();
        $this->assertSame('7925.00', $r->totalFor('BASIC')); // 9,125 − 600 − 600
        $this->assertCount(3, $this->lines($r, 'BASIC'));
        $this->assertStringContainsString('have no attendance record', implode(' ', $r->warnings));
    }

    // ── Commission ────────────────────────────────────────────────────────

    public function test_commission_excluded_when_completed_but_unpaid(): void
    {
        $this->profile();
        $this->percentRule();
        $this->booking('2026-08-18', ['payment_status' => 'unpaid', 'amount_paid' => '0.00']);
        $this->booking('2026-08-18', ['payment_status' => 'partially_paid', 'amount_paid' => '200.00']);
        $this->booking('2026-08-18', ['status' => 'ongoing']);

        $this->assertSame([], $this->lines($this->calculate(), 'COMMISSION'));
    }

    public function test_commission_completed_in_cutoff_1_paid_by_cutoff_2(): void
    {
        $this->profile();
        $this->percentRule();
        $b = $this->booking('2026-09-10', ['payment_status' => 'unpaid', 'amount_paid' => '0.00']);

        $cutoff1 = $this->calculate($this->payrollRun('2026-09-01', '2026-09-15', 1));
        $this->assertSame([], $this->lines($cutoff1, 'COMMISSION'));

        $b->update(['payment_status' => 'paid', 'amount_paid' => '1000.00']);

        $cutoff2 = $this->calculate($this->payrollRun('2026-09-16', '2026-09-30', 2));
        $line = $this->lines($cutoff2, 'COMMISSION')[0];
        $this->assertSame('100.00', $line->amount); // 10% of 1,000
        $this->assertSame($b->id, $line->sourceId);
        $this->assertSame('2026-09-10', $line->date);
        $this->assertStringContainsString('appt 2026-09-10', $line->note);
        $this->assertStringContainsString('base ₱1,000.00 (total_amount)', $line->note);
    }

    public function test_commission_falls_back_to_list_price_when_total_is_zero(): void
    {
        $this->profile();
        $this->percentRule();
        $t = $this->treatment('1500.00', $this->branchA);
        $this->booking('2026-08-18', ['total_amount' => '0.00', 'treatment' => 'treatment_'.$t->id]);

        // Logged in at branch B: the spa_branch global scope would hide a branch-A treatment.
        // forceFill: users.branch_id may not be mass-assignable, and a silently ignored
        // update would make this test pass without exercising the scope.
        $this->admin->forceFill(['branch_id' => $this->branchB->id])->save();
        $this->actingAs($this->admin->fresh());
        $this->assertNull(\App\Models\Treatment::find($t->id), 'precondition: the global scope hides the branch-A treatment');

        $line = $this->lines($this->calculate(), 'COMMISSION')[0];
        $this->assertSame('150.00', $line->amount);
        $this->assertStringContainsString('list price of treatment_'.$t->id, $line->note);
    }

    public function test_no_double_commission_but_regenerating_the_same_run_is_not_blocked(): void
    {
        $this->profile();
        $this->percentRule();
        $b = $this->booking('2026-08-18');
        $current = $this->payrollRun();

        // Already paid in another run → excluded.
        $earlier = $this->payrollRun('2026-08-01', '2026-08-15', 1);
        $this->paidLine($earlier->id, $b->id);
        $this->assertSame([], $this->lines($this->calculate($current), 'COMMISSION'));

        // A line in the run being regenerated does not block it.
        PayslipLine::query()->delete();
        Payslip::query()->delete();
        $this->paidLine($current->id, $b->id);
        $this->assertCount(1, $this->lines($this->calculate($current), 'COMMISSION'));
    }

    private function paidLine(int $runId, int $bookingId): void
    {
        $payslip = Payslip::create([
            'payroll_run_id' => $runId, 'staff_id' => $this->staff->id, 'home_branch_id' => $this->branchA->id,
            'gross_pay' => '0.00', 'total_deductions' => '0.00', 'net_pay' => '0.00', 'taxable_compensation' => '0.00',
            'days_worked' => '0.00', 'is_mwe' => true, 'snapshot' => [],
        ]);
        PayslipLine::create([
            'payslip_id' => $payslip->id, 'component_code' => 'COMMISSION', 'label' => 'Commission', 'kind' => 'earning',
            'branch_id' => $this->branchA->id, 'quantity' => '1.00', 'rate' => '100.0000', 'amount' => '100.00',
            'source_type' => PayslipLine::SOURCE_BOOKING, 'source_id' => $bookingId,
        ]);
    }

    public function test_commission_skipped_when_profile_has_commission_disabled(): void
    {
        $this->profile(['commission_enabled' => false]);
        $this->percentRule();
        $this->booking('2026-08-18');

        $this->assertSame([], $this->lines($this->calculate(), 'COMMISSION'));
    }

    // ── Minimum-wage top-up ───────────────────────────────────────────────

    public function test_min_wage_top_up_without_commission(): void
    {
        $this->profile(['base_rate' => '500.00']);
        $this->attend('2026-08-18');

        $r = $this->calculate();
        $this->assertSame('100.00', $r->totalFor('MINWAGE_TOPUP'));
        $this->assertTrue($r->isMwe);
    }

    public function test_min_wage_top_up_with_commission(): void
    {
        $this->profile(['base_rate' => '500.00']);
        $this->percentRule();
        $this->attend('2026-08-18');
        $this->booking('2026-08-18', ['total_amount' => '800.00']); // commission 80

        $r = $this->calculate();
        $this->assertSame('80.00', $r->totalFor('COMMISSION'));
        $this->assertSame('20.00', $r->totalFor('MINWAGE_TOPUP'));
        $this->assertSame('600.00', $r->gross());
    }

    public function test_missing_branch_minimum_wage_throws_naming_the_branch(): void
    {
        $this->profile();
        $this->branchB->forceFill(['min_daily_wage' => null])->save();
        $this->attend('2026-08-18', branch: $this->branchB);

        $this->expectException(PayrollSetupException::class);
        $this->expectExceptionMessage('Branch "Branch B"');
        $this->calculate();
    }

    // ── Facade ────────────────────────────────────────────────────────────

    public function test_is_mwe_and_no_profile(): void
    {
        $this->profile(['base_rate' => '700.00']);
        $run = $this->payrollRun();
        $this->assertFalse($this->calculate($run)->isMwe);

        $this->staff->payProfiles()->delete();
        $r = $this->calculate($run);
        $this->assertSame([], $r->lines);
        $this->assertStringContainsString('no pay profile', implode(' ', $r->warnings));
    }

    public function test_nothing_is_written(): void
    {
        $this->profile();
        $this->attend('2026-08-18');
        $run = $this->payrollRun();

        $this->calculate($run);

        $this->assertSame(0, Payslip::query()->count());
        $this->assertSame(0, PayslipLine::query()->count());
    }
}
