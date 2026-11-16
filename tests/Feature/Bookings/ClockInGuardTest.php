<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\OperatingHours;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Attendance\LateClockInReview;
use App\Services\Staff\WorkScheduleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Tests\TestCase;

/**
 * Clock-in guard (Nov 16, night before the final defense).
 *
 *   D1  Start needs the therapist's time in today; booking does not.
 *   D2  Book an Appointment: today's list says who clocked in, lists them
 *       first and pre-selects the least busy of them; nobody is hidden.
 *   D3  A self clock-in 30+ min after shift start needs review (Dashboard
 *       alert for edit attendance, roster Review button).
 *   D4  Changing a self clock-in's time needs a remark; the system time is
 *       kept in the remarks and the record becomes manual (flag cleared).
 *
 * World as AttendanceAwareBookingTest: Test Branch A, Tess + Theo on branch
 * hours 09:00–21:00, today Thu 2026-11-12, now 17:10.
 */
class ClockInGuardTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-11-12';
    private const FRI = '2026-11-13';

    private Branch $branch;
    private Treatment $treatment;
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

    private function attendance(User $u, array $fields, string $date = self::TODAY): StaffAttendance
    {
        $staff = $this->staffOf($u);
        DB::table('staff_attendance')->updateOrInsert(
            ['staff_id' => $staff->id, 'date' => $date],
            array_merge(['spa_id' => $staff->spa_id, 'branch_id' => $staff->branch_id, 'status' => 'present', 'source' => 'self',
                'time_in' => null, 'time_out' => null, 'created_at' => now(), 'updated_at' => now()], $fields)
        );

        return StaffAttendance::where('staff_id', $staff->id)->whereDate('date', $date)->firstOrFail();
    }

    private function booking(User $therapist, string $date, string $start, string $end, array $extra = []): Booking
    {
        return Booking::create(array_merge([
            'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id, 'therapist_id' => $therapist->id,
            'status' => 'pending', 'service_type' => 'in_branch', 'booking_source' => 'walk_in',
            'treatment' => 'treatment_'.$this->treatment->id, 'customer_name' => 'Walk-in Wendy',
            'customer_phone' => '09171234567',
            'total_amount' => 1000, 'amount_paid' => 0, 'balance_amount' => 1000,
            'appointment_date' => $date, 'start_time' => $start, 'end_time' => $end,
        ], $extra));
    }

    private function start(Booking $b)
    {
        return $this->actingAs($this->manager)->from(route('appointments.index'))
            ->put(route('appointments.updateStatus', $b), ['status' => 'ongoing']);
    }

    private function therapistList(string $date, string $time): array
    {
        return $this->actingAs($this->manager)->getJson(route('booking.available-therapists', [
            'appointment_date' => $date, 'start_time' => $time, 'treatment' => 'treatment_'.$this->treatment->id,
        ]))->assertOk()->json();
    }

    private function shiftToday(User $u)
    {
        return app(WorkScheduleResolver::class)->forDate($this->staffOf($u), self::TODAY, $this->branch->id);
    }

    // ── D1: Start needs a clock-in ───────────────────────────────────────────

    public function test_start_is_refused_until_the_therapist_clocks_in(): void
    {
        $b = $this->booking($this->tess, self::TODAY, '17:00:00', '18:00:00');

        $this->start($b)->assertSessionHas('error', fn ($m) => str_contains($m, "Tess hasn't clocked in yet today."));
        $this->assertSame('pending', $b->fresh()->status);

        $this->attendance($this->tess, ['time_in' => '09:02:00']);
        $this->start($b)->assertSessionHas('success');
        $this->assertSame('ongoing', $b->fresh()->status);
    }

    public function test_the_appointments_page_carries_the_not_clocked_in_reason(): void
    {
        $this->booking($this->tess, self::TODAY, '17:00:00', '18:00:00');

        $this->actingAs($this->manager)->get(route('appointments.index'))->assertOk()
            ->assertSee("Tess hasn&#039;t clocked in yet today.", false);
    }

    public function test_a_clocked_out_therapist_still_gets_the_clocked_out_message_first(): void
    {
        $this->attendance($this->tess, ['time_in' => '09:00:00', 'time_out' => '16:00:00']);
        $b = $this->booking($this->tess, self::TODAY, '17:00:00', '18:00:00');

        $this->start($b)->assertSessionHas('error', fn ($m) => str_contains($m, 'Tess clocked out today at 4:00 PM.'));
    }

    // ── D2: Book an Appointment list ─────────────────────────────────────────

    public function test_today_list_labels_and_prefers_clocked_in_therapists(): void
    {
        // Theo is clocked in but busier; Tess is free all day but hasn't clocked in.
        $this->attendance($this->theo, ['time_in' => '09:00:00']);
        $this->booking($this->theo, self::TODAY, '10:00:00', '11:00:00', ['status' => 'completed']);

        $data = $this->therapistList(self::TODAY, '18:00');
        $byId = collect($data['therapists'])->keyBy('id');

        $this->assertCount(2, $data['therapists'], 'nobody is hidden');
        $this->assertTrue($byId[$this->theo->id]['clocked_in']);
        $this->assertFalse($byId[$this->tess->id]['clocked_in']);
        $this->assertSame($this->theo->id, $data['therapists'][0]['id'], 'clocked-in first');
        $this->assertSame($this->theo->id, $data['recommended_id'], 'least busy among the clocked-in');
    }

    public function test_today_list_falls_back_to_least_busy_when_nobody_clocked_in(): void
    {
        $this->booking($this->theo, self::TODAY, '10:00:00', '11:00:00', ['status' => 'completed']);

        $data = $this->therapistList(self::TODAY, '18:00');

        $this->assertSame($this->tess->id, $data['recommended_id']);
    }

    public function test_other_days_have_no_clock_in_label(): void
    {
        $data = $this->therapistList(self::FRI, '10:00');

        $this->assertNotEmpty($data['therapists']);
        $this->assertNull($data['therapists'][0]['clocked_in']);
    }

    // ── D3: late clock-in review ─────────────────────────────────────────────

    public function test_review_threshold_is_thirty_minutes_after_shift_start(): void
    {
        $shift = $this->shiftToday($this->tess);

        $this->assertSame(30, LateClockInReview::minutesLate($this->attendance($this->tess, ['time_in' => '09:30:00', 'status' => 'late']), $shift));
        $this->assertNull(LateClockInReview::minutesLate($this->attendance($this->tess, ['time_in' => '09:29:00', 'status' => 'late']), $shift));
        $this->assertNull(LateClockInReview::minutesLate(
            $this->attendance($this->tess, ['time_in' => '10:40:00', 'status' => 'late', 'source' => 'manual']), $shift),
            'a manual record is already reviewed');
    }

    public function test_dashboard_and_roster_show_the_review(): void
    {
        $this->attendance($this->tess, ['time_in' => '10:40:00', 'status' => 'late']);
        $this->attendance($this->theo, ['time_in' => '09:05:00']);

        $this->assertSame(1, app(LateClockInReview::class)->countToday($this->branch->spa_id, $this->branch->id));

        $this->actingAs($this->manager)->get(route('dashboard'))->assertOk()
            ->assertSee('1 Clock-in to Review', false);
        $this->actingAs($this->manager)->getJson(route('dashboard.live-data'))->assertOk()
            ->assertJsonPath('alerts.late_clock_in_reviews', 1);

        $this->actingAs($this->manager)->get(route('attendance.index'))->assertOk()
            ->assertSee('Clocked in 1 h 40 min after shift start: late or forgot?', false)
            ->assertSee('Review Clock-in', false);
    }

    // ── D4: correcting a self clock-in ───────────────────────────────────────

    public function test_changing_a_self_clock_in_needs_a_remark_and_keeps_the_system_time(): void
    {
        $this->attendance($this->tess, ['time_in' => '10:40:00', 'status' => 'late']);
        $post = fn (array $extra) => $this->actingAs($this->manager)->from(route('attendance.index'))
            ->post(route('attendance.record', $this->staffOf($this->tess)), array_merge([
                'date' => self::TODAY, 'status' => 'present', 'time_in' => '09:55',
            ], $extra));

        $post([])->assertSessionHas('error', 'Add a remark saying why the clock time was changed.');
        $this->assertSame('self', StaffAttendance::where('staff_id', $this->staffOf($this->tess)->id)->whereDate('date', self::TODAY)->firstOrFail()->source);

        $post(['remarks' => 'Forgot to clock in; front desk saw her arrive'])->assertSessionHas('success');
        $row = StaffAttendance::where('staff_id', $this->staffOf($this->tess)->id)->whereDate('date', self::TODAY)->firstOrFail();
        $this->assertSame('manual', $row->source);
        $this->assertSame('System clock-in 10:40 AM | Forgot to clock in; front desk saw her arrive', $row->remarks);
        $this->assertSame(0, app(LateClockInReview::class)->countToday($this->branch->spa_id, $this->branch->id));
    }

    public function test_confirming_a_late_clock_in_needs_no_remark(): void
    {
        $this->attendance($this->tess, ['time_in' => '10:40:00', 'status' => 'late']);

        $this->actingAs($this->manager)->from(route('attendance.index'))
            ->post(route('attendance.record', $this->staffOf($this->tess)), [
                'date' => self::TODAY, 'status' => 'late', 'time_in' => '10:40',
            ])->assertSessionHas('success');

        $row = StaffAttendance::where('staff_id', $this->staffOf($this->tess)->id)->whereDate('date', self::TODAY)->firstOrFail();
        $this->assertSame('manual', $row->source);
        $this->assertNull($row->remarks);
    }
}
