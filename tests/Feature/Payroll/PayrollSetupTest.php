<?php

namespace Tests\Feature\Payroll;

use App\Models\Branch;
use App\Models\CommissionRule;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Spa;
use App\Models\Staff;
use App\Models\StaffPayProfile;
use App\Models\StaffRecurringItem;
use App\Models\User;
use Database\Factories\BranchFactory;
use Database\Factories\PayrollUserFactory;
use Database\Factories\SpaFactory;
use Database\Factories\StaffFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Payroll\Concerns\PayrollFixtures;
use Tests\Feature\Payroll\Concerns\PayrollSetupRoutes;
use Tests\TestCase;

/**
 * Unit 5 — payroll setup pages and thin controllers (PayrollSetupController,
 * StaffPayProfileController) plus PayrollSetupService's "used by a finalized run" rules.
 */
class PayrollSetupTest extends TestCase
{
    use RefreshDatabase;
    use PayrollFixtures;
    use PayrollSetupRoutes;

    private User $hr;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-29 09:00:00');

        $this->withoutVite();
        $this->registerPayrollSetupRoutes();
        $this->makeWorld();
        $this->branchA->forceFill(['has_workforce_finance_suite' => true])->save();

        // The app layout's navigation checks every permission by name.
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
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /** A finalized regular run with a payslip for $this->staff whose snapshot lists the given ids. */
    private function finalizedRunUsing(array $profileIds = [], array $ruleIds = [], array $recurringIds = [], string $start = '2026-09-01', string $end = '2026-09-15'): PayrollRun
    {
        $run = $this->payrollRun($start, $end, 1);
        Payslip::create([
            'payroll_run_id' => $run->id, 'staff_id' => $this->staff->id, 'home_branch_id' => $this->branchA->id,
            'gross_pay' => '0.00', 'total_deductions' => '0.00', 'net_pay' => '0.00', 'taxable_compensation' => '0.00',
            'days_worked' => '0.00', 'is_mwe' => false,
            'snapshot' => [
                'pay_profiles' => array_map(fn ($id) => ['id' => $id], $profileIds),
                'rule_ids'     => ['commission_rules' => $ruleIds, 'recurring_items' => $recurringIds],
            ],
        ]);
        $run->update(['status' => PayrollRun::STATUS_APPROVED, 'approved_by' => $this->admin->id, 'approved_at' => now()]);
        $run->update(['status' => PayrollRun::STATUS_FINALIZED, 'finalized_by' => $this->admin->id, 'finalized_at' => now()]);

        return $run;
    }

    private function rule(array $o = []): CommissionRule
    {
        return CommissionRule::create(array_merge([
            'spa_id' => $this->spa->id, 'branch_id' => null, 'target_type' => 'default', 'target_id' => null,
            'method' => 'percent', 'value' => '10.0000', 'effective_from' => '2026-01-01', 'effective_to' => null,
            'created_by' => $this->admin->id,
        ], $o));
    }

    // =====================================================================
    // Access
    // =====================================================================

    public function test_pages_render_for_view_only_user_and_writes_are_forbidden(): void
    {
        $this->actingAs($this->viewer)->get(route('payroll.setup.index'))->assertOk()->assertSee('Payroll Setup');
        $this->actingAs($this->viewer)->get(route('payroll.setup.index', ['tab' => 'commission']))->assertOk();
        $this->actingAs($this->viewer)->get(route('payroll.staff.index'))->assertOk()->assertSee('Staff Pay Setup');
        $this->actingAs($this->viewer)->get(route('payroll.staff.show', $this->staff))->assertOk();

        $this->actingAs($this->viewer)->put(route('payroll.setup.schedule'), ['payroll_first_cutoff_day' => 15, 'payroll_pay_day_offset' => 5])->assertForbidden();
        $this->actingAs($this->viewer)->post(route('payroll.staff.profiles.store', $this->staff), [])->assertForbidden();
    }

