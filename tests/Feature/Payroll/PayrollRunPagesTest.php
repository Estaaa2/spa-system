<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Models\Staff;
use App\Models\User;
use App\Services\Payroll\PayrollRunService;
use App\Services\Payroll\PayrollRunWarnings;
use Database\Factories\BranchFactory;
use Database\Factories\PayrollUserFactory;
use Database\Factories\SpaFactory;
use Database\Factories\StaffFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Payroll\Concerns\PayrollFixtures;
use Tests\Feature\Payroll\Concerns\PayrollRunRoutes;
use Tests\Feature\Payroll\Concerns\PayrollSetupRoutes;
use Tests\TestCase;

/**
 * Unit 6 — runs index, run page, payslip view/print, adjustments, status steps and
 * My Payslips. Amounts are asserted against what PayrollRunService (Unit 4) stored,
 * never typed from memory; the pages must only format them.
 *
 * World (PayrollFixtures): branch A (suite, min wage 600) is home; daily ₱600 profile,
 * Sunday rest day, commission on; default 10% commission rule.
 */
class PayrollRunPagesTest extends TestCase
{
    use RefreshDatabase;
    use PayrollFixtures;
    use PayrollSetupRoutes;
    use PayrollRunRoutes;

    protected User $hr;
    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-10 09:00:00');   // September periods have ended

        $this->withoutVite();
        $this->registerPayrollSetupRoutes();
        $this->registerPayrollRunRoutes();
        $this->makeWorld();
        $this->branchA->forceFill(['has_workforce_finance_suite' => true])->save();
        $this->spa->forceFill(['payroll_first_cutoff_day' => 15, 'payroll_pay_day_offset' => 5])->save();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('view payroll', 'web');
        Permission::findOrCreate('edit payroll', 'web');
        Role::findOrCreate('hr', 'web')->syncPermissions(['view payroll', 'edit payroll']);
        Role::findOrCreate('payroll-viewer', 'web')->syncPermissions(['view payroll']);

