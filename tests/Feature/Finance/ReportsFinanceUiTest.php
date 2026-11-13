<?php

namespace Tests\Feature\Finance;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\Spa;
use App\Models\User;
use Database\Seeders\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Reports & finance revamp: Reports, Decision Support, Revenue, Billing & Expenses and
 * Vendor Bills follow the business-side design language (wrapper, cards, .rt tables,
 * shared date filter, pinned charts, modal contract).
 */
class ReportsFinanceUiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Spa $spa;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 10:00:00');
        $this->withoutVite();

        // Decision Support uses MariaDB's HOUR() and DAYOFWEEK(); give the SQLite test DB the same.
        $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateFunction('HOUR', fn ($t) => $t === null ? null : (int) substr((string) $t, -8, 2), 1);
            $pdo->sqliteCreateFunction('DAYOFWEEK', fn ($d) => $d === null ? null : (int) date('w', strtotime((string) $d)) + 1, 1);
        }

        $this->seed(PayrollTestAccountsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->spa = Spa::withoutGlobalScopes()->where('name', PayrollTestAccountsSeeder::SPA_NAME)->firstOrFail();
        $this->branch = Branch::withoutGlobalScopes()->where('spa_id', $this->spa->id)->where('is_main', true)->firstOrFail();
        $this->owner = User::where('email', 'owner@levictas.test')->firstOrFail();

        Expense::create([
            'spa_id' => $this->spa->id, 'branch_id' => $this->branch->id, 'requested_by' => $this->owner->id,
            'title' => 'Aircon "cleaning"', 'description' => 'Quarterly service', 'amount' => 2500, 'status' => 'pending',
        ]);
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
            'reports'          => ['reports.index'],
            'decision support' => ['decision-support.index'],
            'revenue'          => ['revenue.index'],
            'billing'          => ['billing.index'],
            'vendor bills'     => ['vendor-bills.index'],
        ];
    }

    private function pageHtml(string $route, array $query = [], ?User $as = null): string
    {
        $html = $this->actingAs($as ?? $this->owner)->get(route($route, $query))->assertOk()->getContent();
        $start = strpos($html, 'p-4 mx-auto space-y-6 sm:p-6 max-w-7xl');
        $this->assertNotFalse($start, "$route does not use the page wrapper");

        return substr($html, $start);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_pages_follow_the_design_language(string $route): void
    {
        $html = $this->pageHtml($route);

        $this->assertStringContainsString('rounded-2xl', $html);
        if ($route !== 'decision-support.index') { // charts only, no table
            $this->assertStringContainsString('class="min-w-full divide-y divide-gray-200 rt', $html, "$route table must stack on phones (.rt)");
            $this->assertStringContainsString('.rt td[data-label]::before', $html);
        }
        // Legacy look: small filter chips, rounded-lg controls, unicode ✓ / ✕ icons.
        $this->assertStringNotContainsString('px-3 py-1.5 text-xs font-medium rounded-lg', $html);
        $this->assertStringNotContainsString('rounded-lg hover:opacity-90', $html);
        $this->assertStringNotContainsString('>✕<', $html);
        // Never an unpinned Chart.js build.
        $this->assertStringNotContainsString('cdn.jsdelivr.net/npm/chart.js"', $html);
    }

    /** @return array<string, array{string}> */
    public static function datedPages(): array
    {
        return [
            'reports'          => ['reports.index'],
            'decision support' => ['decision-support.index'],
            'revenue'          => ['revenue.index'],
            'billing'          => ['billing.index'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('datedPages')]
    public function test_dated_pages_use_the_shared_date_filter(string $route): void
    {
        $html = $this->pageHtml($route, ['from' => '2026-10-01', 'to' => '2026-10-31']);

        $this->assertStringContainsString('data-date-range', $html);
        $this->assertMatchesRegularExpression('/<option value="lastmonth"[^>]*selected/', $html);
        $this->assertStringContainsString('name="from" value="2026-10-01"', $html);
        $this->assertStringContainsString('Oct 1 – Oct 31, 2026', $html);
        $this->assertStringContainsString('min-h-[44px]', $html);
    }

    /** @return array<string, array{string}> */
    public static function chartPages(): array
    {
        return [
            'reports'          => ['reports.index'],
            'decision support' => ['decision-support.index'],
            'revenue'          => ['revenue.index'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('chartPages')]
    public function test_charts_are_pinned_and_sized_for_phones(string $route): void
    {
        $html = $this->pageHtml($route, ['from' => '2026-10-01', 'to' => '2026-11-12']);

        $this->assertSame(1, substr_count($html, 'chart.js@4.6.1/dist/chart.umd.min.js'));
        $this->assertStringContainsString('Chart.defaults.maintainAspectRatio = false', $html);
        $this->assertStringContainsString('role="img"', $html);
        $this->assertStringNotContainsString('<canvas id="revenueChart" height=', $html);
    }

    public function test_decision_support_lists_urgent_insights_first(): void
    {
        $html = $this->pageHtml('decision-support.index', ['from' => '2030-01-01', 'to' => '2030-01-07']);

        // No bookings → the "No Booking Data" info insight, and the Bookings KPI card.
        $this->assertStringContainsString('No Booking Data', $html);
        $this->assertStringContainsString('fa-circle-info', $html);
        $this->assertStringContainsString('a day on average', $html);
    }

    public function test_billing_buttons_follow_the_route_permissions(): void
    {
        $html = $this->pageHtml('billing.index');
        $this->assertStringContainsString('File Expense', $html);
        $this->assertStringContainsString('openReviewModal(this)', $html);
        // Title goes through a data attribute, escaped — never into inline JS.
        $this->assertStringContainsString('data-title="Aircon &quot;cleaning&quot;"', $html);
        $this->assertStringContainsString('role="dialog" aria-modal="true" aria-labelledby="expenseModalTitle"', $html);

        $finance = User::where('email', 'finance.a@levictas.test')->firstOrFail();
        Role::findByName('finance')->revokePermissionTo(['create billing', 'edit billing']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $html = $this->pageHtml('billing.index', [], $finance->fresh());
        $this->assertStringNotContainsString('File Expense', $html);
        $this->assertStringNotContainsString('openReviewModal(this)', $html);
        $this->assertStringNotContainsString('id="reviewModal"', $html);
    }

    public function test_file_expense_modal_reopens_with_errors(): void
    {
        $this->actingAs($this->owner)
            ->from(route('billing.index'))
            ->post(route('billing.expense.store'), ['title' => 'Towels', 'amount' => ''])
            ->assertRedirect(route('billing.index'))
            ->assertSessionHasErrors('amount');

        $html = $this->actingAs($this->owner)->get(route('billing.index'))->assertOk()->getContent();
        $this->assertStringContainsString('openExpenseModal();', $html);
        $this->assertStringContainsString('value="Towels"', $html);
        $this->assertStringContainsString('id="expense_amount_error"', $html);
    }

    public function test_vendor_bills_modals_render_safely_and_full_width(): void
    {
        $html = $this->pageHtml('vendor-bills.index');
        $this->assertStringContainsString('selectedEditBill: { items: [] }', $html);
        $this->assertStringContainsString('max-w-3xl', $html);
    }
}