    public function test_other_spa_records_return_404(): void
    {
        $otherOwner = PayrollUserFactory::new()->create();
        $otherSpa = SpaFactory::new()->create(['owner_id' => $otherOwner->id]);
        $otherBranch = BranchFactory::new()->create(['spa_id' => $otherSpa->id]);
        $otherUser = PayrollUserFactory::new()->create(['spa_id' => $otherSpa->id, 'branch_id' => $otherBranch->id]);
        $otherStaff = StaffFactory::new()->create(['user_id' => $otherUser->id, 'spa_id' => $otherSpa->id, 'branch_id' => $otherBranch->id]);

        $this->actingAs($this->hr)->get(route('payroll.staff.show', $otherStaff))->assertNotFound();
        $this->actingAs($this->hr)->put(route('payroll.setup.wages.update', $otherBranch), [
            'min_daily_wage' => '600', 'wage_order_ref' => 'X', 'min_wage_effective_from' => '2026-01-01',
        ])->assertNotFound();

        $otherRule = CommissionRule::create(['spa_id' => $otherSpa->id, 'target_type' => 'default', 'method' => 'percent',
            'value' => '5', 'effective_from' => '2026-01-01', 'created_by' => $otherOwner->id]);
        $this->actingAs($this->hr)->delete(route('payroll.setup.rules.destroy', $otherRule))->assertNotFound();
        $this->assertDatabaseHas('commission_rules', ['id' => $otherRule->id]);
    }

    // =====================================================================
    // Schedule
    // =====================================================================

    public function test_schedule_enforces_art_103_intervals(): void
    {
        $this->actingAs($this->hr)->from(route('payroll.setup.index'))
            ->put(route('payroll.setup.schedule'), ['payroll_first_cutoff_day' => 14, 'payroll_pay_day_offset' => 5])
            ->assertSessionHasErrors('payroll_first_cutoff_day', null, 'schedule');

        $this->actingAs($this->hr)->put(route('payroll.setup.schedule'), ['payroll_first_cutoff_day' => 17, 'payroll_pay_day_offset' => 5])
            ->assertSessionHasErrors('payroll_first_cutoff_day', null, 'schedule');

        $this->actingAs($this->hr)->put(route('payroll.setup.schedule'), ['payroll_first_cutoff_day' => 15, 'payroll_pay_day_offset' => 17])
            ->assertSessionHasErrors('payroll_pay_day_offset', null, 'schedule');

        $this->actingAs($this->hr)->put(route('payroll.setup.schedule'), ['payroll_first_cutoff_day' => 16, 'payroll_pay_day_offset' => 3])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(16, $this->spa->fresh()->payroll_first_cutoff_day);
        $this->assertSame(3, $this->spa->fresh()->payroll_pay_day_offset);
    }

    // =====================================================================
    // Branch minimum wage
    // =====================================================================

    public function test_branch_wage_is_saved_and_validated(): void
    {
        $this->actingAs($this->hr)->put(route('payroll.setup.wages.update', $this->branchB), [
            'min_daily_wage' => '0', 'wage_order_ref' => 'IVA-22', 'min_wage_effective_from' => '2025-10-05',
        ])->assertSessionHasErrors('min_daily_wage', null, 'wage');

        $this->actingAs($this->hr)->put(route('payroll.setup.wages.update', $this->branchB), [
            'min_daily_wage' => '600.00', 'wage_order_ref' => 'Wage Order No. IVA-22', 'min_wage_effective_from' => '2025-10-05',
        ])->assertSessionHasNoErrors();

        $b = $this->branchB->fresh();
        $this->assertSame('600.00', (string) $b->min_daily_wage);
        $this->assertSame('Wage Order No. IVA-22', $b->wage_order_ref);
    }

