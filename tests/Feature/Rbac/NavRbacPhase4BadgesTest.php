<?php

namespace Tests\Feature\Rbac;

use App\Models\Applicant;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchRolePermission;
use App\Models\Interview;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\PurchaseRequest;
use App\Models\ReassignmentRequest;
use App\Models\RescheduleRequest;
use App\Models\Spa;
use App\Models\User;
use App\Services\Navigation\NavBadges;
use Database\Seeders\PayrollTestAccountsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Sidebar & RBAC rework, Phase 4: badge counts per role, branch isolation,
 * permission gating, counts matching their pages, cache invalidation, the
 * refresh endpoint and the rendered badge slots.
 *
 * World: PayrollTestAccountsSeeder (it already holds one pending leave request
 * from Therapist A2 at Branch A), plus one item of every badge type at Branch A.
 */
class NavRbacPhase4BadgesTest extends TestCase
{
    use RefreshDatabase;

    private Spa $spa;
    private Branch $branchA;
    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 09:00:00');
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->spa = Spa::withoutGlobalScopes()->where('name', PayrollTestAccountsSeeder::SPA_NAME)->firstOrFail();
        $this->branchA = Branch::withoutGlobalScopes()->where('spa_id', $this->spa->id)->where('is_main', true)->firstOrFail();
        $this->branchB = Branch::withoutGlobalScopes()->where('spa_id', $this->spa->id)->where('is_main', false)->firstOrFail();

        $owner = $this->account('owner@levictas.test');
        $therapist = $this->account('therapist.a1@levictas.test');

        // Appointments: one unassigned booking starting today (urgent) + one pending reassignment.
        $this->booking(['therapist_id' => null, 'appointment_date' => '2026-11-04']);
        $assigned = $this->booking(['therapist_id' => $therapist->id, 'appointment_date' => '2026-11-06']);
        $this->make(ReassignmentRequest::class, [
            'booking_id' => $assigned->id, 'requested_by' => $therapist->id, 'old_therapist_id' => $therapist->id,
            'reason' => 'Test reassignment', 'status' => 'pending',
        ]);
        // Schedule: one pending reschedule.
        $this->make(RescheduleRequest::class, [
            'booking_id' => $assigned->id, 'requested_by' => $owner->id, 'requested_date' => '2026-11-07',
            'requested_time' => '10:00:00', 'reason' => 'Test reschedule', 'status' => 'pending',
        ]);
        // Recruitment: one new applicant with an interview today.
        $applicant = $this->make(Applicant::class, [
            'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id, 'full_name' => 'Test Applicant',
            'email' => 'applicant@levictas.test', 'status' => Applicant::STATUS_PENDING,
        ]);
        $this->make(Interview::class, [
            'applicant_id' => $applicant->id, 'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id,
            'interviewed_by' => $owner->id, 'interview_date' => '2026-11-04', 'interview_time' => '14:00:00',
            'status' => Interview::STATUS_PENDING,
        ]);
        // Purchasing: one request to review.
        $this->make(PurchaseRequest::class, [
            'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id, 'requested_by' => $owner->id,
            'status' => PurchaseRequest::STATUS_PENDING,
        ]);
        // Payroll: one draft run (awaiting approval).
        $this->make(PayrollRun::class, [
            'spa_id' => $this->spa->id, 'run_type' => 'regular', 'cutoff_no' => 1, 'period_start' => '2026-10-01', 'period_end' => '2026-10-15',
            'pay_date' => '2026-10-20', 'config_version' => '1', 'generated_by' => $owner->id, 'status' => PayrollRun::STATUS_DRAFT,
        ]);
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

    private function make(string $class, array $attributes): Model
    {
        $model = new $class();
        $model->forceFill($attributes)->save();

        return $model;
    }

    private function booking(array $overrides = []): Booking
    {
        return $this->make(Booking::class, array_merge([
            'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id, 'treatment' => 'treatment_1',
            'status' => 'reserved', 'payment_status' => 'partially_paid', 'total_amount' => 1000, 'amount_paid' => 200,
            'balance_amount' => 800, 'service_type' => 'in_branch', 'booking_source' => 'online', 'customer_name' => 'Test',
            'start_time' => '15:00:00', 'end_time' => '16:00:00',
        ], $overrides));
    }

    /** @return array<string, int> link key => count */
    private function counts(string $email): array
    {
        $user = $this->account($email);
        $this->actingAs($user);

        return array_map(fn ($b) => $b['count'], NavBadges::forUser($user, $user->currentBranchId()));
    }

    // ── Per role ────────────────────────────────────────────────────────────

    public function test_each_role_sees_the_badges_it_can_act_on(): void
    {
        $this->assertSame(['appointments' => 2, 'schedule' => 1], $this->counts('reception.a@levictas.test'));
        $this->assertSame(
            ['appointments' => 2, 'schedule' => 1, 'attendance' => 1, 'purchase-requests' => 1],
            $this->counts('manager.a@levictas.test'),
        );
        $this->assertSame(['attendance' => 1, 'applicants' => 1, 'interviews' => 1], $this->counts('hr.a@levictas.test'));
        $this->assertSame(['pay-runs' => 1], $this->counts('finance.head@levictas.test'));   // approves drafts
        $this->assertSame([], $this->counts('therapist.a1@levictas.test'));
    }

    public function test_an_unassigned_booking_starting_today_is_urgent(): void
    {
        $reception = $this->account('reception.a@levictas.test');
        $badge = NavBadges::forUser($reception, $reception->currentBranchId())['appointments'];

        $this->assertTrue($badge['urgent']);
        $this->assertStringContainsString('1 starting today', $badge['label']);
        $this->assertStringContainsString('1 reassignment request', $badge['label']);
    }

