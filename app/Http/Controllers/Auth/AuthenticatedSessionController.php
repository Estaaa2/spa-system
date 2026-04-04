<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        // Block login if email is not yet verified (admins are excluded)
        if (! $user->hasRole('admin') && ! $user->hasVerifiedEmail()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors([
                    'email' => 'You need to verify your email before logging in. Please check your inbox.',
                ]);
        }

        // Force password change if using temporary password
        if ($user->password_reset_required) {
            return redirect()->route('profile.edit')
                ->with('warning', 'Please change your temporary password to continue.');
        }

        $redirectMap = [
            'customer'     => route('landing.page'),
            'admin'        => route('admin.dashboard'),
            'owner'        => route('dashboard'),
            'manager'      => route('dashboard'),
            'receptionist' => route('dashboard'),
            'therapist'    => route('dashboard'),
            'hr'           => route('hiring.index'),
            'finance'      => route('reports.index'),
        ];

        foreach ($redirectMap as $role => $route) {
            if ($user->hasRole($role)) {
                return redirect($route);
            }
        }

        return redirect('/');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
