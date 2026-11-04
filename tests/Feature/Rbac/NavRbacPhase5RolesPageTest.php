<?php

namespace Tests\Feature\Rbac;

use App\Models\Branch;
use App\Models\BranchRolePermission;
use App\Models\Spa;
use App\Models\User;
use App\Services\Permissions\PermissionPresets;
use App\Services\Permissions\PermissionRules as R;
use Database\Seeders\PayrollTestAccountsSeeder;
use Database\Seeders\PlanVariantTestSeeder;
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
