<?php

namespace Tests\Feature\Bookings;

use App\Http\Controllers\LeaveRequestController;
use App\Mail\RescheduleExpiredMail;
use App\Models\Booking;
use App\Models\LeaveRequest;
use App\Models\ReassignmentRequest;
use App\Models\RescheduleRequest;
use App\Models\User;
use App\Services\Navigation\NavBadges;
use Tests\Fixtures\HomeVisitTestSeeder;
use Tests\Fixtures\IdentityVerificationTestSeeder;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Booking lifecycle fix, Phase 3: stale reschedule requests expire at the
 * original start (D5) and stale reassignment requests when the booking ends
 * (D6). Badges escalate (Q2-A, D6), the expiry email goes out (Q3-A), the app
 * sees `rejected` + a new flag until it knows `expired` (Q1-C).
 *
 * HomeVisitTestSeeder at 2026-10-30 09:20: A today 10:00 (Vera), B tomorrow
 * 10:00 (Vera), C tomorrow 14:00 (no therapist). All online home service.
 */
class StaleRequestsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $customer;
    private User $vera;
    private Booking $a;
    private Booking $b;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-30 09:20:00');
        Storage::fake('local');
        Mail::fake();
        Cache::flush();
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);
        $this->seed(HomeVisitTestSeeder::class);

        $this->manager  = User::where('email', 'manager.a@levictas.test')->firstOrFail();
        $this->customer = User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();
        $this->vera     = User::where('email', 'idv.vera@levictas.test')->firstOrFail();

        $bookings = Booking::where('customer_user_id', $this->customer->id)
            ->orderBy('appointment_date')->orderBy('start_time')->get();
        [$this->a, $this->b] = [$bookings[0], $bookings[1]];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function reschedule(Booking $booking, string $date = '2026-11-03', string $time = '11:00:00'): RescheduleRequest
    {
        return RescheduleRequest::create([
            'booking_id' => $booking->id, 'requested_by' => $this->customer->id,
            'requested_date' => $date, 'requested_time' => $time,
            'reason' => 'Something came up at work.', 'status' => 'pending',
        ]);
    }

    private function reassignment(Booking $booking, array $extra = []): ReassignmentRequest
    {
        return ReassignmentRequest::create([
            'booking_id' => $booking->id, 'requested_by' => $this->vera->id,
            'old_therapist_id' => $this->vera->id, 'reason' => 'I am not feeling well today.',
            'status' => 'pending',
        ] + $extra);
    }

    private function sync(): void
    {
        $this->artisan('bookings:sync-statuses')->assertSuccessful();
    }

    // ── Reschedule expiry (D5, Q3-A) ─────────────────────────────────────

    public function test_an_unreviewed_reschedule_expires_at_the_original_start_and_the_customer_is_emailed(): void
    {
        $request = $this->reschedule($this->a);
        $originalDate = $this->a->appointment_date->toDateString();
        $originalStart = $this->a->start_time;

        $this->sync();
        $this->assertSame('pending', $request->fresh()->status);   // before 10:00

        Carbon::setTestNow('2026-10-30 10:05:00');
        $this->sync();

        $this->assertSame(RescheduleRequest::STATUS_EXPIRED, $request->fresh()->status);
        $this->assertSame($originalDate, $this->a->fresh()->appointment_date->toDateString());
        $this->assertSame($originalStart, $this->a->fresh()->start_time);

        Mail::assertSent(RescheduleExpiredMail::class, fn ($mail) => $mail->hasTo($this->customer->email));
        $html = (new RescheduleExpiredMail($request->fresh()))->render();
        $this->assertStringContainsString('reviewed before your appointment', $html);
        $this->assertStringContainsString('please call the branch at', $html);

        // Re-running sends nothing new.
        $this->sync();
        Mail::assertSent(RescheduleExpiredMail::class, 1);
    }

    public function test_a_request_on_a_cancelled_booking_expires_without_an_email(): void
    {
        $request = $this->reschedule($this->b);
        $this->b->forceFill(['status' => 'cancelled'])->saveQuietly();

        $this->sync();

        $this->assertSame(RescheduleRequest::STATUS_EXPIRED, $request->fresh()->status);
        Mail::assertNotSent(RescheduleExpiredMail::class);
    }

    public function test_a_stale_reschedule_cannot_be_approved_even_before_the_job_runs(): void
    {
        $request = $this->reschedule($this->a);
        Carbon::setTestNow('2026-10-30 10:05:00');

        $this->actingAs($this->manager)->postJson(route('reschedule.approve', $request))
            ->assertStatus(422)->assertJsonFragment(['message' => 'This request expired: the appointment has already started or is closed, so the original schedule stands.']);

        $this->assertSame(RescheduleRequest::STATUS_EXPIRED, $request->fresh()->status);
        $this->assertSame('2026-10-30', $this->a->fresh()->appointment_date->toDateString());
        $this->actingAs($this->manager)->getJson(route('reschedule.index'))->assertOk()->assertJsonCount(0);
    }

    public function test_approval_is_refused_when_the_requested_time_has_already_passed(): void
    {
        $request = $this->reschedule($this->b, '2026-10-30', '09:00:00');   // earlier today

        $this->actingAs($this->manager)->postJson(route('reschedule.approve', $request))
            ->assertStatus(422)->assertJsonFragment(['message' => 'The requested date and time have already passed. Please reject and ask the customer to pick a new time.']);
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_reviewers_from_another_branch_cannot_approve_or_reject(): void
    {
        $other = User::where('email', 'manager.b@levictas.test')->firstOrFail();
        $request = $this->reschedule($this->b);
        $reassign = $this->reassignment($this->b);

        $this->actingAs($other)->postJson(route('reschedule.approve', $request))->assertNotFound();
        $this->actingAs($other)->postJson(route('reschedule.reject', $request), ['rejection_reason' => 'Not my branch.'])->assertNotFound();
        $this->actingAs($other)->postJson("/reassignment-requests/{$reassign->id}/reject", ['rejection_reason' => 'Not my branch.'])->assertNotFound();

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame('pending', $reassign->fresh()->status);
    }

    public function test_the_schedule_badge_escalates_for_appointments_today_or_tomorrow(): void
    {
        $badge = fn () => NavBadges::forUser($this->manager, $this->b->branch_id)['schedule'] ?? null;

        $far = $this->b->replicate();
        $far->forceFill(['appointment_date' => '2026-11-05'])->saveQuietly();
        $this->reschedule($far);
        Cache::flush();
        $this->assertFalse($badge()['urgent']);

        $this->reschedule($this->b);   // tomorrow
        Cache::flush();
        $this->assertTrue($badge()['urgent']);
        $this->assertStringContainsString('(1 today or tomorrow)', $badge()['label']);
    }

    // ── Reassignment expiry (D6, Q4-A) ───────────────────────────────────

    public function test_a_reassignment_expires_when_the_booking_ends_and_cannot_be_approved(): void
    {
        $request = $this->reassignment($this->a);
        $twin = $this->a->replicate();
        $twin->saveQuietly();
        $verification = $this->reassignment($twin, ['source' => 'unverified']);

        Carbon::setTestNow('2026-10-30 10:30:00');   // started, not ended
        $this->sync();
        $this->assertSame('pending', $request->fresh()->status);

        Carbon::setTestNow($this->a->endsAt()->copy()->addMinute());
        $this->actingAs($this->manager)->postJson("/reassignment-requests/{$request->id}/approve", ['new_therapist_id' => $this->vera->id])
            ->assertStatus(422)->assertJsonFragment(['message' => 'This request expired: the appointment has already ended or is closed, so its therapist stays as recorded.']);
        $this->assertSame(ReassignmentRequest::STATUS_EXPIRED, $request->fresh()->status);
        $this->assertSame($this->vera->id, $this->a->fresh()->therapist_id);

        $this->sync();
        $this->assertSame(ReassignmentRequest::STATUS_EXPIRED, $verification->fresh()->status);   // Q4-A
    }

    public function test_the_appointments_badge_escalates_for_a_reassignment_tomorrow(): void
    {
        $far = $this->b->replicate();
        $far->forceFill(['appointment_date' => '2026-11-05'])->saveQuietly();
        $this->reassignment($far);
        Cache::flush();
        $badge = NavBadges::forUser($this->manager, $this->b->branch_id)['appointments'] ?? null;
        $this->assertFalse($badge['urgent']);

        $this->reassignment($this->b);
        Cache::flush();
        $badge = NavBadges::forUser($this->manager, $this->b->branch_id)['appointments'] ?? null;
        $this->assertTrue($badge['urgent']);
        $this->assertStringContainsString('2 reassignment requests (1 today or tomorrow)', $badge['label']);
    }

    public function test_a_therapist_cannot_file_cant_make_it_on_a_booking_that_ended(): void
    {
        Carbon::setTestNow($this->a->endsAt()->copy()->addMinute());
        $this->a->forceFill(['status' => 'pending'])->saveQuietly();

        $this->actingAs($this->vera)->postJson("/appointments/{$this->a->id}/reassignment-requests", ['reason' => 'Too late but trying anyway.'])
            ->assertStatus(422);
        $this->assertSame(0, ReassignmentRequest::count());
    }

    // ── Leave flow end to end (F6, Q5-A) ─────────────────────────────────

    public function test_approved_leave_flags_only_bookings_that_have_not_ended_and_they_expire_later(): void
    {
        Carbon::setTestNow($this->a->endsAt()->copy()->addMinute());   // A is over
        $this->a->forceFill(['status' => 'pending'])->saveQuietly();

        $leave = LeaveRequest::create([
            'spa_id' => $this->a->spa_id, 'branch_id' => $this->a->branch_id, 'user_id' => $this->vera->id,
            'leave_type' => 'sick', 'reason' => 'Flu', 'start_date' => '2026-10-30', 'end_date' => '2026-10-31',
            'status' => 'approved',
        ]);

        $flag = new \ReflectionMethod(LeaveRequestController::class, 'autoFlagAffectedBookings');
        $flag->setAccessible(true);
        $flag->invoke(app(LeaveRequestController::class), $leave);

        $this->assertFalse(ReassignmentRequest::where('booking_id', $this->a->id)->exists());   // ended
        $requestB = ReassignmentRequest::where('booking_id', $this->b->id)->firstOrFail();      // tomorrow
        $this->assertSame('pending', $requestB->status);

        // Nobody reassigns it: after B ends, it expires.
        Carbon::setTestNow($this->b->endsAt()->copy()->addMinute());
        $this->sync();
        $this->assertSame(ReassignmentRequest::STATUS_EXPIRED, $requestB->fresh()->status);
    }

    // ── What the apps and the web see (Q1-C) ─────────────────────────────

    public function test_the_app_gets_rejected_plus_an_expired_flag_and_the_web_gets_expired(): void
    {
        $request = $this->reschedule($this->a);
        Carbon::setTestNow('2026-10-30 10:05:00');
        $this->sync();

        // Web (session) — the real value.
        $web = collect($this->actingAs($this->customer)->getJson('/my-appointments')->assertOk()->json())
            ->firstWhere('id', $this->a->id);
        $this->assertSame('expired', $web['reschedule_status']);
        $this->assertTrue($web['reschedule_expired']);
        $this->assertFalse($web['reschedule_pending']);

        // App (Sanctum) — old value + new flag.
        Sanctum::actingAs($this->customer);
        $api = collect($this->getJson('/api/my-appointments')->assertOk()->json())->firstWhere('id', $this->a->id);
        $this->assertSame('rejected', $api['reschedule_status']);
        $this->assertTrue($api['reschedule_expired']);

        // The status endpoints now work for the customer's own booking (were always 403).
        $this->actingAs($this->customer)->getJson(route('reschedule.status', $this->a))
            ->assertOk()->assertJson(['status' => 'expired', 'expired' => true]);
        $this->actingAs($this->customer)->getJson('/reschedule-requests/booking/'.$this->a->id)
            ->assertOk()->assertJson(['status' => 'rejected', 'expired' => true]);
        $this->actingAs($this->manager)->getJson(route('reschedule.status', $this->a))->assertNotFound();
    }

    public function test_the_therapist_app_gets_rejected_plus_an_expired_flag_for_reassignments(): void
    {
        $request = $this->reassignment($this->a);
        Carbon::setTestNow($this->a->endsAt()->copy()->addMinute());
        $this->sync();

        Sanctum::actingAs($this->vera);
        $row = collect($this->getJson('/api/assigned-bookings')->assertOk()->json('bookings'))
            ->firstWhere('id', $this->a->id);
        $this->assertNotNull($row);

        $this->assertSame('rejected', $row['reassignment_status']);
        $this->assertTrue($row['reassignment_expired']);
        $this->assertFalse($row['reassignment_pending']);
    }
}
