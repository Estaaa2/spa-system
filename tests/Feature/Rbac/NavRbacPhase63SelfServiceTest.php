<?php

namespace Tests\Feature\Rbac;

use App\Models\Branch;
use App\Models\BranchRolePermission;
use App\Models\LeaveRequest;
use App\Models\Spa;
use App\Models\StaffAttendance;
use App\Models\User;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Sidebar & RBAC rework, Phase 6.3: employee self-service. Every active staff
 * member can clock in, see their own attendance and (with the default
 * "create leave requests") file leave — Finance included, and whatever an owner
 * unticks on Roles & Permissions. The dashboard clock-in card is for everyone
 * with an active staff record, not only the therapist "My Today" view.
 */
class NavRbacPhase63SelfServiceTest extends TestCase
{
    use RefreshDatabase;

    private Spa $spa;
    private Branch $branchA;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 10:00:00');
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->spa = Spa::withoutGlobalScopes()->where('name', PayrollTestAccountsSeeder::SPA_NAME)->firstOrFail();
        $this->branchA = Branch::withoutGlobalScopes()->where('spa_id', $this->spa->id)->where('is_main', true)->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function deny(string $role, array $permissions): void
    {
        foreach ($permissions as $p) {
            BranchRolePermission::create(['branch_id' => $this->branchA->id, 'spa_id' => $this->spa->id,
                'role_name' => $role, 'permission_name' => $p, 'granted' => false]);
        }
        app(\App\Services\Permissions\BranchPermissionCache::class)->flush();
    }

    public function test_finance_can_reach_attendance_clock_in_and_file_leave(): void
    {
        $finance = $this->account('finance.a@levictas.test');
        $this->actingAs($finance);

        $this->get(route('dashboard'))->assertOk()
            ->assertSee("You haven't clocked in yet today", false)
            ->assertSee('href="'.route('attendance.index').'"', false);
        $this->get(route('attendance.index'))->assertOk()->assertSee('My Leave Requests')->assertSee('onclick="openRequestLeaveModal()"', false);

        $this->post(route('attendance.clock-in'))->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertTrue(StaffAttendance::where('staff_id', $finance->staff->id)->whereDate('date', '2026-11-04')->whereNotNull('time_in')->exists());

        $this->post(route('leave-requests.store'), [
            'leave_type' => 'personal', 'start_date' => '2026-11-10', 'end_date' => '2026-11-10', 'reason' => 'Family matter to attend to.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, LeaveRequest::where('user_id', $finance->id)->count());
    }

    public function test_every_staff_role_gets_the_dashboard_clock_in_card(): void
    {
        foreach (['manager.a', 'reception.a', 'hr.a', 'finance.a', 'hr.head', 'finance.head', 'therapist.a1'] as $account) {
            $this->actingAs($this->account("$account@levictas.test"))->get(route('dashboard'))->assertOk()
                ->assertSee("You haven't clocked in yet today", false);
        }

        // My Today (the therapist's own appointments) is still therapist / "does treatments" only.
        $this->actingAs($this->account('reception.a@levictas.test'))->get(route('dashboard'))->assertDontSee('Appointments assigned');
        $this->actingAs($this->account('therapist.a1@levictas.test'))->get(route('dashboard'))->assertSee('Appointments assigned');

        // The owner has no staff record: no card (and no absences to avoid).
        $this->actingAs($this->account('owner@levictas.test'))->get(route('dashboard'))->assertOk()
            ->assertDontSee("You haven't clocked in yet today", false);
    }

    public function test_unticking_every_attendance_permission_never_blocks_clocking_in(): void
    {
        $this->deny('receptionist', ['view attendance', 'view leave requests', 'create leave requests', 'edit leave requests', 'edit attendance']);
        $reception = $this->account('reception.a@levictas.test');
        $this->actingAs($reception);

        $this->get(route('attendance.index'))->assertOk()
            ->assertDontSee('onclick="openRequestLeaveModal()"', false);   // filing leave stays a permission
        $this->post(route('attendance.clock-in'))->assertSessionMissing('error');
        $this->assertTrue(StaffAttendance::where('staff_id', $reception->staff->id)->whereNotNull('time_in')->exists());

        $this->post(route('leave-requests.store'), [
            'leave_type' => 'personal', 'start_date' => '2026-11-10', 'end_date' => '2026-11-10', 'reason' => 'Family matter to attend to.',
        ])->assertForbidden();
    }

    public function test_people_without_an_active_staff_record_get_no_self_service(): void
    {
        $customer = User::factory()->create(['email' => 'self.customer@levictas.test', 'spa_id' => null, 'branch_id' => null]);
        $customer->assignRole('customer');
        $this->assertFalse($customer->hasAttendanceSelfService());

        $therapist = $this->account('therapist.b@levictas.test');
        $therapist->staff->forceFill(['employment_status' => 'inactive'])->save();
        $this->assertFalse($therapist->fresh()->hasAttendanceSelfService());
    }

    public function test_migration_gives_finance_roles_leave_and_keeps_branch_choices(): void
    {
        Role::findByName('finance')->revokePermissionTo('create leave requests');
        Role::findByName('finance_head')->revokePermissionTo('create leave requests');
        $this->deny('finance', ['create leave requests']);   // an owner's saved choice at Branch A

        $files = glob(database_path('migrations/*_nav_rbac_phase6_3_leave_for_every_employee.php'));
        (require $files[0])->up();
        (require $files[0])->up();   // safe to re-run
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue(Role::findByName('finance')->hasPermissionTo('create leave requests'));
        $this->assertTrue(Role::findByName('finance_head')->hasPermissionTo('create leave requests'));
        $this->assertFalse((bool) BranchRolePermission::where('role_name', 'finance')->where('permission_name', 'create leave requests')->value('granted'));
    }
}
