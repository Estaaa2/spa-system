<?php

namespace Tests\Feature\Rbac;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchRolePermission;
use App\Models\Spa;
use App\Models\User;
use App\Services\Permissions\PermissionRules as R;
use Database\Seeders\PayrollTestAccountsSeeder;
use Database\Seeders\PlanVariantTestRemoveSeeder;
use Database\Seeders\PlanVariantTestResetSeeder;
use Database\Seeders\PlanVariantTestSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Sidebar & RBAC rework, Phase 1: permission rules, seeder defaults, Roles &
 * Permissions save rules, the owner's protected minimum, branch switching
 * without `view branches`, the performs-treatments fixes, sidebar gates, the
 * cleanup migration and the plan-variant test seeders.
 *
 * World: PayrollTestAccountsSeeder (2 branches, Premium trial).
 */
class NavRbacPhase1Test extends TestCase
{
    use RefreshDatabase;

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

        $this->spa = Spa::withoutGlobalScopes()->where('name', PayrollTestAccountsSeeder::SPA_NAME)->firstOrFail();
        $this->branchA = Branch::withoutGlobalScopes()->where('spa_id', $this->spa->id)->where('is_main', true)->firstOrFail();
        $this->branchB = Branch::withoutGlobalScopes()->where('spa_id', $this->spa->id)->where('is_main', false)->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function override(string $role, Branch $branch, string $permission, bool $granted): void
    {
        BranchRolePermission::updateOrCreate(
            ['branch_id' => $branch->id, 'role_name' => $role, 'permission_name' => $permission],
            ['spa_id' => $this->spa->id, 'granted' => $granted],
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function runCleanupMigration(): void
    {
        // Found by name suffix, so renaming the timestamp (as on the pushed branch) can't break it.
        $files = glob(database_path('migrations/*_nav_rbac_phase1_permission_cleanup.php'));
        $this->assertCount(1, $files, 'exactly one cleanup migration expected');
        $migration = require $files[0];
        $migration->up();
    }

    // ── Rules and seeder defaults ───────────────────────────────────────────

    public function test_every_seeded_role_default_has_its_bases_and_no_retired_permission(): void
    {
        foreach (Role::with('permissions')->get() as $role) {
            $names = $role->permissions->pluck('name')->all();
            $this->assertSame([], R::missingBases($names), "{$role->name} default is missing a base");
            $this->assertSame([], array_values(array_intersect($names, R::retired())), "{$role->name} holds a retired permission");
        }

        $this->assertNotContains('edit branch profile', Role::findByName('receptionist')->permissions->pluck('name'));
        foreach (R::OWNER_PROTECTED as $name) {
            $this->assertTrue(Role::findByName('owner')->hasPermissionTo($name), $name);
        }
    }

    public function test_dependency_and_conflict_helpers(): void
    {
        $this->assertEqualsCanonicalizing(['create promos', 'view promos', 'view services'], R::withDependencies(['create promos']));
        $this->assertSame([], R::missingBases(['view staff', 'edit staff']));

        $manager = Role::findByName('manager')->permissions->pluck('name')->all();
        $this->assertNotEmpty(R::conflictsFor('manager', $manager));                    // PR create+review, PO create+receive
        $this->assertSame([], R::newConflicts('manager', $manager, $manager));           // nothing new on an unchanged save
        $this->assertCount(1, R::newConflicts('receptionist', [], ['view payroll']));    // payroll on front desk
    }

    public function test_rerunning_the_seeder_keeps_admin_changes_to_defaults(): void
    {
        Role::findByName('manager')->revokePermissionTo('view reports');

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertFalse(Role::findByName('manager')->hasPermissionTo('view reports'));
    }

    // ── Roles & Permissions save ────────────────────────────────────────────

    public function test_saving_a_role_adds_the_bases_its_permissions_need(): void
    {
        $owner = $this->account('owner@levictas.test');
        $manager = $this->account('manager.a@levictas.test');

        $this->actingAs($owner)->withSession(['current_branch_id' => $this->branchA->id])
            ->put(route('owner.roles-permissions.update', Role::findByName('manager')), ['permissions' => ['edit staff', 'create promos']])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Also turned on, because they are needed'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['edit staff', 'view staff', 'create promos', 'view promos', 'view services'] as $p) {
            $this->assertTrue($manager->fresh()->hasBranchPermission($p), $p);
        }
        $this->assertFalse($manager->fresh()->hasBranchPermission('view reports'));
    }

    public function test_the_owner_role_keeps_its_protected_minimum_and_can_still_switch(): void
    {
        $owner = $this->account('owner@levictas.test');

        $this->actingAs($owner)->withSession(['current_branch_id' => $this->branchA->id])
            ->put(route('owner.roles-permissions.update', Role::findByName('owner')), ['permissions' => []])
            ->assertRedirect();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $owner = $owner->fresh();
        foreach (R::OWNER_PROTECTED as $p) {
            $this->assertTrue($owner->hasBranchPermission($p), $p);
        }
        $this->assertFalse($owner->hasBranchPermission('view reports'));

        $this->actingAs($owner)->get(route('owner.roles-permissions.index'))->assertOk();
        $this->actingAs($owner)->postJson(route('branch.switch'), ['branch_id' => $this->branchB->id])->assertOk();
    }

    public function test_retired_permissions_are_hidden_on_the_roles_page(): void
    {
        $this->actingAs($this->account('owner@levictas.test'))
            ->get(route('owner.roles-permissions.edit', Role::findByName('manager')))
            ->assertOk()
            ->assertSee('Create Inventory Items')
            ->assertDontSee('View Product Inventory')
            ->assertDontSee('Export Reports')
            ->assertDontSee('Delete Billing');
    }

    public function test_basic_plan_hides_inventory_and_procurement_permissions(): void
    {
        $this->seed(PlanVariantTestSeeder::class);

        $this->actingAs($this->account('basic.owner@levictas.test'))
            ->get(route('owner.roles-permissions.edit', Role::findByName('manager')))
            ->assertOk()
            ->assertDontSee('Create Inventory Items')
            ->assertDontSee('Create Purchase Orders')
            ->assertSee('Inventory permissions are available on the Premium plan.');
    }

    // ── Branch switching ────────────────────────────────────────────────────

    public function test_switching_no_longer_needs_view_branches(): void
    {
        $this->override('hr_head', $this->branchA, 'view branches', false);   // heads are stored on the main branch
        $hrHead = $this->account('hr.head@levictas.test');

        $this->assertFalse($hrHead->hasBranchPermission('view branches'));
        $this->actingAs($hrHead)->postJson(route('branch.switch'), ['branch_id' => $this->branchB->id])->assertOk();
        $this->actingAs($hrHead)->get(route('branches.index'))->assertForbidden();

        // Still decided by the role: branch staff can't switch.
        $this->actingAs($this->account('hr.a@levictas.test'))
            ->postJson(route('branch.switch'), ['branch_id' => $this->branchB->id])->assertForbidden();
    }

    // ── Performs treatments (P16) ───────────────────────────────────────────

    public function test_a_receptionist_who_does_treatments_can_hand_back_only_their_own_booking(): void
    {
        $reception = $this->account('reception.a@levictas.test');
        $booking = new Booking();
        $booking->forceFill([
            'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id, 'therapist_id' => $reception->id,
            'treatment' => 'treatment_1', 'status' => 'reserved', 'payment_status' => 'unpaid',
            'total_amount' => 1000, 'amount_paid' => 0, 'balance_amount' => 1000,
            'service_type' => 'in_branch', 'booking_source' => 'walk_in', 'customer_name' => 'Test',
            'appointment_date' => '2026-11-05', 'start_time' => '10:00:00', 'end_time' => '11:00:00',
        ])->save();
        $payload = ['reason' => 'Family emergency, cannot attend this one.'];

        $this->actingAs($reception)->postJson(route('reassignment.store', $booking), $payload)->assertForbidden();

        $reception->staff->forceFill(['performs_treatments' => true])->save();
        $this->actingAs($reception->fresh())->postJson(route('reassignment.store', $booking), $payload)->assertSuccessful();

        // Someone else's booking: still refused (the controller checks the assignee).
        $booking->forceFill(['therapist_id' => $this->account('therapist.a1@levictas.test')->id])->save();
        DB::table('reassignment_requests')->delete();
        $this->actingAs($reception->fresh())->postJson(route('reassignment.store', $booking), $payload)->assertForbidden();
    }

    public function test_dashboard_counts_staff_who_do_treatments_and_shows_them_my_today(): void
    {
        $reception = $this->account('reception.a@levictas.test');
        $reception->staff->forceFill(['performs_treatments' => true])->save();

        $this->actingAs($reception->fresh())->get(route('dashboard'))->assertOk()->assertSee('My Schedule Today');

        $this->actingAs($this->account('owner@levictas.test'))->withSession(['current_branch_id' => $this->branchA->id])
            ->get(route('dashboard'))->assertOk()
            ->assertViewHas('therapists', fn ($list) => collect($list)->pluck('id')->contains($reception->id));
    }

    // ── Sidebar gates ───────────────────────────────────────────────────────

    public function test_sidebar_links_match_their_route_permissions(): void
    {
        // Manager: Staff Deployment (view deployments) and Inventory via the route permissions.
        $this->actingAs($this->account('manager.a@levictas.test'))->get(route('dashboard'))->assertOk()
            ->assertSee(route('deployment.index'), false)
            ->assertSee(route('inventory.products'), false)
            ->assertSee(route('inventory.transfers'), false);

        // HR: "This Branch" is no longer empty — it holds Branch Details.
        $this->actingAs($this->account('hr.a@levictas.test'))->get(route('dashboard'))->assertOk()
            ->assertSee('Branch Details');

        // A link with only an edit permission no longer shows (the save adds view anyway).
        $this->override('receptionist', $this->branchA, 'view services', false);
        $this->actingAs($this->account('reception.a@levictas.test'))->get(route('dashboard'))->assertOk()
            ->assertDontSee(route('services.index'), false);
    }

    // ── Migration ───────────────────────────────────────────────────────────

    public function test_cleanup_migration_fixes_rows_and_is_safe_to_rerun(): void
    {
        Role::findByName('receptionist')->givePermissionTo(['edit branch profile', 'edit own profile']);
        $this->override('manager', $this->branchB, 'view staff', false);          // edit staff without its base
        $this->override('therapist', $this->branchA, 'create promos', true);      // needs view promos + view services
        $this->override('owner', $this->branchB, 'view branches', false);         // protected
        $this->override('hr', $this->branchA, 'delete applications', true);       // retired
        $this->override('receptionist', $this->branchA, 'edit branch profile', false); // equals the new default

        $this->runCleanupMigration();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $rows = BranchRolePermission::query()->get()
            ->map(fn ($r) => "{$r->branch_id}|{$r->role_name}|{$r->permission_name}|".(int) $r->granted)->sort()->values()->all();
        $a = $this->branchA->id;
        $this->assertSame([
            "$a|therapist|create promos|1",
            "$a|therapist|view promos|1",
            "$a|therapist|view services|1",
        ], $rows);
        $this->assertFalse(Role::findByName('receptionist')->hasPermissionTo('edit branch profile'));
        $this->assertFalse(Role::findByName('receptionist')->hasPermissionTo('edit own profile'));

        $before = BranchRolePermission::count();
        $this->runCleanupMigration();
        $this->assertSame($before, BranchRolePermission::count());
    }

    // ── Plan-variant seeders ────────────────────────────────────────────────

    public function test_plan_variant_seeders_create_reset_and_remove_only_their_spas(): void
    {
        $this->seed(PlanVariantTestSeeder::class);

        $plans = Spa::withoutGlobalScopes()->whereIn('name', PlanVariantTestSeeder::spaNames())->get()
            ->mapWithKeys(fn (Spa $s) => [$s->name => [$s->currentPlan(), $s->branches()->count()]])->all();
        $this->assertEquals([
            PlanVariantTestSeeder::SPAS['solo'][0]      => ['premium', 1],
            PlanVariantTestSeeder::SPAS['basic'][0]     => ['basic', 2],
            PlanVariantTestSeeder::SPAS['solobasic'][0] => ['basic', 1],
        ], $plans);   // assertEquals: key order does not matter
        $this->assertTrue((bool) $this->account('solo.reception.tx@levictas.test')->staff->performs_treatments);

        $this->seed(PlanVariantTestSeeder::class);   // second run refused
        $this->assertSame(3, Spa::withoutGlobalScopes()->whereIn('name', PlanVariantTestSeeder::spaNames())->count());

        $this->account('basic.manager@levictas.test')->forceFill(['first_name' => 'Changed'])->save();
        $this->seed(PlanVariantTestResetSeeder::class);
        $this->assertSame('Mara', $this->account('basic.manager@levictas.test')->first_name);

        $this->seed(PlanVariantTestRemoveSeeder::class);
        $this->assertSame(0, Spa::withoutGlobalScopes()->whereIn('name', PlanVariantTestSeeder::spaNames())->count());
        $this->assertSame(0, User::where('email', 'like', 'solo%@levictas.test')->orWhere('email', 'like', 'basic.%@levictas.test')->count());
        $this->assertTrue(Spa::withoutGlobalScopes()->where('name', PayrollTestAccountsSeeder::SPA_NAME)->exists());
        $this->assertSame(12, User::where('email', 'like', '%@levictas.test')->count());
    }
}
