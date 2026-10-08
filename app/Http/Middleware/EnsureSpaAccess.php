<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSpaAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->hasRole('admin') || ! $user->spa_id) {
            return $next($request);
        }

        $spa = $user->spa;

        if (! $spa || $spa->verification_status !== 'verified') {
            return $next($request);
        }

        if (! $spa->isLocked()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if ($user->hasRole('owner')) {
            $allowedPrefixes = [
                'owner.subscription.',
                'owner.trial.',
                'owner.spa-profile.',
            ];

            foreach ($allowedPrefixes as $prefix) {
                if (
                    is_string($routeName)
                    && str_starts_with($routeName, $prefix)
                ) {
                    return $next($request);
                }
            }

            if ($spa->canStartTrial()) {
                return redirect()->route('owner.trial.choose');
            }

            return redirect()
                ->route('owner.subscription.index')
                ->with(
                    'error',
                    'Your free trial or subscription has ended. Please choose a plan to continue.'
                );
        }

        return redirect()->route('spa.locked');
    }
}
