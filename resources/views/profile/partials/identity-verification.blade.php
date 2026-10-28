{{--
    Identity verification card. Only for treatment providers (therapist role or
    "Performs treatments"); see IdentityPolicy::submit.
    Variables: $user, $identityState, $latestVerification, $idTypes, $uploadMaxMb,
    $privacyContact, $consentVersion.
--}}
@use('App\Services\Identity\IdentityVerificationService', 'IVS')
@php
    $state = $identityState;
    $latest = $latestVerification;
    $showForm = in_array($state, [IVS::STATE_NOT_STARTED, IVS::STATE_REJECTED, IVS::STATE_NEEDS_REVERIFICATION, IVS::STATE_WITHDRAWN], true);
    $hasPhoto = $user->hasProfilePhoto();
    $date = fn ($at) => $at?->timezone(config('app.timezone'))->format('M j, Y g:i A');
    $iErr = $errors->identity;

    $reverifyReason = match ($latest?->superseded_reason) {
        'photo_changed' => 'Your profile photo changed after you were verified.',
        'name_changed'  => 'Your name changed after you were verified.',
        default         => 'Something changed after you were verified.',
    };
@endphp

<div id="identity" class="overflow-hidden bg-white border border-gray-200 shadow-sm scroll-mt-6 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
    <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Identity Verification</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">Needed before you can be assigned online home-service bookings.</p>
    </div>

    <div class="px-4 py-6 space-y-6 sm:px-6">

        {{-- ── Current status ── --}}
        @switch($state)
            @case(IVS::STATE_APPROVED)
                <div class="flex flex-col gap-4 p-4 border border-emerald-200 bg-emerald-50 rounded-2xl sm:flex-row sm:items-start sm:justify-between dark:bg-emerald-900/10 dark:border-emerald-800" role="status">
                    <div class="flex items-start gap-3">
                        <i class="mt-0.5 fa-solid fa-shield-halved text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i>
                        <div>
                            <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">Verified</p>
                            <p class="mt-0.5 text-sm text-emerald-700 dark:text-emerald-300">
                                Verified on {{ $date($user->identity_verified_at) }}. Customers who book home service with you see your photo, your name and a verified badge.
                            </p>
                        </div>
                    </div>
                    <button type="button" data-open-modal="withdrawModal"
                            class="shrink-0 min-h-[44px] px-4 py-2 text-sm font-medium text-red-600 bg-white border border-red-200 rounded-xl hover:bg-red-50 dark:bg-gray-800 dark:text-red-400 dark:border-red-800 dark:hover:bg-red-900/20">
                        Withdraw consent
                    </button>
                </div>
                @break

            @case(IVS::STATE_PENDING)
                <div class="flex flex-col gap-4 p-4 border border-blue-200 bg-blue-50 rounded-2xl sm:flex-row sm:items-start sm:justify-between dark:bg-blue-900/10 dark:border-blue-800" role="status">
                    <div class="flex items-start gap-3">
                        <i class="mt-0.5 fa-solid fa-hourglass-half text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
                        <div>
                            <p class="text-sm font-semibold text-blue-800 dark:text-blue-300">Waiting for review</p>
                            <p class="mt-0.5 text-sm text-blue-700 dark:text-blue-300">
                                Submitted {{ $date($latest?->submitted_at) }} with your {{ $latest?->idTypeLabel() }}.
                                A Levictas admin will compare your ID, selfie and profile photo. Your ID and selfie are deleted once they decide.
                            </p>
                        </div>
                    </div>
                    <button type="button" data-open-modal="withdrawModal"
                            class="shrink-0 min-h-[44px] px-4 py-2 text-sm font-medium text-red-600 bg-white border border-red-200 rounded-xl hover:bg-red-50 dark:bg-gray-800 dark:text-red-400 dark:border-red-800 dark:hover:bg-red-900/20">
                        Cancel request
                    </button>
                </div>
                @break

            @case(IVS::STATE_REJECTED)
                <div class="flex items-start gap-3 p-4 border border-red-200 bg-red-50 rounded-2xl dark:bg-red-900/10 dark:border-red-800" role="status">
                    <i class="mt-0.5 fa-solid fa-circle-xmark text-red-600 dark:text-red-400" aria-hidden="true"></i>
                    <div>
                        <p class="text-sm font-semibold text-red-800 dark:text-red-300">Not approved</p>
                        <p class="mt-0.5 text-sm text-red-700 dark:text-red-300">Reviewed {{ $date($latest?->reviewed_at) }}. Reason from the reviewer:</p>
                        <p class="p-3 mt-2 text-sm text-gray-800 bg-white border border-red-100 rounded-xl dark:bg-gray-800 dark:text-gray-200 dark:border-red-900">{{ $latest?->rejection_reason }}</p>
                        <p class="mt-2 text-sm text-red-700 dark:text-red-300">Fix the issue and submit again below.</p>
                    </div>
                </div>
                @break

            @case(IVS::STATE_NEEDS_REVERIFICATION)
                <div class="flex items-start gap-3 p-4 border border-amber-200 bg-amber-50 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800" role="status">
                    <i class="mt-0.5 fa-solid fa-rotate text-amber-600 dark:text-amber-400" aria-hidden="true"></i>
                    <div>
                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Verify again</p>
                        <p class="mt-0.5 text-sm text-amber-700 dark:text-amber-300">
                            {{ $reverifyReason }} Submit your ID and selfie again to receive online home-service bookings.
                        </p>
                    </div>
                </div>
                @break

            @case(IVS::STATE_WITHDRAWN)
                <div class="flex items-start gap-3 p-4 border border-gray-200 bg-gray-50 rounded-2xl dark:bg-gray-900/40 dark:border-gray-700" role="status">
                    <i class="mt-0.5 fa-solid fa-circle-info text-gray-500 dark:text-gray-400" aria-hidden="true"></i>
                    <div>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Consent withdrawn</p>
                        <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                            You withdrew consent on {{ $date($latest?->consent_withdrawn_at) }}. You can verify again at any time.
                        </p>
                    </div>
                </div>
                @break

            @default
                <div class="flex items-start gap-3 p-4 border border-gray-200 bg-gray-50 rounded-2xl dark:bg-gray-900/40 dark:border-gray-700" role="status">
                    <i class="mt-0.5 fa-solid fa-id-card text-gray-500 dark:text-gray-400" aria-hidden="true"></i>
                    <div>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Not verified yet</p>
                        <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                            You can still work in-branch and take walk-in and staff-made bookings. Online home-service bookings are only given to verified staff,
                            so customers can check who arrives at their door.
                        </p>
                    </div>
                </div>
        @endswitch

        {{-- ── Submission form ── --}}
        @if ($showForm)
            @if ($iErr->any())
                <div class="p-4 text-sm text-red-700 border border-red-200 bg-red-50 rounded-2xl dark:bg-red-900/10 dark:border-red-800 dark:text-red-300" role="alert">
                    <p class="font-semibold">Please fix the following:</p>
                    <ul class="mt-1 list-disc list-inside">
                        @foreach ($iErr->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @unless ($hasPhoto)
                <div class="flex items-start gap-3 p-4 border border-amber-200 bg-amber-50 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800">
                    <i class="mt-0.5 fa-solid fa-camera text-amber-600 dark:text-amber-400" aria-hidden="true"></i>
                    <p class="text-sm text-amber-800 dark:text-amber-300">
                        <a href="#photo" class="font-semibold underline underline-offset-2">Upload a profile photo</a> first. The admin checks that your profile photo, your ID and your selfie show the same person.
                    </p>
                </div>
            @endunless

            <form method="POST" action="{{ route('profile.identity.store') }}" enctype="multipart/form-data" data-submit-once>
                @csrf
                <fieldset @disabled(! $hasPhoto) class="space-y-6 disabled:opacity-60">
                    <legend class="sr-only">Submit your ID and selfie</legend>

                    {{-- Step 1: ID type --}}
                    <div>
                        <label for="id_type" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                            <span class="font-semibold text-[#8B7355] dark:text-[#C4A97D]">1.</span> Which ID will you use?
                        </label>
                        <select id="id_type" name="id_type" required
                                class="w-full sm:max-w-md min-h-[44px] px-3 py-2 text-sm border rounded-xl dark:bg-gray-700 dark:text-white {{ $iErr->has('id_type') ? 'border-red-500' : 'border-gray-300 dark:border-gray-600' }}">
                            <option value="">Choose an ID</option>
                            @foreach ($idTypes as $key => $label)
                                <option value="{{ $key }}" @selected(old('id_type') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">A valid Philippine government ID with your photo and name.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        {{-- Step 2: ID photo --}}
                        <div>
                            <label for="id_image" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <span class="font-semibold text-[#8B7355] dark:text-[#C4A97D]">2.</span> Photo of your ID (front)
                            </label>
                            <div class="relative flex items-center justify-center overflow-hidden bg-gray-100 border border-gray-300 border-dashed rounded-2xl aspect-[3/2] dark:bg-gray-700 dark:border-gray-600">
                                <img id="idPreview" alt="Preview of your ID photo" class="absolute inset-0 z-10 hidden object-contain w-full h-full bg-gray-100 dark:bg-gray-700">
                                <i class="text-3xl text-gray-300 fa-solid fa-id-card dark:text-gray-500" aria-hidden="true"></i>
                            </div>
                            <input type="file" id="id_image" name="id_image" required accept="image/jpeg,image/png,image/webp"
                                   aria-describedby="idTips"
                                   class="block w-full mt-3 text-sm text-gray-600 dark:text-gray-300 file:mr-3 file:min-h-[44px] file:px-4 file:py-2 file:rounded-xl file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 dark:file:bg-gray-700 dark:file:text-white">
                            <p id="idClientError" class="hidden mt-1 text-xs text-red-600 dark:text-red-400" role="alert"></p>
                            <ul id="idTips" class="mt-2 space-y-1 text-xs text-gray-500 list-disc list-inside dark:text-gray-400">
                                <li>The whole ID in the frame, readable, no glare.</li>
                                <li>You may cover the ID number. We only need your photo and name.</li>
                            </ul>
                        </div>

                        {{-- Step 3: selfie --}}
                        <div>
                            <label for="selfie" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <span class="font-semibold text-[#8B7355] dark:text-[#C4A97D]">3.</span> Selfie holding the same ID
                            </label>
                            <div class="relative flex items-center justify-center overflow-hidden bg-gray-100 border border-gray-300 border-dashed rounded-2xl aspect-[3/2] dark:bg-gray-700 dark:border-gray-600">
                                <img id="selfiePreview" alt="Preview of your selfie" class="absolute inset-0 z-10 hidden object-contain w-full h-full bg-gray-100 dark:bg-gray-700">
                                <i class="text-3xl text-gray-300 fa-solid fa-user dark:text-gray-500" aria-hidden="true"></i>
                            </div>
                            {{-- capture="user" opens the front camera on phones (desktop browsers ignore it). --}}
                            <input type="file" id="selfie" name="selfie" required accept="image/jpeg,image/png,image/webp" capture="user"
                                   aria-describedby="selfieTips"
                                   class="block w-full mt-3 text-sm text-gray-600 dark:text-gray-300 file:mr-3 file:min-h-[44px] file:px-4 file:py-2 file:rounded-xl file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 dark:file:bg-gray-700 dark:file:text-white">
                            <p id="selfieClientError" class="hidden mt-1 text-xs text-red-600 dark:text-red-400" role="alert"></p>
                            <ul id="selfieTips" class="mt-2 space-y-1 text-xs text-gray-500 list-disc list-inside dark:text-gray-400">
                                <li>Hold the ID next to your face. Both clearly visible.</li>
                                <li>Good light, no filters, no sunglasses or mask.</li>
                            </ul>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">JPG, PNG or WebP, up to {{ $uploadMaxMb }} MB each.</p>

                    {{-- Step 4: consent (RA 10173; NPC Circular 2023-04) --}}
                    <div class="p-4 border border-gray-200 rounded-2xl bg-gray-50 dark:bg-gray-900/40 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                            <span class="font-semibold text-[#8B7355] dark:text-[#C4A97D]">4.</span> Before you submit: how we handle your ID
                        </h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Data Privacy Act of 2012 (RA 10173). Your ID is sensitive personal information, so we need your consent first.</p>
                        <dl class="mt-3 space-y-3 text-sm text-gray-700 dark:text-gray-300">
                            <div>
                                <dt class="font-semibold">What we collect</dt>
                                <dd>One photo of a government ID, one selfie holding it, the ID type, and your name as shown on your profile.</dd>
                            </div>
                            <div>
                                <dt class="font-semibold">Why</dt>
                                <dd>To confirm that the person in your profile photo is you, so customers who book home service can check who arrives at their door.</dd>
                            </div>
                            <div>
                                <dt class="font-semibold">Who sees it</dt>
                                <dd>Only the Levictas admins who review it. Your spa owner and co-workers cannot see your ID or selfie. Every time an admin opens them, it is recorded.</dd>
                            </div>
                            <div>
                                <dt class="font-semibold">How long we keep it</dt>
                                <dd>Your ID and selfie are deleted as soon as the admin approves or rejects your request, or when you cancel it. We keep only the result, the ID type, who reviewed it and the dates, for as long as your account exists.</dd>
                            </div>
                            <div>
                                <dt class="font-semibold">It's your choice</dt>
                                <dd>Verification is optional. If you don't verify, nothing else changes: you can still work in-branch and take walk-in and staff-made bookings. You just won't be assigned online home-service bookings.</dd>
                            </div>
                            <div>
                                <dt class="font-semibold">Changing your mind</dt>
                                <dd>You can withdraw consent at any time on this page. A pending request and its files are deleted right away; an approval ends, and you stop receiving new online home-service bookings.</dd>
                            </div>
                            <div>
                                <dt class="font-semibold">Your rights</dt>
                                <dd>
                                    You can ask to see, correct or delete your data
                                    @if ($privacyContact)
                                        by writing to <a href="mailto:{{ $privacyContact }}" class="font-medium text-[#8B7355] underline underline-offset-2 dark:text-[#C4A97D]">{{ $privacyContact }}</a>.
                                    @else
                                        by contacting the Levictas team.
                                    @endif
                                    You may also file a complaint with the National Privacy Commission (privacy.gov.ph).
                                </dd>
                            </div>
                        </dl>

                        <label class="flex items-start gap-3 p-3 mt-4 bg-white border border-gray-200 cursor-pointer rounded-xl dark:bg-gray-800 dark:border-gray-700 {{ $iErr->has('consent') ? 'border-red-500 dark:border-red-500' : '' }}">
                            <input type="checkbox" name="consent" value="1" required @checked(old('consent'))
                                   class="mt-0.5 w-5 h-5 border-gray-300 rounded text-[#8B7355] focus:ring-[#8B7355] dark:border-gray-600 dark:bg-gray-700">
                            <span class="text-sm text-gray-800 dark:text-gray-200">
                                I have read this notice and I agree to Levictas processing my ID and selfie to verify my identity.
                            </span>
                        </label>
                        <p class="mt-2 text-[11px] text-gray-400 dark:text-gray-500">Notice version {{ $consentVersion }}</p>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                                class="inline-flex items-center gap-2 min-h-[44px] rounded-xl bg-[#8B7355] px-5 py-2 text-sm font-medium text-white hover:bg-[#7A6348] disabled:opacity-60 disabled:cursor-not-allowed">
                            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                            <span data-busy-label="Uploading…">Submit for review</span>
                        </button>
                    </div>
                </fieldset>
            </form>
        @endif
    </div>
</div>
