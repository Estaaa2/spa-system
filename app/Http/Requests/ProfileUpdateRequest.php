<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /profile — what a signed-in staff/owner/admin may change about
 * themselves on the profile page: the phone number only (decision Q25).
 * Name and email are managed by the spa; the legal name is also part of
 * identity verification (Q9).
 */
class ProfileUpdateRequest extends FormRequest
{
    /** Errors show under the Account information card, not elsewhere on the page. */
    protected $errorBag = 'account';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'regex:/^09\d{9}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter an 11-digit mobile number that starts with 09 (09xxxxxxxxx).',
        ];
    }
}
