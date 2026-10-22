<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Spa;
use App\Models\Subscription;
use App\Models\TrialExtension;
use App\Services\Subscription\PlanLimitEnforcer;
use App\Services\PayMongo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    /** Tabs that list payment rows. */
    private const PAYMENT_TABS = ['active', 'scheduled', 'ended', 'unpaid'];

    /** Tabs that list spas (trials have no payment rows). */
    private const SPA_TABS = ['trialing', 'lapsed'];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $tab = $request->query('state');
        $tab = in_array($tab, [...self::PAYMENT_TABS, ...self::SPA_TABS], true) ? $tab : null;

        $search = $q === '' ? null : '%' . addcslashes($q, '%_') . '%';

        $data = [
            'q' => $q,
            'state' => $tab,
            'paymentTabs' => self::PAYMENT_TABS,
            'spaTabs' => self::SPA_TABS,
            'counts' => $this->counts(),
            'kpis' => $this->kpis(),
            'subscriptions' => null,
            'spas' => null,
            'recentExtensions' => collect(),
            'extensionMaxDays' => (int) config('subscription.trial_extension_max_days', 30),
            'extensionMaxTotal' => (int) config('subscription.trial_extension_max_total_days', 60),
        ];

        if (in_array($tab, self::SPA_TABS, true)) {
            $data['spas'] = ($tab === 'trialing' ? $this->trialingSpas() : $this->lapsedSpas())
                ->with([
                    'owner',
                    // Paid rows drive every status method; loading them once
                    // keeps the list to a fixed number of queries.
                    'subscriptions' => fn ($rows) => $rows->where('payment_status', 'paid'),
                ])
                ->withSum('trialExtensions as extended_days', 'days')
                ->when($search, fn ($query) => $query->where('name', 'like', $search))
                ->when(
                    $tab === 'trialing',
                    fn ($query) => $query->orderBy('trial_ends_at'),
                    fn ($query) => $query->latest('updated_at')
                )
                ->paginate(15)
                ->withQueryString();

            if ($tab === 'trialing') {
                $data['recentExtensions'] = TrialExtension::query()
                    ->with(['spa', 'admin'])
                    ->latest('id')
                    ->take(10)
                    ->get();
            }
        } else {
            $data['subscriptions'] = $this->paymentsIn($tab)
                // Removed spas keep their payment history, so load them too.
                ->with(['spa' => fn ($spa) => $spa->withTrashed()->with('owner')])
                ->when($search, fn ($query) => $query->whereHas(
                    'spa',
                    fn ($spa) => $spa->withTrashed()->where('name', 'like', $search)
                ))
                ->latest('id')
                ->paginate(15)
                ->withQueryString();
        }

        return view('admin.subscriptions.index', $data);
    }

    /**
     * Adds days to a spa's free trial. Meant for pilot spas and test runs,
     * so it is limited and every use is logged with a reason.
     */
    public function extendTrial(Request $request, Spa $spa, PlanLimitEnforcer $enforcer): RedirectResponse
    {
        $maxDays = (int) config('subscription.trial_extension_max_days', 30);
        $maxTotal = (int) config('subscription.trial_extension_max_total_days', 60);

        $validator = Validator::make($request->all(), [
            'days' => ['required', 'integer', 'min:1', "max:{$maxDays}"],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [], [
            'days' => 'number of days',
        ]);

        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first());
        }

        $days = (int) $request->input('days');
        $reason = trim((string) $request->input('reason'));

        $result = DB::transaction(function () use ($spa, $days, $reason, $maxTotal) {
            $locked = Spa::query()->whereKey($spa->id)->lockForUpdate()->firstOrFail();

            if ($locked->trial_used !== true || $locked->trial_ends_at === null) {
                return 'This spa has not started its free trial yet. The owner starts it from Subscription & Billing.';
            }

            // Paid periods queue after the trial. Moving the trial end would
            // move or overlap paid time, so only never-paid spas qualify.
            if ($locked->hasEverPaid()) {
                return 'This spa already has paid time, so its trial cannot be extended.';
            }

            $alreadyAdded = (int) TrialExtension::where('spa_id', $locked->id)->sum('days');

            if ($alreadyAdded + $days > $maxTotal) {
                $left = max(0, $maxTotal - $alreadyAdded);

                return $left === 0
                    ? "This spa's trial has already been extended by the maximum of {$maxTotal} days."
                    : "This spa's trial can be extended by at most {$left} more day" . ($left === 1 ? '' : 's') . '.';
            }

            $previous = $locked->trial_ends_at->copy();

            // An ended trial restarts from today, not from its old end date.
            $from = $previous->isFuture() ? $previous->copy() : now();
            $newEnd = $from->addDays($days);

            $locked->update([
                'trial_ends_at' => $newEnd,
                // So the "trial ending soon" email goes out again before the new end.
                'trial_reminder_sent_at' => null,
            ]);

            TrialExtension::create([
                'spa_id' => $locked->id,
                'admin_id' => auth()->id(),
                'days' => $days,
                'previous_ends_at' => $previous,
                'new_ends_at' => $newEnd,
                'reason' => $reason,
            ]);

            return $locked;
        });

        if (is_string($result)) {
            return back()->with('error', $result);
        }

        // A locked spa gets access back: restore anything paused by plan limits.
        $result->forgetSubscriptionCache();
        $enforcer->sync($result);

        return redirect()
            ->route('admin.subscriptions.index', ['state' => 'trialing'])
            ->with('success', sprintf(
                'Trial for %s extended by %d day%s. It now ends %s.',
                $result->name,
                $days,
                $days === 1 ? '' : 's',
                $result->trial_ends_at->format('M d, Y')
            ));
    }

    /*
    |--------------------------------------------------------------------------
    | Queries
    |--------------------------------------------------------------------------
    */

    /**
     * active     paid, counts for access, running now
     * scheduled  paid, starts in the future (early renewal, paid during trial)
     * ended      paid, past its end or credited to an upgrade
     * unpaid     checkout started but never paid (pending, failed, abandoned)
     */
    private function paymentsIn(?string $tab): Builder
    {
        $query = Subscription::query();
        $now = now();

        return match ($tab) {
            'active' => $query->countable()
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now)),
            'scheduled' => $query->countable()->where('starts_at', '>', $now),
            'ended' => $query->where('payment_status', 'paid')
                ->where(fn ($q) => $q->whereNotNull('superseded_at')->orWhere('expires_at', '<=', $now)),
            'unpaid' => $query->where('payment_status', '!=', 'paid'),
            default => $query,
        };
    }

    private function trialingSpas(): Builder
    {
        return Spa::query()
            ->where('trial_used', true)
            ->where('trial_ends_at', '>', now());
    }

    /**
     * Spas with no access right now that had a trial or paid before:
     * in the grace days, or locked.
     */
    private function lapsedSpas(): Builder
    {
        $now = now();

        return Spa::query()
            ->where(fn ($q) => $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', $now))
            ->where(fn ($q) => $q
                ->where('trial_used', true)
                ->orWhereHas('subscriptions', fn ($rows) => $rows->where('payment_status', 'paid')))
            ->whereDoesntHave('subscriptions', fn ($rows) => $rows->countable()
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now)));
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $counts = ['' => $this->paymentsIn(null)->count()];

        foreach (self::PAYMENT_TABS as $tab) {
            $counts[$tab] = $this->paymentsIn($tab)->count();
        }

        $counts['trialing'] = $this->trialingSpas()->count();
        $counts['lapsed'] = $this->lapsedSpas()->count();

        return $counts;
    }

    /** Figures for the KPI cards. Trials never count as revenue. */
    private function kpis(): array
    {
        $now = now();
        $graceDays = (int) config('subscription.grace_days', 3);

        // Trial-to-paid: trials that ended in the last 90 days, and how many
        // of those spas have paid at least once.
        $endedTrials = Spa::withTrashed()
            ->where('trial_used', true)
            ->whereBetween('trial_ends_at', [$now->copy()->subDays(90), $now]);

        $endedCount = (clone $endedTrials)->count();
        $convertedCount = (clone $endedTrials)
            ->whereHas('subscriptions', fn ($rows) => $rows->where('payment_status', 'paid'))
            ->count();

        // Spas whose paid time runs out within 7 days with nothing queued
        // after it. A spa that already paid its next period is not counted.
        $expiringSoon = Subscription::query()
            ->countable()
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->whereBetween('expires_at', [$now, $now->copy()->addDays(7)])
            ->whereNotExists(fn ($next) => $next
                ->selectRaw('1')
                ->from('subscriptions as next_period')
                ->whereColumn('next_period.spa_id', 'subscriptions.spa_id')
                ->where('next_period.payment_status', 'paid')
                ->whereNull('next_period.superseded_at')
                ->whereColumn('next_period.starts_at', '>=', 'subscriptions.expires_at'))
            ->distinct()
            ->count('spa_id');

        $lapsed = $this->lapsedSpas()->count();
        $inGrace = $this->lapsedSpas()
            ->whereHas('subscriptions', fn ($rows) => $rows->countable()
                ->where('expires_at', '>', $now->copy()->subDays($graceDays)))
            ->count();

        return [
            'trialsActive' => $this->trialingSpas()->count(),
            'trialsEndingSoon' => $this->trialingSpas()->where('trial_ends_at', '<=', $now->copy()->addDays(7))->count(),
            'trialsEnded' => $endedCount,
            'trialsConverted' => $convertedCount,
            'conversionRate' => $endedCount > 0 ? (int) round($convertedCount / $endedCount * 100) : null,
            // Only payments made in the current PayMongo mode: test payments
            // are not real money once the site is live.
            'revenueTotal' => (float) Subscription::where('payment_status', 'paid')
                ->where('livemode', PayMongo::isLive())
                ->sum('amount'),
            'revenueMonth' => (float) Subscription::where('payment_status', 'paid')
                ->where('livemode', PayMongo::isLive())
                ->where('paid_at', '>=', $now->copy()->startOfMonth())
                ->sum('amount'),
            'liveMode' => PayMongo::isLive(),
            'expiringSoon' => $expiringSoon,
            'inGrace' => $inGrace,
            'locked' => max(0, $lapsed - $inGrace),
        ];
    }
}
