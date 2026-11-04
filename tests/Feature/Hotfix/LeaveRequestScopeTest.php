<?php

namespace Tests\Feature\Hotfix;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\LeaveRequest;
use App\Models\ReassignmentRequest;
use App\Models\Spa;
use App\Models\User;
use Database\Factories\BookingFactory;
use Database\Factories\BranchFactory;
use Database\Factories\PayrollUserFactory;
use Database\Factories\SpaFactory;
use Database\Factories\StaffFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Leave approve/reject/affected-bookings stay inside the reviewer's spa and branch. */
class LeaveRequestScopeTest extends TestCase
{
    use RefreshDatabase;

    private Spa $spa;
    private Branch $a;
    private Branch $b;
    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-23 09:00:00');
        Mail::fake();
        $this->withoutVite();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        [$this->spa, $this->a, $this->b] = $this->world();
        $this->hr = $this->member($this->spa, $this->a, 'hr');
    }

    private function world(): array
    {
        $owner = PayrollUserFactory::new()->create(['is_owner' => true]);
        $owner->assignRole('owner');
        $spa = SpaFactory::new()->create([
            'owner_id' => $owner->id, 'verification_status' => 'verified',
            'trial_used' => true, 'trial_started_at' => now()->subDays(3), 'trial_ends_at' => now()->addDays(20),
        ]);
        $a = BranchFactory::new()->create(['spa_id' => $spa->id, 'is_main' => true, 'verification_status' => 'verified']);
        $b = BranchFactory::new()->create(['spa_id' => $spa->id, 'verification_status' => 'verified']);
        $owner->forceFill(['spa_id' => $spa->id, 'branch_id' => $a->id])->save();

        return [$spa, $a, $b];
    }

    private function member(Spa $spa, Branch $branch, string $role): User
    {
        $u = PayrollUserFactory::new()->create(['spa_id' => $spa->id, 'branch_id' => $branch->id]);
        $u->assignRole($role);
        StaffFactory::new()->create(['user_id' => $u->id, 'spa_id' => $spa->id, 'branch_id' => $branch->id, 'employment_status' => 'active']);

        return $u;
    }

    private function leave(User $u, Branch $branch): LeaveRequest
    {
        return LeaveRequest::create([
            'user_id' => $u->id, 'spa_id' => $branch->spa_id, 'branch_id' => $branch->id,
            'leave_type' => 'sick', 'start_date' => '2026-10-26', 'end_date' => '2026-10-27',
            'reason' => 'Feeling unwell, doctor advised rest.', 'status' => 'pending',
        ]);
    }

    private function approve(User $as, LeaveRequest $l, array $body = [])
    {
        return $this->actingAs($as)->postJson(route('leave-requests.approve', $l), $body);
    }

    public function test_same_branch_reviewer_can_approve(): void
    {
        $l = $this->leave($this->member($this->spa, $this->a, 'therapist'), $this->a);
        $this->approve($this->hr, $l)->assertOk();
        $this->assertSame('approved', $l->fresh()->status);
    }

    public function test_other_spa_leave_is_not_found_and_unchanged(): void
    {
        [$spa2, $a2] = $this->world();
        $l = $this->leave($this->member($spa2, $a2, 'therapist'), $a2);

        $this->approve($this->hr, $l)->assertNotFound();
        $this->actingAs($this->hr)->postJson(route('leave-requests.reject', $l), ['rejection_reason' => 'No reason at all'])->assertNotFound();
        $this->actingAs($this->hr)->getJson(route('leave-requests.affected-bookings', $l))->assertNotFound();
        $this->assertSame('pending', $l->fresh()->status);
    }

    public function test_other_branch_of_same_spa_is_not_found(): void
    {
        $l = $this->leave($this->member($this->spa, $this->b, 'therapist'), $this->b);
        $this->approve($this->hr, $l)->assertNotFound();
        $this->assertSame('pending', $l->fresh()->status);
    }

    public function test_reviewer_cannot_review_own_leave_and_it_is_not_in_queue(): void
    {
        $own = $this->leave($this->hr, $this->a);

        $this->approve($this->hr, $own)->assertForbidden();
        $this->actingAs($this->hr)->postJson(route('leave-requests.reject', $own), ['rejection_reason' => 'Self reject test'])->assertForbidden();
        $this->assertSame('pending', $own->fresh()->status);

        $this->actingAs($this->hr)->getJson(route('leave-requests.index'))->assertOk()->assertJsonCount(0);
        // The submitter can still preview their own affected bookings.
        $this->actingAs($this->hr)->getJson(route('leave-requests.affected-bookings', $own))->assertOk();
    }

    public function test_second_review_is_refused(): void
    {
        $l = $this->leave($this->member($this->spa, $this->a, 'therapist'), $this->a);
        $this->approve($this->hr, $l)->assertOk();
        $this->approve($this->hr, $l)->assertStatus(422);
        $this->actingAs($this->hr)->postJson(route('leave-requests.reject', $l), ['rejection_reason' => 'Too late now'])->assertStatus(422);
        $this->assertSame('approved', $l->fresh()->status);
    }

    public function test_reassignment_ignores_foreign_bookings_and_ineligible_therapists(): void
    {
        $therapist = $this->member($this->spa, $this->a, 'therapist');
        $cover = $this->member($this->spa, $this->a, 'therapist');
        $l = $this->leave($therapist, $this->a);

        [$spa2, $a2] = $this->world();
        $t2 = $this->member($spa2, $a2, 'therapist');
        $foreign = BookingFactory::new()->create(['spa_id' => $spa2->id, 'branch_id' => $a2->id, 'therapist_id' => $t2->id,
            'appointment_date' => '2026-10-26', 'start_time' => '10:00:00', 'end_time' => '11:00:00', 'status' => 'reserved', 'treatment' => 'treatment_1']);
        $foreignFlag = ReassignmentRequest::create(['booking_id' => $foreign->id, 'requested_by' => $t2->id,
            'old_therapist_id' => $t2->id, 'reason' => 'x', 'status' => 'pending']);

        $own = BookingFactory::new()->create(['spa_id' => $this->spa->id, 'branch_id' => $this->a->id, 'therapist_id' => $therapist->id,
            'appointment_date' => '2026-10-26', 'start_time' => '10:00:00', 'end_time' => '11:00:00', 'status' => 'reserved', 'treatment' => 'treatment_1']);

        DB::table('bookings')->update(['appointment_date' => DB::raw('substr(appointment_date, 1, 10)')]);
        $this->approve($this->hr, $l, ['reassignments' => [
            ['booking_id' => $foreign->id, 'new_therapist_id' => $cover->id],
            ['booking_id' => $own->id, 'new_therapist_id' => $t2->id],  // therapist of another spa
        ]])->assertOk();

        $this->assertSame($t2->id, (int) $foreign->fresh()->therapist_id);
        $this->assertSame('pending', $foreignFlag->fresh()->status);
        $this->assertSame($therapist->id, (int) $own->fresh()->therapist_id);
        $this->assertSame('pending', ReassignmentRequest::where('booking_id', $own->id)->value('status'));

        // A valid pick still works.
        $l2 = $this->leave($this->member($this->spa, $this->a, 'therapist'), $this->a);
        $own2 = BookingFactory::new()->create(['spa_id' => $this->spa->id, 'branch_id' => $this->a->id, 'therapist_id' => $l2->user_id,
            'appointment_date' => '2026-10-27', 'start_time' => '13:00:00', 'end_time' => '14:00:00', 'status' => 'reserved', 'treatment' => 'treatment_1']);
        // SQLite stores the date cast with a time part; MySQL's DATE column does not.
        DB::table('bookings')->update(['appointment_date' => DB::raw('substr(appointment_date, 1, 10)')]);
        $this->approve($this->hr, $l2, ['reassignments' => [['booking_id' => $own2->id, 'new_therapist_id' => $cover->id]]])->assertOk();
        $this->assertSame($cover->id, (int) $own2->fresh()->therapist_id);
    }
}
