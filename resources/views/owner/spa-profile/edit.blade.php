@extends('layouts.app')

@section('title', 'Spa Profile')

@section('content')

@php
    $verificationState = match($spa->verification_status) {
        'verified' => [
            'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
            'card' => 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/10',
            'head' => 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/10',
            'icon' => 'fa-circle-check text-emerald-600 dark:text-emerald-400',
            'title' => 'Your spa is verified',
            'description' => 'Your business documents have been approved.',
        ],

        'pending' => [
            'badge' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'card' => 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/10',
            'head' => 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/10',
            'icon' => 'fa-hourglass-half text-amber-600 dark:text-amber-400',
            'title' => 'Verification is pending review',
            'description' => 'Your submitted documents are being reviewed.',
        ],

        'rejected' => [
            'badge' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
            'card' => 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/10',
            'head' => 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/10',
            'icon' => 'fa-circle-xmark text-red-600 dark:text-red-400',
            'title' => 'Verification was rejected',
            'description' => 'Review the administrator remarks and upload corrected documents.',
        ],

        default => [
            'badge' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
            'card' => 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800',
            'head' => 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/40',
            'icon' => 'fa-shield-halved text-gray-500 dark:text-gray-400',
            'title' => 'Your spa is not yet verified',
            'description' => 'Upload all required documents for verification.',
        ],
    };

    $statusLabel = filled($spa->verification_status)
        ? ucfirst($spa->verification_status)
        : 'Not Verified';

    $isVerified = $spa->verification_status === 'verified';
    $isPending = $spa->verification_status === 'pending';
    $isRejected = $spa->verification_status === 'rejected';

    $renewableTypes = [
        'dti_sec',
        'business_permit',
    ];

    $renewalWindowDays = 30;

    $documentMeta = [
        'government_id' => [
            'label' => 'Government ID',
            'description' => 'Upload a valid government-issued ID showing its expiration date.',
            'expiry' => 'required',
        ],

        'dti_sec' => [
            'label' => 'DTI / SEC Certificate',
            'description' => 'Upload your DTI or SEC registration certificate.',
            'expiry' => 'optional',
        ],

        'bir_certificate' => [
            'label' => 'BIR Certificate of Registration',
            'description' => 'Upload your BIR Certificate of Registration.',
            'expiry' => 'none',
        ],

        'business_permit' => [
            'label' => 'Business Permit',
            'description' => 'Upload the issued Business Permit showing its validity.',
            'expiry' => 'required',
        ],
    ];

    $documents = $spa
        ->verificationDocuments
        ->keyBy('document_type');

    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'primary' => $btnBase . ' bg-gradient-to-r from-[#7A6348] to-[#6F5430] text-white hover:opacity-90',
        'upload' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 cursor-pointer '
                  . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
    ];

    $inputClass = 'block w-full px-3 py-2 text-sm text-gray-900 bg-white border border-gray-300 rounded-xl '
                . 'focus:outline-none focus:ring-2 focus:ring-[#8B7355] focus:border-transparent '
                . 'dark:bg-gray-700 dark:border-gray-600 dark:text-white';

    $maxUploadBytes = 10 * 1024 * 1024;

    $hasUploadableDocument = false;
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">
    <x-page-header
        title="Spa Profile"
        subtitle="Manage your spa information and verification documents."
    />

    <div class="overflow-hidden border shadow-sm rounded-2xl {{ $verificationState['card'] }}">
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-4 border-b sm:px-6 {{ $verificationState['head'] }}">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                Verification Status
            </h2>

            <span class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full {{ $verificationState['badge'] }}">
                {{ $statusLabel }}
            </span>
        </div>

        <div class="p-4 sm:p-5">
            <div class="flex items-start gap-4">
                <div class="flex items-center justify-center bg-white rounded-full shadow-sm w-14 h-14 shrink-0 dark:bg-gray-800">
                    <i class="text-2xl fa-solid {{ $verificationState['icon'] }}" aria-hidden="true"></i>
                </div>

                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ $verificationState['title'] }}
                    </h3>

                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        {{ $verificationState['description'] }}
                    </p>

                    @if ($isVerified && $spa->verified_at)
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Verified {{ $spa->verified_at->format('F d, Y') }}
                        </p>
                    @endif
                </div>
            </div>

            @if ($isRejected && $spa->verification_remarks)
                <div class="p-4 mt-5 text-sm text-red-800 border border-red-200 bg-red-50 rounded-2xl dark:bg-red-900/10 dark:text-red-300 dark:border-red-800">
                    <strong>Admin Remarks:</strong>
                    {{ $spa->verification_remarks }}
                </div>
            @endif
        </div>
    </div>

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                Spa Information
            </h2>
        </div>

        <div class="p-4 sm:p-5">
            <dl>
                <dt class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                    Spa Name
                </dt>

                <dd class="flex items-center justify-between gap-3 px-3 py-2 text-sm text-gray-700 bg-gray-100 border border-gray-300 rounded-xl dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300">
                    <span class="min-w-0 break-words">{{ $spa->name }}</span>

                    <i class="text-gray-400 fa-solid fa-lock shrink-0 dark:text-gray-500"
                        aria-hidden="true"
                        title="Locked"></i>
                </dd>
            </dl>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Your spa name was set during registration and must remain unique. It can no longer be changed.
            </p>
        </div>
    </div>

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                Verification Documents
            </h2>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                @if ($isPending)
                    Documents are locked while under review.
                @elseif ($isVerified)
                    Renewal becomes available 30 days before the confirmed expiration date.
                @else
                    Upload the required business documents.
                @endif
            </p>
        </div>

        <div class="p-4 sm:p-5">
            <div class="p-4 mb-5 text-sm text-blue-800 border border-blue-200 bg-blue-50 rounded-2xl dark:bg-blue-900/10 dark:text-blue-300 dark:border-blue-800">
                PDF, JPG, JPEG or PNG. Maximum 10 MB.
            </div>

            <form id="verificationDocumentsForm"
                method="POST"
                action="{{ route('owner.spa-profile.documents.upload') }}"
                enctype="multipart/form-data"
                class="space-y-5">

                @csrf

                @foreach ($documentMeta as $type => $meta)
                    @php
                        $document = $documents->get($type);

                        $fileField = 'documents.' . $type;
                        $expiryField = 'owner_expiry.' . $type;

                        $hasFileError = $errors->has($fileField);
                        $hasExpiryError = $errors->has($expiryField);

                        $isRenewable = in_array(
                            $type,
                            $renewableTypes,
                            true
                        );

                        $renewalEligible =
                            $isVerified &&
                            $isRenewable &&
                            $document &&
                            $document->isRenewalDue(
                                $renewalWindowDays
                            );

                        $renewalOpensAt =
                            $document &&
                            $isRenewable
                                ? $document->renewalOpensAt(
                                    $renewalWindowDays
                                )
                                : null;

                        $canUploadDocument = false;

                        if (!$isPending) {
                            if ($isRejected) {
                                $canUploadDocument = true;
                            } elseif (!$document && !$isVerified) {
                                $canUploadDocument = true;
                            } elseif ($renewalEligible) {
                                $canUploadDocument = true;
                            }
                        }

                        if ($canUploadDocument) {
                            $hasUploadableDocument = true;
                        }
                    @endphp

                    <div class="p-4 border rounded-2xl sm:p-5 {{ $hasFileError || $hasExpiryError ? 'border-red-300 dark:border-red-800' : 'border-gray-200 dark:border-gray-700' }}">
                        <div class="flex flex-col gap-4">
                            <div>
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                                                {{ $meta['label'] }}
                                            </h3>

                                            @if ($document)
                                                <span class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                                    Uploaded
                                                </span>
                                            @else
                                                <span class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300">
                                                    Required
                                                </span>
                                            @endif
                                        </div>

                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                            {{ $meta['description'] }}
                                        </p>

                                        @if ($document)
                                            <p class="mt-2 text-sm text-gray-700 break-words dark:text-gray-300">
                                                <span class="font-medium">File:</span>
                                                {{ $document->file_name }}
                                            </p>
                                        @endif
                                    </div>

                                    @if ($document && $type !== 'bir_certificate')
                                        <div class="grid grid-cols-2 gap-2 shrink-0 sm:min-w-[310px]">
                                            <div class="px-3 py-2 border border-gray-200 rounded-xl dark:border-gray-700">
                                                <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                                    Your Expiry
                                                </p>

                                                <p class="mt-0.5 text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $document->owner_expiry_date
                                                        ? $document->owner_expiry_date->format('M d, Y')
                                                        : 'Not provided'
                                                    }}
                                                </p>
                                            </div>

                                            @if ($document->expiry_verified_at)
                                                <div class="px-3 py-2 border border-emerald-200 bg-emerald-50 rounded-xl dark:border-emerald-800 dark:bg-emerald-900/10">
                                                    <p class="text-[11px] text-emerald-700 dark:text-emerald-300">
                                                        Admin Confirmed
                                                    </p>

                                                    <p class="mt-0.5 text-sm font-medium text-emerald-700 dark:text-emerald-300">
                                                        {{ $document->expiry_date
                                                            ? $document->expiry_date->format('M d, Y')
                                                            : 'No expiry'
                                                        }}
                                                    </p>
                                                </div>
                                            @else
                                                <div class="px-3 py-2 border border-amber-200 bg-amber-50 rounded-xl dark:border-amber-800 dark:bg-amber-900/10">
                                                    <p class="text-[11px] text-amber-700 dark:text-amber-300">
                                                        Document Review
                                                    </p>

                                                    <p class="mt-0.5 text-sm font-medium text-amber-800 dark:text-amber-300">
                                                        Pending
                                                    </p>
                                                </div>
                                            @endif
                                        </div>
                                    @elseif ($document && $type === 'bir_certificate')
                                        <div class="shrink-0">
                                            <span class="inline-flex items-center gap-2 px-3 py-2 text-sm border text-slate-700 border-slate-200 bg-slate-50 rounded-xl dark:border-slate-700 dark:bg-slate-900/30 dark:text-slate-300">
                                                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                                No expiration required
                                            </span>
                                        </div>
                                    @endif
                                </div>

                                @if ($document)
                                    <div class="mt-3">
                                        <a href="{{ asset('storage/' . $document->file_path) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="inline-flex items-center min-h-[44px] text-sm text-blue-600 underline rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:text-blue-400 dark:focus-visible:ring-offset-gray-800">

                                            View Document
                                        </a>
                                    </div>
                                @endif
                            </div>

                            @if ($canUploadDocument)
                                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        @if ($renewalEligible)
                                            Renew Document
                                        @elseif ($isRejected && $document)
                                            Replace Document
                                        @else
                                            Upload Document
                                        @endif
                                    </p>

                                    <div class="flex flex-wrap items-center gap-3">
                                        <input id="document_file_{{ $type }}"
                                            type="file"
                                            name="documents[{{ $type }}]"
                                            accept=".pdf,.jpg,.jpeg,.png"
                                            class="sr-only"
                                            data-max-bytes="{{ $maxUploadBytes }}"
                                            data-status-id="file_label_{{ $type }}"
                                            data-error-id="file_error_{{ $type }}"
                                            data-expiry-wrapper-id="expiry_wrapper_{{ $type }}"
                                            data-expiry-input-id="owner_expiry_{{ $type }}"
                                            data-picker-label-id="picker_label_{{ $type }}"
                                            data-picker-text-id="picker_text_{{ $type }}"
                                            data-expiry-required="{{ $meta['expiry'] === 'required' ? 'true' : 'false' }}"
                                            onchange="handleFileChange(this)">

                                        <label id="picker_label_{{ $type }}"
                                            for="document_file_{{ $type }}"
                                            class="{{ $btn['upload'] }}">

                                            <i class="text-xs fa-solid fa-arrow-up-from-bracket"
                                                aria-hidden="true"></i>

                                            <span id="picker_text_{{ $type }}">
                                                Choose File
                                            </span>
                                        </label>

                                        <span id="file_label_{{ $type }}"
                                            class="text-sm italic text-gray-500 dark:text-gray-400">
                                            No file chosen
                                        </span>
                                    </div>

                                    <p id="file_error_{{ $type }}"
                                        class="mt-1 text-xs text-red-600 dark:text-red-400 {{ $hasFileError ? '' : 'hidden' }}"
                                        role="alert">

                                        {{ $errors->first($fileField) }}
                                    </p>

                                    @if ($meta['expiry'] !== 'none')
                                        <div id="expiry_wrapper_{{ $type }}"
                                            class="hidden mt-4">

                                            <label for="owner_expiry_{{ $type }}"
                                                class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">

                                                Expiration Date

                                                @if ($meta['expiry'] === 'required')
                                                    <span class="text-red-600">*</span>
                                                @else
                                                    <span class="text-xs font-normal text-gray-500 dark:text-gray-400">
                                                        Optional
                                                    </span>
                                                @endif
                                            </label>

                                            <input type="date"
                                                id="owner_expiry_{{ $type }}"
                                                name="owner_expiry[{{ $type }}]"
                                                value="{{ old($expiryField) }}"
                                                min="{{ now()->format('Y-m-d') }}"
                                                class="{{ $inputClass }}">

                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Enter the date printed on the document.
                                            </p>

                                            @error($expiryField)
                                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                                    {{ $message }}
                                                </p>
                                            @enderror
                                        </div>
                                    @endif
                                </div>
                            @elseif ($isPending)
                                <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                                    <p class="inline-flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                        Locked while under review.
                                    </p>
                                </div>
                            @elseif ($isVerified && $isRenewable && $document)
                                <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                                    <p class="inline-flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <i class="fa-solid fa-lock" aria-hidden="true"></i>

                                        @if ($document->expiry_date && $renewalOpensAt)
                                            Renewal available
                                            {{ $renewalOpensAt->format('M d, Y') }}.
                                        @else
                                            Renewal is currently locked.
                                        @endif
                                    </p>
                                </div>
                            @elseif ($document)
                                <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                                    <p class="inline-flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                        Submitted document is locked.
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if ($hasUploadableDocument)
                    <p id="documents_form_error"
                        class="hidden text-sm text-red-600 dark:text-red-400"
                        role="alert"
                        tabindex="-1">
                    </p>

                    <div class="flex justify-end pt-2">
                        <button type="submit"
                            id="verificationDocumentsSubmitBtn"
                            data-default-text="{{ $isVerified ? 'Submit Renewal' : 'Submit Documents' }}"
                            class="{{ $btn['primary'] }} disabled:opacity-60 disabled:cursor-not-allowed">

                            {{ $isVerified ? 'Submit Renewal' : 'Submit Documents' }}
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>

