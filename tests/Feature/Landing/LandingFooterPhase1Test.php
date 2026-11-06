<?php

namespace Tests\Feature\Landing;

use App\Models\User;
use App\Services\Navigation\Sidebar;
use Database\Seeders\HomeVisitTestSeeder;
use Database\Seeders\IdentityVerificationTestSeeder;
use Database\Seeders\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Landing footer Phase 1: shared nav/footer partials, the public layout,
 * footer "Get Started" per role (D6, Q2-A, Q3-A), the hidden "Own a Spa?"
 * CTA and empty-state button for customers (D7, Q4-A), and config-driven
 * social icons (D1, Q5-A).
 */
class LandingFooterPhase1Test extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);
        $this->seed(HomeVisitTestSeeder::class);

        $this->customer = User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();

        Config::set('levictas.social', ['linkedin' => '', 'x' => '', 'github' => '']);
    }

    private function user(string $key): User
    {
        return User::where('email', "{$key}@levictas.test")->firstOrFail();
    }

    /** The rendered <footer> only, so nav links with the same text don't count. */
    private function footer(string $html): string
    {
        $start = strpos($html, '<footer');
        $end = strpos($html, '</footer>');
        $this->assertNotFalse($start, 'No <footer> rendered.');

        return substr($html, $start, $end - $start);
    }

    /** Renders a throwaway page on the public layout as the current user. */
    private function publicPage(): string
    {
        return Blade::render(<<<'BLADE'
            @extends('layouts.public')
            @section('title', 'Test page')
            @section('content')<p>Public body</p>@endsection
            BLADE);
    }

    private function bookNowHref(): string
    {
        return 'href="' . url('/') . '#browseSections">Book Now</a>';
    }

    // ── Landing page (/) ─────────────────────────────────────────────────────

    public function test_guest_footer_offers_register_login_and_book_now(): void
    {
        $footer = $this->footer($this->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('href="' . route('register') . '">Register</a>', $footer);
        $this->assertStringContainsString('href="' . route('login') . '">Login</a>', $footer);
        $this->assertStringContainsString($this->bookNowHref(), $footer);
        $this->assertStringNotContainsString('My Appointments', $footer);
    }

    public function test_customer_footer_offers_appointments_profile_and_book_now(): void
    {
        $footer = $this->footer($this->actingAs($this->customer)->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('onclick="openAppointmentsModal()"', $footer);
        $this->assertStringContainsString('>My Appointments</button>', $footer);
        $this->assertStringContainsString('onclick="openProfileModal();"', $footer);
        $this->assertStringContainsString('>Profile</button>', $footer);
        $this->assertStringContainsString($this->bookNowHref(), $footer);
        $this->assertStringNotContainsString('>Register</a>', $footer);
        $this->assertStringNotContainsString('>Login</a>', $footer);
    }

    public function test_footer_book_now_no_longer_points_at_the_walk_in_form(): void
    {
        // route('booking') is the business-side walk-in form: customers get 403.
        $this->actingAs($this->customer)->get(route('booking'))->assertForbidden();

        $this->assertStringNotContainsString(
            'href="' . route('booking') . '"',
            $this->footer($this->actingAs($this->customer)->get('/')->getContent())
        );
    }

    public function test_own_a_spa_cta_is_shown_to_guests_only(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Own a Spa? List Your Business with Us!')
            ->assertSee('href="' . route('register.business') . '"', false);

        $this->actingAs($this->customer)->get('/')->assertOk()
            ->assertDontSee('Own a Spa? List Your Business with Us!');
    }

    public function test_customers_get_no_business_sign_up_prompt_or_modal(): void
    {
        $this->actingAs($this->customer)->get('/')->assertOk()
            ->assertDontSee('List Your Spa')
            ->assertDontSee('businessInfoModal', false)
            ->assertDontSee('openBusinessInfo', false);
    }

    public function test_customer_nav_opens_modals_in_place_on_the_landing_page(): void
    {
        $this->actingAs($this->customer)->get('/')->assertOk()
            ->assertSee('onclick="openAppointmentsModal()"', false)
            ->assertSee('onclick="openScheduleModal()"', false)
            ->assertSee('id="myAppointmentsBadge"', false)
            ->assertSee('id="logoutModal"', false)
            ->assertDontSee('?open=', false);
    }

    // ── Social icons (config/levictas.php) ───────────────────────────────────

    public function test_social_icons_are_hidden_when_no_url_is_set(): void
    {
        $footer = $this->footer($this->get('/')->getContent());

        foreach (['fa-linkedin-in', 'fa-x-twitter', 'fa-github', 'fa-facebook-f', 'fa-instagram'] as $icon) {
            $this->assertStringNotContainsString($icon, $footer);
        }
        $this->assertStringNotContainsString('href="#"', substr($footer, 0, strpos($footer, 'Company')));
    }

    public function test_only_social_icons_with_a_url_are_rendered(): void
    {
        Config::set('levictas.social', [
            'linkedin' => 'https://www.linkedin.com/company/levictas-test',
            'x'        => '',
            'github'   => 'https://github.com/levictas-test',
        ]);

        $footer = $this->footer($this->get('/')->getContent());

        $this->assertStringContainsString('href="https://www.linkedin.com/company/levictas-test" target="_blank" rel="noopener noreferrer"', $footer);
        $this->assertStringContainsString('aria-label="Levictas on LinkedIn"', $footer);
        $this->assertStringContainsString('fa-linkedin-in', $footer);
        $this->assertStringContainsString('href="https://github.com/levictas-test" target="_blank" rel="noopener noreferrer"', $footer);
        $this->assertStringContainsString('aria-label="Levictas on GitHub"', $footer);
        $this->assertStringNotContainsString('fa-x-twitter', $footer);
    }

    // ── Public layout (layouts/public.blade.php) ─────────────────────────────

    public function test_public_layout_for_a_guest(): void
    {
        $html = $this->publicPage();

        $this->assertStringContainsString('<title>Test page | Levictas</title>', $html);
        $this->assertStringContainsString('<p>Public body</p>', $html);
        $this->assertStringContainsString('id="topNav"', $html);
        $this->assertStringContainsString('Join as a Partner', $html);
        $this->assertStringContainsString('>Register</a>', $this->footer($html));
        $this->assertStringNotContainsString('id="logoutModal"', $html);
    }

    public function test_public_layout_links_customer_actions_back_to_the_landing_page(): void
    {
        $this->actingAs($this->customer);
        $html = $this->publicPage();
        $footer = $this->footer($html);

        foreach (['appointments', 'schedule', 'profile'] as $modal) {
            $this->assertStringContainsString('href="' . url('/') . '?open=' . $modal . '"', $html);
        }
        $this->assertStringContainsString('href="' . url('/') . '?open=appointments"', $footer);
        $this->assertStringContainsString('href="' . url('/') . '?open=profile"', $footer);
        $this->assertStringContainsString($this->bookNowHref(), $footer);

        // The modals and the badge only exist on /.
        $this->assertStringNotContainsString('openAppointmentsModal()', $html);
        $this->assertStringNotContainsString('myAppointmentsBadge', $html);
        // Logout still works here.
        $this->assertStringContainsString('id="logoutModal"', $html);
        $this->assertStringContainsString('onclick="closeProfileDropdown(); openLogoutModal();"', $html);
    }

    public function test_public_layout_for_an_unverified_customer(): void
    {
        $this->customer->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->customer->fresh());

        $html = $this->publicPage();

        $this->assertStringContainsString('?open=appointments', $html);
        $this->assertStringNotContainsString('Join as a Partner', $html);
    }

    public function test_public_layout_gives_business_users_their_dashboard_and_logout(): void
    {
        foreach (['owner', 'manager.a', 'reception.a', 'therapist.a1'] as $key) {
            $user = $this->user($key);
            $this->actingAs($user);
            $html = $this->publicPage();
            $footer = $this->footer($html);

            $home = $user->hasRole('owner') ? route('dashboard') : Sidebar::landingUrl($user);

            $this->assertStringContainsString('Go to Dashboard', $html, $key);
            $this->assertStringContainsString('href="' . $home . '"', $footer, $key);
            $this->assertStringContainsString('id="logoutModal"', $html, $key);
            $this->assertStringNotContainsString('Join as a Partner', $html, $key);
            $this->assertStringNotContainsString('>Login</a>', $footer, $key);
            $this->assertStringNotContainsString('My Appointments', $html, $key);
        }
    }

    public function test_public_layout_sends_an_admin_to_the_admin_dashboard(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->assertStringContainsString(
            'href="' . route('admin.dashboard') . '">Go to Dashboard</a>',
            $this->footer($this->publicPage())
        );
    }
}
