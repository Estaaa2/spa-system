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
            'privacyContact'     => \App\Support\Identity\ConsentNotice::privacyContact(),
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

    /**
     * Customer profile window (UI rework Phase 5). Each row of the window
     * (name, phone, home location) is edited on its own, so a request may send
     * just one `section` and only that section is validated and saved. A
     * request without `section` saves everything, as before.
     *
     * The window posts with fetch and gets JSON back; a plain form post still
     * redirects back with the named error bag.
     */
    public function updateCustomer(Request $request)
    {
        $user = $request->user();

        $rules = [
            'name'     => [
                'first_name'  => 'required|string|max:255',
                'middle_name' => 'nullable|string|max:255',
                'last_name'   => 'required|string|max:255',
                'suffix'      => 'nullable|string|max:20',
            ],
            'phone'    => [
                'phone' => ['nullable', 'string', 'regex:/^09\d{9}$/'],
            ],
            'location' => [
                'address'   => 'nullable|string|max:255',
                'latitude'  => 'nullable|numeric|between:-90,90|required_with:longitude',
                'longitude' => 'nullable|numeric|between:-180,180|required_with:latitude',
            ],
        ];

        $section = $request->input('section');
        if ($section !== null && ! array_key_exists($section, $rules)) {
            abort(422, 'Unknown profile section.');
        }
        $sectionRules = $section ? $rules[$section] : array_merge(...array_values($rules));

        $messages = ['phone.regex' => 'Enter a valid mobile number (09xxxxxxxxx).'];

        $validated = $request->expectsJson()
            ? $request->validate($sectionRules, $messages)
            : $request->validateWithBag(self::CUSTOMER_PROFILE_ERROR_BAG, $sectionRules, $messages);

        if (array_key_exists('latitude', $sectionRules) && $request->filled('latitude') && $request->filled('longitude')
            && ! $this->withinCavite((float) $validated['latitude'], (float) $validated['longitude'])) {
            $message = 'Pinned location must be within Cavite. Please adjust the pin and try again.';

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'errors' => ['latitude' => [$message]]], 422)
                : back()->withErrors(['latitude' => $message], self::CUSTOMER_PROFILE_ERROR_BAG)->withInput();
        }

        // Name: once every 30 days, with 24 hours to fix a typo (User::guardNameChange).
        if (array_key_exists('first_name', $sectionRules)) {
            $refused = $user->guardNameChange($validated['first_name'], $validated['middle_name'] ?? null, $validated['last_name'], $validated['suffix'] ?? null);
            if ($refused) {
                return $request->expectsJson()
                    ? response()->json(['message' => $refused, 'errors' => ['first_name' => [$refused]]], 422)
                    : back()->withErrors(['first_name' => $refused], self::CUSTOMER_PROFILE_ERROR_BAG)->withInput();
            }
        }

        // Only the fields of the section that was sent; empty optional fields clear.
        $user->fill(collect($sectionRules)->keys()
            ->mapWithKeys(fn ($field) => [$field => $validated[$field] ?? null])
            ->all())->save();

        if ($request->expectsJson()) {
            $fresh = $user->fresh();

            return response()->json([
                'message' => 'Profile updated.',
                'user'    => [
                    'name'        => $fresh->name,
                    'first_name'  => $fresh->first_name,
                    'middle_name' => $fresh->middle_name,
                    'last_name'   => $fresh->last_name,
                    'suffix'      => $fresh->suffix,
                    'phone'       => $fresh->phone,
                    'address'     => $fresh->address,
                    'latitude'    => $fresh->latitude !== null ? (float) $fresh->latitude : null,
                    'longitude'   => $fresh->longitude !== null ? (float) $fresh->longitude : null,
                ] + self::nameRule($fresh),
            ]);
        }

        return back()->with('success', 'Profile updated successfully!');
    }

    public function updateCustomerApi(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name'  => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name'   => 'required|string|max:255',
            'suffix'      => 'nullable|string|max:20',
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

        // Same 30-day name rule as the web, so the app can't be used to bypass it.
        if ($refused = $user->guardNameChange($validated['first_name'], $validated['middle_name'] ?? null, $validated['last_name'], $request->has('suffix') ? ($validated['suffix'] ?? null) : $user->suffix)) {
            return response()->json(['message' => $refused, 'errors' => ['first_name' => [$refused]]], 422);
        }

        $user->fill([
            'first_name'  => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name'   => $validated['last_name'],
            // Only when the app sends it, so an older app build can't clear a saved suffix.
            'suffix'      => $request->has('suffix') ? ($validated['suffix'] ?? null) : $user->suffix,
            'phone'       => $validated['phone'] ?? null,
            'address'     => $validated['address'] ?? null,
            'latitude'    => $validated['latitude'] ?? null,
            'longitude'   => $validated['longitude'] ?? null,
        ])->save();

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
            ] + self::nameRule($fresh),
        ]);
    }

    /**
     * When the customer can next change their name, for the profile window and
     * the app: name_locked_until is null when they can change it now;
     * name_correction_until is set while a just-made change can still be fixed.
     */
    public static function nameRule(\App\Models\User $user): array
    {
        $correction = $user->nameCorrectionUntil();

        return [
            'name_locked_until'     => $user->nameLockedUntil()?->toIso8601String(),
            'name_correction_until' => $correction && now()->lt($correction) ? $correction->toIso8601String() : null,
            'name_change_days'      => \App\Models\User::NAME_CHANGE_DAYS,
        ];
    }

    private function withinCavite(float $lat, float $lng): bool
    {
        return $lat >= self::CAVITE_LAT_MIN && $lat <= self::CAVITE_LAT_MAX
            && $lng >= self::CAVITE_LNG_MIN && $lng <= self::CAVITE_LNG_MAX;
    }
}
