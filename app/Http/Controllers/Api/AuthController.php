<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureMobileAccess;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\PendingRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AuthController extends Controller
{
    // Same limits as the web's PendingRegistrationVerificationController.
    private const MAX_OTP_ATTEMPTS = 5;
    private const RESEND_COOLDOWN_SECONDS = 60;

    // Same limit as the web's LoginRequest.
    private const MAX_LOGIN_ATTEMPTS = 5;

    // ── Registration (Step 1) ────────────────────────────────────────────────

    public function register(Request $request, PendingRegistrationService $registrations)
    {
        // Same rules and messages as the web's RegisteredUserController::store().
        $data = $request->validate([
            'first_name'  => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name'   => ['required', 'string', 'max:100'],
            'suffix'      => ['nullable', 'string', 'max:20'],
            'email'       => ['required', 'string', 'lowercase', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password'    => ['required', 'confirmed', Password::defaults()],
            'terms'       => ['required', 'accepted'],
        ], [
            'terms.required' => 'You must accept the Terms and Conditions before registering.',
            'terms.accepted' => 'You must accept the Terms and Conditions before registering.',
        ]);

        // No users row yet. It is created only after the code is verified.
        $pending = $registrations->start($data, 'customer');

        try {
            $pending->sendVerificationNotification();
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message'               => 'Your registration was saved, but the verification email could not be sent. Tap Resend to try again.',
                'email'                 => $pending->email,
                'requires_verification' => true,
                'email_failed'          => true,
            ], 201);
        }

        return response()->json([
            'message'               => 'We sent a 6-digit code to your email address.',
            'email'                 => $pending->email,
            'requires_verification' => true,
        ], 201);
    }

    public function verifyEmail(Request $request, PendingRegistrationService $registrations)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'otp'   => ['required', 'digits:6'],
        ], [
            'otp.required' => 'Enter the 6-digit verification code.',
            'otp.digits'   => 'The verification code must contain exactly 6 digits.',
        ]);

        $email   = strtolower($request->email);
        $otp     = (string) $request->otp;
        $pending = PendingRegistration::where('email', $email)->first();

        if (! $pending) {
            return $this->verifyWithoutPending($email, $otp);
        }

        if ($pending->expires_at->isPast()) {
            $pending->delete();

            return $this->registrationExpired();
        }

        if (! $pending->otp_hash || ! $pending->otp_expires_at) {
            return response()->json([
                'message' => 'No active verification code was found. Please request a new code.',
            ], 422);
        }

        if ($pending->otp_expires_at->isPast()) {
            return response()->json([
                'message' => 'This verification code has expired. Please request a new one.',
            ], 422);
        }

        if ($pending->otp_attempts >= self::MAX_OTP_ATTEMPTS) {
            return response()->json([
                'message' => 'Too many incorrect attempts. Please request a new verification code.',
            ], 422);
        }

        if (! Hash::check($otp, $pending->otp_hash)) {
            $pending->increment('otp_attempts');
            $pending->refresh();

            $remaining = max(0, self::MAX_OTP_ATTEMPTS - $pending->otp_attempts);

            return response()->json([
                'message' => $remaining > 0
                    ? "The verification code is incorrect. {$remaining} attempts remaining."
                    : 'Too many incorrect attempts. Please request a new verification code.',
            ], 422);
        }

        try {
            $user = $registrations->complete($pending);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'We could not complete your registration. Your account has not been created. Please try again.',
            ], 422);
        }

        return $this->issueToken($user);
    }

    public function resendOtp(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $email   = strtolower($request->email);
        $pending = PendingRegistration::where('email', $email)->first();

        if ($pending) {
            if ($pending->expires_at->isPast()) {
                $pending->delete();

                return $this->registrationExpired();
            }

            if (
                $pending->otp_sent_at &&
                $pending->otp_sent_at->gt(now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))
            ) {
                return response()->json([
                    'message' => 'Please wait a minute before requesting another code.',
                ], 429);
            }

            try {
                $pending->sendVerificationNotification();
            } catch (Throwable $e) {
                report($e);

                return response()->json([
                    'message' => 'We could not send the verification email. Please try again.',
                ], 503);
            }

            return response()->json([
                'message' => 'A new 6-digit code has been sent to your email.',
            ]);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return $this->registrationExpired();
        }

        if ($user->hasVerifiedEmail()) {
            return $this->alreadyVerified();
        }

        // Account made by the old mobile flow.
        try {
            $this->sendLegacyOtp($user);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'We could not send the verification email. Please try again.',
            ], 503);
        }

        return response()->json([
            'message' => 'A new 6-digit code has been sent to your email.',
        ]);
    }

    // ── Login (Step 2) ───────────────────────────────────────────────────────

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
            'platform' => 'required|in:web,mobile',
        ]);

        // Same as the web's LoginRequest: 5 failed tries per email + IP.
        $throttleKey = Str::transliterate(Str::lower($request->email) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ], 429);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey);

            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        RateLimiter::clear($throttleKey);

        // Restrict roles per platform
        $allowedRoles = [
            'web'    => ['owner', 'admin', 'manager', 'customer', 'receptionist', 'therapist'],
            'mobile' => ['customer', 'therapist'],
        ];

        $userRole = $user->getRoleNames()->first();

        if (! in_array($userRole, $allowedRoles[$request->platform])) {
            return response()->json([
                'message'           => 'Access denied for this platform.',
                'unauthorized_role' => true,
            ], 403);
        }

        // Same as the web: every role must verify its email first.
        if (! $user->hasVerifiedEmail()) {
            try {
                $this->sendLegacyOtp($user);
            } catch (Throwable $e) {
                report($e);
            }

            return response()->json([
                'message'               => 'Please verify your email first. A new code has been sent.',
                'requires_verification' => true,
                'email'                 => $user->email,
            ], 403);
        }

        // Therapist at a locked spa or branch: no token, the app shows a popup.
        $lock = EnsureMobileAccess::lockFor($user);

        if ($lock !== null) {
            return response()->json(EnsureMobileAccess::lockedPayload($lock), 423);
        }

        // A temporary password still logs in. formatUser() returns
        // password_reset_required, and EnsureMobileAccess limits what the
        // token can do until the password is changed.
        return $this->issueToken($user);
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

    // ── Profile and password ─────────────────────────────────────────────────

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
            'current_password' => ['required'],
            'new_password'     => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        // Otherwise a therapist could "change" the temporary password to itself.
        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'message' => 'Your new password must be different from your current password.',
            ], 422);
        }

        $user->forceFill([
            'password'                => Hash::make($request->new_password),
            'password_reset_required' => false,
        ])->save();

        return response()->json([
            'message' => 'Password updated successfully.',
            'user'    => $this->formatUser($user->fresh()),
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $generic = ['message' => 'If that email exists, a reset code has been sent.'];

        $user = User::where('email', $request->email)->first();

        // Don't reveal whether the email exists.
        if (! $user) {
            return response()->json($generic);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put('password_reset_otp_' . $user->email, $otp, now()->addMinutes(10));
        Cache::forget('password_reset_attempts_' . $user->email);

        $brand = $this->brandFor($user);

        try {
            Mail::raw(
                "Your $brand password reset code is: $otp\n\nThis code expires in 10 minutes.",
                function ($message) use ($user, $brand) {
                    $message->to($user->email)->subject("Reset Your Password - $brand");
                }
            );
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json($generic);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'        => ['required', 'email'],
            'otp'          => ['required', 'digits:6'],
            'new_password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $invalid = response()->json(['message' => 'Invalid or expired reset code.'], 422);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return $invalid;
        }

        $otpKey      = 'password_reset_otp_' . $user->email;
        $attemptsKey = 'password_reset_attempts_' . $user->email;
        $cachedOtp   = Cache::get($otpKey);

        if (! $cachedOtp) {
            return $invalid;
        }

        if ((string) $cachedOtp !== (string) $request->otp) {
            $attempts = (int) Cache::get($attemptsKey, 0) + 1;
            Cache::put($attemptsKey, $attempts, now()->addMinutes(10));

            if ($attempts >= self::MAX_OTP_ATTEMPTS) {
                Cache::forget($otpKey);
                Cache::forget($attemptsKey);

                return response()->json([
                    'message' => 'Too many incorrect attempts. Please request a new reset code.',
                ], 422);
            }

            return $invalid;
        }

        // A reset is a password the user chose, so it also ends a forced change.
        $user->forceFill([
            'password'                => Hash::make($request->new_password),
            'password_reset_required' => false,
        ])->save();

        $user->tokens()->delete(); // Sign out every device.

        Cache::forget($otpKey);
        Cache::forget($attemptsKey);

        return response()->json(['message' => 'Password reset successfully.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * No pending registration for this email. Either the customer already
     * finished through the link in the email, or the account was made by the
     * old mobile flow (users row exists but is unverified).
     */
    private function verifyWithoutPending(string $email, string $otp)
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return $this->registrationExpired();
        }

        if ($user->hasVerifiedEmail()) {
            return $this->alreadyVerified();
        }

        // Old flow: the code is kept in the cache.
        $cacheKey  = 'email_otp_' . $user->email;
        $cachedOtp = Cache::get($cacheKey);

        if (! $cachedOtp || (string) $cachedOtp !== $otp) {
            return response()->json([
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        $user->markEmailAsVerified();
        Cache::forget($cacheKey);

        return $this->issueToken($user);
    }

    /** Verification code for accounts made by the old mobile flow. */
    private function sendLegacyOtp(User $user): void
    {
        $otp   = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $brand = $this->brandFor($user);

        Cache::put('email_otp_' . $user->email, $otp, now()->addMinutes(10));

        Mail::raw(
            "Your $brand verification code is: $otp\n\nThis code expires in 10 minutes.",
            function ($message) use ($user, $brand) {
                $message->to($user->email)->subject("Verify Your Email - $brand");
            }
        );
    }

    /**
     * Name used in emails: Levictas for customers, the spa's own name for
     * staff accounts that the owner or HR created.
     */
    private function brandFor(User $user): string
    {
        if ($user->hasRole('customer') || ! $user->spa_id) {
            return 'Levictas';
        }

        return $user->spa?->name ?: 'Levictas';
    }

    private function issueToken(User $user)
    {
        $user->tokens()->delete();
        $token = $user->createToken('mobile_app')->plainTextToken;

        return response()->json([
            'user'  => $this->formatUser($user),
            'token' => $token,
        ]);
    }

    private function registrationExpired()
    {
        return response()->json([
            'message'              => 'Your registration expired or was not found. Please register again.',
            'registration_expired' => true,
        ], 410);
    }

    private function alreadyVerified()
    {
        return response()->json([
            'message'          => 'Your email is already verified. Please log in.',
            'already_verified' => true,
        ], 409);
    }

    private function formatUser(User $user): array
    {
        $role = $user->getRoleNames()->first() ?? 'customer';

        return [
            'brand_name'              => $this->brandFor($user),
            'id'                      => $user->id,
            'first_name'              => $user->first_name,
            'middle_name'             => $user->middle_name,
            'last_name'               => $user->last_name,
            'suffix'                  => $user->suffix,
            'full_name'               => $user->name,
            'email'                   => $user->email,
            'phone'                   => $user->phone,
            'role'                    => $role,
            'spa_id'                  => $user->spa_id,
            'branch_id'               => $user->branch_id,
            'is_owner'                => $user->is_owner,
            'is_verified'             => $user->hasVerifiedEmail(),
            'password_reset_required' => (bool) $user->password_reset_required,
            'address'                 => $user->address,
            'latitude'                => $user->latitude,
            'longitude'               => $user->longitude,
        ];
    }
}
