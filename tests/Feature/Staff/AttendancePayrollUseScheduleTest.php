<?php

namespace Tests\Feature\Staff;

use App\Models\StaffAttendance;
use App\Models\StaffScheduleOverride;
use App\Services\Payroll\RateResolver;
use App\Services\Staff\WorkScheduleWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Staff\Concerns\ScheduleFixtures;
use Tests\TestCase;

/**
 * Staff scheduling Phase 4 — attendance and payroll use the work schedule.
 *
 * Clock-in / finalize calendar (2026): Wed Nov 18 · Thu 19 · Fri 20 · Sun 22.
 * Payroll calendar (fixture cutoff Aug 16–31, 2026): Sun 16 · Mon 17 · Tue 18 ·
 * Wed 19 · Thu 20 · Sun 23 · Wed 26 (Aug 21 special day, Aug 31 holiday avoided).
 */
class AttendancePayrollUseScheduleTest extends TestCase
{
    use RefreshDatabase;
    use ScheduleFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->makeWorld();
        $this->hours($this->branchA, '09:00:00', '21:00:00');
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->admin->assignRole('owner');
        $this->user->assignRole('therapist');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function writer(): WorkScheduleWriter
    {
        return app(WorkScheduleWriter::class);
    }

    /** Wednesday 1–9 PM custom, Sunday rest, everything else branch hours. */
    private function novemberSchedule(array $custom = ['Wednesday' => ['13:00', '21:00']]): void
    {
        $this->writer()->savePattern($this->staff, '2026-11-12', $this->pattern(['Sunday'], $custom), $this->admin->id);
    }

    private function clockInAt(string $when)
    {
        Carbon::setTestNow($when);

        return $this->actingAs($this->user)->post(route('attendance.clock-in'));
    }

    private function todayRow(): ?StaffAttendance
    {
        return StaffAttendance::where('staff_id', $this->staff->id)->whereDate('date', today())->first();
    }

    // ── Clock-in by shift (Q3 A, Q4 A) ───────────────────────────────────────

    public function test_afternoon_shift_is_not_late_at_one_and_is_late_after_grace(): void
    {
        $this->novemberSchedule();

        $this->clockInAt('2026-11-18 13:10:00')->assertSessionMissing('error');
        $this->assertSame('present', $this->todayRow()->status);   // the old branch-hours rule said "late"

        $this->todayRow()->delete();
        $this->clockInAt('2026-11-18 13:20:00')->assertSessionMissing('error');
        $this->assertSame('late', $this->todayRow()->status);
    }

    public function test_clock_in_window_follows_the_shift(): void
    {
        $this->novemberSchedule(['Wednesday' => ['13:00', '17:00']]);

        $this->clockInAt('2026-11-18 12:30:00')->assertSessionHas('error');
        $this->assertStringContainsString('your shift starts at 1:00 PM', session('error'));
        $this->assertNull($this->todayRow());

        $this->clockInAt('2026-11-18 17:30:00')->assertSessionHas('error');
        $this->assertStringContainsString('Your shift ended at 5:00 PM', session('error'));

        $this->clockInAt('2026-11-18 12:45:00')->assertSessionMissing('error');   // start − 15
        $this->assertSame('present', $this->todayRow()->status);
    }

    public function test_branch_hours_day_keeps_the_old_rule(): void
    {
        $this->novemberSchedule();

        $this->clockInAt('2026-11-19 09:20:00')->assertSessionMissing('error');
        $this->assertSame('late', $this->todayRow()->status);
    }

    public function test_no_shift_today_refuses_clock_in(): void
    {
        $this->novemberSchedule();
        $this->writer()->saveOverride($this->staff, '2026-11-20', StaffScheduleOverride::DAY_OFF, null, null, null, null);

        $this->clockInAt('2026-11-22 10:00:00')->assertSessionHas('error');                    // Sunday rest day
        $this->assertStringContainsString('rest day', session('error'));
        $this->clockInAt('2026-11-20 10:00:00')->assertSessionHas('error');                    // day off
        $this->assertStringContainsString('day off', session('error'));
        $this->assertSame(0, StaffAttendance::count());

        $this->writer()->saveOverride($this->staff, '2026-11-22', StaffScheduleOverride::SHIFT, '10:00', '14:00', null, null);
        $this->clockInAt('2026-11-22 10:00:00')->assertSessionMissing('error');               // extra shift
        $this->assertSame('present', $this->todayRow()->status);
    }

    public function test_leave_refuses_clock_in(): void
    {
        $this->novemberSchedule();
        $this->approvedLeave('2026-11-19', '2026-11-19');

        $this->clockInAt('2026-11-19 10:00:00')->assertSessionHas('error');
        $this->assertStringContainsString('approved leave', session('error'));
    }

