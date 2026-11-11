<?php

namespace Tests\Feature\Staff;

use App\Models\StaffWorkSchedule;
use App\Services\Staff\WorkScheduleWriter;
use Database\Factories\PayrollUserFactory;
use Database\Factories\StaffFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Staff\Concerns\ScheduleFixtures;
use Tests\TestCase;

/**
 * Staff scheduling Phase 2 — own schedule on the Attendance page and in the
 * mobile API (additive fields, D7), and the backfill command (D8).
 * Today = Thu 2026-11-12; branch A closed Mondays.
 */
class ScheduleSelfServiceAndBackfillTest extends TestCase
{
    use RefreshDatabase;
    use ScheduleFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 09:00:00');
        $this->withoutVite();
        $this->makeWorld();
        $this->hours($this->branchA, closed: ['Monday']);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->admin->assignRole('owner');
        $this->user->assignRole('therapist');
    }

    // ── Own schedule ───────────────────────────────────────────────────────

    public function test_attendance_page_shows_my_week(): void
    {
        app(WorkScheduleWriter::class)->savePattern($this->staff, '2026-11-12', $this->pattern(['Sunday']), $this->admin->id);

        $this->actingAs($this->user)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('My Work Schedule')
            ->assertSee('Rest days: Sunday.')
            ->assertSee('Rest day')
            ->assertSee('Branch closed');
    }

    public function test_attendance_page_without_schedule_explains_the_default(): void
    {
        $this->actingAs($this->user)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee("No schedule set yet", false);
    }

    public function test_api_attendance_adds_schedule_and_keeps_old_fields(): void
    {
        app(WorkScheduleWriter::class)->savePattern($this->staff, '2026-11-12', $this->pattern(['Sunday']), $this->admin->id);
        Sanctum::actingAs($this->user);

        $this->getJson('/api/therapist/attendance')
            ->assertOk()
            ->assertJsonStructure(['has_staff', 'today', 'history', 'schedule' => ['has_schedule', 'rest_days', 'week' => [['date', 'status', 'start_time', 'end_time', 'label']]]])
            ->assertJsonPath('schedule.rest_days', ['Sunday'])
            ->assertJsonCount(7, 'schedule.week')
            ->assertJsonPath('schedule.week.3.status', 'rest_day')   // Sun Nov 15
            ->assertJsonPath('schedule.week.4.status', 'closed');    // Mon Nov 16
    }

    public function test_api_therapist_schedule_adds_shifts_and_survives_bad_dates(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/therapist/schedule?week_start=2026-11-16&week_end=2026-11-22')
            ->assertOk()
            ->assertJsonStructure(['success', 'bookings', 'shifts'])
            ->assertJsonCount(7, 'shifts')
            ->assertJsonPath('shifts.0.status', 'closed')
            ->assertJsonPath('shifts.1.start_time', '09:00');

        $this->getJson('/api/therapist/schedule?week_start=2026-11-01&week_end=2027-01-01')
            ->assertOk()->assertJsonCount(31, 'shifts');
    }

    // ── Backfill ───────────────────────────────────────────────────────────

    public function test_backfill_dry_run_then_real_run_then_rerun(): void
    {
        $this->profile(['rest_days' => ['Saturday', 'Sunday']]);
        // A second staff member with no pay profile is skipped.
        $u = PayrollUserFactory::new()->create(['spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id]);
        StaffFactory::new()->create(['user_id' => $u->id, 'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id]);

        $this->artisan('staff:backfill-work-schedules', ['--dry-run' => true])
            ->expectsOutputToContain('1 schedule(s) would be created')
            ->expectsOutputToContain('1 staff skipped')
            ->assertSuccessful();
        $this->assertSame(0, StaffWorkSchedule::count());

        $this->artisan('staff:backfill-work-schedules')->expectsOutputToContain('Created 1 work schedule(s)')->assertSuccessful();

        $s = StaffWorkSchedule::with('days')->sole();
        $this->assertSame($this->staff->id, $s->staff_id);
        $this->assertSame('2026-11-12', $s->effective_from->toDateString());
        $this->assertSame(['Saturday', 'Sunday'], $s->restDays());
        $this->assertSame(5, $s->days->filter(fn ($d) => $d->followsBranchHours())->count());

        $this->artisan('staff:backfill-work-schedules')->expectsOutputToContain('Nothing to create')->assertSuccessful();
        $this->assertSame(1, StaffWorkSchedule::count());
    }

    public function test_backfill_skips_inactive_and_already_scheduled_staff(): void
    {
        $this->profile();
        app(WorkScheduleWriter::class)->savePattern($this->staff, '2026-11-12', $this->pattern(['Tuesday']), null);

        $this->artisan('staff:backfill-work-schedules')->assertSuccessful();

        $this->assertSame(['Tuesday'], StaffWorkSchedule::sole()->load('days')->restDays());
    }

    // ── Permission plumbing ────────────────────────────────────────────────

    public function test_permission_defaults_and_dependency(): void
    {
        foreach (['owner', 'manager', 'hr', 'hr_head'] as $role) {
            $this->assertTrue(\Spatie\Permission\Models\Role::findByName($role)->hasPermissionTo('edit staff schedules'), $role);
        }
        foreach (['receptionist', 'therapist', 'finance', 'finance_head'] as $role) {
            $this->assertFalse(\Spatie\Permission\Models\Role::findByName($role)->hasPermissionTo('edit staff schedules'), $role);
        }
        $this->assertSame(['view staff'], \App\Services\Permissions\PermissionRules::DEPENDENCIES['edit staff schedules']);
        $this->assertContains('edit staff schedules', \App\Services\Permissions\PermissionRules::OWNER_PROTECTED);
    }

    public function test_data_migration_grants_existing_roles_that_can_edit_staff(): void
    {
        $perm = \Spatie\Permission\Models\Permission::findByName('edit staff schedules');
        foreach (['owner', 'manager', 'hr', 'hr_head'] as $r) {
            \Spatie\Permission\Models\Role::findByName($r)->revokePermissionTo($perm);
        }
        \Spatie\Permission\Models\Role::findByName('manager')->revokePermissionTo('edit staff'); // owner limited it
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        (require database_path('migrations/2026_11_12_100100_staff_scheduling_phase2_schedule_permission.php'))->up();
        (require database_path('migrations/2026_11_12_100100_staff_scheduling_phase2_schedule_permission.php'))->up(); // re-run safe

        $this->assertTrue(\Spatie\Permission\Models\Role::findByName('hr')->hasPermissionTo('edit staff schedules'));
        $this->assertTrue(\Spatie\Permission\Models\Role::findByName('owner')->hasPermissionTo('edit staff schedules'));
        $this->assertFalse(\Spatie\Permission\Models\Role::findByName('manager')->hasPermissionTo('edit staff schedules'));
    }
}
