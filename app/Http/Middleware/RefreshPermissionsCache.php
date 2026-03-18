<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class RefreshPermissionsCache
{
    public function handle(Request $request, Closure $next)
    {
        // Only for authenticated users
        if (auth()->check()) {
            // Force reload permissions from DB on every request
            // This ensures role permission changes reflect immediately
            auth()->user()->unsetRelation('roles');
            auth()->user()->unsetRelation('permissions');
        }

        return $next($request);
    }
}
