<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use App\Services\Navigation\AdminSidebar;
use Database\Seeders\AdminSeeder;
use Tests\Fixtures\IdentityVerificationTestSeeder;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Sidebar & RBAC rework, Phase 3: the admin sidebar uses the business sidebar's
 * structure, partial, drawer behaviour and badge slot (answer Phase 2 Q4 → A).
 */
class NavRbacPhase3AdminSidebarTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 09:00:00');
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(AdminSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);   // 2 identity reviews pending
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->admin = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->firstOrFail();
    }

    /** A request with its route resolved, so routeIs() works as it does in a real request. */
    private function req(string $path): Request
    {
        $request = Request::create($path);
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_sidebar_has_dashboard_review_and_platform(): void
    {
        $nav = AdminSidebar::build($this->admin, $this->req('/admin/users'));

        $this->assertSame(['dashboard'], array_column($nav['top'], 'key'));
        $this->assertSame(['review', 'platform'], array_column($nav['zones'][0]['items'], 'key'));
        $this->assertSame('platform', $nav['activeGroup']);

        $review = $nav['zones'][0]['items'][0];
        $this->assertSame(['registered-spas', 'id-reviews', 'subscriptions', 'refunds'], array_column($review['links'], 'key'));   // Refunds: customer cancellation, Nov 2026
        $this->assertSame(2, $review['badgeTotal']);
    }

    public function test_links_follow_admin_permissions_and_groups_flatten(): void
    {
        Role::findByName('admin')->revokePermissionTo('view system roles');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $items = AdminSidebar::build($this->admin->fresh(), $this->req('/admin/dashboard'))['zones'][0]['items'];
        $platform = collect($items)->firstWhere('key', 'users');   // one link left → plain link (D2)

        $this->assertSame('link', $platform['type']);
        $this->assertNull(collect($items)->firstWhere('key', 'platform'));
    }

    public function test_every_admin_link_opens_and_the_page_shows_the_shared_markup(): void
    {
        $nav = AdminSidebar::build($this->admin, $this->req('/admin/dashboard'));
        $links = [...$nav['top']];
        foreach ($nav['zones'][0]['items'] as $item) {
            array_push($links, ...($item['type'] === 'link' ? [$item] : $item['links']));
        }
        foreach ($links as $link) {
            $this->actingAs($this->admin)->get($link['url'])->assertOk();
        }

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->getContent();
        $this->assertStringContainsString('x-ref="drawerToggle"', $html);
        $this->assertStringContainsString('@keydown.tab="trapFocus($event)"', $html);
        $this->assertStringContainsString('window.levictasSidebar', $html);
        $this->assertStringContainsString('has items needing action', $html);   // amber dot on the collapsed Review group
        $this->assertStringContainsString('Administration', $html);
    }

    public function test_the_business_sidebar_still_renders_through_the_shared_partial(): void
    {
        $owner = User::where('email', 'owner@levictas.test')->firstOrFail();
        $html = $this->actingAs($owner)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Whole spa', $html);
        $this->assertStringContainsString('window.levictasSidebar', $html);
        $this->assertSame(1, substr_count($html, 'window.levictasSidebar = window.levictasSidebar'));
    }
}
