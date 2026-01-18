<x-guest-layout>
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-10">
            <div class="flex justify-between items-center mb-6">
                <a href="{{ route('setup.branches') }}" class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-[#8B7355] dark:hover:text-[#8B7355] transition-colors duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Branches
                </a>
                <h2 class="text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display']">
                    Operating Hours - {{ $branch->name }}
                </h2>
                <div class="w-20"></div>
            </div>
        </div>
            <div class="w-full bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
                <h3 class="text-2xl font-semibold text-gray-800 dark:text-white mb-6">
                    Set Operating Hours
                </h3>

                <form method="POST" action="{{ route('setup.update-operating-hours', $branch) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    @foreach($operatingHours as $hour)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <div class="grid grid-cols-2 md:grid-cols-2 gap-6 items-start">
                                <!-- Day Label (spans left, aligned top) -->
                                <div class="flex items-center pt-2">
                                    <h4 class="text-base font-semibold text-gray-800 dark:text-white">
                                        {{ $hour->day_of_week }}
                                    </h4>
                                </div>

                                <!-- Opening Time (right column) -->
                                <div>
                                    <label for="opening_{{ $hour->id }}" class="block text-xs text-gray-600 dark:text-gray-400 mb-1">
                                        Opening Time
                                    </label>
                                    <input
                                        type="time"
                                        id="opening_{{ $hour->id }}"
                                        name="hours[{{ $loop->index }}][opening_time]"
                                        value="{{ $hour->opening_time }}"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-[#8B7355] focus:outline-none"
                                        {{ $hour->is_closed ? 'disabled' : '' }}
                                    />
                                </div>

                                <!-- Closing Time (left column, second row) -->
                                <div>
                                    <label for="closing_{{ $hour->id }}" class="block text-xs text-gray-600 dark:text-gray-400 mb-1">
                                        Closing Time
                                    </label>
                                    <input
                                        type="time"
                                        id="closing_{{ $hour->id }}"
                                        name="hours[{{ $loop->index }}][closing_time]"
                                        value="{{ $hour->closing_time }}"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-[#8B7355] focus:outline-none"
                                        {{ $hour->is_closed ? 'disabled' : '' }}
                                    />
                                </div>

                                <!-- Closed Checkbox (right column, second row) -->
                                <div class="flex items-center justify-end">
                                    <label class="flex items-center cursor-pointer">
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
    </div>

    <script>
        function toggleTimeInputs(checkbox, openingId, closingId) {
            const openingInput = document.getElementById(openingId);
            const closingInput = document.getElementById(closingId);
            
            if (checkbox.checked) {
                openingInput.disabled = true;
                closingInput.disabled = true;
            } else {
                openingInput.disabled = false;
                closingInput.disabled = false;
            }
        }
    </script>
</x-guest-layout>