    public function test_branch_b_never_sees_branch_a(): void
    {
        $this->assertSame([], $this->counts('manager.b@levictas.test'));
    }

    public function test_badges_follow_the_permission_that_acts_on_them(): void
    {
        BranchRolePermission::create([
            'branch_id' => $this->branchA->id, 'spa_id' => $this->spa->id, 'role_name' => 'receptionist',
            'permission_name' => 'edit appointments', 'granted' => false,
        ]);
        NavBadges::bump($this->spa->id);

        $this->assertSame([], $this->counts('reception.a@levictas.test'));
    }

    // ── Counts match pages ──────────────────────────────────────────────────

    public function test_appointments_page_total_matches_the_badge_past_the_list_cap(): void
    {
        foreach (range(1, 21) as $i) {
            $this->booking(['therapist_id' => null, 'appointment_date' => '2026-11-10']);
        }
        $reception = $this->account('reception.a@levictas.test');

        // 22 unassigned (21 + today's) + 1 reassignment request.
        $this->assertSame(23, NavBadges::forUser($reception, $reception->currentBranchId())['appointments']['count']);
        $this->actingAs($reception)->get(route('appointments.index'))->assertOk()->assertSee('22 to assign');
    }

    public function test_schedule_lists_every_pending_reschedule_and_matches_its_badge(): void
    {
        // Phase 4.1: a request for a booking three weeks out is listed even though
        // the calendar shows this week.
        $later = $this->booking(['therapist_id' => $this->account('therapist.a1@levictas.test')->id, 'appointment_date' => '2026-11-25']);
        $this->make(RescheduleRequest::class, [
            'booking_id' => $later->id, 'requested_by' => $this->account('owner@levictas.test')->id,
            'requested_date' => '2026-11-27', 'requested_time' => '11:00:00', 'reason' => 'Later request', 'status' => 'pending',
        ]);
        $reception = $this->account('reception.a@levictas.test');

        $this->assertSame(2, NavBadges::forUser($reception, $reception->currentBranchId())['schedule']['count']);
        $html = $this->actingAs($reception)->get(route('schedule.index'))->assertOk()
            ->assertSee('Reschedule Requests')->assertSee('2 waiting')->getContent();
        $this->assertSame(2, substr_count($html, 'onclick="openAppointmentModal(this)" aria-haspopup="dialog"'));
        $this->assertStringContainsString('data-reschedule-reason="Later request"', $html);

        // Therapists can't approve or reject, so they get neither the list nor the badge.
        $this->actingAs($this->account('therapist.a1@levictas.test'))->get(route('schedule.index'))
            ->assertOk()->assertDontSee('Reschedule Requests');
    }

    // ── Cache ───────────────────────────────────────────────────────────────

    public function test_acting_on_an_item_updates_the_cached_badge_at_once(): void
    {
        $this->assertSame(1, $this->counts('manager.a@levictas.test')['attendance']);

        LeaveRequest::query()->where('spa_id', $this->spa->id)->pending()->first()
            ->forceFill(['status' => 'approved'])->save();

        $this->assertArrayNotHasKey('attendance', $this->counts('manager.a@levictas.test'));
    }

    // ── Endpoint and markup ─────────────────────────────────────────────────

    public function test_refresh_endpoint_returns_the_same_counts(): void
    {
        $this->actingAs($this->account('manager.a@levictas.test'))->getJson(route('nav.badges'))
            ->assertOk()
            ->assertJsonPath('badges.appointments.count', 2)
            ->assertJsonPath('badges.appointments.urgent', true)
            ->assertJsonPath('badges.purchase-requests.count', 1);

        auth()->logout();
        $this->getJson(route('nav.badges'))->assertUnauthorized();
    }

    public function test_admin_without_email_verification_gets_admin_badges(): void
    {
        $this->seed(\Database\Seeders\AdminSeeder::class);
        $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->firstOrFail();
        $admin->forceFill(['email_verified_at' => null])->save();
        Spa::query()->whereKey($this->spa->id)->update(['verification_status' => 'pending']);
        NavBadges::bump(0);

        $this->actingAs($admin)->getJson(route('nav.badges'))
            ->assertOk()->assertJsonPath('badges.registered-spas.count', 1);
    }

    public function test_locked_branch_gets_no_badges(): void
    {
        $this->branchA->forceFill(['verification_status' => 'pending'])->save();

        $this->actingAs($this->account('manager.a@levictas.test'))->getJson(route('nav.badges'))
            ->assertOk()->assertExactJson(['badges' => []]);
    }

    public function test_sidebar_renders_badges_and_the_group_dot(): void
    {
        $html = $this->actingAs($this->account('manager.a@levictas.test'))->get(route('dashboard'))->assertOk()->getContent();

        // Appointments is pinned (top link) and urgent.
        $this->assertMatchesRegularExpression('/data-nav-badge="appointments" data-count="2"[^>]*class="[^"]*bg-red-100/', $html);
        // Purchase Requests sits inside the Purchasing group, so the group gets the amber dot (not hidden).
        $this->assertMatchesRegularExpression('/data-nav-group-dot="purchasing"(?![^>]*\shidden)[^>]*>/', $html);
        // Zero-count slots are rendered hidden, ready for the refresh.
        $this->assertMatchesRegularExpression('/data-nav-badge="staff" data-count="0"[^>]*hidden/', $html);
        $this->assertStringContainsString('aria-label="Log out"', $html);
    }
}
