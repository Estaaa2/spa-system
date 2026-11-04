@extends('layouts.app')

@section('title', 'Profile')
@section('content')
@php
    $mustChangePassword = $user->password_reset_required;
    $hasPhoto = $user->hasProfilePhoto();
    $isVerified = $user->isIdentityVerified();
    $isPending = $identityState === \App\Services\Identity\IdentityVerificationService::STATE_PENDING;
@endphp

<div class="p-6 mx-auto space-y-6 max-w-7xl">
    <x-page-header
        title="Profile"
        subtitle="Your photo, account details, identity verification and password."
    />

    {{-- ================= FIRST-LOGIN / TEMP PASSWORD NOTICE ================= --}}
    @if ($mustChangePassword)
        <div class="flex items-start gap-3 p-4 border shadow-sm border-amber-200 bg-amber-50 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800" role="status">
            <i class="fa-solid fa-triangle-exclamation text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" aria-hidden="true"></i>
            <div>
                <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                    You're signing in with a temporary password
                </p>
                <p class="mt-0.5 text-sm text-amber-700 dark:text-amber-300">
                    For security, set your own password in the <a href="#password" class="font-semibold underline underline-offset-2">Change Password</a> section below.
                    The rest of the system unlocks automatically once you save it.
                </p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- ================= LEFT: PHOTO ================= --}}
        <div class="space-y-6 lg:col-span-1">
            @include('profile.partials.profile-photo')
        </div>

        {{-- ================= RIGHT: DETAILS ================= --}}
        <div class="space-y-6 lg:col-span-2">
            @include('profile.partials.update-profile-information-form')

            @if ($canVerify)
                @include('profile.partials.identity-verification')
            @endif

            @include('profile.partials.update-password-form')
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════
     CONFIRM: REPLACE A PHOTO THAT VERIFICATION DEPENDS ON
     ═══════════════════════════════════════════════════ --}}
@if ($photoLocked)
<div id="replacePhotoModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="replacePhotoModalTitle" aria-describedby="replacePhotoModalBody"
             class="w-full max-w-md bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <h2 id="replacePhotoModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $isVerified ? 'Replace your verified photo?' : 'Replace your photo?' }}
                </h2>
                <button type="button" data-close-modal="replacePhotoModal" aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="px-4 py-6 space-y-4 sm:px-6">
                <div id="replacePhotoModalBody" class="flex items-start gap-3 p-4 border border-amber-200 bg-amber-50 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800">
                    <i class="fa-solid fa-triangle-exclamation text-amber-600 dark:text-amber-400 mt-0.5" aria-hidden="true"></i>
                    <p class="text-sm text-amber-800 dark:text-amber-300">
                        @if ($isVerified)
                            Your identity was verified against this photo. A new photo ends your verified status, and you won't be assigned new online home-service bookings until you submit your ID and selfie again and they are approved.
                        @else
                            Your verification request was made with this photo. A new photo closes that request and deletes the ID and selfie you sent, so you'll need to submit them again.
                        @endif
                    </p>
                </div>
                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                    <button type="button" data-close-modal="replacePhotoModal"
                            class="min-h-[44px] px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                        Keep current photo
                    </button>
                    <button type="button" id="confirmReplacePhoto"
                            class="min-h-[44px] rounded-xl bg-[#8B7355] px-4 py-2 text-sm font-medium text-white hover:bg-[#7A6348]">
                        Replace photo
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════
     CONFIRM: REMOVE PHOTO
     ═══════════════════════════════════════════════════ --}}
@if ($hasPhoto && ! $photoLocked)
<div id="removePhotoModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="removePhotoModalTitle"
             class="w-full max-w-md bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <h2 id="removePhotoModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Remove your profile photo?</h2>
                <button type="button" data-close-modal="removePhotoModal" aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('profile.photo.destroy') }}" class="px-4 py-6 space-y-4 sm:px-6" data-submit-once>
                @csrf
                @method('DELETE')
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Your initials will show instead.
                    @if ($canVerify)
                        You'll need a photo again before you can verify your identity.
                    @endif
                </p>
                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                    <button type="button" data-close-modal="removePhotoModal"
                            class="min-h-[44px] px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                        Cancel
                    </button>
                    <button type="submit"
                            class="min-h-[44px] rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                        Remove photo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════
     CONFIRM: WITHDRAW CONSENT / CANCEL REQUEST
     ═══════════════════════════════════════════════════ --}}
