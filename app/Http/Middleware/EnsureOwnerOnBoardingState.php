<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnerOnboardingState
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only owners are checked here. Staff and customers pass through.
        if (! $user || ! $user->hasRole('owner')) {
            return $next($request);
        }

        $spa = $user->spa;

        /*
         * Owners who have not created a spa yet must be able to use
         * the initial setup routes.
         */
        if (! $spa) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        /*
         * PENDING
         * The admin is still reviewing the documents.
         * Log the owner out and show the popup on the login page.
         */
        if ($spa->verification_status === 'pending') {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with([
                    'pending_review'   => true,
                    'pending_spa_name' => $spa->name,
                ]);
        }

        /*
         * REJECTED
         * The owner may go back to the setup pages and the document
         * upload/delete routes to fix and resubmit the documents.
         */
        if ($spa->verification_status === 'rejected') {
            $allowedRejectedRoutes = [
                'setup.index',
                'setup.branches',
                'setup.store-spa',
                'setup.store-branch',
                'setup.documents',
                'setup.complete',
                'owner.spa-profile.documents.upload',
                'owner.spa-profile.documents.destroy',
            ];

            if (in_array($routeName, $allowedRejectedRoutes, true)) {
                return $next($request);
            }

            return redirect()
                ->route('setup.documents')
                ->with('error', 'Please correct and resubmit your rejected documents.');
        }

        /*
         * VERIFIED
         * 1. Has access (trial, paid plan or grace period) -> continue.
         * 2. Subscription and Spa Profile pages are always allowed.
         * 3. New owner who never used the free trial -> trial choice page.
         * 4. Trial or plan ended -> Subscription & Billing only.
         */
        if ($spa->verification_status === 'verified') {
            $allowedSubscriptionRoutes = [
                'owner.subscription.index',
                'owner.subscription.checkout',
                'owner.subscription.success',
                'owner.subscription.cancel',
                'owner.subscription.cancel-subscription',
            ];

            $isSubscriptionRoute = in_array($routeName, $allowedSubscriptionRoutes, true)
                || $request->routeIs('owner.spa-profile.*');

            $isTrialRoute = in_array($routeName, [
                'owner.trial.choose',
                'owner.trial.start',
            ], true);

            if ($spa->hasAccess() || $isSubscriptionRoute) {
                return $next($request);
            }

            if ($spa->canStartTrial()) {
                return $isTrialRoute
                    ? $next($request)
                    : redirect()->route('owner.trial.choose');
            }

            return redirect()
                ->route('owner.subscription.index')
                ->with(
                    'error',
                    'Your free trial or subscription has ended. Please choose a plan to continue.'
                );
        }

        /*
         * Any other status (for example "unverified" after a document
         * was removed) keeps the old behavior and is allowed through.
         */
        return $next($request);
    }
}