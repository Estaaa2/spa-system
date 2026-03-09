<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationCheckController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // If session expired or not authenticated, return not verified
        if (! $request->user()) {
            return response()->json(['verified' => false]);
        }

        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            return response()->json(['verified' => false]);
        }

        $redirectMap = [
            'customer'     => route('landing.page'),
            'admin'        => route('admin.dashboard'),
            'owner'        => route('dashboard'),
            'manager'      => route('dashboard'),
            'receptionist' => route('dashboard'),
            'therapist'    => route('dashboard'),
        ];

        $redirect = route('landing.page');

        foreach ($redirectMap as $role => $route) {
            if ($user->hasRole($role)) {
                $redirect = $route;
                break;
            }
        }

        return response()->json([
            'verified' => true,
            'redirect' => $redirect,
        ]);
    }
}
