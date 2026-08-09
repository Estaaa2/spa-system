<x-guest-layout>
    <div class="min-h-screen px-4 py-6">
        <div class="max-w-3xl mx-auto">

            <!-- Header -->
            <div class="relative mb-8 text-center">
                <a href="{{ route('setup.branches') }}"
                   class="absolute left-0 inline-flex items-center text-sm text-gray-600 hover:text-[#8B7355] transition-colors duration-200">
                    <i class="fa-solid fa-circle-chevron-left text-3xl text-[#8B7355]"></i>
                </a>

                <img src="{{ asset('images/1.png') }}" alt="Levictas" class="mx-auto rounded-md h-14"/>

                <h2 class="mt-3 text-3xl font-light text-[#2D3748] font-['Playfair_Display']">
                    Operating Hours
                </h2>
                <p class="mt-1 text-sm font-medium text-[#6F5430]">{{ $branch->name }}</p>
            </div>

            @if ($errors->any())
                <div class="p-4 mb-6 text-sm text-red-700 bg-red-50 rounded-xl ring-1 ring-red-200">
                    <p class="mb-1 font-semibold">Please fix the following:</p>
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
                        <div class="p-4 transition bg-white shadow-sm rounded-2xl ring-1 ring-black/5 hover:shadow-md"
                             id="card_{{ $hour->id }}">

                            {{-- Day label + Closed toggle --}}
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-sm font-semibold text-[#3C2F23]">
                                    {{ $hour->day_of_week }}
                                </h4>

                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="hidden" name="hours[{{ $loop->index }}][is_closed]" value="0"/>
                                    <input
                                        type="checkbox"
                                        name="hours[{{ $loop->index }}][is_closed]"
                                        value="1"
                                        {{ $hour->is_closed ? 'checked' : '' }}
                                        class="w-4 h-4 rounded text-[#8B7355] border-gray-300 focus:ring-[#8B7355]/40"
                                        onchange="toggleTimeInputs(this, 'opening_{{ $hour->id }}', 'closing_{{ $hour->id }}', 'card_{{ $hour->id }}')"
                                    />
                                    <span class="text-xs font-semibold text-gray-500">Closed</span>
                                </label>
                            </div>

                            {{-- Time inputs --}}
                            <div class="grid grid-cols-2 gap-3" id="times_{{ $hour->id }}"
                                 style="{{ $hour->is_closed ? 'opacity:0.4; pointer-events:none;' : '' }}">
                                <div>
                                    <label class="block mb-1 text-[10px] font-semibold text-gray-500 uppercase tracking-wide">
                                        Opens
                                    </label>
                                    <input
                                        type="time"
                                        id="opening_{{ $hour->id }}"
                                        name="hours[{{ $loop->index }}][opening_time]"
                                        value="{{ $hour->opening_time }}"
                                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-white text-gray-900 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none"
                                        {{ $hour->is_closed ? 'disabled' : '' }}
                                        onchange="validateTimeRange('opening_{{ $hour->id }}', 'closing_{{ $hour->id }}', 'time_error_{{ $hour->id }}', 'card_{{ $hour->id }}')"
                                    />
                                </div>

                                <div>
                                    <label class="block mb-1 text-[10px] font-semibold text-gray-500 uppercase tracking-wide">
                                        Closes
                                    </label>
                                    <input
                                        type="time"
                                        id="closing_{{ $hour->id }}"
                                        name="hours[{{ $loop->index }}][closing_time]"
                                        value="{{ $hour->closing_time }}"
                                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-white text-gray-900 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none"
                                        {{ $hour->is_closed ? 'disabled' : '' }}
                                        onchange="validateTimeRange('opening_{{ $hour->id }}', 'closing_{{ $hour->id }}', 'time_error_{{ $hour->id }}', 'card_{{ $hour->id }}')"
                                    />
                                </div>
                            </div>

                            {{-- Inline time validation error --}}
                            <p class="flex items-center hidden gap-1 mt-2 text-xs text-red-600" id="time_error_{{ $hour->id }}">
                                <svg class="inline w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 16 16">
                                    <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5"/>
                                    <line x1="8" y1="5" x2="8" y2="8.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    <circle cx="8" cy="11" r="0.75" fill="currentColor"/>
                                </svg>
                                Closing time must be after opening time.
                            </p>

                            {{-- Closed overlay label --}}
                            @if($hour->is_closed)
                                <p class="mt-3 text-xs italic text-center text-gray-400" id="closed_label_{{ $hour->id }}">
                                    Closed all day
                                </p>
                            @else
                                <p class="hidden mt-3 text-xs italic text-center text-gray-400" id="closed_label_{{ $hour->id }}">
                                    Closed all day
                                </p>
                            @endif

                            <input type="hidden" name="hours[{{ $loop->index }}][id]" value="{{ $hour->id }}"/>
                        </div>
                    @endforeach

                    <!-- HELPFUL NOTE - Placed in the empty space below the grid -->
                    <div class="col-span-1 mt-2 md:col-span-2">
                        <div class="flex items-center justify-center gap-3 p-3 border bg-amber-50/50 rounded-xl border-amber-200/50">
                            <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-sm text-amber-800">
                                <span class="font-semibold">Note:</span> At least 1-3 days must be open to keep your business active.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <button
                        type="submit"
                        id="save-btn"
                        class="w-full bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white text-sm font-semibold py-3 px-4 rounded-xl transition-all shadow-sm hover:shadow-md active:translate-y-0.5">
                        Save Operating Hours
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
            clearTimeError(opening, closing, errorEl, card);
            checkAllClosed();
            return true;
        }

        const isReversed = closing.value <= opening.value;

        if (isReversed) {
            errorEl.classList.remove('hidden');
            closing.classList.add('border-red-400', 'bg-red-50', 'focus:border-red-400', 'focus:ring-red-200');
            closing.classList.remove('border-gray-200');
            card.classList.add('ring-red-300');
            card.classList.remove('ring-black/5');
            checkAllClosed();
            return false;
        } else {
            clearTimeError(opening, closing, errorEl, card);
            checkAllClosed();
            return true;
        }
    }

    function clearTimeError(opening, closing, errorEl, card) {
        errorEl.classList.add('hidden');
        closing.classList.remove('border-red-400', 'bg-red-50', 'focus:border-red-400', 'focus:ring-red-200');
        closing.classList.add('border-gray-200');
        card.classList.remove('ring-red-300');
        card.classList.add('ring-black/5');
    }

    function checkAllClosed() {
        const allCheckboxes = document.querySelectorAll('input[type="checkbox"][name*="is_closed"]');
        const saveBtn = document.getElementById('save-btn');

        const allClosed = Array.from(allCheckboxes).every(cb => cb.checked);

        if (allClosed) {
            saveBtn.disabled = true;
            saveBtn.classList.add('opacity-50', 'cursor-not-allowed');
            saveBtn.classList.remove('hover:from-[#6F5430]', 'hover:to-[#5A4526]', 'active:translate-y-0.5');
        } else {
            saveBtn.disabled = false;
            saveBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            saveBtn.classList.add('hover:from-[#6F5430]', 'hover:to-[#5A4526]', 'active:translate-y-0.5');
        }
    }

    function toggleTimeInputs(checkbox, openingId, closingId, cardId) {
        const openingInput = document.getElementById(openingId);
        const closingInput = document.getElementById(closingId);
        const timesWrapper = document.getElementById('times_' + cardId.replace('card_', ''));
        const closedLabel = document.getElementById('closed_label_' + cardId.replace('card_', ''));
        const errorId = 'time_error_' + cardId.replace('card_', '');
        const errorEl = document.getElementById(errorId);

        const isClosed = checkbox.checked;

        openingInput.disabled = isClosed;
        closingInput.disabled = isClosed;

        if (timesWrapper) {
            timesWrapper.style.opacity = isClosed ? '0.4' : '1';
            timesWrapper.style.pointerEvents = isClosed ? 'none' : '';
        }

        if (closedLabel) {
            closedLabel.classList.toggle('hidden', !isClosed);
        }

        if (isClosed && errorEl) {
            clearTimeError(openingInput, closingInput, errorEl, document.getElementById(cardId));
        }

        checkAllClosed();
    }

    document.getElementById('operating-hours-form').addEventListener('submit', function (e) {
        let hasError = false;

        document.querySelectorAll('[id^="time_error_"]').forEach(function (errorEl) {
            if (!errorEl.classList.contains('hidden')) {
                hasError = true;
            }
        });

        if (hasError) {
            e.preventDefault();
            const firstError = document.querySelector('[id^="time_error_"]:not(.hidden)');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });

    // Run on page load in case all days are already closed (e.g. saved state)
    checkAllClosed();
</script>
</x-guest-layout>
