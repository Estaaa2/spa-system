<?php

namespace Tests\Feature\Landing;

use App\Models\User;
use App\Services\Navigation\Sidebar;
use Tests\Fixtures\HomeVisitTestSeeder;
use Tests\Fixtures\IdentityVerificationTestSeeder;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Landing footer Phase 1.1: LandingPageRedirect sends every business role
 * to the page login sends them to; only guests and verified customers see /.
 */
class LandingRedirectPhase11Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);
        $this->seed(HomeVisitTestSeeder::class);
    }

    private function user(string $key): User
    {
        return User::where('email', "{$key}@levictas.test")->firstOrFail();
    }

    public function test_guests_and_verified_customers_see_the_landing_page(): void
    {
        $this->get('/')->assertOk();

        $customer = User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();
        $this->actingAs($customer)->get('/')->assertOk();
    }

    public function test_unverified_customers_are_sent_to_verify_their_email(): void
    {
        $customer = User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();
        $customer->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($customer)->get('/')->assertRedirect(route('verification.notice'));
    }

    public function test_owner_is_sent_to_the_dashboard(): void
    {
        $this->actingAs($this->user('owner'))->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_every_other_business_role_is_sent_to_its_first_allowed_page(): void
    {
        // therapist, receptionist, HR and finance used to fall through to /.
        foreach (['manager.a', 'therapist.a1', 'reception.a', 'hr.a', 'finance.head'] as $key) {
            $user = $this->user($key);
            $target = Sidebar::landingUrl($user);

            $this->actingAs($user)->get('/')->assertRedirect($target);

            // ...and that page actually lets them in.
            $status = $this->actingAs($user)->get($target)->status();
            $this->assertNotContains($status, [403, 500], "{$key} → {$target} answered {$status}");

            auth()->logout();
        }
    }

    public function test_admin_is_sent_to_the_admin_dashboard_not_a_403(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $this->actingAs($admin)->get('/')->assertRedirect(route('admin.dashboard'));
    }
}
