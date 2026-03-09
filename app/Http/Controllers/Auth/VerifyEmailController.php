<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Response;

class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): Response
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        $redirectUrl = $this->redirectTo($user);

        return response(view('auth.verify-success', [
            'redirectUrl' => $redirectUrl,
        ]));
    }

    private function redirectTo($user): string
    {
        // Owner who hasn't completed setup yet
        if ($user->hasRole('owner') && ! $user->spa_id) {
            return route('setup.index');
        }

        $redirectMap = [
            'customer'     => route('landing.page'),
            'admin'        => route('admin.dashboard'),
            'owner'        => route('dashboard'),
            'manager'      => route('dashboard'),
            'receptionist' => route('dashboard'),
            'therapist'    => route('dashboard'),
        ];

        foreach ($redirectMap as $role => $route) {
            if ($user->hasRole($role)) {
                return $route;
            }
        }

        return route('landing.page');
    }
}
