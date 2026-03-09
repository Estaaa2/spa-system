<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Already verified — redirect to correct destination
        if ($user->hasVerifiedEmail()) {
            return redirect($this->redirectTo($user));
        }

        // Not yet verified — show waiting page
        return view('auth.verify-email');
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
