<?php

namespace Tests\Feature\Identity;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\OnlineReservationPayment;
use App\Models\OperatingHours;
use App\Models\ReassignmentRequest;
use App\Models\Staff;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Bookings\TherapistAssigner;
use App\Services\Identity\IdentityVerificationService;
use Database\Factories\PayrollUserFactory;
use Database\Seeders\IdentityVerificationTestSeeder;
use Database\Seeders\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 4 — online home-service bookings use identity-verified therapists only
 * (D2, D3), on every path; staff-made bookings are exempt (Q14); unassigned paid
 * bookings (Q17); flags when verification is lost (Q33/Q34); the booking form
 * signal (Q35); Staff page (Q36/Q37); removed routes (Q7); name/email from the
 * account at checkout (Q12).
 *
 * World (seeders): Test Branch A has two verified providers — Vera (therapist)
 * and Rhea (receptionist, "Performs treatments") — and several unverified
 * therapists. Opening hours 09:00–21:00 every day are added here.
 */
class BookingEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Treatment $treatment;
    private User $customer;
    private string $date;
    private TherapistAssigner $assigner;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-29 08:00:00');
        Storage::fake('local');
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);

        $this->branch = Branch::where('name', 'Test Branch A')->firstOrFail();
        $this->treatment = Treatment::withoutGlobalScopes()->where('branch_id', $this->branch->id)->firstOrFail(); // in_branch_and_home, 60 min

        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
            OperatingHours::create(['branch_id' => $this->branch->id, 'day_of_week' => $day, 'opening_time' => '09:00:00', 'closing_time' => '21:00:00', 'is_closed' => false]);
        }

        $this->customer = PayrollUserFactory::new()->create(['first_name' => 'Cara', 'last_name' => 'Customer', 'email' => 'cara@levictas.test']);
        $this->customer->assignRole('customer');

        $this->date = '2026-10-31';
        $this->assigner = app(TherapistAssigner::class);
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

    /** Puts a booking on a therapist so they are busy 10:00–11:00. */
    private function busy(User $therapist, string $start = '10:00:00', string $end = '11:00:00', array $extra = []): Booking
    {
        return Booking::create(array_merge([
            'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id, 'therapist_id' => $therapist->id,
            'status' => 'reserved', 'service_type' => 'in_branch', 'booking_source' => 'walk_in',
            'treatment' => 'treatment_'.$this->treatment->id, 'customer_name' => 'Busy',
            'appointment_date' => $this->date, 'start_time' => $start, 'end_time' => $end,
        ], $extra));
    }

    private function slotsFor(string $serviceType): array
    {
        return $this->actingAs($this->customer)->getJson('/bookings/online/available-slots?'.http_build_query([
            'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id,
            'treatment' => 'treatment_'.$this->treatment->id, 'appointment_date' => $this->date,
            'service_type' => $serviceType,
        ]))->assertOk()->json();
    }

    private function slot(array $data, string $time): array
    {
        return collect($data['slots'])->firstWhere('time', $time);
    }

    // ── Pool and verified filter ─────────────────────────────────────────────

    public function test_pool_includes_flagged_staff_and_verified_filter_works(): void
    {
        $pool = $this->assigner->pool($this->branch->spa_id, $this->branch->id)->pluck('email');

        $this->assertContains('idv.rhea@levictas.test', $pool);
        $this->assertNotContains('reception.a@levictas.test', $pool);   // receptionist, not flagged

        $verified = $this->assigner->available($this->branch->spa_id, $this->branch->id, $this->date, '10:00', '11:00', true)->pluck('email')->sort()->values()->all();
        $this->assertSame(['idv.rhea@levictas.test', 'idv.vera@levictas.test'], $verified);
    }

    // ── Slots and dates (Q35) ────────────────────────────────────────────────

    public function test_home_service_slots_count_only_verified_therapists(): void
    {
        $this->busy($this->user('idv.vera@levictas.test'));
        $this->busy($this->user('idv.rhea@levictas.test'));

        $home = $this->slotsFor('in_home');
        $this->assertFalse($this->slot($home, '10:00')['available']);
        $this->assertSame('no_verified_therapist', $this->slot($home, '10:00')['reason']);
        $this->assertTrue($this->slot($home, '12:00')['available']);
        $this->assertTrue($home['home_service_available']);

        $branchSlots = $this->slotsFor('in_branch');
        $this->assertTrue($this->slot($branchSlots, '10:00')['available']);   // unverified therapists are fine in-branch
    }

    public function test_available_days_reports_whether_home_service_is_possible(): void
    {
        $url = '/bookings/online/available-days?branch_id='.$this->branch->id;
        $this->actingAs($this->customer)->getJson($url)->assertOk()->assertJson(['home_service_available' => true]);

        User::whereIn('email', ['idv.vera@levictas.test', 'idv.rhea@levictas.test'])->update(['identity_verified_at' => null]);

        $this->actingAs($this->customer)->getJson($url)->assertOk()
            ->assertJson(['home_service_available' => false])
            ->assertJsonPath('home_service_message', fn ($m) => str_contains($m, 'In-Branch'));
    }

    // ── Checkout (P1, Q12) ───────────────────────────────────────────────────

    private function checkout(string $serviceType, array $overrides = [])
    {
        return $this->actingAs($this->customer)->postJson(route('bookings.online.checkout'), array_merge([
            'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id,
            'customer_name' => 'Someone Else', 'customer_email' => 'not-my-email@example.com',
            'customer_phone' => '09171234567', 'treatment' => 'treatment_'.$this->treatment->id,
            'service_type' => $serviceType, 'customer_address' => 'Imus, Cavite',
            'appointment_date' => $this->date, 'start_time' => '10:00',
        ], $overrides));
    }

    public function test_checkout_refuses_home_service_when_no_verified_therapist_is_free(): void
    {
        $this->busy($this->user('idv.vera@levictas.test'));
        $this->busy($this->user('idv.rhea@levictas.test'));
        Http::fake();

        $this->checkout('in_home')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'verified therapist'));
        Http::assertNothingSent();
    }

    public function test_checkout_copies_name_and_email_from_the_account(): void
    {
        Http::fake(['api.paymongo.com/*' => Http::response(['data' => ['id' => 'cs_test_1', 'attributes' => ['checkout_url' => 'https://pay.example/cs']]], 200)]);

        $this->checkout('in_home')->assertOk()->assertJson(['checkout_url' => 'https://pay.example/cs']);

        $reservation = OnlineReservationPayment::latest('id')->firstOrFail();
        $this->assertSame('Cara Customer', $reservation->customer_name);
        $this->assertSame('cara@levictas.test', $reservation->customer_email);
    }

    // ── Webhook (P3) and unassigned (Q17) ────────────────────────────────────

    private function reservation(string $serviceType): OnlineReservationPayment
    {
        $r = new OnlineReservationPayment();
        $r->forceFill([
            'user_id' => $this->customer->id, 'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id,
            'customer_name' => 'Cara Customer', 'customer_email' => 'cara@levictas.test', 'customer_phone' => '09171234567',
            'customer_address' => 'Imus, Cavite', 'bookable_id' => $this->treatment->id, 'bookable_type' => 'treatment',
            'bookable_name' => $this->treatment->name, 'full_amount' => 1200, 'downpayment_amount' => 240,
            'payment_option' => 'downpayment', 'amount_charged' => 240, 'service_type' => $serviceType,
            'appointment_date' => $this->date, 'start_time' => '10:00:00', 'payment_status' => 'pending',
            'reservation_status' => 'awaiting_payment', 'paymongo_checkout_session_id' => 'cs_'.uniqid(),
        ])->save();

        return $r;
    }

    private function webhookPaid(OnlineReservationPayment $r)
    {
        config(['services.paymongo.test_webhook_secret' => 'whsec_test']);
        $payload = json_encode(['data' => ['attributes' => [
            'type' => 'checkout_session.payment.paid', 'livemode' => false,
            'data' => ['id' => $r->paymongo_checkout_session_id, 'attributes' => ['payments' => []]],
        ]]]);
        $t = time();
        $sig = 't='.$t.',te='.hash_hmac('sha256', $t.'.'.$payload, 'whsec_test').',li=';

        return $this->call('POST', route('webhooks.paymongo'), [], [], [], [
            'HTTP_PAYMONGO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    public function test_webhook_assigns_a_verified_therapist_for_home_service(): void
    {
        $this->webhookPaid($this->reservation('in_home'))->assertOk();

        $booking = Booking::where('booking_source', 'online')->latest('id')->firstOrFail();
        $this->assertTrue($booking->therapist->isIdentityVerified());
        $this->assertContains($booking->therapist->email, ['idv.vera@levictas.test', 'idv.rhea@levictas.test']);
    }

    public function test_webhook_creates_an_unassigned_booking_when_nobody_verified_is_free(): void
    {
        $this->busy($this->user('idv.vera@levictas.test'));
        $this->busy($this->user('idv.rhea@levictas.test'));

        $this->webhookPaid($this->reservation('in_home'))->assertOk();

        $booking = Booking::where('booking_source', 'online')->latest('id')->firstOrFail();
        $this->assertNull($booking->therapist_id);
        $this->assertSame('reserved', $booking->status);

        // The branch sees it on Appointments.
        $this->actingAs($this->user('manager.a@levictas.test'))->get(route('appointments.index'))
            ->assertOk()->assertSee('No Therapist Assigned')->assertSee('Assign therapist');
    }

    // ── Staff edit (P8) and staff booking (Q14) ──────────────────────────────

    public function test_editing_an_online_home_booking_needs_a_verified_therapist(): void
    {
        $online = $this->busy($this->user('idv.vera@levictas.test'), '14:00:00', '15:00:00', ['booking_source' => 'online', 'service_type' => 'in_home', 'customer_address' => 'Imus']);
        $unverified = $this->user('idv.paolo@levictas.test');
        $manager = $this->user('manager.a@levictas.test');

        $payload = fn ($therapist, $type = 'in_home') => [
            'customer_name' => 'Busy', 'service_type' => $type, 'treatment' => 'treatment_'.$this->treatment->id,
            'therapist_id' => $therapist->id, 'appointment_date' => $this->date, 'start_time' => '14:00', 'customer_address' => 'Imus',
        ];

        $this->actingAs($manager)->from(route('appointments.index'))->put(route('appointments.update', $online), $payload($unverified))
            ->assertSessionHasErrors('therapist_id');
        $this->assertSame($this->user('idv.vera@levictas.test')->id, $online->fresh()->therapist_id);

        $this->actingAs($manager)->put(route('appointments.update', $online), $payload($this->user('idv.rhea@levictas.test')))
            ->assertSessionHasNoErrors();
        $this->assertSame($this->user('idv.rhea@levictas.test')->id, $online->fresh()->therapist_id);

        // Staff-made home service: exempt (Q14).
        $walkIn = $this->busy($this->user('idv.vera@levictas.test'), '16:00:00', '17:00:00', ['service_type' => 'in_home', 'customer_address' => 'Imus']);
        $this->actingAs($manager)->put(route('appointments.update', $walkIn), array_merge($payload($unverified), ['start_time' => '16:00']))
            ->assertSessionHasNoErrors();
        $this->assertSame($unverified->id, $walkIn->fresh()->therapist_id);
    }

    // ── Reassignment (P11, R7) ───────────────────────────────────────────────

    public function test_reassignment_approval_uses_the_shared_rule(): void
    {
        $vera = $this->user('idv.vera@levictas.test');
        $online = $this->busy($vera, '14:00:00', '15:00:00', ['booking_source' => 'online', 'service_type' => 'in_home', 'customer_address' => 'Imus']);
        $request = ReassignmentRequest::create(['booking_id' => $online->id, 'requested_by' => $vera->id, 'old_therapist_id' => $vera->id, 'reason' => 'Test', 'status' => 'pending']);
        $manager = $this->user('manager.a@levictas.test');

        // Not in the pool (plain receptionist) — this used to be accepted.
        $this->actingAs($manager)->postJson(route('reassignment.approve', $request), ['new_therapist_id' => $this->user('reception.a@levictas.test')->id])
            ->assertStatus(422);
        // In the pool but not verified, for online home service.
        $this->actingAs($manager)->postJson(route('reassignment.approve', $request), ['new_therapist_id' => $this->user('idv.paolo@levictas.test')->id])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'identity-verified'));
        // Verified and free.
        $this->actingAs($manager)->postJson(route('reassignment.approve', $request), ['new_therapist_id' => $this->user('idv.rhea@levictas.test')->id])
            ->assertOk();

        $this->assertSame($this->user('idv.rhea@levictas.test')->id, $online->fresh()->therapist_id);
    }

    public function test_reassignment_candidates_are_verified_only_for_online_home_service(): void
    {
        $vera = $this->user('idv.vera@levictas.test');
        $online = $this->busy($vera, '14:00:00', '15:00:00', ['booking_source' => 'online', 'service_type' => 'in_home', 'customer_address' => 'Imus']);

        $names = $this->actingAs($this->user('manager.a@levictas.test'))->getJson(route('booking.available-therapists', [
            'treatment' => 'treatment_'.$this->treatment->id, 'appointment_date' => $this->date, 'start_time' => '14:00:00', 'booking_id' => $online->id,
        ]))->assertOk()->assertJson(['verified_only' => true])->json('therapists.*.verified');

        $this->assertNotEmpty($names);
        $this->assertNotContains(false, $names);
    }

    // ── Losing verification (Q33, Q34) ───────────────────────────────────────

    public function test_losing_verification_flags_upcoming_online_home_bookings_only(): void
    {
        $vera = $this->user('idv.vera@levictas.test');
        $home = $this->busy($vera, '14:00:00', '15:00:00', ['booking_source' => 'online', 'service_type' => 'in_home', 'customer_address' => 'Imus']);
        $inBranch = $this->busy($vera, '16:00:00', '17:00:00', ['booking_source' => 'online']);
        $ongoing = $this->busy($vera, '07:30:00', '08:30:00', ['booking_source' => 'online', 'service_type' => 'in_home', 'status' => 'ongoing', 'appointment_date' => '2026-10-29']);

        app(IdentityVerificationService::class)->updateProfilePhoto($vera, UploadedFile::fake()->image('new.jpg', 800, 800));

        $flags = ReassignmentRequest::where('source', 'unverified')->pluck('booking_id')->all();
        $this->assertSame([$home->id], $flags);               // not the in-branch one, not the session in progress
        $this->assertSame($vera->id, $home->fresh()->therapist_id);   // nothing changed automatically

        // Shows up in the existing Reassignment Requests list.
        $this->actingAs($this->user('manager.a@levictas.test'))->getJson(route('reassignment.index'))
            ->assertOk()->assertJsonFragment(['booking_id' => $home->id, 'source' => 'unverified', 'requires_verified' => true]);
    }

    public function test_becoming_verified_again_closes_those_flags(): void
    {
        $vera = $this->user('idv.vera@levictas.test');
        $home = $this->busy($vera, '14:00:00', '15:00:00', ['booking_source' => 'online', 'service_type' => 'in_home', 'customer_address' => 'Imus']);
        $service = app(IdentityVerificationService::class);

        $service->updateProfilePhoto($vera, UploadedFile::fake()->image('new.jpg', 800, 800));
        $row = $service->submit($vera->fresh(), 'philid', UploadedFile::fake()->image('id.jpg', 800, 800), UploadedFile::fake()->image('s.jpg', 800, 800));
        $admin = PayrollUserFactory::new()->create();
        $admin->assignRole('admin');
        $service->approve($row, $admin);

        $this->assertSame('rejected', ReassignmentRequest::where('booking_id', $home->id)->value('status'));
    }

    // ── Staff page (Q36, Q37) and branch readiness ───────────────────────────

    public function test_staff_page_badge_and_performs_treatments_checkbox(): void
    {
        $owner = $this->user('owner@levictas.test');
        $this->actingAs($owner)->get(route('staff.index'))->assertOk()
            ->assertSee('Performs treatments')->assertSee('Not verified')->assertSee('Verified')
            ->assertDontSee('blurry');   // rejection reasons stay private

        $reception = Staff::whereHas('user', fn ($q) => $q->where('email', 'reception.a@levictas.test'))->firstOrFail();
        $this->actingAs($owner)->put(route('staff.update', $reception), ['roles' => 'receptionist', 'performs_treatments' => '1'])->assertRedirect();
        $this->assertTrue($reception->fresh()->performs_treatments);
        $this->assertTrue($this->user('reception.a@levictas.test')->isTreatmentProvider());

        // Becoming a therapist clears the flag (therapists are always in the pool).
        $this->actingAs($owner)->put(route('staff.update', $reception), ['roles' => 'therapist', 'performs_treatments' => '1']);
        $this->assertFalse($reception->fresh()->performs_treatments);
    }

    public function test_a_branch_whose_only_provider_is_a_flagged_receptionist_is_ready(): void
    {
        $branchB = Branch::where('name', 'Test Branch B')->firstOrFail();
        Staff::whereHas('user', fn ($q) => $q->role('therapist'))->where('branch_id', $branchB->id)->update(['employment_status' => 'inactive']);
        Treatment::withoutGlobalScopes()->where('branch_id', $branchB->id)->exists() || $this->markTestSkipped('Branch B has no service.');

        $this->assertFalse($branchB->fresh()->hasActiveTherapist());

        Staff::whereHas('user', fn ($q) => $q->where('email', 'hr.b@levictas.test'))->update(['performs_treatments' => true]);
        $this->assertTrue($branchB->fresh()->hasActiveTherapist());
        $this->assertTrue(Branch::readyForBookings()->whereKey($branchB->id)->exists());
    }

    // ── Removed routes (Q7) ──────────────────────────────────────────────────

    public function test_unused_booking_routes_are_gone(): void
    {
        // 405, not 404: the CORS catch-all OPTIONS /{any} route matches every path.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('bookings.online.store'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('appointments.reserve'));
        $this->actingAs($this->customer)->post('/bookings/online', [])->assertStatus(405);

        $booking = $this->busy($this->user('idv.vera@levictas.test'));
        $this->actingAs($this->user('manager.a@levictas.test'))->post('/appointments/'.$booking->id.'/reserve', ['therapist_id' => 1])->assertStatus(405);
        $this->assertSame($this->user('idv.vera@levictas.test')->id, $booking->fresh()->therapist_id);
    }
}
