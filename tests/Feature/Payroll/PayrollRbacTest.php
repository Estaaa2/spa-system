<?php

namespace Tests\Feature\Payroll;

use App\Models\Branch;
use App\Models\BranchRolePermission;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Services\Payroll\PayrollPermissions as P;
use Database\Factories\PayrollUserFactory;
use Database\Factories\StaffAttendanceFactory;
use Database\Factories\StaffFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Payroll\Concerns\PayrollFixtures;
use Tests\TestCase;

/**
 * Payroll / attendance RBAC refactor (Phase 2+4): default role permissions, every run
 * step per role, branch scope, HR Head / Finance Head switching, staff-role rules,
 * Roles & Permissions storage, locked-branch access and the transition migration.
 * Uses the real RolePermissionSeeder defaults — no hand-made test roles.
 *
 * World: Branch A (main) with $this->staff; Branch B with $staffB. HR Head reports
 * to Branch B (not the main branch) on purpose.
 */
class PayrollRbacTest extends TestCase
{
    use RefreshDatabase;
    use PayrollFixtures;

    private User $owner;
    private User $hrHead;
    private User $finHead;
    private User $hrA;
    private User $hrB;
    private User $finA;
    private User $therapistA;
    private Staff $staffB;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-10 09:00:00');
        Mail::fake();
        Log::spy();
        $this->withoutVite();

        $this->makeWorld();
        $this->spa->forceFill(['payroll_first_cutoff_day' => 15, 'payroll_pay_day_offset' => 5])->save();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->owner = $this->admin;
        $this->owner->assignRole('owner');
        $this->user->assignRole('therapist');
        $this->therapistA = $this->user;

        $this->hrHead = $this->member($this->branchB, P::ROLE_HR_HEAD);
        $this->finHead = $this->member($this->branchA, P::ROLE_FINANCE_HEAD);
        $this->hrA = $this->member($this->branchA, 'hr');
        $this->hrB = $this->member($this->branchB, 'hr');
        $this->finA = $this->member($this->branchA, 'finance');

        $uB = PayrollUserFactory::new()->create(['spa_id' => $this->spa->id, 'branch_id' => $this->branchB->id, 'first_name' => 'Bea', 'last_name' => 'Branchb']);
        $uB->assignRole('therapist');
        $this->staffB = StaffFactory::new()->create(['user_id' => $uB->id, 'spa_id' => $this->spa->id, 'branch_id' => $this->branchB->id]);

