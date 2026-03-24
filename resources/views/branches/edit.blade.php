@extends('layouts.app')

@section('title', 'Edit Branch')

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="max-w-5xl py-3 mx-auto">

    <form method="POST" action="{{ route('branches.update', $branch->id) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Branch Information --}}
        <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="px-6 py-4 border-b dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Branch Information</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Manage the basic details of this branch.
                </p>
            </div>

            <div class="p-6 space-y-5">
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Branch Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="name"
                            id="name"
                            value="{{ old('name', $branch->name) }}"
                            required
                            class="block w-full mt-2 border-gray-300 rounded-xl shadow-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                        >
                    </div>

                    <div>
                        <label for="locationSelect" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Location <span class="text-red-500">*</span>
                        </label>
                        <div id="locationDropdownWrapper" class="mt-2">
                            <select
                                id="locationSelect"
                                name="location"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl bg-white dark:bg-gray-700 dark:border-gray-600 text-gray-900 dark:text-white focus:border-[#8B7355] focus:ring-2 focus:ring-[#8B7355]/20 focus:outline-none"
                            >
                                <option value="">Select a city in Cavite</option>
                                <optgroup label="Cities">
                                    <option value="Bacoor" {{ old('location', $branch->location) == 'Bacoor' ? 'selected' : '' }}>Bacoor</option>
                                    <option value="Cavite City" {{ old('location', $branch->location) == 'Cavite City' ? 'selected' : '' }}>Cavite City</option>
                                    <option value="Dasmariñas" {{ old('location', $branch->location) == 'Dasmariñas' ? 'selected' : '' }}>Dasmariñas</option>
                                    <option value="General Trias" {{ old('location', $branch->location) == 'General Trias' ? 'selected' : '' }}>General Trias</option>
                                    <option value="Imus" {{ old('location', $branch->location) == 'Imus' ? 'selected' : '' }}>Imus</option>
                                    <option value="Carmona" {{ old('location', $branch->location) == 'Carmona' ? 'selected' : '' }}>Carmona</option>
                                    <option value="Tagaytay" {{ old('location', $branch->location) == 'Tagaytay' ? 'selected' : '' }}>Tagaytay</option>
                                    <option value="Trece Martires" {{ old('location', $branch->location) == 'Trece Martires' ? 'selected' : '' }}>Trece Martires</option>
                                </optgroup>
                                <optgroup label="Municipalities">
                                    <option value="Alfonso" {{ old('location', $branch->location) == 'Alfonso' ? 'selected' : '' }}>Alfonso</option>
                                    <option value="Amadeo" {{ old('location', $branch->location) == 'Amadeo' ? 'selected' : '' }}>Amadeo</option>
                                    <option value="Carmen" {{ old('location', $branch->location) == 'Carmen' ? 'selected' : '' }}>Carmen</option>
                                    <option value="General Emilio Aguinaldo" {{ old('location', $branch->location) == 'General Emilio Aguinaldo' ? 'selected' : '' }}>General Emilio Aguinaldo</option>
                                    <option value="General Mariano Alvarez" {{ old('location', $branch->location) == 'General Mariano Alvarez' ? 'selected' : '' }}>General Mariano Alvarez</option>
                                    <option value="Indang" {{ old('location', $branch->location) == 'Indang' ? 'selected' : '' }}>Indang</option>
                                    <option value="Kawit" {{ old('location', $branch->location) == 'Kawit' ? 'selected' : '' }}>Kawit</option>
                                    <option value="Magallanes" {{ old('location', $branch->location) == 'Magallanes' ? 'selected' : '' }}>Magallanes</option>
                                    <option value="Maragondon" {{ old('location', $branch->location) == 'Maragondon' ? 'selected' : '' }}>Maragondon</option>
                                    <option value="Mendez" {{ old('location', $branch->location) == 'Mendez' ? 'selected' : '' }}>Mendez</option>
                                    <option value="Naic" {{ old('location', $branch->location) == 'Naic' ? 'selected' : '' }}>Naic</option>
                                    <option value="Noveleta" {{ old('location', $branch->location) == 'Noveleta' ? 'selected' : '' }}>Noveleta</option>
                                    <option value="Rosario" {{ old('location', $branch->location) == 'Rosario' ? 'selected' : '' }}>Rosario</option>
                                    <option value="Silang" {{ old('location', $branch->location) == 'Silang' ? 'selected' : '' }}>Silang</option>
                                    <option value="Tanza" {{ old('location', $branch->location) == 'Tanza' ? 'selected' : '' }}>Tanza</option>
                                    <option value="Ternate" {{ old('location', $branch->location) == 'Ternate' ? 'selected' : '' }}>Ternate</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <input
                        type="checkbox"
                        name="is_main"
                        id="is_main"
                        value="1"
                        {{ $branch->is_main ? 'checked' : '' }}
                        class="w-4 h-4 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355] dark:bg-gray-700 dark:border-gray-600"
                    >
                    <label for="is_main" class="text-sm text-gray-700 dark:text-gray-300">
                        Set as main branch
                    </label>
                </div>
            </div>
        </div>

        {{-- Workforce & Finance Suite --}}
        @php
            $isProfessionalTier = ($spa->business_tier ?? null) === 'professional';
        @endphp

        <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="px-6 py-4 border-b dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Workforce &amp; Finance Suite</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Control whether this branch can use advanced workforce and finance tools such as hiring, applicants,
                    interviews, attendance, and future payroll features.
                </p>
            </div>

            <div class="p-6 space-y-4">
                @if($isProfessionalTier)
                    <div class="p-4 border border-green-200 rounded-xl bg-green-50 dark:bg-green-900/10 dark:border-green-800">
                        <p class="text-sm text-green-800 dark:text-green-300">
                            This spa is currently on the <span class="font-semibold">Professional</span> business tier, so this
                            branch can enable the Workforce &amp; Finance Suite.
                        </p>
                    </div>
                @else
                    <div class="p-4 border border-yellow-200 rounded-xl bg-yellow-50 dark:bg-yellow-900/10 dark:border-yellow-800">
                        <p class="text-sm text-yellow-800 dark:text-yellow-300">
                            This suite is only available on the <span class="font-semibold">Professional</span> business tier.
                            Upgrade your spa subscription first before enabling it for this branch.
                        </p>

                        @if(Route::has('owner.subscription.index'))
                            <div class="mt-3">
                                <a href="{{ route('owner.subscription.index') }}"
                                class="inline-flex items-center px-3 py-2 text-xs font-medium text-white rounded-lg bg-gradient-to-r from-[#8B7355] to-[#6F5430]">
                                    <i class="mr-2 fa-solid fa-arrow-up-right-from-square"></i>
                                    View Subscription &amp; Billing
                                </a>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="flex items-start gap-3">
                    <input type="hidden" name="has_workforce_finance_suite" value="0">

                    <input
                        type="checkbox"
                        name="has_workforce_finance_suite"
                        id="has_workforce_finance_suite"
                        value="1"
                        {{ old('has_workforce_finance_suite', $branch->has_workforce_finance_suite) ? 'checked' : '' }}
                        {{ $isProfessionalTier ? '' : 'disabled' }}
                        class="w-4 h-4 mt-1 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355] dark:bg-gray-700 dark:border-gray-600 {{ $isProfessionalTier ? '' : 'opacity-60 cursor-not-allowed' }}"
                    >

                    <div>
                        <label for="has_workforce_finance_suite" class="text-sm font-medium text-gray-800 dark:text-gray-200">
                            Enable Workforce &amp; Finance Suite for this branch
                        </label>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            When enabled, this branch can access advanced workforce and finance-related modules like
                            Hiring, Applicants, Interviews, Attendance, and future Payroll tools.
                        </p>

                        @unless($isProfessionalTier)
                            <p class="mt-2 text-xs font-medium text-yellow-700 dark:text-yellow-300">
                                This option is locked because the spa is not yet on the Professional business tier.
                            </p>
                        @endunless
                    </div>
                </div>
            </div>
        </div>

        {{-- Operating Hours --}}
        @if($branch->operatingHours)
        <div x-data="{ open: false }"
            class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">

            <button
                type="button"
                @click="open = !open"
                class="flex items-center justify-between w-full px-6 py-4 text-left border-b dark:border-gray-700"
            >
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Operating Hours</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Set the opening and closing hours of this branch for each day.
                    </p>
                </div>

                <div class="flex items-center justify-center w-10 h-10 bg-gray-100 rounded-full dark:bg-gray-700">
                    <svg x-show="!open" x-cloak class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>

                    <svg x-show="open" x-cloak class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                    </svg>
                </div>
            </button>

            <div x-show="open" x-transition class="grid grid-cols-1 gap-4 p-6 md:grid-cols-2">
                @foreach($operatingHours as $hour)
                <div class="p-4 bg-white border shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700"
                    id="card_{{ $hour->id ?? $loop->index }}">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-sm font-semibold text-gray-800 dark:text-white">
                            {{ $hour->day_of_week }}
                        </h4>

                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="hours[{{ $loop->index }}][is_closed]" value="0"/>
                            <input
                                type="checkbox"
                                name="hours[{{ $loop->index }}][is_closed]"
                                value="1"
                                {{ isset($hour->is_closed) && $hour->is_closed ? 'checked' : '' }}
                                class="w-4 h-4 rounded text-[#8B7355] border-gray-300 focus:ring-[#8B7355]/40"
                                onchange="toggleTimeInputs(this, 'opening_{{ $hour->id ?? $loop->index }}', 'closing_{{ $hour->id ?? $loop->index }}', 'card_{{ $hour->id ?? $loop->index }}')"
                            />
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-300">Closed</span>
                        </label>
                    </div>

                    <div
                        class="grid grid-cols-2 gap-3"
                        id="times_{{ $hour->id ?? $loop->index }}"
                        style="{{ isset($hour->is_closed) && $hour->is_closed ? 'opacity:0.4; pointer-events:none;' : '' }}"
                    >
                        <div>
                            <label class="block mb-1 text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                Opens
                            </label>
                            <input
                                type="time"
                                id="opening_{{ $hour->id ?? $loop->index }}"
                                name="hours[{{ $loop->index }}][opening_time]"
                                value="{{ $hour->opening_time ?? '09:00' }}"
                                class="w-full px-3 py-2 text-sm text-gray-900 bg-white border border-gray-200 dark:border-gray-600 rounded-xl dark:bg-gray-700 dark:text-white"
                                {{ isset($hour->is_closed) && $hour->is_closed ? 'disabled' : '' }}
                            />
                        </div>

                        <div>
                            <label class="block mb-1 text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                Closes
                            </label>
                            <input
                                type="time"
                                id="closing_{{ $hour->id ?? $loop->index }}"
                                name="hours[{{ $loop->index }}][closing_time]"
                                value="{{ $hour->closing_time ?? '18:00' }}"
                                class="w-full px-3 py-2 text-sm text-gray-900 bg-white border border-gray-200 dark:border-gray-600 rounded-xl dark:bg-gray-700 dark:text-white"
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

        {{-- Public Listing / Branch Profile --}}
        @if($spa->verification_status === 'verified')
        <div x-data="{ listed: {{ ($branch->profile->is_listed ?? false) ? 'true' : 'false' }} }"
            class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">

            <div class="px-6 py-4 border-b dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Public Branch Profile</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Since this spa is verified, this branch can now be listed publicly on the landing page.
                </p>
            </div>

            <div class="p-6 space-y-6">
                <div class="p-4 border border-green-200 rounded-xl bg-green-50 dark:bg-green-900/10 dark:border-green-800">
                    <p class="text-sm font-medium text-green-800 dark:text-green-300">
                        This spa is verified and eligible for public branch listing.
                    </p>
                </div>

                <div>
                    <label class="inline-flex items-center gap-3">
                        <input
                            type="checkbox"
                            name="is_listed"
                            x-model="listed"
                            value="1"
                            class="w-4 h-4 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355] dark:bg-gray-700 dark:border-gray-600"
                        >
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            List this branch publicly
                        </span>
                    </label>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        When enabled, customers will be able to see this branch on the landing page.
                    </p>
                </div>

                <div x-show="listed" x-transition class="space-y-6">
                    @php
                        $city = explode(',', $branch->location)[0] ?? $branch->location;
                    @endphp

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Public Listing Title
                        </label>
                        <input
                            type="text"
                            value="{{ $branch->spa->name }} - {{ $city }}"
                            readonly
                            class="block w-full bg-gray-100 border-gray-300 shadow-sm rounded-xl dark:bg-gray-700 dark:text-gray-300 sm:text-sm"
                        >
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Description
                        </label>
                        <textarea
                            name="description"
                            id="description"
                            rows="4"
                            class="block w-full mt-1 border-gray-300 rounded-xl shadow-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                            placeholder="Write a short description about this branch, ambiance, and services."
                        >{{ old('description', $branch->profile->description ?? '') }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Phone
                            </label>
                            <input
                                type="text"
                                name="phone"
                                id="phone"
                                value="{{ old('phone', $branch->profile->phone ?? '') }}"
                                class="block w-full mt-1 border-gray-300 rounded-xl shadow-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                placeholder="Enter branch contact number"
                            >
                        </div>

                        <div>
                            <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Address
                            </label>
                            <input
                                type="text"
                                name="address"
                                id="address"
                                value="{{ old('address', $branch->profile->address ?? '') }}"
                                placeholder="Street, Barangay, City"
                                class="block w-full mt-1 border-gray-300 rounded-xl shadow-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                            >
                        </div>
                    </div>

                    <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude', $branch->profile->latitude ?? '') }}">
                    <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude', $branch->profile->longitude ?? '') }}">

                    <div class="p-4 border rounded-xl dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Pin Branch Location</h3>
                        <p class="mt-1 mb-3 text-xs text-gray-500 dark:text-gray-400">
                            Click the map or drag the marker to set the exact location of this branch.
                        </p>
                        <div id="map" class="w-full h-64 rounded-lg" x-cloak></div>
                    </div>

                    @php
                        $existingCover = $branch->profile->cover_image ?? null;
                        $existingGallery = $branch->profile->gallery_images ?? [];
                        $maxGallerySlots = 4;
                    @endphp

                    <div class="space-y-6">
                        {{-- Cover Image --}}
                        <div class="p-5 border rounded-2xl dark:border-gray-700">
                            <div class="mb-4">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Cover Image</h3>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Upload one main image that will represent this branch on the public listing.
                                </p>
                            </div>

                            <input type="hidden" name="remove_cover_image" id="remove_cover_image" value="0">

                            <div class="flex flex-col gap-4 md:flex-row md:items-start">
                                <div class="w-full md:w-64">
                                    <div id="coverPreviewWrapper"
                                        class="relative flex items-center justify-center overflow-hidden bg-gray-100 border border-dashed rounded-2xl aspect-[4/3] dark:bg-gray-700 dark:border-gray-600">

                                        <img
                                            id="coverPreview"
                                            src="{{ $existingCover ? asset('storage/' . $existingCover) : '' }}"
                                            alt="Cover Image"
                                            class="{{ $existingCover ? '' : 'hidden' }} object-cover w-full h-full"
                                        >

                                        <div id="coverPlaceholder" class="{{ $existingCover ? 'hidden' : 'text-center' }}">
                                            <i class="text-2xl text-gray-400 fa-solid fa-image"></i>
                                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">No cover image selected</p>
                                        </div>

                                        <button
                                            type="button"
                                            id="removeCoverBtn"
                                            onclick="removeCoverImage()"
                                            class="absolute flex items-center justify-center w-8 h-8 text-white bg-red-500 rounded-full top-2 right-2 hover:bg-red-600 {{ $existingCover ? '' : 'hidden' }}"
                                        >
                                            <i class="text-xs fa-solid fa-xmark"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="flex-1 space-y-3">
                                    <div>
                                        <label for="cover_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Upload Cover Image
                                        </label>
                                        <input
                                            type="file"
                                            name="cover_image"
                                            id="cover_image"
                                            accept="image/*"
                                            onchange="previewCoverImage(event)"
                                            class="block w-full mt-2 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 dark:file:bg-gray-700 dark:file:text-white"
                                        >
                                    </div>

                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Accepted image formats only. Upload a clear landscape photo for best results.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Gallery Images --}}
                        <div class="p-5 border rounded-2xl dark:border-gray-700">
                            <div class="mb-4">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Gallery Images</h3>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Add up to 4 gallery images. You can replace or remove each image individually before saving.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                @for ($i = 0; $i < $maxGallerySlots; $i++)
                                    @php
                                        $existingImage = $existingGallery[$i] ?? null;
                                    @endphp

                                    <div class="p-3 border rounded-2xl dark:border-gray-700">
                                        <input type="hidden" name="existing_gallery_images[{{ $i }}]" value="{{ $existingImage }}">
                                        <input type="hidden" name="remove_gallery_images[{{ $i }}]" id="remove_gallery_image_{{ $i }}" value="0">

                                        <div class="relative overflow-hidden bg-gray-100 border border-dashed rounded-2xl aspect-square dark:bg-gray-700 dark:border-gray-600">
                                            <img
                                                id="galleryPreview_{{ $i }}"
                                                src="{{ $existingImage ? asset('storage/' . $existingImage) : '' }}"
                                                alt="Gallery Image {{ $i + 1 }}"
                                                class="{{ $existingImage ? '' : 'hidden' }} object-cover w-full h-full"
                                            >

                                            <div
                                                id="galleryPlaceholder_{{ $i }}"
                                                class="absolute inset-0 flex flex-col items-center justify-center text-center {{ $existingImage ? 'hidden' : '' }}"
                                            >
                                                <i class="text-2xl text-gray-400 fa-solid fa-image"></i>
                                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Empty slot</p>
                                            </div>

                                            <button
                                                type="button"
                                                id="removeGalleryBtn_{{ $i }}"
                                                onclick="removeGalleryImage({{ $i }})"
                                                class="absolute flex items-center justify-center w-8 h-8 text-white bg-red-500 rounded-full top-2 right-2 hover:bg-red-600 {{ $existingImage ? '' : 'hidden' }}"
                                            >
                                                <i class="text-xs fa-solid fa-xmark"></i>
                                            </button>
                                        </div>

                                        <div class="mt-3">
                                            <label for="gallery_image_{{ $i }}"
                                                class="inline-flex items-center justify-center w-full px-3 py-2 text-sm font-medium text-gray-700 transition bg-gray-100 cursor-pointer rounded-xl hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                                                <i class="mr-2 fa-solid fa-plus"></i>
                                                {{ $existingImage ? 'Replace Image' : 'Upload Image' }}
                                            </label>
                                            <input
                                                type="file"
                                                name="gallery_images[{{ $i }}]"
                                                id="gallery_image_{{ $i }}"
                                                accept="image/*"
                                                onchange="previewGalleryImage(event, {{ $i }})"
                                                class="hidden"
                                            >
                                        </div>
                                    </div>
                                @endfor
                            </div>
                        </div>
                    </div>

                    {{-- Amenities --}}
                    <div x-data="amenitiesManager()" class="p-4 border rounded-xl dark:border-gray-700">
                        <div class="flex items-center justify-between mb-3">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Amenities
                            </label>
                            <button
                                type="button"
                                @click="openModal = true"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white rounded-lg transition-colors"
                                style="background-color: #8B7355;"
                                onmouseover="this.style.backgroundColor='#7a6449'"
                                onmouseout="this.style.backgroundColor='#8B7355'"
                            >
                                <i class="fa-solid fa-plus text-[10px]"></i>
                                Add Amenity
                            </button>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <template x-for="(amenity, index) in amenities" :key="amenity.value">
                                <label class="flex items-center gap-3 p-3 transition bg-white border border-gray-200 cursor-pointer dark:bg-gray-700 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-600">
                                    <input
                                        type="checkbox"
                                        :name="'amenities[]'"
                                        :value="amenity.value"
                                        x-model="amenity.checked"
                                        class="w-4 h-4 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355] dark:bg-gray-700 dark:border-gray-500"
                                    >
                                    <span class="text-sm text-gray-700 dark:text-gray-200" x-text="amenity.label"></span>
                                    <button
                                        x-show="amenity.custom"
                                        type="button"
                                        @click.prevent="removeCustomAmenity(index)"
                                        class="ml-auto text-gray-400 transition-colors hover:text-red-500"
                                        title="Remove"
                                    >
                                        <i class="text-xs fa-solid fa-xmark"></i>
                                    </button>
                                </label>
                            </template>
                        </div>

                        {{-- Add Amenity Modal --}}
                        <div
                            x-show="openModal"
                            x-transition.opacity
                            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                            @click.self="openModal = false"
                        >
                            <div class="w-full max-w-sm p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800" @click.stop>
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Add Custom Amenity</h3>
                                    <button type="button" @click="openModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>

                                <input
                                    type="text"
                                    x-model="newAmenityLabel"
                                    @keydown.enter.prevent="addCustomAmenity()"
                                    placeholder="e.g. Steam Room, Jacuzzi, Foot Bath..."
                                    class="block w-full border-gray-300 rounded-xl shadow-sm text-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                >

                                <p x-show="errorMsg" x-text="errorMsg" class="mt-2 text-xs text-red-500"></p>

                                <div class="flex justify-end gap-2 mt-4">
                                    <button
                                        type="button"
                                        @click="openModal = false"
                                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        @click="addCustomAmenity()"
                                        class="px-4 py-2 text-sm font-medium text-white transition-colors rounded-lg"
                                        style="background-color: #8B7355;"
                                        onmouseover="this.style.backgroundColor='#7a6449'"
                                        onmouseout="this.style.backgroundColor='#8B7355'"
                                    >
                                        Add
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ✅ Submit Buttons moved inside card --}}
            <div class="flex justify-end gap-3 px-6 py-4 border-t dark:border-gray-700">
                <a href="{{ route('branches.index') }}"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-600 dark:border-gray-500 dark:text-white dark:hover:bg-gray-500">
                    Cancel
                </a>
                <button type="submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg focus:ring-4 focus:ring-[#8B7355]/30">
                    Update Branch
                </button>
            </div>
        </div>

        @else

        {{-- Unverified spa card --}}
        <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="px-6 py-4 border-b dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Public Branch Profile</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Public listing is only available for verified spas.
                </p>
            </div>

            <div class="p-6">
                <div class="p-4 border border-yellow-200 rounded-xl bg-yellow-50 dark:bg-yellow-900/10 dark:border-yellow-800">
                    <p class="text-sm text-yellow-800 dark:text-yellow-300">
                        This spa is not yet verified, so this branch cannot be listed publicly yet.
                    </p>
                </div>

                @if(Route::has('owner.spa-profile.edit'))
                    <div class="mt-4">
                        <a href="{{ route('owner.spa-profile.edit') }}"
                           class="inline-flex px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg">
                            Go to Spa Profile
                        </a>
                    </div>
                @endif
            </div>

            {{-- ✅ Submit Buttons inside unverified card too --}}
            <div class="flex justify-end gap-3 px-6 py-4 border-t dark:border-gray-700">
                <a href="{{ route('branches.index') }}"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-600 dark:border-gray-500 dark:text-white dark:hover:bg-gray-500">
                    Cancel
                </a>
                <button type="submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg focus:ring-4 focus:ring-[#8B7355]/30">
                    Update Branch
                </button>
            </div>
        </div>
        @endif

    </form>
