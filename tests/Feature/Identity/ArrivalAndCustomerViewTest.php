<?php

namespace Tests\Feature\Identity;

use App\Mail\BookingTherapistMail;
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
 * Phase 5 — what customers and therapists see, and the arrival code
 * (D6, D7, D8, Q6, Q41–Q44). Uses HomeVisitTestSeeder: booking A (today,
 * Vera), B (tomorrow, Vera), C (tomorrow, no therapist).
 */
class ArrivalAndCustomerViewTest extends TestCase
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

        $this->customer = User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();
        $this->vera = User::where('email', 'idv.vera@levictas.test')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function booking(string $which): Booking
    {
        $rows = Booking::where('customer_user_id', $this->customer->id)->orderBy('appointment_date')->orderBy('start_time')->get();

        return ['A' => $rows[0], 'B' => $rows[1], 'C' => $rows[2]][$which];
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    // ── Code basics ──────────────────────────────────────────────────────────

    public function test_online_home_bookings_get_a_4_digit_code_that_is_stored_encrypted_and_hidden(): void
    {
        $a = $this->booking('A');

        $this->assertMatchesRegularExpression('/^\d{4}$/', $a->arrival_code);
        $this->assertNotSame($a->arrival_code, \DB::table('bookings')->where('id', $a->id)->value('arrival_code'));   // encrypted at rest
        $this->assertArrayNotHasKey('arrival_code', $a->toArray());

        $walkIn = Booking::create([
            'spa_id' => $a->spa_id, 'branch_id' => $a->branch_id, 'status' => 'reserved', 'service_type' => 'in_home',
            'booking_source' => 'walk_in', 'treatment' => $a->treatment, 'customer_name' => 'W',
            'appointment_date' => $a->appointment_date, 'start_time' => '15:00:00', 'end_time' => '16:00:00',
        ]);
        $this->assertNull($walkIn->arrival_code);   // staff-made: no code
    }

    // ── Customer view (D6, D7) ───────────────────────────────────────────────

    public function test_customer_sees_the_therapist_card_and_the_code(): void
    {
        $rows = collect($this->actingAs($this->customer)->getJson('/my-appointments')->assertOk()->json());
        $a = $rows->firstWhere('id', $this->booking('A')->id);

        $this->assertSame('Vera Verified (test)', $a['therapist_card']['name']);
        $this->assertTrue($a['therapist_card']['verified']);
        $this->assertNotNull($a['therapist_card']['photo_url']);
        $this->assertTrue($a['is_home_service']);
        $this->assertSame($this->booking('A')->arrival_code, $a['arrival_code']);

        // The customer may load the photo; the unassigned one says so.
        $this->actingAs($this->customer)->get($a['therapist_card']['photo_url'])->assertOk();
        $c = $rows->firstWhere('id', $this->booking('C')->id);
        $this->assertNull($c['therapist_card']);
        $this->assertFalse($c['therapist_assigned']);
    }

    public function test_past_and_cancelled_bookings_show_no_card_or_code(): void
    {
        $b = $this->booking('B');
        $b->update(['status' => 'cancelled']);

        $row = collect($this->actingAs($this->customer)->getJson('/my-appointments')->json())->firstWhere('id', $b->id);
        $this->assertNull($row['therapist_card']);
        $this->assertNull($row['arrival_code']);
    }

    public function test_staff_never_receive_the_code(): void
    {
        // A fixed code that can't also appear as a year, price or time on the page.
        $code = '9731';
        $this->booking('A')->forceFill(['arrival_code' => $code])->saveQuietly();

        foreach (['idv.vera@levictas.test', 'manager.a@levictas.test'] as $email) {
            $this->actingAs($this->user($email))->get(route('appointments.index'))->assertOk()->assertDontSee($code);
            $this->actingAs($this->user($email))->get(route('dashboard'))->assertOk()->assertDontSee($code);
            $this->actingAs($this->user($email))->getJson(route('appointments.live-data'))->assertOk()->assertDontSee($code);
        }

        $this->actingAs($this->vera, 'sanctum')->getJson('/api/assigned-bookings')->assertOk()->assertDontSee('arrival_code');
    }

    // ── Therapist side (D8) ──────────────────────────────────────────────────

    public function test_assigned_therapist_sees_their_home_visits_with_customer_details(): void
    {
        $this->actingAs($this->vera)->get(route('dashboard'))->assertOk()
            ->assertSee('Home Visits')->assertSee('Cara Customer (test)')->assertSee('09170000001')
            ->assertSee('Sample street, Imus, Cavite')->assertSee('Email verified')->assertSee('Enter arrival code');

        // Another therapist doesn't see them.
        $this->actingAs($this->user('idv.paolo@levictas.test'))->get(route('dashboard'))->assertOk()->assertDontSee('Cara Customer (test)');
    }

    // ── Entering the code (Q6, Q41) ──────────────────────────────────────────

    public function test_correct_code_records_arrival_without_changing_status(): void
    {
        $a = $this->booking('A');

        $this->actingAs($this->vera)->from(route('dashboard'))
            ->post(route('appointments.arrival', $a), ['arrival_code' => $a->arrival_code])
            ->assertSessionHasNoErrors();

        $a->refresh();
        $this->assertNotNull($a->arrival_confirmed_at);
        $this->assertSame('code', $a->arrival_method);
        $this->assertSame($this->vera->id, $a->arrival_confirmed_by);
        $this->assertSame('reserved', $a->status);   // status is still clock-driven

        // The customer now sees "arrived", no longer the code.
        $row = collect($this->actingAs($this->customer)->getJson('/my-appointments')->json())->firstWhere('id', $a->id);
        $this->assertNull($row['arrival_code']);
        $this->assertNotNull($row['arrival_confirmed_at']);
    }

    public function test_five_wrong_codes_lock_it(): void
    {
        $a = $this->booking('A');
        $wrong = $a->arrival_code === '0000' ? '1111' : '0000';

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($this->vera)->from(route('dashboard'))
                ->post(route('appointments.arrival', $a), ['arrival_code' => $wrong])
                ->assertSessionHasErrorsIn('arrival', 'arrival_code');
        }

        $a->refresh();
        $this->assertSame(5, $a->arrival_attempts);
        $this->assertNotNull($a->arrival_locked_at);

        // Even the right code is refused now.
        $this->actingAs($this->vera)->from(route('dashboard'))
            ->post(route('appointments.arrival', $a), ['arrival_code' => $a->arrival_code])
            ->assertSessionHasErrorsIn('arrival', 'arrival_code');
        $this->assertNull($a->fresh()->arrival_confirmed_at);
    }

    public function test_only_the_assigned_therapist_and_only_on_the_day(): void
    {
        $a = $this->booking('A');
        $b = $this->booking('B');

        $this->actingAs($this->user('idv.paolo@levictas.test'))->from(route('dashboard'))
            ->post(route('appointments.arrival', $a), ['arrival_code' => $a->arrival_code])
            ->assertSessionHasErrorsIn('arrival', 'arrival_code');

        $this->actingAs($this->vera)->from(route('dashboard'))
            ->post(route('appointments.arrival', $b), ['arrival_code' => $b->arrival_code])
            ->assertSessionHasErrorsIn('arrival', 'arrival_code');   // tomorrow

        $this->assertNull($a->fresh()->arrival_confirmed_at);
        $this->assertNull($b->fresh()->arrival_confirmed_at);
    }

    // ── Branch confirms by hand (Q42a) ───────────────────────────────────────

    public function test_branch_can_confirm_by_hand_with_a_reason(): void
    {
        $a = $this->booking('A');
        $manager = $this->user('manager.a@levictas.test');

        $this->actingAs($manager)->get(route('appointments.index'))->assertSee('Confirm without code');

        $this->actingAs($manager)->from(route('appointments.index'))
            ->post(route('appointments.arrival.manual', $a), ['arrival_reason' => ''])
            ->assertSessionHasErrorsIn('arrival', 'arrival_reason');

        $this->actingAs($manager)->from(route('appointments.index'))
            ->post(route('appointments.arrival.manual', $a), ['arrival_reason' => 'Customer phone is dead; called the landline.'])
            ->assertSessionHasNoErrors();

        $a->refresh();
        $this->assertSame('manual', $a->arrival_method);
        $this->assertSame($manager->id, $a->arrival_confirmed_by);
        $this->assertSame('Customer phone is dead; called the landline.', $a->arrival_manual_reason);
    }

    public function test_therapist_cannot_confirm_by_hand(): void
    {
        $a = $this->booking('A');

        // Vera has no "edit appointments": blocked by the route.
        $this->actingAs($this->vera)->post(route('appointments.arrival.manual', $a), ['arrival_reason' => 'Trust me, I am here.'])
            ->assertForbidden();
        $this->assertNull($a->fresh()->arrival_confirmed_at);
    }

    // ── Emails (D6, Q5, Q43) ─────────────────────────────────────────────────

    public function test_assigning_or_changing_the_therapist_emails_the_customer(): void
    {
        $c = $this->booking('C');
        $c->update(['therapist_id' => $this->vera->id]);

        Mail::assertSent(BookingTherapistMail::class, fn ($m) => $m->hasTo(HomeVisitTestSeeder::CUSTOMER_EMAIL)
            && $m->kind === BookingTherapistMail::KIND_ASSIGNED && $m->booking->is($c));

        $c->update(['therapist_id' => $this->user('idv.rhea@levictas.test')->id]);
        Mail::assertSent(BookingTherapistMail::class, fn ($m) => $m->kind === BookingTherapistMail::KIND_CHANGED);

        // Status or time changes don't send this email.
        Mail::fake();
        $c->update(['status' => 'pending']);
        Mail::assertNothingSent();
    }

    public function test_email_shows_the_therapist_card_and_the_code(): void
    {
        $a = $this->booking('A');
        $html = (new BookingTherapistMail($a->load(['therapist', 'spa', 'branch.profile'])))->render();

        $this->assertStringContainsString('Vera Verified (test)', $html);
        $this->assertStringContainsString('Identity verified', $html);
        $this->assertStringContainsString($a->arrival_code, $html);
        $this->assertStringContainsString('do not let them in', $html);
    }

    // ── Q44 ──────────────────────────────────────────────────────────────────

    public function test_my_performance_is_open_to_staff_who_perform_treatments(): void
    {
        $this->actingAs($this->user('idv.rhea@levictas.test'))->get(route('therapist.performance'))->assertOk();
        $this->actingAs($this->user('reception.a@levictas.test'))->get(route('therapist.performance'))->assertForbidden();
        $this->actingAs($this->user('idv.rhea@levictas.test'))->get(route('dashboard'))->assertSee(route('therapist.performance'), false);
    }

    // ── Demo-data seeders ────────────────────────────────────────────────────

    public function test_seeder_does_not_duplicate_and_reset_gives_a_fresh_copy(): void
    {
        $this->seed(HomeVisitTestSeeder::class);   // already there → stops
        $this->assertSame(3, Booking::where('customer_user_id', $this->customer->id)->count());

        // Things testing leaves behind: a completed booking with an (empty)
        // used-products record, a confirmed arrival, a reassignment request.
        $a = $this->booking('A');
        \DB::table('booking_consumptions')->insert([
            'booking_id' => $a->id, 'spa_id' => $a->spa_id, 'branch_id' => $a->branch_id,
            'service_reference' => $a->treatment, 'service_name' => 'x', 'consumed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $a->forceFill(['status' => 'completed', 'arrival_confirmed_at' => now(), 'arrival_method' => 'code'])->saveQuietly();
        app(\App\Services\Bookings\VerificationBookingFlags::class)->flagAfterVerificationLost($this->vera, 'test');
        $otherBookings = Booking::where('customer_user_id', '!=', $this->customer->id)->orWhereNull('customer_user_id')->count();

        $this->seed(\Database\Seeders\HomeVisitTestResetSeeder::class);

        $fresh = User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();
        $rows = Booking::where('customer_user_id', $fresh->id)->get();
        $this->assertCount(3, $rows);
        $this->assertTrue($rows->every(fn ($b) => $b->status === 'reserved' && $b->arrival_confirmed_at === null));
        $this->assertSame(0, \DB::table('booking_consumptions')->count());
        $this->assertSame($otherBookings, Booking::where('customer_user_id', '!=', $fresh->id)->orWhereNull('customer_user_id')->count());
    }

    public function test_remove_stops_when_a_booking_used_products(): void
    {
        $a = $this->booking('A');
        $id = \DB::table('booking_consumptions')->insertGetId([
            'booking_id' => $a->id, 'spa_id' => $a->spa_id, 'branch_id' => $a->branch_id,
            'service_reference' => $a->treatment, 'service_name' => 'x', 'consumed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = \DB::table('products')->insertGetId(['spa_id' => $a->spa_id, 'name' => 'Oil (test)', 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('booking_consumption_items')->insert([
            'booking_consumption_id' => $id, 'product_id' => $productId, 'product_name' => 'Oil', 'quantity' => 1, 'unit' => 'ml',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->seed(\Database\Seeders\HomeVisitTestRemoveSeeder::class);

        $this->assertNotNull(User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->first());
        $this->assertSame(3, Booking::where('customer_user_id', $this->customer->id)->count());
    }

    public function test_identity_reset_also_resets_home_visits(): void
    {
        // A reassignment request pointing at Vera used to block the identity reset.
        app(\App\Services\Bookings\VerificationBookingFlags::class)->flagAfterVerificationLost($this->vera, 'test');

        $this->seed(\Database\Seeders\IdentityVerificationTestResetSeeder::class);

        $vera = User::where('email', 'idv.vera@levictas.test')->firstOrFail();
        $this->assertNotSame($this->vera->id, $vera->id);
        $fresh = User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();
        $this->assertSame(2, Booking::where('customer_user_id', $fresh->id)->where('therapist_id', $vera->id)->count());
    }
}
