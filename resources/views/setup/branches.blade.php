<x-guest-layout>
    <div class="max-w-5xl px-4 py-8 mx-auto dark:bg-gray-800 dark:border-gray-700">
        <div class="max-w-3xl mx-auto dark:bg-gray-800 dark:border-gray-700">

            <!-- Header -->
            <div class="relative mb-8 text-center">
                <a href="{{ route('setup.index') }}"
                   class="absolute left-0 inline-flex items-center text-sm text-gray-600 hover:text-[#8B7355] transition-colors duration-200">
                    <i class="fa-solid fa-circle-chevron-left text-3xl text-[#8B7355]"></i>
                </a>

                <img src="{{ asset('images/1.png') }}" alt="Levictas" class="mx-auto rounded-md h-14"/>

                <h2 class="mt-5 text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display'] mb-2">
                    Set Up Branches
                </h2>
                <p class="max-w-2xl mx-auto mt-3 text-sm leading-6 text-gray-600 dark:text-gray-400 sm:text-base">
                    Add the branches you want to manage in your spa system. Public branch details like address, contact number, and description can be completed later.
                </p>
            </div>

            <!-- Step Indicator -->
            <div class="mb-12">
                <div class="flex items-center justify-center overflow-x-auto">
                    <div class="flex items-center min-w-max">
                        <!-- Step 1 -->
                        <div class="flex items-center">
                            <div class="flex items-center justify-center w-10 h-10 text-white rounded-full bg-[#8B7355]">
                                <i class="text-sm leading-none fa-solid fa-check"></i>
                            </div>
                            <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Business Info
                            </span>
                        </div>

                        <div class="w-16 sm:w-24 h-1 mx-4 rounded bg-[#8B7355]/30"></div>

                        <!-- Step 2 -->
                        <div class="flex items-center">
                            <div class="flex items-center justify-center w-10 h-10 rounded-full bg-[#8B7355] text-white">
                                2
                            </div>
                            <span class="ml-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                Branches
                            </span>
                        </div>

                        <div class="w-16 sm:w-24 h-1 mx-4 bg-gray-200 rounded dark:bg-gray-700"></div>

                        <!-- Step 3 -->
                        <div class="flex items-center">
                            <div class="flex items-center justify-center w-10 h-10 text-gray-400 bg-gray-200 rounded-full dark:bg-gray-700 dark:text-gray-300">
                                3
                            </div>
                            <span class="ml-3 text-sm font-medium text-gray-500 dark:text-gray-400">
                                Staff
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TWO COLUMN LAYOUT -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">

                <!-- LEFT: Add New Branch -->
                <div class="lg:col-span-5">
                    <div class="p-6 bg-white shadow-sm rounded-2xl ring-1 ring-black/5 dark:bg-gray-900/40 dark:border-gray-700">
                        <h3 class="mb-4 text-base font-semibold text-[#3C2F23] dark:text-white">Add New Branch</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Enter the basic details of a branch you want to manage.
                        </p>

                        <form method="POST" action="{{ route('setup.store-branch') }}" class="space-y-4">
                            @csrf

                            {{-- Branch Name --}}
                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-600">Branch Name *</label>
                                <input type="text" name="branch_name" required
                                    value="{{ old('branch_name') }}"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:border-[#8B7355] focus:ring-2 focus:ring-[#8B7355]/20 focus:outline-none"
                                    placeholder="e.g., Downtown Branch"/>
                                @error('branch_name')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Location — Hybrid dropdown + custom input --}}
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-xs font-semibold text-gray-600">Location / City *</label>
                                    <button type="button" id="toggleLocationMode"
                                        class="text-[10px] font-semibold text-[#8B7355] hover:text-[#6F5430] transition underline">
                                        Type manually
                                    </button>
                                </div>

                                {{-- Dropdown mode (default) --}}
                                <div id="locationDropdownWrapper">
                                    <select id="locationSelect"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-[#8B7355] focus:ring-2 focus:ring-[#8B7355]/20 focus:outline-none">
                                        <option value="">Select a city in Cavite</option>
                                        <optgroup label="Cities">
                                            <option value="Bacoor">Bacoor</option>
                                            <option value="Cavite City">Cavite City</option>
                                            <option value="Dasmariñas">Dasmariñas</option>
                                            <option value="General Trias">General Trias</option>
                                            <option value="Imus">Imus</option>
                                            <option value="Carmona">Carmona</option>
                                            <option value="Tagaytay">Tagaytay</option>
                                            <option value="Trece Martires">Trece Martires</option>
                                        </optgroup>
                                        <optgroup label="Municipalities">
                                            <option value="Alfonso">Alfonso</option>
                                            <option value="Amadeo">Amadeo</option>
                                            <option value="Carmen">Carmen</option>
                                            <option value="General Emilio Aguinaldo">General Emilio Aguinaldo</option>
                                            <option value="General Mariano Alvarez">General Mariano Alvarez</option>
                                            <option value="Indang">Indang</option>
                                            <option value="Kawit">Kawit</option>
                                            <option value="Magallanes">Magallanes</option>
                                            <option value="Maragondon">Maragondon</option>
                                            <option value="Mendez">Mendez</option>
                                            <option value="Naic">Naic</option>
                                            <option value="Noveleta">Noveleta</option>
                                            <option value="Rosario">Rosario</option>
                                            <option value="Silang">Silang</option>
                                            <option value="Tanza">Tanza</option>
                                            <option value="Ternate">Ternate</option>
                                        </optgroup>
                                    </select>
                                </div>

                                {{-- Manual input mode (hidden by default) --}}
                                <div id="locationInputWrapper" class="hidden">
                                    <input type="text" id="locationManualInput"
                                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-white text-gray-900 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none"
                                        placeholder="Type city / area manually"/>
                                </div>

                                {{-- Hidden input that actually gets submitted --}}
                                <input type="hidden" name="location" id="locationValue" value="{{ old('location') }}"/>

                                @error('location')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Has Home Service Toggle --}}
                            <div class="flex items-center gap-3 p-3 rounded-xl bg-[#F6EFE6] ring-1 ring-black/5 dark:bg-amber-500/10 dark:border-amber-400/20">
                                <input type="checkbox" name="has_home_service" id="has_home_service" value="1"
                                    class="w-4 h-4 rounded text-[#8B7355] border-gray-300 focus:ring-[#8B7355]/40"/>
                                <label for="has_home_service" class="text-xs text-gray-800 dark:text-gray-200">
                                    This branch offers <span class="text-[#6F5430] ">Home Service</span>
                                </label>
                            </div>

                            <button type="submit"
                                class="w-full mt-2 bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white text-sm font-semibold py-2.5 px-4 rounded-xl transition-all shadow-sm hover:shadow-md active:translate-y-0.5">
                                Add Branch
                            </button>
                        </form>
                    </div>
                </div>

                <!-- RIGHT: Your Branches -->
                <div class="lg:col-span-7">
                    <div class="p-6 bg-white shadow-sm rounded-2xl ring-1 ring-black/5 dark:bg-gray-900/40 dark:border-gray-700">
                        <h3 class="mb-4 text-base font-semibold text-[#3C2F23] dark:text-white">Your Branches</h3>

                        @if($branches->isEmpty())
                            <div class="py-10 text-center text-gray-400">
                                <i class="mb-2 text-3xl text-gray-300 fa-solid fa-location-dot"></i>
                                <p class="text-sm">No branches yet. Add at least one to continue.</p>
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach($branches as $branch)
                                    <div class="p-4 bg-white border border-gray-200 rounded-xl dark:bg-gray-800 dark:border-gray-700">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <h4 class="text-sm font-semibold text-[#3C2F23] dark:text-white">{{ $branch->name }}</h4>
                                                <p class="mt-0.5 text-xs text-gray-500">{{ $branch->location }}</p>
                                                @if($branch->has_home_service)
                                                    <span class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 text-[10px] font-semibold text-[#6F5430] bg-[#F6EFE6] rounded-full ring-1 ring-black/5">
                                                        <i class="fa-solid fa-house-chimney text-[#8B7355]"></i>
                                                        Home Service
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="flex gap-2">
                                                <a href="{{ route('setup.operating-hours', $branch) }}"
                                                class="inline-flex items-center px-3 py-2 text-xs font-medium text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 transition dark:bg-blue-500/10 dark:text-blue-300 dark:hover:bg-blue-500/20">
                                                    <i class="mr-1 fa-solid fa-clock"></i> Set Hours
                                                </a>
                                                <a href="{{ route('setup.staff', $branch) }}"
                                                   class="inline-flex items-center px-3 py-2 text-xs font-medium text-green-700 bg-green-50 rounded-lg hover:bg-green-100 transition dark:bg-green-500/10 dark:text-green-300 dark:hover:bg-green-500/20">
                                                    <i class="mr-1 fa-solid fa-user-plus"></i> Set Staff
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="pt-5 mt-5 border-t border-black/5">
                                <a href="{{ route('setup.complete') }}"
                                   class="block w-full text-center bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white text-sm font-semibold py-3 px-4 rounded-xl transition-all shadow-sm hover:shadow-md active:translate-y-0.5">
                                    Finish Setup & Go to Dashboard
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        const toggleBtn           = document.getElementById('toggleLocationMode');
        const dropdownWrapper     = document.getElementById('locationDropdownWrapper');
        const inputWrapper        = document.getElementById('locationInputWrapper');
        const locationSelect      = document.getElementById('locationSelect');
        const locationManualInput = document.getElementById('locationManualInput');
        const locationValue       = document.getElementById('locationValue');

        let isManualMode = false;

        // Sync dropdown value to hidden input
        locationSelect.addEventListener('change', () => {
            locationValue.value = locationSelect.value;
        });

        // Sync manual input to hidden input
        locationManualInput.addEventListener('input', () => {
            locationValue.value = locationManualInput.value;
        });

        // Toggle between dropdown and manual input
        toggleBtn.addEventListener('click', () => {
            isManualMode = !isManualMode;

            if (isManualMode) {
                dropdownWrapper.classList.add('hidden');
                inputWrapper.classList.remove('hidden');
                locationManualInput.focus();
                locationValue.value = locationManualInput.value;
                toggleBtn.textContent = 'Pick from list';
            } else {
                dropdownWrapper.classList.remove('hidden');
                inputWrapper.classList.add('hidden');
                locationValue.value = locationSelect.value;
                toggleBtn.textContent = 'Type manually';
            }
        });

        // On form submit — make sure locationValue is filled
        document.querySelector('form').addEventListener('submit', (e) => {
            if (!locationValue.value.trim()) {
                e.preventDefault();
                alert('Please select or enter a location.');

                if (isManualMode) {
                    locationManualInput.focus();
                } else {
                    locationSelect.focus();
                }
            }
        });
    </script>
</x-guest-layout>
