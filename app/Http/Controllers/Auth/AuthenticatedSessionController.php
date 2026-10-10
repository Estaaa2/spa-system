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
        $request->session()->forget('current_branch_id');

        $user = Auth::user();

        /*
         * Email verification is required before login.
         * Administrators are exempt from this requirement.
         */
        if (! $user->hasRole('admin') && ! $user->hasVerifiedEmail()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'You need to verify your email before logging in. Please check your inbox.',
                ]);
        }

        if ($user->hasRole('admin')) {
            return redirect()->route('admin.dashboard');
        }

        /*
         * Owners whose documents are still under review cannot enter
         * the application yet.
         */
        if (
            $user->hasRole('owner')
            && $user->spa
            && $user->spa->verification_status === 'pending'
        ) {
            $spaName = $user->spa->name;

            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with([
                    'pending_review' => true,
                    'pending_spa_name' => $spaName,
                ]);
        }

        /*
         * Force password change when the account was created
         * with a temporary password.
         */
        if ($user->password_reset_required) {
            return redirect(route('profile.edit') . '#password');
        }

        /*
         * Owners are redirected according to their current
         * verification and subscription state.
         */
        if ($user->hasRole('owner')) {
            return redirect()->to($this->ownerRedirectRoute($user));
        }

        $redirectMap = [
            'customer' => route('landing.page'),
            'manager' => route('dashboard'),
            'receptionist' => route('dashboard'),
            'therapist' => route('dashboard'),
            'hr' => route('hiring.index'),
            'finance' => route('reports.index'),
        ];

        foreach ($redirectMap as $role => $route) {
            if ($user->hasRole($role)) {
                return redirect($route);
            }
        }

        return redirect('/');
    }

    /**
     * Determine the correct destination for an owner after login.
     */
    private function ownerRedirectRoute($user): string
    {
        if (! $user->spa_id || ! $user->spa) {
            return route('setup.index');
        }

        $spa = $user->spa;

        if ($spa->verification_status === 'pending') {
            return route('login');
        }

        if ($spa->verification_status === 'rejected') {
            return route('setup.documents');
        }

        if ($spa->verification_status !== 'verified') {
            return route('setup.index');
        }

        if ($spa->hasAccess()) {
            return route('dashboard');
        }

        if ($spa->canStartTrial()) {
            return route('owner.trial.choose');
        }

        return route('owner.subscription.index');
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
