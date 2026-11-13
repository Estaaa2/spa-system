<?php

namespace Tests\Feature\Rbac;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchRolePermission;
use App\Models\LeaveRequest;
use App\Models\ReassignmentRequest;
use App\Models\Spa;
use App\Models\User;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Sidebar & RBAC rework, Phase 6.1: the mobile API files leave and "can't make
 * it" under the same rules as the web (overview F-14 and the reassignment gap).
 */
class NavRbacPhase61ApiTest extends TestCase
{
    use RefreshDatabase;

    private Spa $spa;
    private Branch $branchA;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 09:00:00');
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

    private function leave(): array
    {
        return ['leave_type' => 'personal', 'start_date' => '2026-11-10', 'end_date' => '2026-11-10', 'reason' => 'Family matter to attend to.'];
    }

    private function deny(string $role, string $permission): void
    {
        BranchRolePermission::create(['branch_id' => $this->branchA->id, 'spa_id' => $this->spa->id,
            'role_name' => $role, 'permission_name' => $permission, 'granted' => false]);
        app(\App\Services\Permissions\BranchPermissionCache::class)->flush();
    }

    private function bookingFor(User $therapist): Booking
    {
        $booking = new Booking();
        $booking->forceFill([
            'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id, 'therapist_id' => $therapist->id,
            'treatment' => 'treatment_1', 'status' => 'reserved', 'payment_status' => 'partially_paid',
            'total_amount' => 1000, 'amount_paid' => 200, 'balance_amount' => 800, 'service_type' => 'in_branch',
            'booking_source' => 'online', 'customer_name' => 'Test', 'appointment_date' => '2026-11-06',
            'start_time' => '15:00:00', 'end_time' => '16:00:00',
        ])->save();

        return $booking;
    }

    // ── Leave (F-14) ────────────────────────────────────────────────────────

    public function test_a_customer_cannot_file_leave_through_the_app(): void
    {
        $customer = User::factory()->create(['email' => 'app.customer@levictas.test', 'spa_id' => null, 'branch_id' => null]);
        $customer->assignRole('customer');
        Sanctum::actingAs($customer);

        $this->postJson('/api/leave-requests', $this->leave())->assertForbidden();
        $this->assertSame(0, LeaveRequest::where('user_id', $customer->id)->count());
    }

    public function test_a_therapist_files_leave_unless_the_owner_turned_it_off(): void
    {
        $therapist = $this->account('therapist.a1@levictas.test');
        Sanctum::actingAs($therapist);

        $this->postJson('/api/leave-requests', $this->leave())->assertSuccessful();
        $this->assertSame(1, LeaveRequest::where('user_id', $therapist->id)->count());

        $this->deny('therapist', 'create leave requests');
        LeaveRequest::where('user_id', $therapist->id)->delete();
        $this->postJson('/api/leave-requests', $this->leave())->assertForbidden();
    }

    // ── "Can't make it" ─────────────────────────────────────────────────────

    public function test_the_app_follows_the_web_reassignment_rule(): void
    {
        $therapist = $this->account('therapist.a1@levictas.test');
        $booking = $this->bookingFor($therapist);
        Sanctum::actingAs($therapist);
        $this->deny('therapist', 'request appointment reassignment');

        $this->postJson("/api/appointments/{$booking->id}/reassignment-requests", ['reason' => 'Feeling unwell today.'])
            ->assertForbidden();
        $this->assertSame(0, ReassignmentRequest::count());

        // "Does treatments" on the staff record still allows it (same as the web).
        $therapist->staff->forceFill(['performs_treatments' => true])->save();
        $this->postJson("/api/appointments/{$booking->id}/reassignment-requests", ['reason' => 'Feeling unwell today.'])
            ->assertSuccessful();
        $this->assertSame(1, ReassignmentRequest::count());
    }

    public function test_also_request_leave_needs_the_leave_permission(): void
    {
        $therapist = $this->account('therapist.a1@levictas.test');
        $booking = $this->bookingFor($therapist);
        // The web modal offers "Also request leave" by default…
        $this->actingAs($therapist)->get(route('appointments.index'))->assertOk()->assertSee('id="reassign_also_leave"', false);

        $this->deny('therapist', 'create leave requests');
        Sanctum::actingAs($therapist);

        $this->postJson("/api/appointments/{$booking->id}/reassignment-requests", [
            'reason' => 'Feeling unwell today.', 'request_leave_too' => true, 'leave_type' => 'sick',
        ])->assertForbidden()->assertJsonPath('message', fn ($m) => str_contains($m, "can't file leave requests"));
        $this->assertSame(0, ReassignmentRequest::count());
        $this->assertSame(0, LeaveRequest::where('user_id', $therapist->id)->count());

        // …and hides it once the role can't file leave.
        $this->actingAs($therapist)->get(route('appointments.index'))->assertOk()->assertDontSee('id="reassign_also_leave"', false);
    }
}
