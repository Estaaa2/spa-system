<?php

namespace Tests\Feature\Rbac;

use App\Models\Branch;
use App\Models\BranchRolePermission;
use App\Models\User;
use App\Services\Navigation\Sidebar;
use Database\Seeders\PayrollTestAccountsSeeder;
use Database\Seeders\PlanVariantTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Sidebar & RBAC rework, Phase 2: the sidebar structure (zones, pinned links,
 * children-first groups, one-link flattening), the branch switcher rule (D6),
 * the permission memo (F3), the login landing (P4) and the drawer hooks.
 *
 * World: PayrollTestAccountsSeeder (2 branches, Premium) + PlanVariantTestSeeder
 * (1 branch Premium, 2 branches Basic, 1 branch Basic).
 */
class NavRbacPhase2SidebarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 09:00:00');
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(PlanVariantTestSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
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

    private function nav(User $user): array
    {
        $this->actingAs($user);
        $branchId = $user->currentBranchId();

        return Sidebar::build($user, $branchId ? Branch::find($branchId) : null, false);
    }

    /** @return list<array> every link in the sidebar, flattened */
    private function links(array $nav): array
    {
        $out = $nav['top'];
        foreach ($nav['zones'] as $zone) {
            foreach ($zone['items'] as $item) {
                array_push($out, ...($item['type'] === 'link' ? [$item] : $item['links']));
            }
        }

        return $out;
    }

    private function staffEmails(): array
    {
        return User::where('email', 'like', '%@levictas.test')
            ->whereHas('roles', fn ($q) => $q->whereNotIn('name', ['customer', 'admin']))
            ->orderBy('id')->pluck('email')->all();
    }

    // ── Structure ───────────────────────────────────────────────────────────

    public function test_no_empty_or_single_link_groups_and_no_duplicate_links_for_any_account(): void
    {
        foreach ($this->staffEmails() as $email) {
            $nav = $this->nav($this->account($email));

            foreach ($nav['zones'] as $zone) {
                $this->assertNotEmpty($zone['items'], "$email: empty zone {$zone['key']}");
                foreach ($zone['items'] as $item) {
                    if ($item['type'] === 'group') {
                        $this->assertGreaterThanOrEqual(2, count($item['links']), "$email: group {$item['key']} has < 2 links");
                    }
                }
            }

            $keys = array_column($this->links($nav), 'key');
            $this->assertSame(count($keys), count(array_unique($keys)), "$email: a link appears twice");
        }
    }

    public function test_owner_layout_pins_and_scope_zones(): void
    {
        $nav = $this->nav($this->account('owner@levictas.test'));

        $this->assertSame(['dashboard', 'book', 'appointments', 'schedule'], array_column($nav['top'], 'key'));   // Phase 2 Q1 → A
        $this->assertSame(['branch', 'spa'], array_column($nav['zones'], 'key'));
        $this->assertSame('Test Branch A', $nav['zones'][0]['label']);

        $spaGroups = array_column($nav['zones'][1]['items'], 'key');
        $this->assertSame(['payroll', 'business'], $spaGroups);

        $suppliers = collect($this->links($nav))->firstWhere('key', 'suppliers');
        $this->assertSame('All branches', $suppliers['chip']);
    }

    public function test_payroll_sits_in_the_zone_that_matches_its_scope(): void
    {
        // HR Head: spa-wide payroll → Whole spa zone, and Pay Runs is not pinned into the branch zone.
        $head = $this->nav($this->account('hr.head@levictas.test'));
        $this->assertNotContains('pay-runs', array_column($head['top'], 'key'));
        $this->assertSame('payroll', collect($head['zones'])->firstWhere('key', 'spa')['items'][0]['key']);

        // Branch HR: own-branch payroll → branch zone, no Whole spa zone at all.
        $hr = $this->nav($this->account('hr.a@levictas.test'));
        $this->assertSame(['branch'], array_column($hr['zones'], 'key'));
        $this->assertContains('payroll', array_column($hr['zones'][0]['items'], 'key'));
    }

    public function test_basic_plan_keeps_pages_in_the_same_groups(): void
    {
        $nav = $this->nav($this->account('basic.manager@levictas.test'));
        $people = collect($nav['zones'][0]['items'])->firstWhere('key', 'people');

        // Attendance and Staff live in People on every plan (they used to jump groups on Basic).
        $this->assertSame(['staff', 'attendance'], array_column($people['links'], 'key'));
        $this->assertNull(collect($this->links($nav))->firstWhere('key', 'products'));
    }

    public function test_every_sidebar_link_opens_without_a_403(): void
    {
        foreach (['owner@levictas.test', 'hr.head@levictas.test', 'finance.a@levictas.test', 'manager.a@levictas.test',
                  'reception.a@levictas.test', 'therapist.a1@levictas.test', 'basic.owner@levictas.test', 'solo.reception.tx@levictas.test'] as $email) {
            $user = $this->account($email);
            foreach ($this->links($this->nav($user)) as $link) {
                if ($link['key'] === 'decision-support') {
                    continue;   // MySQL-only SQL (HOUR()); not runnable on the sqlite test database
                }
                $status = $this->actingAs($user)->get($link['url'])->getStatusCode();
                $this->assertNotSame(403, $status, "$email: {$link['label']} answered 403");
                $this->assertLessThan(500, $status, "$email: {$link['label']} answered $status");
            }
        }
    }

    // ── Switcher (D6) ───────────────────────────────────────────────────────

    public function test_switcher_only_for_switching_roles_in_multi_branch_spas(): void
    {
        $hasSwitcher = fn (string $email) => (bool) preg_match('/switchBranch\(\d/', $this->actingAs($this->account($email))->get(route('dashboard'))->getContent());

        $this->assertTrue($hasSwitcher('owner@levictas.test'));          // 2 branches
        $this->assertTrue($hasSwitcher('basic.owner@levictas.test'));    // 2 branches, Basic
        $this->assertFalse($hasSwitcher('solo.owner@levictas.test'));    // 1 branch
        $this->assertFalse($hasSwitcher('solo.hr.head@levictas.test'));  // 1 branch
        $this->assertFalse($hasSwitcher('manager.a@levictas.test'));     // can't switch
    }

    // ── Performance (F3) ────────────────────────────────────────────────────

    public function test_sidebar_render_stays_under_a_query_budget(): void
    {
        foreach (['owner@levictas.test' => 25, 'finance.head@levictas.test' => 25, 'reception.a@levictas.test' => 20] as $email => $budget) {
            $user = $this->account($email);
            $this->actingAs($user);
            $branchId = $user->currentBranchId();
            view()->share('errors', new \Illuminate\Support\ViewErrorBag());
            app(\App\Services\Permissions\BranchPermissionCache::class)->flush();

            DB::flushQueryLog();
            DB::enableQueryLog();
            view('layouts.navigation')->render();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            $this->assertLessThanOrEqual($budget, $count, "$email sidebar ran $count queries");
        }
    }

    public function test_permission_memo_sees_a_change_made_through_the_model(): void
    {
        $reception = $this->account('reception.a@levictas.test');
        $this->assertTrue($reception->hasBranchPermission('view services'));

        BranchRolePermission::create([
            'branch_id' => $reception->branch_id, 'spa_id' => $reception->spa_id,
            'role_name' => 'receptionist', 'permission_name' => 'view services', 'granted' => false,
        ]);

        $this->assertFalse($reception->hasBranchPermission('view services'));
    }

    // ── Landing (P4) ────────────────────────────────────────────────────────

    public function test_staff_land_on_the_dashboard_or_their_first_permitted_link(): void
    {
        $login = fn (string $email) => $this->post('/login', ['email' => $email, 'password' => PayrollTestAccountsSeeder::PASSWORD]);

        $login('hr.a@levictas.test')->assertRedirect(route('dashboard'));   // was hiring.index
        $this->post('/logout');

        // Without the Dashboard, HR lands on its first pinned link (Staff), not a 403.
        Role::findByName('hr')->revokePermissionTo('view business dashboard');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $login('hr.a@levictas.test')->assertRedirect(route('staff.index'));
    }

    // ── Drawer accessibility hooks ──────────────────────────────────────────

    public function test_drawer_has_its_accessibility_hooks(): void
    {
        $html = $this->actingAs($this->account('reception.a@levictas.test'))->get(route('dashboard'))->getContent();

        $this->assertStringContainsString('x-ref="drawerToggle"', $html);
        $this->assertStringContainsString('@keydown.tab="trapFocus($event)"', $html);
        $this->assertStringContainsString('$el.inert = isMobile && !open', $html);
        $this->assertStringContainsString('aria-label="Main navigation"', $html);
    }
}
