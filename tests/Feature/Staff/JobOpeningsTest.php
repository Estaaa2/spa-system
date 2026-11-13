<?php

namespace Tests\Feature\Staff;

use App\Models\Applicant;
use App\Models\Branch;
use App\Models\BranchProfile;
use App\Models\Interview;
use App\Models\JobPosting;
use App\Models\Spa;
use App\Models\StaffWorkSchedule;
use App\Models\User;
use App\Services\Hiring\HiringStatus;
use App\Services\Navigation\Sidebar;
use App\Support\Legal\PrivacyPolicy;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Staff scheduling & hiring Phase 5 — Job Openings.
 * Today = Thu 2026-11-12.
 */
class JobOpeningsTest extends TestCase
{
    use RefreshDatabase;

    private Spa $spa;
    private Branch $branchA;
    private Branch $branchB;
    private User $owner;
    private User $hrA;
    private User $hrB;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 10:00:00');
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->spa = Spa::withoutGlobalScopes()->where('name', PayrollTestAccountsSeeder::SPA_NAME)->firstOrFail();
        $this->branchA = Branch::withoutGlobalScopes()->where('spa_id', $this->spa->id)->where('is_main', true)->firstOrFail();
        $this->branchB = Branch::withoutGlobalScopes()->where('spa_id', $this->spa->id)->where('is_main', false)->firstOrFail();
        $this->owner = User::where('email', 'owner@levictas.test')->firstOrFail();
        $this->hrA = User::where('email', 'hr.a@levictas.test')->firstOrFail();
        $this->hrB = User::where('email', 'hr.b@levictas.test')->firstOrFail();

        foreach ([$this->branchA, $this->branchB] as $branch) {
            BranchProfile::withTrashed()->updateOrCreate(['branch_id' => $branch->id], ['deleted_at' => null]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, array<string, string>> */
    private function schedule(array $rest = ['Sunday'], array $custom = []): array
    {
        $out = [];
        foreach (StaffWorkSchedule::WEEKDAYS as $d) {
            $out[$d] = in_array($d, $rest, true) ? ['mode' => 'rest']
                : (isset($custom[$d]) ? ['mode' => 'custom', 'start' => $custom[$d][0], 'end' => $custom[$d][1]] : ['mode' => 'branch']);
        }

        return $out;
    }

    private function form(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Massage Therapist',
            'role' => 'therapist',
            'openings' => 1,
            'employment_type' => 'full_time',
            'status' => 'open',
            'deadline' => null,
            'schedule' => $this->schedule(['Sunday'], ['Wednesday' => ['12:00', '20:00']]),
            'pay_basis' => 'daily',
            'pay_min' => 600,
            'pay_max' => 750,
            'show_pay' => '1',
            'description' => 'Swedish and shiatsu.',
            'requirements' => 'TESDA NC II',
        ], $overrides);
    }

    private function posting(array $attrs = [], ?Branch $branch = null): JobPosting
    {
        $branch ??= $this->branchA;

        return JobPosting::create(array_replace([
            'spa_id' => $this->spa->id,
            'branch_id' => $branch->id,
            'created_by' => $this->owner->id,
            'title' => 'Massage Therapist',
            'role' => 'therapist',
            'openings' => 1,
            'employment_type' => 'full_time',
            'schedule' => $this->schedule(['Sunday']),
            'status' => 'open',
        ], $attrs));
    }

    private function apply(Branch $branch, array $overrides = [])
    {
        return $this->postJson('/apply/'.$branch->id, array_replace([
            'full_name' => 'Ana Applicant',
            'email' => 'ana@example.com',
            'phone' => '09171234567',
            'address' => 'Imus, Cavite',
            'schedule_fit' => '1',
            'resume' => UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf'),
            'privacy_consent' => '1',
        ], $overrides));
    }

    // ── HR: Job Openings page ────────────────────────────────────────────────

