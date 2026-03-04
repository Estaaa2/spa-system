@extends('layouts.app')

@section('title', 'Edit Branch')

@section('content')
<link
  rel="stylesheet"
  href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
/>


<div class="mx-auto max-w-3xl py-6">
    <h1 class="text-2xl font-semibold text-gray-800 dark:text-white mb-6">Edit Branch</h1>

    {{-- Management Section --}}
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6 mb-6">
        <h2 class="text-lg font-medium text-gray-800 dark:text-white mb-4">Management</h2>

        <form method="POST" action="{{ route('branches.update', $branch->id) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Branch Name *</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $branch->name) }}" required
                        class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                </div>

                <div>
                    <label for="location" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Location *</label>
                    <textarea name="location" id="location" rows="2" required
                        class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">{{ old('location', $branch->location) }}</textarea>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="is_main" id="is_main" value="1" {{ $branch->is_main ? 'checked' : '' }}
                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600">
                    <label for="is_main" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Set as main branch</label>
                </div>
            </div>

            {{-- Operating Hours (Collapsible) --}}
            @if($branch->operatingHours)
            <div x-data="{ open: false }" class="mt-5">
                <button
                    type="button"
                    @click="open = !open"
                    class="flex items-center justify-between w-full px-4 py-2 text-left bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none"
                >
                    <span class="font-semibold text-gray-800 dark:text-white">Operating Hours</span>
                    <svg x-show="!open" class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <svg x-show="open" class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                    </svg>
                </button>

                <div x-show="open" x-transition class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                    @foreach($operatingHours as $hour)
                    <div class="p-4 bg-white dark:bg-gray-800 shadow-sm rounded-2xl ring-1 ring-black/5 dark:ring-white/10" id="card_{{ $hour->id ?? $loop->index }}">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $hour->day_of_week }}</h4>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="hours[{{ $loop->index }}][is_closed]" value="0"/>
                                <input type="checkbox"
                                    name="hours[{{ $loop->index }}][is_closed]"
                                    value="1"
                                    {{ isset($hour->is_closed) && $hour->is_closed ? 'checked' : '' }}
                                    class="w-4 h-4 rounded text-[#8B7355] border-gray-300 focus:ring-[#8B7355]/40"
                                    onchange="toggleTimeInputs(this, 'opening_{{ $hour->id ?? $loop->index }}', 'closing_{{ $hour->id ?? $loop->index }}', 'card_{{ $hour->id ?? $loop->index }}')"
                                />
                                <span class="text-xs font-semibold text-gray-500 dark:text-gray-300">Closed</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-3" id="times_{{ $hour->id ?? $loop->index }}"
                            style="{{ isset($hour->is_closed) && $hour->is_closed ? 'opacity:0.4; pointer-events:none;' : '' }}">
                            <div>
                                <label class="block mb-1 text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Opens</label>
                                <input type="time"
                                    id="opening_{{ $hour->id ?? $loop->index }}"
                                    name="hours[{{ $loop->index }}][opening_time]"
                                    value="{{ $hour->opening_time ?? '09:00' }}"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                    {{ isset($hour->is_closed) && $hour->is_closed ? 'disabled' : '' }}
                                />
                            </div>
                            <div>
                                <label class="block mb-1 text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Closes</label>
                                <input type="time"
                                    id="closing_{{ $hour->id ?? $loop->index }}"
                                    name="hours[{{ $loop->index }}][closing_time]"
                                    value="{{ $hour->closing_time ?? '18:00' }}"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                    {{ isset($hour->is_closed) && $hour->is_closed ? 'disabled' : '' }}
                                />
                            </div>
                        </div>

                        <input type="hidden" name="hours[{{ $loop->index }}][id]" value="{{ $hour->id ?? '' }}"/>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Branch Profile Section --}}
            @if(in_array($spa->business_tier, ['professional','enterprise']))
            <div x-data="{ listed: {{ ($branch->profile->is_listed ?? false) ? 'true' : 'false' }} }"
                class="mt-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6">

                <h2 class="text-lg font-medium text-gray-800 dark:text-white mb-4">Branch Profile</h2>

                {{-- List this branch publicly --}}
                <div class="mb-4">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="is_listed" x-model="listed" value="1"
                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600">
                        <span class="text-sm text-gray-700 dark:text-gray-300">List this branch publicly</span>
                    </label>
                </div>

                {{-- Conditional Profile Inputs --}}
                <div x-show="listed" x-transition class="space-y-4">

                    {{-- Branch Title (readonly) --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Branch Title</label>
                        <input type="text" value="{{ $branch->spa->name ?? '' }} - {{ $branch->profile->address ?? '' }}" readonly
                            class="block w-full mt-1 border-gray-300 rounded-md shadow-sm bg-gray-100 dark:bg-gray-700 dark:text-gray-300 sm:text-sm">
                    </div>

                    {{-- Description --}}
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                        <textarea name="description" id="description" rows="3"
                            class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">{{ old('description', $branch->profile->description ?? '') }}</textarea>
                    </div>

                    {{-- Phone --}}
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
                        <input type="text" name="phone" id="phone"
                            value="{{ old('phone', $branch->profile->phone ?? '') }}"
                            class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                    </div>

                    {{-- Address & Map --}}
                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Address</label>
                        <input type="text" name="address" id="address"
                            value="{{ old('address', $branch->profile->address ?? '') }}"
                            placeholder="Street, Barangay, City"
                            class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                    </div>

                    {{-- Latitude & Longitude --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="latitude" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Latitude</label>
                            <input type="text" name="latitude" id="latitude"
                                value="{{ old('latitude', $branch->profile->latitude ?? '') }}"
                                class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                        </div>

                        <div>
                            <label for="longitude" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Longitude</label>
                            <input type="text" name="longitude" id="longitude"
                                value="{{ old('longitude', $branch->profile->longitude ?? '') }}"
                                class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                        </div>
                    </div>

                    {{-- Google Map --}}
                    <div id="map" class="w-full h-64 rounded-lg mt-2"></div>

                    {{-- Cover Image --}}
                    <div>
                        <label for="cover_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cover Image</label>
                        <input type="file" name="cover_image" id="cover_image"
                            class="block w-full mt-1 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 dark:file:bg-gray-700 dark:file:text-white">
                        @if($branch->profile && $branch->profile->cover_image)
                            <img src="{{ asset('storage/' . $branch->profile->cover_image) }}" class="mt-2 h-24 rounded-md object-cover">
                        @endif
                    </div>

                    {{-- Gallery Images --}}
                    <div>
                        <label for="gallery_images" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gallery Images</label>
                        <input type="file" name="gallery_images[]" id="gallery_images" multiple
                            class="block w-full mt-1 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 dark:file:bg-gray-700 dark:file:text-white">
                        @if($branch->profile && $branch->profile->gallery_images)
                            <div class="flex flex-wrap mt-2 gap-2">
                                @foreach($branch->profile->gallery_images as $img)
                                    <img src="{{ asset('storage/' . $img) }}" class="h-24 rounded-md object-cover">
                                @endforeach
                            </div>
                        @endif
                    </div>

                </div> {{-- end x-show --}}
            </div>
            @else
            <div class="mt-6 bg-gray-50 border border-gray-200 rounded-lg shadow-sm dark:bg-gray-700 dark:border-gray-600 p-6 text-center">
                <p class="text-gray-600 dark:text-gray-300 mb-2">Branch Profile is only available for Professional or Enterprise tiers.</p>
                <button type="button"
                    class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg">
                    Upgrade to unlock
                </button>
            </div>
            @endif

            {{-- Submit Buttons --}}
            <div class="flex justify-end mt-6 space-x-3">
                <a href="{{ route('branches.index') }}"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-600 dark:border-gray-500 dark:text-white dark:hover:bg-gray-500">
                    Cancel
                </a>
                <button type="submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg focus:ring-4 focus:ring-blue-300">
                    Update Branch
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // Map initialization and interaction
    document.addEventListener('DOMContentLoaded', function () {
        const addressInput = document.getElementById('address');
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');

        const defaultLat = parseFloat(latInput.value) || 14.4323; // Imus area default
        const defaultLng = parseFloat(lngInput.value) || 120.9269;

        const map = L.map('map').setView([defaultLat, defaultLng], 13);
        // OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        let marker = L.marker([defaultLat, defaultLng], {
            draggable: true
        }).addTo(map);

        function updateInputs(lat, lng) {
            latInput.value = lat.toFixed(7);
            lngInput.value = lng.toFixed(7);
        }

        // Click on map
        map.on('click', function (e) {
            marker.setLatLng(e.latlng);
            updateInputs(e.latlng.lat, e.latlng.lng);
            reverseGeocode(e.latlng.lat, e.latlng.lng);
        });

        // Drag marker
        marker.on('dragend', function (e) {
            const position = marker.getLatLng();
            updateInputs(position.lat, position.lng);
            reverseGeocode(position.lat, position.lng);
        });

        // Reverse geocode using Nominatim (free)
        function reverseGeocode(lat, lng) {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.display_name) {
                        addressInput.value = data.display_name;
                    }
                })
                .catch(error => console.log(error));
        }
    });

    function toggleTimeInputs(checkbox, openingId, closingId, cardId) {
        const openingInput = document.getElementById(openingId);
        const closingInput = document.getElementById(closingId);
        const card = document.getElementById(cardId);

        if (!openingInput || !closingInput || !card) return;

        const isClosed = checkbox.checked;

        // Disable / enable only the time inputs
        openingInput.disabled = isClosed;
        closingInput.disabled = isClosed;

        // Dim the card visually
        card.style.opacity = isClosed ? '0.6' : '1';

        // Optional: show a "Closed all day" label
        let closedLabel = card.querySelector('.closed-label');
        if (!closedLabel) {
            closedLabel = document.createElement('p');
            closedLabel.classList.add('closed-label', 'mt-3', 'text-xs', 'italic', 'text-center', 'text-gray-400', 'dark:text-gray-300');
            closedLabel.textContent = 'Closed all day';
            card.appendChild(closedLabel);
        }
        closedLabel.style.display = isClosed ? 'block' : 'none';
    }

    // Initialize all checkboxes on page load
    document.addEventListener('DOMContentLoaded', () => {
        const checkboxes = document.querySelectorAll('input[type="checkbox"][name*="[is_closed]"]');
        checkboxes.forEach(cb => {
            const index = cb.name.match(/\[(\d+)\]/)[1];
            const cardId = 'card_' + (cb.closest('div[id^="card_"]')?.id.replace('card_', '') || index);
            const openingId = 'opening_' + (cb.closest('div[id^="card_"]')?.id.replace('card_', '') || index);
            const closingId = 'closing_' + (cb.closest('div[id^="card_"]')?.id.replace('card_', '') || index);
            toggleTimeInputs(cb, openingId, closingId, cardId);

            // Attach onchange event
            cb.addEventListener('change', () => toggleTimeInputs(cb, openingId, closingId, cardId));
        });
    });
</script>

@endsection