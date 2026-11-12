<?php

namespace Tests\Feature\Staff;

use App\Models\BranchRolePermission;
use App\Models\Staff;
use App\Models\StaffScheduleOverride;
use App\Models\StaffWorkSchedule;
use App\Models\User;
use App\Services\Payroll\PayrollPermissions as P;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Database\Factories\PayrollUserFactory;
use Database\Factories\StaffFactory;
use Tests\Feature\Staff\Concerns\ScheduleFixtures;
use Tests\TestCase;

/**
 * Staff scheduling Phase 2 — Staff page "Work schedule" endpoints.
 * Today = Thu 2026-11-12. Nov 15 Sun · Nov 16 Mon (branch A closed) · Nov 17 Tue.
 */
class StaffScheduleControllerTest extends TestCase
{
    use RefreshDatabase;
    use ScheduleFixtures;

    private User $owner;
    private User $hr;
    private User $receptionist;
    private User $hrHead;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 09:00:00');
        $this->withoutVite();

        $this->makeWorld();
        $this->hours($this->branchA, closed: ['Monday']);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->owner = $this->admin;
        $this->owner->assignRole('owner');
        $this->user->assignRole('therapist');
        $this->hr = $this->member('hr');
        $this->receptionist = $this->member('receptionist');
        $this->hrHead = $this->member(P::ROLE_HR_HEAD);
    }

    private function member(string $role): User
    {
        $u = PayrollUserFactory::new()->create(['spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id]);
        $u->assignRole($role);
        StaffFactory::new()->create(['user_id' => $u->id, 'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id]);

        return $u;
    }

    private function save(User $as, ?Staff $staff = null, array $overrides = [])
    {
        return $this->actingAs($as)->putJson(route('staff.schedule.update', $staff ?? $this->staff), array_replace_recursive([
            'effective_from' => '2026-11-12',
            'days'           => $this->pattern(['Sunday'], ['Wednesday' => ['13:00', '21:00']]),
        ], $overrides));
    }

    // ── Read ───────────────────────────────────────────────────────────────

    public function test_view_staff_can_read_but_not_edit(): void
    {
        $this->actingAs($this->receptionist)->getJson(route('staff.schedule.show', $this->staff))
            ->assertOk()
            ->assertJsonPath('can_edit', false)
            ->assertJsonPath('has_schedule', false)
            ->assertJsonPath('branch_hours.Monday.closed', true)
            ->assertJsonPath('week.0.status', 'working')
            ->assertJsonPath('week.4.status', 'closed'); // Mon Nov 16

        $this->save($this->receptionist)->assertForbidden();
        $this->assertSame(0, StaffWorkSchedule::count());
    }

    public function test_other_spa_staff_is_not_found(): void
    {
        $otherOwner = PayrollUserFactory::new()->create(['is_owner' => true]);
        $otherSpa = \Database\Factories\SpaFactory::new()->create(['owner_id' => $otherOwner->id]);
        $otherBranch = \Database\Factories\BranchFactory::new()->create(['spa_id' => $otherSpa->id]);
        $u = PayrollUserFactory::new()->create(['spa_id' => $otherSpa->id, 'branch_id' => $otherBranch->id]);
        $otherSpaStaff = StaffFactory::new()->create(['user_id' => $u->id, 'spa_id' => $otherSpa->id, 'branch_id' => $otherBranch->id]);
        $this->actingAs($this->owner)->getJson(route('staff.schedule.show', $otherSpaStaff))->assertNotFound();
    }

    // ── Save pattern ───────────────────────────────────────────────────────

    public function test_hr_saves_a_weekly_pattern(): void
    {
        $this->save($this->hr)
            ->assertOk()
            ->assertJsonPath('schedule.has_schedule', true)
            ->assertJsonPath('schedule.current.rest_days', ['Sunday'])
            ->assertJsonPath('schedule.editing.days.Wednesday.mode', 'custom')
            ->assertJsonPath('schedule.editing.days.Wednesday.start', '13:00')
            ->assertJsonPath('schedule.week.3.status', 'rest_day'); // Sun Nov 15

        $s = StaffWorkSchedule::with('days')->sole();
        $this->assertSame($this->hr->id, $s->created_by);
        $this->assertCount(7, $s->days);
    }

    public function test_missing_rest_day_is_saved_with_a_warning(): void
    {
        $res = $this->save($this->hr, overrides: ['days' => ['Sunday' => ['mode' => 'branch']]])->assertOk();

        $this->assertStringContainsString('Art. 91', implode(' ', $res->json('warnings')));
        $this->assertSame(1, StaffWorkSchedule::count());
    }

    public function test_warns_about_closed_days_outside_hours_and_pay_profile_rest_days(): void
    {
        $this->profile(['rest_days' => ['Saturday']]);
        $warnings = implode(' ', $this->save($this->hr, overrides: [
            'days' => ['Monday' => ['mode' => 'branch'], 'Tuesday' => ['mode' => 'custom', 'start' => '08:00', 'end' => '17:00']],
        ])->assertOk()->json('warnings'));

        $this->assertStringContainsString('closed on Mondays', $warnings);
        $this->assertStringContainsString('Tuesday: the shift runs outside branch hours', $warnings);
        $this->assertStringContainsString('Payroll still uses the rest days', $warnings);
    }

    public function test_validation_end_after_start_custom_times_and_dates(): void
    {
        $this->save($this->hr, overrides: ['days' => ['Tuesday' => ['mode' => 'custom', 'start' => '17:00', 'end' => '09:00']]])
            ->assertStatus(422)->assertJsonValidationErrors('days.Tuesday.end');
        $this->save($this->hr, overrides: ['days' => ['Tuesday' => ['mode' => 'custom', 'start' => null, 'end' => null]]])
            ->assertStatus(422)->assertJsonValidationErrors(['days.Tuesday.start', 'days.Tuesday.end']);
        $this->save($this->hr, overrides: ['effective_from' => '2026-11-11'])
            ->assertStatus(422)->assertJsonValidationErrors('effective_from');
        $this->save($this->hr, overrides: ['effective_from' => '2027-11-13'])
            ->assertStatus(422)->assertJsonValidationErrors('effective_from');
        $this->save($this->hr, overrides: ['days' => ['Friday' => ['mode' => 'sometimes']]])
            ->assertStatus(422)->assertJsonValidationErrors('days.Friday.mode');

        $this->assertSame(0, StaffWorkSchedule::count());
    }

    public function test_head_accounts_are_owner_only(): void
    {
        $headStaff = Staff::where('user_id', $this->hrHead->id)->sole();

        $this->actingAs($this->hr)->getJson(route('staff.schedule.show', $headStaff))->assertOk()->assertJsonPath('can_edit', false);
        $this->save($this->hr, $headStaff)->assertForbidden();
        $this->save($this->owner, $headStaff)->assertOk();
    }

    public function test_branch_override_can_take_the_permission_away(): void
    {
        BranchRolePermission::updateOrCreate(
            ['branch_id' => $this->branchA->id, 'role_name' => 'hr', 'permission_name' => 'edit staff schedules'],
            ['spa_id' => $this->spa->id, 'granted' => false]
        );
        app(\App\Services\Permissions\BranchPermissionCache::class)->flush();

        $this->save($this->hr)->assertForbidden();
    }

    // ── One-off changes ────────────────────────────────────────────────────

    public function test_add_day_off_and_extra_shift_then_remove(): void
    {
        $this->save($this->hr)->assertOk();

        $this->actingAs($this->hr)->postJson(route('staff.schedule.overrides.store', $this->staff), [
            'date' => '2026-11-17', 'type' => 'day_off', 'reason' => 'Family event',
        ])->assertOk()->assertJsonPath('schedule.overrides.0.label', 'Day off (unpaid)')
            ->assertJsonPath('schedule.week.5.status', 'day_off');

        $this->actingAs($this->hr)->postJson(route('staff.schedule.overrides.store', $this->staff), [
            'date' => '2026-11-15', 'type' => 'shift', 'start_time' => '10:00', 'end_time' => '16:00',
        ])->assertOk()->assertJsonPath('schedule.week.3.status', 'working')
            ->assertJsonPath('schedule.week.3.start_time', '10:00');

        $override = StaffScheduleOverride::whereDate('date', '2026-11-17')->sole();
        $this->actingAs($this->hr)->deleteJson(route('staff.schedule.overrides.destroy', [$this->staff, $override]))
            ->assertOk()->assertJsonCount(1, 'schedule.overrides');
    }

    public function test_override_rules(): void
    {
        $post = fn (array $data) => $this->actingAs($this->hr)->postJson(route('staff.schedule.overrides.store', $this->staff), $data);

        $post(['date' => '2026-11-11', 'type' => 'day_off'])->assertStatus(422)->assertJsonValidationErrors('date');           // past
        $post(['date' => '2026-11-17', 'type' => 'shift'])->assertStatus(422)->assertJsonValidationErrors(['start_time', 'end_time']);
        $post(['date' => '2026-11-17', 'type' => 'shift', 'start_time' => '12:00', 'end_time' => '11:00'])->assertStatus(422)->assertJsonValidationErrors('end_time');
        $post(['date' => '2026-11-16', 'type' => 'shift', 'start_time' => '10:00', 'end_time' => '12:00'])                       // closed Monday
            ->assertStatus(422)->assertJsonPath('errors.date.0', "The branch is closed on Mondays, so a shift can't be worked that day.");

        $this->approvedLeave('2026-11-18', '2026-11-18');
        $post(['date' => '2026-11-18', 'type' => 'shift', 'start_time' => '10:00', 'end_time' => '12:00'])->assertStatus(422)->assertJsonValidationErrors('date');
        $post(['date' => '2026-11-18', 'type' => 'day_off'])->assertOk(); // a day off on a leave day is fine

        $this->assertSame(1, StaffScheduleOverride::count());
    }

    public function test_past_override_cannot_be_removed(): void
    {
        $o = StaffScheduleOverride::create(['staff_id' => $this->staff->id, 'date' => '2026-11-10', 'type' => 'day_off']);

        $this->actingAs($this->hr)->deleteJson(route('staff.schedule.overrides.destroy', [$this->staff, $o]))->assertStatus(422);
        $this->assertTrue($o->fresh() !== null);
    }

    public function test_receptionist_cannot_add_overrides(): void
    {
        $this->actingAs($this->receptionist)->postJson(route('staff.schedule.overrides.store', $this->staff), [
            'date' => '2026-11-17', 'type' => 'day_off',
        ])->assertForbidden();
    }

    // ── Staff page ─────────────────────────────────────────────────────────

    public function test_staff_page_shows_schedule_button_for_every_viewer(): void
    {
        foreach ([$this->owner, $this->hr, $this->receptionist] as $viewer) {
            $html = $this->actingAs($viewer)->get(route('staff.index'))->assertOk()->getContent();
            $this->assertStringContainsString('openWorkScheduleModal(', $html);
            $this->assertStringContainsString('id="workScheduleModal"', $html);
        }

        if ($dump = getenv('DUMP_STAFF_PAGE')) {
            file_put_contents($dump, $this->actingAs($this->hr)->get(route('staff.index'))->getContent());
        }
    }
}
