<x-guest-layout>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <div class="px-4 py-6 sm:px-8 sm:py-8">

        <div class="max-w-3xl mx-auto">

            <div class="relative mb-6 text-center sm:mb-8">

                <a href="{{ route('setup.index') }}"
                   aria-label="Back to business info"
                   class="absolute top-0 left-0 inline-flex items-center justify-center min-w-[44px] min-h-[44px] rounded-full hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]/40">

                    <i class="text-2xl sm:text-3xl fa-solid fa-circle-chevron-left text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>

                </a>

                <img src="{{ asset('images/1.png') }}" alt="Levictas" class="h-12 mx-auto rounded-md sm:h-14"/>

                <h1 class="mt-4 text-2xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display'] sm:text-3xl">
                    Set Up Your Main Branch
                </h1>

                <p class="max-w-2xl mx-auto mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                    Add your main branch and confirm its exact location. One branch is included during initial setup.
                </p>

            </div>

            <x-setup-stepper :current="2" />

            @if(session('error'))
                <div class="p-4 mb-6 text-sm text-red-700 border border-red-200 bg-red-50 rounded-2xl dark:bg-red-900/10 dark:text-red-300 dark:border-red-800">
                    {{ session('error') }}
                </div>
            @endif

            @php
                // Returning from Documents: prefill with the saved branch so typos can be fixed.
                $savedProfile = $branch?->profile;
                $savedLocation = $savedProfile?->address ?? $branch?->location;
                $hasSavedPin = $savedProfile && $savedProfile->latitude !== null && $savedProfile->longitude !== null;
            @endphp

            @if($branch)
                <div class="p-3 mb-6 text-sm text-blue-800 border border-blue-200 bg-blue-50 rounded-xl dark:bg-blue-900/10 dark:text-blue-300 dark:border-blue-800">
                    Your main branch is saved. Fix anything below, or continue if everything is correct.
                </div>
            @endif


                <form method="POST" action="{{ route('setup.store-branch') }}" id="branchSetupForm" class="space-y-6">
                    @csrf

                    <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">

                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                Branch Information
                            </h3>

                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Branch name must be unique within your spa.
                            </p>
                        </div>

                        <div class="mt-5">
                            <label for="branch_name" class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Branch Name <span class="text-red-600">*</span>
                            </label>

                            <input
                                type="text"
                                id="branch_name"
                                name="branch_name"
                                value="{{ old('branch_name', $branch?->name) }}"
                                required
                                placeholder="e.g. Imus Main Branch"
                                class="w-full min-h-[44px] px-3 py-2 text-sm border rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-[#8B7355] focus:ring-2 focus:ring-[#8B7355]/20 focus:outline-none {{ $errors->has('branch_name') ? 'border-red-400' : 'border-gray-300 dark:border-gray-600' }}"
                            />

                            @error('branch_name')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>

                    <div class="p-4 space-y-5 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">

                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                Exact Branch Location
                            </h3>

                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Search for the branch address and choose a suggestion, or pin the exact location on the map. Cavite locations only.
                            </p>
                        </div>

                        <div class="relative">
                            <label for="addressSearch" class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Search Address <span class="text-red-600">*</span>
                            </label>

                            <input
                                type="text"
                                id="addressSearch"
                                autocomplete="off"
                                value="{{ old('location', $savedLocation) }}"
                                placeholder="Search subdivision, barangay, street or establishment"
                                class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl bg-white dark:bg-gray-700 dark:border-gray-600 text-gray-900 dark:text-white focus:border-[#8B7355] focus:ring-2 focus:ring-[#8B7355]/20 focus:outline-none"
                            />

                            <div id="addressSuggestions"
                                class="absolute z-20 hidden w-full mt-1 overflow-hidden bg-white border border-gray-200 shadow-xl rounded-2xl dark:bg-gray-700 dark:border-gray-600">
                            </div>

                            <p id="addressSearching" class="hidden mt-1 text-xs text-gray-500 dark:text-gray-400">
                                <i class="fa-solid fa-spinner fa-spin"></i>
                                Searching...
                            </p>
                        </div>

                        <div>
                            <label for="cityDisplay" class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                                City / Municipality
                            </label>

                            <input
                                type="text"
                                id="cityDisplay"
                                value="{{ old('city', $savedProfile?->city) }}"
                                readonly
                                tabindex="-1"
                                placeholder="Automatically detected"
                                class="w-full min-h-[44px] px-3 py-2 text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-xl cursor-not-allowed dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400"
                            />
                        </div>

                        <div>
                            <div class="mb-3">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                                    Branch Location Pin
                                </h3>

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Click the map or drag the marker to confirm the exact branch location.
                                </p>
                            </div>

                            <div id="branchMap" class="w-full overflow-hidden border border-gray-200 h-72 rounded-xl dark:border-gray-600"></div>

                            <div id="caviteToast"
                                class="flex items-center hidden gap-2 p-3 mt-3 text-sm text-red-600 bg-red-50 rounded-xl ring-1 ring-red-200 dark:bg-red-900/10 dark:ring-red-800 dark:text-red-400">

                                <i class="fa-solid fa-location-crosshairs"></i>
                                <span id="caviteToastMsg">Please select a valid location within Cavite.</span>

                            </div>
                        </div>

                        <input type="hidden" name="location" id="location" value="{{ old('location', $savedLocation) }}">
                        <input type="hidden" name="city" id="city" value="{{ old('city', $savedProfile?->city) }}">
                        <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude', $savedProfile?->latitude) }}">
                        <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude', $savedProfile?->longitude) }}">
                        <input type="hidden" name="location_confirmed" id="location_confirmed" value="{{ old('location_confirmed', $hasSavedPin ? '1' : '0') }}">

                        @error('location')
                            <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror

                        @error('city')
                            <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror

                        @error('latitude')
                            <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror

                        @error('longitude')
                            <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror

                        @error('location_confirmed')
                            <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror

                    </div>

                    <button type="submit"
                        class="w-full min-h-[44px] bg-gradient-to-r from-[#7A6348] to-[#6F5430] text-white text-sm font-semibold py-3 px-4 rounded-xl transition-opacity shadow-sm hover:opacity-90">

                        {{ $branch ? 'Save and Continue' : 'Save Main Branch' }}

                    </button>

                </form>


        </div>

    </div>

    <script>
        const mapContainer = document.getElementById('branchMap');

        if (mapContainer) {
            const caviteBounds = L.latLngBounds(
                [14.020, 120.620],
                [14.520, 121.100]
            );

            const caviteCenter = [14.2456, 120.8786];
            const map = L.map('branchMap', {
                maxBounds: caviteBounds,
                maxBoundsViscosity: 0.8
            }).setView(caviteCenter, 11);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            const addressInput = document.getElementById('addressSearch');
            const locationInput = document.getElementById('location');
            const cityInput = document.getElementById('city');
            const cityDisplay = document.getElementById('cityDisplay');
            const latitudeInput = document.getElementById('latitude');
            const longitudeInput = document.getElementById('longitude');
            const confirmedInput = document.getElementById('location_confirmed');
            const suggestions = document.getElementById('addressSuggestions');
            const searching = document.getElementById('addressSearching');
            const form = document.getElementById('branchSetupForm');

            const CAVITE_BOUNDS_VIEWBOX = '120.620,14.520,121.100,14.020';

            let marker = null;
            let searchTimeout = null;
            let currentSuggestions = [];
            let activeSuggestionIndex = -1;

            function showCaviteToast(message) {
                const toast = document.getElementById('caviteToast');
                const messageElement = document.getElementById('caviteToastMsg');

                if (!toast) return;

                if (messageElement) {
                    messageElement.textContent = message;
                }

                toast.classList.remove('hidden');

                setTimeout(function () {
                    toast.classList.add('hidden');
                }, 4000);
            }

            function isActuallyInCavite(item) {
                if (
                    item.display_name &&
                    item.display_name.toLowerCase().includes('cavite')
                ) {
                    return true;
                }

                if (item.address) {
                    return Object.values(item.address).some(function (value) {
                        return typeof value === 'string' &&
                            value.toLowerCase().includes('cavite');
                    });
                }

                return false;
            }

            function detectedCity(address) {
                if (!address) return '';

                return address.city ||
                    address.town ||
                    address.municipality ||
                    address.city_district ||
                    address.village ||
                    '';
            }

            function clearConfirmedLocation() {
                locationInput.value = '';
                cityInput.value = '';
                cityDisplay.value = '';
                latitudeInput.value = '';
                longitudeInput.value = '';
                confirmedInput.value = '0';
            }

            function setMarker(latlng) {
                if (!marker) {
                    marker = L.marker(latlng, {
                        draggable: true
                    }).addTo(map);

                    marker.on('dragend', function () {
                        reverseGeocode(marker.getLatLng());
                    });
                } else {
                    marker.setLatLng(latlng);
                }
            }

            function applySelectedLocation(item) {
                const latitude = parseFloat(item.lat);
                const longitude = parseFloat(item.lon);
                const latlng = L.latLng(latitude, longitude);

                if (
                    !caviteBounds.contains(latlng) ||
                    !isActuallyInCavite(item)
                ) {
                    showCaviteToast(
                        'That location is outside Cavite.'
                    );
                    return;
                }

                const city = detectedCity(item.address);

                if (!city) {
                    showCaviteToast(
                        'The city or municipality could not be detected. Choose another exact address.'
                    );
                    return;
                }

                addressInput.value = item.display_name;
                locationInput.value = item.display_name;
                cityInput.value = city;
                cityDisplay.value = city;
                latitudeInput.value = latitude.toFixed(7);
                longitudeInput.value = longitude.toFixed(7);
                confirmedInput.value = '1';

                setMarker(latlng);
                map.setView(latlng, 16);

                suggestions.classList.add('hidden');
                currentSuggestions = [];
            }

            function reverseGeocode(latlng) {
                if (!caviteBounds.contains(latlng)) {
                    showCaviteToast(
                        'You can only pin locations within Cavite.'
                    );
                    return;
                }

                fetch(
                    `https://nominatim.openstreetmap.org/reverse?lat=${latlng.lat}&lon=${latlng.lng}&format=json&addressdetails=1`
                )
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (data) {
                        if (!isActuallyInCavite(data)) {
                            showCaviteToast(
                                'That location is not within Cavite.'
                            );
                            return;
                        }

                        const city = detectedCity(data.address);

                        if (!city) {
                            showCaviteToast(
                                'The city or municipality could not be detected at that point.'
                            );
                            return;
                        }

                        setMarker(latlng);

                        addressInput.value = data.display_name;
                        locationInput.value = data.display_name;
                        cityInput.value = city;
                        cityDisplay.value = city;
                        latitudeInput.value = latlng.lat.toFixed(7);
                        longitudeInput.value = latlng.lng.toFixed(7);
                        confirmedInput.value = '1';
                    })
                    .catch(function () {
                        showCaviteToast(
                            'The location could not be verified. Please try again.'
                        );
                    });
            }

            function escapeHtml(value) {
                const div = document.createElement('div');
                div.textContent = value;

                return div.innerHTML;
            }

            function renderSuggestions() {
                activeSuggestionIndex = -1;

                if (currentSuggestions.length === 0) {
                    suggestions.innerHTML =
                        '<div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-300">No matching locations found in Cavite.</div>';

                    suggestions.classList.remove('hidden');

                    return;
                }

                suggestions.innerHTML = currentSuggestions
                    .map(function (item, index) {
                        return `
                            <button type="button"
                                data-index="${index}"
                                class="address-suggestion-item flex items-start w-full gap-2 px-4 py-2.5 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-600 border-b border-gray-100 dark:border-gray-600 last:border-0">

                                <i class="fa-solid fa-location-dot text-[#8B7355] text-xs mt-1 shrink-0"></i>

                                <span class="text-gray-700 dark:text-gray-200">
                                    ${escapeHtml(item.display_name)}
                                </span>
                            </button>
                        `;
                    })
                    .join('');

                suggestions.classList.remove('hidden');

                suggestions
                    .querySelectorAll('.address-suggestion-item')
                    .forEach(function (button) {
                        button.addEventListener('click', function () {
                            const index = parseInt(
                                button.dataset.index,
                                10
                            );

                            const item = currentSuggestions[index];

                            if (item) {
                                applySelectedLocation(item);
                            }
                        });
                    });
            }

            function fetchAddressSuggestions(query) {
                if (query.length < 4) {
                    suggestions.classList.add('hidden');
                    suggestions.innerHTML = '';
                    return;
                }

                searching.classList.remove('hidden');

                const url =
                    `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query + ', Cavite, Philippines')}&format=json&limit=5&viewbox=${CAVITE_BOUNDS_VIEWBOX}&bounded=1&addressdetails=1`;

                fetch(url)
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (data) {
                        searching.classList.add('hidden');

                        currentSuggestions = (data || [])
                            .filter(function (item) {
                                const latlng = L.latLng(
                                    parseFloat(item.lat),
                                    parseFloat(item.lon)
                                );

                                return caviteBounds.contains(latlng) &&
                                    isActuallyInCavite(item);
                            });

                        renderSuggestions();
                    })
                    .catch(function () {
                        searching.classList.add('hidden');
                        suggestions.classList.add('hidden');
                    });
            }

            function highlightSuggestion(items) {
                items.forEach(function (item, index) {
                    item.classList.toggle(
                        'bg-gray-100',
                        index === activeSuggestionIndex
                    );

                    item.classList.toggle(
                        'dark:bg-gray-600',
                        index === activeSuggestionIndex
                    );
                });

                items[activeSuggestionIndex]?.scrollIntoView({
                    block: 'nearest'
                });
            }

            addressInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                clearConfirmedLocation();

                if (marker) {
                    map.removeLayer(marker);
                    marker = null;
                }

                const query = this.value.trim();

                searchTimeout = setTimeout(function () {
                    fetchAddressSuggestions(query);
                }, 500);
            });

            addressInput.addEventListener('keydown', function (event) {
                const items = suggestions.querySelectorAll(
                    '.address-suggestion-item'
                );

                if (items.length === 0) return;

                if (event.key === 'ArrowDown') {
                    event.preventDefault();

                    activeSuggestionIndex = Math.min(
                        activeSuggestionIndex + 1,
                        items.length - 1
                    );

                    highlightSuggestion(items);
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();

                    activeSuggestionIndex = Math.max(
                        activeSuggestionIndex - 1,
                        0
                    );

                    highlightSuggestion(items);
                } else if (
                    event.key === 'Enter' &&
                    activeSuggestionIndex >= 0
                ) {
                    event.preventDefault();

                    applySelectedLocation(
                        currentSuggestions[
                            activeSuggestionIndex
                        ]
                    );
                } else if (event.key === 'Escape') {
                    suggestions.classList.add('hidden');
                }
            });

            map.on('click', function (event) {
                reverseGeocode(event.latlng);
            });

            document.addEventListener('click', function (event) {
                if (
                    !addressInput.contains(event.target) &&
                    !suggestions.contains(event.target)
                ) {
                    suggestions.classList.add('hidden');
                }
            });

            form.addEventListener('submit', function (event) {
                if (
                    confirmedInput.value !== '1' ||
                    !locationInput.value ||
                    !cityInput.value ||
                    !latitudeInput.value ||
                    !longitudeInput.value
                ) {
                    event.preventDefault();

                    showCaviteToast(
                        'Choose an address suggestion or pin the exact branch location before continuing.'
                    );

                    addressInput.focus();
                }
            });

            const oldLatitude = parseFloat(latitudeInput.value);
            const oldLongitude = parseFloat(longitudeInput.value);

            if (
                !Number.isNaN(oldLatitude) &&
                !Number.isNaN(oldLongitude) &&
                caviteBounds.contains([
                    oldLatitude,
                    oldLongitude
                ]) &&
                locationInput.value &&
                cityInput.value
            ) {
                const latlng = L.latLng(
                    oldLatitude,
                    oldLongitude
                );

                setMarker(latlng);
                map.setView(latlng, 16);
                cityDisplay.value = cityInput.value;
                confirmedInput.value = '1';
            }
        }
    </script>

</x-guest-layout>