@if ($canVerify && ($isVerified || $isPending))
<div id="withdrawModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="withdrawModalTitle"
             class="w-full max-w-md bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <h2 id="withdrawModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $isPending ? 'Cancel your verification request?' : 'Withdraw your consent?' }}
                </h2>
                <button type="button" data-close-modal="withdrawModal" aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('profile.identity.withdraw') }}" class="px-4 py-6 space-y-4 sm:px-6" data-submit-once>
                @csrf
                @method('DELETE')
                <ul class="space-y-2 text-sm text-gray-600 list-disc list-inside dark:text-gray-300">
                    @if ($isPending)
                        <li>Your ID and selfie are deleted right away.</li>
                        <li>The admin will not review this request.</li>
                    @else
                        <li>You will no longer be verified.</li>
                        <li>You won't be assigned new online home-service bookings. In-branch work is not affected.</li>
                    @endif
                    <li>You can verify again at any time.</li>
                </ul>
                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                    <button type="button" data-close-modal="withdrawModal"
                            class="min-h-[44px] px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                        {{ $isPending ? 'Keep request' : 'Stay verified' }}
                    </button>
                    <button type="submit"
                            class="min-h-[44px] rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                        {{ $isPending ? 'Cancel request' : 'Withdraw consent' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<script>