    public function test_hr_creates_an_opening_for_their_branch(): void
    {
        $this->actingAs($this->hrA)->post(route('job-openings.store'), $this->form())
            ->assertRedirect(route('job-openings.index'))
            ->assertSessionHasNoErrors();

        $p = JobPosting::firstOrFail();
        $this->assertSame($this->branchA->id, $p->branch_id);
        $this->assertSame(['mode' => 'custom', 'start' => '12:00', 'end' => '20:00'], $p->schedule['Wednesday']);
        $this->assertSame(['mode' => 'rest'], $p->schedule['Sunday']);
        $this->assertSame('₱600 – ₱750 a day', $p->payLabel());

        $this->actingAs($this->hrA)->get(route('job-openings.index'))->assertOk()
            ->assertSee('Massage Therapist')
            ->assertSee('0 of 1')
            ->assertSee('Rest: Sun');
    }

    public function test_opening_validation_rules(): void
    {
        $this->actingAs($this->hrA);

        $this->post(route('job-openings.store'), $this->form(['schedule' => $this->schedule([])]))
            ->assertSessionHasErrors(['schedule'], null, 'posting');

        $this->post(route('job-openings.store'), $this->form(['schedule' => $this->schedule(['Friday', 'Saturday', 'Sunday'])]))
            ->assertSessionHasErrors(['schedule'], null, 'posting');   // daily-paid: 1 or 2 rest days

        $this->post(route('job-openings.store'), $this->form(['pay_basis' => 'monthly', 'schedule' => $this->schedule(['Friday', 'Saturday', 'Sunday'])]))
            ->assertSessionHasNoErrors();                               // monthly: fine

        $this->post(route('job-openings.store'), $this->form(['pay_min' => 900, 'pay_max' => 700]))
            ->assertSessionHasErrors(['pay_max'], null, 'posting');

        $this->post(route('job-openings.store'), $this->form(['schedule' => $this->schedule(['Sunday'], ['Monday' => ['18:00', '10:00']])]))
            ->assertSessionHasErrors(['schedule.Monday.end'], null, 'posting');

        $this->post(route('job-openings.store'), $this->form(['deadline' => '2026-11-01']))
            ->assertSessionHasErrors(['deadline'], null, 'posting');

        // Commission: no range is kept.
        $this->post(route('job-openings.store'), $this->form(['title' => 'Commission', 'pay_basis' => 'commission']))->assertSessionHasNoErrors();
        $c = JobPosting::where('title', 'Commission')->firstOrFail();
        $this->assertNull($c->pay_min);
        $this->assertSame('Commission-based', $c->payLabel());
    }

    public function test_openings_are_scoped_to_the_selected_branch(): void
    {
        $p = $this->posting();

        $this->actingAs($this->hrB)->put(route('job-openings.update', $p), $this->form(['title' => 'Hacked']))->assertForbidden();
        $this->actingAs($this->hrB)->patch(route('job-openings.status', $p), ['status' => 'closed'])->assertForbidden();
        $this->actingAs($this->hrB)->delete(route('job-openings.destroy', $p))->assertForbidden();
        $this->actingAs($this->hrB)->get(route('job-openings.index'))->assertOk()->assertDontSee('0 of 1');

        $this->assertSame('Massage Therapist', $p->fresh()->title);
        $this->assertSame('open', $p->fresh()->status);
    }

    public function test_receptionist_cannot_open_the_page(): void
    {
        $reception = User::where('email', 'reception.a@levictas.test')->firstOrFail();
        $this->actingAs($reception)->get(route('job-openings.index'))->assertForbidden();
    }

    public function test_sidebar_lists_job_openings_under_hiring(): void
    {
        $this->actingAs($this->hrA)->get(route('job-openings.index'))->assertOk()
            ->assertSee(route('job-openings.index'), false)
            ->assertSee('Job Openings');
    }

    public function test_delete_is_soft_and_keeps_applicants(): void
    {
        $p = $this->posting();
        $a = Applicant::create(['spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id, 'job_posting_id' => $p->id,
            'position_applied' => 'therapist', 'full_name' => 'Kept', 'email' => 'kept@example.com', 'status' => 'pending']);

        $this->actingAs($this->hrA)->delete(route('job-openings.destroy', $p))->assertRedirect();

        $this->assertSoftDeleted('job_postings', ['id' => $p->id]);
        $this->assertSame($p->id, $a->fresh()->job_posting_id);
        $this->assertFalse(app(HiringStatus::class)->isHiring($this->branchA->id));

        $this->actingAs($this->hrA)->get(route('applications.index'))->assertOk()->assertSee('Massage Therapist (deleted)');
    }

    // ── Derived "We're Hiring" ───────────────────────────────────────────────

