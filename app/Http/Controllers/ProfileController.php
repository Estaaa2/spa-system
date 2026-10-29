<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\IdentityVerification;
use App\Services\Identity\IdentityVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    private const CAVITE_LAT_MIN = 14.020;
    private const CAVITE_LAT_MAX = 14.520;
    private const CAVITE_LNG_MIN = 120.620;
    private const CAVITE_LNG_MAX = 121.100;

    // Staff/owner/admin profile page: photo, account details, identity
    // verification (treatment providers only) and password.
    public function edit(Request $request, IdentityVerificationService $identity): View
    {
        $user = $request->user()->loadMissing(['staff', 'latestIdentityVerification', 'branch', 'roles']);
        $canVerify = Gate::allows('submit-identity-verification');
        $latest = $user->latestIdentityVerification;

        return view('profile.edit', [
            'user'               => $user,
            'canVerify'          => $canVerify,
            'identityState'      => $canVerify ? $identity->stateFor($user) : null,
            'latestVerification' => $latest,
            'photoLocked'        => $user->isIdentityVerified() || ($latest?->status === IdentityVerification::STATUS_PENDING),
            'idTypes'            => config('identity.id_types', []),
            'uploadMaxMb'        => (int) round(config('identity.upload.max_kb', 5120) / 1024),
            'uploadMimes'        => config('identity.upload.mimes', []),
            'privacyContact'     => config('identity.privacy_contact'),
            'consentVersion'     => config('identity.consent_version'),
        ]);
    }

    // PATCH /profile — phone number only (decision Q25). Name and email are
    // managed by the spa; the legal name is part of identity verification.
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'phone' => $request->validated('phone') ?: null,
        ])->save();

        return Redirect::to(route('profile.edit').'#account')->with('success', 'Phone number saved.');
    }

    public function password(Request $request): RedirectResponse
    {
        // Same rules as registration and the mobile app (AppServiceProvider:
        // 8+ characters, upper and lower case, a number and a symbol).
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();

        // Same as the API: a temporary password can't be "changed" to itself.
        if (Hash::check($request->password, $user->password)) {
            return back()->withErrors([
                'password' => 'Your new password must be different from your current password.',
            ]);
        }

        $user->update([
            'password'                => Hash::make($request->password),
            'temp_password'           => null,    // clear the temporary password
            'password_reset_required' => false,   // unlock the rest of the system
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
