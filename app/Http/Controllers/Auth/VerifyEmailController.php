<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request)
    {
        // Step 1: Get user by ID from URL
        $user = User::findOrFail($request->route('id'));

        // Step 2: Validate hash
        if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            abort(403, 'Invalid verification link.');
        }

        // Step 3: Mark verified
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        // Step 4: Log the user in so the new browser has an active session!
        Auth::login($user);

        return redirect()->to($this->redirectTo($user))->with('verified', true); 
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
