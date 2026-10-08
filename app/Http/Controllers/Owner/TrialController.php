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

        return view('owner.trial.choose', [
            'plans' => collect(['basic', 'premium'])
                ->mapWithKeys(function (string $plan): array {
                    $config = config("plans.{$plan}");

                    return [
                        $plan => [
                            'name' => $config['name'],
                            'monthly_price' => $config['monthly_price'],
                            'max_branches' => $config['max_branches'],
                            'max_staff' => $config['max_staff'],
                            'features' => $config['features'],
                        ],
                    ];
                })
                ->all(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'in:basic,premium'],
        ]);

        $user = $request->user();

        $result = DB::transaction(function () use ($user, $validated): string {
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
                'trial_plan' => $validated['plan'],
                'trial_started_at' => $startedAt,
                'trial_ends_at' => $startedAt->copy()->addMonth(),
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
            ->with('success', 'Your free trial has started. No payment is required.');
    }
}
