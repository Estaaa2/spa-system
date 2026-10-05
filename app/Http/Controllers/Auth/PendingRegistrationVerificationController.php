<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingRegistration;
use App\Services\PendingRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Throwable;

class PendingRegistrationVerificationController extends Controller
{
    private const MAX_OTP_ATTEMPTS = 5;
    private const RESEND_COOLDOWN_SECONDS = 60;

    public function notice(Request $request): View|RedirectResponse
    {
        $pending = $this->getPendingRegistration($request);

        if (!$pending) {
            if (Auth::check()) {
                return $this->redirectAuthenticatedUser();
            }

            return redirect()
                ->route($this->registrationRoute(
                    $request->session()->get(
                        'pending_registration_type',
                        'customer'
                    )
                ));
        }

        if ($pending->expires_at->isPast()) {
            $type = $pending->registration_type;

            $pending->delete();

            $this->clearPendingSession($request);

            return redirect()
                ->route($this->registrationRoute($type))
                ->with(
                    'warning',
                    'Your registration expired. Please register again.'
                );
        }

        return view(
            'auth.verify-registration',
            [
                'pendingRegistration' => $pending,
            ]
        );
    }

    public function verifyOtp(
        Request $request,
        PendingRegistrationService $registrations
    ): RedirectResponse {
        $data = $request->validate([
            'otp' => [
                'required',
                'digits:6',
            ],
        ], [
            'otp.required' => 'Enter the 6-digit verification code.',
            'otp.digits' => 'The verification code must contain exactly 6 digits.',
        ]);

        $pending = $this->getPendingRegistration($request);

        if (!$pending) {
            return redirect()->route('register');
        }

        if ($pending->expires_at->isPast()) {
            $type = $pending->registration_type;

            $pending->delete();
            $this->clearPendingSession($request);

            return redirect()
                ->route($this->registrationRoute($type))
                ->with(
                    'warning',
                    'Your registration expired. Please register again.'
                );
        }

        if (
            !$pending->otp_hash ||
            !$pending->otp_expires_at
        ) {
            return back()->withErrors([
                'otp' => 'No active verification code was found. Please request a new code.',
            ]);
        }

        if ($pending->otp_expires_at->isPast()) {
            return back()->withErrors([
                'otp' => 'This verification code has expired. Please request a new one.',
            ]);
        }

        if (
            $pending->otp_attempts >=
            self::MAX_OTP_ATTEMPTS
        ) {
            return back()->withErrors([
                'otp' => 'Too many incorrect attempts. Please request a new verification code.',
            ]);
        }

        if (
            !Hash::check(
                $data['otp'],
                $pending->otp_hash
            )
        ) {
            $pending->increment('otp_attempts');
            $pending->refresh();

            $remaining = max(
                0,
                self::MAX_OTP_ATTEMPTS -
                $pending->otp_attempts
            );

            return back()
                ->withInput()
                ->withErrors([
                    'otp' => $remaining > 0
                        ? "The verification code is incorrect. {$remaining} attempts remaining."
                        : 'Too many incorrect attempts. Please request a new verification code.',
                ]);
        }

        return $this->completeRegistration(
            $request,
            $registrations,
            $pending
        );
    }

    public function verifyLink(
        Request $request,
        PendingRegistration $pendingRegistration,
        PendingRegistrationService $registrations
    ): RedirectResponse {
        if ($pendingRegistration->expires_at->isPast()) {
            $type = $pendingRegistration->registration_type;

            $pendingRegistration->delete();

            return redirect()
                ->route($this->registrationRoute($type))
                ->with(
                    'warning',
                    'Your registration expired. Please register again.'
                );
        }

        return $this->completeRegistration(
            $request,
            $registrations,
            $pendingRegistration
        );
    }

    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->getPendingRegistration($request);

        if (!$pending) {
            return redirect()->route('register');
        }

        if ($pending->expires_at->isPast()) {
            $type = $pending->registration_type;

            $pending->delete();
            $this->clearPendingSession($request);

            return redirect()
                ->route($this->registrationRoute($type))
                ->with(
                    'warning',
                    'Your registration expired. Please register again.'
                );
        }

        if (
            $pending->otp_sent_at &&
            $pending->otp_sent_at->gt(
                now()->subSeconds(
                    self::RESEND_COOLDOWN_SECONDS
                )
            )
        ) {
            return back()->withErrors([
                'resend' => 'Please wait before requesting another verification email.',
            ]);
        }

        try {
            $pending->sendVerificationNotification();
        } catch (Throwable $e) {
            report($e);

            return back()->with(
                'warning',
                'We could not send the verification email. Please try again.'
            );
        }

        return back()->with(
            'status',
            'A new verification email and 6-digit code have been sent.'
        );
    }

    public function success(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('verification_success')) {
            return $this->redirectAuthenticatedUser();
        }

        return view('auth.verification-success', [
            'redirectUrl' => $request->session()->get(
                'verification_redirect',
                '/'
            ),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        if (Auth::check()) {
            $user = Auth::user();

            $redirectUrl =
                $user->hasRole('owner') && !$user->spa_id
                    ? route('setup.index')
                    : url('/');

            return response()->json([
                'verified' => true,
                'redirect_url' => $redirectUrl,
            ]);
        }

        return response()->json([
            'verified' => false,
        ]);
    }

    private function completeRegistration(
        Request $request,
        PendingRegistrationService $registrations,
        PendingRegistration $pending
    ): RedirectResponse {
        $type = $pending->registration_type;

        try {
            $user = $registrations->complete($pending);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'otp' => 'We could not complete your registration. Your real account has not been created. Please try again.',
            ]);
        }

        Auth::login($user);

        $request->session()->regenerate();

        $this->clearPendingSession($request);

        $redirectUrl = $type === 'business'
            ? route('setup.index')
            : url('/');

        return redirect()
            ->route('pending.verification.success')
            ->with([
                'verification_success' => true,
                'verification_redirect' => $redirectUrl,
            ]);
    }

    private function getPendingRegistration(
        Request $request
    ): ?PendingRegistration {
        $uuid = $request->session()->get(
            'pending_registration_uuid'
        );

        if (!$uuid) {
            return null;
        }

        return PendingRegistration::where(
            'uuid',
            $uuid
        )->first();
    }

    private function clearPendingSession(
        Request $request
    ): void {
        $request->session()->forget([
            'pending_registration_uuid',
            'pending_registration_type',
        ]);
    }

    private function registrationRoute(
        string $type
    ): string {
        return $type === 'business'
            ? 'register.business'
            : 'register';
    }

    private function redirectAuthenticatedUser(): RedirectResponse
    {
        $user = Auth::user();

        if ($user->hasRole('owner') && !$user->spa_id) {
            return redirect()->route('setup.index');
        }

        return redirect('/');
    }
}
