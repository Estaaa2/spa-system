<x-guest-layout>
    <div class="px-4 mx-auto max-w-7xl">
        <div class="relative mb-10">
    <!-- Back Button - Now outside the centered content -->
    <a href="{{ url('/') }}" class="absolute left-0 inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-[#8B7355] dark:hover:text-[#8B7355] transition-colors duration-200 z-10">
        <i class="fa-solid fa-arrow-left text-2xl text-[#8B7355]"></i>
    </a>

    <!-- Centered Content -->
    <div class="text-center">
        <img
            src="{{ asset('images/1.png') }}"
            alt="Levictas"
            class="h-16 m-10 mx-auto mt-10 rounded-md"
        />

        <h2 class="mt-3 text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display']">
            Operating Hours
        </h2>
        <p class="text-lg font-medium text-gray-700 dark:text-gray-300">{{ $branch->name }}</p>
    </div>
</div>

        <!-- MAIN CARD (unchanged) -->
        <div class="w-full p-8 bg-white rounded-lg shadow-lg dark:bg-gray-800">
            <h3 class="mb-6 text-2xl font-semibold text-gray-800 dark:text-white">
                Set Operating Hours
            </h3>

            @if ($errors->any())
                <div class="p-4 mb-6 border border-red-200 rounded-lg bg-red-50 dark:bg-red-900/20 dark:border-red-800">
                    <p class="mb-2 font-medium text-red-700 dark:text-red-400">There were errors with your submission:</p>
                    <ul class="text-sm text-red-600 dark:text-red-300">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('setup.update-operating-hours', $branch) }}" class="space-y-4">
                @csrf
                @method('PUT')

                @foreach($operatingHours as $hour)
                    <div class="p-4 border border-gray-200 rounded-lg dark:border-gray-700">
                        <div class="grid items-center grid-cols-3 gap-4">
                            <!-- Day -->
                            <div>
                                <h4 class="text-base font-semibold text-gray-800 dark:text-white">
                                    {{ $hour->day_of_week }}
                                </h4>
                            </div>

                            <!-- Opening -->
                            <div>
                                <label for="opening_{{ $hour->id }}" class="block mb-1 text-xs text-gray-600 dark:text-gray-400">
                                    Opening Time
                                </label>
                                <input
                                    type="time"
                                    id="opening_{{ $hour->id }}"
                                    name="hours[{{ $loop->index }}][opening_time]"
                                    value="{{ $hour->opening_time }}"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-[#8B7355] focus:outline-none"
                                />
                            </div>

                            <!-- Closing -->
                            <div>
                                <label for="closing_{{ $hour->id }}" class="block mb-1 text-xs text-gray-600 dark:text-gray-400">
                                    Closing Time
                                </label>
                                <input
                                    type="time"
                                    id="closing_{{ $hour->id }}"
                                    name="hours[{{ $loop->index }}][closing_time]"
                                    value="{{ $hour->closing_time }}"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-[#8B7355] focus:outline-none"
                                />
                            </div>

                            <!-- Closed Checkbox (full width under the 3 columns) -->
                            <div class="flex justify-end col-span-3">
                                <label class="flex items-center cursor-pointer">
                                    <input type="hidden" name="hours[{{ $loop->index }}][is_closed]" value="0" />
                                    <input
                                        type="checkbox"
                                        name="hours[{{ $loop->index }}][is_closed]"
                                        value="1"
                                        {{ $hour->is_closed ? 'checked' : '' }}
                                        class="w-4 h-4"
                                        onchange="toggleTimeInputs(this, 'opening_{{ $hour->id }}', 'closing_{{ $hour->id }}')"
                                    />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Closed</span>
                                </label>
                            </div>

                            <input type="hidden" name="hours[{{ $loop->index }}][id]" value="{{ $hour->id }}" />
                        </div>
                    </div>
                @endforeach

                <div class="pt-6">
                    <button
                        type="submit"
                        class="w-full bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white font-medium py-3 px-4 rounded-lg transition-all"
                    >
                        Save Operating Hours
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleTimeInputs(checkbox, openingId, closingId) {
            const openingInput = document.getElementById(openingId);
            const closingInput = document.getElementById(closingId);

            openingInput.disabled = checkbox.checked;
            closingInput.disabled = checkbox.checked;
        }
    </script>
</x-guest-layout>
