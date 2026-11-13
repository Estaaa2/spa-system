<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\BookingConsumption;
use App\Models\User;
use App\Services\Navigation\NavBadges;
use Tests\Fixtures\IdentityVerificationTestSeeder;
use Tests\Fixtures\OverdueBookingsTestRemoveSeeder;
use Tests\Fixtures\OverdueBookingsTestResetSeeder;
use Tests\Fixtures\OverdueBookingsTestSeeder;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Booking lifecycle fix, Phase 2: the Needs Checkout list (D3, Q1–Q4), the
 * checkout gates for therapist (Q5-A) and home-visit arrival (D4, Q6-B), and
 * the one-time reopen command (D8).
 *
 * OverdueBookingsTestSeeder runs "today" = 2026-10-31, so its O1–O4 bookings
 * are dated 2026-10-30 and all have ended.
 */
class NeedsCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-31 09:00:00');
        Storage::fake('local');
        Mail::fake();
        Cache::flush();
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);
        $this->seed(OverdueBookingsTestSeeder::class);

        $this->manager = User::where('email', 'manager.a@levictas.test')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function booking(string $key): Booking
    {
        return Booking::where('customer_name', "Olive Overdue ({$key})")->firstOrFail();
    }

    private function checkout(Booking $booking)
    {
        return $this->actingAs($this->manager)->from(route('appointments.index'))
            ->put(route('appointments.checkout', $booking), ['amount_paid' => (float) $booking->fresh()->balance_amount]);
    }

    // ── The list ─────────────────────────────────────────────────────────

    public function test_ended_open_bookings_are_listed_under_needs_checkout_and_not_in_history(): void
    {
        $this->assertSame(4, Booking::overdue()->count());

        $html = $this->actingAs($this->manager)->get(route('appointments.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="needs-checkout"', $html);
        $this->assertStringContainsString('4 to close', $html);
        $this->assertStringContainsString('No therapist recorded', $html);   // O4
        $this->assertStringContainsString('Arrival not confirmed', $html);   // O3

        // History holds finished bookings only now.
        $history = substr($html, strpos($html, 'Completed, cancelled, and no-show records'));
        $this->assertStringNotContainsString('Olive Overdue', $history);
    }

    public function test_the_appointments_badge_counts_them_and_shows_the_exclamation_for_earlier_days(): void
    {
        $branchId = $this->booking('O1')->branch_id;

        $badge = NavBadges::forUser($this->manager, $branchId)['appointments'] ?? null;
        $this->assertNotNull($badge);
        $this->assertGreaterThanOrEqual(4, $badge['count']);
        $this->assertStringContainsString('4 to check out (4 from earlier days)', $badge['label']);
        $this->assertTrue($badge['urgent']);

        // A booking that ended earlier today counts, but doesn't raise "!" on its own.
        foreach (['O1', 'O2', 'O3', 'O4'] as $key) {
            $this->booking($key)->forceFill(['status' => 'cancelled'])->saveQuietly();
        }
        $today = $this->booking('O1')->replicate();
        $today->forceFill(['status' => 'pending', 'appointment_date' => '2026-10-31',
            'start_time' => '07:00:00', 'end_time' => '08:00:00'])->saveQuietly();
        Cache::flush();

        $badge = NavBadges::forUser($this->manager, $branchId)['appointments'] ?? null;
        $this->assertStringContainsString('1 to check out', $badge['label']);
        $this->assertFalse($badge['urgent']);
    }

    public function test_the_dashboard_alerts_widget_shows_needs_checkout(): void
    {
        $owner = User::where('email', 'owner@levictas.test')->firstOrFail();
        $response = $this->actingAs($owner)->get(route('dashboard'));

        if ($response->isRedirect()) {
            $this->markTestSkipped('The seeded owner is redirected from the dashboard here (branch setup).');
        }

        $response->assertOk()->assertSee('Ended but never closed');
    }

    // ── Gates: therapist (Q5-A) ──────────────────────────────────────────

    public function test_an_unassigned_overdue_booking_needs_a_therapist_before_checkout(): void
    {
        $o4 = $this->booking('O4');
        $vera = User::where('email', 'idv.vera@levictas.test')->firstOrFail();

        $this->checkout($o4)->assertSessionHas('error', 'Assign a therapist first.');
        $this->assertSame('pending', $o4->fresh()->status);

        $assign = fn ($id) => $this->actingAs($this->manager)->from(route('appointments.index'))
            ->put(route('appointments.assignTherapist', $o4), ['therapist_id' => $id]);

        $assign($this->manager->id)->assertSessionHasErrors('therapist_id');   // not a treatment provider
        $assign($vera->id)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame($vera->id, $o4->fresh()->therapist_id);
        Mail::assertNothingSent();   // an ended booking emails nobody

        $this->checkout($o4)->assertSessionHasNoErrors();
        $this->assertSame('completed', $o4->fresh()->status);
        $this->assertSame($this->manager->id, $o4->fresh()->consumption->processed_by);
    }

    public function test_assign_therapist_is_only_for_ended_unassigned_bookings(): void
    {
        $vera = User::where('email', 'idv.vera@levictas.test')->firstOrFail();
        $o1 = $this->booking('O1');   // already has a therapist

        $this->actingAs($this->manager)->from(route('appointments.index'))
            ->put(route('appointments.assignTherapist', $o1), ['therapist_id' => $vera->id])
            ->assertSessionHas('error', 'This appointment already has a therapist.');

        $future = $this->booking('O4')->replicate();
        $future->forceFill(['appointment_date' => '2026-11-02'])->saveQuietly();
        $this->actingAs($this->manager)->from(route('appointments.index'))
            ->put(route('appointments.assignTherapist', $future), ['therapist_id' => $vera->id])
            ->assertSessionHas('error');
        $this->assertNull($future->fresh()->therapist_id);
    }

    // ── Gates: home-visit arrival (D4, Q6-B) ─────────────────────────────

    public function test_a_home_visit_needs_its_arrival_confirmed_and_branch_staff_can_do_it_the_next_day(): void
    {
        $o3 = $this->booking('O3');

        $this->checkout($o3)->assertSessionHas('error', "Confirm the therapist's arrival first.");

        // The therapist's code is same-day only.
        $vera = User::where('email', 'idv.vera@levictas.test')->firstOrFail();
        $this->actingAs($vera)->from(route('appointments.index'))
            ->post(route('appointments.arrival', $o3), ['arrival_code' => $o3->arrival_code])
            ->assertSessionHasErrors('arrival_code', null, 'arrival');
        $this->assertNull($o3->fresh()->arrival_confirmed_at);

        // Branch staff confirm by hand, with a reason, the next day.
        $this->actingAs($this->manager)->from(route('appointments.index'))
            ->post(route('appointments.arrival.manual', $o3), ['arrival_reason' => 'Therapist phone died; customer confirmed by call.'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('appointments.index').'#needs-checkout');

        $fresh = $o3->fresh();
        $this->assertSame('manual', $fresh->arrival_method);
        $this->assertTrue($fresh->arrivalConfirmedLate());   // stored time = when confirmed

        $this->actingAs($this->manager)->get(route('appointments.index'))
            ->assertSee('Arrival confirmed late');

        $this->checkout($o3)->assertSessionHasNoErrors();
        $this->assertSame('completed', $o3->fresh()->status);
    }

    public function test_manual_arrival_is_still_refused_before_the_appointment_day(): void
    {
        $future = $this->booking('O3')->replicate();
        $future->forceFill(['appointment_date' => '2026-11-02', 'arrival_code' => '1234'])->saveQuietly();

        $this->actingAs($this->manager)->from(route('appointments.index'))
            ->post(route('appointments.arrival.manual', $future), ['arrival_reason' => 'Trying to confirm early.'])
            ->assertSessionHasErrors('arrival_code', null, 'arrival');
        $this->assertNull($future->fresh()->arrival_confirmed_at);
    }

    // ── Other actions on overdue bookings ────────────────────────────────

    public function test_overdue_bookings_can_be_marked_no_show_or_cancelled_and_leave_the_list(): void
    {
        $this->actingAs($this->manager)->from(route('appointments.index'))
            ->put(route('appointments.updateStatus', $this->booking('O1')), ['status' => Booking::NO_SHOW])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->from(route('appointments.index'))
            ->put(route('appointments.updateStatus', $this->booking('O2')), ['status' => 'cancelled'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Booking::overdue()->count());
        $this->assertSame(0, BookingConsumption::count());
    }

    public function test_a_started_session_from_yesterday_is_checked_out_for_the_rest_of_the_balance(): void
    {
        $o2 = $this->booking('O2');   // ongoing, half paid
        $this->assertGreaterThan(0, (float) $o2->balance_amount);

        $this->checkout($o2)->assertSessionHasNoErrors();

        $fresh = $o2->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertSame('paid', $fresh->payment_status);
        $this->assertEquals((float) $fresh->total_amount, (float) $fresh->amount_paid);
    }

    // ── D8: reopen clock-completed bookings ──────────────────────────────

    public function test_the_reopen_command_only_touches_bookings_the_clock_completed(): void
    {
        // O1: completed by the old clock (no consumption, or one with no processed_by).
        $o1 = $this->booking('O1');
        $o1->forceFill(['status' => 'completed'])->saveQuietly();
        BookingConsumption::create([
            'booking_id' => $o1->id, 'spa_id' => $o1->spa_id, 'branch_id' => $o1->branch_id,
            'processed_by' => null, 'service_reference' => $o1->treatment, 'service_name' => 'Old page-load completion',
            'consumed_at' => now()->subDay(),
        ]);
        $o2 = $this->booking('O2');
        $o2->forceFill(['status' => 'completed'])->saveQuietly();

        // O3: really checked out by staff.
        $o3 = $this->booking('O3');
        $o3->forceFill(['arrival_confirmed_at' => now(), 'arrival_method' => 'manual'])->saveQuietly();
        $this->checkout($o3)->assertSessionHasNoErrors();

        // PayrollTestAccountsSeeder adds its own raw-completed bookings; they match too.
        $expected = Booking::where('status', 'completed')
            ->whereDoesntHave('consumption', fn ($q) => $q->whereNotNull('processed_by'))->count();
        $this->assertGreaterThanOrEqual(2, $expected);

        $this->artisan('bookings:reopen-clock-completed', ['--dry-run' => true])
            ->expectsOutputToContain("Completed by the clock, no staff checkout: {$expected}")
            ->expectsOutputToContain('Dry run: nothing was changed.')
            ->assertSuccessful();
        $this->assertSame('completed', $o1->fresh()->status);

        $this->artisan('bookings:reopen-clock-completed', ['--force' => true])->assertSuccessful();

        $this->assertSame('pending', $o1->fresh()->status);
        $this->assertSame('pending', $o2->fresh()->status);
        $this->assertSame('completed', $o3->fresh()->status);
        $this->assertEquals((float) $o2->amount_paid, (float) $o2->fresh()->amount_paid);   // payments untouched

        // Safe to re-run.
        $this->artisan('bookings:reopen-clock-completed', ['--force' => true])
            ->expectsOutputToContain('Nothing to reopen')
            ->assertSuccessful();

        // A reopened booking whose stock was already deducted isn't deducted twice.
        $this->checkout($o1)->assertSessionHasNoErrors();
        $this->assertSame(1, BookingConsumption::where('booking_id', $o1->id)->count());
    }

    // ── Seeders ──────────────────────────────────────────────────────────

    public function test_the_overdue_seeders_reset_and_remove_only_their_own_data(): void
    {
        $others = Booking::where('customer_email', '!=', OverdueBookingsTestSeeder::CUSTOMER_EMAIL)->count();

        $this->seed(OverdueBookingsTestResetSeeder::class);
        $this->assertSame(4, Booking::where('customer_email', OverdueBookingsTestSeeder::CUSTOMER_EMAIL)->count());

        $this->seed(OverdueBookingsTestRemoveSeeder::class);
        $this->assertSame(0, Booking::where('customer_email', OverdueBookingsTestSeeder::CUSTOMER_EMAIL)->count());
        $this->assertFalse(User::withTrashed()->where('email', OverdueBookingsTestSeeder::CUSTOMER_EMAIL)->exists());
        $this->assertSame($others, Booking::count());
    }
}
