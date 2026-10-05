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

        $hasAllDocuments =
            collect($requiredTypes)
                ->every(fn ($type) => $documents->has($type));

        $maxUploadBytes = 10 * 1024 * 1024;
    @endphp

    <div class="max-w-3xl px-4 py-8 mx-auto">

        <div class="relative mb-8 text-center">

            @php
                $branch = $spa->branches()->first();
            @endphp

            @if($branch)
                <a href="{{ route('setup.operating-hours', $branch) }}"
                    class="absolute left-0 inline-flex items-center min-h-[44px] text-sm text-gray-600 hover:text-[#8B7355] transition-colors duration-200">

                    <i class="fa-solid fa-circle-chevron-left text-3xl text-[#8B7355]"></i>

                </a>
            @endif

            <img
                src="{{ asset('images/1.png') }}"
                alt="Levictas"
                class="mx-auto rounded-md h-14"
            />

            <h2 class="mt-5 text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display']">
                Business Verification
            </h2>

            <p class="max-w-2xl mx-auto mt-3 text-sm leading-6 text-gray-600 dark:text-gray-400">
                Upload the required documents so the platform administrator can review and verify your spa business.
            </p>

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

                    <div class="w-16 sm:w-24 h-1 mx-4 rounded bg-[#8B7355]/30"></div>

                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 text-white rounded-full bg-[#8B7355]">
                            <i class="text-sm fa-solid fa-check"></i>
                        </div>

                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Main Branch
                        </span>
                    </div>

                    <div class="w-16 sm:w-24 h-1 mx-4 rounded bg-[#8B7355]/30"></div>

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
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                            Documents ready for administrator review
                        </h3>

                        <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                            All required documents have been uploaded. Your spa verification status is currently {{ ucfirst($spa->verification_status ?? 'pending') }}.
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
            class="space-y-4">

            @csrf

            @foreach($documentMeta as $type => $meta)

                @php
                    $document = $documents->get($type);
                    $fieldName = 'documents.' . $type;
                    $hasError = $errors->has($fieldName);
                @endphp

                <div class="p-4 bg-white border rounded-2xl sm:p-5 dark:bg-gray-800 {{ $hasError ? 'border-red-300 dark:border-red-800' : 'border-gray-200 dark:border-gray-700' }}">

                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

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

                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                {{ $meta['description'] }}
                            </p>

                            @if($document)

                                <p class="mt-3 text-sm text-gray-700 break-all dark:text-gray-300">
                                    <span class="font-medium">Current file:</span>
                                    {{ $document->file_name }}
                                </p>

                            @endif

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
                            />

                            <label
                                for="document_{{ $type }}"
                                class="inline-flex items-center justify-center gap-2 min-h-[44px] px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 cursor-pointer rounded-xl hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">

                                <i class="fa-solid fa-arrow-up-from-bracket"></i>

                                {{ $document ? 'Replace' : 'Choose File' }}

                            </label>

                        </div>

                    </div>

                    <p
                        id="file_label_{{ $type }}"
                        class="mt-3 text-xs italic text-gray-500 dark:text-gray-400">

                        No new file chosen

                    </p>

                    <p
                        id="file_error_{{ $type }}"
                        class="mt-1 text-xs text-red-600 dark:text-red-400 {{ $hasError ? '' : 'hidden' }}">

                        {{ $errors->first($fieldName) }}

                    </p>

                </div>

            @endforeach

            <p
                id="documentsFormError"
                class="hidden text-sm text-red-600 dark:text-red-400"
                role="alert">
            </p>

            <button
                type="submit"
                id="uploadDocumentsButton"
                class="inline-flex items-center justify-center w-full min-h-[44px] px-4 py-3 text-sm font-semibold text-white bg-gradient-to-r from-[#7A6348] to-[#6F5430] rounded-xl hover:opacity-90 disabled:opacity-60 disabled:cursor-not-allowed">

                <i class="mr-2 fa-solid fa-arrow-up-from-bracket"></i>

                {{ $hasAllDocuments ? 'Update Documents' : 'Submit Documents' }}

            </button>

        </form>

        @if($hasAllDocuments)
            <div class="pt-5 mt-5 border-t border-gray-200 dark:border-gray-700">
                <button
                    type="button"
                    id="finishSetupButton"
                    class="inline-flex items-center justify-center w-full min-h-[44px] px-4 py-3 text-sm font-semibold text-white bg-gradient-to-r from-[#7A6348] to-[#6F5430] rounded-xl hover:opacity-90 disabled:opacity-60 disabled:cursor-not-allowed">

                    <i class="mr-2 fa-solid fa-circle-check"></i>

                    Finish Setup & Go to Dashboard
                </button>
            </div>
        @endif
    </div>

    <div
        id="setupSuccessOverlay"
        class="fixed inset-0 z-50 flex items-center justify-center invisible px-4 transition-opacity duration-300 opacity-0 bg-black/60 backdrop-blur-sm"
        aria-hidden="true">

        <div class="w-full max-w-md p-8 text-center bg-white border border-gray-200 shadow-xl rounded-2xl dark:bg-gray-800 dark:border-gray-700">

            <div class="relative flex items-center justify-center mx-auto mb-6 w-28 h-28">

                <div class="absolute inset-0 rounded-full bg-emerald-100 dark:bg-emerald-900/30 animate-ping setup-success-ping"></div>

                <div class="relative flex items-center justify-center w-24 h-24 border-4 rounded-full border-emerald-100 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/30">

                    <div
                        id="setupSuccessSpinner"
                        class="w-12 h-12 border-4 rounded-full border-emerald-200 border-t-emerald-600 animate-spin dark:border-emerald-900 dark:border-t-emerald-400">
                    </div>

                    <i
                        id="setupSuccessCheck"
                        class="absolute hidden text-4xl text-emerald-600 fa-solid fa-check dark:text-emerald-400">
                    </i>

                </div>

            </div>

            <p
                id="setupSuccessLabel"
                class="text-xs font-semibold tracking-widest uppercase text-emerald-600 dark:text-emerald-400">
                Finalizing Setup
            </p>

            <h2
                id="setupSuccessTitle"
                class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white font-['Playfair_Display']">
                Preparing your business
            </h2>

            <p
                id="setupSuccessMessage"
                class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                We're saving your setup and getting your dashboard ready.
            </p>

            <div class="w-full h-1.5 mt-6 overflow-hidden bg-gray-100 rounded-full dark:bg-gray-700">
                <div
                    id="setupSuccessProgress"
                    class="h-full transition-all duration-700 rounded-full bg-emerald-600"
                    style="width: 15%;">
                </div>
            </div>

        </div>

    </div>

    <style>
        @media (prefers-reduced-motion: reduce) {
            .setup-success-ping,
            #setupSuccessSpinner {
                animation: none !important;
            }

            #setupSuccessOverlay,
            #setupSuccessProgress {
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
                                `"${file.name}" exceeds the 10 MB maximum file size.`;

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
                        'Choose at least one document before submitting.';

                    error.classList.remove(
                        'hidden'
                    );

                    return;
                }

                submitButton.disabled = true;
                submitButton.innerHTML =
                    '<i class="mr-2 fa-solid fa-spinner fa-spin"></i> Uploading...';
            }
        );

        const finishSetupButton =
    document.getElementById(
        'finishSetupButton'
    );

    const successOverlay =
        document.getElementById(
            'setupSuccessOverlay'
        );

    if (
        finishSetupButton &&
        successOverlay
    ) {
        finishSetupButton.addEventListener(
            'click',
            function () {
                finishSetupButton.disabled = true;

                successOverlay.classList.remove(
                    'invisible',
                    'opacity-0'
                );

                successOverlay.classList.add(
                    'opacity-100'
                );

                successOverlay.setAttribute(
                    'aria-hidden',
                    'false'
                );

                document.body.style.overflow =
                    'hidden';

                const progress =
                    document.getElementById(
                        'setupSuccessProgress'
                    );

                const spinner =
                    document.getElementById(
                        'setupSuccessSpinner'
                    );

                const check =
                    document.getElementById(
                        'setupSuccessCheck'
                    );

                const label =
                    document.getElementById(
                        'setupSuccessLabel'
                    );

                const title =
                    document.getElementById(
                        'setupSuccessTitle'
                    );

                const message =
                    document.getElementById(
                        'setupSuccessMessage'
                    );

                requestAnimationFrame(
                    function () {
                        progress.style.width = '70%';
                    }
                );

                setTimeout(
                    function () {
                        progress.style.width = '100%';

                        spinner.classList.add(
                            'hidden'
                        );

                        check.classList.remove(
                            'hidden'
                        );

                        label.textContent =
                            'Setup Complete';

                        title.textContent =
                            'Your business is ready';

                        message.textContent =
                            'Everything is set. Redirecting you to your dashboard.';
                    },
                    900
                );

                setTimeout(
                    function () {
                        window.location.href =
                            @json(route('setup.complete'));
                    },
                    1800
                );
            }
        );
    }

        window.addEventListener(
            'pageshow',
            function () {
                submitButton.disabled = false;
            }
        );
    </script>

</x-guest-layout>
