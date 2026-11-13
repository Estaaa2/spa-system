<?php

namespace Tests\Feature\Identity;

use App\Models\Booking;
use App\Models\User;
use Tests\Fixtures\HomeVisitTestSeeder;
use Tests\Fixtures\IdentityVerificationTestSeeder;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * UI rework Phase 3: staff record a no-show (decision 1A: only after the start
 * time; 2A: payments handled like a cancellation; 3A: the Dashboard counts real
 * no-shows). Booking A from HomeVisitTestSeeder is today at 10:00 (seeded at 09:20).
 */
class NoShowTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Booking $booking;

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

        $this->manager = User::where('email', 'manager.a@levictas.test')->firstOrFail();
        $customer = User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();
        $this->booking = Booking::where('customer_user_id', $customer->id)
            ->orderBy('appointment_date')->orderBy('start_time')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function markNoShow()
    {
        return $this->actingAs($this->manager)
            ->from(route('appointments.index'))
            ->put(route('appointments.updateStatus', $this->booking), ['status' => Booking::NO_SHOW]);
    }

    public function test_after_the_start_time_staff_can_mark_a_no_show_and_payments_stay_as_they_were(): void
    {
        Carbon::setTestNow('2026-10-30 10:20:00');
        $this->booking->forceFill(['status' => 'pending'])->saveQuietly();
        $before = $this->booking->fresh()->only(['amount_paid', 'balance_amount', 'payment_status']);

        $this->markNoShow()->assertSessionHasNoErrors()->assertSessionHas('success', 'Marked as a no-show.');

        $after = $this->booking->fresh();
        $this->assertSame(Booking::NO_SHOW, $after->status);
        $this->assertEquals($before, $after->only(['amount_paid', 'balance_amount', 'payment_status']));
    }

    public function test_it_is_refused_before_the_start_time_and_on_finished_bookings(): void
    {
        // Before 10:00, still reserved.
        $this->markNoShow()->assertSessionHasErrors('status');
        $this->assertSame('reserved', $this->booking->fresh()->status);

        Carbon::setTestNow('2026-10-30 10:20:00');
        foreach (['completed', 'cancelled'] as $status) {
            $this->booking->forceFill(['status' => $status])->saveQuietly();
            $this->markNoShow()->assertSessionHasErrors('status');
            $this->assertSame($status, $this->booking->fresh()->status);
        }
    }

    public function test_a_no_show_stays_put_and_cannot_be_edited_or_cancelled(): void
    {
        Carbon::setTestNow('2026-10-30 12:00:00');   // past the end time
        $this->booking->forceFill(['status' => Booking::NO_SHOW])->saveQuietly();

        // Loading Appointments runs the automatic status sync: it must not turn it into "completed".
        $this->actingAs($this->manager)->get(route('appointments.index'))->assertOk();
        $this->artisan('bookings:sync-statuses')->assertSuccessful();
        $this->assertSame(Booking::NO_SHOW, $this->booking->fresh()->status);

        $owner = User::where('email', 'owner@levictas.test')->firstOrFail();   // has "delete appointments"
        $this->actingAs($owner)->from(route('appointments.index'))
            ->delete(route('appointments.destroy', $this->booking->id))
            ->assertSessionHas('error', 'This appointment is already no-show.');
        $this->assertSame(Booking::NO_SHOW, $this->booking->fresh()->status);
    }

    public function test_the_appointments_page_shows_the_label_not_the_raw_value(): void
    {
        Carbon::setTestNow('2026-10-30 10:20:00');
        $this->booking->forceFill(['status' => Booking::NO_SHOW])->saveQuietly();

        $html = $this->actingAs($this->manager)->get(route('appointments.index'))->assertOk()->getContent();
        $this->assertStringContainsString('No-show', $html);
        $this->assertStringNotContainsString('No_show', $html);
    }

    public function test_the_dashboard_counts_only_real_no_shows(): void
    {
        Carbon::setTestNow('2026-10-30 10:20:00');
        $this->booking->forceFill(['status' => Booking::NO_SHOW])->saveQuietly();

        $other = $this->booking->replicate();
        $other->forceFill(['status' => 'cancelled', 'start_time' => '15:00:00', 'end_time' => '16:00:00', 'arrival_code' => null])->saveQuietly();

        $owner = User::where('email', 'owner@levictas.test')->firstOrFail();
        $data = $this->actingAs($owner)->getJson(route('dashboard.live-data'))->assertOk()->json();

        $this->assertSame(1, $data['alerts']['no_shows']);
        $this->assertSame(1, $data['kpis']['no_show_today']);
        $this->assertSame(1, $data['kpis']['cancelled_today']);
    }

    public function test_label_helper_covers_every_status(): void
    {
        $this->assertSame('No-show', Booking::statusLabel('no_show'));
        $this->assertSame('Reserved', Booking::statusLabel('reserved'));
        $this->assertSame('Something else', Booking::statusLabel('something_else'));
    }
}
