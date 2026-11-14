<?php

namespace Tests\Feature\Staff;

use App\Models\Staff;
use App\Models\StaffBranchDeployment;
use App\Models\User;
use App\Services\Payroll\PayrollPermissions as P;
use Database\Factories\PayrollUserFactory;
use Database\Factories\StaffFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Staff\Concerns\ScheduleFixtures;
use Tests\TestCase;

/**
 * Staff role guards (Nov 14):
 * - nobody changes their own role or removes their own account;
 * - HR Head / Finance Head cannot be deployed (UI, approve, scheduled job, promotion);
 * - nobody files a deployment for themselves through the HR form.
 */
class StaffRoleGuardsTest extends TestCase
{
    use RefreshDatabase;
    use ScheduleFixtures;

    private User $owner;
    private User $hr;
    private User $hrHead;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-14 09:00:00');
        $this->withoutVite();
        Mail::fake();

        $this->makeWorld();
        $this->hours($this->branchA);
        $this->hours($this->branchB);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->owner = $this->admin;
        $this->owner->assignRole('owner');
        $this->user->assignRole('therapist');
        $this->hr = $this->member('hr');
        $this->hrHead = $this->member(P::ROLE_HR_HEAD);
    }

    private function member(string $role): User
    {
        $u = PayrollUserFactory::new()->create(['spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id]);
        $u->assignRole($role);
        StaffFactory::new()->create(['user_id' => $u->id, 'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id]);

        return $u;
    }

    private function staffOf(User $u): Staff
    {
        return Staff::where('user_id', $u->id)->firstOrFail();
    }

    private function deployment(Staff $staff, array $attrs = []): StaffBranchDeployment
    {
        return StaffBranchDeployment::create(array_merge([
            'staff_id' => $staff->id, 'spa_id' => $this->spa->id, 'requested_by' => $this->owner->id,
            'from_branch_id' => $this->branchA->id, 'to_branch_id' => $this->branchB->id,
            'start_date' => '2026-11-14', 'is_permanent' => true,
            'status' => 'pending', 'staff_response' => 'accepted',
        ], $attrs));
    }

    // ── Own account ────────────────────────────────────────────────────────

    public function test_hr_cannot_change_own_role(): void
    {
        $this->actingAs($this->hr)
            ->put(route('staff.update', $this->staffOf($this->hr)), ['roles' => 'manager'])
            ->assertSessionHas('error');

        $this->assertTrue($this->hr->fresh()->hasRole('hr'));
        $this->assertFalse($this->hr->fresh()->hasRole('manager'));
    }

    public function test_hr_cannot_remove_own_account(): void
    {
        $this->actingAs($this->hr)
            ->delete(route('staff.destroy', $this->staffOf($this->hr)))
            ->assertSessionHas('error');

        $this->assertNotNull(Staff::find($this->staffOf($this->hr)->id));
    }

    public function test_staff_page_hides_edit_and_remove_on_own_row(): void
    {
        $html = $this->actingAs($this->hr)->get(route('staff.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString("openDeleteModal({$this->staffOf($this->hr)->id},", $html);
        $this->assertStringContainsString("openDeleteModal({$this->staff->id},", $html);
    }

    public function test_hr_can_still_change_another_staff_role(): void
    {
        $this->actingAs($this->hr)
            ->put(route('staff.update', $this->staff), ['roles' => 'receptionist'])
            ->assertSessionHas('success');

        $this->assertTrue($this->user->fresh()->hasRole('receptionist'));
    }

    // ── Deployment ────────────────────────────────────────────────────────

    public function test_hr_cannot_deploy_the_hr_head_or_themselves(): void
    {
        foreach ([$this->hrHead, $this->hr] as $target) {
            $this->actingAs($this->hr)->post(route('branch-deployments.store'), [
                'staff_id' => $this->staffOf($target)->id, 'to_branch_id' => $this->branchB->id,
                'start_date' => '2026-11-16', 'is_permanent' => 1,
            ])->assertSessionHas('error');
        }

        $this->assertSame(0, StaffBranchDeployment::count());
    }

    public function test_hr_can_still_deploy_a_therapist(): void
    {
        $this->actingAs($this->hr)->post(route('branch-deployments.store'), [
            'staff_id' => $this->staff->id, 'to_branch_id' => $this->branchB->id,
            'start_date' => '2026-11-16', 'is_permanent' => 1,
        ])->assertSessionHas('success');

        $this->assertSame(1, StaffBranchDeployment::count());
    }

    public function test_page_is_titled_staff_deployment_and_flags_heads_and_self(): void
    {
        $this->actingAs($this->hr)->get(route('deployment.index'))
            ->assertOk()
            ->assertSee('Staff Deployment')
            ->assertDontSee('Branch Deployment')
            ->assertSee('Works across all branches')
            ->assertSee('"is_head":true', false)
            ->assertSee('"is_self":true', false);
    }

    public function test_owner_cannot_approve_a_deployment_for_a_head(): void
    {
        $d = $this->deployment($this->staffOf($this->hrHead));

        $this->actingAs($this->owner)->post(route('branch-deployments.approve', $d))
            ->assertSessionHas('error');

        $this->assertSame('pending', $d->fresh()->status);
    }

    public function test_promotion_to_head_cancels_open_deployments(): void
    {
        $d = $this->deployment($this->staff, ['status' => 'approved']);

        $this->actingAs($this->owner)
            ->put(route('staff.update', $this->staff), ['roles' => P::ROLE_FINANCE_HEAD])
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $d->fresh()->status);
    }

    public function test_scheduler_does_not_move_a_head(): void
    {
        $d = $this->deployment($this->staffOf($this->hrHead), ['status' => 'approved']);

        $this->artisan('deployments:process')->assertSuccessful();

        $this->assertSame('cancelled', $d->fresh()->status);
        $this->assertSame($this->branchA->id, (int) $this->staffOf($this->hrHead)->branch_id);
    }
}