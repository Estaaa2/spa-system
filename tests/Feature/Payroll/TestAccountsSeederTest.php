<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollRun;
use App\Models\Spa;
use App\Models\User;
use Database\Seeders\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Phase 7 — the localhost test accounts seed cleanly, every account can log in and
 * open its pages, and the seeded data produces a payroll run with the expected warnings.
 */
class TestAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-26 09:00:00');
        $this->seed(PayrollTestAccountsSeeder::class);
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

    public function test_it_creates_one_account_per_role_and_runs_only_once(): void
    {
        $this->assertSame(12, User::where('email', 'like', '%@levictas.test')->count());
        $this->assertTrue($this->account('hr.head@levictas.test')->hasRole('hr_head'));
        $this->assertTrue($this->account('finance.head@levictas.test')->hasRole('finance_head'));

        $this->seed(PayrollTestAccountsSeeder::class);   // second run is refused, nothing duplicated
        $this->assertSame(1, Spa::where('name', PayrollTestAccountsSeeder::SPA_NAME)->count());
    }

    public function test_every_account_can_log_in(): void
    {
        foreach (User::where('email', 'like', '%@levictas.test')->where('email', 'not like', 'customer%')->pluck('email') as $email) {
            $this->post('/login', ['email' => $email, 'password' => PayrollTestAccountsSeeder::PASSWORD])
                ->assertRedirect();
            $this->assertAuthenticated();
            $this->post('/logout');
        }
    }

    public function test_pages_in_the_guide_open_for_their_roles(): void
    {
        $pages = [
            'owner@levictas.test'        => ['payroll.index', 'payroll.setup.index', 'payroll.staff.index', 'attendance.index'],
            'hr.head@levictas.test'      => ['payroll.index', 'payroll.setup.index', 'payroll.staff.index', 'attendance.index'],
            'finance.head@levictas.test' => ['payroll.index', 'payroll.staff.index'],
            'hr.a@levictas.test'         => ['payroll.index', 'payroll.setup.index', 'payroll.staff.index', 'attendance.index'],
            'finance.a@levictas.test'    => ['payroll.index'],
            'therapist.a1@levictas.test' => ['attendance.index', 'my-payslips.index'],
        ];
        foreach ($pages as $email => $routes) {
            $user = $this->account($email);
            foreach ($routes as $name) {
                $this->actingAs($user)->get(route($name))->assertOk();
            }
        }
        $this->actingAs($this->account('therapist.a1@levictas.test'))->get(route('payroll.index'))->assertForbidden();
    }

    public function test_hr_head_can_generate_last_months_first_cutoff_with_the_expected_warnings(): void
    {
        $this->actingAs($this->account('hr.head@levictas.test'))
            ->post(route('payroll.runs.store'), ['run_type' => 'regular', 'year' => 2026, 'month' => 9, 'cutoff_no' => 1])
            ->assertRedirect();

        $run = PayrollRun::firstOrFail();
        $messages = collect($run->review['warnings'] ?? [])->pluck('message')->implode("\n");

        $this->assertGreaterThan(0, $run->payslips()->count());
        $this->assertStringContainsString('Manager B', $messages);            // no pay profile
        $this->assertStringContainsString('Test Branch B', $messages);        // branch without a minimum wage
    }
}
