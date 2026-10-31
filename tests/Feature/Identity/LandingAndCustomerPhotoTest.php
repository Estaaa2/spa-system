<?php

namespace Tests\Feature\Identity;

use App\Mail\BookingTherapistMail;
use App\Models\Booking;
use App\Models\OnlineReservationPayment;
use App\Models\Spa;
use App\Models\User;
use Database\Seeders\HomeVisitTestSeeder;
use Database\Seeders\IdentityVerificationTestSeeder;
use Database\Seeders\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 7 — customer avatar (D13, Q54/Q55), owner info on the landing page
 * (D11, Q53), plus the Phase 6 follow-ups Q51 (mobile login for staff who
 * perform treatments) and Q52 (webhook double-booking lock).
 */
class LandingAndCustomerPhotoTest extends TestCase
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

        $this->customer = $this->user(HomeVisitTestSeeder::CUSTOMER_EMAIL);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('me.jpg', 500, 500);
    }

    // ── Customer avatar (D13) ────────────────────────────────────────────────

    public function test_profile_window_offers_an_optional_photo(): void
    {
        // welcome.blade.php declares a PHP function, so it can render only once per test.
        $this->actingAs($this->customer)->get('/')->assertOk()->assertSee('Add photo')
            ->assertSee("Optional. Spas and therapists don't see it.", false);
    }

    public function test_customer_adds_a_photo_from_the_profile_window(): void
    {
        $this->actingAs($this->customer)->from('/')
            ->post(route('profile.photo.update'), ['photo' => $this->photo()])
            ->assertRedirect('/')
            ->assertSessionHas('open_profile_modal', true)
            ->assertSessionHas('success', 'Profile photo updated.');

        $fresh = $this->customer->fresh();
        $this->assertNotNull($fresh->profile_photo_path);
        $this->assertNull($fresh->identity_verified_at);   // never a trust signal

        $page = $this->actingAs($fresh)->get('/')->assertOk();
        $page->assertSee($fresh->profilePhotoUrl(), false)->assertSee('Change photo')->assertSee('Remove');

        $this->actingAs($fresh)->get($fresh->profilePhotoUrl())->assertOk();
    }

    public function test_customer_removes_the_photo(): void
    {
        $this->actingAs($this->customer)->from('/')->post(route('profile.photo.update'), ['photo' => $this->photo()]);

        $this->actingAs($this->customer)->from('/')->delete(route('profile.photo.destroy'))
            ->assertRedirect('/')->assertSessionHas('success', 'Profile photo removed.');

        $this->assertNull($this->customer->fresh()->profile_photo_path);
    }

    public function test_a_bad_file_shows_the_error_in_the_profile_window(): void
    {
        $this->actingAs($this->customer)->from('/')
            ->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf')])
            ->assertRedirect('/')
            ->assertSessionHasErrorsIn('photo', 'photo');

        $this->assertNull($this->customer->fresh()->profile_photo_path);
    }

    public function test_therapists_and_spa_staff_cannot_see_a_customer_photo(): void
    {
        $this->actingAs($this->customer)->from('/')->post(route('profile.photo.update'), ['photo' => $this->photo()]);
        $url = $this->customer->fresh()->profilePhotoUrl();

        // Vera has bookings with this customer and still can't (Q54).
        $this->actingAs($this->user('idv.vera@levictas.test'))->get($url)->assertNotFound();
        $this->actingAs($this->user('manager.a@levictas.test'))->get($url)->assertNotFound();
        $this->actingAs($this->user('idv.vera@levictas.test'))->get(route('dashboard'))->assertOk()->assertDontSee($url, false);
    }

    public function test_staff_photo_upload_still_goes_back_to_the_profile_page(): void
    {
        $this->actingAs($this->user('idv.paolo@levictas.test'))->from(route('profile.edit'))
            ->post(route('profile.photo.update'), ['photo' => $this->photo()])
            ->assertRedirect(route('profile.edit').'#photo');
    }

    public function test_customers_can_use_the_app_photo_endpoint(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->customer);

        $this->post('/api/me/photo', ['photo' => $this->photo()])->assertOk()
            ->assertJsonPath('identity.can_verify', false)
            ->assertJsonPath('user.identity_verified', false);
    }

    // ── Owner info (D11, Q53) ────────────────────────────────────────────────

    public function test_spa_payload_has_owner_name_and_business_verified(): void
    {
        $spa = Spa::where('name', PayrollTestAccountsSeeder::SPA_NAME)->firstOrFail();
        $spa->forceFill(['verification_status' => 'verified'])->saveQuietly();
        $spa->load('owner');

        $this->assertSame(trim($spa->owner->first_name.' '.$spa->owner->last_name), $spa->ownerDisplayName());
        $this->assertTrue($spa->isBusinessVerified());

        $spa->forceFill(['verification_status' => 'pending'])->saveQuietly();
        $this->assertFalse($spa->fresh()->isBusinessVerified());
    }

    public function test_landing_modal_has_the_owner_row_and_no_owner_on_cards(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="spaModalOwnerRow"', $html);
        $this->assertStringContainsString('Business verified', $html);
        // Cards carry the data only in data-spa (for the modal); there is no
        // visible "Owned by" line on the cards (Q53).
        $this->assertSame(1, substr_count($html, 'Owned by'));
    }

    // ── Q51: mobile login for staff who perform treatments ───────────────────

    public function test_staff_who_perform_treatments_can_log_in_to_the_app(): void
    {
        $login = fn (string $email) => $this->postJson('/api/login', [
            'email' => $email, 'password' => PayrollTestAccountsSeeder::PASSWORD, 'platform' => 'mobile',
        ]);

        $login('idv.rhea@levictas.test')->assertOk()
            ->assertJsonPath('user.role', 'receptionist')
            ->assertJsonPath('user.app_role', 'therapist');

        $login('reception.a@levictas.test')->assertForbidden()->assertJsonPath('unauthorized_role', true);

        $login('idv.vera@levictas.test')->assertOk()->assertJsonPath('user.app_role', 'therapist');
        $login(HomeVisitTestSeeder::CUSTOMER_EMAIL)->assertOk()->assertJsonPath('user.app_role', 'customer');
    }

    public function test_locked_spa_also_blocks_staff_who_perform_treatments(): void
    {
        $rhea = $this->user('idv.rhea@levictas.test');
        $rhea->spa->forceFill(['verification_status' => 'pending'])->saveQuietly();

        $this->postJson('/api/login', [
            'email' => $rhea->email, 'password' => PayrollTestAccountsSeeder::PASSWORD, 'platform' => 'mobile',
        ])->assertStatus(423)->assertJsonPath('reason', 'spa_unverified');
    }

    // ── Q52: webhook after the app already booked ────────────────────────────

    public function test_webhook_does_not_book_twice_when_the_app_already_did(): void
    {
        $a = Booking::where('customer_user_id', $this->customer->id)->orderBy('appointment_date')->orderBy('start_time')->firstOrFail();

        $r = new OnlineReservationPayment();
        $r->forceFill([
            'user_id' => $this->customer->id, 'spa_id' => $a->spa_id, 'branch_id' => $a->branch_id,
            'customer_name' => 'Cara Customer (test)', 'customer_email' => HomeVisitTestSeeder::CUSTOMER_EMAIL,
            'customer_phone' => '09170000001', 'customer_address' => 'Sample street, Imus, Cavite',
            'bookable_id' => (int) str_replace('treatment_', '', $a->treatment), 'bookable_type' => 'treatment',
            'bookable_name' => 'Swedish Massage (test)', 'full_amount' => 1200, 'downpayment_amount' => 240,
            'payment_option' => 'downpayment', 'amount_charged' => 240, 'service_type' => 'in_branch',
            'appointment_date' => today()->addDays(2)->toDateString(), 'start_time' => '15:00:00',
            // The race: the app's fallback set booking_id, but the webhook read the
            // row before it was marked paid.
            'payment_status' => 'pending', 'booking_id' => $a->id,
            'reservation_status' => 'awaiting_payment', 'paymongo_checkout_session_id' => 'cs_race',
        ])->save();

        config(['services.paymongo.test_webhook_secret' => 'whsec_test']);
        $payload = json_encode(['data' => ['attributes' => [
            'type' => 'checkout_session.payment.paid', 'livemode' => false,
            'data' => ['id' => 'cs_race', 'attributes' => ['payments' => [['id' => 'pay_123', 'attributes' => ['reference_number' => 'REF1']]]]],
        ]]]);
        $t = time();
        $sig = 't='.$t.',te='.hash_hmac('sha256', $t.'.'.$payload, 'whsec_test').',li=';

        $before = Booking::count();
        $this->call('POST', route('webhooks.paymongo'), [], [], [], [
            'HTTP_PAYMONGO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $this->assertSame($before, Booking::count());
        $r->refresh();
        $this->assertSame('paid', $r->payment_status);
        $this->assertSame('pay_123', $r->paymongo_payment_id);
        Mail::assertNotSent(BookingTherapistMail::class);
    }
}