    public function test_is_hiring_and_note_come_from_open_postings(): void
    {
        $profile = $this->branchA->profile()->firstOrFail();
        $this->assertFalse($profile->is_hiring);
        $this->assertNull($profile->hiring_note);

        $this->posting(['title' => 'Massage Therapist', 'openings' => 2]);
        $this->posting(['title' => 'Receptionist', 'role' => 'receptionist']);
        $this->posting(['title' => 'Hidden draft', 'status' => 'draft']);
        $this->posting(['title' => 'Expired', 'deadline' => '2026-11-11']);
        $this->posting(['title' => 'Closed', 'status' => 'closed']);

        $profile = BranchProfile::where('branch_id', $this->branchA->id)->firstOrFail();
        $this->assertTrue($profile->is_hiring);
        $this->assertSame('Open positions: Massage Therapist (2), Receptionist', $profile->hiring_note);
        $this->assertFalse($this->branchB->profile()->firstOrFail()->is_hiring);

        $res = $this->getJson('/apply/'.$this->branchA->id.'/openings')->assertOk();
        $this->assertSame(['Massage Therapist', 'Receptionist'], collect($res->json('openings'))->pluck('title')->all());
        $this->assertSame('Rest day', $res->json('openings.0.schedule.6.label')); // Sunday
        $this->assertNull($res->json('openings.0.pay'));                         // show_pay off
    }

    public function test_deadline_today_still_counts_and_tomorrow_it_does_not(): void
    {
        $this->posting(['deadline' => '2026-11-12']);
        $this->assertTrue(app(HiringStatus::class)->isHiring($this->branchA->id));

        Carbon::setTestNow('2026-11-13 08:00:00');
        app(HiringStatus::class)->flush();
        $this->assertFalse(app(HiringStatus::class)->isHiring($this->branchA->id));
    }

    public function test_edit_branch_no_longer_saves_the_hiring_toggle(): void
    {
        $this->actingAs($this->owner)->get(route('branches.edit', $this->branchA))->assertOk()
            ->assertDontSee('name="is_hiring"', false)
            ->assertSee('Manage job openings');
    }

    // ── Public apply ─────────────────────────────────────────────────────────

    public function test_public_apply_needs_an_open_posting_and_stores_its_role(): void
    {
        $this->apply($this->branchA)->assertStatus(400); // not hiring

        $p = $this->posting(['role' => 'receptionist', 'title' => 'Front Desk']);

        $this->apply($this->branchA)->assertStatus(422)->assertJsonValidationErrors('job_posting_id');
        $this->apply($this->branchA, ['job_posting_id' => $p->id, 'schedule_fit' => null])->assertStatus(422)->assertJsonValidationErrors('schedule_fit');

        $other = $this->posting([], $this->branchB);
        $this->apply($this->branchA, ['job_posting_id' => $other->id])->assertStatus(422);

        $this->apply($this->branchA, [
            'job_posting_id' => $p->id,
            'schedule_fit' => '0',
            'schedule_fit_note' => 'No Sundays',
            // Sent by an old form: ignored, never stored (RA 10911 / Q2 A).
            'date_of_birth' => '2000-01-01', 'gender' => 'female', 'civil_status' => 'single', 'position_applied' => 'manager',
        ])->assertOk();

        $a = Applicant::firstOrFail();
        $this->assertSame($p->id, $a->job_posting_id);
        $this->assertSame('receptionist', $a->position_applied);
        $this->assertFalse($a->schedule_fit);
        $this->assertSame('No Sundays', $a->schedule_fit_note);
        $this->assertNull($a->date_of_birth);
        $this->assertNull($a->gender);
        $this->assertNull($a->civil_status);
        $this->assertSame(PrivacyPolicy::VERSION, $a->privacy_consent_version);
    }

    public function test_landing_form_no_longer_asks_for_age_gender_or_civil_status(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('name="job_posting_id"', false)
            ->assertSee('name="schedule_fit"', false)
            ->assertDontSee('name="date_of_birth"', false)
            ->assertDontSee('name="civil_status"', false)
            ->assertDontSee('name="gender"', false);

        $this->get(route('legal.privacy'))->assertOk()->assertDontSee('gender, date of birth, civil status, education');
    }

    // ── Walk-in form ─────────────────────────────────────────────────────────

