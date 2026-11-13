<?php

namespace Tests\Feature\DemoSafety;

use App\Models\Branch;
use App\Models\OnlineReservationPayment;
use App\Models\OperatingHours;
use App\Models\Subscription;
use App\Models\Treatment;
use App\Models\User;
use Database\Factories\PayrollUserFactory;
use Tests\Fixtures\IdentityVerificationTestSeeder;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Demo safety Phase 1: when PayMongo can't be reached, every payment path
 * fails politely (no 500, no debug page) and keeps its old behaviour.
 *
 * Uses the same test-account fixtures (tests/Fixtures) as the rest of the suite.
 */
class PaymentGatewayFailureTest extends TestCase
{
    use RefreshDatabase;

    private const DNS_FAIL = 'cURL error 28: Resolving timed out after 5000 milliseconds';

    private Branch $branch;
    private Treatment $treatment;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-29 08:00:00');
        Sleep::fake();
        Storage::fake('local');
        $this->withoutVite();
        config(['app.debug' => false, 'services.paymongo.mode' => 'test', 'services.paymongo.test_secret_key' => 'sk_test_x']);

        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);

        $this->branch = Branch::where('name', 'Test Branch A')->firstOrFail();
        $this->treatment = Treatment::withoutGlobalScopes()->where('branch_id', $this->branch->id)->firstOrFail();

        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
            OperatingHours::create(['branch_id' => $this->branch->id, 'day_of_week' => $day, 'opening_time' => '09:00:00', 'closing_time' => '21:00:00', 'is_closed' => false]);
        }

        $this->customer = PayrollUserFactory::new()->create(['first_name' => 'Cara', 'last_name' => 'Customer', 'email' => 'cara@levictas.test']);
        $this->customer->assignRole('customer');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payload(): array
    {
        return [
            'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id,
            'customer_phone' => '09171234567', 'treatment' => 'treatment_' . $this->treatment->id,
            'service_type' => 'in_branch', 'appointment_date' => '2026-10-31', 'start_time' => '10:00',
            'terms_agreed' => '1',
        ];
    }

    private function dnsFailsTwice(): void
    {
        Http::fake(['api.paymongo.com/*' => Http::sequence()
            ->pushFailedConnection(self::DNS_FAIL)
            ->pushFailedConnection(self::DNS_FAIL)]);
    }

    // ── Booking checkout ─────────────────────────────────────────────────

    public function test_web_booking_checkout_fails_politely_and_marks_the_reservation_failed(): void
    {
        $this->dnsFailsTwice();

        $this->actingAs($this->customer)
            ->from('/')
            ->post(route('bookings.online.checkout'), $this->payload())
            ->assertRedirect('/')
            ->assertSessionHas('booking_error', fn ($m) => str_contains($m, 'Unable to connect to the payment gateway'));

        $reservation = OnlineReservationPayment::latest('id')->firstOrFail();
        $this->assertSame('failed', $reservation->payment_status);
        $this->assertSame('failed', $reservation->reservation_status);
        Http::assertSentCount(2); // one retry, because nothing was sent
    }

    public function test_api_booking_checkout_fails_with_json_422(): void
    {
        $this->dnsFailsTwice();
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/bookings/online/checkout', $this->payload())
            ->assertStatus(422)
            ->assertJson(['message' => 'Unable to connect to the payment gateway. Please check your connection and try again.']);

        $this->assertSame('failed', OnlineReservationPayment::latest('id')->value('reservation_status'));
    }

    public function test_booking_checkout_succeeds_after_one_dns_hiccup(): void
    {
        Http::fake(['api.paymongo.com/*' => Http::sequence()
            ->pushFailedConnection(self::DNS_FAIL)
            ->push(['data' => ['id' => 'cs_test_ok', 'attributes' => ['checkout_url' => 'https://pay.example/cs']]], 200)]);
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/bookings/online/checkout', $this->payload())
            ->assertOk()
            ->assertJson(['checkout_url' => 'https://pay.example/cs']);

        $this->assertSame('cs_test_ok', OnlineReservationPayment::latest('id')->value('paymongo_checkout_session_id'));
    }

    public function test_a_timeout_after_sending_is_not_retried_so_no_duplicate_session(): void
    {
        Http::fake(['api.paymongo.com/*' => Http::sequence()
            ->pushFailedConnection('cURL error 28: Operation timed out after 15000 milliseconds with 0 bytes received')
            ->push(['data' => ['id' => 'cs_duplicate', 'attributes' => ['checkout_url' => 'https://pay.example/dup']]], 200)]);
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/bookings/online/checkout', $this->payload())->assertStatus(422);

        Http::assertSentCount(1);
    }

    // ── Subscription checkout ────────────────────────────────────────────

    public function test_subscription_checkout_fails_politely_and_cancels_the_pending_subscription(): void
    {
        $this->dnsFailsTwice();
        $owner = User::where('email', 'owner@levictas.test')->firstOrFail();

        $before = Subscription::count();

        $response = $this->actingAs($owner)
            ->from(route('owner.subscription.index'))
            ->post(route('owner.subscription.checkout'), ['plan' => 'premium', 'billing_cycle' => 'monthly']);

        $response->assertRedirect(route('owner.subscription.index'));

        $this->assertSame($before + 1, Subscription::count(), 'The checkout should reach PayMongo.');

        $response->assertSessionHasErrors(['payment' => 'Unable to reach the payment gateway. Please check your connection and try again.']);

        $subscription = Subscription::latest('id')->firstOrFail();
        $this->assertSame('cancelled', $subscription->status);
        $this->assertSame('failed', $subscription->payment_status);
    }

    // ── Flutter payment-success return (F4, Q5 A) ────────────────────────

    public function test_flutter_payment_success_no_longer_500s_when_paymongo_is_unreachable(): void
    {
        config(['services.flutter.web_url' => 'https://app.levictas.example']);
        $this->dnsFailsTwice();

        $r = new OnlineReservationPayment();
        $r->forceFill([
            'user_id' => $this->customer->id, 'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id,
            'customer_name' => 'Cara Customer', 'customer_email' => 'cara@levictas.test', 'customer_phone' => '09171234567',
            'bookable_id' => $this->treatment->id, 'bookable_type' => 'treatment', 'bookable_name' => $this->treatment->name,
            'full_amount' => 1200, 'downpayment_amount' => 240, 'payment_option' => 'downpayment', 'amount_charged' => 240,
            'service_type' => 'in_branch', 'appointment_date' => '2026-10-31', 'start_time' => '10:00:00',
            'payment_status' => 'pending', 'reservation_status' => 'awaiting_payment', 'paymongo_checkout_session_id' => 'cs_test_flutter',
        ])->save();

        $this->get(route('flutter.payment.success', ['reservation' => $r->id]))
            ->assertRedirect('https://app.levictas.example/#/payment-success?reservation=' . $r->id);

        // Unchanged: the webhook still finalizes it.
        $this->assertSame('pending', $r->fresh()->payment_status);
        $this->assertNull($r->fresh()->booking_id);
    }
}