        $this->profile();
        $this->profile(['staff_id' => $this->staffB->id]);
        $this->percentRule();
        foreach (['2026-09-01', '2026-09-02'] as $d) {
            $this->attend($d);
            StaffAttendanceFactory::new()->create([
                'staff_id' => $this->staffB->id, 'spa_id' => $this->spa->id, 'branch_id' => $this->branchB->id,
                'date' => $d, 'status' => 'present', 'time_in' => '09:00:00', 'time_out' => '18:00:00',
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function member(Branch $branch, string $role): User
    {
        $u = PayrollUserFactory::new()->create(['spa_id' => $this->spa->id, 'branch_id' => $branch->id]);
        $u->assignRole($role);
        StaffFactory::new()->create(['user_id' => $u->id, 'spa_id' => $this->spa->id, 'branch_id' => $branch->id]);

        return $u;
    }

    private function grant(string $role, Branch $branch, string $permission, bool $granted = true): void
    {
        BranchRolePermission::updateOrCreate(
            ['branch_id' => $branch->id, 'role_name' => $role, 'permission_name' => $permission],
            ['spa_id' => $this->spa->id, 'granted' => $granted],
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function generate(User $as)
    {
        return $this->actingAs($as)->post(route('payroll.runs.store'), [
            'run_type' => 'regular', 'year' => 2026, 'month' => 9, 'cutoff_no' => 1,
        ]);
    }

    private function draftRun(): PayrollRun
    {
        $this->generate($this->hrHead)->assertRedirect();

        return PayrollRun::query()->where('spa_id', $this->spa->id)->firstOrFail();
    }

    private function slipFor(PayrollRun $run, Staff $staff): Payslip
    {
        return Payslip::query()->where('payroll_run_id', $run->id)->where('staff_id', $staff->id)->firstOrFail();
    }

    private function adjust(User $as, Payslip $slip)
    {
        return $this->actingAs($as)->post(route('payroll.payslips.lines.store', $slip), [
            'component_code' => 'ADJ_EARNING', 'label' => 'Correction', 'amount' => '100.00',
        ]);
    }

    // ── 1. Defaults ─────────────────────────────────────────────────────────

    public function test_default_payroll_permissions_follow_the_matrix(): void
    {
        $expect = [
            'owner'   => [$this->owner,   [P::VIEW]],
            'hrHead'  => [$this->hrHead,  [P::VIEW, P::MANAGE_SETUP, P::PREPARE, P::REVEAL_IDS]],
            'finHead' => [$this->finHead, [P::VIEW, P::APPROVE, P::RELEASE, P::REVEAL_IDS]],
            'hr'      => [$this->hrA,     [P::VIEW, P::MANAGE_SETUP, P::REVEAL_IDS]],
            'finance' => [$this->finA,    [P::VIEW]],
            'therapist' => [$this->therapistA, []],
        ];
        foreach ($expect as $label => [$user, $perms]) {
            foreach (P::ALL as $perm) {
                $this->assertSame(in_array($perm, $perms, true), $user->hasBranchPermission($perm), "{$label} / {$perm}");
            }
            $this->assertFalse($user->hasBranchPermission(P::LEGACY_EDIT), "{$label} still has edit payroll");
        }

        // Owner keeps attendance editing and leave approval; nobody holds the retired permission.
        $this->assertTrue($this->owner->hasBranchPermission('edit attendance'));
        $this->assertTrue($this->owner->hasBranchPermission('edit leave requests'));
        $this->assertSame(0, Role::query()->whereHas('permissions', fn ($q) => $q->where('name', 'delete leave requests'))->count());
    }

    // ── 2. Run steps per role ────────────────────────────────────────────────

    public function test_only_spa_wide_preparers_can_generate(): void
    {
        $this->generate($this->owner)->assertForbidden();
        $this->generate($this->finHead)->assertForbidden();
        $this->generate($this->hrA)->assertForbidden();

        // Granting prepare to Branch HR is allowed, but generating covers every branch.
        $this->grant('hr', $this->branchA, P::PREPARE);
        $this->generate($this->hrA)->assertForbidden();

        $this->generate($this->hrHead)->assertRedirect();
        $this->assertSame(1, PayrollRun::query()->count());
    }

    public function test_finance_head_approves_and_releases_hr_head_cannot(): void
    {
        $run = $this->draftRun();

        $this->actingAs($this->hrHead)->post(route('payroll.runs.approve', $run))->assertForbidden();
        $this->actingAs($this->owner)->post(route('payroll.runs.approve', $run))->assertForbidden();
        $this->actingAs($this->finA)->post(route('payroll.runs.approve', $run))->assertForbidden();

        $this->actingAs($this->finHead)->post(route('payroll.runs.approve', $run))->assertRedirect(route('payroll.runs.show', $run));
        $this->actingAs($this->finHead)->post(route('payroll.runs.send-back', $run))->assertRedirect(route('payroll.runs.show', $run));
        $this->actingAs($this->finHead)->post(route('payroll.runs.approve', $run))->assertRedirect();
        $this->actingAs($this->hrHead)->post(route('payroll.runs.finalize', $run))->assertForbidden();
        $this->actingAs($this->finHead)->post(route('payroll.runs.finalize', $run))->assertRedirect();
        $this->actingAs($this->finHead)->post(route('payroll.runs.release', $run))->assertRedirect();

        $run->refresh();
        $this->assertSame(PayrollRun::STATUS_RELEASED, $run->status);
        $this->assertSame((int) $this->finHead->id, (int) $run->approved_by);

        // Maker-checker held: no self-approval badge.
        $this->actingAs($this->owner)->get(route('payroll.runs.show', $run))->assertOk()
            ->assertDontSee('Approved by the person who prepared it');
    }

    public function test_owner_fallback_self_grant_works_and_is_flagged(): void
    {
        $main = $this->branchA;
        foreach ([P::PREPARE, P::APPROVE, P::RELEASE] as $p) {
            $this->grant('owner', $main, $p);
        }

        // Owner is on Branch B in the session: payroll permissions still come from the main branch.
        $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branchB->id]);
        $this->generate($this->owner)->assertRedirect();
        $run = PayrollRun::query()->firstOrFail();
        $this->actingAs($this->owner)->post(route('payroll.runs.approve', $run))->assertRedirect();

        $this->actingAs($this->finHead)->get(route('payroll.runs.show', $run))->assertOk()
            ->assertSee('Approved by the person who prepared it');
    }

    public function test_adding_an_adjustment_counts_as_preparing_for_the_flag(): void
    {
        $run = $this->draftRun();
        $this->grant(P::ROLE_FINANCE_HEAD, $this->branchA, P::PREPARE);
        $this->adjust($this->finHead, $this->slipFor($run, $this->staff))->assertRedirect();
        $this->actingAs($this->finHead)->post(route('payroll.runs.approve', $run))->assertRedirect();

        $this->actingAs($this->owner)->get(route('payroll.runs.show', $run))->assertOk()
            ->assertSee('Approved by the person who prepared it');
    }

    // ── 3. Branch scope ──────────────────────────────────────────────────────

    public function test_branch_hr_with_prepare_adjusts_only_own_branch(): void
    {
        $run = $this->draftRun();
        $this->adjust($this->hrA, $this->slipFor($run, $this->staff))->assertForbidden();   // no prepare by default

        $this->grant('hr', $this->branchA, P::PREPARE);
        $this->adjust($this->hrA, $this->slipFor($run, $this->staff))->assertRedirect();
        $this->adjust($this->hrA, $this->slipFor($run, $this->staffB))->assertNotFound();
        $this->actingAs($this->hrA)->post(route('payroll.runs.regenerate', $run))->assertForbidden();
        $this->actingAs($this->hrA)->delete(route('payroll.runs.destroy', $run))->assertForbidden();
    }

    public function test_branch_scoped_viewers_see_only_their_branch(): void
    {
        $run = $this->draftRun();
        $nameA = $this->therapistA->name;

        $this->actingAs($this->finA)->get(route('payroll.runs.show', ['run' => $run, 'branch' => $this->branchB->id]))
            ->assertOk()->assertSee($nameA)->assertDontSee('Bea Branchb');
        $this->actingAs($this->finA)->get(route('payroll.runs.print', ['run' => $run, 'branch' => $this->branchB->id]))
            ->assertOk()->assertSee($nameA)->assertDontSee('Bea Branchb');
        $this->actingAs($this->finA)->get(route('payroll.payslips.show', $this->slipFor($run, $this->staffB)))->assertNotFound();
        $this->actingAs($this->finA)->get(route('payroll.payslips.show', $this->slipFor($run, $this->staff)))->assertOk();

        $this->actingAs($this->finHead)->get(route('payroll.runs.show', $run))
            ->assertOk()->assertSee($nameA)->assertSee('Bea Branchb');
        $this->actingAs($this->owner)->get(route('payroll.runs.show', $run))
            ->assertOk()->assertSee('Bea Branchb');

        // Run list totals for a branch-scoped user count only their branch.
        $this->actingAs($this->finA)->get(route('payroll.index'))->assertOk()->assertSee('show <strong>Branch A</strong> only', false);
    }

    public function test_staff_pay_and_statutory_ids_are_branch_scoped(): void
    {
        $this->actingAs($this->hrA)->get(route('payroll.staff.show', $this->staffB))->assertNotFound();
        $this->actingAs($this->hrA)->get(route('payroll.staff.show', $this->staff))->assertOk()->assertSee('Reveal');
        $this->actingAs($this->hrA)->post(route('payroll.staff.ids.reveal', $this->staffB))->assertNotFound();
        $this->actingAs($this->hrA)->post(route('payroll.staff.ids.reveal', $this->staff))->assertOk();
        $this->actingAs($this->hrA)->post(route('payroll.staff.profiles.store', $this->staffB), [])->assertNotFound();

        // Branch Finance: view only by default; reveal is grantable.
        $this->actingAs($this->finA)->get(route('payroll.staff.show', $this->staff))->assertOk()->assertDontSee('Edit IDs');
        $this->actingAs($this->finA)->post(route('payroll.staff.ids.reveal', $this->staff))->assertForbidden();
        $this->grant('finance', $this->branchA, P::REVEAL_IDS);
        $this->actingAs($this->finA)->post(route('payroll.staff.ids.reveal', $this->staff))->assertOk();

        $this->actingAs($this->finHead)->post(route('payroll.staff.ids.reveal', $this->staffB))->assertOk();
        $this->actingAs($this->owner)->post(route('payroll.staff.ids.reveal', $this->staffB))->assertForbidden();
    }

    public function test_setup_spa_wide_items_need_a_spa_wide_role(): void
    {
        $schedule = ['payroll_first_cutoff_day' => 15, 'payroll_pay_day_offset' => 5];
        $this->actingAs($this->hrA)->put(route('payroll.setup.schedule'), $schedule)->assertForbidden();
        $this->actingAs($this->hrHead)->put(route('payroll.setup.schedule'), $schedule)->assertRedirect();

        $wage = ['min_daily_wage' => '640', 'wage_order_ref' => 'RB IVA-23', 'min_wage_effective_from' => '2026-10-01'];
        $this->actingAs($this->hrA)->put(route('payroll.setup.wages.update', $this->branchB), $wage)->assertNotFound();
        $this->actingAs($this->hrA)->put(route('payroll.setup.wages.update', $this->branchA), $wage)->assertRedirect();

        $rule = ['target_type' => 'default', 'method' => 'percent', 'value' => '5', 'effective_from' => '2026-11-01'];
        $this->actingAs($this->hrA)->post(route('payroll.setup.rules.store'), $rule + ['branch_id' => ''])->assertForbidden();
        $this->actingAs($this->hrA)->post(route('payroll.setup.rules.store'), $rule + ['branch_id' => $this->branchB->id])->assertForbidden();
        $this->actingAs($this->hrA)->post(route('payroll.setup.rules.store'), $rule + ['branch_id' => $this->branchA->id])->assertSessionHasNoErrors();

        $this->actingAs($this->finHead)->put(route('payroll.setup.schedule'), $schedule)->assertForbidden();   // no manage setup
        $this->actingAs($this->owner)->put(route('payroll.setup.schedule'), $schedule)->assertForbidden();     // view only
    }

    // ── 4. Heads: switcher, home branch, Roles & Permissions ─────────────────

    public function test_heads_switch_branches_and_start_on_their_home_branch(): void
    {
        $this->actingAs($this->hrHead);
        $this->assertSame((int) $this->branchB->id, $this->hrHead->currentBranchId());

        $this->postJson(route('branch.switch'), ['branch_id' => $this->branchA->id])->assertOk();
        $this->assertSame((int) $this->branchA->id, (int) session('current_branch_id'));

        $this->actingAs($this->finHead)->postJson(route('branch.switch'), ['branch_id' => $this->branchB->id])->assertOk();
        $this->actingAs($this->hrA)->postJson(route('branch.switch'), ['branch_id' => $this->branchB->id])->assertForbidden();
    }

    public function test_head_role_permissions_are_stored_once_and_apply_everywhere(): void
    {
        $role = Role::findByName(P::ROLE_HR_HEAD);
        $keep = collect(\Database\Seeders\RolePermissionSeeder::HR_HEAD_PERMISSIONS)
            ->reject(fn ($p) => in_array($p, ['view business dashboard', P::PREPARE], true))->values()->all();

        $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branchB->id])
            ->put(route('owner.roles-permissions.update', $role), ['permissions' => $keep])
            ->assertRedirect();

        $row = BranchRolePermission::query()->where('role_name', P::ROLE_HR_HEAD)->where('permission_name', P::PREPARE)->firstOrFail();
        $this->assertSame((int) $this->branchA->id, (int) $row->branch_id);   // main branch, not the viewed one
        $this->assertFalse((bool) $row->granted);

        foreach ([$this->branchA, $this->branchB] as $b) {
            $this->actingAs($this->hrHead)->withSession(['current_branch_id' => $b->id]);
            $this->assertFalse($this->hrHead->fresh()->hasBranchPermission(P::PREPARE));
        }
        $this->generate($this->hrHead)->assertForbidden();
    }

    // ── 5. Staff rules for the head roles ────────────────────────────────────

    public function test_only_the_owner_assigns_head_roles_and_one_of_each(): void
    {
        $new = fn (string $role, string $email) => ['first_name' => 'N', 'last_name' => 'Ew', 'email' => $email, 'roles' => $role];

        $this->actingAs($this->hrA)->post(route('staff.store'), $new(P::ROLE_HR_HEAD, 'a@x.test'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Only the owner'));
        $this->assertSame(0, User::where('email', 'a@x.test')->count());

        // The spa already has an HR Head.
        $this->actingAs($this->owner)->post(route('staff.store'), $new(P::ROLE_HR_HEAD, 'b@x.test'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'already has an active HR Head'));

        // Branch HR cannot change or remove a head account.
        $headStaff = Staff::where('user_id', $this->finHead->id)->firstOrFail();
        $this->actingAs($this->hrA)->put(route('staff.update', $headStaff), ['roles' => 'therapist'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Only the owner'));
        $this->actingAs($this->hrA)->delete(route('staff.destroy', $headStaff))
            ->assertSessionHas('error');
        $this->assertTrue($this->finHead->fresh()->hasRole(P::ROLE_FINANCE_HEAD));
    }

    public function test_staff_records_are_spa_and_branch_scoped(): void
    {
        // Branch HR cannot edit another branch's staff; HR Head can.
        $this->actingAs($this->hrA)->put(route('staff.update', $this->staffB), ['roles' => 'receptionist'])->assertForbidden();
        $this->actingAs($this->hrHead)->put(route('staff.update', $this->staffB), ['roles' => 'receptionist'])->assertRedirect();

        // Another spa's staff record answers 404.
        $otherOwner = PayrollUserFactory::new()->create();
        $otherSpa = \Database\Factories\SpaFactory::new()->create(['owner_id' => $otherOwner->id]);
        $otherBranch = \Database\Factories\BranchFactory::new()->create(['spa_id' => $otherSpa->id]);
        $other = PayrollUserFactory::new()->create(['spa_id' => $otherSpa->id, 'branch_id' => $otherBranch->id]);
        $foreignId = StaffFactory::new()->create(['user_id' => $other->id, 'spa_id' => $otherSpa->id, 'branch_id' => $otherBranch->id])->id;
        $this->actingAs($this->hrHead)->put(route('staff.update', $foreignId), ['roles' => 'receptionist'])->assertNotFound();
        $this->actingAs($this->owner)->delete(route('staff.destroy', $foreignId))->assertNotFound();
    }

    public function test_heads_cannot_be_deployed(): void
    {
        $headStaff = Staff::where('user_id', $this->hrHead->id)->firstOrFail();
        $this->actingAs($this->owner)->post(route('branch-deployments.store'), [
            'staff_id' => $headStaff->id, 'to_branch_id' => $this->branchA->id,
            'type' => 'permanent', 'start_date' => '2026-10-20', 'reason' => 'Coverage for the main branch',
        ])->assertSessionHas('error', fn ($m) => str_contains($m, 'cannot be deployed'));
    }

    // ── 6. Locked branch (N1) ────────────────────────────────────────────────

    public function test_payroll_and_my_payslips_stay_open_on_a_locked_branch(): void
    {
        $this->branchA->forceFill(['verification_status' => 'rejected', 'verification_due_at' => null])->save();

        $this->actingAs($this->finA)->get(route('payroll.index'))->assertOk();
        $this->actingAs($this->therapistA)->get(route('my-payslips.index'))->assertOk();
        // Other branch pages are still locked.
        $this->actingAs($this->hrA)->get(route('attendance.index'))->assertForbidden();
    }

    // ── 7. Transition migration ──────────────────────────────────────────────

    public function test_transition_migration_maps_old_permissions(): void
    {
        // Rebuild the pre-refactor state.
        $hr = Role::findByName('hr');
        $hr->syncPermissions(['view payroll', 'edit payroll', 'delete leave requests', 'view staff']);
        Role::findByName('owner')->givePermissionTo(['edit payroll', 'delete leave requests']);
        Role::findByName('manager')->givePermissionTo('edit payroll');   // an admin customisation
        Role::findByName('finance')->revokePermissionTo('view payroll');
        BranchRolePermission::query()->delete();
        $this->grant('hr', $this->branchB, P::LEGACY_EDIT, false);
        $this->grant('therapist', $this->branchA, 'delete leave requests', true);

        (require base_path('database/migrations/2026_10_23_172235_payroll_rbac_transition.php'))->up();
        (require base_path('database/migrations/2026_10_23_172235_payroll_rbac_transition.php'))->up();   // idempotent
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = fn (string $role) => Role::findByName($role)->permissions()->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['view payroll', 'manage payroll setup', 'reveal statutory ids', 'view staff'], $names('hr'));
        $this->assertNotContains('edit payroll', $names('owner'));
        $this->assertContains('view payroll', $names('owner'));
        $this->assertNotContains('delete leave requests', $names('owner'));
        $this->assertEqualsCanonicalizing(array_merge(P::FROM_LEGACY_EDIT, ['view payroll']), array_values(array_intersect($names('manager'), P::ALL)));
        $this->assertContains('view payroll', $names('finance'));

        $rows = BranchRolePermission::query()->where('branch_id', $this->branchB->id)->where('role_name', 'hr')->pluck('granted', 'permission_name');
        $this->assertSame(P::FROM_LEGACY_EDIT, array_values(array_intersect(P::FROM_LEGACY_EDIT, $rows->keys()->all())));
        $this->assertFalse((bool) $rows[P::MANAGE_SETUP]);
        $this->assertSame(0, BranchRolePermission::query()->whereIn('permission_name', [P::LEGACY_EDIT, 'delete leave requests'])->count());
    }

    // ── 8. Navigation and owner notice ───────────────────────────────────────

    public function test_attendance_and_payroll_section_per_role(): void
    {
        $page = fn (User $u) => $this->actingAs($u)->get(route('payroll.index'))->assertOk();

        $page($this->finHead)->assertSee('Attendance &amp; Payroll', false)
            ->assertSee(route('payroll.staff.index'), false)
            ->assertDontSee(route('attendance.index'), false);
        $page($this->hrA)->assertSee('Attendance &amp; Payroll', false)
            ->assertSee(route('attendance.index'), false)
            ->assertSee(route('payroll.setup.index'), false);
        $this->actingAs($this->therapistA)->get(route('my-payslips.index'))->assertOk()
            ->assertDontSee(route('payroll.index'), false);
    }

    public function test_owner_notice_shows_only_while_nobody_can_run_payroll(): void
    {
        $this->actingAs($this->owner)->get(route('payroll.index'))->assertOk()
            ->assertDontSee('Owners can view payroll but not run it');

        foreach ([$this->hrHead, $this->finHead] as $head) {
            Staff::where('user_id', $head->id)->update(['employment_status' => 'inactive']);
        }
        $this->actingAs($this->owner)->get(route('payroll.index'))->assertOk()
            ->assertSee('Owners can view payroll but not run it')
            ->assertSee('prepare pay runs (no HR Head)');
    }

    public function test_staff_and_roles_pages_render_head_roles_for_the_owner_only(): void
    {
        $dump = function (string $name, $res) {
            if ($dir = getenv('RBAC_HTML_DUMP')) {
                file_put_contents($dir.'/'.$name.'.html', $res->getContent());
            }

            return $res;
        };

        $dump('staff_owner', $this->actingAs($this->owner)->get(route('staff.index')))->assertOk()
            ->assertSee('<option value="hr_head" >', false)->assertSee('HR Head');
        $dump('staff_hr', $this->actingAs($this->hrA)->get(route('staff.index')))->assertOk()
            ->assertDontSee('<option value="hr_head" >', false);

        $dump('roles_edit_head', $this->actingAs($this->owner)->get(route('owner.roles-permissions.edit', Role::findByName(P::ROLE_HR_HEAD))))
            ->assertOk()->assertSee('these permissions apply to all branches')->assertSee('Prepare Pay Runs');
        $dump('roles_index', $this->actingAs($this->owner)->get(route('owner.roles-permissions.index')))
            ->assertOk()->assertSee('Finance Head');

        $run = $this->draftRun();
        $dump('run_show', $this->actingAs($this->finHead)->get(route('payroll.runs.show', $run)))->assertOk()->assertSee('Approve');
        $dump('staff_pay_show', $this->actingAs($this->finHead)->get(route('payroll.staff.show', $this->staff)))->assertOk();
        $dump('setup_hr', $this->actingAs($this->hrA)->get(route('payroll.setup.index')))->assertOk()
            ->assertDontSee('<option value="">All branches</option>', false);
        $dump('payroll_index_owner', $this->actingAs($this->owner)->get(route('payroll.index')))->assertOk();
        $dump('payroll_index_hrhead', $this->actingAs($this->hrHead)->get(route('payroll.index')))->assertOk()->assertSee('New Run');
        $dump('run_show_hrhead', $this->actingAs($this->hrHead)->get(route('payroll.runs.show', $run)))->assertOk()->assertSee('Regenerate');
        $dump('staff_pay_show_hr', $this->actingAs($this->hrA)->get(route('payroll.staff.show', $this->staff)))->assertOk()->assertSee('Edit IDs');
        $dump('setup_hrhead', $this->actingAs($this->hrHead)->get(route('payroll.setup.index')))->assertOk()
            ->assertSee('<option value="">All branches</option>', false);
    }
}
