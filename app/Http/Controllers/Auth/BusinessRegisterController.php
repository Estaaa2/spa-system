<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class BusinessRegisterController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register-business');
    }

    /**
     * Validate, create owner account, send verification email,
     * then wait for verification before allowing setup access.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'lowercase', 'email:rfc', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Create owner account — not verified yet
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'is_owner' => true,
        ]);

        $user->assignRole('owner');

        // Send verification email via Registered event
        event(new Registered($user));

        Auth::login($user);

        // Flag so VerifyEmailController redirects to setup after verification
        session(['owner_pending_setup' => true]);

        return redirect()->route('verification.notice');
    }
}
