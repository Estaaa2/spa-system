<?php

namespace Tests\Feature\Attendance;

use App\Models\BranchRolePermission;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\User;
use Database\Factories\PayrollUserFactory;
use Database\Factories\StaffFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Payroll\Concerns\PayrollFixtures;
use Tests\TestCase;

/** Phase 6 — Attendance & Leave page fixes (Phase 1 findings F1–F9) and dashboard access. */
class AttendancePageTest extends TestCase
{
    use RefreshDatabase;
    use PayrollFixtures;

    private User $hr;
    private User $therapist;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-23 10:00:00');
        $this->withoutVite();
        $this->makeWorld();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin->assignRole('owner');
        $this->therapist = $this->user;
        $this->therapist->assignRole('therapist');
        $this->hr = PayrollUserFactory::new()->create(['spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id]);
        $this->hr->assignRole('hr');
        StaffFactory::new()->create(['user_id' => $this->hr->id, 'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function dump(string $name, $res)
    {
        if ($dir = getenv('RBAC_HTML_DUMP')) {
            file_put_contents($dir.'/'.$name.'.html', $res->getContent());
        }

        return $res;
    }

    public function test_tab_bar_and_request_leave_are_reachable_without_the_roster(): void
    {
        // F1: a therapist has no roster, but must still reach the Leave tab.
        $this->dump('attendance_therapist', $this->actingAs($this->therapist)->get(route('attendance.index')))
            ->assertOk()
            ->assertSee('role="tablist"', false)
            ->assertSee('id="tab-leave"', false)
            ->assertSee('Request Leave')
            ->assertDontSee('Branch Roster');
    }

    public function test_request_leave_follows_the_permission(): void
    {
        BranchRolePermission::create([
            'branch_id' => $this->branchA->id, 'spa_id' => $this->spa->id, 'role_name' => 'therapist',
            'permission_name' => 'create leave requests', 'granted' => false,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->therapist)->get(route('attendance.index'))
            ->assertOk()->assertDontSee('id="requestLeaveModal"', false);
    }

    public function test_leave_dates_carry_the_server_limits(): void
    {
        // F2: start ≥ today, end ≤ today + 60 (LeaveRequestController::store).
        $this->actingAs($this->therapist)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('min="2026-10-23"', false)
            ->assertSee('max="2026-12-22"', false);
    }

    public function test_clock_out_asks_for_confirmation(): void
    {
        StaffAttendance::create([
            'staff_id' => $this->staff->id, 'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id,
            'date' => '2026-10-23', 'status' => 'present', 'time_in' => '09:00:00', 'source' => 'self',
        ]);

        $this->dump('attendance_clocked_in', $this->actingAs($this->therapist)->get(route('attendance.index')))
            ->assertOk()->assertSee('openClockOutModal()', false)->assertSee('id="clockOutModal"', false);
    }

    public function test_roster_and_record_reject_future_dates_and_clear_times_for_absent(): void
    {
        // F4: a future roster date falls back to today.
        $this->dump('attendance_hr', $this->actingAs($this->hr)->get(route('attendance.index', ['date' => '2026-12-01'])))
            ->assertOk()->assertSee('Friday, October 23, 2026')->assertSee('max="2026-10-23"', false);

        $this->actingAs($this->hr)->post(route('attendance.record', $this->staff), [
            'date' => '2026-10-24', 'status' => 'present', 'time_in' => '09:00', 'time_out' => '18:00',
        ])->assertSessionHasErrors('date');

        $this->actingAs($this->hr)->post(route('attendance.record', $this->staff), [
            'date' => '2026-10-22', 'status' => 'absent', 'time_in' => '09:00', 'time_out' => '18:00',
        ])->assertSessionHasNoErrors();

        $row = StaffAttendance::where('staff_id', $this->staff->id)->whereDate('date', '2026-10-22')->firstOrFail();
        $this->assertSame('absent', $row->status);
        $this->assertNull($row->time_in);
        $this->assertNull($row->time_out);

        // F8: the roster says who recorded it.
        $this->actingAs($this->hr)->get(route('attendance.index', ['date' => '2026-10-22']))
            ->assertOk()->assertSee('Recorded by '.$this->hr->name);
    }

    public function test_hr_finance_and_heads_can_open_the_dashboard(): void
    {
        $fin = PayrollUserFactory::new()->create(['spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id]);
        $fin->assignRole('finance');
        $head = PayrollUserFactory::new()->create(['spa_id' => $this->spa->id, 'branch_id' => $this->branchB->id]);
        $head->assignRole('hr_head');

        foreach ([$this->hr, $fin, $head] as $u) {
            $this->actingAs($u)->get(route('dashboard'))->assertOk();
        }
    }
}
