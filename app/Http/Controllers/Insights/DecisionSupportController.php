<?php

namespace App\Http\Controllers\Insights;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DssRecommendation;
use App\Models\Package;
use App\Models\Treatment;
use App\Models\User;
use App\Services\DecisionSupport\CapacityCalculator;
use App\Services\DecisionSupport\DemandForecaster;
use App\Services\DecisionSupport\RecommendationEngine;
use App\Services\Navigation\NavBadges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

/**
 * Decision Support (DSS revamp). Looks ahead and recommends; Reports keeps
 * "what happened" (D7). The old 11 threshold insights and the charts shared
 * with Reports are gone. Same gate as before: branch.permission:view decision
 * support + plan feature insights (routes/web.php).
 */
class DecisionSupportController extends Controller
{
    public function __construct(
        private readonly DemandForecaster $forecaster,
        private readonly RecommendationEngine $engine,
    ) {
    }

    public function index(Request $request)
    {
        return view('insights.decision-support.index', $this->pageData($request));
    }

    /**
     * Everything the Decision Support page shows for the selected branch.
     * Shared with the owner app (Api\Owner\OwnerAppController::decisionSupport).
     */
    public function pageData(Request $request): array
    {
        $user = $request->user();
        $spa = $user->spa;
        $spaId = (int) $user->spa_id;
        $branchId = $user->currentBranchId();

        // First visit before the nightly job ever ran for this spa: compute now,
        // so the page is never empty just because the scheduler hasn't fired yet.
        if (! Cache::has(self::syncKey($spaId)) && $spa) {
            $this->runSync($spa);
        }

        $forecast = $branchId
            ? $this->forecaster->forecast($spaId, $branchId)
            : ['enough_data' => false, 'weeks' => 0, 'avg_duration' => 60, 'days' => []];

        $open = DssRecommendation::query()->visibleTo($user, $branchId)->open()->byPriority()->with('branch:id,name')->get();
        $history = DssRecommendation::query()->visibleTo($user, $branchId)
            ->whereIn('status', [DssRecommendation::ACCEPTED, DssRecommendation::DISMISSED])
            ->with(['decider:id,first_name,last_name', 'branch:id,name'])
            ->orderByDesc('decided_at')->orderByDesc('id')
            ->limit(25)->get();

        return [
            'branchId'        => $branchId,
            'forecast'        => $forecast,
            'recommendations' => $open,
            'history'         => $history,
            'lastSync'        => Cache::get(self::syncKey($spaId)),
            'promoItems'      => $branchId ? $this->promoItems($spaId, $branchId) : [],
            'actionUrls'      => $open->mapWithKeys(fn ($r) => [$r->id => self::actionUrl($r, $user)])->all(),
            'thresholds'      => [
                'busy' => RecommendationEngine::BUSY_USE, 'light' => RecommendationEngine::LIGHT_USE,
                'weeks' => DemandForecaster::LOOKBACK_WEEKS, 'min_weeks' => DemandForecaster::MIN_WEEKS,
            ],
        ];
    }

    public function refresh(Request $request): RedirectResponse
    {
        $spa = $request->user()->spa;
        abort_unless($spa, 404);

        $open = $this->runSync($spa);

        return redirect()->route('decision-support.index')
            ->with('success', 'Decision Support checked your data again. '.$open.' open '.($open === 1 ? 'recommendation' : 'recommendations').' for the spa.');
    }

    public function accept(Request $request, DssRecommendation $recommendation): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRecommendation($recommendation, $user);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        if ($recommendation->status !== DssRecommendation::OPEN) {
            return back()->with('error', 'This recommendation was already decided or is no longer current.');
        }

        $recommendation->update([
            'status' => DssRecommendation::ACCEPTED, 'note' => $data['note'] ?? null,
            'decided_by' => $user->id, 'decided_at' => now(),
        ]);
        NavBadges::bump((int) $user->spa_id);

