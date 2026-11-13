<?php

namespace Tests\Feature\Rbac;

use App\Models\Branch;
use App\Models\BranchRolePermission;
use App\Models\Spa;
use App\Models\User;
use App\Services\Permissions\PermissionPresets;
use App\Services\Permissions\PermissionRules as R;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Tests\Fixtures\PlanVariantTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Sidebar & RBAC rework, Phase 5: the Roles & Permissions page — server-side
 * conflict confirmation (D8.3, P10/P11), "Also apply to all branches" (D9/Q2),
 * presets (RP4) and the read-only "does treatments" note (RP3/Q1).
 */
class NavRbacPhase5RolesPageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Spa $spa;
    private Branch $branchA;
    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 09:00:00');
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->owner = User::where('email', 'owner@levictas.test')->firstOrFail();
        $this->spa = Spa::withoutGlobalScopes()->where('name', PayrollTestAccountsSeeder::SPA_NAME)->firstOrFail();
        $this->branchA = Branch::withoutGlobalScopes()->where('spa_id', $this->spa->id)->where('is_main', true)->firstOrFail();
        $this->branchB = Branch::withoutGlobalScopes()->where('spa_id', $this->spa->id)->where('is_main', false)->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function save(string $role, array $permissions, array $extra = [])
    {
        // Hidden permissions are never on the form (and the server rejects them).
        $permissions = array_values(array_diff($permissions, ['view business dashboard', 'view spa profile', 'edit spa profile']));

        return $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branchA->id])
            ->put(route('owner.roles-permissions.update', Role::findByName($role)), ['permissions' => $permissions] + $extra);
    }

    private function receptionistDefault(): array
    {
        return Role::findByName('receptionist')->permissions->pluck('name')->all();
    }

    // ── Conflicts ───────────────────────────────────────────────────────────

    public function test_a_new_conflict_is_refused_until_confirmed(): void
    {
        $withPayroll = [...$this->receptionistDefault(), 'view payroll'];   // payroll on front desk (role limit)

        $this->save('receptionist', $withPayroll)
            ->assertRedirect(route('owner.roles-permissions.edit', Role::findByName('receptionist')))
            ->assertSessionHas('pendingConflicts', fn ($c) => count($c) === 1 && in_array('View Payroll', $c[0]['labels'], true));
        $this->assertFalse(BranchRolePermission::where('role_name', 'receptionist')->where('permission_name', 'view payroll')->exists());

        $this->save('receptionist', $withPayroll, ['confirm_conflicts' => 1])->assertSessionHas('success');
        $this->assertTrue((bool) BranchRolePermission::where('role_name', 'receptionist')->where('branch_id', $this->branchA->id)
            ->where('permission_name', 'view payroll')->value('granted'));
    }

    public function test_conflicts_the_role_already_had_need_no_confirmation(): void
    {
        // Manager's default holds two pairs (PR create+review, PO create+receive): saving it unchanged just saves.
        $default = Role::findByName('manager')->permissions->pluck('name')->all();
        $this->assertNotEmpty(R::conflictsFor('manager', $default));

        $this->save('manager', $default)->assertSessionHas('success')->assertSessionMissing('pendingConflicts');
    }

    // ── Branch scope ────────────────────────────────────────────────────────

    public function test_apply_to_all_branches_writes_every_branch_and_defaults_to_this_one(): void
    {
        $noBranchProfile = array_values(array_diff($this->receptionistDefault(), ['view services', 'view promos']));

        $this->save('receptionist', $noBranchProfile)->assertSessionHas('success');
        $this->assertSame([$this->branchA->id], BranchRolePermission::where('role_name', 'receptionist')
            ->where('permission_name', 'view services')->pluck('branch_id')->all());

        $this->save('receptionist', $noBranchProfile, ['apply_all_branches' => 1])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'all 2 branches'));
        $this->assertEqualsCanonicalizing([$this->branchA->id, $this->branchB->id], BranchRolePermission::where('role_name', 'receptionist')
            ->where('permission_name', 'view services')->pluck('branch_id')->all());
    }

    public function test_owner_payroll_permissions_are_written_once_even_for_all_branches(): void
    {
        $owner = Role::findByName('owner')->permissions->pluck('name')->all();
        $this->save('owner', [...$owner, 'prepare payroll runs'], ['apply_all_branches' => 1, 'confirm_conflicts' => 1])
            ->assertSessionHas('success');

        $rows = BranchRolePermission::where('role_name', 'owner')->where('permission_name', 'prepare payroll runs')->get();
        $this->assertCount(1, $rows);
        $this->assertSame($this->branchA->id, (int) $rows->first()->branch_id);   // main branch (F5)
    }

    // ── Presets ─────────────────────────────────────────────────────────────

    public function test_presets_follow_the_rules_and_only_fill_the_page(): void
    {
        $editable = \Spatie\Permission\Models\Permission::pluck('name')->all();
        $presets = PermissionPresets::forRole('owner', Role::findByName('owner')->permissions->pluck('name')->all(), $editable);
        $payroll = collect($presets)->firstWhere('key', 'owner-runs-payroll');

        $this->assertSame('default', $presets[0]['key']);
        $this->assertSame([], R::missingBases($payroll['permissions']));
        $this->assertContains('approve payroll runs', $payroll['permissions']);

        // Showing the page saves nothing.
        $before = BranchRolePermission::count();
        $this->actingAs($this->owner)->get(route('owner.roles-permissions.edit', Role::findByName('owner')))
            ->assertOk()->assertSee('Owner runs payroll')->assertSee('Always on for the owner');
        $this->assertSame($before, BranchRolePermission::count());
    }

    public function test_presets_that_change_nothing_on_basic_are_hidden(): void
    {
        $this->seed(PlanVariantTestSeeder::class);
        $basicOwner = User::where('email', 'basic.owner@levictas.test')->firstOrFail();

        $this->actingAs($basicOwner)->get(route('owner.roles-permissions.edit', Role::findByName('owner')))
            ->assertOk()->assertDontSee('Owner runs payroll');
    }

    // ── Phase 5.1: impossible permissions are locked; new warnings ──────────

    public function test_permissions_a_role_cannot_use_are_locked_and_never_saved(): void
    {
        $manager = Role::findByName('manager')->permissions->pluck('name')->all();

        $this->save('manager', [...$manager, 'create branches', 'delete branches'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, "Not saved, because they can't work for this role: Create Branches, Delete Branches"));
        $this->assertFalse(BranchRolePermission::where('role_name', 'manager')->whereIn('permission_name', ['create branches', 'delete branches'])->exists());

        // Branch Finance can't approve or release pay runs (needs a spa-wide role).
        $this->save('finance', [...Role::findByName('finance')->permissions->pluck('name')->all(), 'approve payroll runs']);
        $this->assertFalse(BranchRolePermission::where('role_name', 'finance')->where('permission_name', 'approve payroll runs')->exists());

        $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branchA->id])
            ->get(route('owner.roles-permissions.edit', Role::findByName('manager')))->assertOk()
            ->assertSee('Owner only')->assertSee(', not available for Manager', false)
            ->assertDontSee('Verify Staff Identity');   // platform-admin permission, never on the business page

        // The owner keeps them, and Finance Head can still approve.
        $this->assertNull(R::unavailableReason('owner', 'create branches'));
        $this->assertNull(R::unavailableReason('finance_head', 'approve payroll runs'));
    }

    public function test_presets_never_tick_a_locked_permission(): void
    {
        $editable = \Spatie\Permission\Models\Permission::pluck('name')->all();
        foreach (PermissionPresets::forRole('finance', Role::findByName('finance')->permissions->pluck('name')->all(), $editable) as $preset) {
            $this->assertNotContains('approve payroll runs', $preset['permissions']);
        }
    }

    public function test_cleanup_migration_removes_saved_grants_of_locked_permissions(): void
    {
        BranchRolePermission::create(['branch_id' => $this->branchA->id, 'spa_id' => $this->spa->id,
            'role_name' => 'manager', 'permission_name' => 'delete branches', 'granted' => true]);
        Role::findByName('finance')->givePermissionTo('release payroll runs');

        $files = glob(database_path('migrations/*_nav_rbac_phase5_unavailable_cleanup.php'));
        (require $files[0])->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertFalse(BranchRolePermission::where('role_name', 'manager')->where('permission_name', 'delete branches')->exists());
        $this->assertFalse(Role::findByName('finance')->hasPermissionTo('release payroll runs'));
        $this->assertTrue(Role::findByName('finance_head')->hasPermissionTo('release payroll runs'));
    }

    public function test_new_warnings_ask_before_saving(): void
    {
        $this->save('receptionist', [...$this->receptionistDefault(), 'edit branch general'])
            ->assertSessionHas('pendingConflicts', fn ($c) => str_contains($c[0]['reason'], 'rename the branch'));

        $this->assertNotEmpty(R::newConflicts('therapist', [], ['view attendance', 'edit attendance']));
        $this->assertNotEmpty(R::newConflicts('receptionist', [], ['view services', 'delete treatments']));
        $this->assertNotEmpty(R::newConflicts('therapist', [], ['view applications']));
        $this->assertSame([], R::newConflicts('manager', [], ['view applications', 'edit branch general', 'delete treatments']));
    }

    // ── Does treatments (RP3 / Q1) ──────────────────────────────────────────

    public function test_roles_pages_show_the_read_only_treatments_note(): void
    {
        User::where('email', 'reception.a@levictas.test')->firstOrFail()->staff->forceFill(['performs_treatments' => true])->save();

        $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branchA->id])
            ->get(route('owner.roles-permissions.index'))->assertOk()
            ->assertSee('1 receptionist')->assertSee('manage on the Staff page');

        $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branchA->id])
            ->get(route('owner.roles-permissions.edit', Role::findByName('receptionist')))->assertOk()
            ->assertSee('1 receptionist at this branch also does treatments.');
    }
}
