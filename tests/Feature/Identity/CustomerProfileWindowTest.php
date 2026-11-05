<?php

namespace Tests\Feature\Identity;

use App\Models\Booking;
use App\Models\User;
use Database\Seeders\HomeVisitTestSeeder;
use Database\Seeders\IdentityVerificationTestSeeder;
use Database\Seeders\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * UI rework Phase 5: the customer profile window saves one row at a time, and
 * the DSS revenue card counts completed bookings only.
 */
class CustomerProfileWindowTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-30 09:20:00');
        Storage::fake('local');
        Mail::fake();
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);
        $this->seed(HomeVisitTestSeeder::class);

        $this->customer = User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();
        $this->customer->forceFill(['latitude' => 14.4297, 'longitude' => 120.9367])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function save(array $data)
    {
        return $this->actingAs($this->customer)->patchJson(route('customer.profile.update'), $data);
    }

    public function test_saving_the_phone_leaves_name_and_location_alone(): void
    {
        $before = $this->customer->only(['first_name', 'last_name', 'address', 'latitude', 'longitude']);

        $this->save(['section' => 'phone', 'phone' => '09171234567'])
            ->assertOk()->assertJsonPath('user.phone', '09171234567');

        $after = $this->customer->fresh();
        $this->assertSame('09171234567', $after->phone);
        $this->assertEquals($before, $after->only(['first_name', 'last_name', 'address', 'latitude', 'longitude']));
    }

    public function test_saving_the_name_validates_only_the_name(): void
    {
        $this->save(['section' => 'name', 'first_name' => '', 'last_name' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors('first_name')
            ->assertJsonMissingValidationErrors('phone');

        $this->save(['section' => 'name', 'first_name' => 'Cara', 'middle_name' => '', 'last_name' => 'Cruz'])
            ->assertOk()->assertJsonPath('user.last_name', 'Cruz');
        $this->assertSame('09170000001', $this->customer->fresh()->phone);
    }

    public function test_location_must_be_in_cavite_and_needs_both_coordinates(): void
    {
        $this->save(['section' => 'location', 'address' => 'Makati', 'latitude' => 14.5547, 'longitude' => 121.0244])
            ->assertStatus(422)->assertJsonValidationErrors('latitude');

        $this->save(['section' => 'location', 'address' => 'Somewhere', 'latitude' => 14.33])
            ->assertStatus(422)->assertJsonValidationErrors('longitude');

        $this->save(['section' => 'location', 'address' => 'Dasmariñas, Cavite', 'latitude' => 14.3294, 'longitude' => 120.9367])
            ->assertOk()->assertJsonPath('user.address', 'Dasmariñas, Cavite');
        $this->assertEqualsWithDelta(14.3294, (float) $this->customer->fresh()->latitude, 0.0001);
    }

    public function test_an_unknown_section_is_refused(): void
    {
        $this->save(['section' => 'email', 'email' => 'x@example.com'])->assertStatus(422);
        $this->assertSame(HomeVisitTestSeeder::CUSTOMER_EMAIL, $this->customer->fresh()->email);
    }

    public function test_a_form_post_without_a_section_still_saves_everything(): void
    {
        $this->actingAs($this->customer)->from('/')
            ->patch(route('customer.profile.update'), [
                'first_name' => 'Cara', 'last_name' => 'Customer', 'phone' => '09170000009',
                'address' => 'Imus, Cavite', 'latitude' => 14.4297, 'longitude' => 120.9367,
            ])->assertRedirect('/')->assertSessionHasNoErrors();

        $this->assertSame('09170000009', $this->customer->fresh()->phone);
    }

    public function test_the_landing_page_prefills_the_booking_address_from_the_profile(): void
    {
        $html = $this->actingAs($this->customer)->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-default-address="Sample street, Imus, Cavite"', $html);
    }

    public function test_dss_revenue_counts_completed_bookings_only(): void
    {
        $owner = User::where('email', 'owner@levictas.test')->firstOrFail();
        $base  = Booking::where('customer_user_id', $this->customer->id)->firstOrFail();

        // In the period: one completed (₱1,000) and one cancelled (₱5,000).
        foreach ([['completed', 1000], ['cancelled', 5000]] as [$status, $amount]) {
            $copy = $base->replicate();
            $copy->forceFill([
                // Fully paid, like every checkout since the booking lifecycle fix:
                // DSS revenue counts the money collected (Phase 4, D9/Q1-A).
                'status' => $status, 'total_amount' => $amount, 'amount_paid' => $amount, 'balance_amount' => 0,
                'appointment_date' => '2026-10-20',
                'start_time' => '10:00:00', 'end_time' => '11:00:00', 'arrival_code' => null,
            ])->saveQuietly();
        }
        // The seeded upcoming bookings are reserved, so they don't count either.

        // The page groups by MySQL's HOUR() and DAYOFWEEK(); give SQLite the same functions for this test.
        $pdo = \DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('HOUR', fn ($t) => (int) substr((string) $t, 0, 2), 1);
        $pdo->sqliteCreateFunction('DAYOFWEEK', fn ($d) => (int) date('w', strtotime((string) $d)) + 1, 1);   // MySQL: Sunday = 1

        $response = $this->actingAs($owner)->get(route('decision-support.index', ['from' => '2026-10-01', 'to' => '2026-10-30']));
        $response->assertOk();
        $this->assertEquals(1000, $response->viewData('kpis')['revenue']);
    }

    // ── Name: once every 30 days, 24 hours to fix a typo ─────────────────────

    private function rename(string $first, string $last = 'Customer (test)')
    {
        return $this->save(['section' => 'name', 'first_name' => $first, 'last_name' => $last]);
    }

    public function test_the_first_name_change_is_allowed_and_starts_the_clock(): void
    {
        $this->assertNull($this->customer->name_changed_at);

        $this->rename('Carla')->assertOk()
            ->assertJsonPath('user.name_locked_until', null)
            ->assertJsonPath('user.name_correction_until', now()->addHours(24)->toIso8601String());

        $this->assertTrue($this->customer->fresh()->name_changed_at->eq(now()));
    }

    public function test_saving_an_unchanged_name_does_not_start_the_clock(): void
    {
        $this->save(['section' => 'name', 'first_name' => ' Cara ', 'last_name' => 'Customer (test)'])->assertOk();
        $this->assertNull($this->customer->fresh()->name_changed_at);
    }

    public function test_a_typo_can_be_fixed_within_24_hours_without_restarting_the_clock(): void
    {
        $this->rename('Carlaa')->assertOk();

        Carbon::setTestNow(now()->addHours(5));
        $this->rename('Carla')->assertOk();

        // Still counted from the first change, not the correction.
        $this->assertTrue($this->customer->fresh()->name_changed_at->eq(Carbon::parse('2026-10-30 09:20:00')));
    }

    public function test_after_24_hours_another_change_waits_until_30_days_have_passed(): void
    {
        $this->rename('Carla')->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-11-05 10:00:00'));
        $this->rename('Karla')->assertStatus(422)
            ->assertJsonPath('errors.first_name.0', 'You can change your name once every 30 days. You can change it again on Nov 29, 2026.');
        $this->assertSame('Carla', $this->customer->fresh()->first_name);

        // Re-saving the same name is not a change.
        $this->rename('Carla')->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-11-29 09:21:00'));
        $this->rename('Karla')->assertOk();
        $this->assertSame('Karla', $this->customer->fresh()->first_name);
    }

    public function test_phone_and_location_saves_ignore_the_name_rule(): void
    {
        $this->rename('Carla')->assertOk();
        Carbon::setTestNow(now()->addDays(3));

        $this->save(['section' => 'phone', 'phone' => '09179998888'])->assertOk();
        $this->assertSame('Carla', $this->customer->fresh()->first_name);
    }

    public function test_the_app_routes_follow_the_same_rule(): void
    {
        $this->rename('Carla')->assertOk();
        Carbon::setTestNow(now()->addDays(3));
        \Laravel\Sanctum\Sanctum::actingAs($this->customer->fresh());

        $body = ['first_name' => 'Karla', 'last_name' => 'Customer (test)'];
        $this->patchJson('/api/profile', $body)->assertStatus(422)->assertJsonValidationErrors('first_name');
        $this->putJson('/api/me', $body)->assertStatus(422)->assertJsonValidationErrors('first_name');
        $this->assertSame('Carla', $this->customer->fresh()->first_name);

        // The app can show the date too.
        $this->getJson('/api/me')->assertOk()->assertJsonPath('name_locked_until', Carbon::parse('2026-11-29 09:20:00')->toIso8601String());
    }

    public function test_spas_near_you_include_the_branch_services_for_a_logged_in_customer(): void
    {
        // The distance query uses SQL maths functions; give SQLite the same ones for this test.
        $pdo = \DB::connection()->getPdo();
        foreach (['ACOS' => 'acos', 'COS' => 'cos', 'SIN' => 'sin', 'RADIANS' => 'deg2rad'] as $sql => $php) {
            $pdo->sqliteCreateFunction($sql, fn ($v) => $php((float) $v), 1);
        }

        $spas = $this->actingAs($this->customer)
            ->getJson('/web-api/spas/nearby?lat=14.4297&lng=120.9367')
            ->assertOk()->json();

        $this->assertNotEmpty($spas, 'The test branch should be within 5 km.');
        $this->assertNotEmpty($spas[0]['treatments'], 'Nearby spas lost their services for logged-in customers.');
        $this->assertArrayHasKey('rating_avg', $spas[0]);
        $this->assertArrayHasKey('has_promo', $spas[0]);
        $this->assertArrayHasKey('distance_km', $spas[0]);
    }

    public function test_the_suffix_is_part_of_the_name(): void
    {
        $this->save(['section' => 'name', 'first_name' => 'Cara', 'last_name' => 'Customer (test)', 'suffix' => 'Jr.'])
            ->assertOk()->assertJsonPath('user.suffix', 'Jr.');
        $this->assertSame('Jr.', $this->customer->fresh()->suffix);
        $this->assertNotNull($this->customer->fresh()->name_changed_at, 'Adding a suffix is a name change.');

        // The app's older endpoint doesn't send a suffix: an unchanged name must not count as a change.
        $this->customer->forceFill(['name_changed_at' => null])->save();
        \Laravel\Sanctum\Sanctum::actingAs($this->customer->fresh());
        $this->putJson('/api/me', ['first_name' => 'Cara', 'last_name' => 'Customer (test)'])->assertOk();
        $this->assertNull($this->customer->fresh()->name_changed_at);
        $this->assertSame('Jr.', $this->customer->fresh()->suffix);
    }
}
