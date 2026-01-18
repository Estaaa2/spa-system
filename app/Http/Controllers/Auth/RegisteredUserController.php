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

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        // Check if first user exists
        if (User::whereIsOwner(true)->exists()) {
            return redirect()->route('login')->with('error', 'Registration is no longer available.');
        }

        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Check if first user (owner) already exists
        if (User::whereIsOwner(true)->exists()) {
            return redirect()->route('login')->with('error', 'Registration is no longer available.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_owner' => true, // First registered user is the owner
        ]);

        // Assign owner role
        $user->assignRole('owner');

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('setup.index', absolute: false));
    }
}
