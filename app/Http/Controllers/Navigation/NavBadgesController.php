<?php

namespace App\Http\Controllers\Navigation;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Navigation\NavBadges;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET nav/badges — the sidebar's 60-second badge refresh (sidebar & RBAC rework
 * Phase 4). Returns the same cached counts the page was rendered with, so a
 * refresh costs one cache read per user per minute in the common case.
 */
class NavBadgesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return response()->json(['badges' => (object) NavBadges::forAdmin($user)]);
        }

        $spa = $user->spa;
        $branchId = $user->currentBranchId();
        $branch = $branchId ? Branch::query()->find($branchId) : null;

        // No badges where the sidebar shows none: no spa access, or a locked branch.
        if (! $spa || ! $spa->hasAccess() || ($branch && $branch->lockReason() !== null)) {
            return response()->json(['badges' => (object) []]);
        }

        return response()->json(['badges' => (object) NavBadges::forUser($user, $branchId)]);
    }
}
