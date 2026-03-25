<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchPermission
{
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403);
        }

        foreach ($permissions as $permission) {
            if ($user->hasBranchPermission($permission)) {
                return $next($request);
            }
        }

        abort(403, 'Unauthorized.');
    }
}