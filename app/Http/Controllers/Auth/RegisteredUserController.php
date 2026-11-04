<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PendingRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register-customer');
    }

    public function store(
        Request $request,
        PendingRegistrationService $registrations
    ): RedirectResponse {
        $data = $request->validate([
            'first_name' => [
                'required',
                'string',
                'max:100',
            ],
            'middle_name' => [
                'nullable',
                'string',
                'max:100',
            ],
            'last_name' => [
                'required',
                'string',
                'max:100',
            ],
            'suffix' => [
                'nullable',
                'string',
                'max:20',
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => [
                'required',
                'confirmed',
                Rules\Password::defaults(),
            ],
            'terms' => [
                'required',
                'accepted',
            ],
        ], [
            'terms.required' => 'You must accept the Terms and Conditions before registering.',
            'terms.accepted' => 'You must accept the Terms and Conditions before registering.',
        ]);

        $pending = $registrations->start(
            $data,
            'customer'
        );

        $request->session()->put([
            'pending_registration_uuid' => $pending->uuid,
            'pending_registration_type' => 'customer',
        ]);
        $request->session()->save();

        try {
            $pending->sendVerificationNotification();
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('pending.verification.notice')
                ->with(
                    'warning',
                    'Your registration information was saved temporarily, but the verification email could not be sent. Please try resending it.'
                );
        }

        return redirect()
            ->route('pending.verification.notice')
            ->with(
                'status',
                'We sent a verification link and 6-digit code to your email address.'
            );
    }
}
