<?php

namespace Tests\Feature\Hotfix;

use App\Mail\ApplicantRejectedMail;
use App\Mail\StaffCredentialsMail;
use App\Models\Applicant;
use App\Models\Branch;
use App\Models\Interview;
use App\Models\Spa;
use App\Models\Staff;
use App\Models\User;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Interviews page fixes:
 *  1. Rejecting an interview emails the applicant (generic + optional message,
 *     never the internal reason).
 *  2. A staff account added by hand on the Staff page is linked to the
 *     approved interview instead of failing with "email already exists".
 *  3. Create Account from the Interviews page no longer 500s (users has no
 *     "name" column; first/last name are required).
 */
class InterviewHiringTest extends TestCase
{
    use RefreshDatabase;

    private Spa $spa;
    private Branch $branch;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-08 10:00:00');
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->spa = Spa::withoutGlobalScopes()->where('name', PayrollTestAccountsSeeder::SPA_NAME)->firstOrFail();
        $this->branch = Branch::withoutGlobalScopes()->where('spa_id', $this->spa->id)->where('is_main', true)->firstOrFail();
        $this->owner = User::where('email', 'owner@levictas.test')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function interview(string $status, string $email = 'juan.applicant@levictas.test'): Interview
    {
        $applicant = Applicant::create([
            'spa_id' => $this->spa->id,
            'branch_id' => $this->branch->id,
            'position_applied' => 'therapist',
            'full_name' => 'Juan Dela Cruz',
            'email' => $email,
            'status' => $status === Interview::STATUS_APPROVED ? Applicant::STATUS_APPROVED : Applicant::STATUS_INTERVIEW,
        ]);

        return Interview::create([
            'applicant_id' => $applicant->id,
            'spa_id' => $this->spa->id,
            'branch_id' => $this->branch->id,
            'interviewed_by' => $this->owner->id,
            'interview_date' => '2026-11-07',
            'interview_time' => '10:00',
            'status' => $status,
        ]);
    }

    public function test_reject_emails_applicant_without_internal_reason(): void
    {
        Mail::fake();
        $interview = $this->interview(Interview::STATUS_PENDING);

        $this->actingAs($this->owner)
            ->post(route('interviews.reject', $interview), [
                'rejection_reason' => 'INTERNAL: weak technique, late to interview',
                'applicant_message' => 'You are welcome to apply again next year.',
            ])
            ->assertSessionHasNoErrors();

        $interview->refresh();
        $this->assertSame(Interview::STATUS_REJECTED, $interview->status);
        $this->assertSame(Applicant::STATUS_REJECTED, $interview->applicant->status);

        Mail::assertSent(ApplicantRejectedMail::class, function (ApplicantRejectedMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('juan.applicant@levictas.test')
                && str_contains($html, 'You are welcome to apply again next year.')
                && ! str_contains($html, 'INTERNAL');
        });
    }

    public function test_reject_without_message_still_sends_generic_email(): void
    {
        Mail::fake();
        $interview = $this->interview(Interview::STATUS_PENDING);

        $this->actingAs($this->owner)
            ->post(route('interviews.reject', $interview), ['rejection_reason' => 'Not a fit'])
            ->assertSessionHasNoErrors();

        Mail::assertSent(ApplicantRejectedMail::class, fn ($mail) => ! str_contains($mail->render(), 'Not a fit'));
    }

    public function test_create_account_from_interview_works_and_hides_button(): void
    {
        Mail::fake();
        $interview = $this->interview(Interview::STATUS_APPROVED);

        $this->actingAs($this->owner)
            ->post(route('interviews.create-staff', $interview), [
                'first_name' => 'Juan', 'middle_name' => '', 'last_name' => 'Dela Cruz',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $user = User::where('email', 'juan.applicant@levictas.test')->firstOrFail();
        $this->assertSame('Juan', $user->first_name);
        $this->assertSame('Dela Cruz', $user->last_name);
        $this->assertTrue($user->hasRole('therapist'));
        $this->assertSame($interview->applicant_id, $user->staff->applicant_id);
        $this->assertTrue($interview->fresh()->staff_account_created);
        $this->assertSame(Applicant::STATUS_HIRED, $interview->applicant->fresh()->status);
        Mail::assertSent(StaffCredentialsMail::class);

        $this->get(route('interviews.index'))->assertOk()
            ->assertSee('Account Created')
            ->assertDontSee('onclick="openCreateStaffModal(this)"', false);
    }

    public function test_create_account_requires_name_parts(): void
    {
        $interview = $this->interview(Interview::STATUS_APPROVED);

        $this->actingAs($this->owner)
            ->post(route('interviews.create-staff', $interview), [])
            ->assertSessionHasErrors('staff');

        $this->assertFalse(User::where('email', 'juan.applicant@levictas.test')->exists());
    }

    public function test_account_added_on_staff_page_is_linked_to_interview(): void
    {
        Mail::fake();
        $interview = $this->interview(Interview::STATUS_APPROVED);

        $this->actingAs($this->owner)
            ->withSession(['current_branch_id' => $this->branch->id])
            ->post(route('staff.store'), [
                'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
                'email' => 'juan.applicant@levictas.test', 'roles' => 'therapist',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($interview->fresh()->staff_account_created);
        $this->assertSame(Applicant::STATUS_HIRED, $interview->applicant->fresh()->status);
    }

    public function test_legacy_unlinked_staff_account_shows_link_button_and_links(): void
    {
        Mail::fake();
        $interview = $this->interview(Interview::STATUS_APPROVED);

        // Account created on the Staff page before this fix: no applicant link.
        $user = User::create([
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan.applicant@levictas.test',
            'password' => bcrypt('secret-123'), 'spa_id' => $this->spa->id, 'branch_id' => $this->branch->id,
        ]);
        $user->assignRole('therapist');
        Staff::create([
            'user_id' => $user->id, 'spa_id' => $this->spa->id, 'branch_id' => $this->branch->id,
            'employment_status' => 'active', 'hire_date' => now(),
        ]);

        $this->actingAs($this->owner)->get(route('interviews.index'))->assertOk()
            ->assertSee('Link Existing Account');

        $this->post(route('interviews.create-staff', $interview))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertTrue($interview->fresh()->staff_account_created);
        $this->assertSame($interview->applicant_id, $user->staff->fresh()->applicant_id);
        $this->assertSame(1, User::where('email', 'juan.applicant@levictas.test')->count());
        Mail::assertNotSent(StaffCredentialsMail::class);
    }

    public function test_email_owned_by_customer_gives_clear_error_not_500(): void
    {
        $interview = $this->interview(Interview::STATUS_APPROVED);

        User::create([
            'first_name' => 'Juan', 'last_name' => 'Customer', 'email' => 'juan.applicant@levictas.test',
            'password' => bcrypt('secret-123'),
        ]);

        $this->actingAs($this->owner)
            ->post(route('interviews.create-staff', $interview), ['first_name' => 'Juan', 'last_name' => 'Dela Cruz'])
            ->assertSessionHasErrors('staff');

        $this->assertFalse($interview->fresh()->staff_account_created);
    }
}