    public function test_walk_in_with_and_without_a_posting(): void
    {
        $p = $this->posting(['role' => 'receptionist', 'title' => 'Front Desk']);
        $this->actingAs($this->hrA);

        $this->get(route('hiring.index'))->assertOk()
            ->assertSee('Front Desk')
            ->assertDontSee('name="date_of_birth"', false)
            ->assertSee('accept=".pdf,.doc,.docx"', false);

        $base = ['full_name' => 'Walk In', 'email' => 'walk@example.com', 'phone' => '09171234567', 'address' => 'Bacoor'];

        $this->post(route('hiring.store'), array_merge($base, ['job_posting_id' => $p->id]))
            ->assertSessionHasErrors(['schedule_fit'], null, 'application');

        $this->post(route('hiring.store'), array_merge($base, ['job_posting_id' => $p->id, 'schedule_fit' => '1', 'position_applied' => 'manager',
            'resume' => UploadedFile::fake()->create('cv.docx', 40, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'date_of_birth' => '1999-01-01']))
            ->assertSessionHasNoErrors('application');

        $a = Applicant::latest('id')->firstOrFail();
        $this->assertSame('receptionist', $a->position_applied);
        $this->assertTrue($a->schedule_fit);
        $this->assertNull($a->date_of_birth);
        $this->assertNotNull($a->resume_path);

        // No posting: the position is required and the availability list is used.
        $this->post(route('hiring.store'), array_merge($base, ['email' => 'walk2@example.com']))
            ->assertSessionHasErrors(['position_applied'], null, 'application');
        $this->post(route('hiring.store'), array_merge($base, ['email' => 'walk2@example.com', 'position_applied' => 'therapist', 'availability' => 'whenever']))
            ->assertSessionHasErrors(['availability'], null, 'application');
        $this->post(route('hiring.store'), array_merge($base, ['email' => 'walk2@example.com', 'position_applied' => 'therapist', 'availability' => 'weekends']))
            ->assertSessionHasNoErrors('application');
        $this->assertNull(Applicant::where('email', 'walk2@example.com')->firstOrFail()->job_posting_id);

        // A posting of another branch is refused.
        $other = $this->posting([], $this->branchB);
        $this->post(route('hiring.store'), array_merge($base, ['email' => 'walk3@example.com', 'job_posting_id' => $other->id, 'schedule_fit' => '1']))
            ->assertSessionHasErrors(['job_posting_id'], null, 'application');
    }

    // ── Hiring from a posting ────────────────────────────────────────────────

    private function approvedInterview(JobPosting $p, string $email): Interview
    {
        $a = Applicant::create(['spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id, 'job_posting_id' => $p->id,
            'position_applied' => $p->role, 'full_name' => 'New Hire', 'email' => $email, 'status' => Applicant::STATUS_APPROVED]);

        return Interview::create(['applicant_id' => $a->id, 'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id,
            'interviewed_by' => $this->owner->id, 'interview_date' => '2026-11-11', 'interview_time' => '10:00',
            'status' => Interview::STATUS_APPROVED]);
    }

    public function test_hire_copies_the_schedule_and_closes_a_filled_posting(): void
    {
        Mail::fake();
        $p = $this->posting(['schedule' => $this->schedule(['Monday'], ['Friday' => ['13:00', '21:00']])]);
        $interview = $this->approvedInterview($p, 'new.hire@example.com');

        $this->actingAs($this->owner)->post(route('interviews.create-staff', $interview), ['first_name' => 'New', 'last_name' => 'Hire'])
            ->assertSessionHasNoErrors();

        $staff = User::where('email', 'new.hire@example.com')->firstOrFail()->staff;
        $schedule = StaffWorkSchedule::where('staff_id', $staff->id)->with('days')->firstOrFail();
        $this->assertSame('2026-11-12', $schedule->effective_from->toDateString());
        $days = $schedule->days->keyBy('day_of_week');
        $this->assertTrue((bool) $days['Monday']->is_rest_day);
        $this->assertSame('13:00', substr((string) $days['Friday']->start_time, 0, 5));
        $this->assertNull($days['Tuesday']->start_time);

        $p->refresh();
        $this->assertSame('closed', $p->status);
        $this->assertSame('filled', $p->closed_reason);
        $this->assertSame('filled', $p->displayStatus());
    }

    public function test_filled_posting_reopens_only_with_more_openings(): void
    {
        Mail::fake();
        $p = $this->posting(['openings' => 2]);
        $this->actingAs($this->owner)->post(route('interviews.create-staff', $this->approvedInterview($p, 'one@example.com')), ['first_name' => 'One', 'last_name' => 'Hire']);
        $this->assertSame('open', $p->fresh()->status);   // 1 of 2

        $this->actingAs($this->owner)->post(route('interviews.create-staff', $this->approvedInterview($p, 'two@example.com')), ['first_name' => 'Two', 'last_name' => 'Hire']);
        $this->assertSame('filled', $p->fresh()->displayStatus());

        $this->actingAs($this->owner)->patch(route('job-openings.status', $p), ['status' => 'open'])->assertSessionHas('error');
        $this->assertSame('closed', $p->fresh()->status);

        $this->actingAs($this->owner)->put(route('job-openings.update', $p), $this->form(['openings' => 2]))
            ->assertSessionHasErrors(['openings'], null, 'posting');

        $this->actingAs($this->owner)->put(route('job-openings.update', $p), $this->form(['openings' => 3]))->assertSessionHasNoErrors();
        $this->assertSame('open', $p->fresh()->status);
        $this->assertNull($p->fresh()->closed_reason);
    }

    public function test_applicants_page_filters_by_posting(): void
    {
        $p = $this->posting(['title' => 'Front Desk', 'role' => 'receptionist']);
        Applicant::create(['spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id, 'job_posting_id' => $p->id,
            'position_applied' => 'receptionist', 'full_name' => 'From Posting', 'email' => 'fp@example.com', 'status' => 'pending',
            'schedule_fit' => true]);

        Applicant::create(['spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id,
            'position_applied' => 'therapist', 'full_name' => 'No Posting Person', 'email' => 'np@example.com', 'status' => 'pending']);

        $this->actingAs($this->hrA)->get(route('applications.index', ['posting' => $p->id]))->assertOk()
            ->assertSee('<option value="'.$p->id.'" selected', false)
            ->assertSee('From Posting')
            ->assertDontSee('No Posting Person');
    }

    public function test_test_seeders_seed_remove_and_reset(): void
    {
        $this->seed(\Tests\Fixtures\JobOpeningsTestSeeder::class);
        $this->assertSame(3, JobPosting::count());
        $this->assertSame(2, Applicant::count());
        $this->assertTrue(app(HiringStatus::class)->isHiring($this->branchA->id));

        $this->seed(\Tests\Fixtures\JobOpeningsTestResetSeeder::class);
        $this->assertSame(3, JobPosting::count());

        $this->seed(\Tests\Fixtures\JobOpeningsTestRemoveSeeder::class);
        $this->assertSame(0, JobPosting::withTrashed()->count());
        $this->assertSame(0, Applicant::withTrashed()->count());
    }

    public function test_plan_without_manpower_hides_openings_and_blocks_the_page(): void
    {
        $this->posting();
        $this->assertTrue(app(HiringStatus::class)->isHiring($this->branchA->id));

        $plan = $this->spa->fresh()->currentPlan();
        config()->set("plans.{$plan}.features", array_values(array_diff(config("plans.{$plan}.features", []), ['manpower'])));
        app(HiringStatus::class)->flush();

        $this->assertFalse(app(HiringStatus::class)->isHiring($this->branchA->id));
        $this->getJson('/apply/'.$this->branchA->id.'/openings')->assertOk()->assertJsonCount(0, 'openings');
        $this->actingAs($this->hrA)->get(route('job-openings.index'))->assertStatus(302);
    }

    public function test_mobile_api_keeps_is_hiring_and_hiring_note(): void
    {
        $this->branchA->profile()->update(['is_listed' => true, 'address' => 'Imus, Cavite', 'city' => 'Imus', 'latitude' => 14.42, 'longitude' => 120.94]);
        $this->posting(['title' => 'Massage Therapist', 'openings' => 2]);

        $res = $this->getJson('/api/spas/'.$this->spa->id)->assertOk();
        $json = json_encode($res->json());
        $this->assertStringContainsString('"is_hiring":true', $json);
        $this->assertStringContainsString('"hiring_note":"Open positions: Massage Therapist (2)"', $json);
    }
}
