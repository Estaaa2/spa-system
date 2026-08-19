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
    private const CAVITE_LAT_MIN = 14.020;
    private const CAVITE_LAT_MAX = 14.520;
    private const CAVITE_LNG_MIN = 120.620;
    private const CAVITE_LNG_MAX = 121.100;

    // Display the profile edit form.
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    // Update the user's profile information.
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('success', 'Profile updated successfully!');
    }

    // Delete the user's account.
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

    /**
     * Named error bag for this form specifically. The landing page also has
     * a job-application form (PublicApplicationController::store, POST
     * /apply/{branch}) that does a full-page redirect on the same page using
     * Laravel's default unnamed bag — without a dedicated bag here, a failed
     * job application would incorrectly pop the profile modal back open.
     */
    private const CUSTOMER_PROFILE_ERROR_BAG = 'customerProfile';

    public function updateCustomer(Request $request)
    {
        $user = $request->user();

        $validated = $request->validateWithBag(self::CUSTOMER_PROFILE_ERROR_BAG, [
            'first_name'  => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name'   => 'required|string|max:255',
            'phone'       => ['nullable', 'string', 'regex:/^09\d{9}$/'],
            'address'     => 'nullable|string|max:255',
            'latitude'    => 'nullable|numeric|between:-90,90',
            'longitude'   => 'nullable|numeric|between:-180,180',
        ], [
            'phone.regex' => 'Enter a valid mobile number (09xxxxxxxxx).',
        ]);

        if ($request->filled('latitude') && $request->filled('longitude')) {
            $lat = (float) $validated['latitude'];
            $lng = (float) $validated['longitude'];

            if (!$this->withinCavite($lat, $lng)) {
                return back()
                    ->withErrors(['latitude' => 'Pinned location must be within Cavite. Please adjust the pin and try again.'], self::CUSTOMER_PROFILE_ERROR_BAG)
                    ->withInput();
            }
        }

        $user->update([
            'first_name'  => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name'   => $validated['last_name'],
            'phone'       => $validated['phone'] ?? null,
            'address'     => $validated['address'] ?? null,
            'latitude'    => $validated['latitude'] ?? null,
            'longitude'   => $validated['longitude'] ?? null,
        ]);

        return back()->with('success', 'Profile updated successfully!');
    }

    public function updateCustomerApi(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name'  => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name'   => 'required|string|max:255',
            'phone'       => ['nullable', 'string', 'regex:/^09\d{9}$/'],
            'address'     => 'nullable|string|max:1000',
            'latitude'    => 'nullable|numeric|between:-90,90',
            'longitude'   => 'nullable|numeric|between:-180,180',
        ], [
            'phone.regex' => 'Enter a valid mobile number (09xxxxxxxxx).',
        ]);

        if ($request->filled('latitude') && $request->filled('longitude')) {
            $lat = (float) $validated['latitude'];
            $lng = (float) $validated['longitude'];

            if (!$this->withinCavite($lat, $lng)) {
                return response()->json([
                    'message' => 'Pinned location must be within Cavite.',
                    'errors'  => ['latitude' => ['Pinned location must be within Cavite.']],
                ], 422);
            }
        }

        $user->update([
            'first_name'  => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name'   => $validated['last_name'],
            'phone'       => $validated['phone'] ?? null,
            'address'     => $validated['address'] ?? null,
            'latitude'    => $validated['latitude'] ?? null,
            'longitude'   => $validated['longitude'] ?? null,
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
                'phone'       => $fresh->phone,
                'role'        => $fresh->role ?? 'customer',
                'is_verified' => (bool) $fresh->email_verified_at,
                'address'     => $fresh->address,
                'latitude'    => $fresh->latitude,
                'longitude'   => $fresh->longitude,
            ],
        ]);
    }

    private function withinCavite(float $lat, float $lng): bool
    {
        return $lat >= self::CAVITE_LAT_MIN && $lat <= self::CAVITE_LAT_MAX
            && $lng >= self::CAVITE_LNG_MIN && $lng <= self::CAVITE_LNG_MAX;
    }
}