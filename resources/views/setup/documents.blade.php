<x-guest-layout>

    @php
        $documentMeta = [
            'government_id' => [
                'label' => 'Government ID',
                'hint' => 'Owner or authorized representative. Name and photo must be readable.',
                'icon' => 'fa-id-card',
            ],
            'dti_sec' => [
                'label' => 'DTI / SEC Certificate',
                'hint' => 'DTI Business Name Certificate or SEC Registration Certificate.',
                'icon' => 'fa-building',
            ],
            'bir_certificate' => [
                'label' => 'BIR Certificate of Registration',
                'hint' => 'Proof that the business is registered for tax purposes.',
                'icon' => 'fa-file-invoice',
            ],
            'business_permit' => [
                'label' => 'Business Permit',
                'hint' => 'A valid business or mayor\'s permit for this spa.',
                'icon' => 'fa-store',
            ],
        ];

        $documents = $spa
            ->verificationDocuments
            ->keyBy('document_type');

        $totalCount = count($documentMeta);

        $readyCount = collect(array_keys($documentMeta))
            ->filter(fn ($type) => $documents->has($type))
            ->count();

        // Same rule the script applies: every type covered AND at least one
        // new file chosen. On first render nothing is chosen yet.
        $allReady = $readyCount === $totalCount;

        $initialStatus = $allReady
            ? 'Choose a replacement for the flagged document to resubmit.'
            : "{$readyCount} of {$totalCount} ready. Finish later saves your files and signs you out.";

        $maxUploadBytes = 10 * 1024 * 1024;

        $isRejected = $spa->verification_status === 'rejected';
    @endphp

    {{-- max-md:pb-28 keeps the last row clear of the fixed mobile action bar. --}}
    <div class="px-4 py-6 sm:px-8 sm:py-8 max-md:pb-28">
        <div class="max-w-3xl mx-auto">

            <div class="relative mb-6 text-center sm:mb-8">
                <a
                    href="{{ route('setup.branches') }}"
                    aria-label="Back to main branch"
                    class="absolute top-0 left-0 inline-flex items-center justify-center min-w-[44px] min-h-[44px] rounded-full hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]/40"
                >
                    <i class="text-2xl sm:text-3xl fa-solid fa-circle-chevron-left text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                </a>

                <img
                    src="{{ asset('images/1.png') }}"
                    alt="Levictas"
                    class="h-12 mx-auto rounded-md sm:h-14"
                >

                <h1 class="mt-4 text-2xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display'] sm:text-3xl">
                    Business Verification
                </h1>

                <p class="max-w-2xl mx-auto mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                    Upload these documents so an administrator can verify your spa.
                    It stays hidden from customers until it is verified.
                </p>
            </div>

            <x-setup-stepper :current="3" />

            @if($isRejected)
                <div class="flex items-start gap-3 p-4 mb-5 border border-red-200 rounded-2xl bg-red-50 dark:border-red-800 dark:bg-red-900/20" role="status">
                    <i class="mt-0.5 text-red-600 fa-solid fa-circle-exclamation dark:text-red-300" aria-hidden="true"></i>

                    <div class="min-w-0 text-sm leading-6">
                        <p class="font-semibold text-red-800 dark:text-red-200">
                            Your submission was sent back
                        </p>

                        @if($spa->verification_remarks)
                            <p class="text-red-700 dark:text-red-300">
                                <span class="font-medium">Administrator remarks:</span>
                                {{ $spa->verification_remarks }}
                            </p>
                        @endif

                        <p class="text-red-700 dark:text-red-300">
                            Replace the incorrect document, then submit again.
                        </p>
                    </div>
                </div>
            @endif

            <form
                id="setupDocumentsForm"
                method="POST"
                action="{{ route('owner.spa-profile.documents.upload') }}"
                enctype="multipart/form-data"
            >
                @csrf

                <div class="flex items-center justify-between gap-3 mb-2">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                        Required documents
                    </h2>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        PDF, JPG or PNG · max 10 MB each
                    </p>
                </div>

                <ul role="list" class="overflow-hidden bg-white border border-gray-200 divide-y divide-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 dark:divide-gray-700">
                    @foreach($documentMeta as $type => $meta)
                        @php
                            $document = $documents->get($type);
                            $fieldName = 'documents.' . $type;
                            $hasError = $errors->has($fieldName);

                            $defaultFileText = $document
                                ? 'Current file: ' . $document->file_name
                                : 'No file uploaded yet';

                            [$pillText, $pillClass] = match (true) {
                                $hasError => ['Needs attention', 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
                                (bool) $document => ['Uploaded', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
                                default => ['Required', 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300'],
                            };
                        @endphp

                        <li class="p-4 sm:p-5 {{ $hasError ? 'bg-red-50/60 dark:bg-red-900/10' : '' }}">
                            <div class="flex items-start gap-3 sm:gap-4">

                                <div class="items-center justify-center hidden w-10 h-10 sm:flex shrink-0 rounded-xl bg-[#8B7355]/10 text-[#6F5430] dark:bg-[#C4A97D]/10 dark:text-[#C4A97D]">
                                    <i class="fa-solid {{ $meta['icon'] }}" aria-hidden="true"></i>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ $meta['label'] }}
                                        </h3>

                                        <span data-pill="default" class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $pillClass }}">
                                            {{ $pillText }}
                                        </span>

                                        <span data-pill="chosen" class="hidden px-2 py-0.5 text-xs font-medium rounded-full bg-[#8B7355]/10 text-[#6F5430] dark:bg-[#C4A97D]/15 dark:text-[#C4A97D]">
                                            Ready to upload
                                        </span>
                                    </div>

                                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">
                                        {{ $meta['hint'] }}
                                    </p>

                                    @if($document?->review_remarks)
                                        <p class="mt-1 text-xs leading-5 text-amber-700 dark:text-amber-300">
                                            <span class="font-medium">Sent back:</span>
                                            {{ $document->review_remarks }}
                                        </p>
                                    @endif

                                    <p
                                        id="file_label_{{ $type }}"
                                        class="mt-2 text-xs text-gray-700 break-all dark:text-gray-300"
                                    >
                                        {{ $defaultFileText }}
                                    </p>

                                    <p
                                        id="file_error_{{ $type }}"
                                        class="mt-1 text-xs leading-5 text-red-600 dark:text-red-400 {{ $hasError ? '' : 'hidden' }}"
                                        role="alert"
                                    >
                                        {{ $errors->first($fieldName) }}
                                    </p>
                                </div>

                                <div class="shrink-0">
                                    <input
                                        id="document_{{ $type }}"
                                        type="file"
                                        name="documents[{{ $type }}]"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        class="sr-only peer"
                                        aria-describedby="file_label_{{ $type }} file_error_{{ $type }}"
                                        data-max-bytes="{{ $maxUploadBytes }}"
                                        data-has-document="{{ $document ? '1' : '0' }}"
                                        data-default-text="{{ $defaultFileText }}"
                                        data-label-id="file_label_{{ $type }}"
                                        data-error-id="file_error_{{ $type }}"
                                    >

                                    <label
                                        for="document_{{ $type }}"
                                        class="inline-flex items-center justify-center gap-2 min-h-[44px] px-3 sm:px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 cursor-pointer rounded-xl hover:bg-gray-50 peer-focus-visible:ring-2 peer-focus-visible:ring-[#8B7355]/40 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                                    >
                                        <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i>
                                        {{ $document ? 'Replace' : 'Choose' }}
                                        <span class="sr-only">{{ $meta['label'] }}</span>
                                    </label>
                                </div>

                            </div>
                        </li>
                    @endforeach
                </ul>

                {{--
                    Below md this bar is fixed to the viewport (thumb zone); from md up
                    it sits in normal flow, where the whole step fits on one screen.
                    position:fixed works only while no ancestor (guest layout card)
                    has a transform or backdrop-filter — keep it that way.
                    DOM order stays rows-then-actions, so tab order is unchanged.
                --}}
                <div class="fixed inset-x-0 bottom-0 z-30 px-4 py-3 bg-white border-t border-gray-200 shadow-[0_-8px_24px_rgba(0,0,0,0.08)] dark:bg-gray-800 dark:border-gray-700 md:static md:z-auto md:p-0 md:mt-6 md:bg-transparent md:border-0 md:shadow-none md:dark:bg-transparent">
                    <div class="max-w-3xl mx-auto">
                        <p
                            id="documentsStatus"
                            aria-live="polite"
                            class="mb-2 text-xs text-center text-gray-600 md:mb-3 md:text-sm dark:text-gray-300"
                        >
                            {{ $initialStatus }}
                        </p>

                        {{-- name/value travel with the clicked button; the controller reads "intent". --}}
                        <div class="grid grid-cols-2 gap-2 md:gap-3">
                            <button
                                type="submit"
                                id="finishLaterButton"
                                name="intent"
                                value="later"
                                class="inline-flex items-center justify-center w-full min-h-[48px] gap-2 px-3 py-3 text-sm font-semibold text-[#6F5430] bg-white border border-[#8B7355]/40 rounded-xl hover:bg-[#8B7355]/5 disabled:opacity-60 disabled:cursor-not-allowed dark:bg-transparent dark:text-[#C4A97D] dark:border-[#C4A97D]/40 dark:hover:bg-[#C4A97D]/10"
                            >
                                <i class="hidden fa-solid fa-clock-rotate-left sm:inline" aria-hidden="true"></i>
                                Finish later
                            </button>

                            <button
                                type="submit"
                                id="uploadDocumentsButton"
                                name="intent"
                                value="submit"
                                @disabled(true)
                                class="inline-flex items-center justify-center w-full min-h-[48px] gap-2 px-3 py-3 text-sm font-semibold text-white bg-gradient-to-r from-[#7A6348] to-[#6F5430] rounded-xl hover:opacity-90 disabled:opacity-60 disabled:cursor-not-allowed"
                            >
                                <i class="hidden fa-solid fa-paper-plane sm:inline" aria-hidden="true"></i>
                                Submit for review
                            </button>
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('setupDocumentsForm');

            if (!form) {
                return;
            }

            const inputs = Array.from(
                form.querySelectorAll('input[type="file"]')
            );

            const submitButton = document.getElementById('uploadDocumentsButton');
            const finishLaterButton = document.getElementById('finishLaterButton');
            const status = document.getElementById('documentsStatus');

            const SUBMIT_LABEL = submitButton.innerHTML;
            const LATER_LABEL = finishLaterButton.innerHTML;
            const TOTAL = inputs.length;

            function hasChosenFile(input) {
                return !!(input.files && input.files.length > 0);
            }

            function setPill(input, chosen) {
                const row = input.closest('li');

                row.querySelector('[data-pill="default"]')
                    .classList.toggle('hidden', chosen);

                row.querySelector('[data-pill="chosen"]')
                    .classList.toggle('hidden', !chosen);
            }

            // Submit needs every type covered (stored or chosen) and at least one
            // new file, which mirrors SpaProfileController::uploadDocument.
            function updateStatus() {
                let ready = 0;
                let chosen = 0;

                inputs.forEach(function (input) {
                    const picked = hasChosenFile(input);

                    if (picked) {
                        chosen++;
                    }

                    if (picked || input.dataset.hasDocument === '1') {
                        ready++;
                    }
                });

                const complete = ready === TOTAL;

                submitButton.disabled = !(complete && chosen > 0);

                if (!complete) {
                    status.textContent =
                        ready + ' of ' + TOTAL +
                        ' ready. Finish later saves your files and signs you out.';
                } else if (chosen > 0) {
                    status.textContent =
                        'All ' + TOTAL + ' documents ready. Submit to send them for review.';
                } else {
                    status.textContent =
                        'Choose a replacement for the flagged document to resubmit.';
                }
            }

            inputs.forEach(function (input) {
                input.addEventListener('change', function () {
                    const label = document.getElementById(input.dataset.labelId);
                    const error = document.getElementById(input.dataset.errorId);
                    const file = input.files && input.files[0];

                    error.textContent = '';
                    error.classList.add('hidden');

                    if (file && file.size > parseInt(input.dataset.maxBytes, 10)) {
                        input.value = '';
                        error.textContent = '"' + file.name + '" exceeds the 10 MB maximum file size.';
                        error.classList.remove('hidden');
                    }

                    const picked = hasChosenFile(input);

                    label.textContent = picked
                        ? 'Selected: ' + input.files[0].name +
                            (input.dataset.hasDocument === '1' ? ' (replaces current file)' : '')
                        : input.dataset.defaultText;

                    setPill(input, picked);
                    updateStatus();
                });
            });

            form.addEventListener('submit', function (event) {
                const isFinishLater = event.submitter === finishLaterButton;
                const busyButton = isFinishLater ? finishLaterButton : submitButton;

                busyButton.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving...';

                // Disable on the next tick: a button disabled during the submit
                // event is dropped from the form data, and the controller needs
                // the clicked button's "intent" value.
                setTimeout(function () {
                    submitButton.disabled = true;
                    finishLaterButton.disabled = true;
                }, 0);
            });

            // Restores the buttons when the page comes back from the bfcache.
            window.addEventListener('pageshow', function () {
                submitButton.innerHTML = SUBMIT_LABEL;
                finishLaterButton.innerHTML = LATER_LABEL;
                finishLaterButton.disabled = false;

                inputs.forEach(function (input) {
                    setPill(input, hasChosenFile(input));
                });

                updateStatus();
            });

            updateStatus();
        })();
    </script>

</x-guest-layout>