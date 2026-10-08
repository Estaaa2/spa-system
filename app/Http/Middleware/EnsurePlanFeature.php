<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanFeature
{
    public function handle(
        Request $request,
        Closure $next,
        string $feature
    ): Response {
        $user = $request->user();
        $spa = $user?->spa;

        if (! $spa) {
            abort(403, 'This account is not associated with a spa.');
        }

        if ($spa->isLocked()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your subscription or trial has expired. Please renew your plan.',
                ], 402);
            }

            return redirect()
                ->route('owner.subscription.index')
                ->with('error', 'Your subscription or trial has expired. Please renew your plan.');
        }

        if (! $spa->hasFeature($feature)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This feature is not available on your current plan.',
                    'feature' => $feature,
                    'current_plan' => $spa->currentPlan(),
                ], 403);
            }

            return redirect()
                ->back()
                ->with(
                    'error',
                    'This feature is not available on your current plan. Upgrade your subscription to continue.'
                );
        }

        return $next($request);
    }
}