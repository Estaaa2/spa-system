<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class CheckOwnerExists
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check on register page
        if ($request->routeIs('register')) {
            // If an owner already exists, redirect to login
            if (User::whereIsOwner(true)->exists()) {
                return redirect()->route('login')->with('error', 'Registration is no longer available. The system has already been set up.');
            }
        }

        return $next($request);
    }
}
