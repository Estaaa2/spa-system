<?php

namespace App\Http\Requests\Identity;

use App\Models\IdentityVerification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff identity verification submission (web form in Phase 2, API in Phase 6).
 * Business rules (one pending request, photo required, pool membership) are
 * enforced again inside IdentityVerificationService::submit().
 */
class SubmitIdentityVerificationRequest extends FormRequest
{
    /** Web form errors show inside their own card on the profile page (JSON is unaffected). */
    protected $errorBag = 'identity';

    public function authorize(): bool
    {
        return $this->user()?->can('submit-identity-verification') ?? false;
    }

    public function rules(): array
    {
        $image = ['required', 'file', 'image', 'mimes:'.implode(',', config('identity.upload.mimes')), 'max:'.config('identity.upload.max_kb')];

        return [
            'id_type'  => ['required', 'string', Rule::in(IdentityVerification::idTypeKeys())],
            'id_image' => $image,
            'selfie'   => $image,
            'consent'  => ['accepted'],
        ];
    }

    public function messages(): array
    {
        $mb = (int) round(config('identity.upload.max_kb') / 1024);

        return [
            'id_type.in'       => 'Choose one of the accepted IDs.',
            'id_image.required' => 'Take or upload a photo of your ID.',
            'selfie.required'  => 'Take or upload a selfie holding your ID.',
            'id_image.max'     => "The ID photo must be {$mb} MB or smaller.",
            'selfie.max'       => "The selfie must be {$mb} MB or smaller.",
            'id_image.mimes'   => 'Use a JPG, PNG or WebP photo.',
            'selfie.mimes'     => 'Use a JPG, PNG or WebP photo.',
            'consent.accepted' => 'Tick the box to agree before submitting.',
        ];
    }
}
