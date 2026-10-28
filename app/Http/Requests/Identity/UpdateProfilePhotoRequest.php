<?php

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

/** Upload or replace the signed-in user's profile photo (Phase 2 web, Phase 6/7 API). */
class UpdateProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'image', 'mimes:'.implode(',', config('identity.upload.mimes')), 'max:'.config('identity.upload.max_kb')],
        ];
    }

    public function messages(): array
    {
        $mb = (int) round(config('identity.upload.max_kb') / 1024);

        return [
            'photo.required' => 'Choose a photo to upload.',
            'photo.max'      => "The photo must be {$mb} MB or smaller.",
            'photo.mimes'    => 'Use a JPG, PNG or WebP photo.',
            'photo.image'    => 'Use a JPG, PNG or WebP photo.',
        ];
    }
}
