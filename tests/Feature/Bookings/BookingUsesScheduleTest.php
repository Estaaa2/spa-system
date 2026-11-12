<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\OperatingHours;
use App\Models\ReassignmentRequest;
use App\Models\RescheduleRequest;
use App\Models\Staff;
use App\Models\StaffScheduleOverride;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Bookings\TherapistAssigner;
use App\Services\Staff\WorkScheduleWriter;
use Database\Factories\PayrollUserFactory;
use Database\Seeders\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Staff scheduling Phase 3 — booking uses the work schedule.
 * World: PayrollTestAccountsSeeder, Test Branch A, pool = Tess + Theo, a 60-min
 * treatment, hours 09:00–21:00 every day. Today Thu 2026-11-12;
 * Fri Nov 13 · Sat Nov 14 · Sun Nov 15.
 */
class BookingUsesScheduleTest extends TestCase
{
    use RefreshDatabase;

    private const SAT = '2026-11-14';

    private Branch $branch;
    private Treatment $treatment;
    private User $customer;
    private User $manager;
    private User $tess;
    private User $theo;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 08:00:00');
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

    private function staffOf(User $u): Staff
    {
        return Staff::where('user_id', $u->id)->firstOrFail();
    }

    /** Every day on branch hours except $rest; $custom = [weekday => [start, end]]. */
    private function pattern(User $u, array $rest = [], array $custom = []): void
    {
        $days = [];
        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $d) {
            $days[$d] = in_array($d, $rest, true) ? ['mode' => 'rest']
                : (isset($custom[$d]) ? ['mode' => 'custom', 'start' => $custom[$d][0], 'end' => $custom[$d][1]] : ['mode' => 'branch']);
        }
        app(WorkScheduleWriter::class)->savePattern($this->staffOf($u), '2026-11-12', $days, null);
    }

    private function booking(User $therapist, string $date, string $start, string $end, array $extra = []): Booking
    {
        return Booking::create(array_merge([
            'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id, 'therapist_id' => $therapist->id,
            'status' => 'reserved', 'service_type' => 'in_branch', 'booking_source' => 'walk_in',
            'treatment' => 'treatment_'.$this->treatment->id, 'customer_name' => 'Walk-in Wendy',
            'appointment_date' => $date, 'start_time' => $start, 'end_time' => $end,
        ], $extra));
    }

    private function slotQuery(string $date = self::SAT): string
    {
        return http_build_query([
            'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id,
            'treatment' => 'treatment_'.$this->treatment->id, 'appointment_date' => $date, 'service_type' => 'in_branch',
        ]);
    }

    private function slots(string $date = self::SAT): array
    {
        return $this->actingAs($this->customer)->getJson('/bookings/online/available-slots?'.$this->slotQuery($date))->assertOk()->json();
    }

    private function slot(array $data, string $time): array
    {
        return collect($data['slots'])->firstWhere('time', $time);
    }

    // ── Slot grid and date list ──────────────────────────────────────────────

    public function test_without_schedules_branch_hours_and_pay_profile_rest_days_apply(): void
    {
        // Seeded pay profiles: Tess rests Sunday, Theo Saturday + Sunday (Phase 4 fallback).
        $fri = $this->slot($this->slots('2026-11-13'), '10:00');
        $this->assertTrue($fri['available']);
        $this->assertSame(2, $fri['free']);

        $this->assertSame(1, $this->slot($this->slots(), '10:00')['free']);   // Saturday: Theo rests
        $this->assertSame('no_therapist_on_shift', $this->slot($this->slots('2026-11-15'), '10:00')['reason']); // Sunday
    }

    public function test_slots_follow_shifts_and_say_when_nobody_is_working(): void
    {
        $this->pattern($this->tess, custom: ['Saturday' => ['13:00', '21:00']]);
        $this->pattern($this->theo, rest: ['Saturday']);

        $data = $this->slots();
        $this->assertFalse($this->slot($data, '10:00')['available']);
        $this->assertSame('no_therapist_on_shift', $this->slot($data, '10:00')['reason']);
        $this->assertSame('no_therapist_on_shift', $this->slot($data, '12:30')['reason']);  // ends 13:30, starts before the shift
        $this->assertTrue($this->slot($data, '13:00')['available']);
        $this->assertSame(1, $this->slot($data, '13:00')['free']);

        $this->booking($this->tess, self::SAT, '13:00:00', '14:00:00');
        $this->assertSame('fully_booked', $this->slot($this->slots(), '13:00')['reason']);  // on shift but busy
    }

    public function test_one_off_changes_apply_to_slots(): void
    {
        $writer = app(WorkScheduleWriter::class);
        $writer->saveOverride($this->staffOf($this->tess), self::SAT, StaffScheduleOverride::DAY_OFF, null, null, null, null);
        $writer->saveOverride($this->staffOf($this->theo), self::SAT, StaffScheduleOverride::SHIFT, '15:00', '18:00', null, null);

        $data = $this->slots();
        $this->assertSame('no_therapist_on_shift', $this->slot($data, '10:00')['reason']);
        $this->assertTrue($this->slot($data, '15:00')['available']);
        $this->assertSame('no_therapist_on_shift', $this->slot($data, '17:30')['reason']);
    }

    public function test_available_days_leave_out_days_nobody_works(): void
    {
        $this->pattern($this->tess, rest: ['Saturday']);
        $this->pattern($this->theo, rest: ['Saturday', 'Sunday']);

        $dates = $this->actingAs($this->customer)->getJson('/bookings/online/available-days?branch_id='.$this->branch->id)
            ->assertOk()->json('dates');

        $this->assertContains('2026-11-13', $dates);
        $this->assertNotContains(self::SAT, $dates);
        $this->assertContains('2026-11-15', $dates);  // Tess works Sundays
        $this->assertNotContains('2026-11-21', $dates);
    }

    public function test_mobile_slots_match_web(): void
    {
        $this->pattern($this->tess, custom: ['Saturday' => ['13:00', '21:00']]);
        $this->pattern($this->theo, rest: ['Saturday']);
        $web = $this->slots();

        Sanctum::actingAs($this->customer);
        $api = $this->getJson('/api/bookings/online/available-slots?'.$this->slotQuery())->assertOk()->json();

        $this->assertSame($web['slots'], $api['slots']);
    }

    // ── Assignment ───────────────────────────────────────────────────────────

    public function test_pick_never_chooses_someone_off_shift(): void
    {
        $this->pattern($this->tess, rest: ['Saturday']);
        $this->pattern($this->theo, rest: ['Sunday']);   // overrides his pay-profile Saturday rest day
        $assigner = app(TherapistAssigner::class);

        $this->assertSame($this->theo->id, $assigner->pick($this->branch->spa_id, $this->branch->id, self::SAT, '10:00', '11:00')?->id);

        app(WorkScheduleWriter::class)->saveOverride($this->staffOf($this->theo), self::SAT, StaffScheduleOverride::DAY_OFF, null, null, null, null);
        $this->assertNull($assigner->pick($this->branch->spa_id, $this->branch->id, self::SAT, '10:00', '11:00'));
    }

    public function test_rejection_messages_name_the_reason(): void
    {
        $this->pattern($this->tess, rest: ['Saturday'], custom: ['Friday' => ['13:00', '21:00']]);
        app(WorkScheduleWriter::class)->saveOverride($this->staffOf($this->theo), self::SAT, StaffScheduleOverride::DAY_OFF, null, null, null, null);
        $assigner = app(TherapistAssigner::class);
        $b = $this->booking($this->theo, '2026-11-13', '10:00:00', '11:00:00');

        $this->assertStringContainsString("It's Tess's rest day", $assigner->rejectionFor($this->tess->id, $b, self::SAT, '10:00', '11:00'));
        $this->assertStringContainsString("Outside Tess's shift (1:00 PM – 9:00 PM)", $assigner->rejectionFor($this->tess->id, $b, '2026-11-13', '10:00', '11:00'));
        $this->assertNull($assigner->rejectionFor($this->tess->id, $b, '2026-11-13', '13:00', '14:00'));
        $this->assertStringContainsString('Theo has a day off', $assigner->rejectionFor($this->theo->id, $b, self::SAT, '10:00', '11:00'));
        $this->booking($this->tess, '2026-11-13', '13:00:00', '14:00:00');
        $this->assertStringContainsString('Tess already has another booking', $assigner->rejectionFor($this->tess->id, $b, '2026-11-13', '13:30', '14:30'));
    }

    // ── Staff booking (Q1 A: block) ──────────────────────────────────────────

    public function test_staff_booking_off_shift_is_blocked_with_the_reason(): void
    {
        $this->pattern($this->theo, rest: ['Saturday']);
        $post = fn (User $t, string $time) => $this->actingAs($this->manager)->post(route('bookings.store'), [
            'service_type' => 'in_branch', 'treatment' => 'treatment_'.$this->treatment->id,
            'therapist_id' => $t->id, 'customer_name' => 'Walk-in Wendy', 'customer_phone' => '09171234567',
            'appointment_date' => self::SAT, 'start_time' => $time,
        ]);

        $before = Booking::count();
        $post($this->theo, '10:00')->assertSessionHasErrors(['therapist_id']);
        $this->assertStringContainsString("Theo's rest day", session('errors')->first('therapist_id'));
        $this->assertSame($before, Booking::count());

        $post($this->tess, '10:00')->assertSessionHasNoErrors();
        $this->assertSame($before + 1, Booking::count());
    }

    // ── Schedule changes flag bookings (Q2 A) ────────────────────────────────

    public function test_schedule_change_flags_bookings_and_closes_them_when_fixed(): void
    {
        $booking = $this->booking($this->tess, self::SAT, '10:00:00', '11:00:00');
        $save = fn (array $sat) => $this->actingAs($this->manager)->putJson(route('staff.schedule.update', $this->staffOf($this->tess)), [
            'effective_from' => '2026-11-12',
            // Tess is daily-paid: keep Sunday as a rest day (1–2 rest days, Phase 4).
            'days' => array_merge(array_fill_keys(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], ['mode' => 'branch']), ['Saturday' => $sat, 'Sunday' => ['mode' => 'rest']]),
        ]);

        $res = $save(['mode' => 'rest'])->assertOk()->assertJsonPath('flagged', 1);
        $this->assertStringContainsString('sent to Reassignment Requests', $res->json('message'));
        $r = ReassignmentRequest::where('booking_id', $booking->id)->sole();
        $this->assertSame(['schedule', 'pending', $this->tess->id], [$r->source, $r->status, (int) $r->old_therapist_id]);
        $this->assertSame($this->tess->id, (int) $booking->fresh()->therapist_id);  // nothing moved

        $save(['mode' => 'rest'])->assertOk()->assertJsonPath('flagged', 0);           // no duplicate

        $save(['mode' => 'branch'])->assertOk();
        $this->assertSame('rejected', $r->fresh()->status);
        $this->assertStringContainsString('Closed automatically', $r->fresh()->rejection_reason);
    }

    public function test_one_off_day_off_flags_and_removing_it_closes(): void
    {
        $booking = $this->booking($this->tess, self::SAT, '10:00:00', '11:00:00');
        $staff = $this->staffOf($this->tess);

        $this->actingAs($this->manager)->postJson(route('staff.schedule.overrides.store', $staff), ['date' => self::SAT, 'type' => 'day_off'])
            ->assertOk()->assertJsonPath('flagged', 1);
        $override = StaffScheduleOverride::sole();
        $this->actingAs($this->manager)->deleteJson(route('staff.schedule.overrides.destroy', [$staff, $override]))->assertOk();

        $this->assertSame('rejected', ReassignmentRequest::where('booking_id', $booking->id)->value('status'));
    }

    public function test_approving_a_flag_moves_it_to_someone_on_shift_only(): void
    {
        $this->pattern($this->theo, rest: ['Saturday']);
        $booking = $this->booking($this->tess, self::SAT, '10:00:00', '11:00:00');
        $this->actingAs($this->manager)->postJson(route('staff.schedule.overrides.store', $this->staffOf($this->tess)), ['date' => self::SAT, 'type' => 'day_off'])->assertOk();
        $r = ReassignmentRequest::sole();

        $this->actingAs($this->manager)->postJson("/reassignment-requests/{$r->id}/approve", ['new_therapist_id' => $this->theo->id])->assertStatus(422);
        $this->assertSame($this->tess->id, (int) $booking->fresh()->therapist_id);
    }

    // ── Customer reschedule approval (Q4 A) ──────────────────────────────────

    public function test_reschedule_approval_refused_when_therapist_is_off_shift(): void
    {
        $this->pattern($this->tess, rest: ['Saturday']);
        $booking = $this->booking($this->tess, '2026-11-13', '10:00:00', '11:00:00', ['customer_user_id' => $this->customer->id]);
        $rr = RescheduleRequest::create([
            'booking_id' => $booking->id, 'requested_by' => $this->customer->id,
            'requested_date' => self::SAT, 'requested_time' => '10:00:00', 'reason' => 'Work', 'status' => 'pending',
        ]);

        $res = $this->actingAs($this->manager)->postJson(route('reschedule.approve', $rr))->assertStatus(422);
        $this->assertStringContainsString("Tess's rest day", $res->json('message'));
        $this->assertStringContainsString('Reassign the booking', $res->json('message'));
        $this->assertSame('2026-11-13', $booking->fresh()->appointment_date->toDateString());
    }

    // ── Schedule page and dead code ──────────────────────────────────────────

    public function test_schedule_page_has_a_shifts_roster(): void
    {
        $this->pattern($this->tess, rest: ['Saturday'], custom: ['Friday' => ['13:00', '21:00']]);

        $this->actingAs($this->manager)->get(route('schedule.index', ['week' => '2026-11-09']))
            ->assertOk()
            ->assertSee('Therapist Shifts')
            ->assertSee('id="shiftRosterTrigger"', false)
            ->assertSee('Rest day')
            ->assertSeeInOrder(['1:00 PM', '– 9:00 PM']);
    }

    public function test_dead_availability_routes_and_code_are_gone(): void
    {
        $this->assertFalse(Route::has('staff.availability'));
        $this->assertFileDoesNotExist(app_path('Http/Controllers/StaffAvailabilityController.php'));
        $this->assertFileDoesNotExist(app_path('Models/StaffAvailability.php'));
        $this->assertFileDoesNotExist(resource_path('views/staff/availability/index.blade.php'));
        $this->assertFalse(method_exists(\App\Http\Controllers\Api\FlutterBookingController::class, 'createBooking'));
    }
}
