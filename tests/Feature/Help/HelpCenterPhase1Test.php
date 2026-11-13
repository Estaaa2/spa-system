<?php

namespace Tests\Feature\Help;

use App\Models\BranchRolePermission;
use App\Models\User;
use App\Services\Navigation\Sidebar;
use App\Support\Guides\GuideCatalog;
use App\Support\Guides\GuideContext;
use App\Support\Guides\Guides;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Tests\Fixtures\PlanVariantTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Help guides Phase 1: the Help page, its filtering (D1), the header's Help
 * button (D2), the "New here?" banner (D4) and the content class (D6).
 *
 * World: PayrollTestAccountsSeeder (Premium, one account per role) +
 * PlanVariantTestSeeder (Basic-plan spas).
 */
class HelpCenterPhase1Test extends TestCase
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

    /** @return list<string> business accounts from both seeders */
    private function staffEmails(): array
    {
        return User::where('email', 'like', '%@levictas.test')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', GuideCatalog::BUSINESS_ROLES))
            ->orderBy('id')->pluck('email')->all();
    }

    /** @return list<string> guide ids rendered on the Help page */
    private function guideIds(string $html): array
    {
        preg_match_all('/<details id="([a-z-]+)"/', $html, $m);

        return $m[1];
    }

    private function sidebarKeys(User $user): array
    {
        $this->actingAs($user);

        return array_column(Sidebar::links(Sidebar::forCurrentRequest($user)), 'key');
    }

    // ── Access ──────────────────────────────────────────────────────────────

    public function test_every_business_account_can_open_help(): void
    {
        foreach ($this->staffEmails() as $email) {
            $this->actingAs($this->account($email))
                ->get(route('help.index'))
                ->assertOk()
                ->assertSee('Help &amp; Guides', false)
                ->assertSee('id="getting-started"', false)
                ->assertSee('id="profile"', false);
        }
    }

    public function test_customers_and_admins_cannot_open_help(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $customer->assignRole('customer');
        $this->actingAs($customer)->get(route('help.index'))->assertForbidden();
        $this->actingAs($customer)->post(route('help.banner.dismiss'))->assertForbidden();

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');
        $this->actingAs($admin)->get(route('help.index'))->assertForbidden();

        auth()->logout();
        $this->get(route('help.index'))->assertRedirect(route('login'));
    }

    // ── D1: same rules as the sidebar ───────────────────────────────────────

    public function test_help_lists_exactly_the_written_guides_for_pages_in_the_sidebar(): void
    {
        foreach ($this->staffEmails() as $email) {
            $user = $this->account($email);
            $expected = array_values(array_unique([
                ...Guides::ALWAYS,
                ...array_values(array_filter($this->sidebarKeys($user), fn ($k) => Guides::has($k))),
            ]));

            $ids = $this->guideIds($this->actingAs($user)->get(route('help.index'))->getContent());

            sort($expected);
            sort($ids);
            $this->assertSame($expected, $ids, "$email: Help does not match the sidebar");
        }
    }

    public function test_removing_a_permission_removes_the_guide(): void
    {
        $reception = $this->account('reception.a@levictas.test');
        $this->actingAs($reception)->get(route('help.index'))->assertSee('id="schedule"', false);

        BranchRolePermission::create([
            'branch_id' => $reception->branch_id, 'spa_id' => $reception->spa_id,
            'role_name' => 'receptionist', 'permission_name' => 'view schedule', 'granted' => false,
        ]);
        app(\App\Services\Permissions\BranchPermissionCache::class)->flush();

        $this->actingAs($reception->fresh())->get(route('help.index'))
            ->assertOk()
            ->assertDontSee('id="schedule"', false);
    }

    public function test_plan_features_filter_tasks(): void
    {
        // Treatment recipes use inventory (Premium only).
        $this->actingAs($this->account('owner@levictas.test'))->get(route('help.index'))
            ->assertSee('Set the products a treatment uses');

        $this->actingAs($this->account('basic.owner@levictas.test'))->get(route('help.index'))
            ->assertOk()
            ->assertSee('id="services"', false)
            ->assertDontSee('Set the products a treatment uses');
    }

    public function test_tasks_follow_the_users_permissions(): void
    {
        // A therapist can't process appointments, but can say they can't make one.
        $html = $this->actingAs($this->account('therapist.a1@levictas.test'))->get(route('help.index'))->getContent();
        $this->assertStringNotContainsString('Check out a finished appointment', $html);
        $this->assertStringContainsString('you can&#039;t make an appointment', $html);

        $html = $this->actingAs($this->account('reception.a@levictas.test'))->get(route('help.index'))->getContent();
        $this->assertStringContainsString('Check out a finished appointment', $html);
    }

    // ── D2: Help button in the page header ──────────────────────────────────

    public function test_help_button_links_to_the_current_pages_guide(): void
    {
        $reception = $this->account('reception.a@levictas.test');

        $this->actingAs($reception)->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('href="'.route('help.index').'#appointments"', false)
            ->assertSee('target="_blank"', false);

        $this->actingAs($reception)->get(route('schedule.index'))
            ->assertSee('href="'.route('help.index').'#schedule"', false);

        // Not a sidebar page, matched by route.
        $this->actingAs($reception)->get(route('profile.edit'))
            ->assertSee('href="'.route('help.index').'#profile"', false);
    }

    public function test_no_help_button_on_pages_without_a_guide_or_for_admins(): void
    {
        // Revenue has no guide yet (Phase 2).
        $this->actingAs($this->account('finance.a@levictas.test'))->get(route('revenue.index'))
            ->assertOk()
            ->assertDontSee(route('help.index').'#', false);

        // The Help page itself.
        $this->actingAs($this->account('owner@levictas.test'))->get(route('help.index'))
            ->assertDontSee('href="'.route('help.index').'#', false);

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('help.index'), false);
    }

    public function test_sidebar_has_the_help_link_for_business_users(): void
    {
        $this->actingAs($this->account('therapist.a1@levictas.test'))->get(route('profile.edit'))
            ->assertSee('href="'.route('help.index').'"', false);
    }

    // ── D4: first-login banner ──────────────────────────────────────────────

    public function test_banner_shows_until_dismissed(): void
    {
        $user = $this->account('manager.a@levictas.test');
        $this->assertNull($user->guides_dismissed_at);

        $this->actingAs($user)->get(route('appointments.index'))->assertSee('New here?');

        $this->actingAs($user)->postJson(route('help.banner.dismiss'))->assertNoContent();
        $this->assertNotNull($user->fresh()->guides_dismissed_at);

        $this->actingAs($user->fresh())->get(route('appointments.index'))->assertDontSee('New here?');
    }

    public function test_dismiss_without_javascript_redirects_back(): void
    {
        $user = $this->account('hr.a@levictas.test');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('help.banner.dismiss'))
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh()->guides_dismissed_at);
    }

    public function test_opening_help_also_dismisses_the_banner(): void
    {
        $user = $this->account('therapist.a2@levictas.test');

        $this->actingAs($user)->get(route('help.index'))->assertOk()->assertDontSee('New here?');
        $this->assertNotNull($user->fresh()->guides_dismissed_at);
    }

    public function test_dismissing_keeps_the_first_time(): void
    {
        $user = $this->account('finance.a@levictas.test');
        $this->actingAs($user)->post(route('help.banner.dismiss'));
        $first = $user->fresh()->guides_dismissed_at;

        Carbon::setTestNow('2026-11-05 09:00:00');
        $this->actingAs($user->fresh())->post(route('help.banner.dismiss'));

        $this->assertTrue($first->equalTo($user->fresh()->guides_dismissed_at));
    }

    public function test_no_banner_for_owners_without_access_or_for_admins(): void
    {
        $owner = $this->account('owner@levictas.test');
        $this->assertTrue(GuideCatalog::showsBanner($owner));

        $owner->spa->forceFill(['verification_status' => 'rejected'])->save();
        $this->assertFalse(GuideCatalog::showsBanner($owner->fresh()));

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');
        $this->assertFalse(GuideCatalog::showsBanner($admin));
    }

    // ── D6: the content class ───────────────────────────────────────────────

    public function test_every_guide_key_is_a_sidebar_key_or_an_always_guide(): void
    {
        preg_match_all("/->link\\('([a-z-]+)'/", file_get_contents(app_path('Services/Navigation/Sidebar.php')), $m);
        $sidebarKeys = array_unique($m[1]);
        $this->assertContains('appointments', $sidebarKeys, 'Sidebar key scan found nothing');

        foreach (array_keys(Guides::all()) as $key) {
            $this->assertTrue(
                in_array($key, $sidebarKeys, true) || in_array($key, Guides::ALWAYS, true),
                "Guide '$key' matches no sidebar link and is not an always-shown guide"
            );
        }
    }

    public function test_every_guide_is_complete_and_uses_known_conditions(): void
    {
        foreach (Guides::all() as $key => $guide) {
            $this->assertNotEmpty($guide['title'] ?? null, "$key: title");
            $this->assertNotEmpty($guide['summary'] ?? null, "$key: summary");
            $this->assertNotEmpty($guide['tasks'] ?? [], "$key: tasks");

            foreach ($guide['tasks'] as $task) {
                $this->assertNotEmpty($task['title'] ?? null, "$key: task title");
                $this->assertNotEmpty($task['steps'] ?? [], "$key: {$task['title']} has no steps");

                $conditions = (array) ($task['when'] ?? []);
                foreach ($task['steps'] as $step) {
                    if (is_array($step)) {
                        $this->assertNotEmpty($step['text'] ?? null, "$key: step text");
                        array_push($conditions, ...(array) ($step['when'] ?? []));
                    }
                }
                foreach ($conditions as $c) {
                    $this->assertTrue(GuideContext::isKnown($c), "$key: unknown condition '$c'");
                }
            }

            if (isset($guide['routes'])) {
                $this->assertContains($key, Guides::ALWAYS, "$key: only always-shown guides match by route");
            }
        }
    }

    // ── Sidebar reuse ───────────────────────────────────────────────────────

    public function test_sidebar_is_built_once_per_request_context(): void
    {
        $user = $this->account('owner@levictas.test');
        $this->actingAs($user);

        $a = Sidebar::forCurrentRequest($user);
        $b = Sidebar::forCurrentRequest($user);
        $this->assertSame($a, $b);

        // A different branch is a different build, never a stale copy.
        $other = $user->spa->branches()->where('id', '!=', $user->currentBranchId())->first();
        $c = Sidebar::cached($user, $other, false);
        $this->assertSame($other->name, $c['zones'][0]['label']);
    }
}
