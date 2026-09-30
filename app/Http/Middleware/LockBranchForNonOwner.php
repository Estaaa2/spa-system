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

        if ($user && ! $user->hasRole('owner') && $user->branch_id) {
            session(['current_branch_id' => $user->branch_id]);
        }

        return $next($request);
    }
}
