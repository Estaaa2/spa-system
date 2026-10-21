<?php

namespace App\Http\Middleware;

use App\Models\Staff;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // Staff paused because the spa is over its plan's staff limit are
        // signed out with a message, the usual "account not active,
        // contact your admin" pattern. Owners are never paused.
        if (! $user->hasRole('owner') && self::isPlanPaused($user)) {
            return self::signOutPaused($request, $spa->name);
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

    public static function isPlanPaused($user): bool
    {
        return Staff::query()
            ->where('user_id', $user->id)
            ->whereNotNull('plan_paused_at')
            ->whereNull('deleted_at')
            ->exists();
    }

    public static function pausedMessage(string $spaName): string
    {
        return "Your account is paused because {$spaName} has more staff than its current plan allows. Please contact your spa owner.";
    }

    private static function signOutPaused(Request $request, string $spaName): Response
    {
        $message = self::pausedMessage($spaName);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'reason' => 'staff_paused'], 423);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
