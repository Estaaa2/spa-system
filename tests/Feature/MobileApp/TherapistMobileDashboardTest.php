<?php

namespace Tests\Feature\MobileApp;

use App\Models\Branch;
use App\Models\Booking;
use App\Models\OperatingHours;
use App\Models\Spa;
use App\Models\StaffBranchDeployment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Tests\TestCase;

/**
 * Therapist mobile dashboard (Nov 2026): GET /api/therapist/dashboard is the
 * web dashboard's personal parts (liveData's my_today + pending_deployment,
 * and the transfer card); the transfer actions go through the web's own
 * BranchDeploymentController and answer JSON for the app.
 */
class TherapistMobileDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $therapist;
    private Branch $branchA;
    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
        $this->seed(PayrollTestAccountsSeeder::class);

        $this->therapist = User::where('email', 'therapist.a1@levictas.test')->firstOrFail();
        $this->branchA = Branch::findOrFail($this->therapist->branch_id);
        $this->branchB = Branch::where('spa_id', $this->therapist->spa_id)
            ->where('id', '!=', $this->branchA->id)->firstOrFail();

        // Branch B open every day, so a transfer start date is never on a closed day.
        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
            OperatingHours::updateOrCreate(
                ['branch_id' => $this->branchB->id, 'day_of_week' => $day],
                ['opening_time' => '09:00:00', 'closing_time' => '18:00:00', 'is_closed' => false],
            );
        }
    }

    private function request(array $overrides = []): array
    {
        return array_merge([
            'to_branch_id' => $this->branchB->id,
            'start_date'   => now()->addDays(3)->toDateString(),
            'is_permanent' => true,
            'notes'        => 'Closer to home.',
        ], $overrides);
    }

    public function test_dashboard_has_my_today_and_the_transfer_card(): void
    {
        Booking::factory()->create([
            'spa_id' => $this->therapist->spa_id,
            'branch_id' => $this->branchA->id,
            'therapist_id' => $this->therapist->id,
            'appointment_date' => now()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => 'reserved',
            'treatment' => 'Swedish Massage',
        ]);
        Sanctum::actingAs($this->therapist);

        $this->getJson('/api/therapist/dashboard')->assertOk()
            ->assertJsonPath('my_today.stats.total', 1)
            ->assertJsonPath('my_today.stats.remaining', 1)
            ->assertJsonPath('my_today.appointments.0.start_time_fmt', '10:00')
            ->assertJsonPath('pending_deployment', null)
            ->assertJsonPath('transfer.open_request', null)
            ->assertJsonPath('transfer.branches.0.id', $this->branchB->id)
            ->assertJsonMissingPath('kpis');
    }

    public function test_customers_cannot_open_the_therapist_dashboard(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        Sanctum::actingAs($customer);

        $this->getJson('/api/therapist/dashboard')->assertForbidden();
    }

    public function test_request_then_cancel_a_transfer(): void
    {
        Sanctum::actingAs($this->therapist);

        $this->postJson('/api/branch-deployments/self-request', $this->request())
            ->assertCreated()
            ->assertJsonPath('message', 'Your transfer request has been submitted and is awaiting approval.');

        $id = $this->getJson('/api/therapist/dashboard')
            ->assertJsonPath('transfer.open_request.status', 'pending')
            ->assertJsonPath('transfer.open_request.can_cancel', true)
            ->json('transfer.open_request.id');

        // A second one is refused while the first is open.
        $this->postJson('/api/branch-deployments/self-request', $this->request())->assertStatus(422);

        $this->deleteJson("/api/branch-deployments/{$id}/self-cancel")->assertOk();
        $this->assertSame('cancelled', StaffBranchDeployment::find($id)->status);
    }

    public function test_reason_is_required_and_other_spas_branches_are_refused(): void
    {
        Sanctum::actingAs($this->therapist);

        $this->postJson('/api/branch-deployments/self-request', $this->request(['notes' => '']))
            ->assertStatus(422)->assertJsonValidationErrors('notes');

        $otherSpa = Spa::factory()->create();
        $foreign = Branch::factory()->create(['spa_id' => $otherSpa->id]);

        $this->postJson('/api/branch-deployments/self-request', $this->request(['to_branch_id' => $foreign->id]))
            ->assertStatus(422)->assertJsonPath('message', 'That branch is not part of your spa.');
        $this->assertSame(0, StaffBranchDeployment::count());
    }

    public function test_respond_to_a_deployment_and_only_your_own(): void
    {
        $deployment = StaffBranchDeployment::create([
            'staff_id' => $this->therapist->staff->id,
            'spa_id' => $this->therapist->spa_id,
            'requested_by' => User::where('email', 'owner@levictas.test')->value('id'),
            'from_branch_id' => $this->branchA->id,
            'to_branch_id' => $this->branchB->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'is_permanent' => false,
            'status' => 'approved',
            'staff_response' => 'pending',
            'notes' => 'Cover for Branch B.',
        ]);

        // Someone else's deployment.
        Sanctum::actingAs(User::where('email', 'therapist.a2@levictas.test')->firstOrFail());
        $this->postJson("/api/branch-deployments/{$deployment->id}/staff-respond", ['response' => 'accepted'])
            ->assertForbidden();

        Sanctum::actingAs($this->therapist);
        $this->getJson('/api/therapist/dashboard')
            ->assertJsonPath('pending_deployment.id', $deployment->id)
            ->assertJsonPath('pending_deployment.status', 'approved')
            ->assertJsonPath('pending_deployment.notes', 'Cover for Branch B.');

        $this->postJson("/api/branch-deployments/{$deployment->id}/staff-respond", ['response' => 'declined'])
            ->assertStatus(422)->assertJsonValidationErrors('decline_reason');

        $this->postJson("/api/branch-deployments/{$deployment->id}/staff-respond", [
            'response' => 'declined', 'decline_reason' => 'Family reasons.',
        ])->assertOk();

        $this->assertSame('cancelled', $deployment->fresh()->status);
        $this->getJson('/api/therapist/dashboard')->assertJsonPath('pending_deployment', null);
    }

    public function test_web_transfer_request_still_redirects(): void
    {
        $this->actingAs($this->therapist)
            ->post('/branch-deployments/self-request', $this->request())
            ->assertRedirect(route('dashboard'));
    }
}
