<?php

namespace Tests\Feature\Identity;

use App\Models\Booking;
use App\Models\IdentityVerification;
use App\Models\OnlineReservationPayment;
use App\Models\User;
use App\Support\Identity\ConsentNotice;
use Database\Seeders\HomeVisitTestSeeder;
use Database\Seeders\IdentityVerificationTestSeeder;
use Database\Seeders\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 6 — mobile API parity (Q46–Q50, Q40). Same seeders as Phase 5:
 * booking A (today, Vera), B (tomorrow, Vera), C (tomorrow, no therapist).
 */
class ApiParityTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $vera;

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
        $this->vera = $this->user('idv.vera@levictas.test');
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

    private function booking(string $which): Booking
    {
        $rows = Booking::where('customer_user_id', $this->customer->id)->orderBy('appointment_date')->orderBy('start_time')->get();

        return ['A' => $rows[0], 'B' => $rows[1], 'C' => $rows[2]][$which];
    }

    private function as(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('p.jpg', 600, 600);
    }

    // ── Identity screen ──────────────────────────────────────────────────────

    public function test_identity_state_for_staff_and_customers(): void
    {
        $this->as($this->vera)->getJson('/api/me/identity')->assertOk()
            ->assertJsonPath('identity.can_verify', true)
            ->assertJsonPath('identity.state', 'approved')
            ->assertJsonPath('identity.photo_locked', true)
            ->assertJsonPath('identity.consent.version', config('identity.consent_version'))
            ->assertJsonCount(6, 'identity.id_types')
            ->assertJsonPath('user.identity_verified', true)
            ->assertJsonPath('user.is_treatment_provider', true);

        $this->as($this->user('idv.rey@levictas.test'))->getJson('/api/me/identity')->assertOk()
            ->assertJsonPath('identity.state', 'rejected')
            ->assertJsonPath('identity.latest.rejection_reason', fn ($r) => filled($r));

        $this->as($this->customer)->getJson('/api/me/identity')->assertOk()
            ->assertJsonPath('identity.can_verify', false)
            ->assertJsonPath('identity.state', null);
    }

    public function test_me_includes_the_api_photo_url(): void
    {
        $url = $this->as($this->vera)->getJson('/api/me')->assertOk()->json('profile_photo_url');

        $this->assertStringContainsString('/api/profile-photos/'.$this->vera->id, $url);
        $this->as($this->vera)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    // ── Photo ────────────────────────────────────────────────────────────────

    public function test_photo_upload_and_its_effect_on_verification(): void
    {
        $paolo = $this->user('idv.paolo@levictas.test');
        $this->as($paolo)->post('/api/me/photo', ['photo' => $this->photo()])->assertOk()
            ->assertJsonPath('message', 'Profile photo updated.')
            ->assertJsonPath('user.profile_photo_url', fn ($u) => str_contains($u, '/api/profile-photos/'));

        // A verified person's new photo ends the verification (same as web).
        $this->as($this->vera)->post('/api/me/photo', ['photo' => $this->photo()])->assertOk()
            ->assertJsonPath('user.identity_verified', false)
            ->assertJsonPath('identity.state', 'needs_reverification');

        $this->as($paolo)->post('/api/me/photo', ['photo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertStatus(422)->assertJsonValidationErrors('photo');
    }

    public function test_photo_cannot_be_removed_while_verified(): void
    {
        $this->as($this->vera)->deleteJson('/api/me/photo')->assertStatus(422);
        $this->assertNotNull($this->vera->fresh()->profile_photo_path);
    }

    // ── Submit / withdraw ────────────────────────────────────────────────────

    public function test_submit_needs_consent_and_records_the_api_channel(): void
    {
        $paolo = $this->user('idv.paolo@levictas.test');
        $files = ['id_type' => 'philid', 'id_image' => $this->photo(), 'selfie' => $this->photo()];

        $this->as($paolo)->post('/api/me/identity-verification', $files)
            ->assertStatus(422)->assertJsonValidationErrors('consent');

        $this->as($paolo)->post('/api/me/identity-verification', $files + ['consent' => '1'])
            ->assertCreated()->assertJsonPath('identity.state', 'pending');

        $row = IdentityVerification::where('user_id', $paolo->id)->latest('id')->firstOrFail();
        $this->assertSame('api', $row->consent_channel);
        $this->assertSame(config('identity.consent_version'), $row->consent_version);

        $this->as($paolo)->deleteJson('/api/me/identity-verification')->assertOk()
            ->assertJsonPath('identity.state', 'withdrawn');
        $this->as($paolo)->deleteJson('/api/me/identity-verification')->assertStatus(422);
    }

    public function test_customers_and_non_providers_cannot_submit(): void
    {
        $files = ['id_type' => 'philid', 'id_image' => $this->photo(), 'selfie' => $this->photo(), 'consent' => '1'];

        $this->as($this->customer)->post('/api/me/identity-verification', $files)->assertForbidden();
        $this->as($this->user('reception.a@levictas.test'))->post('/api/me/identity-verification', $files)->assertForbidden();
    }

    public function test_web_and_app_show_the_same_consent_notice(): void
    {
        $page = $this->actingAs($this->user('idv.paolo@levictas.test'))->get(route('profile.edit'))->assertOk();

        foreach (ConsentNotice::sections() as $section) {
            $page->assertSee($section['title'])->assertSee($section['body']);
        }
        $page->assertSee(ConsentNotice::AGREEMENT);
    }

    // ── Photo access ─────────────────────────────────────────────────────────

    public function test_photo_route_follows_the_same_rule_as_the_web(): void
    {
        $url = '/api/profile-photos/'.$this->vera->id;

        $this->getJson($url)->assertUnauthorized();
        $this->as($this->customer)->get($url)->assertOk();   // has an upcoming booking with her

        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $stranger->assignRole('customer');
        $this->as($stranger)->get($url)->assertNotFound();
    }

    public function test_customer_appointments_give_the_app_the_api_photo_url_and_code(): void
    {
        $rows = collect($this->as($this->customer)->getJson('/api/my-appointments')->assertOk()->json());
        $a = $rows->firstWhere('id', $this->booking('A')->id);

        $this->assertStringContainsString('/api/profile-photos/'.$this->vera->id, $a['therapist_card']['photo_url']);
        $this->assertSame($this->booking('A')->arrival_code, $a['arrival_code']);
    }

    // ── Therapist: bookings + arrival ────────────────────────────────────────

    public function test_assigned_bookings_show_arrival_state_but_never_the_code(): void
    {
        $code = '9731';
        $this->booking('A')->forceFill(['arrival_code' => $code])->saveQuietly();

        $res = $this->as($this->vera)->getJson('/api/assigned-bookings')->assertOk();
        $this->assertStringNotContainsString($code, $res->getContent());
        $this->assertStringNotContainsString('arrival_code', $res->getContent());

        $rows = collect($res->json('bookings'));
        $a = $rows->firstWhere('id', $this->booking('A')->id);
        $b = $rows->firstWhere('id', $this->booking('B')->id);

        $this->assertTrue($a['is_home_service']);
        $this->assertTrue($a['customer_email_verified']);
        $this->assertTrue($a['arrival']['can_enter_code']);
        $this->assertSame(5, $a['arrival']['attempts_left']);
        $this->assertFalse($b['arrival']['can_enter_code']);   // tomorrow
    }

    public function test_arrival_code_from_the_app(): void
    {
        $a = $this->booking('A');
        $wrong = $a->arrival_code === '0000' ? '1111' : '0000';

        $this->as($this->vera)->postJson("/api/appointments/{$a->id}/arrival", ['arrival_code' => $wrong])
            ->assertStatus(422)->assertJsonPath('errors.arrival_code.0', fn ($m) => str_contains($m, '4 tries left'));

        $this->as($this->user('idv.paolo@levictas.test'))->postJson("/api/appointments/{$a->id}/arrival", ['arrival_code' => $a->arrival_code])
            ->assertStatus(422);

        $this->as($this->customer)->postJson("/api/appointments/{$a->id}/arrival", ['arrival_code' => $a->arrival_code])
            ->assertForbidden();

        $this->as($this->vera)->postJson("/api/appointments/{$a->id}/arrival", ['arrival_code' => $a->arrival_code])
            ->assertOk()->assertJsonPath('arrival.method', 'code')->assertJsonPath('arrival.can_enter_code', false);

        $this->assertNotNull($a->fresh()->arrival_confirmed_at);
        $this->assertSame('reserved', $a->fresh()->status);
    }

    // ── Q48: flagged staff ───────────────────────────────────────────────────

    public function test_therapist_endpoints_open_to_staff_who_perform_treatments(): void
    {
        $rhea = $this->user('idv.rhea@levictas.test');

        $this->as($rhea)->getJson('/api/assigned-bookings')->assertOk();
        $this->as($rhea)->getJson('/api/therapist/schedule')->assertOk();
        $this->as($rhea)->getJson('/api/therapist/performance')->assertOk();

        $this->as($this->user('reception.a@levictas.test'))->getJson('/api/assigned-bookings')->assertForbidden();
        $this->as($this->customer)->getJson('/api/assigned-bookings')->assertForbidden();
    }

    // ── Q40: payment fallback amounts ────────────────────────────────────────

    private function paidReservation(string $option): OnlineReservationPayment
    {
        $a = $this->booking('A');
        $r = new OnlineReservationPayment();
        $r->forceFill([
            'user_id' => $this->customer->id, 'spa_id' => $a->spa_id, 'branch_id' => $a->branch_id,
            'customer_name' => 'Cara Customer (test)', 'customer_email' => HomeVisitTestSeeder::CUSTOMER_EMAIL,
            'customer_phone' => '09170000001', 'customer_address' => 'Sample street, Imus, Cavite',
            'bookable_id' => (int) str_replace('treatment_', '', $a->treatment), 'bookable_type' => 'treatment',
            'bookable_name' => 'Swedish Massage (test)', 'full_amount' => 1200, 'downpayment_amount' => 240,
            'payment_option' => $option, 'amount_charged' => $option === 'full' ? 1200 : 240, 'service_type' => 'in_branch',
            'appointment_date' => today()->addDays(2)->toDateString(), 'start_time' => '15:00:00', 'payment_status' => 'pending',
            'reservation_status' => 'awaiting_payment', 'paymongo_checkout_session_id' => 'cs_'.$option,
        ])->save();

        return $r;
    }

    public function test_payment_fallback_records_a_full_payment_as_paid(): void
    {
        Http::fake(['api.paymongo.com/*' => Http::response(['data' => ['attributes' => ['status' => 'completed']]], 200)]);

        foreach (['full' => ['paid', 1200.0, 0.0], 'downpayment' => ['partially_paid', 240.0, 960.0]] as $option => [$status, $paid, $balance]) {
            $r = $this->paidReservation($option);
            $this->get('/flutter/payment/success?reservation='.$r->id)->assertRedirect();

            $booking = Booking::findOrFail($r->fresh()->booking_id);
            $this->assertSame($status, $booking->payment_status, $option);
            $this->assertEquals($paid, (float) $booking->amount_paid, $option);
            $this->assertEquals($balance, (float) $booking->balance_amount, $option);
        }
    }

    public function test_payment_fallback_does_not_book_twice(): void
    {
        Http::fake(['api.paymongo.com/*' => Http::response(['data' => ['attributes' => ['status' => 'completed']]], 200)]);
        $r = $this->paidReservation('full');

        $this->get('/flutter/payment/success?reservation='.$r->id);
        $this->get('/flutter/payment/success?reservation='.$r->id);

        $this->assertSame(1, Booking::where('appointment_date', $r->appointment_date)->where('start_time', '15:00:00')->count());
    }
}
