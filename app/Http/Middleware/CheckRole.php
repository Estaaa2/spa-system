<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $userRole = auth()->user()->role;

        // If no specific roles are required, allow all authenticated users
        if (empty($roles)) {
            return $next($request);
        }

        // Check if user's role is allowed
        if (in_array($userRole, $roles)) {
            return $next($request);
        }

        return response()->json([
            'error' => 'USER DOES NOT HAVE THE RIGHT ROLES.',
            'required_roles' => $roles,
            'user_role' => $userRole
        ], 403);
    }
}
