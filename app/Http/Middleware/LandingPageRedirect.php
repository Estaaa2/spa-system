<?php

namespace App\Http\Middleware;

use App\Support\BusinessHome;
use Closure;
use Illuminate\Http\Request;

/**
 * Applied to the landing page (/) only. Public pages (layouts/public) must
 * not use it: they are open to every role.
 */
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

            // Any business role (owner, manager, therapist, receptionist, HR,
            // finance, admin, …) → the page login sends them to.
            // This used to check ['staff', 'admin', 'owner', 'manager'] and send
            // them to route('dashboard'): 'staff' is not a role, so therapists,
            // receptionists, HR and finance users fell through to the landing
            // page, and admins were sent to a dashboard that doesn't allow them
            // (403). Landing footer Phase 1.1.
            if ($user->roles()->where('name', '!=', 'customer')->exists()) {
                return redirect()->to(BusinessHome::url($user));
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

        // Guests (and a user with no role at all) → landing page
        return $next($request);
    }
}