        $this->hr = PayrollUserFactory::new()->create(['spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id]);
        $this->hr->assignRole('hr');
        $this->viewer = PayrollUserFactory::new()->create(['spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id]);
        $this->viewer->assignRole('payroll-viewer');
        foreach ([$this->hr, $this->viewer, $this->user] as $u) {
            $u->forceFill(['email_verified_at' => now()])->save();   // web.php business group requires `verified`
        }

        $this->profile();
        $this->percentRule();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /** Three worked days in cutoff 1 and one commission booking dated in August (paid late). */
    private function workSeptemberCutoffOne(): void
    {
        foreach (['2026-09-01', '2026-09-02', '2026-09-03'] as $d) {
            $this->attend($d);
        }
        $this->booking('2026-08-28', ['total_amount' => '1500.00']);
    }

    private function generateC1(): PayrollRun
    {
        $this->actingAs($this->hr)->post(route('payroll.runs.store'), [
            'run_type' => 'regular', 'year' => 2026, 'month' => 9, 'cutoff_no' => 1,
        ])->assertRedirect();

        return PayrollRun::query()->where('spa_id', $this->spa->id)->where('cutoff_no', 1)->firstOrFail();
    }

    private function slip(PayrollRun $run): Payslip
    {
        return Payslip::query()->where('payroll_run_id', $run->id)->where('staff_id', $this->staff->id)->firstOrFail();
    }

    private function toStatus(PayrollRun $run, string ...$steps): void
    {
        $map = ['approve' => 'payroll.runs.approve', 'finalize' => 'payroll.runs.finalize', 'release' => 'payroll.runs.release'];
        foreach ($steps as $s) {
            $this->actingAs($this->hr)->post(route($map[$s], $run))->assertRedirect(route('payroll.runs.show', $run));
        }
    }

    // =====================================================================
    // Index
    // =====================================================================

    public function test_index_lists_runs_and_hides_new_run_for_view_only(): void
    {
        $run = $this->generateC1();

        $this->actingAs($this->viewer)->get(route('payroll.index', ['year' => 2026]))
            ->assertOk()
            ->assertSee('Pay Runs')
            ->assertSee(route('payroll.runs.show', $run))
            ->assertDontSee('New Pay Run');

        $this->actingAs($this->hr)->get(route('payroll.index'))->assertOk()->assertSee('New Pay Run');
    }

    public function test_index_filters_by_status(): void
    {
        $run = $this->generateC1();

        $this->actingAs($this->hr)->get(route('payroll.index', ['year' => 2026, 'status' => 'released']))
            ->assertOk()
            ->assertDontSee(route('payroll.runs.show', $run))
            ->assertSee('No runs match these filters.');
    }

    public function test_setup_gap_banner_links_to_unit5_pages(): void
    {
        $this->branchB->forceFill(['has_workforce_finance_suite' => true, 'min_daily_wage' => null])->save();
        $other = PayrollUserFactory::new()->create(['spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id]);
        StaffFactory::new()->create(['user_id' => $other->id, 'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id]);

        $this->actingAs($this->hr)->get(route('payroll.index'))
            ->assertOk()
            ->assertSee('Branch B')
            ->assertSee(route('payroll.setup.index', ['tab' => 'wages']), false)
            ->assertSee(route('payroll.staff.index', ['filter' => 'no_profile']), false)
            ->assertSee('1 active staff member(s) have no pay profile');
    }

    // =====================================================================
    // New-run preview and generation
    // =====================================================================

    public function test_preview_uses_the_engine_period_and_blocks_cutoff_two_until_cutoff_one_is_final(): void
    {
        $this->actingAs($this->hr)->getJson(route('payroll.runs.preview', ['run_type' => 'regular', 'year' => 2026, 'month' => 9, 'cutoff_no' => 1]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('period.start', 'Sep 1, 2026')
            ->assertJsonPath('period.end', 'Sep 15, 2026')
            ->assertJsonPath('period.pay', 'Sep 20, 2026');

        $this->actingAs($this->hr)->getJson(route('payroll.runs.preview', ['run_type' => 'regular', 'year' => 2026, 'month' => 9, 'cutoff_no' => 2]))
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('blocking', 'Cutoff 1 of September 2026 must be finalized first (it has not been generated).');
    }

    public function test_preview_thirteenth_warns_after_deadline(): void
    {
        $res = $this->actingAs($this->hr)->getJson(route('payroll.runs.preview', ['run_type' => 'thirteenth_month', 'year' => 2026, 'pay_date' => '2026-12-28']))
            ->assertOk()->assertJsonPath('ok', true);

        $this->assertStringContainsString('on or before December 24', implode(' ', $res->json('notes')));
    }

    public function test_generate_creates_draft_caches_warnings_and_shows_register(): void
    {
        $this->workSeptemberCutoffOne();
        $run = $this->generateC1();
        $slip = $this->slip($run);

        $this->assertSame(PayrollRun::STATUS_DRAFT, $run->status);
        $this->assertNotNull(app(PayrollRunWarnings::class)->get($run->fresh()));   // stored on the run (payroll_runs.review)

        $this->actingAs($this->viewer)->get(route('payroll.runs.show', $run))
            ->assertOk()
            ->assertSee('Payroll Register')
            ->assertSee('₱'.number_format((float) $slip->gross_pay, 2))
            ->assertSee('₱'.number_format((float) $slip->net_pay, 2))
            // Commission line shows its booking and that the appointment predates the period.
            ->assertSee('appointment Aug 28, 2026')
            ->assertSee('Earlier appointment')
            ->assertSee('10% of ₱1,500.00')
            ->assertSee($this->user->name)
            ->assertSee('Rates version');
    }

    public function test_generate_validation_and_engine_errors_return_to_the_modal(): void
    {
        $this->actingAs($this->hr)->from(route('payroll.index'))
            ->post(route('payroll.runs.store'), ['run_type' => 'regular', 'year' => 2026, 'month' => 9, 'cutoff_no' => 2])
            ->assertRedirect(route('payroll.index'))
            ->assertSessionHasErrors('newRun', null, 'newRun')
            ->assertSessionHas('_old_input._modal', 'newRunModal');

        $this->assertSame(0, PayrollRun::count());
    }

    public function test_view_only_user_cannot_generate(): void
    {
        $this->actingAs($this->viewer)->post(route('payroll.runs.store'), [
            'run_type' => 'regular', 'year' => 2026, 'month' => 9, 'cutoff_no' => 1,
        ])->assertForbidden();
    }

    // =====================================================================
    // Adjustments
    // =====================================================================

    public function test_add_and_remove_manual_adjustment_recomputes_payslip(): void
    {
        $this->workSeptemberCutoffOne();
        $run = $this->generateC1();
        $before = $this->slip($run);

        $this->actingAs($this->hr)->post(route('payroll.payslips.lines.store', $before), [
            'component_code' => 'ADJ_EARNING_NONTAX', 'label' => 'Reimbursement', 'amount' => '250.00', 'note' => 'Taxi receipt',
        ])->assertRedirect(route('payroll.runs.show', $run).'#payslip-'.$before->id)->assertSessionHas('expand', $before->id);

        $after = $before->fresh();
        $this->assertSame(bcadd($before->gross_pay, '250.00', 2), $after->gross_pay);
        $line = PayslipLine::query()->where('payslip_id', $before->id)->where('is_manual', true)->firstOrFail();
        $this->assertSame($this->hr->id, (int) $line->created_by);

        $this->actingAs($this->hr)->get(route('payroll.runs.show', $run))->assertOk()->assertSee('Reimbursement')->assertSee('Added by');

        $this->actingAs($this->hr)->delete(route('payroll.lines.destroy', $line))->assertRedirect();
        $this->assertSame($before->gross_pay, $before->fresh()->gross_pay);
    }

    public function test_adjustment_validation_errors_use_the_adjustment_bag(): void
    {
        $this->workSeptemberCutoffOne();
        $run = $this->generateC1();
        $slip = $this->slip($run);

        $this->actingAs($this->hr)->from(route('payroll.runs.show', $run))
            ->post(route('payroll.payslips.lines.store', $slip), ['component_code' => 'ADJ_DEDUCTION', 'label' => 'Oops', 'amount' => '-5'])
            ->assertRedirect(route('payroll.runs.show', $run))
            ->assertSessionHasErrors('amount', null, 'adjustment')
            ->assertSessionHas('_old_input._record', (string) $slip->id);
    }

    public function test_adjustments_are_refused_once_approved(): void
    {
        $this->workSeptemberCutoffOne();
        $run = $this->generateC1();
        $slip = $this->slip($run);
        $this->toStatus($run, 'approve');

        $this->actingAs($this->hr)->from(route('payroll.runs.show', $run))
            ->post(route('payroll.payslips.lines.store', $slip), ['component_code' => 'ADJ_EARNING', 'label' => 'Late', 'amount' => '100'])
            ->assertSessionHas('error');
        $this->assertSame(0, PayslipLine::query()->where('payslip_id', $slip->id)->where('is_manual', true)->count());
    }

    // =====================================================================
    // Steps
    // =====================================================================

    public function test_steps_record_each_actor_and_show_self_approval(): void
    {
        $this->workSeptemberCutoffOne();
        $run = $this->generateC1();
        $this->toStatus($run, 'approve', 'finalize', 'release');

        $run->refresh();
        $this->assertSame(PayrollRun::STATUS_RELEASED, $run->status);
        $this->assertSame($this->hr->id, (int) $run->approved_by);
        $this->assertSame($this->hr->id, (int) $run->finalized_by);

        $this->actingAs($this->viewer)->get(route('payroll.runs.show', $run))
            ->assertOk()
            ->assertSee('Approved by the person who generated it')
            ->assertSee($this->hr->name)
            ->assertDontSee('Add Adjustment');
    }

    public function test_send_back_clears_approval(): void
    {
        $run = $this->generateC1();
        $this->toStatus($run, 'approve');
        $this->actingAs($this->hr)->post(route('payroll.runs.send-back', $run))->assertRedirect();

        $run->refresh();
        $this->assertSame(PayrollRun::STATUS_DRAFT, $run->status);
        $this->assertNull($run->approved_by);
    }

    public function test_invalid_step_shows_error_and_changes_nothing(): void
    {
        $run = $this->generateC1();

        $this->actingAs($this->hr)->from(route('payroll.runs.show', $run))
            ->post(route('payroll.runs.release', $run))
            ->assertSessionHas('error');
        $this->assertSame(PayrollRun::STATUS_DRAFT, $run->fresh()->status);
    }

    public function test_delete_only_drafts(): void
    {
        $this->workSeptemberCutoffOne();
        $run = $this->generateC1();
        $slipId = $this->slip($run)->id;

        $this->actingAs($this->hr)->delete(route('payroll.runs.destroy', $run))->assertRedirect(route('payroll.index', ['year' => 2026]));
        $this->assertNull(PayrollRun::find($run->id));
        $this->assertNull(Payslip::find($slipId));

        $again = $this->generateC1();
        $this->toStatus($again, 'approve');
        $this->actingAs($this->hr)->delete(route('payroll.runs.destroy', $again))->assertSessionHas('error');
        $this->assertNotNull(PayrollRun::find($again->id));
    }

    public function test_regenerate_keeps_manual_lines(): void
    {
        $this->workSeptemberCutoffOne();
        $run = $this->generateC1();
        $slip = $this->slip($run);
        app(PayrollRunService::class)->addManualLine($slip, 'ADJ_EARNING', 'SIL pay', '600.00', null, $this->hr);

        $this->actingAs($this->hr)->post(route('payroll.runs.regenerate', $run))->assertRedirect(route('payroll.runs.show', $run));
        $this->assertSame(1, PayslipLine::query()->where('payslip_id', $slip->id)->where('is_manual', true)->count());
    }

    public function test_other_spa_run_is_not_found(): void
    {
        $run = $this->generateC1();

        $owner = PayrollUserFactory::new()->create();
        $otherSpa = SpaFactory::new()->create(['owner_id' => $owner->id]);
        $otherBranch = BranchFactory::new()->create(['spa_id' => $otherSpa->id]);
        $outsider = PayrollUserFactory::new()->create(['spa_id' => $otherSpa->id, 'branch_id' => $otherBranch->id]);
        $outsider->assignRole('hr');

        $this->actingAs($outsider)->get(route('payroll.runs.show', $run))->assertNotFound();
        $this->actingAs($outsider)->post(route('payroll.runs.approve', $run))->assertNotFound();
    }

    // =====================================================================
    // Contribution summary and payslip copy
    // =====================================================================

    public function test_cutoff_two_page_shows_contribution_summary_and_payslip_hides_employer_shares(): void
    {
        $this->workSeptemberCutoffOne();
        $c1 = $this->generateC1();
        $this->toStatus($c1, 'approve', 'finalize');

        foreach (['2026-09-16', '2026-09-17'] as $d) {
            $this->attend($d);
        }
        $this->actingAs($this->hr)->post(route('payroll.runs.store'), ['run_type' => 'regular', 'year' => 2026, 'month' => 9, 'cutoff_no' => 2])->assertRedirect();
        $c2 = PayrollRun::query()->where('cutoff_no', 2)->firstOrFail();
        $slip = $this->slip($c2);
        $this->assertTrue($slip->lines()->where('component_code', 'SSS_ER')->exists());

        $this->actingAs($this->hr)->get(route('payroll.runs.show', $c2))
            ->assertOk()
            ->assertSee('Monthly Contribution Summary — September 2026')
            ->assertSee('Incomplete — this run is not finalized yet')
            ->assertSee('Official agency file formats are not generated');

        $this->actingAs($this->hr)->get(route('payroll.payslips.show', $slip))
            ->assertOk()
            ->assertSee('SSS Contribution')
            ->assertDontSee('SSS (Employer)')
            ->assertSee('not final')
            ->assertSee('window.print()', false);
    }

    // =====================================================================
    // Saved warnings, batch print, service names
    // =====================================================================

    public function test_warnings_are_saved_on_the_run_and_approval_needs_no_checkbox(): void
    {
        $this->workSeptemberCutoffOne();   // staff has no statutory IDs → at least one warning
        $run = $this->generateC1();
        $this->assertGreaterThan(0, app(PayrollRunWarnings::class)->count($run->fresh()));

        // A different user opening the run sees the same saved warnings.
        $this->actingAs($this->viewer)->get(route('payroll.runs.show', $run))->assertOk()->assertSee('warning(s)');

        $this->actingAs($this->hr)->post(route('payroll.runs.approve', $run))
            ->assertRedirect(route('payroll.runs.show', $run));

        $run->refresh();
        $this->assertSame(PayrollRun::STATUS_APPROVED, $run->status);
        $this->assertSame($this->hr->id, (int) $run->approved_by);
        $this->assertGreaterThan(0, app(PayrollRunWarnings::class)->count($run));   // kept with the approved run
    }

    public function test_adjustment_updates_the_saved_warnings(): void
    {
        $this->workSeptemberCutoffOne();
        $run = $this->generateC1();
        $this->assertNull($run->fresh()->review['adjusted_at']);

        $this->actingAs($this->hr)->post(route('payroll.payslips.lines.store', $this->slip($run)), [
            'component_code' => 'ADJ_EARNING_NONTAX', 'label' => 'Reimbursement', 'amount' => '100',
        ]);

        $this->assertNotNull($run->fresh()->review['adjusted_at']);
    }

    public function test_saved_warnings_are_frozen_after_approval(): void
    {
        $run = $this->generateC1();
        $this->toStatus($run, 'approve');

        $this->expectException(\App\Exceptions\PayrollStateException::class);
        app(PayrollRunWarnings::class)->recordSettlement($run->id, $this->staff->id, [], false);
    }

    public function test_batch_print_lists_every_payslip_with_optional_receipt_line(): void
    {
        $this->workSeptemberCutoffOne();
        $run = $this->generateC1();

        $this->actingAs($this->viewer)->get(route('payroll.runs.print', $run))
            ->assertOk()
            ->assertSee($this->user->name)
            ->assertSee('payslip-print-area', false)
            ->assertSee('not finalized')
            ->assertDontSee('Signature over printed name');

        $this->actingAs($this->viewer)->get(route('payroll.runs.print', ['run' => $run->id, 'receipt' => 1]))
            ->assertOk()
            ->assertSee('Signature over printed name');
    }

    public function test_commission_lines_name_the_service_for_the_therapist(): void
    {
        $this->workSeptemberCutoffOne();
        $run = $this->generateC1();
        $this->toStatus($run, 'approve', 'finalize', 'release');
        $slip = $this->slip($run);
        $service = \Illuminate\Support\Facades\DB::table('treatments')->orderByDesc('id')->value('name');

        $this->actingAs($this->user)->get(route('my-payslips.show', $slip))
            ->assertOk()
            ->assertSee('Aug 28 · '.$service.' · 10% of ₱1,500.00', false);
    }

    // =====================================================================
    // My Payslips
    // =====================================================================

    public function test_my_payslips_shows_only_released_own_payslips_with_ytd(): void
    {
        $this->workSeptemberCutoffOne();
        $run = $this->generateC1();
        $slip = $this->slip($run);

        // Draft: invisible to the staff member.
        $this->actingAs($this->user)->get(route('my-payslips.index', ['year' => 2026]))->assertOk()->assertSee('No released payslips for 2026 yet.');
        $this->actingAs($this->user)->get(route('my-payslips.show', $slip))->assertNotFound();

        $this->toStatus($run, 'approve', 'finalize', 'release');

        $this->actingAs($this->user)->get(route('my-payslips.index', ['year' => 2026]))
            ->assertOk()
            ->assertSee(route('my-payslips.show', $slip))
            ->assertSee('₱'.number_format((float) $slip->net_pay, 2))
            ->assertSee('₱'.number_format((float) $slip->gross_pay, 2));   // YTD gross

        $this->actingAs($this->user)->get(route('my-payslips.show', $slip))
            ->assertOk()
            ->assertSee('Net Pay')
            ->assertDontSee('not final');
    }

    public function test_another_staff_members_payslip_is_not_found(): void
    {
        $this->workSeptemberCutoffOne();
        $run = $this->generateC1();
        $slip = $this->slip($run);
        $this->toStatus($run, 'approve', 'finalize', 'release');

        $colleague = PayrollUserFactory::new()->create(['spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id]);
        StaffFactory::new()->create(['user_id' => $colleague->id, 'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id]);

        $this->actingAs($colleague)->get(route('my-payslips.show', $slip))->assertNotFound();
        // HR holds `view payroll` but My Payslips is identity-based: still not theirs.
        $this->actingAs($this->hr)->get(route('my-payslips.show', $slip))->assertNotFound();
    }

    public function test_former_staff_still_see_released_payslips(): void
    {
        $this->workSeptemberCutoffOne();
        $run = $this->generateC1();
        $slip = $this->slip($run);
        $this->toStatus($run, 'approve', 'finalize', 'release');

        Staff::query()->whereKey($this->staff->id)->update(['employment_status' => 'inactive']);

        $this->actingAs($this->user)->get(route('my-payslips.show', $slip))->assertOk();
    }
}