(function () {
    'use strict';

    // ════════════════════════════════════════════════════════════════
    // SHARED MODAL BEHAVIOUR (same as appointments.blade.php)
    // ════════════════════════════════════════════════════════════════
    const MODAL_IDS = ['replacePhotoModal', 'removePhotoModal', 'withdrawModal'];
    const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    let lastFocused = null;

    function topmostOpenModal() {
        for (let i = MODAL_IDS.length - 1; i >= 0; i--) {
            const el = document.getElementById(MODAL_IDS[i]);
            if (el && !el.classList.contains('hidden')) return el;
        }
        return null;
    }

    function openModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        lastFocused = document.activeElement;
        el.classList.remove('hidden');
        const target = el.querySelector(FOCUSABLE);
        if (target) target.focus();
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('hidden');
        if (lastFocused && document.contains(lastFocused)) lastFocused.focus();
        lastFocused = null;
    }

    document.addEventListener('keydown', function (e) {
        const modal = topmostOpenModal();
        if (!modal) return;

        if (e.key === 'Escape') {
            e.preventDefault();
            closeModal(modal.id);
            return;
        }
        if (e.key !== 'Tab') return;

        const items = Array.from(modal.querySelectorAll(FOCUSABLE)).filter(el => el.offsetParent !== null);
        if (!items.length) return;
        const first = items[0];
        const last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault(); last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault(); first.focus();
        }
    });

    document.querySelectorAll('[data-open-modal]').forEach(btn => {
        btn.addEventListener('click', () => openModal(btn.dataset.openModal));
    });
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => closeModal(btn.dataset.closeModal));
    });
    // Backdrop click closes these: none of them hold typed input.
    MODAL_IDS.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('click', e => { if (e.target === el) closeModal(id); });
    });

    // ════════════════════════════════════════════════════════════════
    // FILE CHECKS + PREVIEWS (the server checks again and re-encodes)
    // ════════════════════════════════════════════════════════════════
    const MAX_BYTES = {{ (int) $uploadMaxMb }} * 1024 * 1024;
    const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /** Returns an error message, or '' when the file looks acceptable. */
    function checkImage(file) {
        if (!file) return '';
        if (!ALLOWED_TYPES.includes(file.type)) return 'Use a JPG, PNG or WebP photo.';
        if (file.size > MAX_BYTES) return 'This photo is larger than {{ (int) $uploadMaxMb }} MB. Choose a smaller one.';
        return '';
    }

    function showFieldError(id, message) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = message;
        el.classList.toggle('hidden', message === '');
    }

    function previewInto(input, imgId, errorId, onReady) {
        const file = input.files && input.files[0];
        const error = checkImage(file);
        showFieldError(errorId, error);

        const img = document.getElementById(imgId);
        if (!file || error) {
            if (error) input.value = '';
            if (img) img.classList.add('hidden');
            if (onReady) onReady(false);
            return;
        }
        if (img) {
            if (img.dataset.objectUrl) URL.revokeObjectURL(img.dataset.objectUrl);
            img.dataset.objectUrl = URL.createObjectURL(file);
            img.src = img.dataset.objectUrl;
            img.classList.remove('hidden');
        }
        if (onReady) onReady(true);
    }

    // ── Profile photo ──
    const photoForm = document.getElementById('photoForm');
    const photoInput = document.getElementById('photoInput');
    const photoActions = document.getElementById('photoPendingActions');
    const photoCurrent = document.getElementById('photoCurrent');

    if (photoInput) {
        photoInput.addEventListener('change', function () {
            previewInto(photoInput, 'photoPreview', 'photoClientError', ready => {
                photoActions?.classList.toggle('hidden', !ready);
                photoActions?.classList.toggle('flex', ready);
                photoCurrent?.classList.toggle('hidden', ready);
            });
        });
    }

    document.getElementById('photoCancel')?.addEventListener('click', function () {
        photoInput.value = '';
        document.getElementById('photoPreview')?.classList.add('hidden');
        photoActions?.classList.add('hidden');
        photoActions?.classList.remove('flex');
        photoCurrent?.classList.remove('hidden');
        showFieldError('photoClientError', '');
    });

    document.getElementById('photoSave')?.addEventListener('click', function () {
        if (!photoInput?.files?.length) return;
        if (document.getElementById('replacePhotoModal')) {
            openModal('replacePhotoModal');   // the photo is part of a verification
            return;
        }
        submitOnce(photoForm);
    });

    document.getElementById('confirmReplacePhoto')?.addEventListener('click', function () {
        submitOnce(photoForm);
    });

    // ── Identity verification ──
    const idInput = document.getElementById('id_image');
    const selfieInput = document.getElementById('selfie');
    idInput?.addEventListener('change', () => previewInto(idInput, 'idPreview', 'idClientError'));
    selfieInput?.addEventListener('change', () => previewInto(selfieInput, 'selfiePreview', 'selfieClientError'));

    // In the Median Android app, photos taken with the camera are saved to the
    // phone's gallery by default. An ID photo should not stay there.
    try {
        if (window.median && window.median.camera && typeof window.median.camera.saveToGallery === 'function') {
            window.median.camera.saveToGallery(false);
        }
    } catch (e) { /* not in the app, or the bridge is unavailable */ }

    // ════════════════════════════════════════════════════════════════
    // DOUBLE-SUBMIT GUARD (same idea as staff/index.blade.php)
    // ════════════════════════════════════════════════════════════════
    function lockButtons(form) {
        form.querySelectorAll('button[type="submit"]').forEach(btn => {
            btn.disabled = true;
            btn.classList.add('opacity-70', 'cursor-not-allowed');
            const label = btn.querySelector('[data-busy-label]');
            if (label) label.textContent = label.dataset.busyLabel;
            const icon = btn.querySelector('i');
            if (icon) icon.className = 'fa-solid fa-spinner fa-spin';
        });
    }

    function submitOnce(form) {
        if (!form || form.dataset.submitting === 'true') return;
        form.dataset.submitting = 'true';
        lockButtons(form);
        document.getElementById('photoSave')?.setAttribute('disabled', 'disabled');
        document.getElementById('confirmReplacePhoto')?.setAttribute('disabled', 'disabled');
        form.submit();
    }

    document.querySelectorAll('form[data-submit-once]').forEach(form => {
        form.addEventListener('submit', function (e) {
            if (form.dataset.submitting === 'true') { e.preventDefault(); return; }
            if (!form.checkValidity()) return;   // let the browser show its own errors
            form.dataset.submitting = 'true';
            lockButtons(form);
        });
    });
}());
</script>
@endsection