<script>
function handleFileChange(input) {
    const status =
        document.getElementById(
            input.dataset.statusId
        );

    const error =
        document.getElementById(
            input.dataset.errorId
        );

    const wrapper =
        document.getElementById(
            input.dataset.expiryWrapperId
        );

    const expiryInput =
        document.getElementById(
            input.dataset.expiryInputId
        );

    const pickerLabel =
        document.getElementById(
            input.dataset.pickerLabelId
        );

    const pickerText =
        document.getElementById(
            input.dataset.pickerTextId
        );

    const max =
        parseInt(
            input.dataset.maxBytes,
            10
        );

    const file =
        input.files &&
        input.files[0];

    if (error) {
        error.textContent = '';
        error.classList.add(
            'hidden'
        );
    }

    if (!file) {
        resetFilePicker(
            status,
            pickerLabel,
            pickerText
        );

        toggleExpiry(
            input,
            wrapper,
            expiryInput,
            false
        );

        updateSubmitState();

        return;
    }

    if (file.size > max) {
        input.value = '';

        resetFilePicker(
            status,
            pickerLabel,
            pickerText
        );

        if (error) {
            error.textContent =
                '"' +
                file.name +
                '" exceeds 10 MB.';

            error.classList.remove(
                'hidden'
            );
        }

        toggleExpiry(
            input,
            wrapper,
            expiryInput,
            false
        );

        updateSubmitState();

        return;
    }

    if (status) {
        status.textContent =
            file.name;

        status.classList.remove(
            'italic',
            'text-gray-500',
            'dark:text-gray-400'
        );

        status.classList.add(
            'text-gray-800',
            'dark:text-gray-200',
            'font-medium'
        );
    }

    lockFilePicker(
        input,
        pickerLabel,
        pickerText
    );

    toggleExpiry(
        input,
        wrapper,
        expiryInput,
        true
    );

    clearFormError();
    updateSubmitState();
}

