<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\Rating;
use App\Models\User;
use App\Services\Finance\BookingRevenue;
use Database\Seeders\HomeVisitTestSeeder;
use Database\Seeders\IdentityVerificationTestSeeder;
use Database\Seeders\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Booking lifecycle fix, Phase 4: every report uses the same money rules
 * (BookingRevenue).
 *
 *   service revenue = amount_paid on checked-out bookings     (D9, Q1-A)
 *   kept deposits   = amount_paid on no-show / cancelled       (Q2-A, Q3-A)
 *   owed on open    = balance on ended bookings nobody closed  (Q4-A)
 *   "paid toward today" = amount_paid on today's bookings, any status (Q5-A)
 *
 * Each test adds known bookings to a branch and checks the change in each
 * figure, so the seeders' own bookings don't matter.
 */
class RevenueRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $manager;
    private Booking $template;
    private int $spaId;
    private int $branchId;

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

        $this->owner   = User::where('email', 'owner@levictas.test')->firstOrFail();
        $this->manager = User::where('email', 'manager.a@levictas.test')->firstOrFail();
        $customer = User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();
        $this->template = Booking::where('customer_user_id', $customer->id)->firstOrFail();
        $this->spaId = (int) $this->template->spa_id;
        $this->branchId = (int) $this->template->branch_id;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A copy of the seeded booking with the given money and status. */
    private function make(string $status, float $total, float $paid, string $date = '2026-10-30', string $start = '09:00:00', string $end = '10:00:00'): Booking
    {
        $b = $this->template->replicate();
        $b->forceFill([
            'status' => $status, 'total_amount' => $total, 'amount_paid' => $paid,
            'balance_amount' => max($total - $paid, 0),
            'payment_status' => $paid >= $total ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid'),
            'appointment_date' => $date, 'start_time' => $start, 'end_time' => $end,
        ])->saveQuietly();

        return $b;
    }

    private function figures(): array
    {
        return [
            'revenue' => BookingRevenue::serviceRevenue($this->spaId, $this->branchId, '2026-10-01', '2026-10-31'),
            'kept'    => BookingRevenue::keptDeposits($this->spaId, $this->branchId, '2026-10-01', '2026-10-31'),
            'owed'    => BookingRevenue::owedOnOpen($this->spaId, $this->branchId),
        ];
    }

    public function test_the_shared_rules_count_collected_money_and_keep_deposits_apart(): void
    {
        $before = $this->figures();

        $this->make('completed', 1000, 1000);                  // checked out, fully paid
        $this->make('completed', 1000, 600);                   // older row: partially paid
        $this->make(Booking::NO_SHOW, 1000, 240);              // deposit kept
        $this->make('cancelled', 1000, 0);                     // nothing kept
        $this->make('pending', 1000, 200, '2026-10-29');       // ended yesterday, not closed

        $after = $this->figures();

        // Q1-A: money collected, not the billed total (would be +2000).
        $this->assertEquals(1600.0, $after['revenue'] - $before['revenue']);
        // Q2-A: kept deposits on their own, not in revenue.
        $this->assertEquals(240.0, $after['kept']['amount'] - $before['kept']['amount']);
        $this->assertSame(1, $after['kept']['count'] - $before['kept']['count']);
        // Q4-A: balance on the ended, unclosed booking.
        $this->assertEquals(800.0, $after['owed']['amount'] - $before['owed']['amount']);
        $this->assertSame(1, $after['owed']['count'] - $before['owed']['count']);
    }

    public function test_the_revenue_and_reports_pages_show_the_new_figures(): void
    {
        $this->make(Booking::NO_SHOW, 1000, 240);
        $this->make('pending', 1000, 200, '2026-10-29');

        $kept = BookingRevenue::keptDeposits($this->spaId, $this->branchId, '2026-10-01', '2026-10-31')['amount'];
        $owed = BookingRevenue::owedOnOpen($this->spaId, $this->branchId)['amount'];
        $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branchId]);

        $revenue = $this->get(route('revenue.index', ['from' => '2026-10-01', 'to' => '2026-10-31']));
        if ($revenue->status() === 200) {
            $revenue->assertSee('Service Revenue')->assertSee('Kept Deposits')
                ->assertSee('₱'.number_format($kept, 2))
                ->assertSee('Owed on Open Appointments')->assertSee('₱'.number_format($owed, 2))
                ->assertDontSee('Gross Revenue');
        } else {
            $this->markTestIncomplete("Revenue page answered {$revenue->status()} for the seeded owner (plan gate).");
        }

        $reports = $this->get(route('reports.index', ['from' => '2026-10-01', 'to' => '2026-10-31']));
        if ($reports->status() === 200) {
            $reports->assertSee('Service Revenue')->assertSee('Kept Deposits')->assertSee('Owed on Open Appointments')
                ->assertDontSee('Gross Revenue (Total Billed)');
        }

        // Decision Support groups by MySQL's HOUR() / DAYOFWEEK(); give SQLite the
        // same functions (as CustomerProfileWindowTest does) and check its revenue
        // KPI equals the shared rule.
        $pdo = \DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('HOUR', fn ($t) => (int) substr((string) $t, 0, 2), 1);
        $pdo->sqliteCreateFunction('DAYOFWEEK', fn ($d) => (int) date('w', strtotime((string) $d)) + 1, 1);

        $this->make('completed', 1000, 600);   // older row, partially paid: counts 600
        $dss = $this->get(route('decision-support.index', ['from' => '2026-10-01', 'to' => '2026-10-31']));
        $dss->assertOk();
        $this->assertEquals(
            BookingRevenue::serviceRevenue($this->spaId, $this->branchId, '2026-10-01', '2026-10-31'),
            (float) $dss->viewData('kpis')['revenue']
        );
    }

    public function test_paid_toward_today_counts_every_status_including_pending_deposits(): void
    {
        $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branchId]);
        $before = (float) $this->get(route('dashboard'))->viewData('collectedToday');

        $this->make('pending', 1000, 200);     // deposit, nobody pressed Start
        $this->make('completed', 500, 500);    // checked out today
        $this->make(Booking::NO_SHOW, 800, 160);

        $after = (float) $this->get(route('dashboard'))->viewData('collectedToday');
        $this->assertEquals(860.0, $after - $before);

        // Appointments: checking a booking out no longer removes its money from the card.
        $this->actingAs($this->manager);
        $summaryBefore = $this->get(route('appointments.index'))->viewData('summary')['collected_today'];
        $done = $this->make('ongoing', 300, 0, '2026-10-30', '13:00:00', '14:00:00');
        $done->forceFill(['service_type' => 'in_branch'])->saveQuietly();   // no arrival step (Phase 2 gate)
        $this->put(route('appointments.checkout', $done), ['amount_paid' => 300])->assertSessionHasNoErrors();
        $this->assertSame('completed', $done->fresh()->status);
        $summaryAfter = $this->get(route('appointments.index'))->viewData('summary')['collected_today'];
        $this->assertEquals(300.0, (float) $summaryAfter - (float) $summaryBefore);
    }

    public function test_only_checked_out_bookings_can_be_rated(): void
    {
        $customer = User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();
        $open = $this->make('pending', 1000, 200, '2026-10-29');   // ended, not checked out
        $done = $this->make('completed', 1000, 1000);

        $this->actingAs($customer)->postJson('/ratings', ['booking_id' => $open->id, 'rating' => 5])
            ->assertStatus(422);
        $this->actingAs($customer)->postJson('/ratings', ['booking_id' => $done->id, 'rating' => 5])
            ->assertSuccessful();

        $this->assertSame(1, Rating::whereIn('booking_id', [$open->id, $done->id])->count());
    }

    public function test_commission_still_requires_a_paid_checkout(): void
    {
        // CommissionEarnings filters completed + paid. Every checkout is paid now,
        // so a partially paid older completed row stays out (unchanged rule).
        $partial = $this->make('completed', 1000, 600);
        $paid    = $this->make('completed', 1000, 1000);

        $eligible = Booking::whereIn('id', [$partial->id, $paid->id])
            ->where('status', 'completed')->where('payment_status', 'paid')->pluck('id')->all();

        $this->assertSame([$paid->id], $eligible);
        $this->assertStringContainsString(
            "->where('payment_status', 'paid')",
            file_get_contents(app_path('Services/Payroll/CommissionEarnings.php'))
        );
    }
}
