<?php

namespace Tests\Feature\Identity;

use App\Models\Booking;
use App\Models\RescheduleRequest;
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
 * UI rework Phase 1: a customer can ask to reschedule only before the
 * appointment starts. Booking A from HomeVisitTestSeeder starts at 10:00 today
 * (seeded with the clock at 09:20).
 */
class RescheduleWindowTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
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

        $this->customer = User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();
        $this->booking = Booking::where('customer_user_id', $this->customer->id)
            ->orderBy('appointment_date')->orderBy('start_time')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ask(array $overrides = [])
    {
        return $this->actingAs($this->customer)->postJson('/reschedule-requests', $overrides + [
            'booking_id'     => $this->booking->id,
            'requested_date' => '2026-11-02',
            'requested_time' => '11:00',
            'reason'         => 'A work meeting came up that morning.',
        ]);
    }

    public function test_before_the_start_time_a_reserved_booking_can_be_rescheduled(): void
    {
        $this->assertSame('10:00:00', $this->booking->start_time);
        $this->assertTrue($this->booking->canRequestReschedule());

        $this->ask()->assertOk();
        $this->assertSame(1, RescheduleRequest::where('booking_id', $this->booking->id)->count());
    }

    public function test_once_the_start_time_passes_it_is_refused_even_if_the_status_is_still_reserved(): void
    {
        // The status sync hasn't run, so the row still says 'reserved'.
        Carbon::setTestNow('2026-10-30 10:05:00');
        $this->assertSame('reserved', $this->booking->fresh()->status);
        $this->assertFalse($this->booking->fresh()->canRequestReschedule());

        $this->ask()->assertStatus(422)->assertJsonFragment([
            'message' => 'This appointment has already started or ended, so it can no longer be rescheduled. Please call the branch.',
        ]);
        $this->assertSame(0, RescheduleRequest::count());
    }

    public function test_pending_ongoing_and_finished_bookings_are_refused(): void
    {
        foreach (['pending', 'ongoing', 'completed', 'cancelled'] as $status) {
            $this->booking->forceFill(['status' => $status])->saveQuietly();
            $this->assertFalse($this->booking->fresh()->canRequestReschedule(), $status);
            $this->ask()->assertStatus(422);
        }
        $this->assertSame(0, RescheduleRequest::count());
    }

    public function test_a_new_time_earlier_today_is_refused(): void
    {
        $this->ask(['requested_date' => '2026-10-30', 'requested_time' => '09:00'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Please choose a time that hasn\'t passed yet.']);
    }

    public function test_the_appointments_feed_tells_the_page_and_the_app_whether_it_can_be_rescheduled(): void
    {
        $row = collect($this->actingAs($this->customer)->getJson('/my-appointments')->assertOk()->json())
            ->firstWhere('id', $this->booking->id);
        $this->assertTrue($row['can_reschedule']);

        Carbon::setTestNow('2026-10-30 10:05:00');
        $row = collect($this->actingAs($this->customer)->getJson('/my-appointments')->assertOk()->json())
            ->firstWhere('id', $this->booking->id);
        $this->assertFalse($row['can_reschedule']);
    }
}
