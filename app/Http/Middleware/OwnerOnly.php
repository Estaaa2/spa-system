<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OwnerOnly
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (!$user || !$user->hasRole('owner')) {
            abort(403);
        }

        // If owner has NO spa yet → allow setup index (so they can create spa),
        // but block everything else under /setup that requires spa.
        if (!$user->spa) {
            // Allow only the setup index + storeSpa endpoint
            if ($request->routeIs('setup.index') || $request->routeIs('setup.store-spa')) {
                return $next($request);
            }

            return redirect()->route('setup.index');
        }

        // If you added is_setup_complete column, this is safe now
        if (!$user->spa->is_setup_complete) {
            // Still allow owner to access setup pages
            return $next($request);
        }

        // If setup is complete but no branches exist (just in case) → force branches setup
        if ($user->spa->branches()->count() === 0) {
            return redirect()->route('setup.branches');
        }

        return $next($request);
    }
}
