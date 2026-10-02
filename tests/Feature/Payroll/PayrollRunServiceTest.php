<?php

namespace Tests\Feature\Payroll;

use App\Exceptions\PayrollSetupException;
use App\Exceptions\PayrollStateException;
use App\Models\CommissionRule;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Models\Spa;
use App\Models\Staff;
use App\Models\StaffPayProfile;
use App\Models\StaffRecurringItem;
use App\Models\User;
use App\Services\Payroll\PayrollRunService;
use App\Services\Payroll\ValueObjects\RunWarning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

/**
 * Unit 4 — PayrollRunService. Expected amounts were computed with the real
 * StatutoryCalculator and config/payroll.php (version 2026.4), not typed from memory:
 *
 *  Staff A (daily ₱700, Sunday rest day, commission 10%): 5 days per cutoff.
 *    c1: BASIC 3,500 + COMMISSION 100 · c2: BASIC 3,500 + COMMISSION 300
 *    Month SSS compensation 7,400 → MSC 7,500 → EE 375 / ER 750 / EC 10
 *    PhilHealth MBS 700 × 313 ÷ 12 = 18,258.33 → EE 456.46 / ER 456.46
 *    Pag-IBIG fund salary 7,000 (BASIC only) → EE 140 / ER 140
 *    c2 taxable 3,800 − 971.46 = 2,828.54 → WTAX 0
 *  Staff B (monthly ₱60,000): c1 WTAX on 30,000 = 3,604.10;
 *    c2: SSS 1,750/3,500/30, PHIC 1,500/1,500, HDMF 200/200, WTAX on 26,550 = 2,914.10
 *  Staff C (daily ₱600 = branch minimum → MWE, commission 15,000 in c1):
 *    taxable 15,000 → WTAX 687.45 (would be 1,204.10 on 18,000 without the MWE exclusion)
 *  Staff G (daily ₱700, one day in the month): SSS 250 (MSC 5,000), PHIC 456.46,
 *    HDMF EE 7 / ER 14 (fund salary 700 ≤ 1,500 → 1% / 2%); the floor applies 250 + 450.
 *
 * Test data is inserted directly (no factories are assumed to exist for these tables).
 */
class PayrollRunServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayrollRunService $service;
    private User $owner;
    private Spa $spa;
    private int $suiteBranch;
    private int $plainBranch;
    private int $ruleId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-12-01 09:00:00');   // every September period has ended

        $this->service = app(PayrollRunService::class);
        $this->owner = User::findOrFail($this->makeUser('Olive', 'Owner'));

        $spaId = DB::table('spas')->insertGetId([
            'owner_id' => $this->owner->id, 'name' => 'Test Spa', 'business_tier' => 'professional',
            'payroll_first_cutoff_day' => 15, 'payroll_pay_day_offset' => 5,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->spa = Spa::findOrFail($spaId);

        $this->suiteBranch = $this->makeBranch('Suite Branch', true);
        $this->plainBranch = $this->makeBranch('Plain Branch', false);

        $this->ruleId = CommissionRule::create([
            'spa_id' => $spaId, 'branch_id' => null, 'target_type' => 'default', 'target_id' => null,
            'method' => 'percent', 'value' => '10.0000', 'effective_from' => '2026-01-01',
            'created_by' => $this->owner->id,
        ])->id;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Tests ────────────────────────────────────────────────────────────────

    public function test_cutoff_one_has_no_contributions(): void
    {
        $a = $this->staffA();

        $result = $this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner);

        $this->assertSame('2026-09-01', $result->run->period_start->toDateString());
        $this->assertSame('2026-09-15', $result->run->period_end->toDateString());
        $this->assertSame('2026-09-20', $result->run->pay_date->toDateString());
        $this->assertSame(config('payroll.version'), $result->run->config_version);

        $slip = $this->payslipFor($result->run, $a);
        $this->assertSame('3600.00', $slip->gross_pay);
        $this->assertSame(0, $slip->lines()->whereIn('component_code', ['SSS_EE', 'SSS_ER', 'SSS_EC', 'PHIC_EE', 'PHIC_ER', 'HDMF_EE', 'HDMF_ER'])->count());
        $this->assertSame('3600.00', $slip->net_pay);

        // Snapshot records the rule and settings used (locked model). The rule id is parsed
        // from CommissionEarnings' note — this assertion fails if Unit 3 changes that format.
        $this->assertSame([$this->ruleId], $slip->snapshot['rule_ids']['commission_rules']);
        $this->assertSame('600.00', $slip->snapshot['home_branch']['min_daily_wage']);
        $this->assertSame(15, $slip->snapshot['spa_settings']['payroll_first_cutoff_day']);
    }

    public function test_cutoff_two_contributions_use_both_cutoffs_including_commission_variance(): void
    {
        $a = $this->staffA();
        $b = $this->staffB();

        $c1 = $this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner)->run;
        $this->finalize($c1);

        $result = $this->service->generateRegular($this->spa, 2026, 9, 2, $this->owner);
        $this->assertSame('2026-09-16', $result->run->period_start->toDateString());
        $this->assertSame('2026-10-05', $result->run->pay_date->toDateString());

        $slipA = $this->payslipFor($result->run, $a);
        $this->assertSame('3800.00', $slipA->gross_pay);   // commission 300 this cutoff vs 100 in c1
        // MSC 7,500 proves cutoff 1 was included (cutoff 2 alone, 3,800, would be MSC 5,000).
        $this->assertLine($slipA, 'SSS_EE', '375.00');
        $this->assertLine($slipA, 'SSS_ER', '750.00');
        $this->assertLine($slipA, 'SSS_EC', '10.00');
        $this->assertLine($slipA, 'PHIC_EE', '456.46');
        $this->assertLine($slipA, 'PHIC_ER', '456.46');
        $this->assertLine($slipA, 'HDMF_EE', '140.00');
        $this->assertLine($slipA, 'HDMF_ER', '140.00');
        $this->assertSame(0, $slipA->lines()->where('component_code', 'WTAX')->count());
        $this->assertSame('971.46', $slipA->total_deductions);
        $this->assertSame('2828.54', $slipA->net_pay);
        $this->assertSame('2828.54', $slipA->taxable_compensation);

        $slipB = $this->payslipFor($result->run, $b);
        $this->assertLine($slipB, 'SSS_EE', '1750.00');
        $this->assertLine($slipB, 'PHIC_EE', '1500.00');
        $this->assertLine($slipB, 'HDMF_EE', '200.00');
        $this->assertLine($slipB, 'WTAX', '2914.10');   // 30,000 − 3,450 contributions
        $this->assertSame('26550.00', $slipB->taxable_compensation);
    }

    public function test_cutoff_two_is_blocked_until_cutoff_one_is_finalized(): void
    {
        $this->staffA();

        $this->assertThrows(fn () => $this->service->generateRegular($this->spa, 2026, 9, 2, $this->owner), PayrollStateException::class);

        $c1 = $this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner)->run;
        $this->assertThrows(fn () => $this->service->generateRegular($this->spa, 2026, 9, 2, $this->owner), PayrollStateException::class);

        $c1 = $this->service->approve($c1, $this->owner);
        $this->assertThrows(fn () => $this->service->generateRegular($this->spa, 2026, 9, 2, $this->owner), PayrollStateException::class);

        $this->service->finalize($c1, $this->owner);
        $this->assertSame(PayrollRun::STATUS_DRAFT, $this->service->generateRegular($this->spa, 2026, 9, 2, $this->owner)->run->status);
    }

    public function test_wtax_without_mwe_uses_annex_e_semi_monthly(): void
    {
        $b = $this->staffB();

        $result = $this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner);
        $slip = $this->payslipFor($result->run, $b);

        $this->assertFalse($slip->is_mwe);
        $this->assertSame('30000.00', $slip->taxable_compensation);
        $this->assertLine($slip, 'WTAX', '3604.10');
        $this->assertSame('26395.90', $slip->net_pay);
    }

    public function test_wtax_with_mwe_excludes_exempt_components(): void
    {
        $c = $this->staffC();

        $result = $this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner);
        $slip = $this->payslipFor($result->run, $c);

        $this->assertTrue($slip->is_mwe);
        $this->assertSame('18000.00', $slip->gross_pay);
        $this->assertSame('15000.00', $slip->taxable_compensation);   // BASIC 3,000 exempt, commission taxable
        $this->assertLine($slip, 'WTAX', '687.45');
        $this->assertTrue($result->hasWarning(RunWarning::UNVERIFIED_RULE));
    }

    public function test_commission_therapists_are_flagged_for_the_cumulative_average_method(): void
    {
        $this->staffB();                 // ₱30,000 regular, no commission → not flagged
        $c = $this->staffC();            // MWE with commission → flagged (regular pay is exempt)
        $a = $this->staffA();            // ₱3,500 regular (below ₱10,417) + commission → flagged

        $result = $this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner);

        $flags = $result->warningsWithCode(RunWarning::CUMULATIVE_AVERAGE);
        $this->assertCount(1, $flags);                       // folded into one run-level warning
        $this->assertNull($flags[0]->staffId);
        $this->assertStringContainsString('2 payslip(s)', $flags[0]->message);
        $this->assertStringContainsString("#{$c->id}", $flags[0]->message);
        $this->assertStringContainsString("#{$a->id}", $flags[0]->message);

        // Detection only: the amount is still the per-cutoff Annex E figure (locked rule).
        $this->assertLine($this->payslipFor($result->run, $c), 'WTAX', '687.45');
    }

    public function test_transitions_are_logged_with_actor_and_self_approval_flag(): void
    {
        $this->staffB();
        $run = $this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner)->run;

        Log::spy();
        $run = $this->service->approve($run, $this->owner);          // generator approves own run
        $this->service->sendBackToDraft($run, $this->owner);

        Log::shouldHaveReceived('info')->with('payroll.run.transition', Mockery::on(fn (array $c): bool =>
            $c['to'] === PayrollRun::STATUS_APPROVED && $c['self_approved'] === true && $c['user_id'] === $this->owner->id));
        Log::shouldHaveReceived('info')->with('payroll.run.transition', Mockery::on(fn (array $c): bool =>
            $c['from'] === PayrollRun::STATUS_APPROVED && $c['to'] === PayrollRun::STATUS_DRAFT && $c['self_approved'] === false));
    }

    public function test_net_pay_never_goes_below_zero_and_unapplied_amounts_are_warnings(): void
    {
        $d = $this->makeStaff('Dana', 'Debt', $this->suiteBranch, 'daily', '700.00');
        $this->attend($d, ['2026-09-01']);
        $loan = $this->recurringDeduction($d, 'LOAN_DEDUCTION', 'Cash advance', '500.00');
        $this->recurringDeduction($d, 'OTHER_DEDUCTION', 'Uniform', '400.00');

        $result = $this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner);
        $slip = $this->payslipFor($result->run, $d);

        $this->assertSame('700.00', $slip->gross_pay);
        $this->assertSame('0.00', $slip->net_pay);
        $this->assertSame('700.00', $slip->total_deductions);
        $this->assertSame('500.00', $this->lineFor($slip, 'LOAN_DEDUCTION')->amount);
        $otherLine = $this->lineFor($slip, 'OTHER_DEDUCTION');
        $this->assertSame('200.00', $otherLine->amount);                        // applied
        $this->assertSame('400.0000', $otherLine->rate);                         // scheduled kept
        $this->assertTrue($result->hasWarning(RunWarning::UNAPPLIED_DEDUCTION));

        // A manual deduction after the net reached 0 applies nothing and warns again.
        $settlement = $this->service->addManualLine($slip, 'ADJ_DEDUCTION', 'Breakage', '100.00', null, $this->owner);
        $this->assertSame('0.00', $settlement->payslip->net_pay);
        $adj = $this->lineFor($settlement->payslip, 'ADJ_DEDUCTION');
        $this->assertSame('0.00', $adj->amount);
        $this->assertSame('100.0000', $adj->rate);
        $this->assertCount(2, array_filter($settlement->warnings, fn (RunWarning $w) => $w->code === RunWarning::UNAPPLIED_DEDUCTION));

        // Removing the loan frees room: the floor re-runs in order and fully applies OTHER.
        StaffRecurringItem::whereKey($loan)->update(['is_active' => false]);
        $slip = $this->payslipFor($this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner)->run, $d);
        $this->assertSame('400.00', $this->lineFor($slip, 'OTHER_DEDUCTION')->amount);
        $this->assertSame('100.00', $this->lineFor($slip, 'ADJ_DEDUCTION')->amount);
        $this->assertSame('200.00', $slip->net_pay);
    }

    public function test_deduction_without_authorization_is_blocked(): void
    {
        $d = $this->makeStaff('Dana', 'Debt', $this->suiteBranch, 'daily', '700.00');
        $this->attend($d, ['2026-09-01']);

        // Unit 1 hook: the model refuses to save it (Labor Code Art. 113).
        $this->assertThrows(fn () => StaffRecurringItem::create([
            'staff_id' => $d->id, 'kind' => 'deduction', 'component_code' => 'OTHER_DEDUCTION',
            'label' => 'No paper', 'amount' => '50.00', 'frequency' => 'per_cutoff',
            'start_date' => '2026-01-01', 'is_active' => true, 'created_by' => $this->owner->id,
        ]), ValidationException::class);

        // A row that reached the DB anyway is still not deducted.
        $rawId = DB::table('staff_recurring_items')->insertGetId([
            'staff_id' => $d->id, 'kind' => 'deduction', 'component_code' => 'OTHER_DEDUCTION',
            'label' => 'No paper', 'amount' => '50.00', 'frequency' => 'per_cutoff',
            'start_date' => '2026-01-01', 'authorization_ref' => null, 'is_active' => true,
            'created_by' => $this->owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = $this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner);
        $slip = $this->payslipFor($result->run, $d);

        $this->assertSame(0, $slip->lines()->where('source_type', 'recurring_item')->where('source_id', $rawId)->count());
        $this->assertSame('700.00', $slip->net_pay);
        $this->assertTrue($result->hasWarning(RunWarning::DEDUCTION_UNAUTHORIZED));
    }

    public function test_manual_line_survives_regeneration_and_is_kept_when_staff_becomes_ineligible(): void
    {
        $a = $this->staffA();

        $run = $this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner)->run;
        $slip = $this->payslipFor($run, $a);
        $settlement = $this->service->addManualLine($slip, 'ADJ_EARNING', 'Retro pay', '1000.00', 'August shortfall', $this->owner);
        $manual = $this->lineFor($settlement->payslip, 'ADJ_EARNING');
        $this->assertSame('4600.00', $settlement->payslip->gross_pay);

        $result = $this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner);
        $this->assertTrue($result->regenerated);
        $this->assertSame($run->id, $result->run->id);

        $slip = $this->payslipFor($result->run, $a);
        $this->assertSame($settlement->payslip->id, $slip->id);
        $this->assertTrue(PayslipLine::whereKey($manual->id)->where('payslip_id', $slip->id)->exists());
        $this->assertSame('4600.00', $slip->gross_pay);
        $this->assertSame(1, $slip->lines()->where('component_code', 'COMMISSION')->count());   // no duplicates

        // Staff no longer eligible: the payslip is kept with only the manual line, and flagged.
        $a->update(['employment_status' => 'inactive']);
        $result = $this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner);
        $slip = $this->payslipFor($result->run, $a);
        $this->assertSame(1, $slip->lines()->count());
        $this->assertSame('1000.00', $slip->gross_pay);
        $this->assertTrue($result->hasWarning(RunWarning::ORPHANED_MANUAL_LINES));

        // Removing its last manual line removes the orphaned payslip.
        $removed = $this->service->removeManualLine(PayslipLine::findOrFail($manual->id));
        $this->assertNull($removed->payslip);
        $this->assertFalse(Payslip::whereKey($slip->id)->exists());
    }

    public function test_finalized_run_is_immutable(): void
    {
        $b = $this->staffB();
        $run = $this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner)->run;
        $slip = $this->payslipFor($run, $b);

        $run = $this->service->approve($run, $this->owner);
        $this->assertSame($this->owner->id, $run->approved_by);
        $this->assertNotNull($run->approved_at);
        $this->assertThrows(fn () => $this->service->addManualLine($slip, 'ADJ_EARNING', 'Late', '10.00', null, $this->owner), PayrollStateException::class);

        $run = $this->service->sendBackToDraft($run, $this->owner);
        $this->assertNull($run->approved_by);
        $run = $this->service->finalize($this->service->approve($run, $this->owner), $this->owner);
        $this->assertSame(PayrollRun::STATUS_FINALIZED, $run->status);
        $this->assertNotNull($run->finalized_at);

        $this->assertThrows(fn () => $this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner), PayrollStateException::class);
        $this->assertThrows(fn () => $this->service->addManualLine($slip, 'ADJ_DEDUCTION', 'Late', '10.00', null, $this->owner), PayrollStateException::class);
        $this->assertThrows(fn () => Payslip::findOrFail($slip->id)->update(['net_pay' => '1.00']), PayrollStateException::class);
        $this->assertThrows(fn () => $slip->lines()->first()->delete(), PayrollStateException::class);
        $this->assertThrows(fn () => $this->service->sendBackToDraft($run, $this->owner), PayrollStateException::class);
        $this->assertThrows(fn () => PayrollRun::findOrFail($run->id)->delete(), PayrollStateException::class);

        $run = $this->service->release($run, $this->owner);
        $this->assertSame(PayrollRun::STATUS_RELEASED, $run->status);
        $this->assertSame($this->owner->id, $run->released_by);
        $this->assertThrows(fn () => $this->service->finalize($run, $this->owner), PayrollStateException::class);
    }

    public function test_thirteenth_month_includes_commission_and_warns_on_late_pay_date(): void
    {
        $a = $this->staffA();
        $this->finalize($this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner)->run);
        $this->finalize($this->service->generateRegular($this->spa, 2026, 9, 2, $this->owner)->run);

        $result = $this->service->generateThirteenthMonth($this->spa, 2026, '2026-12-20', $this->owner);

        $this->assertSame(PayrollRun::TYPE_THIRTEENTH_MONTH, $result->run->run_type);
        $this->assertNull($result->run->cutoff_no);
        $slip = $this->payslipFor($result->run, $a);
        // (BASIC 7,000 + COMMISSION 400) ÷ 12 = 616.67; without commission it would be 583.33.
        $this->assertLine($slip, 'THIRTEENTH_MONTH', '616.67');
        $this->assertSame('616.67', $slip->net_pay);
        $this->assertSame('0.00', $slip->taxable_compensation);
        $this->assertSame(0, $slip->lines()->where('kind', '!=', 'earning')->count());   // no contributions / WTAX
        $this->assertTrue($result->hasWarning(RunWarning::UNFINALIZED_RUNS));             // Oct–Dec not finalized
        $this->assertFalse($result->hasWarning(RunWarning::PAY_DATE_LATE));

        $late = $this->service->generateThirteenthMonth($this->spa, 2026, '2026-12-28', $this->owner);
        $this->assertTrue($late->regenerated);
        $this->assertTrue($late->hasWarning(RunWarning::PAY_DATE_LATE));

        // Taxable adjustments are refused on the 13th-month run.
        $this->assertThrows(fn () => $this->service->addManualLine($slip, 'ADJ_EARNING', 'x', '1.00', null, $this->owner), ValidationException::class);
    }

    public function test_only_staff_of_suite_branches_with_a_profile_are_paid(): void
    {
        $a = $this->staffA();
        $e = $this->makeStaff('Eli', 'Elsewhere', $this->plainBranch, 'daily', '700.00');
        $this->attend($e, ['2026-09-01', '2026-09-02']);
        $f = $this->makeStaff('Fay', 'Noprofile', $this->suiteBranch, null, null);

        $result = $this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner);

        $this->assertSame(1, $result->totals['payslips']);
        $this->assertNotNull($this->payslipFor($result->run, $a));
        $this->assertFalse(Payslip::where('payroll_run_id', $result->run->id)->where('staff_id', $e->id)->exists());
        $this->assertFalse(Payslip::where('payroll_run_id', $result->run->id)->where('staff_id', $f->id)->exists());
        $this->assertSame([$e->id], array_map(fn ($w) => $w->staffId, $result->warningsWithCode(RunWarning::NOT_IN_SUITE_BRANCH)));
        $this->assertSame([$f->id], array_map(fn ($w) => $w->staffId, $result->warningsWithCode(RunWarning::MISSING_PROFILE)));

        // 13th month follows the same branch rule.
        $this->finalize($result->run);
        $thirteenth = $this->service->generateThirteenthMonth($this->spa, 2026, '2026-12-20', $this->owner);
        $this->assertSame(1, $thirteenth->totals['payslips']);
    }

    public function test_period_builder_enforces_the_sixteen_day_interval(): void
    {
        $this->spa->update(['payroll_first_cutoff_day' => 14]);

        $sept = $this->service->buildPeriod($this->spa->refresh(), 2026, 9, 2);   // 15..30 = 16 days
        $this->assertSame('2026-09-15', $sept->start);
        $this->assertThrows(fn () => $this->service->buildPeriod($this->spa, 2026, 10, 2), PayrollSetupException::class);   // 15..31 = 17 days

        $this->spa->update(['payroll_first_cutoff_day' => 17]);
        $this->assertThrows(fn () => $this->service->buildPeriod($this->spa->refresh(), 2026, 9, 1), PayrollSetupException::class);
    }

    public function test_monthly_summary_groups_contributions_by_period_and_wtax_by_pay_date(): void
    {
        $this->staffA();
        $this->staffB();
        $this->finalize($this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner)->run);   // pays Sept 20
        $c2 = $this->service->generateRegular($this->spa, 2026, 9, 2, $this->owner)->run;             // pays Oct 5

        $draft = $this->service->monthlyContributionSummary($this->spa, 2026, 9);
        $this->assertFalse($draft['complete']);
        $this->assertSame('0.00', $draft['totals']['SSS_EE']);        // cutoff 2 still draft
        $this->assertSame('3604.10', $draft['totals']['WTAX']);       // cutoff 1 already finalized

        $this->finalize($c2);
        $sept = $this->service->monthlyContributionSummary($this->spa, 2026, 9);
        $this->assertTrue($sept['complete']);
        $this->assertCount(2, $sept['rows']);
        $this->assertSame('2125.00', $sept['totals']['SSS_EE']);            // 375 + 1,750
        $this->assertSame('2125.00', $sept['totals']['SSS_EE_withheld']);
        $this->assertSame('4250.00', $sept['totals']['SSS_ER']);
        $this->assertSame('40.00', $sept['totals']['SSS_EC']);
        $this->assertSame('1956.46', $sept['totals']['PHIC_EE']);           // 456.46 + 1,500
        $this->assertSame('340.00', $sept['totals']['HDMF_ER']);            // 140 + 200
        $this->assertSame('3604.10', $sept['totals']['WTAX']);              // only the Sept 20 pay date

        // BIR 1601-C groups by the month tax was withheld: cutoff 2's WTAX lands in October.
        $oct = $this->service->monthlyContributionSummary($this->spa, 2026, 10);
        $this->assertSame('2914.10', $oct['totals']['WTAX']);
        $this->assertSame('0.00', $oct['totals']['SSS_EE']);
        $this->assertCount(1, $oct['rows']);

        $this->assertSame([], $this->service->monthlyContributionSummary($this->spa, 2026, 9, $this->plainBranch)['rows']);
    }

    public function test_short_contributions_are_still_reported_as_due(): void
    {
        // One day's pay (₱700) cannot cover the month's employee shares.
        $g = $this->makeStaff('Gil', 'Oneday', $this->suiteBranch, 'daily', '700.00');
        $this->attend($g, ['2026-09-30']);
        $this->finalize($this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner)->run);
        $result = $this->service->generateRegular($this->spa, 2026, 9, 2, $this->owner);

        $slip = $this->payslipFor($result->run, $g);
        $this->assertSame('250.00', $this->lineFor($slip, 'SSS_EE')->amount);
        $this->assertSame('450.00', $this->lineFor($slip, 'PHIC_EE')->amount);     // 456.46 due
        $this->assertSame('0.00', $this->lineFor($slip, 'HDMF_EE')->amount);       // 7.00 due (fund salary ≤ ₱1,500 → 1%)
        $this->assertSame('0.00', $slip->net_pay);
        $this->assertTrue($result->hasWarning(RunWarning::UNAPPLIED_DEDUCTION));

        $this->finalize($result->run);
        $sum = $this->service->monthlyContributionSummary($this->spa, 2026, 9)['totals'];
        $this->assertSame('456.46', $sum['PHIC_EE']);            // remit this
        $this->assertSame('450.00', $sum['PHIC_EE_withheld']);   // employer advanced 6.46
        $this->assertSame('7.00', $sum['HDMF_EE']);
        $this->assertSame('0.00', $sum['HDMF_EE_withheld']);
        $this->assertSame('14.00', $sum['HDMF_ER']);
    }

    public function test_month_without_earnings_has_no_contributions(): void
    {
        $z = $this->makeStaff('Zed', 'Noshow', $this->suiteBranch, 'daily', '700.00');   // profile, no attendance

        $this->finalize($this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner)->run);
        $result = $this->service->generateRegular($this->spa, 2026, 9, 2, $this->owner);

        $slip = $this->payslipFor($result->run, $z);
        $this->assertSame('0.00', $slip->gross_pay);
        $this->assertSame(0, $slip->lines()->where('kind', '!=', 'earning')->count());
        $this->assertTrue($result->hasWarning(RunWarning::CONTRIBUTIONS_SKIPPED));

        $this->finalize($result->run);
        $rows = $this->service->monthlyContributionSummary($this->spa, 2026, 9)['rows'];
        $this->assertCount(1, $rows);                    // still listed (report as "no earnings")
        $this->assertSame('0.00', $rows[0]['SSS_EE']);
    }

    public function test_thirteenth_month_balance_after_late_cutoffs_are_finalized(): void
    {
        $a = $this->staffA();
        $this->finalize($this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner)->run);
        $this->finalize($this->service->generateRegular($this->spa, 2026, 9, 2, $this->owner)->run);
        $this->finalize($this->service->generateThirteenthMonth($this->spa, 2026, '2026-12-20', $this->owner)->run);   // 616.67

        // A cutoff finalized after the 13th-month run: October 1, one more day (₱700).
        $this->attend($a, ['2026-10-01']);
        $this->finalize($this->service->generateRegular($this->spa, 2026, 10, 1, $this->owner)->run);

        $balance = $this->service->thirteenthMonthBalance($this->spa, 2026);
        $this->assertCount(1, $balance['rows']);
        $this->assertSame('8100.00', $balance['rows'][0]['basis']);   // 7,400 + 700
        $this->assertSame('675.00', $balance['rows'][0]['owed']);
        $this->assertSame('616.67', $balance['rows'][0]['paid']);
        $this->assertSame('58.33', $balance['rows'][0]['balance']);
        $this->assertContains('Oct cutoff 2', $balance['pending_cutoffs']);
    }

    public function test_basic_plan_cannot_generate_but_can_finish_existing_runs(): void
    {
        $this->staffA();
        $run = $this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner)->run;

        DB::table('spas')->where('id', $this->spa->id)->update(['business_tier' => 'basic']);   // subscription lapsed
        $this->spa->refresh();

        $this->assertThrows(fn () => $this->service->generateRegular($this->spa, 2026, 9, 1, $this->owner), PayrollSetupException::class);
        $this->assertThrows(fn () => $this->service->generateThirteenthMonth($this->spa, 2026, '2026-12-20', $this->owner), PayrollSetupException::class);

        // Wages already computed can still be paid out.
        $released = $this->service->release($this->finalize($run), $this->owner);
        $this->assertSame(PayrollRun::STATUS_RELEASED, $released->status);
    }

    // ── Scenario staff ───────────────────────────────────────────────────────

    /** Daily ₱700, commission on; 5 days + one booking in each September cutoff. */
    private function staffA(): Staff
    {
        $a = $this->makeStaff('Ana', 'Alpha', $this->suiteBranch, 'daily', '700.00', commission: true);
        $this->attend($a, ['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04', '2026-09-05',
            '2026-09-16', '2026-09-17', '2026-09-18', '2026-09-19', '2026-09-21']);
        $this->booking($a, '2026-09-03', '1000.00');
        $this->booking($a, '2026-09-22', '3000.00');

        return $a;
    }

    /** Monthly ₱60,000, no attendance rows needed for semi-monthly BASIC. */
    private function staffB(): Staff
    {
        return $this->makeStaff('Ben', 'Bravo', $this->suiteBranch, 'monthly', '60000.00');
    }

    /** Daily ₱600 (= branch minimum → MWE) with a ₱150,000 booking at 10%. */
    private function staffC(): Staff
    {
        $c = $this->makeStaff('Cara', 'Charlie', $this->suiteBranch, 'daily', '600.00', commission: true);
        $this->attend($c, ['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04', '2026-09-05']);
        $this->booking($c, '2026-09-02', '150000.00');

        return $c;
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    private function makeUser(string $first, string $last): int
    {
        return DB::table('users')->insertGetId([
            'first_name' => $first, 'last_name' => $last,
            'email' => strtolower($first.'.'.$last).'@example.test', 'password' => bcrypt('secret'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeBranch(string $name, bool $suite): int
    {
        return DB::table('branches')->insertGetId([
            'spa_id' => $this->spa->id, 'name' => $name, 'location' => 'Imus, Cavite',
            'has_workforce_finance_suite' => $suite, 'min_daily_wage' => '600.00',
            'wage_order_ref' => 'RB IVA-22', 'min_wage_effective_from' => '2025-10-05',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeStaff(string $first, string $last, int $branchId, ?string $basis, ?string $rate, bool $commission = false): Staff
    {
        $staff = Staff::create([
            'user_id' => $this->makeUser($first, $last), 'spa_id' => $this->spa->id, 'branch_id' => $branchId,
            'employment_status' => 'active', 'hire_date' => '2025-01-06',
            'tin' => '000-000-000', 'sss_no' => '00-0000000-0', 'philhealth_no' => '00-000000000-0', 'pagibig_no' => '0000-0000-0000',
        ]);

        if ($basis !== null) {
            StaffPayProfile::create([
                'staff_id' => $staff->id, 'effective_from' => '2026-01-01', 'effective_to' => null,
                'pay_basis' => $basis, 'base_rate' => $rate, 'commission_enabled' => $commission,
                'rest_days' => ['Sunday'], 'created_by' => $this->owner->id,
            ]);
        }

        return $staff;
    }

    /** @param list<string> $dates 09:00–18:00 = 8 h after the 60-min meal → no OT, no ND. */
    private function attend(Staff $staff, array $dates): void
    {
        foreach ($dates as $date) {
            DB::table('staff_attendance')->insert([
                'staff_id' => $staff->id, 'spa_id' => $this->spa->id, 'branch_id' => $staff->branch_id,
                'date' => $date, 'status' => 'present', 'time_in' => '09:00:00', 'time_out' => '18:00:00',
                'source' => 'manual', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function booking(Staff $staff, string $date, string $total): void
    {
        DB::table('bookings')->insert([
            'spa_id' => $this->spa->id, 'branch_id' => $staff->branch_id, 'service_type' => 'in_branch',
            'treatment' => 'treatment_999', 'appointment_date' => $date, 'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => $total, 'amount_paid' => $total,
            'therapist_id' => $staff->user_id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function recurringDeduction(Staff $staff, string $code, string $label, string $amount): int
    {
        return StaffRecurringItem::create([
            'staff_id' => $staff->id, 'kind' => 'deduction', 'component_code' => $code, 'label' => $label,
            'amount' => $amount, 'frequency' => 'per_cutoff', 'start_date' => '2026-01-01',
            'authorization_ref' => 'Signed form 2026-001', 'is_active' => true, 'created_by' => $this->owner->id,
        ])->id;
    }

    private function finalize(PayrollRun $run): PayrollRun
    {
        return $this->service->finalize($this->service->approve($run, $this->owner), $this->owner);
    }

    private function payslipFor(PayrollRun $run, Staff $staff): Payslip
    {
        return Payslip::where('payroll_run_id', $run->id)->where('staff_id', $staff->id)->firstOrFail();
    }

    private function lineFor(Payslip $slip, string $code): PayslipLine
    {
        return $slip->lines()->where('component_code', $code)->firstOrFail();
    }

    private function assertLine(Payslip $slip, string $code, string $amount): void
    {
        $this->assertSame($amount, $this->lineFor($slip, $code)->amount, "{$code} amount");
    }
}
