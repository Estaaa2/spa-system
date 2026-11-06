<?php

namespace App\Support;

use App\Models\User;
use App\Services\Navigation\Sidebar;

/**
 * Where a logged-in business user's "Go to Dashboard" link points on the
 * public pages (landing footer Phase 1, Q2-A).
 *
 * Mirrors AuthenticatedSessionController's post-login destinations:
 * - admin: the admin dashboard (route('dashboard') does not allow admins);
 * - owner: route('dashboard'), which sends an owner mid-setup to setup;
 * - every other business role: their first allowed page
 *   (Sidebar::landingUrl), because their Dashboard can be switched off and
 *   would answer 403.
 */
class BusinessHome
{
    public static function url(User $user): string
    {
        // The nav (desktop and mobile) and the footer each ask; build the
        // sidebar once per request.
        return once(function () use ($user) {
            if ($user->hasRole('admin')) {
                return route('admin.dashboard');
            }

            if ($user->hasRole('owner')) {
                return route('dashboard');
            }

            return Sidebar::landingUrl($user);
        });
    }
}