    public function test_mobile_clock_in_uses_the_same_rules(): void
    {
        $this->novemberSchedule();
        Sanctum::actingAs($this->user);

        Carbon::setTestNow('2026-11-18 12:00:00');
        $this->postJson('/api/therapist/attendance/clock-in')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, '1:00 PM'));

        Carbon::setTestNow('2026-11-18 13:20:00');
        $this->postJson('/api/therapist/attendance/clock-in')->assertOk()->assertJsonPath('today.status', 'late');
    }

    public function test_clock_out_after_shift_end_is_flagged(): void
    {
        $this->novemberSchedule(['Wednesday' => ['13:00', '17:00']]);
        $this->clockInAt('2026-11-18 13:00:00');

        Carbon::setTestNow('2026-11-18 17:40:00');
        $this->actingAs($this->user)->post(route('attendance.clock-out'))->assertSessionMissing('error');

        $this->assertStringContainsString('clocked out after shift end (5:00 PM)', $this->todayRow()->remarks);
    }

    // ── attendance:finalize-day by schedule ──────────────────────────────────

    private function finalizeOn(string $date): ?StaffAttendance
    {
        Carbon::setTestNow($date.' 23:55:00');
        $this->artisan('attendance:finalize-day')->assertSuccessful();

        return StaffAttendance::where('staff_id', $this->staff->id)->whereDate('date', $date)->first();
    }

    public function test_finalize_marks_by_schedule(): void
    {
        $this->novemberSchedule();
        $w = $this->writer();
        $w->saveOverride($this->staff, '2026-11-19', StaffScheduleOverride::DAY_OFF, null, null, 'Errand', null);   // unpaid
        $w->saveSwap($this->staff, '2026-11-20', '2026-11-22', null, null, 'Swap', null);                            // Fri off, Sun works

        $this->assertSame('absent', $this->finalizeOn('2026-11-18')?->status);                   // workday
        $unpaid = $this->finalizeOn('2026-11-19');
        $this->assertSame(['on_leave', 'Day off (unpaid): Errand'], [$unpaid?->status, $unpaid?->remarks]);
        $this->assertNull($this->finalizeOn('2026-11-20'));                                       // swap day off
        $this->assertSame('absent', $this->finalizeOn('2026-11-22')?->status);                   // swapped workday
        $this->assertNull($this->finalizeOn('2026-11-29'));                                       // Sunday rest day
    }

    public function test_without_a_schedule_pay_profile_rest_days_still_apply(): void
    {
        $this->profile(['rest_days' => ['Thursday']]);

        $this->assertNull($this->finalizeOn('2026-11-19'));
        $this->assertSame('absent', $this->finalizeOn('2026-11-18')?->status);
    }

    // ── Payroll rest days from the schedule (D3 B) ───────────────────────────

    private function augustPattern(array $rest = ['Sunday'], string $from = '2026-08-01'): void
    {
        $this->writer()->savePattern($this->staff, $from, $this->pattern($rest), null);
    }

    public function test_extra_shift_on_a_rest_day_earns_the_premium(): void
    {
        $this->profile(['rest_days' => ['Monday']]);   // the schedule wins
        $this->augustPattern(['Sunday']);
        $this->writer()->saveOverride($this->staff, '2026-08-23', StaffScheduleOverride::SHIFT, '09:00', '18:00', null, null);
        $this->attend('2026-08-23');
        $this->attend('2026-08-24');                    // Monday: an ordinary day now

        $r = $this->calculate();
        $this->assertSame('180.00', $r->totalFor('RESTDAY_PREM'));   // 600 × 30%, Sunday only
    }

    public function test_swap_makes_the_old_rest_day_ordinary_and_keeps_the_day_off_paid(): void
    {
        $this->profile(['pay_basis' => 'monthly', 'base_rate' => '18250.00', 'rest_days' => ['Sunday']]);
        $this->augustPattern(['Sunday']);
        $this->writer()->saveSwap($this->staff, '2026-08-19', '2026-08-23', null, null, null, null);   // Wed off, Sun works
        $this->attend('2026-08-23');
        $this->attend('2026-08-19', 'absent', null, null);   // even a stray absence row on the swap day off

        $r = $this->calculate();
        $this->assertSame('0.00', $r->totalFor('RESTDAY_PREM'));
        $this->assertSame('9125.00', $r->totalFor('BASIC'));   // no deduction: Aug 19 is that week's rest day
    }

    public function test_unpaid_day_off_is_deducted_for_monthly_paid(): void
    {
        $this->profile(['pay_basis' => 'monthly', 'base_rate' => '18250.00', 'rest_days' => ['Sunday']]);
        $this->augustPattern(['Sunday']);
        $this->writer()->saveOverride($this->staff, '2026-08-18', StaffScheduleOverride::DAY_OFF, null, null, null, null);
        $this->attend('2026-08-18', 'on_leave', null, null);   // what finalize-day writes

        $this->assertSame('8525.00', $this->calculate()->totalFor('BASIC'));   // 9,125 − 600
    }

    public function test_pattern_change_mid_cutoff_moves_the_premium(): void
    {
        $this->profile();
        $this->augustPattern(['Sunday']);
        $this->augustPattern(['Wednesday'], '2026-08-20');
        $this->attend('2026-08-23');   // Sunday under the new pattern: ordinary
        $this->attend('2026-08-26');   // Wednesday: rest day → premium
        $this->attend('2026-08-16');   // Sunday under the old pattern → premium

        $prem = collect($this->calculate()->lines)->where('componentCode', 'RESTDAY_PREM')->pluck('date')->sort()->values()->all();
        $this->assertSame(['2026-08-16', '2026-08-26'], $prem);
    }

    public function test_daily_factor_and_snapshot_use_the_schedule_rest_days(): void
    {
        $this->profile(['rest_days' => ['Sunday']]);
        $this->augustPattern(['Saturday', 'Sunday']);

        $p = app(RateResolver::class)->timelineFor($this->staff, '2026-08-31')->on('2026-08-31');
        $this->assertSame(['Saturday', 'Sunday'], $p->restDays);   // → DOLE factor 261, and the payslip snapshot
    }

    public function test_without_a_schedule_payroll_is_unchanged(): void
    {
        $this->profile(['rest_days' => ['Sunday']]);
        $this->attend('2026-08-23');

        $this->assertSame('180.00', $this->calculate()->totalFor('RESTDAY_PREM'));
        $this->assertSame(['Sunday'], app(RateResolver::class)->timelineFor($this->staff, '2026-08-31')->on('2026-08-31')->restDays);
    }

    // ── Schedule modal rules ─────────────────────────────────────────────────

    public function test_daily_paid_pattern_needs_one_or_two_rest_days(): void
    {
        Carbon::setTestNow('2026-11-12 09:00:00');
        $this->profile();   // daily
        $save = fn (array $rest) => $this->actingAs($this->admin)->putJson(route('staff.schedule.update', $this->staff), [
            'effective_from' => '2026-11-12', 'days' => $this->pattern($rest),
        ]);

        $save([])->assertStatus(422)->assertJsonValidationErrors('days');
        $save(['Saturday', 'Sunday', 'Monday'])->assertStatus(422);
        $save(['Saturday', 'Sunday'])->assertOk();
    }

    public function test_swap_rules_and_pair_removal(): void
    {
        Carbon::setTestNow('2026-11-12 09:00:00');
        $this->profile(['pay_basis' => 'monthly', 'base_rate' => '18250.00']);
        $this->novemberSchedule();
        $post = fn (array $data) => $this->actingAs($this->admin)->postJson(route('staff.schedule.overrides.store', $this->staff), $data + ['type' => 'day_off', 'kind' => 'swap']);

        $post(['date' => '2026-11-18', 'swap_work_date' => '2026-11-29'])->assertStatus(422)->assertJsonPath('errors.swap_work_date.0', 'A rest-day swap must stay within the same Monday–Sunday week.');
        $post(['date' => '2026-11-18', 'swap_work_date' => '2026-11-19'])->assertStatus(422)->assertJsonValidationErrors('swap_work_date');   // not a rest day
        $post(['date' => '2026-11-22', 'swap_work_date' => '2026-11-22'])->assertStatus(422);

        $res = $post(['date' => '2026-11-18', 'swap_work_date' => '2026-11-22', 'reason' => 'Covering for Ana'])->assertOk();
        $this->assertStringContainsString('Rest-day swap saved', $res->json('message'));
        $this->assertSame(2, StaffScheduleOverride::where('kind', 'swap')->count());
        $res->assertJsonPath('schedule.overrides.0.label', 'Day off (rest-day swap)')
            ->assertJsonPath('schedule.overrides.0.paired_date_label', 'Sun, Nov 22')
            ->assertJsonPath('schedule.overrides.1.label', 'Works branch hours (rest-day swap)');

        $one = StaffScheduleOverride::whereDate('date', '2026-11-22')->sole();
        $this->actingAs($this->admin)->deleteJson(route('staff.schedule.overrides.destroy', [$this->staff, $one]))
            ->assertOk()->assertJsonCount(0, 'schedule.overrides');
    }

    public function test_attendance_roster_shows_the_schedule(): void
    {
        Carbon::setTestNow('2026-11-18 10:00:00');
        $this->novemberSchedule();

        $this->actingAs($this->admin)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('>Schedule</th>', false)
            ->assertSee('1:00 PM – 9:00 PM');
    }
}
