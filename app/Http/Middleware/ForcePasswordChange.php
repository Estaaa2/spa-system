<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && $user->password_reset_required) {
            // Allow profile routes only
            if (!$request->routeIs('profile.*') && !$request->routeIs('logout')) {
                return redirect()->route('profile.edit')
                    ->with('warning', 'You must change your password before continuing.');
            }
        }

        return $next($request);
    }
}
