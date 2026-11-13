<?php

namespace Tests\Feature\DecisionSupport;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\DssRecommendation;
use App\Models\LeaveRequest;
use App\Models\OperatingHours;
use App\Models\Promo;
use App\Models\Spa;
use App\Models\Treatment;
use App\Models\User;
use App\Services\DecisionSupport\CapacityCalculator;
use App\Services\DecisionSupport\DemandForecaster;
use App\Services\DecisionSupport\RecommendationEngine;
use App\Services\Navigation\NavBadges;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Tests\TestCase;

/**
 * DSS revamp: capacity, forecast, rules R1–R6, stored recommendations with
 * accept / dismiss history, the access gate, the badge, the dashboard card.
 *
 * World (PayrollTestAccountsSeeder): Test Branch A has two therapists with no
 * weekly pattern, so their pay-profile rest days apply: Tess (A1) rests on Sunday,
 * Theo (A2) on Saturday and Sunday. Branch hours here: 09:00–18:00 every day.
 * Today is Monday 2026-11-16.
 */
class DssRevampTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Spa $spa;
    private Branch $branchA;
    private Branch $branchB;
    private User $tess;
    private User $theo;
    private Treatment $treatment;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-16 08:00:00');
        Storage::fake('local');
        Mail::fake();
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);

        $this->owner = User::where('email', 'owner@levictas.test')->firstOrFail();
        $this->spa = Spa::findOrFail($this->owner->spa_id);
        $this->branchA = Branch::withoutGlobalScopes()->where('spa_id', $this->spa->id)->where('name', 'Test Branch A')->firstOrFail();
        $this->branchB = Branch::withoutGlobalScopes()->where('spa_id', $this->spa->id)->where('name', 'Test Branch B')->firstOrFail();
        $this->tess = User::where('first_name', 'Tess')->firstOrFail();
        $this->theo = User::where('first_name', 'Theo')->firstOrFail();

        foreach ([$this->branchA, $this->branchB] as $branch) {
            OperatingHours::where('branch_id', $branch->id)->delete();
            foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
                OperatingHours::create(['branch_id' => $branch->id, 'day_of_week' => $day,
                    'opening_time' => '09:00:00', 'closing_time' => '18:00:00', 'is_closed' => false]);
            }
        }

        // Start every test from no bookings: each test builds the history it needs.
        Booking::query()->delete();

        $this->treatment = Treatment::withoutGlobalScopes()->where('branch_id', $this->branchA->id)->firstOrFail();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function book(string $date, string $start, string $end, ?User $therapist = null, string $status = 'completed', ?string $treatment = null, float $paid = 1000, ?Branch $branch = null): void
    {
        $b = new Booking();
        $b->forceFill([
            'spa_id' => $this->spa->id, 'branch_id' => ($branch ?? $this->branchA)->id,
            'therapist_id' => $therapist?->id, 'treatment' => $treatment ?? 'treatment_'.$this->treatment->id,
            'status' => $status, 'payment_status' => 'paid', 'amount_paid' => $paid, 'total_amount' => $paid,
            'balance_amount' => 0, 'service_type' => 'in_branch', 'booking_source' => 'walk_in',
            'customer_name' => 'DSS test', 'customer_phone' => '09170000000',
            'appointment_date' => $date, 'start_time' => $start, 'end_time' => $end,
        ])->saveQuietly();
    }

    /** Fill every hour both therapists work, for the given number of past weeks. */
    private function fillPastWeeks(int $weeks): void
    {
        $today = CarbonImmutable::today();
        for ($d = $today->subDays($weeks * 7); $d->lt($today); $d = $d->addDay()) {
            foreach ([$this->tess, $this->theo] as $t) {
                if ($d->isSunday() || ($t->is($this->theo) && $d->isSaturday())) {
                    continue;
                }
                for ($h = 9; $h < 18; $h++) {
                    $this->book($d->toDateString(), sprintf('%02d:00:00', $h), sprintf('%02d:00:00', $h + 1), $t);
                }
            }
        }
    }

    private function engine(): RecommendationEngine
    {
        return app(RecommendationEngine::class);
    }

    private function keys(array $candidates): array
    {
        return array_values(array_unique(array_column($candidates, 'rule_key')));
    }

    // ── Capacity ────────────────────────────────────────────────────────────

    public function test_capacity_follows_rest_days_leave_and_branch_hours(): void
    {
        LeaveRequest::create([
            'user_id' => $this->tess->id, 'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id,
            'leave_type' => 'vacation', 'start_date' => '2026-11-18', 'end_date' => '2026-11-18',
            'reason' => 'test', 'status' => 'approved',
        ]);
        $this->book('2026-11-17', '10:30:00', '12:00:00', $this->tess, 'reserved');
        $this->book('2026-11-17', '13:00:00', '14:00:00', $this->tess, 'cancelled');   // never counts

        $rows = app(CapacityCalculator::class)->range($this->spa->id, $this->branchA->id, '2026-11-16', '2026-11-22');

        $this->assertSame(2, $rows['2026-11-16']['therapists']);          // Monday: both
        $this->assertSame(1080, $rows['2026-11-16']['scheduled']);        // 2 × 9 h
        $this->assertSame(1, $rows['2026-11-18']['therapists']);          // Tess on leave
        $this->assertSame(1, $rows['2026-11-21']['therapists']);          // Saturday: Theo rests
        $this->assertSame(0, $rows['2026-11-22']['therapists']);          // Sunday: both rest
        $this->assertSame(90, $rows['2026-11-17']['booked']);             // 10:30–12:00, cancelled ignored
        $this->assertSame(30, $rows['2026-11-17']['hours'][10]['booked']);
        $this->assertSame(60, $rows['2026-11-17']['hours'][11]['booked']);
    }

    // ── Not enough data ─────────────────────────────────────────────────────

    public function test_a_new_branch_gets_no_recommendations_and_says_why(): void
    {
        $this->fillPastWeeks(2);   // < 4 weeks

        $forecast = app(DemandForecaster::class)->forecast($this->spa->id, $this->branchA->id);
        $this->assertFalse($forecast['enough_data']);
        $this->assertSame([], $this->engine()->candidatesForSpa($this->spa, CarbonImmutable::today()));

        $this->actingAs($this->owner)->get(route('decision-support.index'))
            ->assertOk()->assertSee('Not enough data yet')->assertSee('No decisions needed right now');
    }

    // ── R1 staffing / R2 hire / R6 expansion ────────────────────────────────

    public function test_busy_weeks_trigger_staffing_and_hiring(): void
    {
        $this->fillPastWeeks(5);

        $candidates = $this->engine()->candidatesForSpa($this->spa, CarbonImmutable::today());
        $keys = $this->keys($candidates);

        $this->assertContains('r1_staffing', $keys);
        $this->assertContains('r2_hire', $keys);
        $this->assertNotContains('r3_overstaffed', $keys);

        $hire = collect($candidates)->firstWhere('rule_key', 'r2_hire');
        $this->assertSame(4, $hire['details']['numbers']['busy_weeks']);
        $this->assertSame([100.0, 100.0, 100.0, 100.0], $hire['details']['numbers']['weekly_use']);
        $this->assertSame('job-openings.index', $hire['action']['route']);

        // Tomorrow (Tuesday): every Tuesday was 100% booked → R1 for that date.
        $this->assertTrue(collect($candidates)->contains(fn ($c) => $c['fingerprint'] === 'r1|'.$this->branchA->id.'|2026-11-17'));
    }

    public function test_a_quiet_branch_gets_no_hiring_advice_but_light_days_show(): void
    {
        // 5 weeks with one booking a day: far below 85%.
        $today = CarbonImmutable::today();
        for ($d = $today->subDays(35); $d->lt($today); $d = $d->addDay()) {
            if (! $d->isSunday()) {
                $this->book($d->toDateString(), '10:00:00', '11:00:00', $this->tess);
            }
        }

        $keys = $this->keys($this->engine()->candidatesForSpa($this->spa, $today));
        $this->assertNotContains('r2_hire', $keys);
        $this->assertNotContains('r1_staffing', $keys);
        $this->assertContains('r3_overstaffed', $keys);
    }

    public function test_r6_needs_revenue_above_expected_for_three_months(): void
    {
        $busy = ['busy_weeks' => 4, 'use' => [90, 90, 90, 90], 'full_hours' => 40, 'top_full' => []];
        $today = CarbonImmutable::today();
        $m1 = $today->startOfMonth()->subMonth();   // October
        foreach ([5, 4, 3] as $k) {
            $this->book($m1->subMonths($k)->addDays(3)->toDateString(), '10:00:00', '11:00:00', $this->tess, 'completed', null, 10000);
        }
        foreach ([2, 1, 0] as $k) {
            $this->book($m1->subMonths($k)->addDays(3)->toDateString(), '10:00:00', '11:00:00', $this->tess, 'completed', null, 12000);
        }

        $r6 = $this->engine()->r6Expansion($this->spa, $this->branchA, $busy, $today);
        $this->assertNotNull($r6);
        $this->assertSame('spa', $r6['scope']);
        $this->assertEquals(10000, $r6['details']['numbers']['baseline']);

        // One recent month only 5% above → silent.
        Booking::whereDate('appointment_date', $m1->addDays(3)->toDateString())->update(['amount_paid' => 10500]);
        $this->assertNull($this->engine()->r6Expansion($this->spa, $this->branchA, $busy, $today));

        // Fewer than 6 months of revenue → silent.
        Booking::query()->delete();
        $this->book($m1->addDays(3)->toDateString(), '10:00:00', '11:00:00', $this->tess, 'completed', null, 50000);
        $this->assertNull($this->engine()->r6Expansion($this->spa, $this->branchA, $busy, $today));
    }

    // ── R4 promo ────────────────────────────────────────────────────────────

    public function test_a_treatment_that_dropped_40_percent_gets_a_promo_unless_one_runs(): void
    {
        $today = CarbonImmutable::today();
        $this->book($today->subDays(70)->toDateString(), '10:00:00', '11:00:00', $this->tess);   // 8+ weeks of history
        foreach ([30, 33, 36, 40, 45] as $ago) {
            $this->book($today->subDays($ago)->toDateString(), '10:00:00', '11:00:00', $this->tess);
        }
        $this->book($today->subDays(5)->toDateString(), '10:00:00', '11:00:00', $this->tess);

        $r4 = $this->engine()->r4Promo($this->branchA, $today);
        $this->assertCount(1, $r4);
        $this->assertSame(80.0, $r4[0]['details']['numbers']['drop_pct']);   // 5 → 1
        $this->assertSame($this->treatment->id, $r4[0]['action']['params']['treatment']);

        $promo = Promo::withoutGlobalScopes()->create([
            'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id, 'name' => 'Running',
            'discount_type' => 'percent', 'discount_value' => 10, 'is_active' => true,
            'start_date' => $today->subDay()->toDateString(), 'end_date' => $today->addDays(5)->toDateString(),
        ]);
        $promo->treatments()->attach($this->treatment->id);
        $this->assertSame([], $this->engine()->r4Promo($this->branchA, $today));
    }

    public function test_r4_stays_silent_with_too_few_earlier_bookings(): void
    {
        $today = CarbonImmutable::today();
        foreach ([30, 40] as $ago) {   // only 2 before
            $this->book($today->subDays($ago)->toDateString(), '10:00:00', '11:00:00', $this->tess);
        }
        $this->assertSame([], $this->engine()->r4Promo($this->branchA, $today));
    }

    // ── Store, accept, dismiss ──────────────────────────────────────────────

    public function test_sync_stores_and_dismiss_hides_until_the_situation_changes(): void
    {
        $this->fillPastWeeks(5);
        $engine = $this->engine();
        $this->assertGreaterThan(0, $engine->sync($this->spa));

        $hire = DssRecommendation::where('rule_key', 'r2_hire')->firstOrFail();
        $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branchA->id])
            ->post(route('decision-support.dismiss', $hire), ['reason' => 'not_now', 'note' => 'After the holidays'])
            ->assertRedirect(route('decision-support.index'));

        $hire->refresh();
        $this->assertSame('dismissed', $hire->status);
        $this->assertSame('not_now', $hire->dismiss_reason);
        $this->assertSame($this->owner->id, $hire->decided_by);

        // Same week, same situation: stays dismissed.
        $engine->sync($this->spa);
        $this->assertSame('dismissed', $hire->fresh()->status);

        // Situation goes away: open items expire.
        Booking::query()->delete();
        $engine->sync($this->spa);
        $this->assertSame(0, DssRecommendation::open()->count());
        $this->assertGreaterThan(0, DssRecommendation::where('status', 'expired')->count());

        $this->get(route('decision-support.index'))->assertOk()
            ->assertSee('Decision history')->assertSee('Consider hiring another therapist')->assertSee('After the holidays');
    }

    public function test_accept_saves_history_and_opens_the_prefilled_action(): void
    {
        $this->fillPastWeeks(5);
        $this->engine()->sync($this->spa);
        $hire = DssRecommendation::where('rule_key', 'r2_hire')->firstOrFail();

        $response = $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branchA->id])
            ->post(route('decision-support.accept', $hire));

        $response->assertRedirect(route('job-openings.index', ['dss_prefill' => 1, 'role' => 'therapist', 'title' => 'Massage Therapist']));
        $this->assertSame('accepted', $hire->fresh()->status);

        // A decided item cannot be decided again.
        $this->post(route('decision-support.dismiss', $hire))->assertSessionHas('error');
        $this->assertSame('accepted', $hire->fresh()->status);

        // The job openings page reads the prefill.
        $this->get(route('job-openings.index', ['dss_prefill' => 1, 'role' => 'therapist', 'title' => 'Massage Therapist']))
            ->assertOk()->assertSee("q.get('dss_prefill')", false);
    }

    public function test_decisions_cannot_reach_another_spa_branch_or_spa_level_item(): void
    {
        $mk = fn (array $o) => DssRecommendation::create($o + [
            'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id, 'scope' => 'branch',
            'rule_key' => 'r2_hire', 'priority' => 'high', 'title' => 'T', 'message' => 'M', 'status' => 'open',
            'fingerprint' => uniqid('fp', true),
        ]);

        $otherSpa = Spa::factory()->create();
        $foreign = $mk(['spa_id' => $otherSpa->id]);
        $branchB = $mk(['branch_id' => $this->branchB->id]);
        $spaLevel = $mk(['scope' => 'spa', 'rule_key' => 'r6_expansion']);

        $manager = User::where('email', 'manager.a@levictas.test')->firstOrFail();
        // Pretend the nightly job already ran, so opening the page does not re-sync
        // (which would expire these hand-made rows).
        Cache::forever(\App\Http\Controllers\Insights\DecisionSupportController::syncKey($this->spa->id), now()->toIso8601String());

        $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branchA->id]);
        $this->post(route('decision-support.accept', $foreign))->assertNotFound();
        $this->post(route('decision-support.accept', $branchB))->assertNotFound();

        $this->actingAs($manager)->withSession(['current_branch_id' => $this->branchA->id]);
        if (! $manager->hasBranchPermission('view decision support')) {
            $this->markTestSkipped('The seeded manager cannot open Decision Support.');
        }
        $this->post(route('decision-support.dismiss', $spaLevel))->assertNotFound();
        $this->get(route('decision-support.index'))->assertOk()->assertDontSee('Whole spa · owner only');

        foreach ([$foreign, $branchB, $spaLevel] as $r) {
            $this->assertSame('open', $r->fresh()->status);
        }
    }

    // ── Gate, badge, dashboard ──────────────────────────────────────────────

    public function test_badge_card_and_popup_follow_the_permission_gate(): void
    {
        $this->fillPastWeeks(5);
        $this->engine()->sync($this->spa);
        $open = DssRecommendation::open()->where('branch_id', $this->branchA->id)->where('scope', 'branch')->count();
        $this->assertGreaterThan(0, $open);

        $badges = NavBadges::forUser($this->owner, $this->branchA->id);
        $this->assertSame($open, $badges['decision-support']['count']);
        $this->assertTrue($badges['decision-support']['urgent']);

        $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branchA->id]);
        $first = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Needs your decision', $first);
        $this->assertStringContainsString('dssPopupOpen: true', $first);
        // Once a day: the second visit has the card but no pop-up.
        $second = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Needs your decision', $second);
        $this->assertStringNotContainsString('dssPopupTitle', $second);

        // Without the permission: no page, no badge, no card.
        $manager = User::where('email', 'manager.a@levictas.test')->firstOrFail();
        Role::findByName('manager')->revokePermissionTo('view decision support');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $manager = $manager->fresh();
        NavBadges::bump($this->spa->id);

        $this->assertArrayNotHasKey('decision-support', NavBadges::forUser($manager, $this->branchA->id));
        $this->actingAs($manager)->withSession(['current_branch_id' => $this->branchA->id]);
        $this->get(route('decision-support.index'))->assertForbidden();
        $this->assertStringNotContainsString('Needs your decision', $this->get(route('dashboard'))->getContent());
    }

    public function test_refresh_and_the_daily_command_store_recommendations(): void
    {
        $this->fillPastWeeks(5);

        $this->artisan('dss:generate')->assertSuccessful();
        $this->assertGreaterThan(0, DssRecommendation::open()->count());

        $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branchA->id])
            ->post(route('decision-support.refresh'))
            ->assertRedirect(route('decision-support.index'))->assertSessionHas('success');
    }

    public function test_reports_survives_a_malformed_date(): void
    {
        $this->actingAs($this->owner)->get(route('reports.index', ['from' => 'abc', 'to' => '2026-13-45']))->assertOk();
        $this->get(route('reports.index', ['from' => '2026-11-10', 'to' => '2026-11-01']))->assertOk();
    }
}
