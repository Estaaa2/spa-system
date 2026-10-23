<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LockBranchForNonOwner
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // The owner, HR Head and Finance Head switch branches; everyone else is
        // pinned to their home branch.
        if ($user && ! $user->canSwitchBranches() && $user->branch_id) {
            session(['current_branch_id' => $user->branch_id]);
        }

        return $next($request);
    }
}