        // Q10 B: accept, then open the prefilled action when the user may use it.
        if ($url = self::actionUrl($recommendation, $user)) {
            return redirect()->to($url)->with('success', 'Accepted. Saved to the Decision Support history.');
        }

        return redirect()->route('decision-support.index')->with('success', 'Accepted. Saved to the decision history.');
    }

    public function dismiss(Request $request, DssRecommendation $recommendation): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRecommendation($recommendation, $user);

        $data = $request->validate([
            'reason' => ['nullable', Rule::in(array_keys(DssRecommendation::DISMISS_REASONS))],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        if ($recommendation->status !== DssRecommendation::OPEN) {
            return back()->with('error', 'This recommendation was already decided or is no longer current.');
        }

        $recommendation->update([
            'status' => DssRecommendation::DISMISSED,
            'dismiss_reason' => $data['reason'] ?? null, 'note' => $data['note'] ?? null,
            'decided_by' => $user->id, 'decided_at' => now(),
        ]);
        NavBadges::bump((int) $user->spa_id);

        return redirect()->route('decision-support.index')->with('success', 'Dismissed. It stays hidden until the situation changes.');
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    public static function syncKey(int $spaId): string
    {
        return 'dss-last-sync:'.$spaId;
    }

    private function runSync($spa): int
    {
        $open = $this->engine->sync($spa);
        Cache::forever(self::syncKey((int) $spa->id), now()->toIso8601String());

        return $open;
    }

    /** Same spa, and the branch the user is looking at; spa-level items for the owner only. */
    private function authorizeRecommendation(DssRecommendation $r, User $user): void
    {
        $ok = (int) $r->spa_id === (int) $user->spa_id
            && ($r->scope === DssRecommendation::SCOPE_SPA
                ? $user->hasRole('owner')
                : (int) $r->branch_id === (int) $user->currentBranchId());

        abort_unless($ok, 404);
    }

    /** The action link, or null when the user lacks the permission / plan feature for it. */
    public static function actionUrl(DssRecommendation $r, User $user): ?string
    {
        $a = $r->action;
        if (! is_array($a) || empty($a['route']) || ! Route::has($a['route'])) {
            return null;
        }
        if (! empty($a['permission']) && ! $user->hasBranchPermission($a['permission'])) {
            return null;
        }
        if (! empty($a['feature']) && ! $user->spa?->hasFeature($a['feature'])) {
            return null;
        }
        if (in_array($a['route'], ['branches.index', 'subscription.index'], true) && ! $user->hasRole('owner')) {
            return null;
        }

        return route($a['route'], $a['params'] ?? []);
    }

    /** Treatments and packages of the branch with price and last-28-day bookings, for break-even. */
    private function promoItems(int $spaId, int $branchId): array
    {
        $since = now()->subDays(28)->toDateString();
        $counts = Booking::query()->where('spa_id', $spaId)->where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$since, now()->subDay()->toDateString()])
            ->whereNotIn('status', CapacityCalculator::NOT_DEMAND)
            ->whereNotNull('treatment')
            ->groupBy('treatment')->selectRaw('treatment, COUNT(*) as n')
            ->pluck('n', 'treatment');

        $items = [];
        foreach (Treatment::withoutGlobalScope('spa_branch')->where('spa_id', $spaId)->where('branch_id', $branchId)->orderBy('name')->get(['id', 'name', 'price']) as $t) {
            $items[] = ['code' => 'treatment_'.$t->id, 'name' => $t->name, 'price' => (float) $t->price, 'bookings' => (int) ($counts['treatment_'.$t->id] ?? 0)];
        }
        foreach (Package::withoutGlobalScope('spa_branch')->where('spa_id', $spaId)->where('branch_id', $branchId)->orderBy('name')->get(['id', 'name', 'price']) as $p) {
            $items[] = ['code' => 'package_'.$p->id, 'name' => $p->name.' (package)', 'price' => (float) $p->price, 'bookings' => (int) ($counts['package_'.$p->id] ?? 0)];
        }

        return $items;
    }
}
