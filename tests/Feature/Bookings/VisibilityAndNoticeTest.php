<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\OperatingHours;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Attendance\TherapistDayStatus;
use App\Services\Staff\ScheduledDay;
use Database\Factories\PayrollUserFactory;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Attendance-aware booking, Phase 3: visibility and online lead time.
 *
 *   D5, Q1 B  customer label: pending = "Waiting to start", ongoing = "In progress"
 *   D7, Q2 A  30-minute online notice: slot reason too_soon, checkout refused
 *             (web + API); staff bookings unaffected
 *   D6, Q3 A  dashboard Therapist Status: attendance first, one badge + note
 *
 * World: PayrollTestAccountsSeeder, Test Branch A, Tess + Theo on branch hours
 * 09:00–21:00. Today Thu 2026-11-12, now 17:10.
 */
class VisibilityAndNoticeTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-11-12';

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

    private function query(string $date = self::TODAY): string
    {
        return http_build_query([
            'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id,
            'treatment' => 'treatment_'.$this->treatment->id, 'appointment_date' => $date, 'service_type' => 'in_branch',
        ]);
    }

    private function slots(string $date = self::TODAY): array
    {
        return $this->actingAs($this->customer)->getJson('/bookings/online/available-slots?'.$this->query($date))->assertOk()->json();
    }

    private function slot(string $time, string $date = self::TODAY): array
    {
        return collect($this->slots($date)['slots'])->firstWhere('time', $time);
    }

    private function checkout(string $time)
    {
        return $this->actingAs($this->customer)->postJson(route('bookings.online.checkout'), [
            'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id,
            'customer_phone' => '09171234567', 'treatment' => 'treatment_'.$this->treatment->id,
            'service_type' => 'in_branch', 'appointment_date' => self::TODAY, 'start_time' => $time, 'terms_agreed' => '1',
        ]);
    }

    private function attendance(User $u, array $fields): void
    {
        $staff = Staff::where('user_id', $u->id)->firstOrFail();
        DB::table('staff_attendance')->insert(array_merge([
            'staff_id' => $staff->id, 'spa_id' => $staff->spa_id, 'branch_id' => $staff->branch_id,
            'date' => self::TODAY, 'status' => 'present', 'source' => 'self', 'created_at' => now(), 'updated_at' => now(),
        ], $fields));
    }

    private function booking(User $t, string $start, string $end, string $status): Booking
    {
        return Booking::create([
            'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id, 'therapist_id' => $t->id,
            'status' => $status, 'service_type' => 'in_branch', 'booking_source' => 'walk_in',
            'treatment' => 'treatment_'.$this->treatment->id, 'customer_name' => 'Walk-in Wendy',
            'appointment_date' => self::TODAY, 'start_time' => $start, 'end_time' => $end,
        ]);
    }

    // ── D7: online minimum notice ────────────────────────────────────────────

    public function test_slot_grid_marks_the_notice_window_too_soon(): void
    {
        $data = $this->slots();
        $this->assertSame(30, $data['min_notice_minutes']);
        $at = fn (string $t) => collect($data['slots'])->firstWhere('time', $t);

        $this->assertSame('past', $at('17:00')['reason']);
        $this->assertSame(['too_soon', false], [$at('17:30')['reason'], $at('17:30')['available']]);
        $this->assertTrue($at('18:00')['available']);

        Carbon::setTestNow('2026-11-12 17:00:00');   // exactly 30 minutes ahead is allowed
        $this->assertTrue($this->slot('17:30')['available']);

        $this->assertTrue($this->slot('09:00', '2026-11-13')['available']);   // other days: no window
    }

    public function test_mobile_slots_match_web(): void
    {
        $web = $this->slots();
        Sanctum::actingAs($this->customer);
        $api = $this->getJson('/api/bookings/online/available-slots?'.$this->query())->assertOk()->json();

        $this->assertSame($web['slots'], $api['slots']);
        $this->assertSame(30, $api['min_notice_minutes']);
    }

    public function test_checkout_refuses_a_start_inside_the_window_on_web_and_api(): void
    {
        $msg = "Online bookings need at least 30 minutes' notice. Please choose a later time, or call the spa.";
        $this->checkout('17:30')->assertStatus(422)->assertJson(['message' => $msg]);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/bookings/online/checkout', [
            'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id,
            'customer_phone' => '09171234567', 'treatment' => 'treatment_'.$this->treatment->id,
            'service_type' => 'in_branch', 'appointment_date' => self::TODAY, 'start_time' => '17:30',
        ])->assertStatus(422)->assertJson(['message' => $msg]);
    }

    public function test_notice_is_a_config_value(): void
    {
        config(['booking.online_min_notice_minutes' => 60]);

        $this->assertSame('too_soon', $this->slot('18:00')['reason']);
        $this->assertTrue($this->slot('18:30')['available']);
        $this->checkout('18:00')->assertStatus(422)->assertJson(['message' => "Online bookings need at least 60 minutes' notice. Please choose a later time, or call the spa."]);
    }

    public function test_staff_bookings_are_not_affected(): void
    {
        $before = Booking::count();
        $this->actingAs($this->manager)->post(route('bookings.store'), [
            'service_type' => 'in_branch', 'treatment' => 'treatment_'.$this->treatment->id,
            'therapist_id' => $this->tess->id, 'customer_name' => 'Walk-in Wendy', 'customer_phone' => '09171234567',
            'appointment_date' => self::TODAY, 'start_time' => '17:30',
        ])->assertSessionHasNoErrors();

        $this->assertSame($before + 1, Booking::count());
    }

    // ── D5: customer label ───────────────────────────────────────────────────

    public function test_customer_label_for_pending_is_waiting_to_start(): void
    {
        $js = file_get_contents(resource_path('js/welcome.js'));

        $this->assertStringContainsString("if (s === 'pending')                           return { key: 'waiting',   label: 'Waiting to start' };", $js);
        $this->assertStringContainsString("if (s === 'ongoing')                           return { key: 'progress',  label: 'In progress' };", $js);
        $this->assertStringNotContainsString("s === 'pending' || s === 'ongoing'", $js);
        $this->assertStringContainsString('.apt-pill--waiting', file_get_contents(resource_path('css/landing.css')));
        $this->assertStringContainsString("too_soon: 'Too soon'", $js);
    }

    // ── D6: Therapist Status ─────────────────────────────────────────────────

    public function test_status_priority(): void
    {
        $s = app(TherapistDayStatus::class);
        $shift = new ScheduledDay(self::TODAY, $this->branch->id, ScheduledDay::WORKING, ScheduledDay::SOURCE_SCHEDULE, '13:00', '21:00', true);
        $rest = new ScheduledDay(self::TODAY, $this->branch->id, ScheduledDay::REST_DAY, ScheduledDay::SOURCE_SCHEDULE, null, null, true);
        $att = fn (array $a) => new StaffAttendance($a);
        $now = Carbon::parse('2026-11-12 17:10:00');
        $early = Carbon::parse('2026-11-12 11:00:00');
        $key = fn (array $r) => [$r['key'], $r['note'], $r['note_warn']];

        $this->assertSame(['absent', null, false], $key($s->decide($att(['status' => 'absent']), $shift, 0, 2, 0, 0, $now)));
        $this->assertSame(['on_leave', null, false], $key($s->decide($att(['status' => 'on_leave']), $shift, 0, 2, 0, 0, $now)));
        $this->assertSame(['clocked_out', 'Out 5:03 PM', false], $key($s->decide($att(['status' => 'present', 'time_in' => '13:00:00', 'time_out' => '17:03:00']), $shift, 0, 2, 0, 0, $now)));
        $this->assertSame(['clocked_out', 'Out 5:03 PM · 2 sessions still open', true], $key($s->decide($att(['status' => 'present', 'time_in' => '13:00:00', 'time_out' => '17:03:00']), $shift, 1, 0, 0, 1, $now)));
        $this->assertSame(['in_session', 'In 1:02 PM', false], $key($s->decide($att(['status' => 'present', 'time_in' => '13:02:00']), $shift, 1, 0, 0, 0, $now)));
        $this->assertSame(['in_session', 'Not clocked in', true], $key($s->decide(null, $shift, 1, 0, 0, 0, $now)));
        $this->assertSame(['not_clocked_in', 'Shift 1:00 PM – 9:00 PM', false], $key($s->decide(null, $shift, 0, 2, 0, 0, $now)));
        $this->assertSame(['available', 'Shift starts 1:00 PM', false], $key($s->decide(null, $shift, 0, 2, 0, 0, $early)));
        $this->assertSame(['available', 'In 1:02 PM', false], $key($s->decide($att(['status' => 'present', 'time_in' => '13:02:00']), $shift, 0, 2, 0, 0, $now)));
        $this->assertSame(['finished', 'In 1:02 PM', false], $key($s->decide($att(['status' => 'present', 'time_in' => '13:02:00']), $shift, 0, 0, 3, 0, $now)));
        $this->assertSame(['free', 'In 1:02 PM', false], $key($s->decide($att(['status' => 'present', 'time_in' => '13:02:00']), $shift, 0, 0, 0, 0, $now)));
        $this->assertSame(['off_today', null, false], $key($s->decide(null, $rest, 0, 0, 0, 0, $now)));
        $this->assertSame('available', $s->decide(null, $rest, 0, 1, 0, 0, $now)['key']);   // booked on a rest day
    }

    public function test_dashboard_shows_clocked_out_instead_of_available(): void
    {
        $this->attendance($this->tess, ['time_in' => '09:00:00', 'time_out' => '17:03:00']);
        $this->attendance($this->theo, ['time_in' => '09:05:00']);
        $this->booking($this->tess, '18:00:00', '19:00:00', 'reserved');
        $this->booking($this->theo, '18:00:00', '19:00:00', 'reserved');

        $this->actingAs($this->manager)->get(route('dashboard'))->assertOk()
            ->assertSeeInOrder(['Tess', 'Clocked out', 'Out 5:03 PM'])
            ->assertSeeInOrder(['Theo', 'Available', 'In 9:05 AM']);

        $rows = collect($this->actingAs($this->manager)->getJson(route('dashboard.live-data'))->assertOk()->json('therapists'))->keyBy('id');
        $this->assertSame(['clocked_out', 'Clocked out', 'Out 5:03 PM'], [$rows[$this->tess->id]['status_key'], $rows[$this->tess->id]['status_label'], $rows[$this->tess->id]['status_note']]);
        $this->assertSame('available', $rows[$this->theo->id]['status_key']);
        $this->assertSame(1, $rows[$this->tess->id]['remaining_count']);   // existing keys kept
    }
}
