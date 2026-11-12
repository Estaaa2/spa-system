<?php

namespace Tests\Feature\Attendance;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\OperatingHours;
use App\Models\ReassignmentRequest;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Bookings\AttendanceBookingFlags;
use Database\Seeders\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Attendance-aware booking, Phase 2: clock-in/out confirmations and the
 * hand-off of bookings left behind.
 *
 *   D9, Q1 A  preview (web + API, same shape) and the record-preview
 *   Q2 A      early clock-out (beyond the 15-min grace) needs a reason on the web
 *   Q3 A      API: reason optional; "no reason given, mobile" remark
 *   D4, Q4 A  open bookings left today → one pending 'clocked_out' reassignment
 *             request each (no duplicates); undo closes them
 *   Q5 A      ongoing / ended-not-checked-out listed as "still open", never flagged
 *
 * World: PayrollTestAccountsSeeder, Test Branch A, Tess (therapist, no schedule
 * = branch hours 09:00–21:00, rests Sundays) and Theo. Today Thu 2026-11-12.
 */
class ClockConfirmationsTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-11-12';

    private Branch $branch;
    private Treatment $treatment;
    private User $manager;
    private User $tess;
    private User $theo;
    private Staff $tessStaff;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 09:05:00');
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
        $this->tessStaff = Staff::where('user_id', $this->tess->id)->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function at(string $time): void
    {
        Carbon::setTestNow(self::TODAY.' '.$time);
    }

    /** Today's row as MySQL stores it (plain DATE; see Phase 1 test). */
    private function clockedIn(string $timeIn = '09:00:00'): void
    {
        DB::table('staff_attendance')->insert([
            'staff_id' => $this->tessStaff->id, 'spa_id' => $this->tessStaff->spa_id, 'branch_id' => $this->tessStaff->branch_id,
            'date' => self::TODAY, 'status' => 'present', 'time_in' => $timeIn, 'source' => 'self',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function today(): ?StaffAttendance
    {
        return StaffAttendance::where('staff_id', $this->tessStaff->id)->whereDate('date', self::TODAY)->first();
    }

    private function booking(User $therapist, string $start, string $end, string $status = 'reserved', array $extra = []): Booking
    {
        return Booking::create(array_merge([
            'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id, 'therapist_id' => $therapist->id,
            'status' => $status, 'service_type' => 'in_branch', 'booking_source' => 'walk_in',
            'treatment' => 'treatment_'.$this->treatment->id, 'customer_name' => 'Walk-in Wendy',
            'appointment_date' => self::TODAY, 'start_time' => $start, 'end_time' => $end,
        ], $extra));
    }

    /** At 17:00: two to hand off, two still open, one finished. */
    private function tessDay(): array
    {
        return [
            'later1'  => $this->booking($this->tess, '17:30:00', '18:30:00'),
            'later2'  => $this->booking($this->tess, '19:00:00', '20:00:00'),
            'overdue' => $this->booking($this->tess, '15:00:00', '16:00:00', 'pending'),
            'ongoing' => $this->booking($this->tess, '16:30:00', '17:30:00', 'ongoing'),
            'done'    => $this->booking($this->tess, '10:00:00', '11:00:00', 'completed'),
        ];
    }

    private function preview(string $action): array
    {
        return $this->actingAs($this->tess)->getJson(route('attendance.clock-preview', ['action' => $action]))->assertOk()->json();
    }

    private function requests(): \Illuminate\Support\Collection
    {
        return ReassignmentRequest::where('source', AttendanceBookingFlags::SOURCE)->orderBy('booking_id')->get();
    }

    /**
     * The manual record, as a manager. The date is sent with a midnight time so
     * the controller's updateOrCreate finds the row the model's date cast wrote
     * in SQLite ("Y-m-d 00:00:00"); MySQL's DATE column has no such mismatch.
     */
    private function record(array $fields)
    {
        $fields += ['date' => self::TODAY];
        $fields['date'] .= strlen($fields['date']) === 10 ? ' 00:00:00' : '';

        return $this->actingAs($this->manager)->from(route('attendance.index'))
            ->post(route('attendance.record', $this->tessStaff), $fields);
    }

    // ── Clock-in preview ─────────────────────────────────────────────────────

    public function test_clock_in_preview_on_time_with_todays_bookings(): void
    {
        $this->booking($this->tess, '10:00:00', '11:00:00');
        $this->booking($this->theo, '10:00:00', '11:00:00');   // not hers

        $p = $this->preview('in');
        $this->assertTrue($p['allowed']);
        $this->assertSame(['09:05', '9:05 AM'], [$p['time'], $p['time_label']]);
        $this->assertSame('09:00', $p['shift']['start']);
        $this->assertSame(['on_time', 'On time'], [$p['timing']['kind'], $p['timing']['label']]);
        $this->assertCount(1, $p['bookings_today']);
        $this->assertSame('10:00 AM – 11:00 AM', $p['bookings_today'][0]['time_label']);
        $this->assertFalse($p['bookings_today'][0]['waiting']);
    }

    public function test_clock_in_preview_late_marks_waiting_bookings(): void
    {
        $this->at('09:40:00');
        $this->booking($this->tess, '09:30:00', '10:30:00', 'pending');

        $p = $this->preview('in');
        $this->assertSame('late', $p['timing']['kind']);
        $this->assertSame(40, $p['timing']['minutes']);
        $this->assertSame('Late by 40 min (shift starts 9:00 AM)', $p['timing']['label']);
        $this->assertTrue($p['bookings_today'][0]['waiting']);
    }

    public function test_clock_in_preview_refuses_on_a_rest_day_and_when_already_in(): void
    {
        Carbon::setTestNow('2026-11-15 10:00:00');   // Sunday: Tess's rest day
        $p = $this->preview('in');
        $this->assertFalse($p['allowed']);
        $this->assertStringContainsString('rest day', $p['refusal']);

        $this->at('09:05:00');
        $this->clockedIn();
        $this->assertSame('You are already clocked in for today.', $this->preview('in')['refusal']);
    }

    // ── Clock-out preview and save ───────────────────────────────────────────

    public function test_clock_out_preview_lists_hand_offs_and_still_open(): void
    {
        $this->clockedIn();
        $b = $this->tessDay();
        $this->at('17:00:00');

        $p = $this->preview('out');
        $this->assertSame(['early', 240], [$p['timing']['kind'], $p['timing']['minutes']]);
        $this->assertSame('4 h before the shift ends (9:00 PM)', $p['timing']['label']);
        $this->assertTrue($p['reason_required']);
        $this->assertSame([$b['later1']->id, $b['later2']->id], array_column($p['to_flag'], 'id'));
        $this->assertEqualsCanonicalizing([$b['overdue']->id, $b['ongoing']->id], array_column($p['still_open'], 'id'));

        $this->at('21:20:00');
        $this->assertSame(['after_shift', '20 min after the shift ended (9:00 PM)'], [$this->preview('out')['timing']['kind'], $this->preview('out')['timing']['label']]);
    }

    public function test_early_web_clock_out_needs_a_reason_then_hands_off_the_bookings(): void
    {
        $this->clockedIn();
        $b = $this->tessDay();
        $this->at('17:00:00');
        $out = fn (array $f = []) => $this->actingAs($this->tess)->from(route('attendance.index'))->post(route('attendance.clock-out'), $f);

        $out()->assertSessionHas('error', 'Please give a reason for clocking out early.');
        $this->assertNull($this->today()->time_out);
        $this->assertCount(0, $this->requests());

        $out(['reason' => 'Family emergency'])
            ->assertSessionHas('success', 'Clocked out. 2 bookings were sent to the front desk for reassignment. See you next shift!');

        $row = $this->today();
        $this->assertSame('17:00:00', $row->time_out);
        $this->assertSame('Early clock-out (4 h before shift end, 9:00 PM): Family emergency', $row->remarks);

        $r = $this->requests();
        $this->assertSame([$b['later1']->id, $b['later2']->id], $r->pluck('booking_id')->all());
        $this->assertSame(['pending', 'pending'], $r->pluck('status')->all());
        $this->assertStringContainsString('Tess clocked out today at 5:00 PM.', $r->first()->reason);
        $this->assertSame($this->tess->id, (int) $b['later1']->fresh()->therapist_id);   // nothing moved
        $this->assertSame('ongoing', $b['ongoing']->fresh()->status);                      // still open: not flagged
    }

    public function test_clock_out_within_the_grace_needs_no_reason(): void
    {
        $this->clockedIn();
        $this->at('20:50:00');

        $this->actingAs($this->tess)->from(route('attendance.index'))->post(route('attendance.clock-out'))
            ->assertSessionHas('success', 'Clocked out. See you next shift!');
        $this->assertNull($this->today()->remarks);
        $this->assertFalse($this->preview('in')['allowed']);
    }

    public function test_api_preview_matches_web_and_clock_out_without_reason_is_remarked(): void
    {
        $this->clockedIn();
        $this->tessDay();
        $this->at('17:00:00');
        $web = $this->preview('out');

        Sanctum::actingAs($this->tess);
        $api = $this->getJson('/api/therapist/attendance/clock-preview?action=out')->assertOk()->json();
        $this->assertSame($web, $api);

        $res = $this->postJson('/api/therapist/attendance/clock-out')->assertOk();
        $this->assertSame(2, $res->json('flagged'));
        $this->assertStringContainsString('2 bookings were sent', $res->json('message'));
        $this->assertSame('Early clock-out (4 h before shift end, 9:00 PM): no reason given, mobile', $this->today()->remarks);
    }

    public function test_api_clock_out_keeps_the_reason(): void
    {
        $this->clockedIn();
        $this->at('17:00:00');
        Sanctum::actingAs($this->tess);

        $this->postJson('/api/therapist/attendance/clock-out', ['reason' => 'Fever'])->assertOk()->assertJson(['flagged' => 0]);
        $this->assertStringEndsWith('): Fever', $this->today()->remarks);
    }

    public function test_no_bookings_means_no_flags(): void
    {
        $this->clockedIn();
        $this->booking($this->theo, '18:00:00', '19:00:00');
        $this->at('17:00:00');

        $this->actingAs($this->tess)->post(route('attendance.clock-out'), ['reason' => 'Errand']);
        $this->assertCount(0, ReassignmentRequest::all());
    }

    // ── Manual record ────────────────────────────────────────────────────────

    public function test_manual_record_flags_once_and_a_correction_closes_them(): void
    {
        $b = $this->tessDay();
        $this->at('17:00:00');

        $this->record(['status' => 'present', 'time_in' => '09:00', 'time_out' => '17:00'])
            ->assertSessionHas('success', fn ($m) => str_ends_with($m, '2 bookings were sent to the front desk for reassignment.'));
        $this->record(['status' => 'present', 'time_in' => '09:00', 'time_out' => '17:00'])
            ->assertSessionHas('success', fn ($m) => is_string($m) && ! str_contains($m, 'reassignment'));   // re-save: no duplicates
        $this->assertCount(2, $this->requests());

        // Time out moved to 19:15 (recorded at 19:20): 17:30–18:30 has ended, so its
        // request is left for the expiry job; 19:00–20:00 still needs someone.
        $this->at('19:20:00');
        $this->record(['status' => 'present', 'time_in' => '09:00', 'time_out' => '19:15'])->assertSessionHas('success');
        $this->assertSame(['pending', 'pending'], $this->requests()->pluck('status')->all());

        // Time out cleared: the open one is closed; the ended one is not touched.
        $this->record(['status' => 'present', 'time_in' => '09:00', 'time_out' => ''])->assertSessionHas('success');
        $this->assertSame(['pending', 'rejected'], $this->requests()->pluck('status')->all());
        $this->assertStringContainsString('Closed automatically', $this->requests()->last()->rejection_reason);

        // Absent: the open one is flagged again (a new request).
        $this->record(['status' => 'absent'])
            ->assertSessionHas('success', fn ($m) => str_ends_with($m, '1 booking was sent to the front desk for reassignment.'));
        $this->assertCount(3, $this->requests());
        $this->assertSame($b['later2']->id, (int) $this->requests()->where('status', 'pending')->last()->booking_id);
    }

    public function test_manual_record_for_a_past_day_flags_nothing(): void
    {
        $this->booking($this->tess, '19:00:00', '20:00:00', 'pending', ['appointment_date' => '2026-11-08']);
        $this->at('17:00:00');

        $this->record(['date' => '2026-11-08', 'status' => 'present', 'time_in' => '09:00', 'time_out' => '12:00'])
            ->assertSessionHas('success', fn ($m) => is_string($m) && ! str_contains($m, 'reassignment'));
        $this->assertCount(0, ReassignmentRequest::all());
    }

    public function test_record_preview_shows_timing_and_hand_offs_and_is_permission_gated(): void
    {
        $b = $this->tessDay();
        $this->at('17:00:00');
        $url = fn (array $q) => route('attendance.record-preview', $this->tessStaff).'?'.http_build_query($q + ['date' => self::TODAY]);

        $p = $this->actingAs($this->manager)->getJson($url(['status' => 'late', 'time_in' => '09:30', 'time_out' => '17:00']))->assertOk()->json();
        $this->assertTrue($p['is_today']);
        $this->assertSame('Late by 30 min (shift starts 9:00 AM)', $p['time_in']['label']);
        $this->assertSame('4 h before the shift ends (9:00 PM)', $p['time_out']['label']);
        $this->assertSame([$b['later1']->id, $b['later2']->id], array_column($p['to_flag'], 'id'));

        $absent = $this->actingAs($this->manager)->getJson($url(['status' => 'absent', 'time_out' => '17:00']))->assertOk()->json();
        $this->assertNull($absent['time_out']);
        $this->assertCount(2, $absent['to_flag']);

        $past = $this->actingAs($this->manager)->getJson($url(['date' => '2026-11-08', 'status' => 'present', 'time_out' => '12:00']))->assertOk()->json();
        $this->assertSame([], $past['to_flag']);

        $this->actingAs($this->tess)->getJson($url(['status' => 'present']))->assertForbidden();
        $this->assertCount(0, ReassignmentRequest::all());   // preview never writes
    }

    // ── Hand-off flow and page ───────────────────────────────────────────────

    public function test_flagged_booking_shows_as_clocked_out_and_can_be_moved_to_someone_here(): void
    {
        $this->clockedIn();
        $b = $this->tessDay();
        $this->at('17:00:00');
        $this->actingAs($this->tess)->post(route('attendance.clock-out'), ['reason' => 'Sick']);

        $list = collect($this->actingAs($this->manager)->getJson('/reassignment-requests')->assertOk()->json());
        $this->assertSame(['clocked_out', 'clocked_out'], $list->pluck('source')->all());

        $r = $this->requests()->first();
        $this->actingAs($this->manager)->postJson("/reassignment-requests/{$r->id}/approve", ['new_therapist_id' => $this->theo->id])->assertOk();
        $this->assertSame($this->theo->id, (int) $b['later1']->fresh()->therapist_id);
    }

    public function test_attendance_page_has_the_three_confirmations(): void
    {
        $this->actingAs($this->tess)->get(route('attendance.index'))->assertOk()
            ->assertSee('id="clockInModal"', false)
            ->assertSee('onclick="openClockInModal()"', false)
            ->assertDontSee('id="clockOutModal"', false);

        $this->clockedIn();
        $this->actingAs($this->tess)->get(route('attendance.index'))->assertOk()
            ->assertSee('id="clockOut_reason"', false)
            ->assertSee('id="clockOut_flagWrap"', false);

        $this->actingAs($this->manager)->get(route('attendance.index'))->assertOk()
            ->assertSee('id="record_preview"', false)
            ->assertSee('id="record_submitLabel"', false);
    }
}
