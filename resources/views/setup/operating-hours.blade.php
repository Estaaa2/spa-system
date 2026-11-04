<x-guest-layout>
    <div class="min-h-screen px-4 py-6">

        <div class="max-w-3xl mx-auto">

            <div class="relative mb-8 text-center">

                <a href="{{ route('setup.branches') }}"
                   class="absolute left-0 inline-flex items-center min-h-[44px] text-sm text-gray-600 hover:text-[#8B7355] transition-colors duration-200">

                    <i class="fa-solid fa-circle-chevron-left text-3xl text-[#8B7355]"></i>

                </a>

                <img src="{{ asset('images/1.png') }}" alt="Levictas" class="mx-auto rounded-md h-14"/>

                <h2 class="mt-3 text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display']">
                    Operating Hours
                </h2>

                <p class="mt-1 text-sm font-medium text-[#6F5430] dark:text-[#C4A97D]">
                    {{ $branch->name }}
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
                            <div class="flex items-center justify-center w-10 h-10 text-gray-400 bg-gray-200 rounded-full dark:bg-gray-700 dark:text-gray-300">
                                3
                            </div>

                            <span class="ml-3 text-sm font-medium text-gray-500 dark:text-gray-400">
                                Documents
                            </span>
                        </div>

                    </div>
                </div>
            </div>

            @if ($errors->any())
                <div class="p-4 mb-6 text-sm text-red-700 bg-red-50 rounded-xl ring-1 ring-red-200 dark:bg-red-900/10 dark:text-red-300 dark:ring-red-800">

                    <p class="mb-1 font-semibold">
                        Please fix the following:
                    </p>

                    <ul class="space-y-1 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>

                </div>
            @endif

            <form method="POST" action="{{ route('setup.update-operating-hours', $branch) }}" id="operating-hours-form">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">

                    @foreach($operatingHours as $hour)

                        <div class="p-4 transition bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700"
                             id="card_{{ $hour->id }}">

                            <div class="flex items-center justify-between mb-4">

                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $hour->day_of_week }}
                                </h4>

                                <label class="flex items-center gap-2 cursor-pointer">

                                    <input type="hidden"
                                        name="hours[{{ $loop->index }}][is_closed]"
                                        value="0"/>

                                    <input
                                        type="checkbox"
                                        name="hours[{{ $loop->index }}][is_closed]"
                                        value="1"
                                        {{ $hour->is_closed ? 'checked' : '' }}
                                        class="w-4 h-4 rounded text-[#8B7355] border-gray-300 focus:ring-[#8B7355]/40"
                                        onchange="toggleTimeInputs(this, 'opening_{{ $hour->id }}', 'closing_{{ $hour->id }}', 'card_{{ $hour->id }}')"
                                    />

                                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                                        Closed
                                    </span>

                                </label>

                            </div>

                            <div class="grid grid-cols-2 gap-3"
                                 id="times_{{ $hour->id }}"
                                 style="{{ $hour->is_closed ? 'opacity:0.4; pointer-events:none;' : '' }}">

                                <div>

                                    <label class="block mb-1 text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                        Opens
                                    </label>

                                    <input
                                        type="time"
                                        id="opening_{{ $hour->id }}"
                                        name="hours[{{ $loop->index }}][opening_time]"
                                        value="{{ substr($hour->opening_time, 0, 5) }}"
                                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-white dark:bg-gray-700 dark:border-gray-600 text-gray-900 dark:text-white focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none"
                                        {{ $hour->is_closed ? 'disabled' : '' }}
                                        onchange="validateTimeRange('opening_{{ $hour->id }}', 'closing_{{ $hour->id }}', 'time_error_{{ $hour->id }}', 'card_{{ $hour->id }}')"
                                    />

                                </div>

                                <div>

                                    <label class="block mb-1 text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                        Closes
                                    </label>

                                    <input
                                        type="time"
                                        id="closing_{{ $hour->id }}"
                                        name="hours[{{ $loop->index }}][closing_time]"
                                        value="{{ substr($hour->closing_time, 0, 5) }}"
                                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-white dark:bg-gray-700 dark:border-gray-600 text-gray-900 dark:text-white focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none"
                                        {{ $hour->is_closed ? 'disabled' : '' }}
                                        onchange="validateTimeRange('opening_{{ $hour->id }}', 'closing_{{ $hour->id }}', 'time_error_{{ $hour->id }}', 'card_{{ $hour->id }}')"
                                    />

                                </div>

                            </div>

                            <p class="flex items-center hidden gap-1 mt-2 text-xs text-red-600"
                                id="time_error_{{ $hour->id }}">

                                <i class="fa-solid fa-circle-exclamation"></i>

                                Closing time must be after opening time.

                            </p>

                            @if($hour->is_closed)

                                <p class="mt-3 text-xs italic text-center text-gray-500 dark:text-gray-400"
                                   id="closed_label_{{ $hour->id }}">
                                    Closed all day
                                </p>

                            @else

                                <p class="hidden mt-3 text-xs italic text-center text-gray-500 dark:text-gray-400"
                                   id="closed_label_{{ $hour->id }}">
                                    Closed all day
                                </p>

                            @endif

                            <input
                                type="hidden"
                                name="hours[{{ $loop->index }}][id]"
                                value="{{ $hour->id }}"
                            />

                        </div>

                    @endforeach

                    <div class="col-span-1 mt-2 md:col-span-2">

                        <div class="flex items-center justify-center gap-3 p-3 border bg-amber-50 rounded-xl border-amber-200 dark:bg-amber-900/10 dark:border-amber-800">

                            <i class="fa-solid fa-circle-info text-amber-600 dark:text-amber-400"></i>

                            <p class="text-sm text-amber-800 dark:text-amber-300">
                                <span class="font-semibold">Note:</span>
                                At least one day must remain open for customer bookings.
                            </p>

                        </div>

                    </div>

                </div>

                <div class="mt-6">

                    <button
                        type="submit"
                        id="save-btn"
                        class="w-full min-h-[44px] bg-gradient-to-r from-[#7A6348] to-[#6F5430] text-white text-sm font-semibold py-3 px-4 rounded-xl transition-opacity shadow-sm hover:opacity-90">

                        Save Hours & Continue to Documents

                    </button>

                </div>

            </form>

        </div>

    </div>

    <script>
        function validateTimeRange(openingId, closingId, errorId, cardId) {
            const opening = document.getElementById(openingId);
            const closing = document.getElementById(closingId);
            const errorEl = document.getElementById(errorId);
            const card = document.getElementById(cardId);

            if (!opening.value || !closing.value) {
                clearTimeError(
                    opening,
                    closing,
                    errorEl,
                    card
                );

                checkAllClosed();

                return true;
            }

            const isReversed =
                closing.value <= opening.value;

            if (isReversed) {
                errorEl.classList.remove('hidden');

                closing.classList.add(
                    'border-red-400',
                    'bg-red-50',
                    'focus:border-red-400'
                );

                card.classList.add('ring-2', 'ring-red-300');

                checkAllClosed();

                return false;
            }

            clearTimeError(
                opening,
                closing,
                errorEl,
                card
            );

            checkAllClosed();

            return true;
        }

        function clearTimeError(
            opening,
            closing,
            errorEl,
            card
        ) {
            errorEl.classList.add('hidden');

            closing.classList.remove(
                'border-red-400',
                'bg-red-50',
                'focus:border-red-400'
            );

            card.classList.remove(
                'ring-2',
                'ring-red-300'
            );
        }

        function checkAllClosed() {
            const allCheckboxes =
                document.querySelectorAll(
                    'input[type="checkbox"][name*="is_closed"]'
                );

            const saveBtn =
                document.getElementById('save-btn');

            const allClosed =
                Array.from(allCheckboxes)
                    .every(function (checkbox) {
                        return checkbox.checked;
                    });

            saveBtn.disabled = allClosed;
            saveBtn.classList.toggle(
                'opacity-50',
                allClosed
            );

            saveBtn.classList.toggle(
                'cursor-not-allowed',
                allClosed
            );
        }

        function toggleTimeInputs(
            checkbox,
            openingId,
            closingId,
            cardId
        ) {
            const openingInput =
                document.getElementById(openingId);

            const closingInput =
                document.getElementById(closingId);

            const suffix =
                cardId.replace('card_', '');

            const timesWrapper =
                document.getElementById(
                    'times_' + suffix
                );

            const closedLabel =
                document.getElementById(
                    'closed_label_' + suffix
                );

            const errorEl =
                document.getElementById(
                    'time_error_' + suffix
                );

            const card =
                document.getElementById(cardId);

            const isClosed = checkbox.checked;

            openingInput.disabled = isClosed;
            closingInput.disabled = isClosed;

            if (timesWrapper) {
                timesWrapper.style.opacity =
                    isClosed ? '0.4' : '1';

                timesWrapper.style.pointerEvents =
                    isClosed ? 'none' : '';
            }

            closedLabel?.classList.toggle(
                'hidden',
                !isClosed
            );

            if (
                isClosed &&
                errorEl
            ) {
                clearTimeError(
                    openingInput,
                    closingInput,
                    errorEl,
                    card
                );
            }

            checkAllClosed();
        }

        document
            .getElementById('operating-hours-form')
            .addEventListener(
                'submit',
                function (event) {
                    let hasError = false;

                    document
                        .querySelectorAll(
                            '[id^="time_error_"]'
                        )
                        .forEach(function (errorElement) {
                            if (
                                !errorElement.classList.contains(
                                    'hidden'
                                )
                            ) {
                                hasError = true;
                            }
                        });

                    if (hasError) {
                        event.preventDefault();

                        const firstError =
                            document.querySelector(
                                '[id^="time_error_"]:not(.hidden)'
                            );

                        firstError?.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    }
                }
            );

        checkAllClosed();
    </script>

</x-guest-layout>
