<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LandingPageRedirect
{
    public function handle(Request $request, Closure $next)
    {
        // Always skip verification routes first
        if ($request->routeIs('verification.verify') || $request->routeIs('verification.notice')) {
            return $next($request);
        }

        $user = $request->user()?->refresh();
        if ($user) {

            // Staff / admin / owner / manager → dashboard
            if ($user->hasAnyRole(['staff', 'admin', 'owner', 'manager'])) {
                if ($request->routeIs('dashboard')) {
                    return $next($request);
                }
                return redirect()->route('dashboard');
            }

            // Customer logic
            if ($user->hasRole('customer')) {

                // Already on landing page → skip redirect
                if ($request->routeIs('landing.page') && $user->hasVerifiedEmail()) {
                    return $next($request);
                }

                if ($user->hasVerifiedEmail()) {
                    return redirect()->route('landing.page'); // verified customer
                } else {
                    return redirect()->route('verification.notice'); // unverified customer
                }
            }
        }

        // Guests → landing page
        return $next($request);
    }
}