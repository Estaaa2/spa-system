<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class EnsureCurrentBranch
{
    public function handle($request, Closure $next)
    {
        $user = Auth::user();

        // If not logged in, just continue
        if (! $user) {
            return $next($request);
        }

        // Manager: force their branch
        if ($user->hasRole('manager')) {
            session(['current_branch_id' => $user->branch_id]);
        }

        // Owner: ensure session exists, otherwise pick first branch
        if ($user->hasRole('owner') && ! session()->has('current_branch_id')) {

            // Owner may not have a spa yet (setup flow)
            $spa = $user->ownedSpas()->first();

            if ($spa) {
                $firstBranch = $spa->branches()->first();

                if ($firstBranch) {
                    session(['current_branch_id' => $firstBranch->id]);
                }
            }
        }

        // ✅ ALWAYS continue the request
        return $next($request);
    }
}