function lockFilePicker(
    input,
    pickerLabel,
    pickerText
) {
    if (!pickerLabel) {
        return;
    }

    pickerLabel.classList.add(
        'pointer-events-none',
        'opacity-50',
        'cursor-not-allowed'
    );

    pickerLabel.setAttribute(
        'aria-disabled',
        'true'
    );

    input.setAttribute(
        'tabindex',
        '-1'
    );

    if (pickerText) {
        pickerText.textContent =
            'File Selected';
    }
}

function resetFilePicker(
    status,
    pickerLabel,
    pickerText
) {
    if (status) {
        status.textContent =
            'No file chosen';

        status.classList.add(
            'italic',
            'text-gray-500',
            'dark:text-gray-400'
        );

        status.classList.remove(
            'text-gray-800',
            'dark:text-gray-200',
            'font-medium'
        );
    }

    if (pickerLabel) {
        pickerLabel.classList.remove(
            'pointer-events-none',
            'opacity-50',
            'cursor-not-allowed'
        );

        pickerLabel.removeAttribute(
            'aria-disabled'
        );
    }

    if (pickerText) {
        pickerText.textContent =
            'Choose File';
    }
}

function toggleExpiry(
    fileInput,
    wrapper,
    expiryInput,
    show
) {
    if (
        !wrapper ||
        !expiryInput
    ) {
        return;
    }

    wrapper.classList.toggle(
        'hidden',
        !show
    );

    expiryInput.required =
        show &&
        fileInput.dataset.expiryRequired ===
            'true';

    if (!show) {
        expiryInput.value = '';
    }

    expiryInput.oninput =
        updateSubmitState;
}

