<!-- resources/views/branches/edit.blade.php -->

@extends('layouts.app')

@section('title', 'Edit Branch — ' . $branch->name)

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

@php
    // Determine which tab to open:
    //   1. After a successful save → we redirect with ?tab=xxx, so read from query string
    //   2. After a validation error → back() keeps the same URL, so query string still correct
    //   3. Direct link from index → query string set by the card buttons
    $activeTab = request()->query('tab', 'general');
    if (!in_array($activeTab, ['general', 'hours', 'profile'])) {
        $activeTab = 'general';
    }
@endphp

<div class="max-w-3xl py-3 mx-auto"
     x-data="branchEditPage()"
     x-init="init()">

    {{-- ── Page Header ─────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('branches.index') }}"
           class="flex items-center justify-center w-9 h-9 text-gray-500 transition border border-gray-200 rounded-xl hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700">
            <i class="text-sm fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $branch->name }}</h1>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                <i class="mr-1 fa-solid fa-location-dot text-[#8B7355] text-xs"></i>
                {{ $branch->location }}
            </p>
        </div>
    </div>

    {{-- ── Flash messages ───────────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="flex items-center gap-3 p-4 mb-5 text-sm text-green-700 bg-green-50 rounded-2xl ring-1 ring-green-200 dark:bg-green-900/10 dark:text-green-300 dark:ring-green-800">
            <i class="fa-solid fa-circle-check flex-shrink-0"></i>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="flex items-center gap-3 p-4 mb-5 text-sm text-red-600 bg-red-50 rounded-2xl ring-1 ring-red-200 dark:bg-red-900/10 dark:text-red-400 dark:ring-red-800">
            <i class="fa-solid fa-circle-exclamation flex-shrink-0"></i>
            {{ session('error') }}
        </div>
    @endif

    {{-- ── Tab Navigation ───────────────────────────────────────────────── --}}
    <div class="flex border-b border-gray-200 dark:border-gray-700 mb-6">
        <button @click="tab = 'general'"
            :class="tab === 'general'
                ? 'border-[#8B7355] text-[#6F5430] dark:text-[#C4A97D]'
                : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
            class="flex items-center gap-2 px-5 py-3 text-sm font-medium border-b-2 transition-colors -mb-px">
            <i class="fa-solid fa-pen text-xs"></i>
            General
        </button>

        <button @click="tab = 'hours'"
            :class="tab === 'hours'
                ? 'border-[#8B7355] text-[#6F5430] dark:text-[#C4A97D]'
                : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
            class="flex items-center gap-2 px-5 py-3 text-sm font-medium border-b-2 transition-colors -mb-px">
            <i class="fa-solid fa-clock text-xs"></i>
            Operating Hours
        </button>

        <button @click="tab = 'profile'"
            :class="tab === 'profile'
                ? 'border-[#8B7355] text-[#6F5430] dark:text-[#C4A97D]'
                : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
            class="flex items-center gap-2 px-5 py-3 text-sm font-medium border-b-2 transition-colors -mb-px">
            <i class="fa-solid fa-image text-xs"></i>
            Public Profile
            @if(optional($branch->profile)->is_listed)
                <span class="px-1.5 py-0.5 text-[10px] font-semibold rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                    Live
                </span>
            @endif
        </button>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════
         TAB 1 — GENERAL INFO
    ═════════════════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'general'" x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">

        <form method="POST" action="{{ route('branches.update.general', $branch->id) }}">
            @csrf
            @method('PUT')

            <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 space-y-5">

                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Branch Information</h2>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Update the display name and main branch status.</p>
                </div>

                @if($errors->has('name'))
                    <div class="p-3 text-sm text-red-600 bg-red-50 rounded-xl ring-1 ring-red-200">
                        {{ $errors->first('name') }}
                    </div>
                @endif

                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Branch Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" id="name"
                           value="{{ old('name', $branch->name) }}"
                           required
                           class="block w-full mt-2 border-gray-300 rounded-xl shadow-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                </div>

                {{-- Location (read-only) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Location</label>
                    <div class="flex items-center gap-2 mt-2 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl dark:bg-gray-700 dark:border-gray-600">
                        <i class="fa-solid fa-location-dot text-[#8B7355] text-sm flex-shrink-0"></i>
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $branch->location }}</p>
                        <span class="ml-auto text-[10px] text-gray-400 dark:text-gray-500">Cannot be changed</span>
                    </div>
                </div>

                {{-- Main branch --}}
                <div class="flex items-start gap-3 p-4 rounded-xl bg-gray-50 dark:bg-gray-700/50">
                    <input type="hidden" name="is_main" value="0">
                    <input type="checkbox" id="is_main" name="is_main" value="1"
                           {{ $branch->is_main ? 'checked' : '' }}
                           class="mt-0.5 w-4 h-4 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355] dark:bg-gray-600 dark:border-gray-500">
                    <div>
                        <label for="is_main" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Set as main branch
                        </label>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            The main branch is the spa's primary location. Only one branch can be main at a time.
                        </p>
                    </div>
                </div>

            </div>

            <div class="flex items-center justify-between mt-4">
                <a href="{{ route('branches.index') }}"
                   class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition">
                    ← Back to Branches
                </a>
                <button type="submit"
                    class="px-6 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-xl hover:opacity-90 transition shadow-sm">
                    Save General Info
                </button>
            </div>

        </form>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════
         TAB 2 — OPERATING HOURS
    ═════════════════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'hours'" x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">

        <form method="POST" action="{{ route('branches.update.hours', $branch->id) }}">
            @csrf
            @method('PUT')

            <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 space-y-5">

                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Operating Hours</h2>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        Set opening and closing times for each day. These are enforced during booking validation.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach($operatingHours as $hour)
                    @php $suffix = $hour->id ?? $loop->index; @endphp

                    <div class="p-4 border border-gray-100 rounded-2xl dark:border-gray-700 dark:bg-gray-800/50"
                         id="hours_card_{{ $suffix }}">

                        <div class="flex items-center justify-between mb-3">
                            <p class="text-sm font-semibold text-gray-800 dark:text-white">{{ $hour->day_of_week }}</p>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="hours[{{ $loop->index }}][is_closed]" value="0" />
                                <input type="checkbox"
                                    name="hours[{{ $loop->index }}][is_closed]"
                                    value="1"
                                    {{ isset($hour->is_closed) && $hour->is_closed ? 'checked' : '' }}
                                    class="w-4 h-4 rounded text-[#8B7355] border-gray-300 focus:ring-[#8B7355]/40"
                                    onchange="toggleHoursCard(this, '{{ $suffix }}')" />
                                <span class="text-xs font-semibold text-gray-500 dark:text-gray-300">Closed</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-3"
                             id="hours_times_{{ $suffix }}"
                             style="{{ isset($hour->is_closed) && $hour->is_closed ? 'opacity:0.4;pointer-events:none;' : '' }}">
                            <div>
                                <label class="block mb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Opens</label>
                                <input type="time"
                                    id="opening_{{ $suffix }}"
                                    name="hours[{{ $loop->index }}][opening_time]"
                                    value="{{ $hour->opening_time ?? '09:00' }}"
                                    {{ isset($hour->is_closed) && $hour->is_closed ? 'disabled' : '' }}
                                    class="w-full px-3 py-2 text-sm text-gray-900 bg-white border border-gray-200 rounded-xl dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <input type="hidden" name="hours[{{ $loop->index }}][day_of_week]" value="{{ $hour->day_of_week }}">
                            </div>
                            <div>
                                <label class="block mb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Closes</label>
                                <input type="time"
                                    id="closing_{{ $suffix }}"
                                    name="hours[{{ $loop->index }}][closing_time]"
                                    value="{{ $hour->closing_time ?? '18:00' }}"
                                    {{ isset($hour->is_closed) && $hour->is_closed ? 'disabled' : '' }}
                                    class="w-full px-3 py-2 text-sm text-gray-900 bg-white border border-gray-200 rounded-xl dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                        </div>

                        <input type="hidden" name="hours[{{ $loop->index }}][id]" value="{{ $hour->id ?? '' }}">

                        @if(isset($hour->is_closed) && $hour->is_closed)
                            <p class="mt-2 text-xs italic text-center text-gray-400 closed-day-label">Closed all day</p>
                        @else
                            <p class="mt-2 text-xs italic text-center text-gray-400 closed-day-label hidden">Closed all day</p>
                        @endif
                    </div>
                    @endforeach
                </div>

            </div>

            <div class="flex items-center justify-between mt-4">
                <a href="{{ route('branches.index') }}"
                   class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 transition">
                    ← Back to Branches
                </a>
                <button type="submit"
                    class="px-6 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-xl hover:opacity-90 transition shadow-sm">
                    Save Operating Hours
                </button>
            </div>

        </form>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════
         TAB 3 — PUBLIC PROFILE
    ═════════════════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'profile'" x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         @tab-profile-shown.window="initProfileMap()">

        @if($spa->verification_status !== 'verified')
            {{-- ── Unverified State ──────────────────────────────────────── --}}
            <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="flex flex-col items-center py-8 text-center">
                    <div class="flex items-center justify-center w-14 h-14 mb-4 rounded-2xl bg-amber-50 dark:bg-amber-900/20">
                        <i class="fa-solid fa-lock text-xl text-amber-500"></i>
                    </div>
                    <h3 class="font-semibold text-gray-900 dark:text-white">Spa Verification Required</h3>
                    <p class="mt-2 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                        Your spa must be verified before this branch can be listed publicly or have a profile page.
                    </p>
                    @if(Route::has('owner.spa-profile.edit'))
                        <a href="{{ route('owner.spa-profile.edit') }}"
                           class="inline-flex items-center gap-2 mt-5 px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-xl hover:opacity-90">
                            <i class="fa-solid fa-arrow-right text-xs"></i>
                            Go to Spa Profile
                        </a>
                    @endif
                </div>
            </div>

        @else
            {{-- ── Verified: Full Profile Form ─────────────────────────── --}}
            <form method="POST"
                  action="{{ route('branches.update.profile', $branch->id) }}"
                  enctype="multipart/form-data">
                @csrf
                @method('PUT')

                {{-- Validation errors --}}
                @if($errors->any())
                    <div class="p-4 mb-5 text-sm text-red-600 bg-red-50 rounded-2xl ring-1 ring-red-200 dark:bg-red-900/10 dark:ring-red-800">
                        <p class="font-semibold mb-1">Please fix the following:</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- ── Card 1: Listing Toggle ────────────────────────────── --}}
                <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 mb-4"
                     x-data="{ listed: {{ optional($branch->profile)->is_listed ? 'true' : 'false' }} }">

                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Public Listing</h2>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                When enabled, customers will see this branch on the landing page.
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="is_listed" value="0">
                            <input type="checkbox" name="is_listed" value="1"
                                   x-model="listed"
                                   {{ optional($branch->profile)->is_listed ? 'checked' : '' }}
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-[#8B7355] transition-colors dark:bg-gray-600
                                        after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all
                                        peer-checked:after:translate-x-5"></div>
                        </label>
                    </div>

                    <div x-show="listed"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                         class="flex items-center gap-2 mt-3 px-3 py-2 bg-green-50 rounded-xl ring-1 ring-green-200 dark:bg-green-900/10 dark:ring-green-800">
                        <i class="fa-solid fa-circle-check text-green-500 text-xs"></i>
                        <p class="text-xs font-medium text-green-700 dark:text-green-300">
                            This branch is visible on the public landing page.
                        </p>
                    </div>

                </div>

                {{-- ── Card 2: Basic Info ────────────────────────────────── --}}
                <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 mb-4 space-y-4">

                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Branch Details</h2>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                            Info shown to customers on the listing page.
                        </p>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                        <textarea name="description" id="description" rows="3"
                            placeholder="Describe this branch — ambiance, services, what makes it special."
                            class="block w-full mt-2 border-gray-300 rounded-xl shadow-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm resize-none">{{ old('description', optional($branch->profile)->description) }}</textarea>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
                            <input type="text" name="phone" id="phone"
                                   maxlength="11" pattern="^09\d{9}$"
                                   value="{{ old('phone', optional($branch->profile)->phone) }}"
                                   placeholder="09xxxxxxxxx"
                                   class="block w-full mt-2 border-gray-300 rounded-xl shadow-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                        </div>
                        <div>
                            <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Address</label>
                            <input type="text" name="address" id="address"
                                   value="{{ old('address', optional($branch->profile)->address) }}"
                                   placeholder="Street, Barangay, City"
                                   class="block w-full mt-2 border-gray-300 rounded-xl shadow-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                        </div>
                    </div>

                </div>

                {{-- ── Card 3: Map Pin ───────────────────────────────────── --}}
                <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 mb-4">

                    <div class="mb-4">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Branch Location Pin</h2>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                            Click the map or drag the marker to set the exact location. Cavite area only.
                        </p>
                    </div>

                    <input type="hidden" name="latitude"  id="latitude"  value="{{ old('latitude',  optional($branch->profile)->latitude) }}">
                    <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude', optional($branch->profile)->longitude) }}">

                    <div id="profileMap" class="w-full h-64 rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden"></div>

                    {{-- Cavite toast --}}
                    <div id="caviteToast"
                         class="hidden mt-3 flex items-center gap-2 p-3 text-sm text-red-600 bg-red-50 rounded-xl ring-1 ring-red-200 dark:bg-red-900/10 dark:ring-red-800 dark:text-red-400">
                        <i class="fa-solid fa-location-crosshairs flex-shrink-0"></i>
                        <span id="caviteToastMsg">Please pin a location within Cavite only.</span>
                    </div>

                </div>

                {{-- ── Card 4: Cover Image ───────────────────────────────── --}}
                @php
                    $existingCover   = optional($branch->profile)->cover_image;
                    $existingGallery = optional($branch->profile)->gallery_images ?? [];
                @endphp

                <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 mb-4">

                    <div class="mb-4">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Cover Image</h2>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                            The main photo shown on the listing card.
                        </p>
                    </div>

                    <input type="hidden" name="remove_cover_image" id="remove_cover_image" value="0">

                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                        <div class="w-full sm:w-52 flex-shrink-0">
                            <div class="relative flex items-center justify-center overflow-hidden bg-gray-100 border border-dashed border-gray-300 rounded-2xl aspect-[4/3] dark:bg-gray-700 dark:border-gray-600">
                                <img id="coverPreview"
                                     src="{{ $existingCover ? asset('storage/' . $existingCover) : '' }}"
                                     class="{{ $existingCover ? '' : 'hidden' }} object-cover w-full h-full">
                                <div id="coverPlaceholder" class="{{ $existingCover ? 'hidden' : '' }} flex flex-col items-center gap-1">
                                    <i class="text-2xl text-gray-300 fa-solid fa-image"></i>
                                    <p class="text-xs text-gray-400">No image</p>
                                </div>
                                <button type="button" id="removeCoverBtn"
                                    onclick="removeCoverImage()"
                                    class="{{ $existingCover ? '' : 'hidden' }} absolute top-2 right-2 flex items-center justify-center w-7 h-7 text-white bg-red-500 rounded-full hover:bg-red-600">
                                    <i class="text-xs fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div>
                        <div class="flex-1">
                            <label for="cover_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Upload New Cover</label>
                            <input type="file" name="cover_image" id="cover_image"
                                   accept="image/*" onchange="previewCoverImage(event)"
                                   class="block w-full mt-2 text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 dark:file:bg-gray-700 dark:file:text-white">
                            <p class="mt-2 text-xs text-gray-400">JPG, PNG, or WebP. Max 2MB. Landscape ratio recommended.</p>
                        </div>
                    </div>

                </div>

                {{-- ── Card 5: Gallery ──────────────────────────────────── --}}
                <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 mb-4">

                    <div class="mb-4">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Gallery Images</h2>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                            Up to 4 additional photos shown in the branch detail view.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @for($i = 0; $i < 4; $i++)
                        @php $existingImage = $existingGallery[$i] ?? null; @endphp

                        <div class="space-y-2">
                            <input type="hidden" name="existing_gallery_images[{{ $i }}]" value="{{ $existingImage }}">
                            <input type="hidden" name="remove_gallery_images[{{ $i }}]" id="remove_gallery_image_{{ $i }}" value="0">

                            <div class="relative overflow-hidden bg-gray-100 border border-dashed border-gray-300 rounded-2xl aspect-square dark:bg-gray-700 dark:border-gray-600">
                                <img id="galleryPreview_{{ $i }}"
                                     src="{{ $existingImage ? asset('storage/' . $existingImage) : '' }}"
                                     class="{{ $existingImage ? '' : 'hidden' }} object-cover w-full h-full">
                                <div id="galleryPlaceholder_{{ $i }}"
                                     class="{{ $existingImage ? 'hidden' : '' }} absolute inset-0 flex flex-col items-center justify-center">
                                    <i class="text-xl text-gray-300 fa-solid fa-image"></i>
                                    <p class="mt-1 text-[10px] text-gray-400">Empty</p>
                                </div>
                                <button type="button" id="removeGalleryBtn_{{ $i }}"
                                    onclick="removeGalleryImage({{ $i }})"
                                    class="{{ $existingImage ? '' : 'hidden' }} absolute top-1.5 right-1.5 flex items-center justify-center w-6 h-6 text-white bg-red-500 rounded-full hover:bg-red-600">
                                    <i class="text-[10px] fa-solid fa-xmark"></i>
                                </button>
                            </div>

                            <label for="gallery_image_{{ $i }}"
                                class="flex items-center justify-center w-full py-1.5 text-xs font-medium text-gray-600 transition bg-gray-50 border border-gray-200 cursor-pointer rounded-xl hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                                <i class="mr-1 fa-solid fa-plus text-[10px]"></i>
                                {{ $existingImage ? 'Replace' : 'Upload' }}
                            </label>
                            <input type="file" id="gallery_image_{{ $i }}" name="gallery_images[{{ $i }}]"
                                   accept="image/*" class="hidden"
                                   onchange="previewGalleryImage(event, {{ $i }})">
                        </div>
                        @endfor
                    </div>

                </div>

                {{-- ── Card 6: Amenities ────────────────────────────────── --}}
                <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 mb-4"
                     x-data="amenitiesManager()">

                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Amenities</h2>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Features offered at this branch.</p>
                        </div>
                        <button type="button" @click="openModal = true"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white rounded-lg bg-[#8B7355] hover:bg-[#7a6449] transition">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                            Add Custom
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <template x-for="(amenity, index) in amenities" :key="amenity.value">
                            <label class="flex items-center gap-3 p-3 transition bg-gray-50 border border-gray-100 cursor-pointer dark:bg-gray-700 dark:border-gray-600 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-600">
                                <input type="checkbox" :name="'amenities[]'" :value="amenity.value"
                                       x-model="amenity.checked"
                                       class="w-4 h-4 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355] dark:bg-gray-700">
                                <span class="flex-1 text-sm text-gray-700 dark:text-gray-200" x-text="amenity.label"></span>
                                <button x-show="amenity.custom" type="button" @click.prevent="removeCustomAmenity(index)"
                                    class="text-gray-300 hover:text-red-500 transition">
                                    <i class="text-xs fa-solid fa-xmark"></i>
                                </button>
                            </label>
                        </template>
                    </div>

                    {{-- Add custom amenity modal --}}
                    <div x-show="openModal" x-transition.opacity
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                         @click.self="openModal = false">
                        <div class="w-full max-w-sm p-6 bg-white rounded-2xl shadow-xl dark:bg-gray-800" @click.stop>
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Add Custom Amenity</h3>
                                <button type="button" @click="openModal = false" class="text-gray-400 hover:text-gray-600">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                            <input type="text" x-model="newAmenityLabel"
                                   @keydown.enter.prevent="addCustomAmenity()"
                                   placeholder="e.g. Steam Room, Jacuzzi, Foot Bath..."
                                   class="block w-full text-sm border-gray-300 rounded-xl shadow-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <p x-show="errorMsg" x-text="errorMsg" class="mt-2 text-xs text-red-500"></p>
                            <div class="flex justify-end gap-2 mt-4">
                                <button type="button" @click="openModal = false"
                                    class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300">
                                    Cancel
                                </button>
                                <button type="button" @click="addCustomAmenity()"
                                    class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-lg hover:bg-[#7a6449]">
                                    Add
                                </button>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="flex items-center justify-between mt-4">
                    <a href="{{ route('branches.index') }}"
                       class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 transition">
                        ← Back to Branches
                    </a>
                    <button type="submit"
                        class="px-6 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-xl hover:opacity-90 transition shadow-sm">
                        Save Public Profile
                    </button>
                </div>

            </form>
        @endif

    </div>{{-- end profile tab --}}

</div>{{-- end x-data wrapper --}}

{{-- ════════════════════════════════════════════════════════════════════════
     SCRIPTS
═════════════════════════════════════════════════════════════════════════ --}}
<script>
// ── Alpine component
function branchEditPage() {
    return {
        tab: '{{ $activeTab }}',

        init() {
            // Sync URL when tab changes so browser back/forward works
            this.$watch('tab', (value) => {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', value);
                window.history.replaceState({}, '', url.toString());

                // Trigger map init when profile tab opens
                if (value === 'profile') {
                    this.$nextTick(() => {
                        this.$dispatch('tab-profile-shown');
                    });
                }
            });

            // If landing on profile tab, init map after DOM settles
            if (this.tab === 'profile') {
                this.$nextTick(() => {
                    this.$dispatch('tab-profile-shown');
                });
            }
        },

        initProfileMap() {
            initLeafletMap();
        }
    };
}

// ── Amenities manager
function amenitiesManager() {
    return {
        openModal: false,
        newAmenityLabel: '',
        errorMsg: '',

        amenities: [
            @php
                $selectedAmenities = optional($branch->profile)->amenities ?? [];
                $defaultAmenities  = [
                    'aircon'        => 'Air Conditioning',
                    'private_rooms' => 'Private Rooms',
                    'shower'        => 'Shower',
                    'parking'       => 'Parking',
                    'wifi'          => 'WiFi',
                    'locker'        => 'Locker',
                    'pet_friendly'  => 'Pet Friendly',
                    'sauna'         => 'Sauna',
                ];
                $customAmenities = array_diff($selectedAmenities, array_keys($defaultAmenities));
            @endphp

            @foreach($defaultAmenities as $value => $label)
                { value: '{{ $value }}', label: '{{ $label }}', checked: {{ in_array($value, $selectedAmenities) ? 'true' : 'false' }}, custom: false },
            @endforeach
            @foreach($customAmenities as $customValue)
                { value: '{{ $customValue }}', label: '{{ ucwords(str_replace("_", " ", $customValue)) }}', checked: true, custom: true },
            @endforeach
        ],

        addCustomAmenity() {
            this.errorMsg = '';
            const label   = this.newAmenityLabel.trim();
            if (!label) { this.errorMsg = 'Please enter an amenity name.'; return; }
            const value   = label.toLowerCase().replace(/\s+/g, '_');
            if (this.amenities.some(a => a.value === value)) { this.errorMsg = 'Already exists.'; return; }
            this.amenities.push({ value, label, checked: true, custom: true });
            this.newAmenityLabel = '';
            this.openModal       = false;
        },

        removeCustomAmenity(index) { this.amenities.splice(index, 1); }
    };
}

// ── Operating hours toggle
function toggleHoursCard(checkbox, suffix) {
    const timesDiv   = document.getElementById(`hours_times_${suffix}`);
    const closedLabel = document.querySelector(`#hours_card_${suffix} .closed-day-label`);
    const inputs     = timesDiv?.querySelectorAll('input[type="time"]');
    const isClosed   = checkbox.checked;

    if (timesDiv) timesDiv.style.opacity = isClosed ? '0.4' : '1';
    if (timesDiv) timesDiv.style.pointerEvents = isClosed ? 'none' : '';
    inputs?.forEach(input => { input.disabled = isClosed; });
    if (closedLabel) closedLabel.classList.toggle('hidden', !isClosed);
}

// ── Cover image
function previewCoverImage(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
        const preview = document.getElementById('coverPreview');
        const placeholder = document.getElementById('coverPlaceholder');
        const removeBtn = document.getElementById('removeCoverBtn');
        preview.src = e.target.result;
        preview.classList.remove('hidden');
        placeholder?.classList.add('hidden');
        removeBtn?.classList.remove('hidden');
        document.getElementById('remove_cover_image').value = '0';
    };
    reader.readAsDataURL(file);
}

function removeCoverImage() {
    document.getElementById('coverPreview').src = '';
    document.getElementById('coverPreview').classList.add('hidden');
    document.getElementById('coverPlaceholder')?.classList.remove('hidden');
    document.getElementById('removeCoverBtn')?.classList.add('hidden');
    document.getElementById('cover_image').value = '';
    document.getElementById('remove_cover_image').value = '1';
}

// ── Gallery images
function previewGalleryImage(event, index) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
        const preview     = document.getElementById(`galleryPreview_${index}`);
        const placeholder = document.getElementById(`galleryPlaceholder_${index}`);
        const removeBtn   = document.getElementById(`removeGalleryBtn_${index}`);
        preview.src = e.target.result;
        preview.classList.remove('hidden');
        placeholder?.classList.add('hidden');
        removeBtn?.classList.remove('hidden');
        document.getElementById(`remove_gallery_image_${index}`).value = '0';
    };
    reader.readAsDataURL(file);
}

function removeGalleryImage(index) {
    document.getElementById(`galleryPreview_${index}`).src = '';
    document.getElementById(`galleryPreview_${index}`).classList.add('hidden');
    document.getElementById(`galleryPlaceholder_${index}`)?.classList.remove('hidden');
    document.getElementById(`removeGalleryBtn_${index}`)?.classList.add('hidden');
    document.getElementById(`gallery_image_${index}`).value = '';
    document.getElementById(`remove_gallery_image_${index}`).value = '1';
}

// ── Leaflet map (profile tab)
let leafletMapInstance = null;

function initLeafletMap() {
    const mapContainer = document.getElementById('profileMap');
    if (!mapContainer || leafletMapInstance) return; // already initialized

    const caviteBounds = L.latLngBounds([13.983, 120.850], [14.600, 121.200]);
    const caviteCenter = [14.2823, 120.8687];

    let defaultLat = parseFloat(document.getElementById('latitude').value);
    let defaultLng = parseFloat(document.getElementById('longitude').value);

    if (isNaN(defaultLat) || isNaN(defaultLng) || !caviteBounds.contains([defaultLat, defaultLng])) {
        defaultLat = caviteCenter[0];
        defaultLng = caviteCenter[1];
    }

    const map = L.map('profileMap', {
        maxBounds: caviteBounds,
        maxBoundsViscosity: 1.0,
    }).setView([defaultLat, defaultLng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);
    document.getElementById('latitude').value  = defaultLat;
    document.getElementById('longitude').value = defaultLng;

    function updatePin(latlng) {
        if (!caviteBounds.contains(latlng)) {
            showCaviteToast('You can only pin locations within Cavite.');
            return false;
        }
        marker.setLatLng(latlng);
        document.getElementById('latitude').value  = latlng.lat.toFixed(7);
        document.getElementById('longitude').value = latlng.lng.toFixed(7);
        // Reverse geocode to fill address field
        fetch(`https://nominatim.openstreetmap.org/reverse?lat=${latlng.lat}&lon=${latlng.lng}&format=json`)
            .then(r => r.json())
            .then(data => {
                const addr = document.getElementById('address');
                if (addr && data.display_name) addr.value = data.display_name;
            })
            .catch(() => {});
        return true;
    }

    map.on('click', (e) => updatePin(e.latlng));
    marker.on('dragend', () => updatePin(marker.getLatLng()));

    setTimeout(() => map.invalidateSize(), 200);
    leafletMapInstance = map;
}

function showCaviteToast(message) {
    const toast = document.getElementById('caviteToast');
    const msg   = document.getElementById('caviteToastMsg');
    if (!toast) return;
    if (msg) msg.textContent = message;
    toast.classList.remove('hidden');
    setTimeout(() => toast.classList.add('hidden'), 4000);
}

// ── Init hours card state on page load
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[name*="[is_closed]"][type="checkbox"]').forEach(cb => {
        const match = cb.closest('[id^="hours_card_"]');
        if (!match) return;
        const suffix = match.id.replace('hours_card_', '');
        toggleHoursCard(cb, suffix);
        cb.addEventListener('change', () => toggleHoursCard(cb, suffix));
    });
});
</script>

<style>
    #profileMap { z-index: 1 !important; }
    .leaflet-container { z-index: 1 !important; }
    .leaflet-pane, .leaflet-top, .leaflet-bottom, .leaflet-control { z-index: 1 !important; }
</style>
@endsection