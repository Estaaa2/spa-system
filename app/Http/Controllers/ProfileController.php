<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('success', 'Profile updated successfully!');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function password(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password'      => ['required', 'current_password'],
            'password'              => ['required', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        $user->update([
            'password'                => Hash::make($request->password),
            'temp_password'           => null,       // 👈 clear temp password
            'password_reset_required' => false,       // 👈 unlock dashboard access
        ]);

        return redirect()->route('profile.edit')
            ->with('success', 'Password changed successfully! You can now access the dashboard.');
    }

    public function updateCustomer(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);


        $user->update([
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'address' => $request->address,
            'latitude' => $request->latitude ?: null,
            'longitude' => $request->longitude ?: null,
        ]);

        return back()->with('success', 'Profile updated successfully!');
    }

    public function updateCustomerApi(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'first_name'  => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name'   => 'required|string|max:255',
            'address'     => 'nullable|string|max:1000',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
        ]);

        $user->update([
            'first_name'  => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name'   => $request->last_name,
            'address'     => $request->address,
            'latitude'    => $request->latitude ?: null,
            'longitude'   => $request->longitude ?: null,
        ]);

        $fresh = $user->fresh();

        return response()->json([
            'user' => [
                'id'          => $fresh->id,
                'first_name'  => $fresh->first_name,
                'middle_name' => $fresh->middle_name,
                'last_name'   => $fresh->last_name,
                'full_name'   => trim("{$fresh->first_name} {$fresh->last_name}"),
                'email'       => $fresh->email,
                'role'        => $fresh->role ?? 'customer',
                'is_verified' => (bool) $fresh->email_verified_at,
                'address'     => $fresh->address,
                'latitude'    => $fresh->latitude,
                'longitude'   => $fresh->longitude,
            ],
        ]);
    }

}
