<?php

namespace Tests\Feature\MobileApp;

use App\Models\Branch;
use App\Models\Spa;
use App\Models\User;
use Database\Factories\BranchFactory;
use Database\Factories\SpaFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\HomeVisitTestSeeder;
use Tests\Fixtures\IdentityVerificationTestSeeder;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Tests\TestCase;

/**
 * Owner side of the mobile app (Nov 2026): view only, branch from X-Branch-Id,
 * the same data and checks as the web pages.
 */
class OwnerMobileApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Spa $spa;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);
        $this->seed(HomeVisitTestSeeder::class);

        $this->owner = User::where('email', 'owner@levictas.test')->firstOrFail();
        $this->spa = Spa::findOrFail($this->owner->spa_id);
    }

    public function test_owners_can_log_into_the_app_and_get_the_owner_side(): void
    {
        $res = $this->postJson('/api/login', [
            'email' => 'owner@levictas.test', 'password' => PayrollTestAccountsSeeder::PASSWORD, 'platform' => 'mobile',
        ])->assertOk();

        $this->assertSame('owner', $res->json('user.app_role'));

        // Therapists still get the therapist side.
        $this->postJson('/api/login', [
            'email' => 'therapist.a1@levictas.test', 'password' => PayrollTestAccountsSeeder::PASSWORD, 'platform' => 'mobile',
        ])->assertOk()->assertJsonPath('user.app_role', 'therapist');
    }

    public function test_the_branch_picker_and_the_header_choose_the_branch(): void
    {
        Sanctum::actingAs($this->owner);

        $data = $this->getJson('/api/owner/branches')->assertOk()
            ->assertJsonStructure(['access' => ['appointments', 'schedule', 'services', 'inventory', 'logs', 'reports', 'decision_support']])
            ->json();
        $this->assertGreaterThanOrEqual(2, count($data['branches']));
        $main = collect($data['branches'])->firstWhere('is_main', true);
        $this->assertSame($main['id'], $data['current_branch_id']);

        $other = collect($data['branches'])->firstWhere('is_main', false);
        $this->getJson('/api/owner/branches', ['X-Branch-Id' => $other['id']])
            ->assertJsonPath('current_branch_id', $other['id']);

        // Another spa's branch is not found.
        $foreign = BranchFactory::new()->create(['spa_id' => SpaFactory::new()->create()->id]);
        $this->getJson('/api/owner/dashboard', ['X-Branch-Id' => $foreign->id])->assertNotFound();
    }

    public function test_every_screen_answers_for_the_chosen_branch(): void
    {
        Sanctum::actingAs($this->owner);
        $branchId = Branch::where('spa_id', $this->spa->id)->orderByDesc('is_main')->value('id');
        $h = ['X-Branch-Id' => $branchId];

        $this->getJson('/api/owner/dashboard', $h)->assertOk()->assertJsonStructure(['server_time']);
        $this->getJson('/api/owner/appointments', $h)->assertOk()
            ->assertJsonStructure(['summary', 'today_appointments', 'upcoming_appointments']);
        $this->getJson('/api/owner/appointments/history', $h)->assertOk()
            ->assertJsonStructure(['history', 'needs_checkout', 'needs_checkout_total', 'page', 'last_page']);
        $this->getJson('/api/owner/schedule', $h)->assertOk()
            ->assertJsonStructure(['week_start', 'week_label', 'prev_week', 'next_week', 'days' => [['date', 'closed', 'bookings']]])
            ->assertJsonCount(7, 'days');
        $this->getJson('/api/owner/services', $h)->assertOk()->assertJsonStructure(['treatments', 'packages']);

        // Inventory follows the plan, like the web.
        $expected = $this->spa->hasFeature('inventory_basic') ? 200 : 403;
        $this->getJson('/api/owner/inventory/products', $h)->assertStatus($expected);
        $this->getJson('/api/owner/inventory/logs', $h)->assertStatus($expected);
        if ($expected === 200) {
            $this->getJson('/api/owner/inventory/products', $h)->assertJsonStructure(['products', 'stock_shortfalls']);
            $this->getJson('/api/owner/inventory/logs', $h)->assertJsonStructure(['logs', 'filters' => ['products', 'movement_types']]);
        }
    }

    public function test_reports_and_decision_support_match_the_web_pages(): void
    {
        Sanctum::actingAs($this->owner);
        $branchId = Branch::where('spa_id', $this->spa->id)->orderByDesc('is_main')->value('id');
        $h = ['X-Branch-Id' => $branchId];

        if (! $this->spa->hasFeature('insights')) {
            $this->getJson('/api/owner/reports', $h)->assertForbidden();
            $this->getJson('/api/owner/decision-support', $h)->assertForbidden();

            return;
        }

        $this->getJson('/api/owner/reports?from=2026-11-01&to=2026-11-14', $h)->assertOk()
            ->assertJsonPath('filters.from', '2026-11-01')
            ->assertJsonStructure([
                'total_bookings', 'status_summary' => ['completed', 'no_show'], 'gross_revenue',
                'kept_deposits' => ['amount', 'count'], 'owed_on_open' => ['amount', 'count'],
                'treat_revenue', 'pkg_revenue', 'sources' => ['online', 'walk_in', 'staff'],
                'services', 'assigned_count', 'unassigned_count', 'therapists',
                'bookings_per_day', 'revenue_per_day',
            ]);
        // A bad date falls back to the default range, like the web.
        $this->getJson('/api/owner/reports?from=nope', $h)->assertOk()
            ->assertJsonPath('filters.from', now()->startOfMonth()->format('Y-m-d'));

        $this->getJson('/api/owner/decision-support', $h)->assertOk()
            ->assertJsonStructure([
                'last_checked', 'thresholds' => ['busy', 'light', 'weeks', 'min_weeks'],
                'forecast' => ['enough_data', 'weeks', 'days' => [['date', 'label', 'open', 'therapists', 'booked_count', 'expected_count', 'use', 'short_hours']]],
                'recommendations', 'history',
            ])
            ->assertJsonCount(7, 'forecast.days');

        // The web pages still render from the shared methods.
        $this->actingAs($this->owner)->withSession(['current_branch_id' => $branchId])
            ->get('/reports')->assertOk()->assertSee('Booking Summary');
        $this->actingAs($this->owner)->withSession(['current_branch_id' => $branchId])
            ->get('/decision-support')->assertOk()->assertSee('Decision history');
    }

    public function test_a_locked_branch_only_answers_the_branch_list(): void
    {
        Sanctum::actingAs($this->owner);
        $locked = BranchFactory::new()->create(['spa_id' => $this->spa->id, 'is_main' => false, 'verification_status' => 'pending']);

        $this->getJson('/api/owner/dashboard', ['X-Branch-Id' => $locked->id])->assertStatus(423);
        $this->getJson('/api/owner/branches', ['X-Branch-Id' => $locked->id])
            ->assertOk()
            ->assertJsonFragment(['id' => $locked->id, 'locked' => true]);
    }

    public function test_only_owners_reach_the_owner_side(): void
    {
        Sanctum::actingAs(User::where('email', 'therapist.a1@levictas.test')->firstOrFail());
        $this->getJson('/api/owner/branches')->assertForbidden();

        Sanctum::actingAs(User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail());
        $this->getJson('/api/owner/dashboard')->assertForbidden();
    }
}
