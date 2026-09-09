@extends('layouts.app')

@section('title', 'Spa Profile')
@section('content')

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">
    <x-page-header
        title="Spa Profile"
        subtitle="Manage your spa information and upload business verification documents."
    />

    @php
        $verificationState = match($spa->verification_status) {
            'verified' => [
                'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                'card'  => 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/10',
                'head'  => 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/10',
                'icon'  => 'fa-circle-check text-emerald-600 dark:text-emerald-400',
                'title' => 'Your spa is verified',
                'description' => 'Your business documents have been reviewed and approved by the platform administrator.',
            ],
            'pending' => [
                'badge' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                'card'  => 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/10',
                'head'  => 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/10',
                'icon'  => 'fa-hourglass-half text-amber-600 dark:text-amber-400',
                'title' => 'Verification is pending review',
                'description' => 'Your uploaded documents are currently being reviewed by the platform administrator. <br><i>Waiting time may vary depending on the volume of submissions, but it typically takes 1-3 business days.</i>',
            ],
            'rejected' => [
                'badge' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                'card'  => 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/10',
                'head'  => 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/10',
                'icon'  => 'fa-circle-xmark text-red-600 dark:text-red-400',
                'title' => 'Verification was rejected',
                'description' => 'Please review the admin remarks below and update your submitted documents if needed.',
            ],
            default => [
                'badge' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
                'card'  => 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800',
                'head'  => 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/40',
                'icon'  => 'fa-shield-halved text-gray-500 dark:text-gray-400',
                'title' => 'Your spa is not yet verified',
                'description' => 'Upload all required documents to submit your spa for business verification.',
            ],
        };

        $statusLabel = filled($spa->verification_status)
            ? ucfirst($spa->verification_status)
            : 'Not Verified';

        $isVerified = $spa->verification_status === 'verified';

        $documentMeta = [
            'government_id' => [
                'label' => 'Government ID',
                'description' => 'Upload one valid government-issued ID of the spa owner or authorized representative. Make sure the name and photo are clear and readable.',
            ],
            'dti_sec' => [
                'label' => 'DTI / SEC Certificate',
                'description' => 'Upload your DTI Business Name Certificate or SEC Registration Certificate as proof that your spa business is registered.',
            ],
            'bir_certificate' => [
                'label' => 'BIR Certificate of Registration',
                'description' => 'Upload your BIR Certificate of Registration to confirm that your business is registered for tax purposes.',
            ],
        ];

        $documents = $spa->verificationDocuments->keyBy('document_type');

        $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
                 . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
                 . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

        $btn = [
            'primary' => $btnBase . ' bg-gradient-to-r from-[#7A6348] to-[#6F5430] text-white hover:opacity-90',
            'upload'  => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 cursor-pointer '
                       . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        ];

        $maxUploadBytes = 10 * 1024 * 1024;
    @endphp

    {{-- VERIFICATION STATUS --}}
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
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex items-center justify-center bg-white rounded-full shadow-sm w-14 h-14 shrink-0 dark:bg-gray-800">
                        <i class="text-2xl fa-solid {{ $verificationState['icon'] }}" aria-hidden="true"></i>
                    </div>

                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ $verificationState['title'] }}
                        </h3>

                        {{-- Raw echo: the pending copy contains <br><i> markup. Every arm of the
                             match is a static literal, so nothing user-supplied reaches here. --}}
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                            {!! $verificationState['description'] !!}
                        </p>

                        @if ($isVerified && $spa->verified_at)
                            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                Verified on {{ $spa->verified_at->format('F d, Y h:i A') }}
                            </p>
                        @endif
                    </div>
                </div>

                <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-3 lg:w-[430px] lg:shrink-0">
                    @foreach ($documentMeta as $type => $meta)
                        @php $document = $documents->get($type); @endphp

                        <div class="flex flex-col h-full p-3 bg-white border border-gray-200 rounded-2xl dark:bg-gray-800 dark:border-gray-700 min-h-[76px] sm:min-h-[96px]">
                            <p class="text-[11px] sm:text-xs font-medium leading-snug tracking-wide text-gray-500 uppercase break-words dark:text-gray-400">
                                {{ $meta['label'] }}
                            </p>

                            <p class="pt-2 mt-auto sm:pt-3 text-sm font-semibold {{ $document ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}">
                                {{ $document ? 'Uploaded' : 'Required' }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($spa->verification_status === 'rejected' && $spa->verification_remarks)
                <div class="p-4 mt-5 text-sm text-red-800 border border-red-200 bg-red-50 rounded-2xl dark:bg-red-900/10 dark:text-red-300 dark:border-red-800">
                    <strong>Admin Remarks:</strong> {{ $spa->verification_remarks }}
                </div>
            @endif
        </div>
    </div>

    {{-- SPA INFORMATION --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Spa Information</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Update your spa's main business information.</p>
        </div>

        <div class="p-4 sm:p-5">
            <form method="POST" action="{{ route('owner.spa-profile.update') }}" class="space-y-5">
                @csrf
                @method('PATCH')

                <div>
                    <label for="name" class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Spa Name <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                    </label>

                    <input type="text" id="name" name="name"
                        value="{{ old('name', $spa->name) }}"
                        placeholder="Enter your spa's registered business name"
                        @disabled($isVerified)
                        @if ($isVerified) aria-describedby="name-locked-hint" @endif
                        class="w-full px-3 py-2 text-gray-900 bg-white border rounded-xl dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355] dark:focus:ring-[#C4A97D] focus:border-transparent disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500 dark:disabled:bg-gray-900 dark:disabled:text-gray-400
                               {{ $errors->has('name') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600' }}"
                        required>

                    @error('name')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    {{-- The old file locked this field once verified with nothing explaining why. --}}
                    @if ($isVerified)
                        <p id="name-locked-hint" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Locked because your spa is verified. Contact the platform administrator to change your registered business name.
                        </p>
                    @endif
                </div>

                @unless ($isVerified)
                    <div class="flex items-center gap-4">
                        <button type="submit" class="{{ $btn['primary'] }}">Save</button>
                    </div>
                @endunless
            </form>
        </div>
    </div>

    {{-- VERIFICATION DOCUMENTS --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Verification Documents</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Upload all required documents to submit your spa for verification.</p>
        </div>

        <div class="p-4 sm:p-5">
            <div class="p-4 mb-5 text-sm text-blue-800 border border-blue-200 bg-blue-50 rounded-2xl dark:bg-blue-900/10 dark:text-blue-300 dark:border-blue-800">
                Accepted formats: PDF, JPG, JPEG, PNG. Maximum file size: 10 MB per document.
            </div>

            @if ($errors->has('documents'))
                <div class="p-4 mb-5 text-sm text-red-800 border border-red-200 bg-red-50 rounded-2xl dark:bg-red-900/10 dark:text-red-300 dark:border-red-800" role="alert">
                    {{ $errors->first('documents') }}
                </div>
            @endif

            <form id="verificationDocumentsForm"
                method="POST"
                action="{{ route('owner.spa-profile.documents.upload') }}"
                enctype="multipart/form-data"
                class="space-y-5">
                @csrf

                @foreach ($documentMeta as $type => $meta)
                    @php
                        $document  = $documents->get($type);
                        $fieldName = 'documents.' . $type;
                        $hasError  = $errors->has($fieldName);
                    @endphp

                    <div class="p-4 border rounded-2xl sm:p-5 {{ $hasError ? 'border-red-300 dark:border-red-800' : 'border-gray-200 dark:border-gray-700' }}">
                        <div class="space-y-4">
                            <div>
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
                                    <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                                        <span class="font-medium">Current file:</span> {{ $document->file_name }}
                                    </p>

                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Uploaded on {{ $document->created_at->format('F d, Y h:i A') }}
                                    </p>

                                    {{-- Storage path and link left exactly as they were. See the
                                         outstanding storage-access item before defence. --}}
                                    <a href="{{ asset('storage/' . $document->file_path) }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex items-center gap-1.5 mt-3 min-h-[44px] text-sm text-blue-600 underline rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:text-blue-400 dark:focus-visible:ring-offset-gray-800">
                                        View Current Document
                                        <span class="sr-only">({{ $meta['label'] }}, opens in a new tab)</span>
                                    </a>
                                @else
                                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                                        No document uploaded yet.
                                    </p>
                                @endif
                            </div>

                            @unless ($isVerified)
                                <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                                    <p id="upload_label_{{ $type }}" class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ $document ? 'Replace File' : 'Upload File' }}
                                    </p>

                                    <div class="flex flex-wrap items-center gap-3">
                                        <input
                                            id="document_file_{{ $type }}"
                                            type="file"
                                            name="documents[{{ $type }}]"
                                            accept=".pdf,.jpg,.jpeg,.png"
                                            class="sr-only peer"
                                            aria-labelledby="upload_label_{{ $type }}"
                                            aria-describedby="file_hint_{{ $type }}"
                                            data-max-bytes="{{ $maxUploadBytes }}"
                                            data-status-id="file_label_{{ $type }}"
                                            data-error-id="file_error_{{ $type }}"
                                            onchange="handleFileChange(this)"
                                        />

                                        {{-- peer-focus-visible lights the label up when the hidden input
                                             takes focus, so tabbing to it shows something. --}}
                                        <label
                                            for="document_file_{{ $type }}"
                                            class="{{ $btn['upload'] }} peer-focus-visible:ring-2 peer-focus-visible:ring-[#8B7355] peer-focus-visible:ring-offset-2 dark:peer-focus-visible:ring-offset-gray-800"
                                        >
                                            <i class="text-xs fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i>
                                            {{ $document ? 'Replace File' : 'Choose File' }}
                                        </label>

                                        <span
                                            id="file_label_{{ $type }}"
                                            class="text-sm italic text-gray-500 dark:text-gray-400"
                                            aria-live="polite"
                                        >
                                            No file chosen
                                        </span>
                                    </div>

                                    <p id="file_hint_{{ $type }}" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        PDF, JPG, JPEG or PNG. Maximum 10 MB.
                                    </p>

                                    {{-- Filled by the size check below, or by the server on a rejected upload. --}}
                                    <p id="file_error_{{ $type }}" class="mt-1 text-xs text-red-600 dark:text-red-400 {{ $hasError ? '' : 'hidden' }}" role="alert">
                                        {{ $errors->first($fieldName) }}
                                    </p>
                                </div>
                            @endunless
                        </div>
                    </div>
                @endforeach

                @unless ($isVerified)
                    {{-- Filled by the submit guard when nothing has been chosen. --}}
                    <p id="documents_form_error"
                        class="hidden text-sm text-red-600 dark:text-red-400"
                        role="alert" tabindex="-1"></p>

                    <div class="flex justify-end pt-2">
                        <button type="submit"
                            id="verificationDocumentsSubmitBtn"
                            class="{{ $btn['primary'] }} disabled:opacity-60 disabled:cursor-not-allowed">
                            Submit Documents
                        </button>
                    </div>
                @endunless
            </form>

        </div>
    </div>
</div>

<script>
    function handleFileChange(input) {
        const status = document.getElementById(input.dataset.statusId);
        const error  = document.getElementById(input.dataset.errorId);
        const max    = parseInt(input.dataset.maxBytes, 10);
        const file   = input.files && input.files[0];

        error.textContent = '';
        error.classList.add('hidden');

        if (!file) {
            status.textContent = 'No file chosen';
            status.classList.add('italic', 'text-gray-500', 'dark:text-gray-400');
            status.classList.remove('text-gray-800', 'dark:text-gray-200', 'font-medium');
            return;
        }

        // Tells the owner the file is too big now, instead of after the upload round-trip.
        if (file.size > max) {
            input.value = '';
            status.textContent = 'No file chosen';
            status.classList.add('italic', 'text-gray-500', 'dark:text-gray-400');
            status.classList.remove('text-gray-800', 'dark:text-gray-200', 'font-medium');

            error.textContent = '"' + file.name + '" is '
                + (file.size / 1048576).toFixed(1) + ' MB. The maximum is 10 MB.';
            error.classList.remove('hidden');
            return;
        }

        status.textContent = file.name;
        status.classList.remove('italic', 'text-gray-500', 'dark:text-gray-400');
        status.classList.add('text-gray-800', 'dark:text-gray-200', 'font-medium');

        // A newly chosen file clears the "nothing selected" warning.
        clearFormError();
    }

    function clearFormError() {
        const box = document.getElementById('documents_form_error');
        if (!box) return;
        box.textContent = '';
        box.classList.add('hidden');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('verificationDocumentsForm');
        if (!form) return;

        const submitBtn = document.getElementById('verificationDocumentsSubmitBtn');

        form.addEventListener('submit', function (event) {
            const inputs = form.querySelectorAll('input[type="file"]');
            const chosen = Array.from(inputs).some(i => i.files && i.files.length > 0);

            if (!chosen) {
                event.preventDefault();

                const box = document.getElementById('documents_form_error');
                if (box) {
                    box.textContent = 'Choose at least one document before submitting.';
                    box.classList.remove('hidden');
                    box.focus();
                }
                return;
            }

            // Stops a second POST while the upload is in flight.
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Uploading...';
            }
        });

        // Back-button restores from bfcache with the button still disabled otherwise.
        window.addEventListener('pageshow', function () {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Documents';
            }
        });
    });
</script>

@endsection