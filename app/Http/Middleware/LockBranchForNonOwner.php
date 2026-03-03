<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LockBranchForNonOwner
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();

            // If NOT owner → force branch session
            if (! $user->can('view owner dashboard')) {

                if ($user->branch_id) {
                    session(['current_branch_id' => $user->branch_id]);
                }
            }
        }

        return $next($request);
    }
}
