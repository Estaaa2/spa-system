<?php

namespace Tests\Feature\Staff;

use App\Models\Applicant;
use App\Models\Branch;
use App\Models\Interview;
use App\Models\JobPosting;
use App\Models\Spa;
use App\Models\User;
use App\Support\TableFilters;
use Database\Seeders\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Table filters workstream: one shared filter bar (Pay Runs pattern) on Applicants,
 * Interviews, Job Openings, Staff, Staff Pay and Deployment. Server-side, GET.
 */
class TableFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Spa $spa;
    private Branch $branchA;
    private User $owner;
    private User $hrA;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 10:00:00');
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->spa = Spa::withoutGlobalScopes()->where('name', PayrollTestAccountsSeeder::SPA_NAME)->firstOrFail();
        $this->branchA = Branch::withoutGlobalScopes()->where('spa_id', $this->spa->id)->where('is_main', true)->firstOrFail();
        $this->owner = User::where('email', 'owner@levictas.test')->firstOrFail();
        $this->hrA = User::where('email', 'hr.a@levictas.test')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function applicant(string $name, string $source, ?JobPosting $p = null, string $status = 'pending'): Applicant
    {
        return Applicant::create([
            'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id, 'job_posting_id' => $p?->id,
            'position_applied' => $p?->role ?? 'therapist', 'full_name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com', 'source' => $source, 'status' => $status,
        ]);
    }

    private function posting(string $title, string $status = 'open'): JobPosting
    {
        return JobPosting::create([
            'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id, 'created_by' => $this->owner->id,
            'title' => $title, 'role' => 'therapist', 'status' => $status,
        ]);
    }

    public function test_helper_ignores_unknown_values(): void
    {
        $r = Request::create('/x', 'GET', ['status' => 'hacked', 'q' => '  ana  ', 'n' => ['x']]);
        $this->assertNull(TableFilters::choice($r, 'status', ['pending']));
        $this->assertNull(TableFilters::choice($r, 'n', ['x']));
        $this->assertSame('ana', TableFilters::search($r));
        $this->assertTrue(TableFilters::matches('ANA', 'Ana Reyes'));
        $this->assertSame(2, TableFilters::activeCount(['a' => 'x', 'b' => null, 'c' => '', 'd' => '1']));
    }

    public function test_applicants_badge_and_filters(): void
    {
        $p = $this->posting('Massage Therapist');
        $this->applicant('Online Olive', 'website', $p);
        $this->applicant('Walkin Wally', 'manual', $p);
        $this->applicant('Walkin General', 'manual', null, 'hired');

        $this->actingAs($this->hrA);

        $this->get(route('applications.index'))->assertOk()
            ->assertSee('Online')->assertSee('Walk-in')
            ->assertSee('Online Olive')->assertSee('Walkin General')
            ->assertSee('3 applicants')
            ->assertDontSee('filterStatus(', false);

        $this->get(route('applications.index', ['source' => 'manual']))->assertOk()
            ->assertDontSee('Online Olive')->assertSee('Walkin Wally')->assertSee('Walkin General')
            ->assertSee('Showing 2 of 3 applicants')->assertSee('Clear');

        $this->get(route('applications.index', ['source' => 'manual', 'posting' => $p->id]))->assertOk()
            ->assertSee('Walkin Wally')->assertDontSee('Walkin General')->assertSee('2 filters on');

        $this->get(route('applications.index', ['status' => 'hired']))->assertOk()
            ->assertSee('Walkin General')->assertDontSee('Walkin Wally');

        $this->get(route('applications.index', ['q' => 'olive']))->assertOk()
            ->assertSee('Online Olive')->assertDontSee('Walkin Wally');

        // Unknown values are ignored, not errors.
        $this->get(route('applications.index', ['source' => 'drop table', 'posting' => '99999']))->assertOk()
            ->assertSee('Walkin Wally')->assertSee('3 applicants');
    }

    public function test_interviews_filter_keeps_summary_totals(): void
    {
        foreach ([['Pat Pending', 'pending'], ['Abe Approved', 'approved'], ['Rex Rejected', 'rejected']] as [$name, $status]) {
            $a = $this->applicant($name, 'manual', null, 'interview');
            Interview::create(['applicant_id' => $a->id, 'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id,
                'interviewed_by' => $this->owner->id, 'interview_date' => '2026-11-13', 'interview_time' => '10:00', 'status' => $status]);
        }

        $this->actingAs($this->owner)->get(route('interviews.index', ['status' => 'approved']))->assertOk()
            ->assertSee('Abe Approved')->assertDontSee('Pat Pending')->assertDontSee('Rex Rejected')
            ->assertSee('Showing 1 of 3 interviews')
            ->assertSee('>3</h3>', false);   // Total Interviews card still counts all
    }

    public function test_job_openings_filter_by_display_status(): void
    {
        $this->posting('Open One');
        $this->posting('Draft One', 'draft');

        $this->actingAs($this->hrA)->get(route('job-openings.index', ['status' => 'draft']))->assertOk()
            ->assertSee('Draft One')->assertDontSee('Open One')->assertSee('Showing 1 of 2 job openings');
    }

    public function test_staff_directory_filters_by_role_and_search(): void
    {
        $this->actingAs($this->owner);

        $this->get(route('staff.index', ['role' => 'therapist']))->assertOk()
            ->assertSee('therapist.a1@levictas.test')->assertDontSee('reception.a@levictas.test');

        $this->get(route('staff.index', ['q' => 'Rina']))->assertOk()
            ->assertSee('reception.a@levictas.test')->assertDontSee('therapist.a1@levictas.test')
            ->assertSee('1 filter on');
    }

    public function test_staff_pay_keeps_setup_filter_and_adds_search(): void
    {
        $this->actingAs($this->owner);

        // Existing links (?filter=no_profile) still work.
        $this->get(route('payroll.staff.index', ['filter' => 'no_profile']))->assertOk()
            ->assertSee('<option value="no_profile" selected', false);

        $this->get(route('payroll.staff.index', ['q' => 'Tess']))->assertOk()
            ->assertSee('Tess')->assertDontSee('Rina Reception A');
    }

    public function test_deployment_filters(): void
    {
        $this->actingAs($this->hrA)->get(route('deployment.index', ['deployment' => 'pending']))->assertOk()
            ->assertSee('No staff member matches these filters.');

        $this->actingAs($this->hrA)->get(route('deployment.index', ['role' => 'therapist']))->assertOk()
            ->assertSee('therapist.a1@levictas.test')->assertDontSee('reception.a@levictas.test');
    }
}
