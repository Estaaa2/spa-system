<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentBranch
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
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
            $firstBranch = $user->spa->branches->first();
            if ($firstBranch) {
                session(['current_branch_id' => $firstBranch->id]);
            }
        }

        return $next($request);
    }
}