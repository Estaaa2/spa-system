@extends('layouts.app')

@section('content')

<div class="p-6 mx-auto space-y-6 max-w-7xl">
    <x-page-header
        title="Spa Profile"
        subtitle="Manage your spa information and upload business verification documents."
    />

    @php
        $statusClasses = match($spa->verification_status) {
            'verified' => [
                'badge' => 'bg-green-100 text-green-800 border border-green-200',
                'card' => 'border-green-200 bg-green-50/70 dark:bg-green-900/10 dark:border-green-800',
                'icon' => 'fa-circle-check text-green-600 dark:text-green-400',
                'title' => 'Your spa is verified',
                'description' => 'Your business documents have been reviewed and approved by the platform administrator.',
            ],
            'pending' => [
                'badge' => 'bg-yellow-100 text-yellow-800 border border-yellow-200',
                'card' => 'border-yellow-200 bg-yellow-50/70 dark:bg-yellow-900/10 dark:border-yellow-800',
                'icon' => 'fa-hourglass-half text-yellow-600 dark:text-yellow-400',
                'title' => 'Verification is pending review',
                'description' => 'Your uploaded documents are currently being reviewed by the platform administrator.',
            ],
            'rejected' => [
                'badge' => 'bg-red-100 text-red-800 border border-red-200',
                'card' => 'border-red-200 bg-red-50/70 dark:bg-red-900/10 dark:border-red-800',
                'icon' => 'fa-circle-xmark text-red-600 dark:text-red-400',
                'title' => 'Verification was rejected',
                'description' => 'Please review the admin remarks below and update your submitted documents if needed.',
            ],
            default => [
                'badge' => 'bg-gray-100 text-gray-800 border border-gray-200',
                'card' => 'border-gray-200 bg-gray-50/70 dark:bg-gray-800 dark:border-gray-700',
                'icon' => 'fa-shield-halved text-gray-500 dark:text-gray-300',
                'title' => 'Your spa is not yet verified',
                'description' => 'Upload all required documents to submit your spa for business verification.',
            ],
        };

        $documentLabels = [
            'government_id' => 'Government ID',
            'dti_sec' => 'DTI / SEC Certificate',
            'bir_certificate' => 'BIR Certificate of Registration',
        ];
    @endphp

    <div class="space-y-6">

        {{-- VERIFICATION STATUS --}}
        <div class="p-6 border shadow-sm rounded-2xl {{ $statusClasses['card'] }}">
            <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex items-center justify-center bg-white rounded-full shadow-sm w-14 h-14 dark:bg-gray-800">
                        <i class="text-2xl fa-solid {{ $statusClasses['icon'] }}"></i>
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                Verification Status
                            </h2>

                            <span class="inline-flex px-3 py-1 text-sm font-medium rounded-full {{ $statusClasses['badge'] }}">
                                {{ ucfirst($spa->verification_status) }}
                            </span>
                        </div>

                        <h3 class="mt-3 text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $statusClasses['title'] }}
                        </h3>

                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                            {{ $statusClasses['description'] }}
                        </p>

                        @if ($spa->verification_status === 'verified' && $spa->verified_at)
                            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                Verified on {{ $spa->verified_at->format('F d, Y h:i A') }}
                            </p>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 md:w-[430px]">
                    @foreach ($documentLabels as $type => $label)
                        @php
                            $document = $spa->verificationDocuments->firstWhere('document_type', $type);
                        @endphp

                        <div class="flex flex-col h-full p-3 bg-white border rounded-xl dark:bg-gray-800 dark:border-gray-700 min-h-[96px]">
                            <p class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                {{ $label }}
                            </p>

                            <p class="mt-auto pt-3 text-sm font-semibold {{ $document ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-gray-500' }}">
                                {{ $document ? 'Uploaded' : 'Required' }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($spa->verification_status === 'rejected' && $spa->verification_remarks)
                <div class="p-4 mt-6 text-sm text-red-800 bg-red-100 border border-red-200 rounded-xl dark:bg-red-900/20 dark:text-red-300 dark:border-red-800">
                    <strong>Admin Remarks:</strong> {{ $spa->verification_remarks }}
                </div>
            @endif
        </div>

        {{-- SPA INFORMATION --}}
        <div class="p-6 bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Spa Information
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Update your spa's main business information.
            </p>

            <form method="POST" action="{{ route('owner.spa-profile.update') }}" class="mt-6 space-y-6">
                @csrf
                @method('PATCH')

                <div>
                    <x-input-label for="name" :value="__('Spa Name')" />
                    <x-text-input
                        id="name"
                        name="name"
                        type="text"
                        class="block w-full mt-1"
                        :value="old('name', $spa->name)"
                        :disabled="$spa->verification_status === 'verified'"
                        required
                    />
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>

                @if ($spa->verification_status !== 'verified')
                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                    </div>
                @endif
            </form>
        </div>

        {{-- VERIFICATION DOCUMENTS --}}
        <div class="p-6 bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Verification Documents
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Upload all required documents to submit your spa for verification.
            </p>

            @php
                $documentLabels = [
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
            @endphp

            <form method="POST"
                action="{{ route('owner.spa-profile.documents.upload') }}"
                enctype="multipart/form-data"
                class="mt-6 space-y-5">
                @csrf

                @foreach ($documentLabels as $type => $item)
                    @php
                        $document = $spa->verificationDocuments->firstWhere('document_type', $type);
                    @endphp

                    <div class="p-5 border rounded-xl dark:border-gray-700">
                        <div class="space-y-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $item['label'] }}
                                    </h3>

                                    @if ($document)
                                        <span class="inline-flex px-2.5 py-1 text-xs font-medium text-green-700 bg-green-100 border border-green-200 rounded-full dark:bg-green-900/20 dark:text-green-300 dark:border-green-800">
                                            Uploaded
                                        </span>
                                    @else
                                        <span class="inline-flex px-2.5 py-1 text-xs font-medium text-gray-700 bg-gray-100 border border-gray-200 rounded-full dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600">
                                            Required
                                        </span>
                                    @endif
                                </div>

                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $item['description'] }}
                                </p>

                                @if ($document)
                                    <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                                        <span class="font-medium">Current file:</span> {{ $document->file_name }}
                                    </p>

                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Uploaded on {{ $document->created_at->format('F d, Y h:i A') }}
                                    </p>

                                    <a href="{{ asset('storage/' . $document->file_path) }}"
                                        target="_blank"
                                        class="inline-flex mt-3 text-sm text-blue-600 underline dark:text-blue-400">
                                        View Current Document
                                    </a>
                                @else
                                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                                        No document uploaded yet.
                                    </p>
                                @endif
                            </div>

                            @if ($spa->verification_status !== 'verified')
                                <div class="pt-3 border-t dark:border-gray-700">
                                    <x-input-label
                                        for="document_file_{{ $type }}"
                                        :value="$document ? __('Replace File') : __('Upload File')"
                                    />

                                    {{-- Hidden native file input --}}
                                    <input
                                        id="document_file_{{ $type }}"
                                        type="file"
                                        name="documents[{{ $type }}]"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        style="position: fixed; top: -9999px; left: -9999px; opacity: 0; width: 0; height: 0;"
                                        onchange="handleFileChange(this, 'file_label_{{ $type }}')"
                                    />

                                    {{-- Themed upload button + selected filename --}}
                                    <div class="flex flex-wrap items-center gap-3 mt-2">
                                        <label
                                            for="document_file_{{ $type }}"
                                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white transition-colors duration-150 rounded-lg cursor-pointer"
                                            style="background-color: #8B7355;"
                                            onmouseover="this.style.backgroundColor='#7a6449'"
                                            onmouseout="this.style.backgroundColor='#8B7355'"
                                        >
                                            <i class="text-xs fa-solid fa-arrow-up-from-bracket"></i>
                                            {{ $document ? 'Replace File' : 'Choose File' }}
                                        </label>

                                        <span
                                            id="file_label_{{ $type }}"
                                            class="text-sm italic text-gray-500 dark:text-gray-400"
                                        >
                                            No file chosen
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if ($spa->verification_status !== 'verified')
                    <div class="flex justify-end pt-2">
                        <x-primary-button>
                            {{ __('Save Documents') }}
                        </x-primary-button>
                    </div>
                @endif
            </form>

            <div class="p-4 mt-6 text-sm text-gray-700 border rounded-xl bg-gray-50 dark:bg-gray-700/50 dark:text-gray-200 dark:border-gray-700">
                Accepted formats: PDF, JPG, JPEG, PNG. Maximum file size: 10 MB per document.
            </div>
        </div>
    </div>
</div>

<script>
    function handleFileChange(input, labelId) {
        const label = document.getElementById(labelId);
        if (input.files && input.files.length > 0) {
            label.textContent = input.files[0].name;
            label.classList.remove('italic', 'text-gray-500', 'dark:text-gray-400');
            label.classList.add('text-gray-800', 'dark:text-gray-200', 'font-medium');
        } else {
            label.textContent = 'No file chosen';
            label.classList.add('italic', 'text-gray-500', 'dark:text-gray-400');
            label.classList.remove('text-gray-800', 'dark:text-gray-200', 'font-medium');
        }
    }
</script>

@endsection
