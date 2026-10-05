<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EmailVerificationOtpController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'otp' => ['required', 'digits:6'],
        ], [
            'otp.required' => 'Enter the 6-digit verification code.',
            'otp.digits' => 'The verification code must contain exactly 6 digits.',
        ]);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect('/');
        }

        if (
            !$user->email_verification_otp_hash ||
            !$user->email_verification_otp_expires_at
        ) {
            throw ValidationException::withMessages([
                'otp' => 'No active verification code was found. Please request a new code.',
            ]);
        }

        if (now()->greaterThan($user->email_verification_otp_expires_at)) {
            throw ValidationException::withMessages([
                'otp' => 'This verification code has expired. Please request a new one.',
            ]);
        }

        if ($user->email_verification_otp_attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'otp' => 'Too many incorrect attempts. Please request a new verification code.',
            ]);
        }

        if (!Hash::check($data['otp'], $user->email_verification_otp_hash)) {
            $user->increment('email_verification_otp_attempts');
            $user->refresh();

            $remaining = max(
                0,
                self::MAX_ATTEMPTS - $user->email_verification_otp_attempts
            );

            throw ValidationException::withMessages([
                'otp' => $remaining > 0
                    ? "The verification code is incorrect. {$remaining} attempts remaining."
                    : 'Too many incorrect attempts. Please request a new verification code.',
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect('/')
            ->with('success', 'Your email address has been verified successfully.');
    }
}
