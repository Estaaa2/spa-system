<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;


class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'first_name'  => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name'   => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email',
            'password'    => 'required|min:6|confirmed',
        ]);

        $user = User::create([
            'first_name'  => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name'   => $request->last_name,
            'email'       => $request->email,
            'password'    => Hash::make($request->password),
        ]);

        $role = Role::firstOrCreate(
            ['name' => 'customer', 'guard_name' => 'web']
        );
        $user->assignRole($role);

        $otp = rand(100000, 999999);
        Cache::put('email_otp_' . $user->email, $otp, now()->addMinutes(10));

        Mail::raw("Your verification code is: $otp\n\nThis code expires in 10 minutes.", function ($message) use ($user) {
            $message->to($user->email)
                    ->subject('Verify Your Email - Esta\'s Spa');
        });

        return response()->json([
            'message' => 'Registration successful. Please check your email for the verification code.',
            'email'   => $user->email,
        ], 201);
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|digits:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $cachedOtp = Cache::get('email_otp_' . $request->email);

        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }

        $user->markEmailAsVerified();
        Cache::forget('email_otp_' . $request->email);
        Cache::forget('email_otp_' . $request->email);

        $user->tokens()->delete();
        $user->tokens()->delete();
        $token = $user->createToken('mobile_app')->plainTextToken;

        return response()->json([
            'user'  => $this->formatUser($user),
            'token' => $token,
        ]);
    }

    public function resendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $otp = rand(100000, 999999);
        Cache::put('email_otp_' . $user->email, $otp, now()->addMinutes(10));

        Mail::raw("Your new verification code is: $otp\n\nThis code expires in 10 minutes.", function ($message) use ($user) {
            $message->to($user->email)
                    ->subject('New Verification Code - Esta\'s Spa');
        });

        return response()->json(['message' => 'A new verification code has been sent to your email.']);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        // ✅ Block roles not allowed on mobile (only customer & therapist)
        $allowedRoles = ['customer', 'therapist'];
        $userRole = $user->getRoleNames()->first();

        if (!$userRole || !in_array($userRole, $allowedRoles)) {
            return response()->json([
                'message'           => 'Access denied. This app is for customers and therapists only.',
                'unauthorized_role' => true,
            ], 403);
        }

        // ✅ Block unverified customers
        if ($user->hasRole('customer') && !$user->hasVerifiedEmail()) {
            $otp = rand(100000, 999999);
            Cache::put('email_otp_' . $user->email, $otp, now()->addMinutes(10));
            Mail::raw("Your verification code is: $otp\n\nThis code expires in 10 minutes.", function ($message) use ($user) {
                $message->to($user->email)->subject('Verify Your Email - Esta\'s Spa');
            });

            return response()->json([
                'message'               => 'Please verify your email first. A new code has been sent.',
                'requires_verification' => true,
                'email'                 => $user->email,
            ], 403);
        }

        if ($user->getRoleNames()->isEmpty()) {
            $role = Role::where('name', 'customer')->first();
            if ($role) $user->assignRole($role);
            if ($role) $user->assignRole($role);
        }

        $user->tokens()->delete();
        $token = $user->createToken('mobile_app')->plainTextToken;

        return response()->json([
            'user'  => $this->formatUser($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        return response()->json($this->formatUser($request->user()));
    }

    private function formatUser(User $user): array
    {
        $role = $user->getRoleNames()->first() ?? 'customer';
        return [
            'id'          => $user->id,
            'first_name'  => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name'   => $user->last_name,
            'full_name'   => $user->name,
            'email'       => $user->email,
            'role'        => $role,
            'spa_id'      => $user->spa_id,
            'branch_id'   => $user->branch_id,
            'is_owner'    => $user->is_owner,
            'is_verified' => $user->hasVerifiedEmail(),
            'address'     => $user->address,
            'latitude'    => $user->latitude,
            'longitude'   => $user->longitude,
        ];
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'first_name'  => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name'   => 'required|string|max:255',
            'address'     => 'nullable|string|max:255',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
        ]);

        $user->update([
            'first_name'  => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name'   => $request->last_name,
            'address'     => $request->address,
            'latitude'    => $request->latitude,
            'longitude'   => $request->longitude,
        ]);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user'    => $this->formatUser($user->fresh()),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:6|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['message' => 'Password updated successfully.']);
    }
}
