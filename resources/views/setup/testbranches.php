<x-guest-layout>
    <div class="max-w-5xl px-4 py-8 mx-auto">
        <div class="p-8 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <!-- Header -->
            <div class="relative mb-10 text-center">
                <a href="{{ route('setup.index') }}"
                   class="absolute left-0 inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-[#8B7355] dark:hover:text-[#8B7355] transition-colors duration-200">
                    <i class="fa-solid fa-circle-chevron-left text-3xl text-[#8B7355]"></i>
                </a>

                <img
                    src="{{ asset('images/1.png') }}"
                    alt="Levictas"
                    class="h-16 mx-auto rounded-md"
                />

                <h1 class="mt-5 text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display'] mb-2">
                    Set Up Branches
                </h1>

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

            <!-- Session Status -->
            @if (session('success'))
                <div class="p-4 mb-6 text-sm text-green-800 border border-green-200 rounded-xl bg-green-50 dark:bg-green-500/10 dark:border-green-400/20 dark:text-green-300">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Content -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                
                <!-- Left Panel -->
                <div class="lg:col-span-5">
                    <div class="p-6 bg-gray-50 border border-gray-200 rounded-2xl dark:bg-gray-900/40 dark:border-gray-700">
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
                            Add Branch
                        </h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Enter the basic details of a branch you want to manage.
                        </p>

                        <form method="POST" action="{{ route('setup.store-branch') }}" class="mt-6 space-y-5">
                            @csrf

                            <!-- Branch Name -->
                            <div>
                                <label for="branch_name" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Branch Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="branch_name"
                                    name="branch_name"
                                    value="{{ old('branch_name') }}"
                                    required
                                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:border-[#8B7355] focus:ring-2 focus:ring-[#8B7355]/20 focus:outline-none"
                                    placeholder="e.g., Main Branch"
                                />
                                @error('branch_name')
                                    <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Location -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label for="locationSelect" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Location / City <span class="text-red-500">*</span>
                                    </label>
                                    <button
                                        type="button"
                                        id="toggleLocationMode"
                                        class="text-xs font-medium text-[#8B7355] hover:text-[#6F5430] transition-colors underline"
                                    >
                                        Type manually
                                    </button>
                                </div>

                                <!-- Dropdown -->
                                <div id="locationDropdownWrapper">
                                    <select
                                        id="locationSelect"
                                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-[#8B7355] focus:ring-2 focus:ring-[#8B7355]/20 focus:outline-none"
                                    >
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

                                <!-- Manual input -->
                                <div id="locationInputWrapper" class="hidden">
                                    <input
                                        type="text"
                                        id="locationManualInput"
                                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:border-[#8B7355] focus:ring-2 focus:ring-[#8B7355]/20 focus:outline-none"
                                        placeholder="Type city / area manually"
                                    />
                                </div>

                                <input type="hidden" name="location" id="locationValue" value="{{ old('location') }}"/>

                                @error('location')
                                    <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Home Service -->
                            <div class="p-4 border rounded-xl bg-amber-50 border-amber-200 dark:bg-amber-500/10 dark:border-amber-400/20">
                                <label for="has_home_service" class="flex items-start gap-3 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        name="has_home_service"
                                        id="has_home_service"
                                        value="1"
                                        {{ old('has_home_service') ? 'checked' : '' }}
                                        class="w-4 h-4 mt-1 rounded text-[#8B7355] border-gray-300 dark:border-gray-600 focus:ring-[#8B7355]/40"
                                    >
                                    <div>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                            This branch offers Home Service
                                        </p>
                                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                            Enable this if this branch can provide services outside the physical spa location.
                                        </p>
                                    </div>
                                </label>
                            </div>

                            <!-- Notice -->
                            <div class="p-4 border rounded-xl bg-gray-50 border-gray-200 dark:bg-gray-700/40 dark:border-gray-600">
                                <div class="flex gap-3">
                                    <div class="mt-0.5 text-gray-500 dark:text-gray-300">
                                        <i class="fa-solid fa-circle-info"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                            Public branch details can be added later
                                        </p>
                                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                            Address, contact number, description, photos, and listing details can be configured later when you set up the branch profile.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2">
                                <button
                                    type="submit"
                                    class="w-full bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white font-medium py-3 px-4 rounded-xl transition-all duration-300 transform hover:scale-[1.01] focus:outline-none focus:ring-2 focus:ring-[#8B7355]/30"
                                >
                                    Add Branch
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Panel -->
                <div class="lg:col-span-7">
                    <div class="p-6 bg-gray-50 border border-gray-200 rounded-2xl dark:bg-gray-900/40 dark:border-gray-700">
                        <div class="flex items-center justify-between gap-4 mb-4">
                            <div>
                                <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
                                    Your Branches
                                </h2>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Review the branches you have added so far.
                                </p>
                            </div>
                        </div>

                        @if($branches->isEmpty())
                            <div class="py-12 text-center">
                                <div class="flex items-center justify-center w-14 h-14 mx-auto rounded-full bg-gray-100 dark:bg-gray-700">
                                    <i class="text-xl text-gray-400 fa-solid fa-location-dot"></i>
                                </div>
                                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                    No branches yet. Add at least one branch to continue.
                                </p>
                            </div>
                        @else
                            <div class="space-y-4">
                                @foreach($branches as $branch)
                                    <div class="p-4 bg-white border border-gray-200 rounded-xl dark:bg-gray-800 dark:border-gray-700">
                                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white">
                                                        {{ $branch->name }}
                                                    </h3>

                                                    @if($branch->is_main)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 text-[11px] font-medium text-[#6F5430] bg-amber-100 dark:bg-amber-500/10 dark:text-amber-300 rounded-full">
                                                            Main Branch
                                                        </span>
                                                    @endif
                                                </div>

                                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $branch->location }}
                                                </p>

                                                @if($branch->has_home_service)
                                                    <span class="inline-flex items-center gap-1 mt-3 px-2.5 py-1 text-[11px] font-medium text-[#6F5430] bg-amber-100 dark:bg-amber-500/10 dark:text-amber-300 rounded-full">
                                                        <i class="fa-solid fa-house-chimney text-[10px]"></i>
                                                        Home Service
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="flex flex-wrap gap-2">
                                                <a href="{{ route('setup.operating-hours', $branch) }}"
                                                   class="inline-flex items-center px-3 py-2 text-xs font-medium text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 transition dark:bg-blue-500/10 dark:text-blue-300 dark:hover:bg-blue-500/20">
                                                    <i class="mr-1 fa-solid fa-clock"></i>
                                                    Hours
                                                </a>

                                                <a href="{{ route('setup.staff', $branch) }}"
                                                   class="inline-flex items-center px-3 py-2 text-xs font-medium text-green-700 bg-green-50 rounded-lg hover:bg-green-100 transition dark:bg-green-500/10 dark:text-green-300 dark:hover:bg-green-500/20">
                                                    <i class="mr-1 fa-solid fa-user-plus"></i>
                                                    Staff
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                                <a href="{{ route('setup.complete') }}"
                                   class="block w-full text-center bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white font-medium py-3 px-4 rounded-xl transition-all duration-300 transform hover:scale-[1.01] focus:outline-none focus:ring-2 focus:ring-[#8B7355]/30">
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
        const toggleBtn = document.getElementById('toggleLocationMode');
        const dropdownWrapper = document.getElementById('locationDropdownWrapper');
        const inputWrapper = document.getElementById('locationInputWrapper');
        const locationSelect = document.getElementById('locationSelect');
        const locationManualInput = document.getElementById('locationManualInput');
        const locationValue = document.getElementById('locationValue');
        const branchForm = document.querySelector('form[action="{{ route('setup.store-branch') }}"]');

        let isManualMode = false;

        // Restore old value if validation fails
        const oldLocation = locationValue.value;

        if (oldLocation) {
            const optionExists = Array.from(locationSelect.options).some(option => option.value === oldLocation);

            if (optionExists) {
                locationSelect.value = oldLocation;
            } else {
                isManualMode = true;
                dropdownWrapper.classList.add('hidden');
                inputWrapper.classList.remove('hidden');
                locationManualInput.value = oldLocation;
                toggleBtn.textContent = 'Pick from list';
            }
        }

        locationSelect.addEventListener('change', () => {
            locationValue.value = locationSelect.value;
        });

        locationManualInput.addEventListener('input', () => {
            locationValue.value = locationManualInput.value;
        });

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

        branchForm.addEventListener('submit', (e) => {
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