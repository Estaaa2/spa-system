<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchOperational
{
    /**
     * Routes that must keep working on a locked branch, so the owner can
     * switch away from it and manage the branch list.
     */
    private const ALWAYS_ALLOWED = [
        'branch.switch',
        'branch.current',
        'branches.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        return self::denialFor($request) ?? $next($request);
    }

    /**
     * Returns the response that blocks this request when the user's
     * current branch is locked, or null when the request may continue.
     */
    public static function denialFor(Request $request): ?Response
    {
        $user = $request->user();

        if (! $user || $user->hasRole('admin') || ! $user->spa_id) {
            return null;
        }

        if ($request->routeIs(...self::ALWAYS_ALLOWED)) {
            return null;
        }

        $spa = $user->spa;

        // Unverified spas and ended subscriptions are already handled
        // by the onboarding and subscription middleware.
        if (
            ! $spa ||
            $spa->verification_status !== 'verified' ||
            ! $spa->hasAccess()
        ) {
            return null;
        }

        $branchId = $user->currentBranchId();
        $branch = $branchId ? Branch::find($branchId) : null;

        if (! $branch) {
            return null;
        }

        $branch->setRelation('spa', $spa);

        $reason = $branch->lockReason();

        if ($reason === null) {
            return null;
        }

        $message = match ($reason) {
            'documents_expired' =>
                $branch->name . ' is locked because its Business Permit has expired. Renew it to continue.',

            'plan_limit' =>
                $branch->name . ' is not covered by your current plan. Upgrade your subscription to use it.',

            default =>
                $branch->name . ' is locked until an administrator approves its documents.',
        };

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'reason' => $reason,
            ], 423);
        }

        if ($user->hasRole('owner')) {
            return redirect()
                ->route(
                    $reason === 'plan_limit'
                        ? 'owner.subscription.index'
                        : 'owner.spa-profile.edit'
                )
                ->with('error', $message);
        }

        abort(403, $message);
    }
}