{{-- Profile photo card. Variables: $user, $canVerify, $photoLocked, $uploadMaxMb (from ProfileController::edit). --}}
@php
    $roleKey = $user->getRoleNames()->first();
    $roleLabel = [
        'hr' => 'HR', 'hr_head' => 'HR Head', 'finance_head' => 'Finance Head',
    ][$roleKey] ?? ($roleKey ? ucwords(str_replace('_', ' ', $roleKey)) : null);
    $performsTreatments = (bool) $user->staff?->performs_treatments && ! $user->hasRole('therapist');
@endphp

<div id="photo" class="overflow-hidden bg-white border border-gray-200 shadow-sm scroll-mt-6 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
    <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Profile Photo</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            @if ($canVerify)
                Required for identity verification.
            @else
                Optional. Your initials show when there is no photo.
            @endif
        </p>
    </div>

    <div class="flex flex-col items-center px-4 py-6 text-center sm:px-6">
        <div class="relative">
            <div id="photoCurrent">
                <x-avatar :user="$user" size="xl" :alt="$user->hasProfilePhoto() ? 'Your profile photo' : ''" />
            </div>
            <img id="photoPreview" alt="Preview of your new photo"
                 class="hidden object-cover w-32 h-32 bg-gray-100 rounded-full dark:bg-gray-700 ring-4 ring-[#8B7355]/30">

            @if ($user->isIdentityVerified())
                <span class="absolute bottom-1 right-1 inline-flex items-center justify-center w-8 h-8 text-white bg-emerald-600 rounded-full ring-4 ring-white dark:ring-gray-800"
                      title="Identity verified">
                    <i class="text-sm fa-solid fa-check" aria-hidden="true"></i>
                    <span class="sr-only">Identity verified</span>
                </span>
            @endif
        </div>

        <p class="mt-4 text-base font-semibold text-gray-900 dark:text-white">{{ $user->name }}</p>
        <p class="text-sm text-gray-500 break-all dark:text-gray-400">{{ $user->email }}</p>

        <div class="flex flex-wrap items-center justify-center gap-2 mt-3">
            @if ($roleLabel)
                <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">{{ $roleLabel }}</span>
            @endif
            @if ($performsTreatments)
                <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">Performs treatments</span>
            @endif
            @if ($user->isIdentityVerified())
                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Identity verified
                </span>
            @endif
        </div>

        {{-- Server-side errors for this card --}}
        @if ($errors->photo->any())
            <p class="w-full p-3 mt-4 text-sm text-left text-red-700 border border-red-200 bg-red-50 rounded-xl dark:bg-red-900/10 dark:border-red-800 dark:text-red-300" role="alert">
                {{ $errors->photo->first() }}
            </p>
        @endif
        <p id="photoClientError" class="hidden w-full p-3 mt-4 text-sm text-left text-red-700 border border-red-200 bg-red-50 rounded-xl dark:bg-red-900/10 dark:border-red-800 dark:text-red-300" role="alert"></p>

        <form id="photoForm" method="POST" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data" class="w-full mt-5">
            @csrf
            <input type="file" id="photoInput" name="photo" class="sr-only"
                   accept="image/jpeg,image/png,image/webp">

            <div id="photoPendingActions" class="flex-col hidden gap-2 mb-2">
                <button type="button" id="photoCancel"
                        class="min-h-[44px] px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                    Cancel
                </button>
                <button type="button" id="photoSave"
                        class="min-h-[44px] rounded-xl bg-[#8B7355] px-4 py-2 text-sm font-medium text-white hover:bg-[#7A6348]">
                    <i class="mr-1 fa-solid fa-floppy-disk" aria-hidden="true"></i> Save photo
                </button>
            </div>

            <div class="flex flex-col gap-2">
                <label for="photoInput"
                       class="inline-flex items-center justify-center gap-2 min-h-[44px] px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 cursor-pointer rounded-xl hover:bg-gray-50 focus-within:ring-2 focus-within:ring-[#8B7355] dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600">
                    <i class="fa-solid fa-camera" aria-hidden="true"></i>
                    {{ $user->hasProfilePhoto() ? 'Choose a new photo' : 'Upload a photo' }}
                </label>

                @if ($user->hasProfilePhoto() && ! $photoLocked)
                    <button type="button" data-open-modal="removePhotoModal"
                            class="inline-flex items-center justify-center gap-2 min-h-[44px] px-4 py-2 text-sm font-medium text-red-600 rounded-xl hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">
                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove
                    </button>
                @endif
            </div>
        </form>

        <div class="w-full mt-5 space-y-2 text-xs text-left text-gray-500 dark:text-gray-400">
            <p>JPG, PNG or WebP, up to {{ $uploadMaxMb }} MB. A clear, recent photo of your face, looking at the camera, without filters or sunglasses.</p>
            <p>
                {{ $canVerify
                    ? 'Seen by staff of your spa, Levictas admins, and customers with an upcoming booking with you, so they can recognise you when you arrive.'
                    : 'Seen by staff of your spa and Levictas admins.' }}
                Location data in the photo is removed when it is saved.
            </p>
            @if ($photoLocked)
                <p class="text-amber-700 dark:text-amber-300">
                    <i class="mr-1 fa-solid fa-lock" aria-hidden="true"></i>
                    This photo is part of your identity verification. You can replace it, but you can't remove it.
                </p>
            @endif
        </div>
    </div>
</div>
