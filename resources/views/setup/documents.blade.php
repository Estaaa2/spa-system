<x-guest-layout>

    @php
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
            'business_permit' => [
                'label' => 'Business Permit',
                'description' => 'Upload a valid business or mayor\'s permit as proof that the spa is authorized to operate.',
            ],
        ];

        $documents = $spa
            ->verificationDocuments
            ->keyBy('document_type');

        $requiredTypes = array_keys($documentMeta);

        $hasAllDocuments = collect($requiredTypes)
            ->every(fn ($type) => $documents->has($type));

        $maxUploadBytes = 10 * 1024 * 1024;

        $isRejected = $spa->verification_status === 'rejected';
    @endphp

    <div class="max-w-3xl px-4 py-8 mx-auto">

        <div class="relative mb-8 text-center">

            @php
                $branch = $spa->branches()->first();
            @endphp

            @if($branch)
                <a
                    href="{{ route('setup.branches') }}"
                    aria-label="Back to main branch"
                    class="absolute left-0 inline-flex items-center min-h-[44px] text-sm text-gray-600 transition-colors hover:text-[#8B7355]"
                >
                    <i class="text-3xl fa-solid fa-circle-chevron-left text-[#8B7355]"></i>
                </a>
            @endif

            <img
                src="{{ asset('images/1.png') }}"
                alt="Levictas"
                class="mx-auto rounded-md h-14"
            >

            <h2 class="mt-5 text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display']">
                Business Verification
            </h2>

            <p class="max-w-2xl mx-auto mt-3 text-sm leading-6 text-gray-600 dark:text-gray-400">
                Upload the required documents so the platform administrator can review and verify your spa business.
            </p>

            @if($isRejected)
                <div class="p-4 mt-6 text-left border border-red-200 rounded-2xl bg-red-50 dark:border-red-800 dark:bg-red-900/20">
                    <div class="flex items-start gap-3">
                        <i class="mt-0.5 text-red-600 fa-solid fa-circle-exclamation dark:text-red-300"></i>

                        <div>
                            <h3 class="text-sm font-semibold text-red-800 dark:text-red-200">
                                Your document submission was rejected
                            </h3>

                            <p class="mt-1 text-sm leading-6 text-red-700 dark:text-red-300">
                                Replace the incorrect document, then click
                                <strong>Save and Submit for Review</strong>.
                            </p>

                            @if($spa->verification_remarks)
                                <p class="mt-3 text-sm leading-6 text-red-800 dark:text-red-200">
                                    <span class="font-semibold">
                                        Administrator remarks:
                                    </span>

                                    {{ $spa->verification_remarks }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

        </div>

        <div class="mb-10">
            <div class="flex items-center justify-center overflow-x-auto">
                <div class="flex items-center min-w-max">

                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 text-white rounded-full bg-[#8B7355]">
                            <i class="text-sm fa-solid fa-check"></i>
                        </div>

                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Business Info
                        </span>
                    </div>

                    <div class="w-16 h-1 mx-4 rounded sm:w-24 bg-[#8B7355]/30"></div>

                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 text-white rounded-full bg-[#8B7355]">
                            <i class="text-sm fa-solid fa-check"></i>
                        </div>

                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Main Branch
                        </span>
                    </div>

                    <div class="w-16 h-1 mx-4 rounded sm:w-24 bg-[#8B7355]/30"></div>

                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 text-white rounded-full bg-[#8B7355]">
                            3
                        </div>

                        <span class="ml-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Documents
                        </span>
                    </div>

                </div>
            </div>
        </div>

        @if($hasAllDocuments)
            <div class="p-5 mb-5 border border-amber-200 bg-amber-50 rounded-2xl dark:border-amber-800 dark:bg-amber-900/10">
                <div class="flex items-start gap-3">
                    <div class="flex items-center justify-center w-10 h-10 bg-white text-amber-600 rounded-xl shrink-0 dark:bg-gray-800 dark:text-amber-400">
                        <i class="fa-solid fa-file-circle-check"></i>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                            Documents ready for administrator review
                        </h3>

                        <p class="mt-1 text-sm leading-6 text-amber-700 dark:text-amber-300">
                            All required documents are uploaded. Review your files, then submit them for administrator review.
                        </p>
                    </div>
                </div>
            </div>
        @else
            <div class="p-4 mb-5 text-sm text-blue-800 border border-blue-200 bg-blue-50 rounded-xl dark:border-blue-800 dark:bg-blue-900/10 dark:text-blue-300">
                Accepted formats: PDF, JPG, JPEG and PNG. Maximum file size is 10 MB per document.
            </div>
        @endif

        <form
            id="setupDocumentsForm"
            method="POST"
            action="{{ route('owner.spa-profile.documents.upload') }}"
            enctype="multipart/form-data"
            class="grid grid-cols-1 gap-4 md:grid-cols-2"
        >
            @csrf

            @foreach($documentMeta as $type => $meta)
                @php
                    $document = $documents->get($type);
                    $fieldName = 'documents.' . $type;
                    $hasError = $errors->has($fieldName);
                @endphp

                <div class="h-full p-4 bg-white border rounded-2xl sm:p-5 dark:bg-gray-800 {{ $hasError ? 'border-red-300 dark:border-red-800' : 'border-gray-200 dark:border-gray-700' }}">

                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $meta['label'] }}
                                </h3>

                                @if($document)
                                    <span class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                        Uploaded
                                    </span>
                                @else
                                    <span class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300">
                                        Required
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="shrink-0">
                            <input
                                id="document_{{ $type }}"
                                type="file"
                                name="documents[{{ $type }}]"
                                accept=".pdf,.jpg,.jpeg,.png"
                                class="sr-only"
                                data-max-bytes="{{ $maxUploadBytes }}"
                                data-label-id="file_label_{{ $type }}"
                                data-error-id="file_error_{{ $type }}"
                            >

                            <label
                                for="document_{{ $type }}"
                                class="inline-flex items-center justify-center gap-2 min-h-[44px] px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 cursor-pointer rounded-xl hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                            >
                                <i class="fa-solid fa-arrow-up-from-bracket"></i>
                                {{ $document ? 'Replace' : 'Choose File' }}
                            </label>
                        </div>
                    </div>

                    <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-400">
                        {{ $meta['description'] }}
                    </p>

                    @if($document)
                        <p class="mt-3 text-sm text-gray-700 break-all dark:text-gray-300">
                            <span class="font-medium">Current file:</span>
                            {{ $document->file_name }}
                        </p>
                    @endif

                    <p
                        id="file_label_{{ $type }}"
                        class="mt-3 text-xs italic text-gray-500 dark:text-gray-400"
                    >
                        No new file chosen
                    </p>

                    <p
                        id="file_error_{{ $type }}"
                        class="mt-1 text-xs text-red-600 dark:text-red-400 {{ $hasError ? '' : 'hidden' }}"
                        role="alert"
                    >
                        {{ $errors->first($fieldName) }}
                    </p>
                </div>
            @endforeach

            <p
                id="documentsFormError"
                class="hidden text-sm text-red-600 dark:text-red-400 md:col-span-2"
                role="alert"
            ></p>

            <button
                type="submit"
                id="uploadDocumentsButton"
                class="inline-flex items-center justify-center w-full min-h-[48px] gap-2 px-4 py-3 text-sm font-semibold text-white bg-gradient-to-r from-[#7A6348] to-[#6F5430] rounded-xl hover:opacity-90 disabled:opacity-60 disabled:cursor-not-allowed md:col-span-2"
            >
                <i class="fa-solid fa-paper-plane"></i>
                Save and Submit for Review
            </button>

            <p class="text-xs leading-5 text-center text-gray-500 md:col-span-2 dark:text-gray-400">
                Valid documents will be preserved even if another selected document fails validation.
            </p>
        </form>

    </div>

    <style>
        @media (prefers-reduced-motion: reduce) {
            #uploadDocumentsButton {
                transition: none !important;
            }
        }
    </style>

    <script>
        document
            .querySelectorAll(
                '#setupDocumentsForm input[type="file"]'
            )
            .forEach(function (input) {
                input.addEventListener(
                    'change',
                    function () {
                        const label =
                            document.getElementById(
                                input.dataset.labelId
                            );

                        const error =
                            document.getElementById(
                                input.dataset.errorId
                            );

                        const file =
                            input.files &&
                            input.files[0];

                        error.textContent = '';
                        error.classList.add('hidden');

                        if (!file) {
                            label.textContent =
                                'No new file chosen';

                            return;
                        }

                        const maxBytes =
                            parseInt(
                                input.dataset.maxBytes,
                                10
                            );

                        if (file.size > maxBytes) {
                            input.value = '';

                            label.textContent =
                                'No new file chosen';

                            error.textContent =
                                '"' +
                                file.name +
                                '" exceeds the 10 MB maximum file size.';

                            error.classList.remove(
                                'hidden'
                            );

                            return;
                        }

                        label.textContent =
                            file.name;
                    }
                );
            });

        const form =
            document.getElementById(
                'setupDocumentsForm'
            );

        const submitButton =
            document.getElementById(
                'uploadDocumentsButton'
            );

        if (form && submitButton) {
            form.addEventListener(
                'submit',
                function (event) {
                    const chosen =
                        Array.from(
                            form.querySelectorAll(
                                'input[type="file"]'
                            )
                        ).some(function (input) {
                            return input.files &&
                                input.files.length > 0;
                        });

                    if (!chosen) {
                        event.preventDefault();

                        const error =
                            document.getElementById(
                                'documentsFormError'
                            );

                        error.textContent =
                            'Choose at least one document before submitting for review.';

                        error.classList.remove(
                            'hidden'
                        );

                        return;
                    }

                    submitButton.disabled = true;

                    submitButton.innerHTML =
                        '<i class="mr-2 fa-solid fa-spinner fa-spin"></i> Saving and Submitting...';
                }
            );
        }

        window.addEventListener(
            'pageshow',
            function () {
                if (submitButton) {
                    submitButton.disabled = false;

                    submitButton.innerHTML =
                        '<i class="fa-solid fa-paper-plane"></i> Save and Submit for Review';
                }
            }
        );
    </script>

</x-guest-layout>