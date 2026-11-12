<?php

namespace Tests\Feature\Staff;

use App\Models\Applicant;
use App\Models\Branch;
use App\Models\Interview;
use App\Models\JobPosting;
use App\Models\Spa;
use App\Models\User;
use Database\Seeders\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Hiring pages follow the design language (cards, .rt tables, modal contract, $btn
 * tokens, canon palette) like Staff and Appointments.
 */
class HiringUiConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Applicant $applicant;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 10:00:00');
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $spa = Spa::withoutGlobalScopes()->where('name', PayrollTestAccountsSeeder::SPA_NAME)->firstOrFail();
        $branch = Branch::withoutGlobalScopes()->where('spa_id', $spa->id)->where('is_main', true)->firstOrFail();
        $this->owner = User::where('email', 'owner@levictas.test')->firstOrFail();

        $posting = JobPosting::create(['spa_id' => $spa->id, 'branch_id' => $branch->id, 'created_by' => $this->owner->id,
            'title' => 'Massage Therapist', 'role' => 'therapist', 'status' => 'open']);
        $this->applicant = Applicant::create(['spa_id' => $spa->id, 'branch_id' => $branch->id, 'job_posting_id' => $posting->id,
            'position_applied' => 'therapist', 'full_name' => 'Ana Applicant', 'email' => 'ana@example.com', 'source' => 'website', 'status' => 'pending']);
        $other = Applicant::create(['spa_id' => $spa->id, 'branch_id' => $branch->id,
            'position_applied' => 'therapist', 'full_name' => 'Ivan Interview', 'email' => 'ivan@example.com', 'source' => 'manual', 'status' => 'interview']);
        Interview::create(['applicant_id' => $other->id, 'spa_id' => $spa->id, 'branch_id' => $branch->id,
            'interviewed_by' => $this->owner->id, 'interview_date' => '2026-11-13', 'interview_time' => '10:00', 'status' => 'pending']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        return [
            'job openings' => ['job-openings.index'],
            'applicants'   => ['applications.index'],
            'interviews'   => ['interviews.index'],
            'deployment'   => ['deployment.index'],
        ];
    }

    /** The page's own markup, without the layout (sidebar, toasts). */
    private function pageHtml(string $route): string
    {
        $html = $this->actingAs($this->owner)->get(route($route))->assertOk()->getContent();
        $start = strpos($html, 'p-4 mx-auto space-y-6 sm:p-6 max-w-7xl');
        $this->assertNotFalse($start, "$route does not use the page wrapper");
        $end = strrpos($html, '</style>');

        return substr($html, $start, ($end !== false && $end > $start ? $end : strlen($html)) - $start);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_table_pages_use_the_shared_patterns(string $route): void
    {
        $html = $this->pageHtml($route);

        $this->assertStringContainsString('rounded-2xl', $html);
        $this->assertMatchesRegularExpression('/<table role="table" class="[^"]*\brt\b/', $html);   // stacked cards on phones
        $this->assertStringContainsString('content: attr(data-label)', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString("e.key === 'Escape'", $html);
        $this->assertStringContainsString("e.key !== 'Tab'", $html);                            // focus trap
        $this->assertStringContainsString('min-h-[44px] min-w-[44px]', $html);                   // $btn tokens

        foreach (['bg-yellow-', 'bg-green-', 'bg-opacity-50', 'mt-16', 'fas fa-times', 'rounded-lg'] as $legacy) {
            $this->assertStringNotContainsString($legacy, $html, "$route still uses $legacy");
        }
    }

    public function test_application_form_uses_the_card_recipe(): void
    {
        $html = $this->pageHtml('hiring.index');

        $this->assertStringContainsString('Walk-in Applicant', $html);
        $this->assertStringContainsString('<label for="full_name"', $html);       // every field labelled
        $this->assertStringNotContainsString('bg-gradient-to-r', $html);
        $this->assertStringNotContainsString('rounded-lg', $html);
    }

    public function test_reject_is_amber_not_solid_red(): void
    {
        $html = $this->pageHtml('interviews.index');

        $this->assertStringContainsString('border-amber-300 bg-white text-amber-800', $html);
        $this->assertStringNotContainsString('bg-red-600', $html);
    }

    public function test_schedule_modal_reopens_with_errors(): void
    {
        $this->actingAs($this->owner)
            ->from(route('applications.index'))
            ->post(route('applications.schedule-interview', $this->applicant), [
                '_schedule_url' => route('applications.schedule-interview', $this->applicant),
                '_applicant_name' => 'Ana Applicant',
                'interview_date' => '',
                'interview_time' => '',
            ])
            ->assertRedirect(route('applications.index'));

        $html = $this->get(route('applications.index'))->assertOk()->getContent();
        $this->assertStringContainsString("openModal('scheduleModal', '#interview_date');", $html);
        $this->assertStringContainsString('action="'.route('applications.schedule-interview', $this->applicant).'"', $html);
    }

    public function test_deployment_uses_role_labels_and_no_browser_confirm(): void
    {
        $html = $this->pageHtml('deployment.index');

        $this->assertStringContainsString('HR Head', $html);
        $this->assertStringNotContainsString('Hr_head', $html);
        $this->assertStringNotContainsString("if (!confirm(", $html);
        $this->assertStringContainsString('role="alertdialog"', $html);
    }
}
