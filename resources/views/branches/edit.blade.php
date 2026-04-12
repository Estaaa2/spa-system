@extends('layouts.app')

@section('title', 'Edit Branch — ' . $branch->name)

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

@php
    // Determine active tab: query param > default 'general'
    $activeTab = request()->query('tab', 'general');
    if (!in_array($activeTab, ['general', 'hours', 'profile'])) $activeTab = 'general';

    // Helper: slice time to H:i regardless of whether DB stores H:i:s
    $t = fn($time, $default = '09:00') => $time ? substr($time, 0, 5) : $default;
@endphp

<div class="max-w-3xl py-3 mx-auto" x-data="branchEditPage()" x-init="init()">

    {{-- ── Page Header ─────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('branches.index') }}"
           class="flex items-center justify-center w-9 h-9 text-gray-500 transition border border-gray-200 rounded-xl hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700">
            <i class="text-sm fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $branch->name }}</h1>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                <i class="mr-1 fa-solid fa-location-dot text-[#8B7355] text-xs"></i>{{ $branch->location }}
            </p>
        </div>
    </div>

    {{-- ── Tab Navigation ───────────────────────────────────────────────── --}}
    <div class="flex border-b border-gray-200 dark:border-gray-700 mb-6">
        <button @click="tab = 'general'"
            :class="tab === 'general' ? 'border-[#8B7355] text-[#6F5430] dark:text-[#C4A97D]' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
            class="flex items-center gap-2 px-5 py-3 text-sm font-medium border-b-2 transition-colors -mb-px">
            <i class="fa-solid fa-pen text-xs"></i>
            General
            @if($errors->hasBag('general') && $errors->getBag('general')->any())
                <span class="flex items-center justify-center w-4 h-4 text-[10px] font-bold text-white bg-red-500 rounded-full">!</span>
            @endif
        </button>

        <button @click="tab = 'hours'"
            :class="tab === 'hours' ? 'border-[#8B7355] text-[#6F5430] dark:text-[#C4A97D]' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
            class="flex items-center gap-2 px-5 py-3 text-sm font-medium border-b-2 transition-colors -mb-px">
            <i class="fa-solid fa-clock text-xs"></i>
            Operating Hours
            @if($errors->hasBag('hours') && $errors->getBag('hours')->any())
                <span class="flex items-center justify-center w-4 h-4 text-[10px] font-bold text-white bg-red-500 rounded-full">!</span>
            @endif
        </button>

        <button @click="tab = 'profile'"
            :class="tab === 'profile' ? 'border-[#8B7355] text-[#6F5430] dark:text-[#C4A97D]' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
            class="flex items-center gap-2 px-5 py-3 text-sm font-medium border-b-2 transition-colors -mb-px">
            <i class="fa-solid fa-image text-xs"></i>
            Public Profile
            @if(optional($branch->profile)->is_listed)
                <span class="px-1.5 py-0.5 text-[10px] font-semibold rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">Live</span>
            @endif
            @if($errors->hasBag('profile') && $errors->getBag('profile')->any())
                <span class="flex items-center justify-center w-4 h-4 text-[10px] font-bold text-white bg-red-500 rounded-full">!</span>
            @endif
        </button>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════
         TAB 1 — GENERAL INFO
    ═════════════════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'general'"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0">

        <form method="POST" action="{{ route('branches.update.general', $branch->id) }}">
            @csrf
            @method('PUT')

            <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 space-y-5">

                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Branch Information</h2>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Update the display name and main branch status.</p>
                </div>

                {{-- Errors for this tab only --}}
                @if($errors->hasBag('general') && $errors->getBag('general')->any())
                    <div class="p-3 text-sm text-red-600 bg-red-50 rounded-xl ring-1 ring-red-200 dark:bg-red-900/10 dark:ring-red-800 dark:text-red-400">
                        <ul class="space-y-0.5 list-disc list-inside">
                            @foreach($errors->getBag('general')->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Success flash --}}
                @if(session('tab_success') === 'general')
                    <div class="flex items-center gap-2 p-3 text-sm text-green-700 bg-green-50 rounded-xl ring-1 ring-green-200 dark:bg-green-900/10 dark:ring-green-800 dark:text-green-300">
                        <i class="fa-solid fa-circle-check flex-shrink-0"></i>
                        Branch information updated successfully.
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
                           class="block w-full mt-2 border-gray-300 rounded-xl shadow-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm
                           @error('name', 'general') border-red-400 @enderror">
                </div>

                {{-- Location (read-only) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Location</label>
                    <div class="flex items-center gap-2 mt-2 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl dark:bg-gray-700 dark:border-gray-600">
                        <i class="fa-solid fa-location-dot text-[#8B7355] text-sm flex-shrink-0"></i>
                        <p class="flex-1 text-sm text-gray-600 dark:text-gray-300">{{ $branch->location }}</p>
                        <span class="text-[10px] text-gray-400 dark:text-gray-500">Cannot be changed</span>
                    </div>
                </div>

                {{-- Main branch --}}
                <div class="flex items-start gap-3 p-4 rounded-xl bg-gray-50 dark:bg-gray-700/50">
                    <input type="hidden" name="is_main" value="0">
                    <input type="checkbox" id="is_main" name="is_main" value="1"
                           {{ $branch->is_main ? 'checked' : '' }}
                           class="mt-0.5 w-4 h-4 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355] dark:bg-gray-600 dark:border-gray-500">
                    <div>
                        <label for="is_main" class="text-sm font-medium text-gray-700 dark:text-gray-300">Set as main branch</label>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            The main branch is the spa's primary location. Only one branch can be main at a time.
                        </p>
                    </div>
                </div>

            </div>

            <div class="flex items-center justify-between mt-4">
                <a href="{{ route('branches.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 transition">
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
    <div x-show="tab === 'hours'"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0">

        <form method="POST" action="{{ route('branches.update.hours', $branch->id) }}" id="hoursForm">
            @csrf
            @method('PUT')

            <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 space-y-5">

                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Operating Hours</h2>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        Set opening and closing times per day. These are enforced during booking validation.
                        At least one day must be open.
                    </p>
                </div>

                {{-- Errors for this tab only --}}
                @if($errors->hasBag('hours') && $errors->getBag('hours')->any())
                    <div class="p-3 text-sm text-red-600 bg-red-50 rounded-xl ring-1 ring-red-200 dark:bg-red-900/10 dark:ring-red-800 dark:text-red-400">
                        <ul class="space-y-0.5 list-disc list-inside">
                            @foreach($errors->getBag('hours')->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(session('tab_success') === 'hours')
                    <div class="flex items-center gap-2 p-3 text-sm text-green-700 bg-green-50 rounded-xl ring-1 ring-green-200 dark:bg-green-900/10 dark:ring-green-800 dark:text-green-300">
                        <i class="fa-solid fa-circle-check flex-shrink-0"></i>
                        Operating hours updated successfully.
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach($operatingHours as $hour)
                    @php $suffix = $hour->id ?? $loop->index; @endphp

                    <div class="p-4 bg-white shadow-sm dark:bg-gray-800 rounded-2xl ring-1 ring-black/5 dark:ring-white/10"
                         id="hours_card_{{ $suffix }}">

                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $hour->day_of_week }}</h4>
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
                                <label class="block mb-1 text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Opens</label>
                                {{-- $t() slices HH:MM:SS → HH:MM so the date_format:H:i validation always passes --}}
                                <input type="time"
                                    id="opening_{{ $suffix }}"
                                    name="hours[{{ $loop->index }}][opening_time]"
                                    value="{{ $t($hour->opening_time, '09:00') }}"
                                    {{ isset($hour->is_closed) && $hour->is_closed ? 'disabled' : '' }}
                                    class="w-full px-3 py-2 text-sm text-gray-900 bg-white border border-gray-200 rounded-xl dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    onchange="validateTimeRange('opening_{{ $suffix }}', 'closing_{{ $suffix }}', 'time_error_{{ $suffix }}', 'hours_card_{{ $suffix }}')">
                                <input type="hidden" name="hours[{{ $loop->index }}][day_of_week]" value="{{ $hour->day_of_week }}">
                            </div>
                            <div>
                                <label class="block mb-1 text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Closes</label>
                                <input type="time"
                                    id="closing_{{ $suffix }}"
                                    name="hours[{{ $loop->index }}][closing_time]"
                                    value="{{ $t($hour->closing_time, '18:00') }}"
                                    {{ isset($hour->is_closed) && $hour->is_closed ? 'disabled' : '' }}
                                    class="w-full px-3 py-2 text-sm text-gray-900 bg-white border border-gray-200 rounded-xl dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    onchange="validateTimeRange('opening_{{ $suffix }}', 'closing_{{ $suffix }}', 'time_error_{{ $suffix }}', 'hours_card_{{ $suffix }}')">
                            </div>
                        </div>

                        {{-- Inline time-range error (mirrors operating-hours.blade.php) --}}
                        <p id="time_error_{{ $suffix }}"
                           class="hidden flex items-center gap-1 mt-2 text-xs text-red-600">
                            <i class="fa-solid fa-circle-exclamation flex-shrink-0"></i>
                            Closing time must be after opening time.
                        </p>

                        @if(isset($hour->is_closed) && $hour->is_closed)
                            <p class="mt-2 text-xs italic text-center text-gray-400" id="closed_label_{{ $suffix }}">Closed all day</p>
                        @else
                            <p class="hidden mt-2 text-xs italic text-center text-gray-400" id="closed_label_{{ $suffix }}">Closed all day</p>
                        @endif

                        <input type="hidden" name="hours[{{ $loop->index }}][id]" value="{{ $hour->id ?? '' }}">
                    </div>
                    @endforeach
                </div>

                {{-- "All closed" warning — mirrors operating-hours.blade.php --}}
                <div id="allClosedWarning" class="hidden flex items-center gap-3 p-3 border border-amber-200 bg-amber-50 rounded-xl dark:bg-amber-900/10 dark:border-amber-800">
                    <i class="fa-solid fa-triangle-exclamation text-amber-600 flex-shrink-0"></i>
                    <p class="text-sm text-amber-800 dark:text-amber-300">
                        <span class="font-semibold">Note:</span> At least one day must be open for online bookings to work.
                    </p>
                </div>

            </div>

            <div class="flex items-center justify-between mt-4">
                <a href="{{ route('branches.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 transition">
                    ← Back to Branches
                </a>
                <button type="submit" id="hoursSubmitBtn"
                    class="px-6 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-xl hover:opacity-90 transition shadow-sm">
                    Save Operating Hours
                </button>
            </div>

        </form>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════
         TAB 3 — PUBLIC PROFILE
    ═════════════════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'profile'"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         @tab-profile-shown.window="initProfileMap()">

        @if($spa->verification_status !== 'verified')

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

            <form method="POST"
                  action="{{ route('branches.update.profile', $branch->id) }}"
                  enctype="multipart/form-data">
                @csrf
                @method('PUT')

                {{-- Profile tab errors only --}}
                @if($errors->hasBag('profile') && $errors->getBag('profile')->any())
                    <div class="p-4 mb-5 text-sm text-red-600 bg-red-50 rounded-2xl ring-1 ring-red-200 dark:bg-red-900/10 dark:ring-red-800 dark:text-red-400">
                        <p class="font-semibold mb-1">Please fix the following:</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($errors->getBag('profile')->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(session('tab_success') === 'profile')
                    <div class="flex items-center gap-2 p-3 mb-5 text-sm text-green-700 bg-green-50 rounded-2xl ring-1 ring-green-200 dark:bg-green-900/10 dark:ring-green-800 dark:text-green-300">
                        <i class="fa-solid fa-circle-check flex-shrink-0"></i>
                        Public profile updated successfully.
                    </div>
                @endif

                {{-- ── Listing Toggle ─────────────────────────────────────── --}}
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

                    <div x-show="listed" x-transition class="flex items-center gap-2 mt-3 px-3 py-2 bg-green-50 rounded-xl ring-1 ring-green-200 dark:bg-green-900/10 dark:ring-green-800">
                        <i class="fa-solid fa-circle-check text-green-500 text-xs"></i>
                        <p class="text-xs font-medium text-green-700 dark:text-green-300">This branch is visible on the public landing page.</p>
                    </div>
                </div>

                {{-- ── Branch Details ─────────────────────────────────────── --}}
                <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 mb-4 space-y-4">

                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Branch Details</h2>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Info shown to customers on the listing page.</p>
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

                {{-- ── Map Pin ─────────────────────────────────────────────── --}}
                <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 mb-4">
                    <div class="mb-3">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Branch Location Pin</h2>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Click the map or drag the marker to set the exact location. Cavite area only.</p>
                    </div>
                    <input type="hidden" name="latitude"  id="latitude"  value="{{ old('latitude',  optional($branch->profile)->latitude) }}">
                    <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude', optional($branch->profile)->longitude) }}">
                    <div id="profileMap" class="w-full h-64 rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden"></div>
                    <div id="caviteToast" class="hidden mt-3 flex items-center gap-2 p-3 text-sm text-red-600 bg-red-50 rounded-xl ring-1 ring-red-200 dark:bg-red-900/10 dark:ring-red-800 dark:text-red-400">
                        <i class="fa-solid fa-location-crosshairs flex-shrink-0"></i>
                        <span id="caviteToastMsg">Please pin a location within Cavite only.</span>
                    </div>
                </div>

                {{-- ── Cover Image ─────────────────────────────────────────── --}}
                @php
                    $existingCover   = optional($branch->profile)->cover_image;
                    $existingGallery = optional($branch->profile)->gallery_images ?? [];
                @endphp

                <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 mb-4">
                    <div class="mb-4">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Cover Image</h2>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">The main photo shown on the listing card.</p>
                    </div>
                    <input type="hidden" name="remove_cover_image" id="remove_cover_image" value="0">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                        <div class="w-full sm:w-52 flex-shrink-0">
                            <div class="relative flex items-center justify-center overflow-hidden bg-gray-100 border border-dashed border-gray-300 rounded-2xl aspect-[4/3] dark:bg-gray-700 dark:border-gray-600">
                                <img id="coverPreview" src="{{ $existingCover ? asset('storage/' . $existingCover) : '' }}"
                                     class="{{ $existingCover ? '' : 'hidden' }} object-cover w-full h-full">
                                <div id="coverPlaceholder" class="{{ $existingCover ? 'hidden' : '' }} flex flex-col items-center gap-1">
                                    <i class="text-2xl text-gray-300 fa-solid fa-image"></i>
                                    <p class="text-xs text-gray-400">No image</p>
                                </div>
                                <button type="button" id="removeCoverBtn" onclick="removeCoverImage()"
                                    class="{{ $existingCover ? '' : 'hidden' }} absolute top-2 right-2 flex items-center justify-center w-7 h-7 text-white bg-red-500 rounded-full hover:bg-red-600">
                                    <i class="text-xs fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div>
                        <div class="flex-1">
                            <label for="cover_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Upload New Cover</label>
                            <input type="file" name="cover_image" id="cover_image" accept="image/*" onchange="previewCoverImage(event)"
                                   class="block w-full mt-2 text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 dark:file:bg-gray-700 dark:file:text-white">
                            <p class="mt-2 text-xs text-gray-400">JPG, PNG, WebP. Max 2MB. Landscape ratio recommended.</p>
                        </div>
                    </div>
                </div>

                {{-- ── Gallery ─────────────────────────────────────────────── --}}
                <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 mb-4">
                    <div class="mb-4">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Gallery Images</h2>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Up to 4 additional photos shown in the branch detail view.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @for($i = 0; $i < 4; $i++)
                        @php $existingImage = $existingGallery[$i] ?? null; @endphp
                        <div class="space-y-2">
                            <input type="hidden" name="existing_gallery_images[{{ $i }}]" value="{{ $existingImage }}">
                            <input type="hidden" name="remove_gallery_images[{{ $i }}]" id="remove_gallery_image_{{ $i }}" value="0">
                            <div class="relative overflow-hidden bg-gray-100 border border-dashed border-gray-300 rounded-2xl aspect-square dark:bg-gray-700 dark:border-gray-600">
                                <img id="galleryPreview_{{ $i }}" src="{{ $existingImage ? asset('storage/' . $existingImage) : '' }}"
                                     class="{{ $existingImage ? '' : 'hidden' }} object-cover w-full h-full">
                                <div id="galleryPlaceholder_{{ $i }}"
                                     class="{{ $existingImage ? 'hidden' : '' }} absolute inset-0 flex flex-col items-center justify-center">
                                    <i class="text-xl text-gray-300 fa-solid fa-image"></i>
                                    <p class="mt-1 text-[10px] text-gray-400">Empty</p>
                                </div>
                                <button type="button" id="removeGalleryBtn_{{ $i }}" onclick="removeGalleryImage({{ $i }})"
                                    class="{{ $existingImage ? '' : 'hidden' }} absolute top-1.5 right-1.5 flex items-center justify-center w-6 h-6 text-white bg-red-500 rounded-full hover:bg-red-600">
                                    <i class="text-[10px] fa-solid fa-xmark"></i>
                                </button>
                            </div>
                            <label for="gallery_image_{{ $i }}"
                                class="flex items-center justify-center w-full py-1.5 text-xs font-medium text-gray-600 transition bg-gray-50 border border-gray-200 cursor-pointer rounded-xl hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">
                                <i class="mr-1 fa-solid fa-plus text-[10px]"></i>
                                {{ $existingImage ? 'Replace' : 'Upload' }}
                            </label>
                            <input type="file" id="gallery_image_{{ $i }}" name="gallery_images[{{ $i }}]"
                                   accept="image/*" class="hidden" onchange="previewGalleryImage(event, {{ $i }})">
                        </div>
                        @endfor
                    </div>
                </div>

                {{-- ── Amenities ───────────────────────────────────────────── --}}
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
                            <label class="flex items-center gap-3 p-3 transition border border-gray-200 cursor-pointer dark:bg-gray-700 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-600">
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

                    {{-- Custom amenity modal --}}
                    <div x-show="openModal" x-transition.opacity
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                         @click.self="openModal = false">
                        <div class="w-full max-w-sm p-6 bg-white rounded-2xl shadow-xl dark:bg-gray-800" @click.stop>
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Add Custom Amenity</h3>
                                <button type="button" @click="openModal = false" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                            <input type="text" x-model="newAmenityLabel"
                                   @keydown.enter.prevent="addCustomAmenity()"
                                   placeholder="e.g. Steam Room, Jacuzzi, Foot Bath..."
                                   class="block w-full text-sm border-gray-300 rounded-xl shadow-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <p x-show="errorMsg" x-text="errorMsg" class="mt-2 text-xs text-red-500"></p>
                            <div class="flex justify-end gap-2 mt-4">
                                <button type="button" @click="openModal = false" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg dark:bg-gray-700 dark:text-gray-300">Cancel</button>
                                <button type="button" @click="addCustomAmenity()" class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-lg hover:bg-[#7a6449]">Add</button>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="flex items-center justify-between mt-4">
                    <a href="{{ route('branches.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 transition">
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

</div>{{-- end x-data --}}

{{-- ════════════════════════════════════════════════════════════════════════
     SCRIPTS
═════════════════════════════════════════════════════════════════════════ --}}
<script>
// ── Alpine: tab state + URL sync + lazy map init
function branchEditPage() {
    return {
        tab: '{{ $activeTab }}',

        init() {
            this.$watch('tab', (value) => {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', value);
                window.history.replaceState({}, '', url.toString());
                if (value === 'profile') this.$nextTick(() => this.$dispatch('tab-profile-shown'));
            });
            if (this.tab === 'profile') this.$nextTick(() => this.$dispatch('tab-profile-shown'));
        },

        initProfileMap() { initLeafletMap(); }
    };
}

// ── Amenities manager (unchanged from original edit page)
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

// ────────────────────────────────────────────────────────────
// OPERATING HOURS — time range validation
// Ported from operating-hours.blade.php to keep behaviour consistent
// ────────────────────────────────────────────────────────────

function validateTimeRange(openingId, closingId, errorId, cardId) {
    const opening = document.getElementById(openingId);
    const closing = document.getElementById(closingId);
    const errorEl = document.getElementById(errorId);
    const card    = document.getElementById(cardId);

    if (!opening || !closing || !errorEl || !card) return true;
    if (!opening.value || !closing.value) {
        clearTimeError(opening, closing, errorEl, card);
        checkAllClosed();
        return true;
    }

    const isReversed = closing.value <= opening.value;

    if (isReversed) {
        errorEl.classList.remove('hidden');
        closing.classList.add('border-red-400', 'bg-red-50', 'focus:border-red-400');
        closing.classList.remove('border-gray-200');
        card.classList.add('ring-red-300');
        card.classList.remove('ring-black/5');
    } else {
        clearTimeError(opening, closing, errorEl, card);
    }

    checkAllClosed();
    return !isReversed;
}

function clearTimeError(opening, closing, errorEl, card) {
    errorEl.classList.add('hidden');
    closing.classList.remove('border-red-400', 'bg-red-50', 'focus:border-red-400');
    closing.classList.add('border-gray-200');
    card.classList.remove('ring-red-300');
    card.classList.add('ring-black/5');
}

function checkAllClosed() {
    const checkboxes = document.querySelectorAll('#hoursForm input[type="checkbox"][name*="is_closed"]');
    const saveBtn    = document.getElementById('hoursSubmitBtn');
    const warning    = document.getElementById('allClosedWarning');
    if (!saveBtn) return;

    const allClosed = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);

    saveBtn.disabled = allClosed;
    saveBtn.classList.toggle('opacity-50', allClosed);
    saveBtn.classList.toggle('cursor-not-allowed', allClosed);
    warning?.classList.toggle('hidden', !allClosed);
}

function toggleHoursCard(checkbox, suffix) {
    const timesDiv    = document.getElementById(`hours_times_${suffix}`);
    const closedLabel = document.getElementById(`closed_label_${suffix}`);
    const inputs      = timesDiv?.querySelectorAll('input[type="time"]');
    const isClosed    = checkbox.checked;

    if (timesDiv) {
        timesDiv.style.opacity      = isClosed ? '0.4' : '1';
        timesDiv.style.pointerEvents = isClosed ? 'none' : '';
    }

    inputs?.forEach(input => { input.disabled = isClosed; });
    closedLabel?.classList.toggle('hidden', !isClosed);

    // Clear any time error on the card when toggling closed
    if (isClosed) {
        const errorEl = document.getElementById(`time_error_${suffix}`);
        const card    = document.getElementById(`hours_card_${suffix}`);
        const opening = document.getElementById(`opening_${suffix}`);
        const closing = document.getElementById(`closing_${suffix}`);
        if (errorEl && card && opening && closing) clearTimeError(opening, closing, errorEl, card);
    }

    checkAllClosed();
}

// Block form submission if any time-range errors exist
document.getElementById('hoursForm')?.addEventListener('submit', function (e) {
    const errors = document.querySelectorAll('[id^="time_error_"]:not(.hidden)');
    if (errors.length > 0) {
        e.preventDefault();
        errors[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});

// ── Cover image
function previewCoverImage(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
        const preview = document.getElementById('coverPreview');
        preview.src = e.target.result;
        preview.classList.remove('hidden');
        document.getElementById('coverPlaceholder')?.classList.add('hidden');
        document.getElementById('removeCoverBtn')?.classList.remove('hidden');
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
        const preview = document.getElementById(`galleryPreview_${index}`);
        preview.src = e.target.result;
        preview.classList.remove('hidden');
        document.getElementById(`galleryPlaceholder_${index}`)?.classList.add('hidden');
        document.getElementById(`removeGalleryBtn_${index}`)?.classList.remove('hidden');
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

// ── Leaflet map (lazy, only on profile tab open)
let leafletMapInstance = null;

function initLeafletMap() {
    const mapContainer = document.getElementById('profileMap');
    if (!mapContainer || leafletMapInstance) return;

    const caviteBounds = L.latLngBounds([13.983, 120.850], [14.600, 121.200]);
    const caviteCenter = [14.2823, 120.8687];

    let defaultLat = parseFloat(document.getElementById('latitude').value);
    let defaultLng = parseFloat(document.getElementById('longitude').value);

    if (isNaN(defaultLat) || isNaN(defaultLng) || !caviteBounds.contains([defaultLat, defaultLng])) {
        defaultLat = caviteCenter[0];
        defaultLng = caviteCenter[1];
    }

    const map    = L.map('profileMap', { maxBounds: caviteBounds, maxBoundsViscosity: 1.0 }).setView([defaultLat, defaultLng], 13);
    const marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    document.getElementById('latitude').value  = defaultLat;
    document.getElementById('longitude').value = defaultLng;

    function updatePin(latlng) {
        if (!caviteBounds.contains(latlng)) { showCaviteToast('You can only pin locations within Cavite.'); return; }
        marker.setLatLng(latlng);
        document.getElementById('latitude').value  = latlng.lat.toFixed(7);
        document.getElementById('longitude').value = latlng.lng.toFixed(7);
        fetch(`https://nominatim.openstreetmap.org/reverse?lat=${latlng.lat}&lon=${latlng.lng}&format=json`)
            .then(r => r.json())
            .then(data => { const a = document.getElementById('address'); if (a && data.display_name) a.value = data.display_name; })
            .catch(() => {});
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

// ── On page load: restore operating hours card states
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#hoursForm input[type="checkbox"][name*="is_closed"]').forEach(cb => {
        const card = cb.closest('[id^="hours_card_"]');
        if (!card) return;
        const suffix = card.id.replace('hours_card_', '');
        toggleHoursCard(cb, suffix);
        cb.addEventListener('change', () => toggleHoursCard(cb, suffix));
    });
    checkAllClosed();
});
</script>

<style>
    #profileMap { z-index: 1 !important; }
    .leaflet-container { z-index: 1 !important; }
    .leaflet-pane, .leaflet-top, .leaflet-bottom, .leaflet-control { z-index: 1 !important; }
</style>
@endsection