    public function test_wage_tab_flags_missing_rate(): void
    {
        $this->branchB->forceFill(['min_daily_wage' => null])->save();

        $this->actingAs($this->hr)->get(route('payroll.setup.index', ['tab' => 'wages']))
            ->assertOk()->assertSee('payroll will not run for this branch', false);
    }

    // =====================================================================
    // Commission rules
    // =====================================================================

    public function test_rule_create_and_preview_follow_the_locked_resolution_order(): void
    {
        $t = $this->treatment();

        $this->actingAs($this->hr)->post(route('payroll.setup.rules.store'), [
            'branch_id' => '', 'target_type' => 'default', 'method' => 'percent', 'value' => '5', 'effective_from' => '2026-01-01',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->hr)->post(route('payroll.setup.rules.store'), [
            'branch_id' => $this->branchA->id, 'target_type' => 'default', 'method' => 'percent', 'value' => '7', 'effective_from' => '2026-01-01',
        ])->assertSessionHasNoErrors();

        $preview = fn (string $target) => $this->actingAs($this->viewer)->getJson(route('payroll.setup.rules.preview', [
            'branch_id' => $this->branchA->id, 'target' => $target, 'date' => '2026-09-10',
        ]));

        $preview('treatment_'.$t->id)->assertOk()->assertJsonPath('tier', 'branch_default');

        $this->actingAs($this->hr)->post(route('payroll.setup.rules.store'), [
            'branch_id' => '', 'target_type' => 'treatment', 'target_id' => $t->id, 'method' => 'flat', 'value' => '150', 'effective_from' => '2026-01-01',
        ])->assertSessionHasNoErrors();
        $preview('treatment_'.$t->id)->assertJsonPath('tier', 'spa_target')->assertJsonPath('rule.method', 'flat');

        $this->actingAs($this->hr)->post(route('payroll.setup.rules.store'), [
            'branch_id' => $this->branchA->id, 'target_type' => 'treatment', 'target_id' => $t->id, 'method' => 'percent', 'value' => '12.5', 'effective_from' => '2026-01-01',
        ])->assertSessionHasNoErrors();
        $preview('treatment_'.$t->id)->assertJsonPath('tier', 'branch_target');

        // Branch B has no branch rules → falls to the spa rules.
        $this->actingAs($this->viewer)->getJson(route('payroll.setup.rules.preview', [
            'branch_id' => $this->branchB->id, 'target' => 'default', 'date' => '2026-09-10',
        ]))->assertJsonPath('tier', 'spa_default');
    }

    public function test_rule_validation_rejects_bad_input(): void
    {
        $this->actingAs($this->hr)->post(route('payroll.setup.rules.store'), [
            'target_type' => 'default', 'method' => 'percent', 'value' => '101', 'effective_from' => '2026-01-01',
        ])->assertSessionHasErrors('value', null, 'ruleCreate');

        $this->actingAs($this->hr)->post(route('payroll.setup.rules.store'), [
            'target_type' => 'treatment', 'target_id' => 999999, 'method' => 'percent', 'value' => '10', 'effective_from' => '2026-01-01',
        ])->assertSessionHasErrors('target_id', null, 'ruleCreate');

        // Overlap with an existing rule for the same branch + target (model guard).
        $this->rule();
        $this->actingAs($this->hr)->post(route('payroll.setup.rules.store'), [
            'target_type' => 'default', 'method' => 'percent', 'value' => '8', 'effective_from' => '2026-06-01',
        ])->assertSessionHasErrors('effective_from', null, 'ruleCreate');
    }

    public function test_change_from_date_ends_old_rule_and_starts_new(): void
    {
        $r = $this->rule();

        $this->actingAs($this->hr)->post(route('payroll.setup.rules.supersede', $r), [
            'method' => 'percent', 'value' => '12', 'effective_from' => '2026-10-01',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-09-30', $r->fresh()->effective_to->toDateString());
        $new = CommissionRule::where('effective_from', '2026-10-01')->firstOrFail();
        $this->assertSame('12.0000', (string) $new->value);
        $this->assertNull($new->effective_to);
    }

    public function test_rule_used_by_finalized_run_is_locked(): void
    {
        $r = $this->rule();
        $this->finalizedRunUsing(ruleIds: [$r->id]);   // period ends 2026-09-15

        $this->actingAs($this->hr)->put(route('payroll.setup.rules.update', $r), [
            'target_type' => 'default', 'method' => 'percent', 'value' => '20', 'effective_from' => '2026-01-01',
        ])->assertSessionHasErrors('rule', null, 'ruleEdit');
        $this->actingAs($this->hr)->delete(route('payroll.setup.rules.destroy', $r))->assertSessionHasErrors('rule', null, 'ruleDelete');
        $this->assertSame('10.0000', (string) $r->fresh()->value);

        // Cannot end or supersede inside the paid range …
        $this->actingAs($this->hr)->post(route('payroll.setup.rules.end', $r), ['effective_to' => '2026-09-10'])
            ->assertSessionHasErrors('effective_to', null, 'ruleEnd');
        $this->actingAs($this->hr)->post(route('payroll.setup.rules.supersede', $r), [
            'method' => 'percent', 'value' => '12', 'effective_from' => '2026-09-10',
        ])->assertSessionHasErrors('effective_to', null, 'ruleChange');
        $this->assertNull($r->fresh()->effective_to);
        $this->assertSame(1, CommissionRule::count());

        // … but can from the last paid day onward.
        $this->actingAs($this->hr)->post(route('payroll.setup.rules.end', $r), ['effective_to' => '2026-09-15'])->assertSessionHasNoErrors();
        $this->assertSame('2026-09-15', $r->fresh()->effective_to->toDateString());
    }

    public function test_rule_used_only_by_draft_run_can_be_edited(): void
    {
        $r = $this->rule();
        $run = $this->payrollRun('2026-09-01', '2026-09-15', 1);
        Payslip::create([
            'payroll_run_id' => $run->id, 'staff_id' => $this->staff->id, 'home_branch_id' => $this->branchA->id,
            'gross_pay' => '0', 'total_deductions' => '0', 'net_pay' => '0', 'taxable_compensation' => '0', 'days_worked' => '0', 'is_mwe' => false,
            'snapshot' => ['rule_ids' => ['commission_rules' => [$r->id], 'recurring_items' => []]],
        ]);

        $this->actingAs($this->hr)->put(route('payroll.setup.rules.update', $r), [
            'target_type' => 'default', 'method' => 'percent', 'value' => '11', 'effective_from' => '2026-01-01',
        ])->assertSessionHasNoErrors();
        $this->assertSame('11.0000', (string) $r->fresh()->value);
    }

    // =====================================================================
    // Pay profiles
    // =====================================================================

    public function test_new_profile_from_date_closes_the_previous_one(): void
    {
        $old = $this->profile();

        $this->actingAs($this->hr)->post(route('payroll.staff.profiles.store', $this->staff), [
            'effective_from' => '2026-10-01', 'pay_basis' => 'daily', 'base_rate' => '650.00',
            'commission_enabled' => '1', 'rest_days' => ['Sunday'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-09-30', $old->fresh()->effective_to->toDateString());
        $new = StaffPayProfile::where('effective_from', '2026-10-01')->firstOrFail();
        $this->assertSame('650.00', (string) $new->base_rate);
        $this->assertTrue($new->commission_enabled);
        $this->assertSame(['Sunday'], $new->rest_days);
    }

    public function test_profile_rest_day_rules(): void
    {
        $post = fn (array $o) => $this->actingAs($this->hr)->post(route('payroll.staff.profiles.store', $this->staff), array_merge([
            'effective_from' => '2026-10-01', 'pay_basis' => 'daily', 'base_rate' => '600', 'rest_days' => ['Sunday'],
        ], $o));

        $post(['rest_days' => ['Saturday', 'Sunday', 'Monday']])->assertSessionHasErrors('rest_days', null, 'profileCreate');
        $post(['rest_days' => []])->assertSessionHasErrors('rest_days', null, 'profileCreate');
        $post(['pay_basis' => 'monthly', 'base_rate' => '20000', 'rest_days' => []])->assertSessionHasErrors('rest_days', null, 'profileCreate');
        $post(['rest_days' => ['Saturday', 'Sunday']])->assertSessionHasNoErrors();
    }

    public function test_below_minimum_wage_warning_is_flashed(): void
    {
        $this->actingAs($this->hr)->post(route('payroll.staff.profiles.store', $this->staff), [
            'effective_from' => '2026-10-01', 'pay_basis' => 'daily', 'base_rate' => '500', 'rest_days' => ['Sunday'],
        ])->assertSessionHasNoErrors()->assertSessionHas('warning', fn ($w) => str_contains($w, 'below'));

        // Monthly: 15,000 × 12 ÷ 365 = 493.15 < 600.
        $this->actingAs($this->hr)->post(route('payroll.staff.profiles.store', $this->staff), [
            'effective_from' => '2026-11-01', 'pay_basis' => 'monthly', 'base_rate' => '15000', 'rest_days' => ['Sunday'],
        ])->assertSessionHas('warning', fn ($w) => str_contains($w, '₱493.15'));
    }

    public function test_profile_used_by_finalized_run_is_locked(): void
    {
        $p = $this->profile();
        $this->finalizedRunUsing(profileIds: [$p->id]);

        $this->actingAs($this->hr)->put(route('payroll.staff.profiles.update', [$this->staff, $p]), [
            'effective_from' => '2026-01-01', 'pay_basis' => 'daily', 'base_rate' => '700', 'rest_days' => ['Sunday'],
        ])->assertSessionHasErrors('profile', null, 'profileEdit');
        $this->actingAs($this->hr)->delete(route('payroll.staff.profiles.destroy', [$this->staff, $p]))->assertSessionHasErrors('profile', null, 'profileDelete');

        // New profile starting inside the paid range would cut it → refused.
        $this->actingAs($this->hr)->post(route('payroll.staff.profiles.store', $this->staff), [
            'effective_from' => '2026-09-10', 'pay_basis' => 'daily', 'base_rate' => '700', 'rest_days' => ['Sunday'],
        ])->assertSessionHasErrors('effective_from', null, 'profileCreate');
        $this->assertSame(1, StaffPayProfile::count());

        $this->actingAs($this->hr)->post(route('payroll.staff.profiles.store', $this->staff), [
            'effective_from' => '2026-09-16', 'pay_basis' => 'daily', 'base_rate' => '700', 'rest_days' => ['Sunday'],
        ])->assertSessionHasNoErrors();
        $this->assertSame('2026-09-15', $p->fresh()->effective_to->toDateString());
        $this->assertSame('600.00', (string) $p->fresh()->base_rate);
    }

    public function test_show_page_marks_locked_profile(): void
    {
        $p = $this->profile();
        $this->finalizedRunUsing(profileIds: [$p->id]);

        $this->actingAs($this->hr)->get(route('payroll.staff.show', $this->staff))
            ->assertOk()->assertSee('Locked — add a new profile to change');
    }

    // =====================================================================
    // Statutory IDs
    // =====================================================================

    public function test_statutory_ids_are_encrypted_masked_and_revealed_only_through_the_logged_endpoint(): void
    {
        Log::spy();

        $this->actingAs($this->hr)->put(route('payroll.staff.ids.update', $this->staff), [
            'tin' => '123-456-789-00000', 'sss_no' => '34-1234567-8', 'philhealth_no' => '', 'pagibig_no' => '1234-5678-9012',
        ])->assertSessionHasNoErrors();

        $raw = DB::table('staff')->where('id', $this->staff->id)->value('sss_no');
        $this->assertNotSame('34-1234567-8', $raw);                 // encrypted at rest
        $this->assertSame('34-1234567-8', $this->staff->fresh()->sss_no);
        $this->assertNull($this->staff->fresh()->philhealth_no);

        // Plaintext is never rendered — not even for editors.
        $this->actingAs($this->viewer)->get(route('payroll.staff.show', $this->staff))
            ->assertOk()->assertDontSee('34-1234567-8')->assertSee('•••• 5678', false);
        $this->actingAs($this->hr)->get(route('payroll.staff.show', $this->staff))
            ->assertOk()->assertDontSee('34-1234567-8');

        // Reveal: editors only, JSON, no-store, logged.
        $this->actingAs($this->viewer)->postJson(route('payroll.staff.ids.reveal', $this->staff))->assertForbidden();
        $this->actingAs($this->hr)->postJson(route('payroll.staff.ids.reveal', $this->staff))
            ->assertOk()->assertJsonPath('ids.sss_no', '34-1234567-8')->assertJsonPath('ids.philhealth_no', null)
            ->assertHeader('Cache-Control', 'no-store, private');
        Log::shouldHaveReceived('info')->withArgs(fn ($msg, $ctx) => $msg === 'payroll.statutory_ids.revealed' && $ctx['staff_id'] === $this->staff->id);

        // Blank keeps; clear[] removes; bad characters rejected.
        $this->actingAs($this->hr)->put(route('payroll.staff.ids.update', $this->staff), ['sss_no' => '', 'clear' => ['tin']])->assertSessionHasNoErrors();
        $this->assertSame('34-1234567-8', $this->staff->fresh()->sss_no);
        $this->assertNull($this->staff->fresh()->tin);
        $this->actingAs($this->hr)->put(route('payroll.staff.ids.update', $this->staff), ['tin' => 'ABC123'])
            ->assertSessionHasErrors('tin', null, 'ids');
    }

    public function test_statutory_id_format_and_duplicate_hints_do_not_block(): void
    {
        $u2 = PayrollUserFactory::new()->create(['spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id, 'first_name' => 'Twin', 'last_name' => 'Entry']);
        StaffFactory::new()->create(['user_id' => $u2->id, 'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id, 'sss_no' => '3412345678']);

        $this->actingAs($this->hr)->put(route('payroll.staff.ids.update', $this->staff), ['sss_no' => '34-1234567-8', 'pagibig_no' => '123'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'Pag-IBIG has 3 digits') && str_contains($w, 'Twin Entry'));
        $this->assertSame('123', $this->staff->fresh()->pagibig_no);
    }

    public function test_branch_rule_for_another_branchs_treatment_is_refused(): void
    {
        $tB = $this->treatment('1000.00', $this->branchB);

        $this->actingAs($this->hr)->post(route('payroll.setup.rules.store'), [
            'branch_id' => $this->branchA->id, 'target_type' => 'treatment', 'target_id' => $tB->id,
            'method' => 'percent', 'value' => '10', 'effective_from' => '2026-01-01',
        ])->assertSessionHasErrors('target_id', null, 'ruleCreate');

        $this->actingAs($this->hr)->post(route('payroll.setup.rules.store'), [
            'branch_id' => '', 'target_type' => 'treatment', 'target_id' => $tB->id,
            'method' => 'percent', 'value' => '10', 'effective_from' => '2026-01-01',
        ])->assertSessionHasNoErrors();
    }

    public function test_future_dated_wage_warns_that_it_applies_immediately(): void
    {
        $this->actingAs($this->hr)->put(route('payroll.setup.wages.update', $this->branchA), [
            'min_daily_wage' => '650', 'wage_order_ref' => 'IVA-23 (sample)', 'min_wage_effective_from' => '2026-10-05',
        ])->assertSessionHasNoErrors()->assertSessionHas('warning', fn ($w) => str_contains($w, '2026-10-05'));
    }

    public function test_staff_index_filters_missing_setup(): void
    {
        $this->branchB->forceFill(['has_workforce_finance_suite' => false])->save();
        $u2 = PayrollUserFactory::new()->create(['spa_id' => $this->spa->id, 'branch_id' => $this->branchB->id, 'first_name' => 'Zed', 'last_name' => 'NoSuite']);
        StaffFactory::new()->create(['user_id' => $u2->id, 'spa_id' => $this->spa->id, 'branch_id' => $this->branchB->id]);
        $this->profile();
        $this->staff->forceFill(['tin' => '1', 'sss_no' => '1', 'philhealth_no' => '1', 'pagibig_no' => '1'])->save();

        $this->actingAs($this->viewer)->get(route('payroll.staff.index'))
            ->assertOk()->assertSee('Zed NoSuite')->assertSee('Not included in payroll');

        $this->actingAs($this->viewer)->get(route('payroll.staff.index', ['filter' => 'missing']))
            ->assertOk()->assertSee('Zed NoSuite')->assertDontSee($this->user->email);
    }

    // =====================================================================
    // Recurring items
    // =====================================================================

    public function test_recurring_deduction_requires_authorization_and_can_be_stopped(): void
    {
        $base = ['kind' => 'deduction', 'component_code' => 'LOAN_DEDUCTION', 'label' => 'Cash advance', 'amount' => '500',
                 'frequency' => 'per_cutoff', 'start_date' => '2026-09-01'];

        $this->actingAs($this->hr)->post(route('payroll.staff.recurring.store', $this->staff), $base)
            ->assertSessionHasErrors('authorization_ref', null, 'recurringCreate');
        $this->actingAs($this->hr)->post(route('payroll.staff.recurring.store', $this->staff), array_merge($base, ['component_code' => 'ALLOWANCE', 'authorization_ref' => 'x']))
            ->assertSessionHasErrors('component_code', null, 'recurringCreate');

        $this->actingAs($this->hr)->post(route('payroll.staff.recurring.store', $this->staff), array_merge($base, ['authorization_ref' => 'Signed form 2026-08-30']))
            ->assertSessionHasNoErrors();
        $item = StaffRecurringItem::firstOrFail();

        $this->finalizedRunUsing(recurringIds: [$item->id]);

        $this->actingAs($this->hr)->delete(route('payroll.staff.recurring.destroy', [$this->staff, $item]))
            ->assertSessionHasErrors('recurring', null, 'recurringDelete');
        $this->actingAs($this->hr)->post(route('payroll.staff.recurring.stop', [$this->staff, $item]), ['end_date' => '2026-09-10'])
            ->assertSessionHasErrors('end_date', null, 'recurringStop');
        $this->actingAs($this->hr)->post(route('payroll.staff.recurring.stop', [$this->staff, $item]), ['end_date' => '2026-09-30'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-09-30', $item->fresh()->end_date->toDateString());
    }

    public function test_allowance_without_authorization_is_fine_and_unused_item_can_be_deleted(): void
    {
        $this->actingAs($this->hr)->post(route('payroll.staff.recurring.store', $this->staff), [
            'kind' => 'earning', 'component_code' => 'ALLOWANCE', 'label' => 'Rice', 'amount' => '50',
            'frequency' => 'per_day_worked', 'start_date' => '2026-10-01', 'authorization_ref' => 'ignored',
        ])->assertSessionHasNoErrors();
        $item = StaffRecurringItem::firstOrFail();
        $this->assertNull($item->authorization_ref);

        $this->actingAs($this->hr)->delete(route('payroll.staff.recurring.destroy', [$this->staff, $item]))->assertSessionHasNoErrors();
        $this->assertSame(0, StaffRecurringItem::count());
    }
}
