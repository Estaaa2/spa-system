<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\User;
use App\Services\Finance\BookingRevenue;
use Tests\Fixtures\HomeVisitTestSeeder;
use Tests\Fixtures\IdentityVerificationTestSeeder;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Booking lifecycle follow-up (Q1-A): Reports and Decision Support use the same
 * branch scope as Revenue — User::currentBranchId(), which checks the session
 * branch belongs to the user's spa and otherwise falls back to the main/home
 * branch. They used to read session('current_branch_id') raw.
 *
 * Regression guards, not bug reproductions: these also pass on the old code,
 * because the branch.permission middleware already calls currentBranchId()
 * (via hasBranchPermission) and writes a validated branch into the session
 * before the controller runs. The change removes the dependence on that side
 * effect; these tests keep both pages on the shared scope if the middleware
 * ever changes.
 */
class InsightsBranchScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private int $spaId;
    private int $branchA;
    private int $branchB;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-30 15:00:00');
        Storage::fake('local');
        Mail::fake();
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);
        $this->seed(HomeVisitTestSeeder::class);

        $this->owner = User::where('email', 'owner@levictas.test')->firstOrFail();
        $this->spaId = (int) $this->owner->spa_id;
        $this->branchA = (int) Branch::withoutGlobalScopes()->where('spa_id', $this->spaId)->where('name', 'Test Branch A')->value('id');
        $this->branchB = (int) Branch::withoutGlobalScopes()->where('spa_id', $this->spaId)->where('name', 'Test Branch B')->value('id');

        // One checked-out booking per branch, different amounts, so the scope shows.
        $template = Booking::where('customer_email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();
        foreach ([[$this->branchA, 1000], [$this->branchB, 777]] as [$branchId, $amount]) {
            $b = $template->replicate();
            $b->forceFill([
                'branch_id' => $branchId, 'status' => 'completed', 'total_amount' => $amount,
                'amount_paid' => $amount, 'balance_amount' => 0, 'payment_status' => 'paid',
                'appointment_date' => '2026-10-20', 'start_time' => '10:00:00', 'end_time' => '11:00:00',
                'therapist_id' => null, 'arrival_code' => null,
            ])->saveQuietly();
        }

        // No SQLite stubs: Decision Support groups hours in PHP since the DSS revamp.
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function figures(int $sessionBranch): array
    {
        $this->actingAs($this->owner)->withSession(['current_branch_id' => $sessionBranch]);
        $period = ['from' => '2026-10-01', 'to' => '2026-10-30'];

        return [
            'reports' => (float) $this->get(route('reports.index', $period))->assertOk()->viewData('grossRevenue'),
            'dss'     => (int) $this->get(route('decision-support.index'))->assertOk()->viewData('branchId'),
        ];
    }

    public function test_a_session_branch_from_another_spa_falls_back_to_the_main_branch(): void
    {
        $expected = BookingRevenue::serviceRevenue($this->spaId, $this->branchA, '2026-10-01', '2026-10-30');
        $spaWide  = BookingRevenue::serviceRevenue($this->spaId, null, '2026-10-01', '2026-10-30');
        $this->assertNotEquals($expected, $spaWide);

        $f = $this->figures(999999);   // not a branch of this spa

        $this->assertEquals($expected, $f['reports']);
        $this->assertSame($this->branchA, $f['dss']);
    }

    public function test_a_head_with_no_branch_picked_sees_their_home_branch_not_the_whole_spa(): void
    {
        // HR Head / Finance Head can switch branches, so no middleware pins their
        // session branch. Before: session empty → Reports/DSS showed the whole spa.
        $head = User::where('email', 'finance.head@levictas.test')->firstOrFail();

        if (! $head->hasBranchPermission('view reports') || ! $head->hasBranchPermission('view decision support')) {
            $this->markTestSkipped('The seeded Finance Head cannot open Reports / Decision Support.');
        }

        $expected = BookingRevenue::serviceRevenue($this->spaId, (int) $head->branch_id, '2026-10-01', '2026-10-30');
        $spaWide  = BookingRevenue::serviceRevenue($this->spaId, null, '2026-10-01', '2026-10-30');
        $this->assertNotEquals($expected, $spaWide);

        $this->actingAs($head);   // fresh session: no current_branch_id
        $period = ['from' => '2026-10-01', 'to' => '2026-10-30'];

        $this->assertEquals($expected, (float) $this->get(route('reports.index', $period))->assertOk()->viewData('grossRevenue'));
        $this->assertSame((int) $head->branch_id, (int) $this->get(route('decision-support.index'))->assertOk()->viewData('branchId'));
    }

    public function test_switching_to_another_branch_scopes_both_pages_to_it(): void
    {
        $expected = BookingRevenue::serviceRevenue($this->spaId, $this->branchB, '2026-10-01', '2026-10-30');

        $f = $this->figures($this->branchB);

        $this->assertEquals($expected, $f['reports']);
        $this->assertSame($this->branchB, $f['dss']);
    }
}
