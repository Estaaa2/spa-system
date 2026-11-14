<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The branch an owner is looking at in the mobile app (owner mobile, Nov 2026).
 *
 * The web keeps it in the session (branch switcher, BranchController::switch).
 * The app has no session, so it sends X-Branch-Id on every request. This checks
 * the branch is one of the owner's, then puts it where the web code reads it
 * (session current_branch_id, for this request only), so the web's own
 * controllers answer for the right branch.
 *
 * No header: the main branch. A locked branch (verification, expired
 * documents, over the plan limit) is refused with 423, except on the branch
 * list, which the picker needs to switch away.
 */
class OwnerMobileBranch
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $requested = (int) ($request->header('X-Branch-Id') ?: $request->query('branch_id', 0));

        $branches = Branch::where('spa_id', $user->spa_id)->orderByDesc('is_main')->orderBy('id')->get();
        $spa = $user->spa;
        $branches->each(fn (Branch $b) => $b->setRelation('spa', $spa));

        $branch = $requested
            ? $branches->firstWhere('id', $requested)
            : ($branches->first(fn (Branch $b) => $b->isOperational()) ?? $branches->first());

        if (! $branch) {
            return response()->json(['message' => 'Branch not found.'], 404);
        }

        $request->attributes->set('owner_branch', $branch);
        $request->attributes->set('owner_branches', $branches);

        if (! $request->is('api/owner/branches') && ! $branch->isOperational()) {
            return response()->json([
                'message'     => 'This branch is locked. Choose another branch, or open Branches on the website.',
                'branch_lock' => $branch->lockReason(),
            ], 423);
        }

        // Where the web code reads the current branch (User::currentBranchId).
        session(['current_branch_id' => $branch->id]);

        return $next($request);
    }
}
