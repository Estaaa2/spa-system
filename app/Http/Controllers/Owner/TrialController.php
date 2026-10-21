<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Spa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TrialController extends Controller
{
    public function choose(Request $request): View|RedirectResponse
    {
        $spa = $request->user()->spa;

        abort_unless($spa, 403, 'No spa assigned to this account.');

        if ($spa->verification_status !== 'verified') {
            return redirect()->route('setup.index');
        }

        if ($spa->hasAccess()) {
            return redirect()
                ->route('dashboard')
                ->with('success', 'Your spa already has active access.');
        }

        if (! $spa->canStartTrial()) {
            return redirect()
                ->route('owner.subscription.index')
                ->with(
                    'error',
                    'Your free trial or subscription has ended. Please choose a plan to continue.'
                );
        }

        // The trial plan's details, plus the plans the owner can buy after it.
        $planKeys = array_unique(array_merge(
            [config('subscription.trial_plan', 'premium')],
            config('subscription.purchasable_plans', ['basic', 'premium'])
        ));

        return view('owner.trial.choose', [
            'plans' => collect($planKeys)
                ->mapWithKeys(fn (string $plan) => [$plan => config("plans.{$plan}")])
                ->filter(fn ($config) => is_array($config))
                ->sortBy(fn ($config) => $config['monthly_price'] ?? 0)
                ->all(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        // Every trial gives full access, so the plan picked on the old
        // choose page is ignored and the configured trial plan is used.
        $request->validate([
            'plan' => ['nullable', 'in:basic,premium'],
        ]);

        $user = $request->user();

        $result = DB::transaction(function () use ($user): string {
            $spa = Spa::query()
                ->whereKey($user->spa_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($spa->verification_status !== 'verified') {
                return 'setup';
            }

            if ($spa->hasAccess()) {
                return 'dashboard';
            }

            if (! $spa->canStartTrial()) {
                return 'subscription';
            }

            $startedAt = now();

            $spa->update([
                'trial_plan' => config('subscription.trial_plan', 'premium'),
                'trial_started_at' => $startedAt,
                'trial_ends_at' => $startedAt->copy()->addMonthsNoOverflow(
                    (int) config('subscription.trial_months', 1)
                ),
                'trial_used' => true,
            ]);

            return 'started';
        });

        if ($result === 'setup') {
            return redirect()->route('setup.index');
        }

        if ($result === 'dashboard') {
            return redirect()
                ->route('dashboard')
                ->with('success', 'Your spa already has active access.');
        }

        if ($result === 'subscription') {
            return redirect()
                ->route('owner.subscription.index')
                ->with(
                    'error',
                    'Your free trial or subscription has ended. Please choose a plan to continue.'
                );
        }

        return redirect()
            ->route('dashboard')
            ->with('success', 'Your free trial has started with full Premium access. No payment is required.');
    }
}