</div>

<script>
function amenitiesManager() {
    return {
        openModal: false,
        newAmenityLabel: '',
        errorMsg: '',

        amenities: [
            @php
                $selectedAmenities = $branch->profile->amenities ?? [];
                $defaultAmenities = [
                    'aircon'          => 'Air Conditioning',
                    'private_rooms'   => 'Private Rooms',
                    'shower'          => 'Shower',
                    'parking'         => 'Parking',
                    'wifi'            => 'WiFi',
                    'locker'          => 'Locker',
                    'pet_friendly'    => 'Pet Friendly',
                    'sauna'           => 'Sauna',
                ];
                // Detect custom amenities saved previously (not in default list)
                $customAmenities = array_diff($selectedAmenities, array_keys($defaultAmenities));
            @endphp

            @foreach($defaultAmenities as $value => $label)
                { value: '{{ $value }}', label: '{{ $label }}', checked: {{ in_array($value, $selectedAmenities) ? 'true' : 'false' }}, custom: false },
            @endforeach

            @foreach($customAmenities as $customValue)
                { value: '{{ $customValue }}', label: '{{ ucwords(str_replace('_', ' ', $customValue)) }}', checked: true, custom: true },
            @endforeach
        ],

        addCustomAmenity() {
            this.errorMsg = '';
            const label = this.newAmenityLabel.trim();

            if (!label) {
                this.errorMsg = 'Please enter an amenity name.';
                return;
            }

            const value = label.toLowerCase().replace(/\s+/g, '_');
            const exists = this.amenities.some(a => a.value === value);

            if (exists) {
                this.errorMsg = 'This amenity already exists.';
                return;
            }

            this.amenities.push({ value, label, checked: true, custom: true });
            this.newAmenityLabel = '';
            this.openModal = false;
        },

        removeCustomAmenity(index) {
            this.amenities.splice(index, 1);
        }
    };
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const addressInput = document.getElementById('address');
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    const mapContainer = document.getElementById('map');

    if (!mapContainer || !addressInput || !latInput || !lngInput) return;

    const caviteBounds = [
        [13.983, 120.850],
        [14.600, 121.200]
    ];

    const defaultLat = parseFloat(latInput.value) || 14.4323;
    const defaultLng = parseFloat(lngInput.value) || 120.9269;

    let mapInitialized = false;

    function initMap() {
        if (mapInitialized) return;
        mapInitialized = true;

        const map = L.map('map', {
            maxBounds: caviteBounds,
            maxBoundsViscosity: 0.8,
            zoomControl: true
        }).setView([defaultLat, defaultLng], 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);

        function updateInputs(lat, lng) {
            latInput.value = lat.toFixed(7);
            lngInput.value = lng.toFixed(7);
        }

        map.on('click', e => {
            marker.setLatLng(e.latlng);
            updateInputs(e.latlng.lat, e.latlng.lng);
            reverseGeocode(e.latlng.lat, e.latlng.lng);
        });

        marker.on('dragend', () => {
            const pos = marker.getLatLng();
            updateInputs(pos.lat, pos.lng);
            reverseGeocode(pos.lat, pos.lng);
        });

        function reverseGeocode(lat, lng) {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`, {
                headers: { 'User-Agent': 'SpaManagementSystem/1.0 (student-project)' }
            })
            .then(res => res.json())
            .then(data => {
                if (data?.display_name) addressInput.value = data.display_name;
            })
            .catch(err => console.log(err));
        }

        setTimeout(() => map.invalidateSize(), 300);
    }

    // ✅ Poll until map container is visible — works with Alpine x-show
    const checkVisible = setInterval(() => {
        if (mapContainer.offsetParent !== null && mapContainer.offsetHeight > 0) {
            clearInterval(checkVisible);
            initMap();
        }
    }, 100);
});

function previewCoverImage(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('coverPreview');
    const removeBtn = document.getElementById('removeCoverBtn');
    const removeInput = document.getElementById('remove_cover_image');
    const placeholder = document.getElementById('coverPlaceholder');

    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        preview.src = e.target.result;
        preview.classList.remove('hidden');
        removeBtn.classList.remove('hidden');
        removeInput.value = '0';
        if (placeholder) placeholder.classList.add('hidden');
    };
    reader.readAsDataURL(file);
}

function removeCoverImage() {
    const preview = document.getElementById('coverPreview');
    const input = document.getElementById('cover_image');
    const removeBtn = document.getElementById('removeCoverBtn');
    const removeInput = document.getElementById('remove_cover_image');
    const placeholder = document.getElementById('coverPlaceholder');

    preview.src = '';
    preview.classList.add('hidden');
    input.value = '';
    removeBtn.classList.add('hidden');
    removeInput.value = '1';
    if (placeholder) placeholder.classList.remove('hidden');
}

function previewGalleryImage(event, index) {
    const file = event.target.files[0];
    if (!file) return;

    const preview = document.getElementById(`galleryPreview_${index}`);
    const placeholder = document.getElementById(`galleryPlaceholder_${index}`);
    const removeBtn = document.getElementById(`removeGalleryBtn_${index}`);
    const removeInput = document.getElementById(`remove_gallery_image_${index}`);

    const reader = new FileReader();
    reader.onload = function(e) {
        preview.src = e.target.result;
        preview.classList.remove('hidden');
        removeBtn.classList.remove('hidden');
        removeInput.value = '0';
        if (placeholder) placeholder.classList.add('hidden');
    };
    reader.readAsDataURL(file);
}

function removeGalleryImage(index) {
    const preview = document.getElementById(`galleryPreview_${index}`);
    const placeholder = document.getElementById(`galleryPlaceholder_${index}`);
    const removeBtn = document.getElementById(`removeGalleryBtn_${index}`);
    const input = document.getElementById(`gallery_image_${index}`);
    const removeInput = document.getElementById(`remove_gallery_image_${index}`);

    preview.src = '';
    preview.classList.add('hidden');
    input.value = '';
    removeBtn.classList.add('hidden');
    removeInput.value = '1';
    if (placeholder) placeholder.classList.remove('hidden');
}

function toggleTimeInputs(checkbox, openingId, closingId, cardId) {
    const openingInput = document.getElementById(openingId);
    const closingInput = document.getElementById(closingId);
    const card = document.getElementById(cardId);

    if (!openingInput || !closingInput || !card) return;

    const isClosed = checkbox.checked;
    openingInput.disabled = isClosed;
    closingInput.disabled = isClosed;
    card.style.opacity = isClosed ? '0.6' : '1';

    let closedLabel = card.querySelector('.closed-label');
    if (!closedLabel) {
        closedLabel = document.createElement('p');
        closedLabel.classList.add('closed-label', 'mt-3', 'text-xs', 'italic', 'text-center', 'text-gray-400', 'dark:text-gray-300');
        closedLabel.textContent = 'Closed all day';
        card.appendChild(closedLabel);
    }
    closedLabel.style.display = isClosed ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', () => {
    const checkboxes = document.querySelectorAll('input[type="checkbox"][name*="[is_closed]"]');
    checkboxes.forEach(cb => {
        const index = cb.name.match(/\[(\d+)\]/)[1];
        const cardId = 'card_' + (cb.closest('div[id^="card_"]')?.id.replace('card_', '') || index);
        const openingId = 'opening_' + (cb.closest('div[id^="card_"]')?.id.replace('card_', '') || index);
        const closingId = 'closing_' + (cb.closest('div[id^="card_"]')?.id.replace('card_', '') || index);

        toggleTimeInputs(cb, openingId, closingId, cardId);
        cb.addEventListener('change', () => toggleTimeInputs(cb, openingId, closingId, cardId));
    });
});
</script>
@endsection