function updateSubmitState() {
    const form =
        document.getElementById(
            'verificationDocumentsForm'
        );

    const submitBtn =
        document.getElementById(
            'verificationDocumentsSubmitBtn'
        );

    if (
        !form ||
        !submitBtn
    ) {
        return;
    }

    const fileInputs =
        form.querySelectorAll(
            'input[type="file"]'
        );

    let hasFile = false;
    let missingRequiredExpiry = false;

    fileInputs.forEach(
        input => {
            const hasSelectedFile =
                input.files &&
                input.files.length > 0;

            if (!hasSelectedFile) {
                return;
            }

            hasFile = true;

            if (
                input.dataset.expiryRequired ===
                'true'
            ) {
                const expiryInput =
                    document.getElementById(
                        input.dataset.expiryInputId
                    );

                if (
                    !expiryInput ||
                    !expiryInput.value
                ) {
                    missingRequiredExpiry =
                        true;
                }
            }
        }
    );

    submitBtn.disabled =
        !hasFile ||
        missingRequiredExpiry;
}

function clearFormError() {
    const box =
        document.getElementById(
            'documents_form_error'
        );

    if (!box) {
        return;
    }

    box.textContent = '';

    box.classList.add(
        'hidden'
    );
}

document.addEventListener(
    'DOMContentLoaded',
    function () {
        const form =
            document.getElementById(
                'verificationDocumentsForm'
            );

        if (!form) {
            return;
        }

        const submitBtn =
            document.getElementById(
                'verificationDocumentsSubmitBtn'
            );

        updateSubmitState();

        form.addEventListener(
            'submit',
            function (event) {
                const inputs =
                    form.querySelectorAll(
                        'input[type="file"]'
                    );

                const chosen =
                    Array.from(
                        inputs
                    ).some(
                        input =>
                            input.files &&
                            input.files.length > 0
                    );

                if (!chosen) {
                    event.preventDefault();

                    const box =
                        document.getElementById(
                            'documents_form_error'
                        );

                    if (box) {
                        box.textContent =
                            'Choose at least one document.';

                        box.classList.remove(
                            'hidden'
                        );

                        box.focus();
                    }

                    return;
                }

                let missingExpiry =
                    false;

                inputs.forEach(
                    input => {
                        if (
                            !input.files ||
                            input.files.length === 0 ||
                            input.dataset.expiryRequired !==
                                'true'
                        ) {
                            return;
                        }

                        const expiryInput =
                            document.getElementById(
                                input.dataset.expiryInputId
                            );

                        if (
                            !expiryInput ||
                            !expiryInput.value
                        ) {
                            missingExpiry =
                                true;
                        }
                    }
                );

                if (missingExpiry) {
                    event.preventDefault();

                    const box =
                        document.getElementById(
                            'documents_form_error'
                        );

                    if (box) {
                        box.textContent =
                            'Enter the required expiration date before submitting.';

                        box.classList.remove(
                            'hidden'
                        );

                        box.focus();
                    }

                    return;
                }

                if (submitBtn) {
                    submitBtn.disabled =
                        true;

                    submitBtn.textContent =
                        'Uploading...';
                }
            }
        );

        window.addEventListener(
            'pageshow',
            function () {
                if (submitBtn) {
                    submitBtn.textContent =
                        submitBtn.dataset
                            .defaultText ||
                        'Submit Documents';
                }

                updateSubmitState();
            }
        );
    }
);
</script>

@endsection
