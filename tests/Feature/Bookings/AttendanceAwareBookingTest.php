<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\OperatingHours;
use App\Models\ReassignmentRequest;
use App\Models\RescheduleRequest;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Bookings\TherapistAssigner;
use Database\Factories\PayrollUserFactory;
use Database\Seeders\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Attendance-aware booking, Phase 1.
 *
 *   D1/D2, Q2 A  clocked out, marked absent or on leave TODAY = can't be assigned;
 *                not clocked in yet = still bookable; other dates unaffected
 *   Q1 B         a time out blocks only ranges ending after it
 *   Q3 A         slot reason no_therapist_on_shift
 *   Q5 A         editing a booking keeps re-checking its therapist
 *   D3, Q4 B     Start refused; Checkout needs confirm_clocked_out when the
 *                time out came before the start
 *   F5           manual record: no time later than now for today
 *   Q6 A         online checkout refuses a start time that has passed
 *
 * World: PayrollTestAccountsSeeder, Test Branch A, pool = Tess + Theo, 60-min
 * treatment, hours 09:00–21:00. Today Thu 2026-11-12 (both work Thursdays and
 * Fridays on branch hours), now 17:10 unless a test moves the clock.
 */
class AttendanceAwareBookingTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-11-12';
    private const FRI = '2026-11-13';

    private Branch $branch;
    private Treatment $treatment;
    private User $customer;
    private User $manager;
    private User $tess;
    private User $theo;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 17:10:00');
        Storage::fake('local');
        Mail::fake();
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);

        $this->branch = Branch::where('name', 'Test Branch A')->firstOrFail();
        $this->treatment = Treatment::withoutGlobalScopes()->where('branch_id', $this->branch->id)->firstOrFail();
        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
            OperatingHours::create(['branch_id' => $this->branch->id, 'day_of_week' => $day,
                'opening_time' => '09:00:00', 'closing_time' => '21:00:00', 'is_closed' => false]);
        }

        $this->customer = PayrollUserFactory::new()->create(['first_name' => 'Cara', 'email' => 'cara@levictas.test']);
        $this->customer->assignRole('customer');
        $this->manager = User::where('email', 'manager.a@levictas.test')->firstOrFail();
        $this->tess = User::where('email', 'therapist.a1@levictas.test')->firstOrFail();
        $this->theo = User::where('email', 'therapist.a2@levictas.test')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function staffOf(User $u): Staff
    {
        return Staff::where('user_id', $u->id)->firstOrFail();
    }

    /** Writes today's row directly (what a self clock-out or manual record leaves). */
    private function attendance(User $u, array $fields, string $date = self::TODAY): StaffAttendance
    {
        $staff = $this->staffOf($u);

        // Plain date string, as MySQL's DATE column stores it (the model's date cast
        // would write "Y-m-d 00:00:00" in SQLite and break the controllers' lookups).
        DB::table('staff_attendance')->updateOrInsert(
            ['staff_id' => $staff->id, 'date' => $date],
            array_merge(['spa_id' => $staff->spa_id, 'branch_id' => $staff->branch_id, 'status' => 'present', 'source' => 'self',
                'time_in' => null, 'time_out' => null, 'created_at' => now(), 'updated_at' => now()], $fields)
        );

        return StaffAttendance::where('staff_id', $staff->id)->whereDate('date', $date)->firstOrFail();
    }

    private function clockOut(User $u, string $timeOut): StaffAttendance
    {
        return $this->attendance($u, ['time_in' => '09:00:00', 'time_out' => $timeOut]);
    }

    private function booking(?User $therapist, string $date, string $start, string $end, array $extra = []): Booking
    {
        return Booking::create(array_merge([
            'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id, 'therapist_id' => $therapist?->id,
            'status' => 'reserved', 'service_type' => 'in_branch', 'booking_source' => 'walk_in',
            'treatment' => 'treatment_'.$this->treatment->id, 'customer_name' => 'Walk-in Wendy',
            'customer_phone' => '09171234567',
            'total_amount' => 1000, 'amount_paid' => 0, 'balance_amount' => 1000,
            'appointment_date' => $date, 'start_time' => $start, 'end_time' => $end,
        ], $extra));
    }

    private function slotQuery(string $date = self::TODAY): string
    {
        return http_build_query([
            'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id,
            'treatment' => 'treatment_'.$this->treatment->id, 'appointment_date' => $date, 'service_type' => 'in_branch',
        ]);
    }

    private function slot(string $time, string $date = self::TODAY): array
    {
        $data = $this->actingAs($this->customer)->getJson('/bookings/online/available-slots?'.$this->slotQuery($date))->assertOk()->json();

        return collect($data['slots'])->firstWhere('time', $time);
    }

    private function onlineCheckout(string $time, string $date = self::TODAY)
    {
        return $this->actingAs($this->customer)->postJson(route('bookings.online.checkout'), [
            'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id,
            'customer_phone' => '09171234567', 'treatment' => 'treatment_'.$this->treatment->id,
            'service_type' => 'in_branch', 'appointment_date' => $date, 'start_time' => $time,
            'terms_agreed' => '1',
        ]);
    }

    private function assigner(): TherapistAssigner
    {
        return app(TherapistAssigner::class);
    }

    private function pick(string $start, string $end, string $date = self::TODAY): ?User
    {
        return $this->assigner()->pick($this->branch->spa_id, $this->branch->id, $date, $start, $end);
    }

    private function editAs(Booking $b, User $therapist)
    {
        return $this->actingAs($this->manager)->from(route('appointments.index'))->put(route('appointments.update', $b), [
            'customer_name' => $b->customer_name, 'customer_phone' => '09171234567',
            'service_type' => 'in_branch', 'treatment' => $b->treatment, 'therapist_id' => $therapist->id,
            'appointment_date' => $b->appointment_date->toDateString(), 'start_time' => substr($b->start_time, 0, 5),
        ]);
    }

    // ── The groupmate's scenario ─────────────────────────────────────────────

    public function test_groupmates_scenario_clocked_out_therapist_is_not_booked(): void
    {
        // 5:03 PM: the owner/manager records Tess's time out by hand. Theo is off today.
        Carbon::setTestNow('2026-11-12 17:03:30');
        $this->actingAs($this->manager)->post(route('attendance.record', $this->staffOf($this->tess)), [
            'date' => self::TODAY, 'status' => 'present', 'time_in' => '09:00', 'time_out' => '17:03',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->attendance($this->theo, ['status' => 'absent', 'source' => 'manual']);

        // 5:10 PM: the customer looks at 5:30 PM and tries to pay for it.
        Carbon::setTestNow('2026-11-12 17:10:00');
        $slot = $this->slot('17:30');
        $this->assertFalse($slot['available']);
        $this->assertSame('no_therapist_on_shift', $slot['reason']);
        $this->assertSame(0, $slot['free']);

        $this->onlineCheckout('17:30')->assertStatus(422);
        $this->assertNull($this->pick('17:30', '18:00'));   // webhook / Flutter fallback: nobody assigned
    }

    // ── Who is free ──────────────────────────────────────────────────────────

    public function test_time_out_blocks_only_ranges_ending_after_it(): void
    {
        $this->clockOut($this->tess, '13:00:00');
        $b = $this->booking($this->theo, self::TODAY, '18:00:00', '19:00:00');

        $this->assertNull($this->assigner()->rejectionFor($this->tess->id, $b, self::TODAY, '12:00', '13:00'));
        $this->assertSame('Tess clocked out today at 1:00 PM.', $this->assigner()->rejectionFor($this->tess->id, $b, self::TODAY, '12:30', '13:30'));
        $this->assertSame('Tess clocked out today at 1:00 PM.', $this->assigner()->rejectionFor($this->tess->id, $b, self::TODAY, '18:00', '19:00'));
        $this->assertNull($this->assigner()->rejectionFor($this->tess->id, $b, self::FRI, '18:00', '19:00'));  // tomorrow unaffected
    }

    public function test_absent_and_on_leave_hide_the_whole_day(): void
    {
        $this->attendance($this->theo, ['status' => 'absent', 'source' => 'manual']);
        $this->attendance($this->tess, ['status' => 'on_leave', 'source' => 'manual']);
        $b = $this->booking(null, self::TODAY, '18:00:00', '19:00:00');

        $this->assertSame('Theo is marked absent today.', $this->assigner()->rejectionFor($this->theo->id, $b, self::TODAY, '09:00', '10:00'));
        $this->assertSame('Tess is marked on leave today.', $this->assigner()->rejectionFor($this->tess->id, $b, self::TODAY, '18:00', '19:00'));
        $this->assertSame('no_therapist_on_shift', $this->slot('18:00')['reason']);
        $this->assertTrue($this->slot('18:00', self::FRI)['available']);
    }

    public function test_not_clocked_in_or_still_clocked_in_stays_bookable(): void
    {
        $this->assertSame(2, $this->slot('17:30')['free']);

        $this->attendance($this->tess, ['time_in' => '09:00:00', 'status' => 'late']);   // clocked in, no time out
        $this->assertSame(2, $this->slot('17:30')['free']);
    }

    public function test_one_gone_leaves_the_other_and_auto_assign_skips_them(): void
    {
        $this->clockOut($this->tess, '17:03:00');

        $this->assertSame(1, $this->slot('17:30')['free']);
        $this->assertSame($this->theo->id, $this->pick('17:30', '18:30')?->id);

        $this->booking($this->theo, self::TODAY, '17:30:00', '18:30:00');
        $this->assertSame('fully_booked', $this->slot('17:30')['reason']);   // Theo on shift but busy
        $this->assertSame($this->tess->id, $this->pick('17:30', '18:30', self::FRI)?->id);
    }

    public function test_manager_can_undo_a_time_out_with_the_manual_record(): void
    {
        $this->clockOut($this->tess, '17:03:00');
        $this->assertSame(1, $this->slot('17:30')['free']);

        $this->actingAs($this->manager)->post(route('attendance.record', $this->staffOf($this->tess)), [
            'date' => self::TODAY, 'status' => 'present', 'time_in' => '09:00', 'time_out' => '',
        ])->assertSessionHas('success');

        $this->assertSame(2, $this->slot('17:30')['free']);
    }

    public function test_mobile_slots_match_web(): void
    {
        $this->clockOut($this->tess, '17:03:00');
        $this->attendance($this->theo, ['status' => 'absent', 'source' => 'manual']);
        $web = $this->actingAs($this->customer)->getJson('/bookings/online/available-slots?'.$this->slotQuery())->assertOk()->json();

        Sanctum::actingAs($this->customer);
        $api = $this->getJson('/api/bookings/online/available-slots?'.$this->slotQuery())->assertOk()->json();

        $this->assertSame($web['slots'], $api['slots']);
        $this->assertSame('no_therapist_on_shift', collect($api['slots'])->firstWhere('time', '18:00')['reason']);
    }

    public function test_available_days_drop_today_only_when_everyone_is_gone(): void
    {
        $url = '/bookings/online/available-days?branch_id='.$this->branch->id;

        $this->clockOut($this->tess, '17:03:00');
        $this->assertContains(self::TODAY, $this->actingAs($this->customer)->getJson($url)->json('dates'));

        $this->attendance($this->theo, ['status' => 'absent', 'source' => 'manual']);
        $dates = $this->actingAs($this->customer)->getJson($url)->json('dates');
        $this->assertNotContains(self::TODAY, $dates);
        $this->assertContains(self::FRI, $dates);
    }

    // ── Manual choices: edit, reassignment, reschedule ───────────────────────

    public function test_edit_to_or_keeping_a_clocked_out_therapist_is_rejected(): void
    {
        $this->clockOut($this->tess, '17:03:00');

        $theos = $this->booking($this->theo, self::TODAY, '18:00:00', '19:00:00');
        $this->editAs($theos, $this->tess)->assertSessionHasErrors(['therapist_id' => 'Tess clocked out today at 5:03 PM.']);
        $this->assertSame($this->theo->id, (int) $theos->fresh()->therapist_id);

        // Q5 A: still assigned to Tess from before she left → saving it needs a reassignment.
        $tesss = $this->booking($this->tess, self::TODAY, '19:00:00', '20:00:00');
        $this->editAs($tesss, $this->tess)->assertSessionHasErrors('therapist_id');

        // Q1 B: a session she finished before clocking out can still be edited.
        $earlier = $this->booking($this->tess, self::TODAY, '15:00:00', '16:00:00', ['status' => 'pending']);
        $this->editAs($earlier, $this->tess)->assertSessionHasNoErrors();
    }

    public function test_reassignment_approval_to_a_clocked_out_therapist_is_rejected(): void
    {
        $this->clockOut($this->tess, '17:03:00');
        $b = $this->booking($this->theo, self::TODAY, '18:00:00', '19:00:00');
        $r = ReassignmentRequest::forceCreate([
            'booking_id' => $b->id, 'requested_by' => $this->theo->id, 'old_therapist_id' => $this->theo->id,
            'reason' => 'Feeling unwell', 'status' => 'pending',
        ]);

        $res = $this->actingAs($this->manager)->postJson("/reassignment-requests/{$r->id}/approve", ['new_therapist_id' => $this->tess->id])
            ->assertStatus(422);
        $this->assertStringContainsString('Tess clocked out today at 5:03 PM.', json_encode($res->json()));
        $this->assertSame($this->theo->id, (int) $b->fresh()->therapist_id);
    }

    public function test_reschedule_into_today_after_the_time_out_is_refused(): void
    {
        $this->clockOut($this->tess, '17:03:00');
        $b = $this->booking($this->tess, self::FRI, '10:00:00', '11:00:00', ['customer_user_id' => $this->customer->id]);
        $rr = RescheduleRequest::create([
            'booking_id' => $b->id, 'requested_by' => $this->customer->id,
            'requested_date' => self::TODAY, 'requested_time' => '18:00:00', 'reason' => 'Free tonight', 'status' => 'pending',
        ]);

        $res = $this->actingAs($this->manager)->postJson(route('reschedule.approve', $rr))->assertStatus(422);
        $this->assertStringContainsString('Tess clocked out today at 5:03 PM.', $res->json('message'));
        $this->assertSame(self::FRI, $b->fresh()->appointment_date->toDateString());
    }

    // ── Start and Checkout (D3) ──────────────────────────────────────────────

    public function test_start_is_refused_when_the_therapist_is_gone(): void
    {
        $this->clockOut($this->tess, '17:03:00');
        $this->attendance($this->theo, ['status' => 'absent', 'source' => 'manual']);
        $tesss = $this->booking($this->tess, self::TODAY, '17:00:00', '18:00:00', ['status' => 'pending']);
        $theos = $this->booking($this->theo, self::TODAY, '16:30:00', '17:30:00', ['status' => 'pending']);
        $start = fn (Booking $b) => $this->actingAs($this->manager)->from(route('appointments.index'))
            ->put(route('appointments.updateStatus', $b), ['status' => 'ongoing']);

        $start($tesss)->assertSessionHas('error', fn ($m) => str_contains($m, 'Tess clocked out today at 5:03 PM.') && str_contains($m, 'choose another therapist'));
        $start($theos)->assertSessionHas('error', fn ($m) => str_contains($m, 'Theo is marked absent today.'));
        $this->assertSame(['pending', 'pending'], [$tesss->fresh()->status, $theos->fresh()->status]);

        // Not gone → starts as before.
        $this->attendance($this->theo, ['status' => 'present', 'time_in' => '09:00:00']);
        $start($theos)->assertSessionHas('success');
        $this->assertSame('ongoing', $theos->fresh()->status);
    }

    public function test_checkout_needs_confirmation_only_when_the_time_out_came_before_the_start(): void
    {
        $this->clockOut($this->tess, '17:03:00');
        Carbon::setTestNow('2026-11-12 18:10:00');
        $after = $this->booking($this->tess, self::TODAY, '17:30:00', '18:00:00', ['status' => 'pending']);
        $before = $this->booking($this->tess, self::TODAY, '16:00:00', '17:00:00', ['status' => 'pending']);
        $checkout = fn (Booking $b, array $extra = []) => $this->actingAs($this->manager)->from(route('appointments.index'))
            ->put(route('appointments.checkout', $b), ['amount_paid' => 1000] + $extra);

        $checkout($after)->assertSessionHas('error', fn ($m) => str_contains($m, "before this session's 5:30 PM start") && str_contains($m, 'Tick the confirmation box'));
        $this->assertSame('pending', $after->fresh()->status);

        $checkout($after, ['confirm_clocked_out' => '1'])->assertSessionHas('success');
        $this->assertSame('completed', $after->fresh()->status);

        $checkout($before)->assertSessionHas('success');   // done before she left: no confirmation
        $this->assertSame('completed', $before->fresh()->status);
    }

    public function test_checkout_ignores_rows_the_nightly_job_wrote(): void
    {
        $yesterday = '2026-11-11';
        $this->attendance($this->theo, ['status' => 'absent', 'source' => 'system'], $yesterday);
        $b = $this->booking($this->theo, $yesterday, '15:00:00', '16:00:00', ['status' => 'pending']);

        $this->actingAs($this->manager)->from(route('appointments.index'))
            ->put(route('appointments.checkout', $b), ['amount_paid' => 1000])->assertSessionHas('success');
        $this->assertSame('completed', $b->fresh()->status);
    }

    public function test_appointments_page_and_live_data_carry_the_reasons(): void
    {
        $this->clockOut($this->tess, '17:03:00');
        Carbon::setTestNow('2026-11-12 18:10:00');
        $this->booking($this->tess, self::TODAY, '17:30:00', '18:30:00', ['status' => 'pending']);

        $this->actingAs($this->manager)->get(route('appointments.index'))->assertOk()
            ->assertSee('data-start-block="Tess clocked out today at 5:03 PM.', false)
            ->assertSee('data-clockout-warning="Tess clocked out today at 5:03 PM, before this session', false)
            ->assertSee('id="process_confirm_clocked_out"', false);

        $live = collect($this->actingAs($this->manager)->getJson(route('appointments.live-data'))->assertOk()->json('today_appointments'))->first();
        $this->assertStringContainsString('Tess clocked out today', $live['start_block']);
        $this->assertStringContainsString('Check out only if Tess really did this session', $live['clockout_warning']);
    }

    // ── Manual record (F5) and online checkout (Q6 A) ────────────────────────

    public function test_manual_record_refuses_times_later_than_now_today(): void
    {
        $record = fn (array $f) => $this->actingAs($this->manager)->from(route('attendance.index'))
            ->post(route('attendance.record', $this->staffOf($this->tess)), $f + ['status' => 'present', 'time_in' => '09:00']);

        $record(['date' => self::TODAY, 'time_out' => '18:00'])
            ->assertSessionHas('error', "Time out can't be later than the current time (5:10 PM).");
        $this->assertNull(StaffAttendance::where('staff_id', $this->staffOf($this->tess)->id)->whereDate('date', self::TODAY)->first());

        $record(['date' => self::TODAY, 'time_in' => '17:30'])->assertSessionHas('error', fn ($m) => str_starts_with($m, 'Time in'));

        $record(['date' => self::TODAY, 'time_out' => '17:10'])->assertSessionHas('success');   // the current minute is fine
        $record(['date' => '2026-11-08', 'time_out' => '20:00'])->assertSessionHas('success');  // earlier day (no seeded row): any time
    }

    public function test_online_checkout_refuses_a_start_time_that_has_passed(): void
    {
        $this->onlineCheckout('17:00')->assertStatus(422)->assertJson(['message' => 'That time has already passed. Please choose a later time.']);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/bookings/online/checkout', [
            'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id,
            'customer_phone' => '09171234567', 'treatment' => 'treatment_'.$this->treatment->id,
            'service_type' => 'in_branch', 'appointment_date' => self::TODAY, 'start_time' => '17:00',
        ])->assertStatus(422)->assertJson(['message' => 'That time has already passed. Please choose a later time.']);
    }
}
