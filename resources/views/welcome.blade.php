<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Levictas</title>

    @vite(['resources/css/app.css','resources/css/landing.css', 'resources/js/app.js', 'resources/js/welcome.js'])

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="images/1.png" />

</head>

@php
    function getImageUrl($path) {
        if (empty($path)) {
            return asset('storage/branch_profiles/emptyspa.jpg');
        }

        // If already has branch_profiles in path
        if (str_contains($path, 'branch_profiles/')) {
            return asset('storage/' . $path);
        }

        // Add branch_profiles to the path
        return asset('storage/branch_profiles/' . $path);
    }
@endphp

{{-- data-fallback-image lets welcome.js read the asset URL without needing inline Blade --}}
<body class="bg-[#F6EFE6] dark:bg-gray-900 text-gray-800 dark:text-gray-100 selection:bg-[#D2A85B]/30 selection:text-[#3C2F23]"
      data-fallback-image="{{ asset('storage/branch_profiles/emptyspa.jpg') }}">

<nav class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 nav-glass" id="topNav">
    <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <div class="flex items-center">
                <a href="{{ url('/') }}" class="flex items-center space-x-3 group">
                    <img src="{{ asset('images/1.png') }}" alt="Levictas" class="w-auto h-10 rounded-md ring-1 ring-black/5">
                    <div class="flex flex-col leading-tight">
                        <span class="text-2xl font-semibold text-[#2D3748] dark:text-white font-['Playfair_Display'] tracking-wide group-hover:text-[#6F5430] dark:group-hover:text-[#C4A97D] transition">
                            Levictas
                        </span>
                        <span class="text-[10px] tracking-[0.18em] text-gray-500 dark:text-gray-400 uppercase">
                            Spa & Wellness Sanctuary
                        </span>
                    </div>
                </a>
            </div>

            <div class="items-center hidden space-x-2 md:flex">
                <a href="{{ url('/') }}"
                   class="relative px-4 py-2 text-sm font-medium rounded-full transition
                   {{ request()->is('/') ? 'text-[#6F5430] dark:text-[#C4A97D] bg-white/60 dark:bg-gray-800/60 ring-1 ring-black/5 dark:ring-white/10' : 'text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#C4A97D] hover:bg-white/50 dark:hover:bg-gray-800/50' }}">
                    Home
                </a>

                @guest
                    <a href="{{ route('login') }}"
                    class="relative px-4 py-2 text-sm font-medium rounded-full transition
                    {{ request()->is('login') ? 'text-[#6F5430] dark:text-[#C4A97D] bg-white/60 dark:bg-gray-800/60 ring-1 ring-black/5 dark:ring-white/10' : 'text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#C4A97D] hover:bg-white/50 dark:hover:bg-gray-800/50' }}">
                        Login
                    </a>

                    <a href="{{ route('register') }}"
                    class="relative px-4 py-2 text-sm font-medium rounded-full transition
                    {{ request()->is('register') ? 'text-[#6F5430] dark:text-[#C4A97D] bg-white/60 dark:bg-gray-800/60 ring-1 ring-black/5 dark:ring-white/10' : 'text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#C4A97D] hover:bg-white/50 dark:hover:bg-gray-800/50' }}">
                        Register
                    </a>

                    <a href="{{ route('register.business') }}"
                    class="booking-btn ml-3 px-6 py-2.5 text-sm font-semibold text-white rounded-full transition-all duration-300 shadow-lg hover:shadow-xl active:translate-y-0.5">
                        Join as a Partner
                    </a>

                @else
                    @role('customer')
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-1">
                            <a href="#" onclick="openAppointmentsModal()"
                                class="relative flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#C4A97D]">
                                My Appointments
                                <span id="myAppointmentsBadge"
                                    class="hidden absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] items-center justify-center px-1 text-[10px] font-bold text-white bg-red-500 rounded-full ring-2 ring-[#F6EFE6] dark:ring-gray-900">
                                    0
                                </span>
                            </a>
                            <a href="#" onclick="openScheduleModal()"
                                class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#C4A97D]">
                                My Schedule
                            </a>
                        </div>

                        <div class="relative" id="profileDropdownWrapper">
                            <button type="button" id="profileDropdownBtn"
                                class="flex items-center gap-2 px-3 py-2 transition rounded-full hover:bg-white/60 dark:hover:bg-gray-800/60 ring-1 ring-black/5 dark:ring-white/10">
                                <div class="flex items-center justify-center w-8 h-8 bg-[#8B7355] text-white rounded-full text-xs font-semibold leading-none shrink-0">
                                    {{ strtoupper(substr(auth()->user()?->name ?? 'Guest', 0, 1)) }}
                                </div>
                                <i class="fa-solid fa-chevron-down text-[10px] text-gray-400 transition-transform duration-200" id="profileChevron"></i>
                            </button>

                            <div id="profileDropdownMenu"
                                class="absolute right-0 z-50 hidden w-48 mt-2 overflow-hidden bg-white shadow-xl dark:bg-gray-800 rounded-2xl ring-1 ring-black/10 dark:ring-white/10">
                                <div class="px-4 py-3 border-b border-black/5 dark:border-white/10 bg-[#F6EFE6]/60 dark:bg-gray-900/40">
                                    <p class="text-xs font-semibold text-[#3C2F23] dark:text-white truncate">{{ auth()->user()?->name ?? 'Guest' }}</p>
                                    <p class="text-[11px] text-gray-400 truncate">{{ auth()->user()?->email ?? '' }}</p>
                                </div>
                                <div class="py-1">
                                    <button type="button"
                                        onclick="closeProfileDropdown(); openProfileModal();"
                                        class="flex items-center gap-3 w-full px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-[#F6EFE6] dark:hover:bg-gray-700 transition">
                                        <i class="fa-solid fa-user text-[#8B7355] w-4"></i>
                                        Profile
                                    </button>
                                    <button type="button" onclick="closeProfileDropdown(); openLogoutModal();"
                                        class="flex items-center gap-3 w-full px-4 py-2.5 text-sm text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                                        <i class="w-4 fa-solid fa-right-from-bracket"></i>
                                        Logout
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endrole
                @endguest
            </div>

            <div class="md:hidden">
                <button type="button" id="mobile-menu-button"
                        class="relative p-2 text-gray-700 transition-colors duration-200 dark:text-gray-300 rounded-xl hover:bg-white/60 dark:hover:bg-gray-800/60 ring-1 ring-black/5 dark:ring-white/10">
                    <i class="text-xl fas fa-bars"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    @auth
    <div id="profileModal" class="fixed inset-0 z-[130] hidden">
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeProfileModal()"></div>
        <div class="relative mx-auto w-[92%] max-w-xl mt-6 sm:mt-12 pb-6">
            <div class="flex flex-col overflow-hidden bg-white dark:bg-gray-800 shadow-2xl rounded-3xl ring-1 ring-black/10 dark:ring-white/10 max-h-[88vh]">

                {{-- HEADER: never scrolls. Gradient is already dark-toned brown, left as-is. --}}
                <div class="relative px-6 py-6 bg-gradient-to-br from-[#6F5430] to-[#8B7355] text-white text-center flex-shrink-0">
                    <div class="flex items-center justify-center mx-auto text-xl font-bold rounded-full w-14 h-14 bg-white/20 ring-2 ring-white/30">
                        {{ strtoupper(substr(auth()->user()?->name ?? 'Guest', 0, 1)) }}
                    </div>
                    <h3 class="mt-2 text-base font-semibold font-['Playfair_Display']">
                        {{ auth()->user()?->name ?? 'Guest' }}
                    </h3>
                    <p class="mt-0.5 text-xs tracking-wide uppercase text-white/70">Customer Account</p>
                    <button onclick="closeProfileModal()"
                        class="absolute flex items-center justify-center w-8 h-8 transition top-3 right-3 rounded-xl bg-white/10 hover:bg-white/20">
                        <i class="text-sm fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('customer.profile.update') }}" class="flex flex-col flex-1 min-h-0">
                    @csrf
                    @method('PATCH')

                    {{-- SCROLLABLE BODY --}}
                    <div class="flex-1 p-5 space-y-6 overflow-y-auto">

                        {{-- Errors use a named bag ('customerProfile') set in ProfileController so a
                             failed job-application form on this same page can't also pop this modal. --}}
                        @if($errors->hasBag('customerProfile') && $errors->getBag('customerProfile')->any())
                            <div class="p-3 text-sm text-red-600 bg-red-50 rounded-xl ring-1 ring-red-200 dark:bg-red-900/10 dark:ring-red-800 dark:text-red-400">
                                <p class="mb-1 font-semibold">Please fix the following:</p>
                                <ul class="pl-4 list-disc space-y-0.5">
                                    @foreach($errors->getBag('customerProfile')->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- PERSONAL INFORMATION --}}
                        <div>
                            <h4 class="flex items-center gap-2 mb-3 text-xs font-bold tracking-widest text-[#8B7355] dark:text-[#C4A97D] uppercase">
                                <i class="fa-solid fa-user"></i> Personal Information
                            </h4>
                            <div class="grid grid-cols-3 gap-3">
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">First</label>
                                    <input type="text" name="first_name"
                                        value="{{ old('first_name', auth()->user()->first_name) }}"
                                        class="w-full rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40 text-sm">
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Middle</label>
                                    <input type="text" name="middle_name"
                                        value="{{ old('middle_name', auth()->user()->middle_name) }}"
                                        class="w-full rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40 text-sm">
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Last</label>
                                    <input type="text" name="last_name"
                                        value="{{ old('last_name', auth()->user()->last_name) }}"
                                        class="w-full rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40 text-sm">
                                </div>
                            </div>
                        </div>

                        <hr class="border-[#E8DDD0] dark:border-gray-700">

                        {{-- CONTACT & LOCATION --}}
                        <div>
                            <h4 class="flex items-center gap-2 mb-3 text-xs font-bold tracking-widest text-[#8B7355] dark:text-[#C4A97D] uppercase">
                                <i class="fa-solid fa-location-dot"></i> Contact & Location
                            </h4>

                            <div class="space-y-4">
                                {{-- Email --}}
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Email Address</label>
                                    @php
                                        $email = auth()->user()?->email ?? '';
                                        $parts = explode('@', $email);
                                        $name = $parts[0]; $domain = $parts[1] ?? '';
                                        $maskedName = strlen($name) > 3
                                            ? substr($name, 0, 2) . str_repeat('*', strlen($name) - 2)
                                            : str_repeat('*', strlen($name));
                                        $maskedEmail = $maskedName . '@' . $domain;
                                    @endphp
                                    <div class="flex items-center gap-2 px-3 py-2 rounded-xl bg-gray-50 dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                                        <p id="emailDisplay" class="flex-1 text-sm text-[#3C2F23] dark:text-gray-100 truncate">{{ $maskedEmail }}</p>
                                        <button type="button" id="emailToggleBtn" onclick="toggleEmail(this)"
                                            data-masked="{{ $maskedEmail }}" data-real="{{ $email }}"
                                            class="text-[#8B7355] hover:text-[#6F5430] dark:hover:text-[#C4A97D] transition flex-shrink-0">
                                            <i id="emailToggleIcon" class="text-xs fa-solid fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                {{-- Phone --}}
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Phone Number</label>
                                    <input type="text" name="phone" id="phone"
                                        maxlength="11" pattern="^09\d{9}$"
                                        value="{{ old('phone', auth()->user()->phone) }}"
                                        placeholder="09xxxxxxxxx"
                                        class="w-full rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40 text-sm">
                                    <p class="text-[11px] text-gray-400">Used for booking updates, and to speed up GCash/Maya checkout.</p>
                                </div>

                                {{-- Address --}}
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Location / Address</label>
                                    <div class="relative">
                                        <input type="text" name="address" id="address" autocomplete="off"
                                            value="{{ old('address', auth()->user()->address) }}"
                                            placeholder="Search your barangay, subdivision, or city"
                                            class="w-full rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40 text-sm">
                                        {{-- Autocomplete suggestions dropdown, populated by welcome.js --}}
                                        <div id="addressSuggestions"
                                            class="absolute z-20 hidden w-full mt-1 overflow-hidden overflow-y-auto bg-white border border-gray-200 shadow-lg max-h-48 rounded-xl dark:bg-gray-700 dark:border-gray-600">
                                        </div>
                                    </div>
                                    <p class="text-[11px] text-gray-400">Search above, or fine-tune by dragging the pin below. Cavite locations only — used to recommend nearby spas.</p>

                                    {{-- Leaflet/OSM tiles can't be themed via CSS, so a dimming
                                         overlay tints the map to sit better against the dark
                                         modal. pointer-events-none keeps the map fully clickable. --}}
                                    <div class="relative">
                                        <div id="map" class="w-full h-52 rounded-xl border border-[#E8DDD0] dark:border-gray-600 overflow-hidden"></div>
                                        <div class="absolute inset-0 hidden pointer-events-none dark:block rounded-xl bg-gray-900/30 mix-blend-multiply"></div>
                                    </div>
                                    <div class="flex justify-end">
                                        <button type="button" id="profileLocationReset"
                                            class="text-[11px] font-semibold text-[#8B7355] dark:text-[#C4A97D] hover:text-[#6F5430] dark:hover:text-white">
                                            <i class="mr-1 fa-solid fa-rotate-left"></i>Reset to saved location
                                        </button>
                                    </div>

                                    <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude', auth()->user()->latitude) }}">
                                    <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude', auth()->user()->longitude) }}">
                                </div>
                            </div>
                        </div>

                        <hr class="border-[#E8DDD0] dark:border-gray-700">

                        {{-- Account status --}}
                        <div class="flex items-center gap-2">
                            @if(auth()->user()->hasVerifiedEmail())
                                <span class="text-xs font-semibold text-green-600">✔ Verified Account</span>
                            @else
                                <span class="text-xs font-semibold text-amber-500">⚠ Unverified Account</span>
                            @endif
                        </div>

                    </div>{{-- end scrollable body --}}

                    {{-- FOOTER: always visible --}}
                    <div class="flex flex-shrink-0 gap-2 px-5 py-4 bg-white border-t dark:bg-gray-800 border-black/5 dark:border-white/10">
                        <button type="submit"
                            class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-white booking-btn shadow-md hover:shadow-lg transition">
                            Save Changes
                        </button>
                        <button type="button" onclick="closeProfileModal()"
                            class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-[#8B7355] dark:text-[#C4A97D] border border-[#8B7355] dark:border-[#C4A97D] hover:bg-[#F6EFE6] dark:hover:bg-gray-700 transition">
                            Cancel
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
    @endauth

    <div id="mobile-menu" class="hidden bg-[#F6EFE6]/95 dark:bg-gray-900/95 border-t border-black/10 dark:border-white/10 shadow-lg md:hidden">
        <div class="px-3 pt-3 pb-5 space-y-2">
            <a href="{{ url('/') }}"
               class="block px-4 py-3 rounded-xl text-sm font-medium transition
               {{ request()->is('/') ? 'bg-white/70 dark:bg-gray-800/70 text-[#6F5430] dark:text-[#C4A97D] ring-1 ring-black/5 dark:ring-white/10' : 'text-gray-700 dark:text-gray-300 hover:bg-white/60 dark:hover:bg-gray-800/60' }}">
                Home
            </a>
            @guest
                <a href="{{ route('login') }}" class="block px-4 py-3 text-base font-medium text-gray-700 dark:text-gray-300 rounded-xl hover:bg-white/60 dark:hover:bg-gray-800/60">Login</a>
                <a href="{{ route('register') }}" class="block px-4 py-3 text-base font-medium text-gray-700 dark:text-gray-300 rounded-xl hover:bg-white/60 dark:hover:bg-gray-800/60">Register</a>
                <a href="{{ route('register.business') }}" class="block px-4 py-3 text-base font-medium text-gray-700 dark:text-gray-300 rounded-xl hover:bg-white/60 dark:hover:bg-gray-800/60">Join as a Partner</a>
            @else
                @role('customer')
                    <a href="#" onclick="openAppointmentsModal()" class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#C4A97D]">
                        My Appointments
                        <span id="myAppointmentsBadgeMobile"
                            class="hidden items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-white bg-red-500 rounded-full">
                            0
                        </span>
                    </a>
                    <a href="#" onclick="openScheduleModal()" class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#C4A97D]">My Schedule</a>
                    <a href="#" onclick="openProfileModal();" class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#C4A97D]">Profile</a>
                    <button type="button" onclick="openLogoutModal()" class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#C4A97D]">
                        Logout
                    </button>
                @endrole
            @endguest
        </div>
    </div>
</nav>

<main class="pt-20">
    <section class="relative overflow-hidden">
        <div class="absolute inset-0">
            <img src="{{ asset('images/heads.png') }}" class="object-cover w-full h-full" alt="Hero">
            <div class="absolute inset-0 bg-black/45"></div>
            <div class="absolute inset-0 bg-gradient-to-b from-black/30 via-black/35 to-[#F6EFE6] dark:to-gray-900"></div>
        </div>

        <div class="relative px-6 py-24 mx-auto text-center max-w-7xl">
            <div class="max-w-3xl mx-auto">
                <p class="inline-flex items-center gap-2 px-4 py-2 text-xs tracking-[0.2em] uppercase text-white/90 bg-white/10 rounded-full ring-1 ring-white/10">
                    <i class="fa-solid fa-spa text-white/80"></i>
                    Wellness • Relaxation • Care
                </p>
                <h1 class="mt-6 text-4xl md:text-6xl font-['Playfair_Display'] text-white font-semibold leading-tight">
                    Find and Book the Best Spa
                </h1>
                <p class="mt-4 text-lg md:text-xl text-white/90">
                    Relaxation, wellness, and pampering — made easy.
                </p>
            </div>

            <form action="{{ url('/') }}" method="GET" id="spaSearchForm"
                  class="flex items-stretch max-w-2xl gap-1 p-2 mx-auto mt-10 bg-white rounded-full shadow-2xl dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10">
                <div class="relative flex-1 px-5 py-2 text-left search-segment" id="placeSegment">
                    <label class="block text-[10px] font-bold uppercase tracking-wide text-[#8B7355] dark:text-[#C4A97D]">Place</label>
                    <input type="text" id="placeInput" name="place" value="{{ $place ?? '' }}"
                           placeholder="Anywhere in Cavite" autocomplete="off"
                           class="w-full text-sm text-gray-800 bg-transparent border-0 focus:ring-0 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500">
                    <div id="placeDropdown" class="search-dropdown">
                        <p class="search-dropdown-label">Cities &amp; municipalities</p>
                        <div id="placeChips" class="flex flex-wrap gap-2"></div>
                    </div>
                </div>

                <div class="w-px my-2 bg-black/10 dark:bg-white/10"></div>

                <div class="relative flex-1 px-5 py-2 text-left search-segment" id="treatmentSegment">
                    <label class="block text-[10px] font-bold uppercase tracking-wide text-[#8B7355] dark:text-[#C4A97D]">Treatment</label>
                    <input type="text" id="treatmentInput" name="treatment" value="{{ $treatment ?? '' }}"
                           placeholder="Any treatment" autocomplete="off"
                           class="w-full text-sm text-gray-800 bg-transparent border-0 focus:ring-0 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500">
                    <div id="treatmentDropdown" class="search-dropdown">
                        <p class="search-dropdown-label">Suggested treatments</p>
                        <div id="treatmentSuggestionList"></div>
                    </div>
                </div>

                <button type="submit" title="Search"
                        class="booking-btn flex-shrink-0 flex items-center justify-center w-11 h-11 self-center text-white rounded-full shadow-lg hover:opacity-95 transition active:translate-y-0.5">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </form>

            {{-- Treatment suggestions are real data (names actually offered today); embedded once
                 so the pill can filter them client-side with zero network calls per keystroke. --}}
            <script id="treatmentSuggestionsData" type="application/json">{!! json_encode($treatmentSuggestions ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>
        </div>

        <div class="h-10 bg-gradient-to-b from-transparent to-[#F6EFE6]"></div>
    </section>

    <!-- ================= MY APPOINTMENTS MODAL ================= -->
    <div id="appointmentsModal" class="fixed inset-0 z-[120] hidden">
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeAppointmentsModal()"></div>
        <div class="relative mx-auto w-[92%] max-w-2xl mt-10 sm:mt-16">
            <div class="overflow-hidden bg-white shadow-2xl dark:bg-gray-800 rounded-3xl ring-1 ring-black/10 dark:ring-white/10">
                <div class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                    <h3 class="text-lg font-semibold text-[#3C2F23] dark:text-white">My Appointments</h3>
                    <button onclick="closeAppointmentsModal()"
                        class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10">
                        <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="flex border-b border-black/5 dark:border-white/10">
                    @foreach(['upcoming' => 'Upcoming', 'past' => 'Past', 'cancelled' => 'Cancelled'] as $key => $label)
                    <button onclick="switchTab('{{ $key }}')"
                        id="tab-{{ $key }}"
                        class="flex-1 py-3 text-sm font-semibold transition border-b-2
                        {{ $key === 'upcoming' ? 'border-[#8B7355] dark:border-[#C4A97D] text-[#8B7355] dark:text-[#C4A97D]' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-[#8B7355] dark:hover:text-[#C4A97D]' }}">
                        {{ $label }}
                        <span id="tab-count-{{ $key }}"
                            class="ml-1 px-2 py-0.5 text-xs rounded-full bg-[#F6EFE6] dark:bg-gray-700 text-[#6F5430] dark:text-[#C4A97D]">0</span>
                    </button>
                    @endforeach
                </div>
                <div class="overflow-y-auto max-h-[60vh] p-6" id="appointmentsContent">
                    <div class="flex items-center justify-center py-12">
                        <i class="text-2xl text-gray-300 dark:text-gray-600 fa-solid fa-spinner fa-spin"></i>
                    </div>
                </div>
            </div>
            <div class="h-10"></div>
        </div>
    </div>

    <!-- ================= MY SCHEDULE MODAL ================= -->
    <div id="scheduleModal" class="fixed inset-0 z-[120] hidden">
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeScheduleModal()"></div>
        <div class="relative mx-auto w-[92%] max-w-2xl mt-10 sm:mt-16">
            <div class="overflow-hidden bg-white shadow-2xl dark:bg-gray-800 rounded-3xl ring-1 ring-black/10 dark:ring-white/10">
                <div class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                    <div class="flex items-center gap-3">
                        <button onclick="changeMonth(-1)"
                            class="flex items-center justify-center w-8 h-8 text-gray-700 transition rounded-lg hover:bg-black/5 dark:hover:bg-white/10 dark:text-gray-300">
                            <i class="text-sm fa-solid fa-chevron-left"></i>
                        </button>
                        <h3 id="calendarTitle" class="text-lg font-semibold text-[#3C2F23] dark:text-white">March 2026</h3>
                        <button onclick="changeMonth(1)"
                            class="flex items-center justify-center w-8 h-8 text-gray-700 transition rounded-lg hover:bg-black/5 dark:hover:bg-white/10 dark:text-gray-300">
                            <i class="text-sm fa-solid fa-chevron-right"></i>
                        </button>
                        <button type="button" onclick="toggleScheduleView()" id="scheduleViewToggleBtn"
                            title="Switch to calendar view"
                            class="flex items-center justify-center w-8 h-8 ml-1 text-[#8B7355] dark:text-[#C4A97D] transition rounded-lg hover:bg-[#F6EFE6] dark:hover:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                            <i class="text-sm fa-solid fa-calendar-days"></i>
                        </button>
                    </div>
                    <button onclick="closeScheduleModal()"
                        class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10">
                        <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto max-h-[65vh]">
                    {{-- LIST VIEW (default) --}}
                    <div id="scheduleListView">
                        <div id="scheduleListContent" class="space-y-3"></div>
                    </div>

                    {{-- CALENDAR VIEW (optional, toggled via the icon in the header) --}}
                    <div id="scheduleCalendarView" class="hidden">
                        <div class="grid grid-cols-7 mb-2">
                            @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
                            <div class="py-2 text-xs font-semibold text-center text-gray-400 dark:text-gray-500">{{ $day }}</div>
                            @endforeach
                        </div>
                        <div id="calendarGrid" class="grid grid-cols-7 gap-1"></div>
                        <div id="selectedDayBookings" class="hidden mt-6 space-y-3">
                            <h4 id="selectedDayTitle" class="text-sm font-semibold text-[#3C2F23] dark:text-white"></h4>
                            <div id="selectedDayContent"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="h-10"></div>
        </div>
    </div>

    <!-- ================= LOGOUT CONFIRMATION MODAL ================= -->
    <div id="logoutModal" class="fixed inset-0 z-[145] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeLogoutModal()"></div>
        <div class="relative w-[80%] max-w-sm">
            <div class="overflow-hidden bg-white shadow-2xl dark:bg-gray-800 rounded-3xl ring-1 ring-black/10 dark:ring-white/10">
                <div class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                    <h3 class="text-lg font-semibold text-[#3C2F23] dark:text-white">Confirm Logout</h3>
                    <button type="button" onclick="closeLogoutModal()"
                        class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10">
                        <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-red-100 rounded-full dark:bg-red-900/30">
                            <i class="text-xl text-red-600 dark:text-red-400 fa-solid fa-right-from-bracket"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-[#3C2F23] dark:text-white">Are you sure?</h4>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                You will be logged out of your account and redirected to the home page.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 px-6 pb-6">
                    <button type="button" onclick="closeLogoutModal()"
                        class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-[#8B7355] dark:text-[#C4A97D] border border-[#8B7355] dark:border-[#C4A97D] hover:bg-[#F6EFE6] dark:hover:bg-gray-700 transition">
                        Cancel
                    </button>
                    <form method="POST" action="{{ route('logout') }}" class="flex-1">
                        @csrf
                        <button type="submit"
                            class="w-full py-2.5 rounded-xl text-sm font-semibold text-white bg-red-600 hover:bg-red-700 transition">
                            Yes, Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= BOOKING DETAILS MODAL ================= -->
    <div id="bookingDetailsModal" class="fixed inset-0 z-[125] hidden">
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeBookingDetailsModal()"></div>
        <div class="relative mx-auto w-[92%] max-w-lg mt-10 sm:mt-16">
            <div class="overflow-hidden bg-white shadow-2xl dark:bg-gray-800 rounded-3xl ring-1 ring-black/10 dark:ring-white/10">
                <div class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                    <h3 class="text-lg font-semibold text-[#3C2F23] dark:text-white">Booking Details</h3>
                    <button type="button" onclick="closeBookingDetailsModal()"
                        class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10">
                        <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <!-- Spa Info -->
                    <div class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50">
                        <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 bg-white rounded-lg dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10">
                            <i class="fa-solid fa-spa text-[#8B7355] dark:text-[#C4A97D] text-sm"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Spa & Branch</p>
                            <p id="detailSpaName" class="text-sm font-semibold text-[#3C2F23] dark:text-white"></p>
                        </div>
                    </div>
                    <!-- Treatment -->
                    <div class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50">
                        <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 bg-white rounded-lg dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10">
                            <i class="fa-solid fa-list-check text-[#8B7355] dark:text-[#C4A97D] text-sm"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Treatment</p>
                            <p id="detailTreatment" class="text-sm font-semibold text-[#3C2F23] dark:text-white"></p>
                        </div>
                    </div>
                    <!-- Date & Time -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50">
                            <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 bg-white rounded-lg dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10">
                                <i class="fa-solid fa-calendar text-[#8B7355] dark:text-[#C4A97D] text-sm"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Date</p>
                                <p id="detailDate" class="text-sm font-semibold text-[#3C2F23] dark:text-white"></p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50">
                            <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 bg-white rounded-lg dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10">
                                <i class="fa-solid fa-clock text-[#8B7355] dark:text-[#C4A97D] text-sm"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Time</p>
                                <p id="detailTime" class="text-sm font-semibold text-[#3C2F23] dark:text-white"></p>
                            </div>
                        </div>
                    </div>
                    <!-- Therapist -->
                    <div class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50">
                        <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 bg-white rounded-lg dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10">
                            <i class="fa-solid fa-user-nurse text-[#8B7355] dark:text-[#C4A97D] text-sm"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Therapist</p>
                            <p id="detailTherapist" class="text-sm font-semibold text-[#3C2F23] dark:text-white"></p>
                        </div>
                    </div>
                    <!-- Status -->
                    <div class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50">
                        <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 bg-white rounded-lg dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10">
                            <i class="fa-solid fa-circle-info text-[#8B7355] dark:text-[#C4A97D] text-sm"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Status</p>
                            <p id="detailStatus" class="text-sm font-semibold"></p>
                        </div>
                    </div>
                    <!-- Reschedule Status (shown if request exists) -->
                    <div id="detailRescheduleStatus" class="hidden p-3 rounded-xl ring-1">
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Reschedule Request</p>
                        <p id="detailRescheduleStatusText" class="text-sm font-semibold"></p>
                    </div>
                </div>
                <div class="px-6 pb-6 space-y-2">
                    <!-- Reschedule Button -->
                    <button type="button" id="openRescheduleBtn"
                        onclick="openRescheduleModal()"
                        class="w-full py-3 rounded-xl text-sm font-semibold text-white booking-btn shadow-md hover:shadow-lg transition active:translate-y-0.5">
                        <i class="mr-2 fa-solid fa-calendar-pen"></i>
                        Request Reschedule
                    </button>
                    <button type="button" onclick="closeBookingDetailsModal()"
                        class="w-full py-3 rounded-xl text-sm font-semibold text-[#8B7355] dark:text-[#C4A97D] border border-[#8B7355] dark:border-[#C4A97D] hover:bg-[#F6EFE6] dark:hover:bg-gray-700 transition">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= RESCHEDULE REQUEST MODAL ================= -->
    <div id="rescheduleModal" class="fixed inset-0 z-[130] hidden">
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeRescheduleModal()"></div>
        <div class="relative mx-auto w-[92%] max-w-lg mt-10 sm:mt-16">
            <div class="overflow-hidden bg-white shadow-2xl dark:bg-gray-800 rounded-3xl ring-1 ring-black/10 dark:ring-white/10">
                <div class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                    <div>
                        <h3 class="text-lg font-semibold text-[#3C2F23] dark:text-white">Request Reschedule</h3>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Please provide a valid reason for rescheduling.</p>
                    </div>
                    <button type="button" onclick="closeRescheduleModal()"
                        class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10">
                        <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <input type="hidden" id="rescheduleBookingId">

                    <!-- Current Schedule -->
                    <div class="p-3 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50 ring-1 ring-black/5 dark:ring-white/10">
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Current Schedule</p>
                        <p id="rescheduleCurrentSchedule" class="text-sm text-[#3C2F23] dark:text-white font-medium"></p>
                    </div>

                    <!-- New Date -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                            New Preferred Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" id="rescheduleDate"
                            class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40"
                            required>
                    </div>

                    <!-- New Time -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                            New Preferred Time <span class="text-red-500">*</span>
                        </label>
                        <input type="time" id="rescheduleTime"
                            class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40"
                            required>
                        <p id="rescheduleTimeError" class="hidden mt-1 text-[11px] text-red-500 dark:text-red-400">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <span id="rescheduleTimeErrorText"></span>
                        </p>
                    </div>

                    <!-- Reason -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                            Reason for Rescheduling <span class="text-red-500">*</span>
                        </label>
                        <textarea id="rescheduleReason" rows="4"
                            placeholder="Please explain why you need to reschedule (minimum 10 characters)..."
                            class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40 text-sm resize-none"
                            required></textarea>
                        <p id="rescheduleReasonCount" class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">0 / 1000 characters</p>
                    </div>

                    <!-- Error message -->
                    <div id="rescheduleError" class="hidden p-3 text-sm text-red-600 dark:text-red-300 rounded-xl bg-red-50 dark:bg-red-900/20 ring-1 ring-red-200 dark:ring-red-800">
                        <i class="mr-1 fa-solid fa-circle-exclamation"></i>
                        <span id="rescheduleErrorText"></span>
                    </div>
                </div>
                <div class="px-6 pb-6">
                    <button type="button" id="rescheduleSubmitBtn"
                        onclick="submitRescheduleRequest()"
                        class="w-full py-3 rounded-xl text-sm font-semibold text-white booking-btn shadow-md hover:shadow-lg transition active:translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="mr-2 fa-solid fa-paper-plane"></i>
                        Submit Request
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= SPAS NEAR YOU ================= -->
    @auth
    @role('customer')
    <section id="nearbySection" class="hidden py-5">
        <div class="px-6 mx-auto mt-5 max-w-7xl">
            <div class="text-center">
                <div class="flex items-center justify-center gap-6">
                    <span class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                    <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] dark:text-white font-semibold">Spas Near You</h2>
                    <span class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                </div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Based on your saved location.</p>
            </div>
            <div id="nearbyGrid" class="grid grid-cols-1 gap-6 mt-5 sm:grid-cols-2 lg:grid-cols-4">
                {{-- filled by JS --}}
            </div>
        </div>
    </section>
    @endrole
    @endauth

    <!-- ================= FEATURED SPAS ================= -->
    <div id="browseSections" class="{{ $isSearching ? 'hidden' : '' }}">
    <section class="py-5">
        <div class="px-6 mx-auto mt-5 max-w-7xl">
            <div class="text-center">
                <div class="flex items-center justify-center gap-6">
                    <span class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                    <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] dark:text-white font-semibold">Featured Spas</h2>
                    <span class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                </div>
                <p id="featuredSpasSubtitle" class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    @if(!empty($search))
                        Showing results for "<span class="font-semibold text-[#8B7355] dark:text-[#C4A97D]">{{ $search }}</span>"
                    @else
                        Curated picks for a premium relaxation experience.
                    @endif
                </p>
            </div>

            <div id="featuredSpasGrid" class="grid grid-cols-1 gap-6 mt-5 sm:grid-cols-2 lg:grid-cols-4">
                @php $featuredCount = 0; @endphp
                @forelse($spas as $spa)
                    @foreach($spa->branches as $branch)
                        @if($spa->verification_status === 'verified' && $branch->profile?->is_listed)
                            @php
                                $featuredCount++;
                                $lowestPrice = \App\Models\Treatment::withoutGlobalScopes()
                                    ->where('spa_id', $spa->id)
                                    ->where('branch_id', $branch->id)
                                    ->min('price');

                                $profile = $branch->profile;
                                $fallbackImage = asset('storage/branch_profiles/emptyspa.jpg');

                                $coverPhoto = !empty($profile?->cover_image)
                                ? asset('storage/' . $profile->cover_image)
                                : $fallbackImage;

                                $galleryPhotos = collect($profile->gallery_images ?? [])
                                ->filter()
                                ->map(fn($img) => asset('storage/' . $img))
                                ->values();

                                $photos = collect([$coverPhoto])
                                    ->merge($galleryPhotos)
                                    ->take(5)
                                    ->pad(5, $fallbackImage)
                                    ->values()
                                    ->toArray();

                                $thumb = $coverPhoto;

                                $activePromo = \App\Models\Promo::withoutGlobalScopes()
                                    ->where('spa_id', $spa->id)
                                    ->where('branch_id', $branch->id)
                                    ->activeToday()
                                    ->first();

                                // Spa rating aggregate — avg of ratings.spa_rating for this
                                // spa/branch, joined through bookings since ratings has no
                                // spa_id/branch_id of its own.
                                $ratingAgg = \App\Models\Rating::query()
                                    ->join('bookings', 'bookings.id', '=', 'ratings.booking_id')
                                    ->where('bookings.spa_id', $spa->id)
                                    ->where('bookings.branch_id', $branch->id)
                                    ->whereNotNull('ratings.spa_rating')
                                    ->selectRaw('AVG(ratings.spa_rating) as avg_rating, COUNT(*) as rating_count')
                                    ->first();

                                $branchTreatments = \App\Models\Treatment::withoutGlobalScope('spa_branch')
                                    ->where('branch_id', $branch->id)
                                    ->where('spa_id', $spa->id)
                                    ->with(['promos' => fn($q) => $q->withoutGlobalScope('spa_branch')->activeToday()])
                                    ->get();

                                $branchPackages = \App\Models\Package::withoutGlobalScope('spa_branch')
                                    ->where('branch_id', $branch->id)
                                    ->where('spa_id', $spa->id)
                                    ->with(['promos' => fn($q) => $q->withoutGlobalScope('spa_branch')->activeToday()])
                                    ->get();

                                $spaPayload = [
                                    'id'              => $spa->id,
                                    'name'            => $spa->name,
                                    'tag'             => 'Featured Spa',
                                    'branch_id'       => $branch->id,
                                    'branch_name'     => $branch->name,
                                    'branch_location' => $branch->location ?? '',
                                    'desc'            => $profile->description ?? '',
                                    'price_note'      => $lowestPrice ? number_format($lowestPrice, 2) : null,
                                    'photos'          => $photos,
                                    'address'         => $profile->address ?? $branch->location ?? 'Location unavailable',
                                    'phone'           => $profile->phone ?? '',
                                    'lat'             => $profile->latitude,
                                    'lng'             => $profile->longitude,
                                    'treatments'      => $branchTreatments,
                                    'packages'        => $branchPackages,
                                    'has_promo'    => (bool) $activePromo,
                                    'promo_label'  => $activePromo?->name,
                                    'amenities'       => $profile->amenities ?? [],
                                    'is_hiring'   => $profile->is_hiring ?? false,
                                    'hiring_note' => $profile->hiring_note ?? null,
                                    'rating_avg'   => $ratingAgg->avg_rating ? round($ratingAgg->avg_rating, 1) : null,
                                    'rating_count' => (int) ($ratingAgg->rating_count ?? 0),
                                ];
                            @endphp

                            <button type="button"
                                class="w-full overflow-hidden text-left transition bg-white shadow-sm dark:bg-gray-800 group rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-2xl"
                                data-open-spa-modal
                                data-spa='@json($spaPayload)'>
                                <div class="relative overflow-hidden">
                                    <img src="{{ $thumb }}" class="h-56 w-full object-cover transition duration-500 group-hover:scale-[1.04]" alt="{{ $spa->name }}">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-black/0 to-transparent"></div>
                                    <div class="absolute top-3 left-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-[#6F5430]/90 text-white text-[11px] font-semibold backdrop-blur-sm">
                                        <i class="fa-solid fa-star text-[#F5C842] text-[10px]"></i>
                                        Featured
                                    </div>
                                    @if($profile->is_hiring ?? false)
                                        <span onclick="event.stopPropagation(); openApplicationModal({{ $spa->id }}, {{ $branch->id }}, '{{ addslashes($spa->name) }}')"
                                            class="absolute top-3 right-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-500 hover:bg-red-700 text-white text-[11px] font-semibold backdrop-blur-sm transition cursor-pointer">
                                            <i class="fa-solid fa-briefcase text-[10px]"></i>
                                            We're Hiring · <span class="underline underline-offset-2">Apply Now</span>
                                        </span>
                                    @endif
                                    @if($activePromo)
                                        <div class="absolute {{ ($profile->is_hiring ?? false) ? 'top-12' : 'top-3' }} right-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-600 text-white text-[11px] font-semibold backdrop-blur-sm">
                                            <i class="fa-solid fa-tag text-[10px]"></i>
                                            {{ $activePromo->name }}
                                        </div>
                                    @endif
                                </div>
                                <div class="p-5">
                                    <h3 class="text-[15px] font-semibold text-[#3C2F23] dark:text-white leading-tight">{{ $spa->name }}</h3>
                                    @if($spaPayload['rating_avg'])
                                    <div class="flex items-center gap-1 mt-1">
                                        <i class="fa-solid fa-star text-[#D2A85B] text-xs"></i>
                                        <span class="text-xs font-semibold text-[#3C2F23] dark:text-white">{{ $spaPayload['rating_avg'] }}</span>
                                        <span class="text-xs text-gray-400">({{ $spaPayload['rating_count'] }})</span>
                                    </div>
                                    @endif
                                    @php
                                        $addr = $spaPayload['address'] ?? '';
                                        $cleaned = preg_replace('/,?\s*(Philippines|Calabarzon|\d{4})\s*/i', '', $addr);
                                        $parts = array_values(array_filter(array_map('trim', explode(',', $cleaned))));
                                        $addrSummary = count($parts) >= 2
                                            ? implode(', ', array_slice($parts, -2))
                                            : (implode(', ', $parts) ?: 'Location unavailable');
                                    @endphp
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $addrSummary }}</p>
                                    @if($lowestPrice)
                                        <p class="mt-2 text-xs font-medium text-[#8B7355] dark:text-[#C4A97D]">
                                            Starts at ₱{{ number_format($lowestPrice, 2) }}
                                        </p>
                                    @endif
                                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400 line-clamp-2">{{ $spaPayload['desc'] ?? 'No description yet.' }}</p>
                                </div>
                            </button>
                        @endif
                    @endforeach
                @empty
                @endforelse

                @if($featuredCount === 0)
                    <div class="py-16 text-center col-span-full">
                        <div class="flex items-center justify-center w-14 h-14 mx-auto mb-4 rounded-2xl bg-[#F6EFE6] dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10">
                            <i class="fa-solid fa-star text-xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                        </div>
                        <p class="font-semibold text-[#3C2F23] dark:text-white">No featured spas found</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            @if(!empty($search))
                                No featured spas match "{{ $search }}". Try a different name or location.
                            @else
                                No featured spas available yet.
                            @endif
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <!-- ================= LISTED SPAS ================= -->
        <section class="pb-10 mt-10 ">
            <div class="px-6 mx-auto max-w-7xl">
                <div class="text-center">
                    <div class="flex items-center justify-center gap-6">
                        <span class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                        <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] dark:text-white font-semibold">Other Spas in Cavite</h2>
                        <span class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                    </div>
                    <p id="otherSpasSubtitle" class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                        @if(!empty($search))
                            Showing results for "<span class="font-semibold text-[#8B7355] dark:text-[#C4A97D]">{{ $search }}</span>"
                        @else
                            Explore more verified wellness destinations.
                        @endif
                    </p>
                </div>

                @php
                    $hasListedBasic = $basicSpas->flatMap->branches->contains(fn($b) => $b->profile?->is_listed);
                @endphp

                <div id="otherSpasContainer">
                @if($hasListedBasic)
                    <div class="grid grid-cols-1 gap-5 mt-12 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach($basicSpas as $spa)
                            @foreach($spa->branches as $branch)
                                @if($branch->profile?->is_listed)
                                    @php
                                        $lowestPrice = \App\Models\Treatment::withoutGlobalScopes()
                                            ->where('spa_id', $spa->id)
                                            ->where('branch_id', $branch->id)
                                            ->min('price');

                                        $profile = $branch->profile;
                                        $fallbackImage = asset('storage/branch_profiles/emptyspa.jpg');

                                        $coverPhoto = !empty($profile?->cover_image)
                                            ? asset('storage/' . $profile->cover_image)
                                            : $fallbackImage;

                                        $galleryPhotos = collect($profile->gallery_images ?? [])
                                            ->filter()
                                            ->map(fn($img) => asset('storage/' . $img))
                                            ->values();

                                        $photos = collect([$coverPhoto])
                                            ->merge($galleryPhotos)
                                            ->take(5)
                                            ->pad(5, $fallbackImage)
                                            ->values()
                                            ->toArray();

                                        $branchTreatments = \App\Models\Treatment::withoutGlobalScope('spa_branch')
                                            ->where('branch_id', $branch->id)
                                            ->where('spa_id', $spa->id)
                                            ->with(['promos' => fn($q) => $q->withoutGlobalScope('spa_branch')->activeToday()])
                                            ->get();

                                        $branchPackages = \App\Models\Package::withoutGlobalScope('spa_branch')
                                            ->where('branch_id', $branch->id)
                                            ->where('spa_id', $spa->id)
                                            ->with(['promos' => fn($q) => $q->withoutGlobalScope('spa_branch')->activeToday()])
                                            ->get();

                                        $activePromo = \App\Models\Promo::withoutGlobalScopes()
                                            ->where('spa_id', $spa->id)
                                            ->where('branch_id', $branch->id)
                                            ->activeToday()
                                            ->first();

                                        // Spa rating aggregate — same pattern as Featured Spas above.
                                        $ratingAgg = \App\Models\Rating::query()
                                            ->join('bookings', 'bookings.id', '=', 'ratings.booking_id')
                                            ->where('bookings.spa_id', $spa->id)
                                            ->where('bookings.branch_id', $branch->id)
                                            ->whereNotNull('ratings.spa_rating')
                                            ->selectRaw('AVG(ratings.spa_rating) as avg_rating, COUNT(*) as rating_count')
                                            ->first();

                                        $spaPayload = [
                                            'id'              => $spa->id,
                                            'name'            => $spa->name,
                                            'tag'             => 'Listed Spa',
                                            'branch_id'       => $branch->id,
                                            'branch_name'     => $branch->name,
                                            'branch_location' => $branch->location ?? '',
                                            'desc'            => $profile->description ?? '',
                                            'price_note'      => $lowestPrice ? number_format($lowestPrice, 2) : null,
                                            'photos'          => $photos,
                                            'address'         => $profile->address ?? $branch->location ?? 'Location unavailable',
                                            'phone'           => $profile->phone ?? '',
                                            'lat'             => $profile->latitude,
                                            'lng'             => $profile->longitude,
                                            'treatments'      => $branchTreatments,
                                            'packages'        => $branchPackages,
                                            'has_promo'    => (bool) $activePromo,
                                            'promo_label'  => $activePromo?->name,
                                            'amenities'       => $profile->amenities ?? [],
                                            'is_hiring'   => $profile->is_hiring ?? false,
                                            'hiring_note' => $profile->hiring_note ?? null,
                                            'rating_avg'   => $ratingAgg->avg_rating ? round($ratingAgg->avg_rating, 1) : null,
                                            'rating_count' => (int) ($ratingAgg->rating_count ?? 0),
                                        ];
                                    @endphp

                                    <button type="button"
                                        class="w-full overflow-hidden text-left transition bg-white shadow-sm dark:bg-gray-800 group rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl"
                                        data-open-spa-modal
                                        data-spa='@json($spaPayload)'>
                                        <div class="relative overflow-hidden">
                                            <img src="{{ $coverPhoto }}" class="h-48 w-full object-cover transition duration-500 group-hover:scale-[1.04]" alt="{{ $spa->name }}">
                                            <div class="absolute inset-0 bg-gradient-to-t from-black/35 via-black/0 to-transparent"></div>
                                            <div class="absolute top-3 left-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/80 dark:bg-gray-900/70 text-[#6F5430] dark:text-[#C4A97D] text-[11px] font-semibold backdrop-blur-sm ring-1 ring-black/5 dark:ring-white/10">
                                                <i class="fa-solid fa-spa text-[#8B7355] dark:text-[#C4A97D] text-[10px]"></i>
                                                Verified
                                            </div>
                                            @if($profile->is_hiring ?? false)
                                                <span onclick="event.stopPropagation(); openApplicationModal({{ $spa->id }}, {{ $branch->id }}, '{{ addslashes($spa->name) }}')"
                                                    class="absolute top-3 right-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-500 hover:bg-red-700 text-white text-[11px] font-semibold backdrop-blur-sm transition cursor-pointer">
                                                    <i class="fa-solid fa-briefcase text-[10px]"></i>
                                                    We're Hiring · <span class="underline underline-offset-2">Apply Now</span>
                                                </span>
                                            @endif
                                            @if($activePromo)
                                                <div class="absolute {{ ($profile->is_hiring ?? false) ? 'top-12' : 'top-3' }} right-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-600 text-white text-[11px] font-semibold backdrop-blur-sm">
                                                    <i class="fa-solid fa-tag text-[10px]"></i>
                                                    {{ $activePromo->name }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="p-4">
                                            <h3 class="text-[15px] font-semibold text-[#3C2F23] dark:text-white leading-tight">{{ $spa->name }}</h3>
                                            @if($spaPayload['rating_avg'])
                                            <div class="flex items-center gap-1 mt-1">
                                                <i class="fa-solid fa-star text-[#D2A85B] text-xs"></i>
                                                <span class="text-xs font-semibold text-[#3C2F23] dark:text-white">{{ $spaPayload['rating_avg'] }}</span>
                                                <span class="text-xs text-gray-400">({{ $spaPayload['rating_count'] }})</span>
                                            </div>
                                            @endif
                                            @php
                                                $addr = $spaPayload['address'] ?? '';
                                                $cleaned = preg_replace('/,?\s*(Philippines|Calabarzon|\d{4})\s*/i', '', $addr);
                                                $parts = array_values(array_filter(array_map('trim', explode(',', $cleaned))));
                                                $addrSummary = count($parts) >= 2
                                                    ? implode(', ', array_slice($parts, -2))
                                                    : (implode(', ', $parts) ?: 'Location unavailable');
                                            @endphp
                                            <p class="mt-1 text-xs text-gray-900 dark:text-gray-300">{{ $addrSummary }}</p>
                                            @if($lowestPrice)
                                                <p class="mt-2 text-xs font-medium text-[#8B7355] dark:text-[#C4A97D]">
                                                    Starts at ₱{{ number_format($lowestPrice, 2) }}
                                                </p>
                                            @endif
                                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 line-clamp-2">
                                                {{ $spaPayload['desc'] ?: 'No description yet.' }}
                                            </p>
                                        </div>
                                    </button>
                                @endif
                            @endforeach
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-16 mt-12 border border-dashed border-[#C4A97D]/40 dark:border-[#C4A97D]/25 rounded-3xl bg-white/50 dark:bg-gray-800/40">
                        <div class="flex items-center justify-center w-16 h-16 mb-5 rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                            <i class="fa-solid fa-spa text-2xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                        </div>
                        @if(!empty($search))
                            <h3 class="text-lg font-semibold font-['Playfair_Display'] text-[#3C2F23] dark:text-white">No spas found for "{{ $search }}"</h3>
                            <p class="max-w-xs mt-2 text-sm text-center text-gray-500 dark:text-gray-400">
                                Try a different name or location, or browse all available spas.
                            </p>
                            <a href="{{ url('/') }}"
                                class="inline-flex items-center gap-2 mt-6 px-6 py-2.5 text-sm font-semibold text-white rounded-xl booking-btn shadow-md hover:shadow-lg transition active:translate-y-0.5">
                                <i class="text-xs fa-solid fa-arrow-left"></i>
                                Browse All Spas
                            </a>
                        @else
                            <h3 class="text-lg font-semibold font-['Playfair_Display'] text-[#3C2F23] dark:text-white">No spas listed yet</h3>
                            <p class="max-w-xs mt-2 text-sm text-center text-gray-500 dark:text-gray-400">
                                Be the first to list your spa and reach customers looking for wellness experiences.
                            </p>
                            @auth
                                @role('customer')
                                    <button type="button" onclick="openBusinessInfo()"
                                        class="inline-flex items-center gap-2 mt-6 px-6 py-2.5 text-sm font-semibold text-white rounded-xl booking-btn shadow-md hover:shadow-lg transition active:translate-y-0.5">
                                        <i class="text-xs fa-solid fa-plus"></i>
                                        List Your Spa
                                    </button>
                                @else
                                    <a href="{{ route('register.business') }}"
                                        class="inline-flex items-center gap-2 mt-6 px-6 py-2.5 text-sm font-semibold text-white rounded-xl booking-btn shadow-md hover:shadow-lg transition active:translate-y-0.5">
                                        <i class="text-xs fa-solid fa-plus"></i>
                                        List Your Spa
                                    </a>
                                @endrole
                            @else
                                <a href="{{ route('register.business') }}"
                                    class="inline-flex items-center gap-2 mt-6 px-6 py-2.5 text-sm font-semibold text-white rounded-xl booking-btn shadow-md hover:shadow-lg transition active:translate-y-0.5">
                                    <i class="text-xs fa-solid fa-plus"></i>
                                    List Your Spa
                                </a>
                            @endauth
                        @endif
                    </div>
                @endif
                </div>
            </div>
        </section>
    </section>
    </div>

    <!-- ================= SEARCH RESULTS (shown instead of the two sections above while searching) ================= -->
    <section id="unifiedResultsSection" class="py-5 {{ $isSearching ? '' : 'hidden' }}">
        <div class="px-6 mx-auto mt-5 max-w-7xl">
            <div class="text-center">
                <div class="flex items-center justify-center gap-6">
                    <span class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                    <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] dark:text-white font-semibold">Search Results</h2>
                    <span class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                </div>
                <p id="unifiedResultsSubtitle" class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    @if($place || $treatment)
                        Showing results
                        @if($place) in "<span class="font-semibold text-[#8B7355] dark:text-[#C4A97D]">{{ $place }}</span>" @endif
                        @if($treatment) for "<span class="font-semibold text-[#8B7355] dark:text-[#C4A97D]">{{ $treatment }}</span>" @endif
                    @else
                        Showing all spas
                    @endif
                    <button type="button" onclick="clearSpaSearch()" class="ml-2 text-[#8B7355] dark:text-[#C4A97D] underline underline-offset-2">Clear search</button>
                </p>
            </div>

            <div id="unifiedResultsGrid" class="grid grid-cols-1 gap-6 mt-8 sm:grid-cols-2 lg:grid-cols-4">
                @forelse($results ?? [] as $item)
                    @php
                        $addr = $item['address'] ?? '';
                        $cleaned = preg_replace('/,?\s*(Philippines|Calabarzon|\d{4})\s*/i', '', $addr);
                        $parts = array_values(array_filter(array_map('trim', explode(',', $cleaned))));
                        $addrSummary = count($parts) >= 2
                            ? implode(', ', array_slice($parts, -2))
                            : (implode(', ', $parts) ?: 'Location unavailable');
                    @endphp
                    <button type="button"
                        class="w-full overflow-hidden text-left transition bg-white shadow-sm dark:bg-gray-800 group rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-2xl"
                        data-open-spa-modal
                        data-spa='@json($item)'>
                        <div class="relative overflow-hidden">
                            <img src="{{ $item['photos'][0] ?? '' }}" class="h-56 w-full object-cover transition duration-500 group-hover:scale-[1.04]" alt="{{ $item['name'] }}">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-black/0 to-transparent"></div>
                            <div class="absolute top-3 left-3 flex items-center gap-1 px-2.5 py-1 rounded-full {{ $item['is_featured'] ? 'bg-[#6F5430]/90 text-white' : 'bg-white/80 dark:bg-gray-900/70 text-[#6F5430] dark:text-[#C4A97D] ring-1 ring-black/5 dark:ring-white/10' }} text-[11px] font-semibold backdrop-blur-sm">
                                <i class="fa-solid {{ $item['is_featured'] ? 'fa-star text-[#F5C842]' : 'fa-spa text-[#8B7355] dark:text-[#C4A97D]' }} text-[10px]"></i>
                                {{ $item['is_featured'] ? 'Featured' : 'Verified' }}
                            </div>
                            @if($item['is_hiring'] ?? false)
                                <span onclick="event.stopPropagation(); openApplicationModal({{ $item['id'] }}, {{ $item['branch_id'] }}, '{{ addslashes($item['name']) }}')"
                                    class="absolute top-3 right-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-500 hover:bg-red-700 text-white text-[11px] font-semibold backdrop-blur-sm transition cursor-pointer">
                                    <i class="fa-solid fa-briefcase text-[10px]"></i>
                                    We're Hiring · <span class="underline underline-offset-2">Apply Now</span>
                                </span>
                            @endif
                        </div>
                        <div class="p-5">
                            <h3 class="text-[15px] font-semibold text-[#3C2F23] dark:text-white leading-tight">{{ $item['name'] }}</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $addrSummary }}</p>
                            @if($item['price_note'] ?? null)
                                <p class="mt-2 text-xs font-medium text-[#8B7355] dark:text-[#C4A97D]">Starts at ₱{{ $item['price_note'] }}</p>
                            @endif
                            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400 line-clamp-2">{{ $item['desc'] ?: 'No description yet.' }}</p>
                        </div>
                    </button>
                @empty
                    <div class="py-16 text-center col-span-full">
                        <div class="flex items-center justify-center w-14 h-14 mx-auto mb-4 rounded-2xl bg-[#F6EFE6] dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10">
                            <i class="fa-solid fa-magnifying-glass text-xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                        </div>
                        <p class="font-semibold text-[#3C2F23] dark:text-white">No spas found</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Try a different place or treatment, or
                            <button type="button" onclick="clearSpaSearch()" class="text-[#8B7355] dark:text-[#C4A97D] underline underline-offset-2">browse all spas</button>.
                        </p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <!-- ================= REVIEWS MODAL ================= -->
    <div id="reviewsModal" class="fixed inset-0 z-[145] hidden">
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeReviewsModal()"></div>
        <div class="relative mx-auto w-[92%] max-w-lg mt-10 sm:mt-16">
            <div class="overflow-hidden bg-white shadow-2xl dark:bg-gray-800 rounded-3xl ring-1 ring-black/10 dark:ring-white/10 flex flex-col max-h-[80vh]">
                <div class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                    <div>
                        <h3 class="text-lg font-semibold text-[#3C2F23] dark:text-white">Ratings & Reviews</h3>
                        <p id="reviewsModalSpaName" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"></p>
                    </div>
                    <button type="button" onclick="closeReviewsModal()"
                        class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10">
                        <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- Filter tabs -->
                <div id="reviewFilterTabs" class="flex items-center gap-2 px-6 py-3 overflow-x-auto border-b border-black/5 dark:border-white/10">
                    <p class="text-sm text-gray-400">Loading...</p>
                </div>

                <!-- List -->
                <div id="reviewsModalList" class="flex-1 p-6 space-y-3 overflow-y-auto">
                    <p class="text-sm italic text-gray-400 dark:text-gray-500">Loading reviews...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= SPA MODAL ================= -->
    <div id="spaModal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" data-close-spa-modal></div>
        <div class="relative mx-auto w-[92%] max-w-5xl mt-8 mb-8">
            <div class="overflow-hidden bg-white dark:bg-gray-800 shadow-2xl rounded-3xl flex flex-col max-h-[90vh]">
                <div class="sticky top-0 z-10 flex items-center justify-between px-6 py-4 border-b bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm border-black/5 dark:border-white/10">
                    <div>
                        <h3 id="spaModalName" class="text-2xl font-['Playfair_Display'] font-bold tracking-tight text-[#3C2F23] dark:text-white">Spa Name</h3>
                        <div class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 mt-1">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#F6EFE6] dark:bg-gray-700 text-[#6F5430] dark:text-[#C4A97D] text-xs font-semibold ring-1 ring-[#8B7355]/20 dark:ring-[#C4A97D]/20">
                                <i class="fa-solid fa-star text-[#D2A85B] text-[10px]"></i>
                                <span id="spaModalTag">Featured Spa</span>
                            </span>
                            <span class="text-gray-300 dark:text-gray-600">·</span>
                            <i class="fa-solid fa-location-dot text-[#8B7355] dark:text-[#C4A97D] text-xs"></i>
                            <span id="spaModalAddressSummary" class="font-medium text-[#6F5430] dark:text-[#C4A97D] underline underline-offset-2 decoration-dotted">Location</span>
                            <button type="button" id="spaModalRating" onclick="openReviewsModalFromSpa()"
                                class="items-center hidden gap-1 hover:underline underline-offset-2">
                                <span class="text-gray-300 dark:text-gray-600">·</span>
                                <i class="fa-solid fa-star text-[#D2A85B] text-xs"></i>
                                <span id="spaModalRatingValue" class="font-medium text-[#3C2F23] dark:text-white"></span>
                                <span id="spaModalRatingCount" class="text-gray-400"></span>
                            </button>
                        </div>
                    </div>
                    <button data-close-spa-modal
                        class="flex items-center justify-center w-9 h-9 text-gray-500 dark:text-gray-400 transition rounded-xl hover:bg-[#F6EFE6] dark:hover:bg-gray-700 hover:text-[#3C2F23] dark:hover:text-white ring-1 ring-black/5 dark:ring-white/10">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="overflow-y-auto">
                    <div class="p-6">
                        <div class="grid grid-cols-4 grid-rows-2 gap-2 h-[380px] rounded-2xl overflow-hidden">
                            <div class="relative col-span-2 row-span-2 bg-gray-100 cursor-pointer dark:bg-gray-700 group">
                                <img id="spaModalMainPhoto" src="" class="object-cover w-full h-full transition duration-500 group-hover:scale-[1.02]">
                                <div class="absolute inset-0 transition opacity-0 bg-gradient-to-t from-black/20 to-transparent group-hover:opacity-100"></div>
                            </div>
                            <div class="col-span-1 row-span-1 overflow-hidden bg-gray-100 dark:bg-gray-700">
                                <img id="gallery_1" class="object-cover w-full h-full transition duration-300 cursor-pointer hover:scale-105">
                            </div>
                            <div class="col-span-1 row-span-1 overflow-hidden bg-gray-100 dark:bg-gray-700">
                                <img id="gallery_2" class="object-cover w-full h-full transition duration-300 cursor-pointer hover:scale-105">
                            </div>
                            <div class="col-span-1 row-span-1 overflow-hidden bg-gray-100 dark:bg-gray-700">
                                <img id="gallery_3" class="object-cover w-full h-full transition duration-300 cursor-pointer hover:scale-105">
                            </div>
                            <div class="relative col-span-1 row-span-1 overflow-hidden bg-gray-100 cursor-pointer dark:bg-gray-700 group">
                                <img id="gallery_4" class="object-cover w-full h-full transition duration-300 group-hover:scale-105">
                                <div id="spaModalGalleryCount"
                                    class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-white bg-black/40 backdrop-blur-[1px] transition group-hover:bg-black/50">
                                    <span class="flex flex-col items-center gap-1">
                                        <i class="text-lg fa-solid fa-images"></i>
                                        View All
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-8 px-6 pb-8 md:grid-cols-3">
                        <div class="space-y-7 md:col-span-2">
                            <div>
                                <h4 class="mb-2 text-xl font-['Playfair_Display'] font-semibold text-[#3C2F23] dark:text-white">About this spa</h4>
                                <p id="spaModalDesc" class="text-sm leading-relaxed text-gray-600 dark:text-gray-400"></p>
                            </div>
                            <div id="spaModalHiring" class="hidden p-4 rounded-2xl bg-green-50 dark:bg-green-900/20 ring-1 ring-green-200 dark:ring-green-800">
                                <div class="flex items-center gap-2">
                                    <i class="text-sm text-green-600 dark:text-green-400 fa-solid fa-briefcase"></i>
                                    <p class="text-sm font-semibold text-green-700 dark:text-green-300">We're Hiring</p>
                                </div>
                                <p id="spaModalHiringNote" class="mt-1 text-sm text-green-700/90 dark:text-green-300/80"></p>
                            </div>
                            <hr class="border-[#E8DDD0] dark:border-gray-700">
                            <div>
                                <h4 class="mb-4 text-xl font-['Playfair_Display'] font-semibold text-[#3C2F23] dark:text-white">What this place offers</h4>
                                <hr class="border-[#E8DDD0] dark:border-gray-700">
                                <div id="spaModalAmenities">
                                    <p class="text-sm italic text-gray-400 dark:text-gray-500">No amenities listed yet.</p>
                                </div>
                            </div>
                        </div>
                        <div class="md:col-span-1">
                            <div class="sticky top-4 p-5 space-y-3 border border-[#E8DDD0] dark:border-gray-700 shadow-sm rounded-2xl bg-[#FDFAF6] dark:bg-gray-900/40">
                                <div class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-white dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10 flex-shrink-0 mt-0.5">
                                        <i class="fa-solid fa-location-dot text-[#8B7355] dark:text-[#C4A97D] text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Address</p>
                                        <p id="spaModalAddress" class="mt-0.5 text-sm text-[#3C2F23] dark:text-gray-200 leading-snug"></p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-white dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10 flex-shrink-0 mt-0.5">
                                        <i class="fa-solid fa-phone text-[#8B7355] dark:text-[#C4A97D] text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Contact</p>
                                        <p id="spaModalPhone" class="mt-0.5 text-sm text-[#3C2F23] dark:text-gray-200"></p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-white dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10 flex-shrink-0 mt-0.5">
                                        <i class="fa-solid fa-tag text-[#8B7355] dark:text-[#C4A97D] text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Price</p>
                                        <p id="spaModalPrice" class="mt-0.5 text-sm font-semibold text-[#6F5430] dark:text-[#C4A97D]"></p>
                                    </div>
                                </div>
                                {{-- Leaflet/OSM tiles can't be themed via CSS; dimming overlay
                                     keeps the map readable against the dark sidebar without
                                     blocking interaction (pointer-events-none). --}}
                                <div class="relative">
                                    <div id="spaModalMap" class="w-full h-[170px] rounded-xl border border-[#E8DDD0] dark:border-gray-700 bg-[#F6EFE6] dark:bg-gray-700 overflow-hidden shadow-inner"></div>
                                    <div class="absolute inset-0 hidden pointer-events-none dark:block rounded-xl bg-gray-900/30 mix-blend-multiply"></div>
                                </div>
                                <button type="button" id="openBookingModalBtn"
                                    class="flex items-center justify-center w-full gap-2 py-3 mt-1 text-sm font-semibold text-white transition rounded-xl booking-btn shadow-md hover:shadow-lg active:translate-y-0.5">
                                    <i class="fa-solid fa-calendar-check"></i>
                                    Reserve An Appointment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= BOOKING MODAL ================= -->
    <style>
        /*
          These rules back elements that are built entirely in JavaScript
          (service cards, time slots, filter tabs). Tailwind's build only
          generates CSS for class names it can find as literal text in
          scanned files — a class that only ever exists inside a JS string
          has nothing to generate from, so it silently does nothing. Plain
          CSS here has no such dependency.
        */
        .svc-card { display: flex; flex-direction: row; align-items: flex-start; gap: 12px; padding: 12px 16px; cursor: pointer; border-left: 4px solid transparent; transition: background-color .15s ease, border-color .15s ease; }
        .svc-card.hidden { display: none !important; }
        .svc-card-thumb { flex-shrink: 0; width: 56px; height: 56px; border-radius: 10px; overflow: hidden; background: #F6EFE6; border: 1px solid rgba(0,0,0,0.05); }
        .svc-card-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .svc-card-content { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
        .svc-card:hover { background-color: rgba(246, 239, 230, 0.5); }
        .svc-card.is-selected { border-left-color: #8B7355; background-color: rgba(246, 239, 230, 0.7); }
        .svc-card:focus-within { outline: 2px solid #8B7355; outline-offset: -2px; }
        .svc-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
        .svc-card-name { font-size: 13px; font-weight: 500; color: #3C2F23; }
        .svc-card-badge { margin-left: 6px; font-size: 10px; font-weight: 600; color: #6F5430; background: #F6EFE6; border: 1px solid rgba(0,0,0,0.05); border-radius: 999px; padding: 1px 8px; }
        .svc-card-price { font-size: 13px; font-weight: 600; color: #6F5430; flex-shrink: 0; }
        .svc-card-desc { font-size: 12px; color: #6b7280; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .svc-card-meta { font-size: 11px; color: #8B7355; }
        .svc-section-header { padding: 10px 16px 4px; font-size: 10px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #9ca3af; background: #fafafa; }

        /* Filter Tabs */
        .svc-filter-tab { padding: 6px 12px; font-size: 12px; font-weight: 500; border-radius: 8px; color: #6b7280; background: transparent; transition: background-color .15s ease, color .15s ease; }
        .svc-filter-tab.is-active { background: #8B7355; color: #fff; }

        /* Slot Button */
        .slot-btn { padding: 8px 4px; font-size: 12px; font-weight: 500; border-radius: 10px; border: 1px solid rgba(0,0,0,0.1); color: #3C2F23; background: #fff; transition: border-color .15s ease, background-color .15s ease, color .15s ease; }
        .slot-btn:hover:not(:disabled) { border-color: #8B7355; background-color: rgba(246, 239, 230, 0.5); }
        .slot-btn.is-selected { border-color: #8B7355; background-color: #8B7355; color: #fff; }
        .slot-btn:disabled { cursor: not-allowed; background-color: #f9fafb; color: #d1d5db; border-color: #f3f4f6; }
        .slot-btn.is-past-closing:disabled { border-style: dashed; border-color: #d1d5db; }

        /* Segmented Search Pill */
        .search-segment { border-radius: 999px; transition: box-shadow .15s ease; }
        .search-segment.active { box-shadow: 0 1px 8px rgba(0,0,0,0.12); }
        .search-dropdown { position: fixed; z-index: 200; display: none; width: 320px; max-width: calc(100vw - 32px); padding: 14px; overflow-y: auto; background: #fff; border-radius: 18px; box-shadow: 0 16px 40px rgba(0,0,0,0.18), 0 0 0 1px rgba(0,0,0,0.06); max-height: 320px; }
        .search-dropdown.open { display: block; }
        .search-dropdown-label { margin-bottom: 8px; font-size: 10px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #9a8f80; }
        .search-chip { padding: 6px 14px; border-radius: 999px; background: #F6EFE6; font-size: 13px; cursor: pointer; border: 1px solid rgba(0,0,0,0.05); color: #3C2F23; }
        .search-chip:hover { background: #efe0cd; }
        .search-suggestion-row { display: flex; align-items: center; gap: 8px; padding: 7px 10px; border-radius: 10px; cursor: pointer; font-size: 13.5px; color: #3C2F23; }
        .search-suggestion-row:hover { background: #F6EFE6; }
        .search-empty-note { padding: 8px 4px; font-size: 12px; color: #9a8f80; }

        /* Amenity chips (built in welcome.js's openSpaModal) - same rationale
           as svc-card/slot-btn above: JS template strings aren't scanned by
           Tailwind, so arbitrary-value dark: classes there would be silently
           dropped. Plain CSS class instead. */
        .amenity-chip { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 12px; background: rgba(246, 239, 230, 0.7); border: 1px solid rgba(139, 115, 85, 0.10); }
        .amenity-chip-icon { display: flex; align-items: center; justify-content: center; flex-shrink: 0; width: 28px; height: 28px; border-radius: 8px; background: #fff; border: 1px solid rgba(0,0,0,0.05); color: #8B7355; font-size: 11px; }
        .amenity-chip-label { font-size: 12px; font-weight: 500; color: #3C2F23; }

        /* Booking modal step indicator - welcome.js's showBookingStep()
           overwrites circle/label/bar className wholesale on every step
           change, so dark: utility classes here would be wiped on the very
           first click. Plain CSS with state classes survives that. */
        .step-circle { display: flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; font-size: 0.75rem; font-weight: 600; border-radius: 9999px; transition: background-color .15s ease, color .15s ease; }
        .step-circle.is-pending { color: #9ca3af; background: #e5e7eb; }
        .step-circle.is-active, .step-circle.is-done { color: #fff; background: #8B7355; }
        .step-label { margin-left: 0.5rem; font-size: 0.75rem; transition: color .15s ease; }
        .step-label.is-pending { font-weight: 500; color: #9ca3af; }
        .step-label.is-active { font-weight: 600; color: #3C2F23; }
        .step-bar { width: 2.5rem; height: 2px; margin: 0 0.75rem; border-radius: 9999px; transition: background-color .15s ease; }
        @media (min-width: 640px) { .step-bar { width: 4rem; } }
        .step-bar.is-pending { background: #e5e7eb; }
        .step-bar.is-done { background: #8B7355; }

        /* Dark mode (prefers-color-scheme) for the hero search dropdown.
           Plain CSS, not dark: utilities - no .dark class is ever added. */
        @media (prefers-color-scheme: dark) {
            .search-dropdown { background: #1f2937; box-shadow: 0 16px 40px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.08); }
            .search-dropdown-label { color: #9ca3af; }
            .search-chip { background: #374151; color: #e5e7eb; border-color: rgba(255,255,255,0.08); }
            .search-chip:hover { background: #4b5563; }
            .search-suggestion-row { color: #e5e7eb; }
            .search-suggestion-row:hover { background: #374151; }
            .search-empty-note { color: #9ca3af; }

            .amenity-chip { background: rgba(55, 65, 81, 0.5); border-color: rgba(196, 169, 125, 0.15); }
            .amenity-chip-icon { background: #1f2937; border-color: rgba(255,255,255,0.08); color: #C4A97D; }
            .amenity-chip-label { color: #f3f4f6; }

            .svc-card-thumb { background: #374151; border-color: rgba(255,255,255,0.06); }
            .svc-card:hover { background-color: rgba(55, 65, 81, 0.5); }
            .svc-card.is-selected { border-left-color: #C4A97D; background-color: rgba(55, 65, 81, 0.7); }
            .svc-card:focus-within { outline-color: #C4A97D; }
            .svc-card-name { color: #f3f4f6; }
            .svc-card-badge { color: #C4A97D; background: #374151; border-color: rgba(255,255,255,0.08); }
            .svc-card-price { color: #C4A97D; }
            .svc-card-desc { color: #9ca3af; }
            .svc-card-meta { color: #C4A97D; }
            .svc-section-header { color: #9ca3af; background: #1f2937; }

            .svc-filter-tab { color: #9ca3af; }
            .svc-filter-tab.is-active { background: #8B7355; color: #fff; }

            .slot-btn { color: #f3f4f6; background: #374151; border-color: rgba(255,255,255,0.08); }
            .slot-btn:hover:not(:disabled) { border-color: #C4A97D; background-color: rgba(55, 65, 81, 0.8); }
            .slot-btn.is-selected { border-color: #C4A97D; background-color: #8B7355; color: #fff; }
            .slot-btn:disabled { background-color: #1f2937; color: #4b5563; border-color: #374151; }
            .slot-btn.is-past-closing:disabled { border-color: #4b5563; }

            .step-circle.is-pending { background: #374151; color: #9ca3af; }
            .step-circle.is-active, .step-circle.is-done { background: #8B7355; color: #fff; }
            .step-label.is-active { color: #f3f4f6; }
            .step-label.is-pending { color: #6b7280; }
            .step-bar.is-pending { background: #374151; }
            .step-bar.is-done { background: #C4A97D; }
        }
    </style>
    <div id="bookingModal" class="fixed inset-0 z-[110] hidden">
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" data-close-booking-modal></div>
        <div class="relative mx-auto w-[92%] max-w-2xl mt-10 sm:mt-16">
            <div class="overflow-hidden bg-white shadow-2xl dark:bg-gray-800 rounded-3xl ring-1 ring-black/10 dark:ring-white/10">
                <div class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                    <div>
                        <h3 class="text-lg font-semibold text-[#3C2F23] dark:text-white">Make a Reservation</h3>
                        <p id="bookingSpaMeta" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Spa • Branch</p>
                    </div>
                    <button type="button"
                            class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10"
                            data-close-booking-modal aria-label="Close">
                        <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                    </button>
                </div>
                {{-- Step indicator — plain CSS state classes (.is-pending/.is-active/.is-done),
                     since welcome.js's showBookingStep() rewrites className wholesale on every
                     step change and isn't scanned by Tailwind's content config. --}}
                @auth
                <div class="px-6 pt-5">
                    <div class="flex items-center justify-center overflow-x-auto">
                        <div class="flex items-center min-w-max">
                            <div class="flex items-center">
                                <div data-step-circle="1" class="step-circle is-active">1</div>
                                <span data-step-label="1" class="step-label is-active">Service</span>
                            </div>
                            <div data-step-bar="1" class="step-bar is-pending"></div>
                            <div class="flex items-center">
                                <div data-step-circle="2" class="step-circle is-pending">2</div>
                                <span data-step-label="2" class="step-label is-pending">Date &amp; Time</span>
                            </div>
                            <div data-step-bar="2" class="step-bar is-pending"></div>
                            <div class="flex items-center">
                                <div data-step-circle="3" class="step-circle is-pending">3</div>
                                <span data-step-label="3" class="step-label is-pending">Confirm</span>
                            </div>
                        </div>
                    </div>
                </div>
                @endauth

                <div class="overflow-y-auto max-h-[65vh] p-6">
                    @auth
                        <form method="POST" action="{{ route('bookings.online.checkout') }}" id="bookingForm" class="space-y-4">
                            @csrf
                            <input type="hidden" name="spa_id" id="bookingSpaIdInput">
                            <input type="hidden" name="branch_id" id="bookingBranchIdInput">

                            {{-- ============ STEP 1 — SERVICE ============ --}}
                            <div data-booking-step="1">
                                <div>
                                    <div class="flex items-center justify-between gap-2">
                                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Treatment / Package</label>
                                        <div class="inline-flex gap-1 p-1 bg-gray-100 rounded-lg dark:bg-gray-700" id="bookingServiceFilterTabs">
                                            <button type="button" class="svc-filter-tab is-active" data-service-filter="all">All</button>
                                            <button type="button" class="svc-filter-tab" data-service-filter="treatment">Treatments</button>
                                            <button type="button" class="svc-filter-tab" data-service-filter="package">Packages</button>
                                        </div>
                                    </div>
                                    <div class="relative mt-2">
                                        <i class="absolute text-xs text-gray-300 -translate-y-1/2 pointer-events-none dark:text-gray-500 fa-solid fa-magnifying-glass left-3 top-1/2"></i>
                                        <input type="text" id="bookingServiceSearch" placeholder="Search services…" autocomplete="off"
                                            class="w-full py-2 pl-9 pr-3 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400 ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                    </div>
                                    <div id="bookingServiceList"
                                         class="mt-2 overflow-y-auto bg-white border divide-y dark:bg-gray-800 max-h-60 rounded-xl border-black/10 dark:border-white/10 ring-1 ring-black/5 dark:ring-white/10 divide-black/5 dark:divide-white/10">
                                        <p class="px-4 py-6 text-sm text-center text-gray-400 dark:text-gray-500">Select a spa to see its services.</p>
                                    </div>
                                    <p id="bookingTreatmentError" class="hidden mt-1 text-[11px] text-red-500 dark:text-red-400">

                                        <i class="fa-solid fa-circle-exclamation"></i>
                                        Please select a treatment or package.
                                    </p>
                                </div>

                                <div class="mt-4">
                                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Service Type</label>
                                    <select name="service_type" id="bookingServiceType"
                                        class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40 disabled:bg-gray-100 dark:disabled:bg-gray-800 disabled:text-gray-400 dark:disabled:text-gray-500 disabled:cursor-not-allowed disabled:opacity-80">
                                        <option value="">Select service type</option>
                                    </select>
                                    <p id="bookingServiceTypeHint" class="mt-1 text-[11px] text-gray-500 dark:text-gray-400"></p>
                                </div>

                                <div id="addressWrapper" class="hidden mt-4">
                                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                        Home Address <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="customer_address" id="bookingAddressInput"
                                        class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40"
                                        placeholder="Enter your full address">
                                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Required for home service bookings.</p>
                                </div>
                            </div>

                            {{-- ============ STEP 2 — DATE & TIME ============ --}}
                            <div data-booking-step="2" class="hidden">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Appointment Date</label>
                                    <input type="date" name="appointment_date" id="bookingDateInput"
                                        class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                </div>

                                <div class="mt-4">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Available Times</label>
                                        <span id="bookingSlotsLoading" class="hidden text-[11px] text-gray-400 dark:text-gray-500">
                                            <i class="fa-solid fa-spinner fa-spin"></i> Checking availability…
                                        </span>
                                    </div>
                                    <div id="bookingSlotGrid" class="grid grid-cols-3 gap-2 mt-2 sm:grid-cols-4">
                                        <p class="col-span-3 py-6 text-sm text-center text-gray-400 dark:text-gray-500 sm:col-span-4">Pick a date to see available times.</p>
                                    </div>
                                    <div id="bookingSlotLegend" class="hidden flex-wrap gap-3 mt-3 text-[11px] text-gray-500 dark:text-gray-400">
                                        <span class="flex items-center gap-1.5"><span class="inline-block border border-gray-200 dark:border-gray-600 rounded w-2.5 h-2.5 bg-gray-50 dark:bg-gray-800"></span> Fully booked</span>
                                        <span class="flex items-center gap-1.5"><span class="inline-block border border-gray-300 dark:border-gray-500 border-dashed rounded w-2.5 h-2.5"></span> Not enough time before closing</span>
                                    </div>
                                    <input type="hidden" name="start_time" id="bookingTimeInput">
                                    <p id="bookingTimeError" class="hidden mt-2 text-[11px] text-red-500 dark:text-red-400">
                                        <i class="fa-solid fa-circle-exclamation"></i>
                                        <span id="bookingTimeErrorText">Please select an available time.</span>
                                    </p>
                                </div>
                            </div>

                            {{-- ============ STEP 3 — YOUR DETAILS & CONFIRM ============ --}}
                            <div data-booking-step="3" class="hidden">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Full Name</label>
                                        <input type="text" name="customer_name" id="bookingCustomerName"
                                            value="{{ auth()->user()->name }}" readonly
                                            class="w-full mt-1 text-gray-700 bg-gray-100 dark:text-gray-300 dark:bg-gray-700 rounded-xl border-black/10 dark:border-white/10 ring-1 ring-black/5 dark:ring-white/10">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Email</label>
                                        <input type="email" name="customer_email" id="bookingCustomerEmail"
                                            value="{{ auth()->user()->email }}" readonly
                                            class="w-full mt-1 text-gray-700 bg-gray-100 dark:text-gray-300 dark:bg-gray-700 rounded-xl border-black/10 dark:border-white/10 ring-1 ring-black/5 dark:ring-white/10">
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Phone Number</label>
                                    <input type="text" name="customer_phone" id="bookingCustomerPhone"
                                        value="{{ auth()->user()->phone }}"
                                        data-default-phone="{{ auth()->user()->phone }}"
                                        placeholder="09xxxxxxxxx" maxlength="11"
                                        class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">We'll use this to reach you about your booking and for GCash/Maya payment.</p>
                                </div>

                                <div class="p-4 mt-4 space-y-2 text-sm border border-[#E8DDD0] dark:border-gray-700 rounded-xl bg-[#FDFAF6] dark:bg-gray-900/40">
                                    <p class="text-[10px] font-semibold tracking-wider text-gray-400 uppercase">Booking Summary</p>
                                    <div class="flex justify-between gap-3">
                                        <span class="text-gray-500 dark:text-gray-400">Service</span>
                                        <span id="recapService" class="font-medium text-[#3C2F23] dark:text-white text-right"></span>
                                    </div>
                                    <div class="flex justify-between gap-3">
                                        <span class="text-gray-500 dark:text-gray-400">Type</span>
                                        <span id="recapServiceType" class="font-medium text-[#3C2F23] dark:text-white"></span>
                                    </div>
                                    <div id="recapAddressRow" class="justify-between hidden gap-3">
                                        <span class="text-gray-500 dark:text-gray-400">Address</span>
                                        <span id="recapAddress" class="font-medium text-[#3C2F23] dark:text-white text-right"></span>
                                    </div>
                                    <div class="flex justify-between gap-3">
                                        <span class="text-gray-500 dark:text-gray-400">Date &amp; Time</span>
                                        <span id="recapDateTime" class="font-medium text-[#3C2F23] dark:text-white text-right"></span>
                                    </div>
                                    <div class="flex justify-between gap-3 pt-2 mt-2 border-t border-black/5 dark:border-white/10">
                                        <span class="text-gray-500 dark:text-gray-400">Total Service Price</span>
                                        <span id="recapTotalPrice" class="font-medium text-[#3C2F23] dark:text-white"></span>
                                    </div>
                                    <div class="flex justify-between gap-3">
                                        <span class="text-gray-500 dark:text-gray-400">Reservation fee (20%)</span>
                                        <span id="recapDownpayment" class="font-semibold text-[#6F5430] dark:text-[#C4A97D]"></span>
                                    </div>
                                </div>

                                <!-- Terms & Agreements -->
                                <div class="p-4 mt-4 border border-[#E8DDD0] dark:border-gray-700 rounded-xl bg-[#FDFAF6] dark:bg-gray-900/40 space-y-3">
                                    <label class="flex items-center gap-3 pt-1 cursor-pointer group">
                                        <input type="checkbox" id="bookingTermsCheckbox" name="terms_agreed" value="1"
                                            class="w-4 h-4 rounded accent-[#8B7355] cursor-pointer flex-shrink-0">
                                        <span class="text-xs font-medium text-gray-700 dark:text-gray-300 group-hover:text-[#6F5430] dark:group-hover:text-[#C4A97D] transition">
                                            I have read and agree to the
                                            <button type="button" onclick="openTermsModal()"
                                                class="text-[#8B7355] dark:text-[#C4A97D] underline underline-offset-2 hover:text-[#6F5430] dark:hover:text-white transition font-semibold">
                                                terms and conditions
                                            </button>.
                                        </span>
                                    </label>
                                </div>
                            </div>

                            {{-- ============ STEP NAVIGATION ============ --}}
                            <div class="flex items-center justify-between pt-2">
                                <button type="button" id="bookingBackBtn"
                                        class="hidden px-5 py-2.5 text-sm font-semibold text-gray-600 dark:text-gray-300 transition rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="mr-1 fa-solid fa-arrow-left"></i> Back
                                </button>
                                <div class="flex-1"></div>
                                <button type="button" id="bookingNextBtn"
                                        class="booking-btn text-white px-6 py-2.5 rounded-xl text-sm font-semibold shadow-md hover:shadow-lg transition active:translate-y-0.5">
                                    Continue <i class="ml-1 fa-solid fa-arrow-right"></i>
                                </button>
                                <button type="submit" id="bookingSubmitBtn"
                                        class="hidden booking-btn text-white px-6 py-2.5 rounded-xl text-sm font-semibold shadow-md hover:shadow-lg transition active:translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:translate-y-0">
                                    Reserve An Appointment
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="p-4 rounded-2xl bg-[#F6EFE6]/70 dark:bg-gray-700/50 ring-1 ring-black/5 dark:ring-white/10">
                            <p class="text-sm text-gray-700 dark:text-gray-300">Please log in to book an appointment.</p>
                            <a href="{{ route('login') }}"
                            class="block mt-4 text-center booking-btn text-white py-3 rounded-xl text-sm font-semibold shadow-md hover:shadow-lg transition active:translate-y-0.5">
                                Login to Continue
                            </a>
                        </div>
                    @endauth
                </div>
            </div>
            <div class="h-10"></div>
        </div>
    </div>

    <!-- ================= JOB APPLICATION MODAL ================= -->
    <div id="applicationModal" class="fixed inset-0 z-[115] hidden">
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeApplicationModal()"></div>
        <div class="relative mx-auto w-[92%] max-w-3xl mt-8 mb-8">
            <div class="overflow-hidden bg-white dark:bg-gray-800 shadow-2xl rounded-3xl ring-1 ring-black/10 dark:ring-white/10 flex flex-col max-h-[90vh]">
                <div class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                    <div>
                        <h3 class="text-lg font-semibold text-[#3C2F23] dark:text-white">Apply for a Position</h3>
                        <p id="applicationSpaMeta" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Spa Name</p>
                    </div>
                    <button type="button" onclick="closeApplicationModal()"
                        class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10">
                        <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto">
                    <form id="applicationForm" method="POST" class="space-y-6" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="spa_id" id="applicationSpaId">
                        <input type="hidden" name="branch_id" id="applicationBranchId">
                        <input type="hidden" name="source" value="website">

                        {{-- PERSONAL INFORMATION --}}
                        <div>
                            <h4 class="flex items-center gap-2 mb-3 text-xs font-bold tracking-widest text-[#8B7355] dark:text-[#C4A97D] uppercase">
                                <i class="fa-solid fa-user"></i> Personal Information
                            </h4>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Full Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="full_name" required
                                        class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                </div>
                                <div>
                                    <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Email <span class="text-red-500">*</span></label>
                                    <input type="email" name="email" required
                                        class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                </div>
                                <div>
                                    <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Phone <span class="text-red-500">*</span></label>
                                    <input type="text" name="phone" required placeholder="09xxxxxxxxx"
                                        class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                </div>
                                <div>
                                    <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Gender</label>
                                    <select name="gender" class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                        <option value="">Select</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Date of Birth</label>
                                    <input type="date" name="date_of_birth"
                                        class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                </div>
                                <div>
                                    <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Civil Status</label>
                                    <select name="civil_status" class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                        <option value="">Select</option>
                                        <option value="single">Single</option>
                                        <option value="married">Married</option>
                                        <option value="widowed">Widowed</option>
                                        <option value="separated">Separated</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Address <span class="text-red-500">*</span></label>
                                    <input type="text" name="address" required placeholder="Street, Barangay, City"
                                        class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                </div>
                            </div>
                        </div>

                        <hr class="border-[#E8DDD0] dark:border-gray-700">

                        {{-- POSITION DETAILS --}}
                        <div>
                            <h4 class="flex items-center gap-2 mb-3 text-xs font-bold tracking-widest text-[#8B7355] dark:text-[#C4A97D] uppercase">
                                <i class="fa-solid fa-briefcase"></i> Position Details
                            </h4>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Applying For <span class="text-red-500">*</span></label>
                                    <select name="position_applied" required class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                        <option value="">Select</option>
                                        <option value="therapist">Therapist</option>
                                        <option value="receptionist">Receptionist</option>
                                        <option value="manager">Manager</option>
                                        <option value="hr">HR</option>
                                        <option value="finance">Finance</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Shift Availability</label>
                                    <select name="availability" class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                        <option value="">Select</option>
                                        <option value="full_time">Full Time</option>
                                        <option value="part_time">Part Time</option>
                                        <option value="weekdays">Weekdays Only</option>
                                        <option value="weekends">Weekends Only</option>
                                        <option value="shifting">Shifting</option>
                                        <option value="flexible">Flexible</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Expected Start Date</label>
                                    <input type="date" name="expected_start_date"
                                        class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                </div>
                                <div>
                                    <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Educational Attainment</label>
                                    <select name="education" class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                        <option value="">Select</option>
                                        <option value="high_school">High School</option>
                                        <option value="vocational">Vocational</option>
                                        <option value="undergraduate">Undergraduate</option>
                                        <option value="college">College Graduate</option>
                                        <option value="postgrad">Post Graduate</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">
                                        Resume / CV <span class="text-xs font-normal text-gray-400">(optional for walk-ins)</span>
                                    </label>
                                    <input type="file" name="resume" accept=".pdf,.doc,.docx"
                                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
                                </div>
                            </div>
                        </div>

                        <hr class="border-[#E8DDD0] dark:border-gray-700">

                        {{-- EMERGENCY CONTACT --}}
                        <div>
                            <h4 class="flex items-center gap-2 mb-3 text-xs font-bold tracking-widest text-[#8B7355] dark:text-[#C4A97D] uppercase">
                                <i class="fa-solid fa-phone-volume"></i> Emergency Contact
                            </h4>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Contact Person</label>
                                    <input type="text" name="emergency_contact_name"
                                        class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                </div>
                                <div>
                                    <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Relationship</label>
                                    <input type="text" name="emergency_contact_relation" placeholder="e.g. Mother"
                                        class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                </div>
                                <div>
                                    <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Contact Number</label>
                                    <input type="text" name="emergency_contact_phone" placeholder="09xxxxxxxxx"
                                        class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                </div>
                            </div>
                        </div>

                        {{-- RESUME UPLOAD STATUS --}}
                        <div id="resumeUploadStatus" class="hidden p-3 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50 ring-1 ring-black/5 dark:ring-white/10">
                            <div class="flex items-center gap-3">
                                <i class="fa-solid fa-file-pdf text-[#8B7355] dark:text-[#C4A97D] text-lg"></i>
                                <div class="flex-1">
                                    <p id="resumeFileName" class="text-sm font-medium text-[#3C2F23] dark:text-white"></p>
                                    <p id="resumeFileSize" class="text-xs text-gray-500 dark:text-gray-400"></p>
                                </div>
                                <button type="button" onclick="clearResumeUpload()"
                                    class="text-red-500 transition hover:text-red-700">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </div>

                        <div id="applicationError" class="hidden p-3 text-sm text-red-600 dark:text-red-300 rounded-xl bg-red-50 dark:bg-red-900/20 ring-1 ring-red-200 dark:ring-red-800">
                            <i class="mr-1 fa-solid fa-circle-exclamation"></i>
                            <span id="applicationErrorText"></span>
                        </div>

                        <button type="submit" id="applicationSubmitBtn"
                            class="w-full booking-btn text-white py-3 rounded-xl text-sm font-semibold shadow-md hover:shadow-lg transition active:translate-y-0.5">
                            <i class="mr-2 fa-solid fa-paper-plane"></i>
                            Submit Application
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= BUSINESS REGISTER INFO MODAL ================= -->
    @role('customer')
    <div id="businessInfoModal" class="fixed inset-0 z-[150] hidden">
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeBusinessInfo()"></div>
        <div class="relative mx-auto w-[92%] max-w-md mt-24 sm:mt-32">
            <div class="overflow-hidden bg-white shadow-2xl dark:bg-gray-800 rounded-3xl ring-1 ring-black/10 dark:ring-white/10">
                <div class="p-6 text-center">
                    <div class="flex items-center justify-center w-14 h-14 mx-auto rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                        <i class="fa-solid fa-store text-2xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-[#3C2F23] dark:text-white">Business Account Required</h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                        You're currently logged in as a customer. Listing a spa requires a separate business account.
                        Please log out first, then register as a business partner.
                    </p>
                </div>
                <div class="px-6 pb-6 space-y-2">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="w-full py-3 text-sm font-semibold text-white transition shadow-md rounded-xl booking-btn hover:shadow-lg">
                            <i class="mr-2 fa-solid fa-right-from-bracket"></i>
                            Log Out & Register as Business
                        </button>
                    </form>
                    <button type="button" onclick="closeBusinessInfo()"
                        class="w-full py-3 rounded-xl text-sm font-semibold text-[#8B7355] dark:text-[#C4A97D] border border-[#8B7355] dark:border-[#C4A97D] hover:bg-[#F6EFE6] dark:hover:bg-gray-700 transition">
                        Maybe Later
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endrole

    <!-- ================= TERMS MODAL ================= -->
    <div id="termsModal" class="fixed inset-0 z-[120] hidden">
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeTermsModal()"></div>
        <div class="relative mx-auto w-[92%] max-w-lg mt-10 sm:mt-16">
            <div class="overflow-hidden bg-white shadow-2xl dark:bg-gray-800 rounded-3xl ring-1 ring-black/10 dark:ring-white/10">
                <div class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                    <h3 class="text-lg font-semibold text-[#3C2F23] dark:text-white">Terms & Conditions</h3>
                    <button type="button" onclick="closeTermsModal()"
                        class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10">
                        <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="overflow-y-auto max-h-[60vh] p-6">
                    <ul class="space-y-4 text-xs leading-relaxed text-gray-600 dark:text-gray-400">
                        <li class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/50 dark:bg-gray-700/40">
                            <i class="fa-solid fa-circle-check text-[#8B7355] dark:text-[#C4A97D] mt-0.5 flex-shrink-0 text-sm"></i>
                            <div>
                                <p class="font-semibold text-[#3C2F23] dark:text-white mb-1">Downpayment</p>
                                <p>A 20% non-refundable downpayment is required to confirm your reservation. The remaining 80% is payable at the spa on the day of your appointment.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/50 dark:bg-gray-700/40">
                            <i class="fa-solid fa-circle-check text-[#8B7355] dark:text-[#C4A97D] mt-0.5 flex-shrink-0 text-sm"></i>
                            <div>
                                <p class="font-semibold text-[#3C2F23] dark:text-white mb-1">Cancellation</p>
                                <p>Cancellations must be made at least 24 hours before your appointment. The 20% downpayment is non-refundable regardless of cancellation timing.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/50 dark:bg-gray-700/40">
                            <i class="fa-solid fa-circle-check text-[#8B7355] dark:text-[#C4A97D] mt-0.5 flex-shrink-0 text-sm"></i>
                            <div>
                                <p class="font-semibold text-[#3C2F23] dark:text-white mb-1">No-Show Policy</p>
                                <p>Failure to arrive without prior notice will forfeit your downpayment and may result in restricted future bookings on this platform.</p>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="px-6 pt-2 pb-6">
                    <button type="button" onclick="closeTermsModal()"
                        class="w-full py-3 rounded-xl text-sm font-semibold text-white booking-btn shadow-md hover:shadow-lg transition active:translate-y-0.5">
                        I Understand
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= RATING MODAL ================= -->
    <div id="ratingModal" class="fixed inset-0 z-[140] hidden">
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeRatingModal()"></div>
        <div class="relative mx-auto w-[92%] max-w-lg mt-10 sm:mt-16">
            <div class="overflow-hidden bg-white shadow-2xl dark:bg-gray-800 rounded-3xl ring-1 ring-black/10 dark:ring-white/10">
                <div class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                    <div>
                        <h3 class="text-lg font-semibold text-[#3C2F23] dark:text-white">Rate Your Experience</h3>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Help us improve our service quality</p>
                    </div>
                    <button type="button" onclick="closeRatingModal()"
                        class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10">
                        <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="overflow-y-auto max-h-[65vh] p-6 space-y-6">
                    <input type="hidden" id="ratingBookingId">
                    <input type="hidden" id="selectedRating" value="0">
                    <input type="hidden" id="selectedSpaRating" value="0">

                    <!-- ── SPA SECTION ── -->
                    <div class="p-4 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50 ring-1 ring-black/5 dark:ring-white/10 space-y-4">
                        <div>
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Spa</p>
                            <p id="ratingSpaName" class="mt-1 text-base font-semibold text-[#3C2F23] dark:text-white"></p>
                            <div class="flex items-center gap-1 mt-1">
                                <i class="fa-solid fa-location-dot text-[#8B7355] dark:text-[#C4A97D] text-[10px]"></i>
                                <p id="ratingSpaBranchLocation" class="text-xs text-gray-500 dark:text-gray-400"></p>
                            </div>
                        </div>

                        <div>
                            <label class="block mb-2 text-xs font-semibold text-gray-600 dark:text-gray-300">
                                Overall Spa Rating <span class="text-red-500">*</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <div class="flex gap-1">
                                    {!! implode('', array_map(fn($i) =>
                                        '<button type="button" onclick="setSpaRating('.$i.')"
                                            class="transition focus:outline-none hover:scale-110">
                                            <i id="spa-star-'.$i.'" class="text-2xl text-gray-300 dark:text-gray-600 fa-solid fa-star"></i>
                                        </button>', range(1, 5))) !!}
                                </div>
                                <span id="spaRatingLabel" class="ml-2 text-sm text-gray-500 dark:text-gray-400">Select rating</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Comment (optional)</label>
                            <textarea id="spaComment" rows="2" maxlength="500"
                                placeholder="e.g., Clean facilities, relaxing ambiance..."
                                class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40 text-sm resize-none"></textarea>
                            <p class="mt-1 text-right text-[10px] text-gray-400 dark:text-gray-500"><span id="spaCommentCount">0</span>/500</p>
                        </div>
                    </div>

                    <hr class="border-[#E8DDD0] dark:border-gray-700">

                    <!-- ── THERAPIST SECTION ── -->
                    <div class="p-4 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50 ring-1 ring-black/5 dark:ring-white/10 space-y-4">
                        <div>
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Therapist</p>
                            <p id="ratingTherapistName" class="mt-1 text-base font-semibold text-[#3C2F23] dark:text-white"></p>
                            <div class="flex items-center gap-1 mt-1">
                                <i class="fa-solid fa-location-dot text-[#8B7355] dark:text-[#C4A97D] text-[10px]"></i>
                                <p id="ratingBranchLocation" class="text-xs text-gray-500 dark:text-gray-400"></p>
                            </div>
                        </div>

                        <div>
                            <label class="block mb-2 text-xs font-semibold text-gray-600 dark:text-gray-300">
                                Your Rating <span class="text-red-500">*</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <div class="flex gap-1">
                                    {!! implode('', array_map(fn($i) =>
                                        '<button type="button" onclick="setRating('.$i.')"
                                            class="transition focus:outline-none hover:scale-110">
                                            <i id="star-'.$i.'" class="text-2xl text-gray-300 dark:text-gray-600 fa-solid fa-star"></i>
                                        </button>', range(1, 5))) !!}
                                </div>
                                <span id="ratingLabel" class="ml-2 text-sm text-gray-500 dark:text-gray-400">Select rating</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">What went well?</label>
                            <textarea id="ratingComment" rows="2" maxlength="500"
                                placeholder="e.g., Great massage, very professional..."
                                class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40 text-sm resize-none"></textarea>
                            <p class="mt-1 text-right text-[10px] text-gray-400 dark:text-gray-500"><span id="ratingCommentCount">0</span>/500</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Suggestions for improvement (optional)</label>
                            <textarea id="ratingFeedback" rows="2" maxlength="1000"
                                placeholder="e.g., Could be more attentive, room was cold..."
                                class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40 text-sm resize-none"></textarea>
                            <p class="mt-1 text-right text-[10px] text-gray-400 dark:text-gray-500"><span id="ratingFeedbackCount">0</span>/1000</p>
                        </div>
                    </div>
                </div>
                <div class="px-6 pb-6 space-y-2">
                    <button type="button" id="ratingSubmitBtn" onclick="submitRating()"
                        class="w-full py-3 rounded-xl text-sm font-semibold text-white booking-btn shadow-md hover:shadow-lg transition active:translate-y-0.5">
                        <i class="mr-2 fa-solid fa-paper-plane"></i>
                        Submit Rating
                    </button>
                    <button type="button" onclick="closeRatingModal()"
                        class="w-full py-3 rounded-xl text-sm font-semibold text-[#8B7355] dark:text-[#C4A97D] border border-[#8B7355] dark:border-[#C4A97D] hover:bg-[#F6EFE6] dark:hover:bg-gray-700 transition">
                        Maybe Later
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= RATE REMINDER MODAL ================= -->
    @auth
    @role('customer')
    <div id="rateReminderModal" class="fixed inset-0 z-[135] hidden">
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="dismissRateReminder()"></div>
        <div class="relative mx-auto w-[92%] max-w-lg mt-10 sm:mt-16">
            <div class="overflow-hidden bg-white shadow-2xl dark:bg-gray-800 rounded-3xl ring-1 ring-black/10 dark:ring-white/10">
                <div class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 bg-[#F6EFE6] dark:bg-gray-700 rounded-2xl ring-1 ring-black/5 dark:ring-white/10">
                            <i class="fa-solid fa-star text-[#D2A85B]"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-[#3C2F23] dark:text-white">How was your visit?</h3>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">You have unrated appointments</p>
                        </div>
                    </div>
                    <button type="button" onclick="dismissRateReminder()"
                        class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10">
                        <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div id="rateReminderContent" class="overflow-y-auto max-h-[55vh] p-6 space-y-3">
                    {{-- filled by JS --}}
                </div>

                <div class="px-6 pt-2 pb-6">
                    <button type="button" onclick="dismissRateReminder()"
                        class="w-full py-3 rounded-xl text-sm font-semibold text-[#8B7355] dark:text-[#C4A97D] border border-[#8B7355] dark:border-[#C4A97D] hover:bg-[#F6EFE6] dark:hover:bg-gray-700 transition">
                        Maybe Later
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endrole
    @endauth

    <!-- ================= HOW IT WORKS ================= -->
    <section class="bg-[#EFE3D6] dark:bg-gray-800 py-20">
        <div class="px-6 mx-auto max-w-7xl">
            <div class="text-center">
                <div class="flex items-center justify-center gap-6">
                    <span class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                    <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] dark:text-white font-semibold">How It Works</h2>
                    <span class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                </div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Book in minutes with a simple flow.</p>
            </div>
            <div class="grid gap-6 mt-12 md:grid-cols-3">
                <div class="p-8 text-center transition shadow-sm bg-white/70 dark:bg-gray-900/40 rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-lg">
                    <div class="flex items-center justify-center w-14 h-14 mx-auto rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                        <i class="fa-solid fa-location-dot text-2xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                    </div>
                    <h3 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">Find Your Spa</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Browse verified spas near you.</p>
                </div>
                <div class="p-8 text-center transition shadow-sm bg-white/70 dark:bg-gray-900/40 rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-lg">
                    <div class="flex items-center justify-center w-14 h-14 mx-auto rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                        <i class="fa-solid fa-list-check text-2xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                    </div>
                    <h3 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">Choose Service</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Select the service you want.</p>
                </div>
                <div class="p-8 text-center transition shadow-sm bg-white/70 dark:bg-gray-900/40 rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-lg">
                    <div class="flex items-center justify-center w-14 h-14 mx-auto rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                        <i class="fa-solid fa-spa text-2xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                    </div>
                    <h3 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">Relax & Enjoy</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Arrive and unwind.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= WHY BOOK ================= -->
    <section class="py-20">
        <div class="px-6 mx-auto max-w-7xl">
            <div class="text-center">
                <div class="flex items-center justify-center gap-6">
                    <span class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                    <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] dark:text-white font-semibold">Why Book With Us</h2>
                    <span class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                </div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Built for convenience and trust.</p>
            </div>
            <div class="grid gap-6 mt-12 md:grid-cols-4">
                <div class="transition bg-white shadow-sm dark:bg-gray-800 p-7 rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                        <i class="fa-solid fa-check text-xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                    </div>
                    <h4 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">Verified Spas</h4>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Only trusted listings appear on the platform.</p>
                </div>
                <div class="transition bg-white shadow-sm dark:bg-gray-800 p-7 rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                        <i class="fa-solid fa-calendar-check text-xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                    </div>
                    <h4 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">Easy Booking</h4>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Reserve quickly with clear scheduling.</p>
                </div>
                <div class="transition bg-white shadow-sm dark:bg-gray-800 p-7 rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                        <i class="fa-solid fa-user-nurse text-xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                    </div>
                    <h4 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">Expert Therapists</h4>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Quality care from professional practitioners.</p>
                </div>
                <div class="transition bg-white shadow-sm dark:bg-gray-800 p-7 rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                        <i class="fa-solid fa-lock text-xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                    </div>
                    <h4 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">Secure Payments</h4>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Safe checkout experience and privacy-focused flow.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= CTA ================= -->
    <section class="py-16">
        <div class="px-6 mx-auto max-w-7xl">
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-[#6F5430] to-[#8B7355] p-10 md:p-14 text-center text-white shadow-2xl">
                <div class="absolute inset-0 opacity-20"
                     style="background-image: radial-gradient(circle at 20% 20%, rgba(255,255,255,.35) 0, transparent 35%),
                                            radial-gradient(circle at 80% 30%, rgba(255,255,255,.25) 0, transparent 40%),
                                            radial-gradient(circle at 50% 90%, rgba(255,255,255,.18) 0, transparent 45%);">
                </div>
                <div class="relative">
                    <h2 class="text-3xl md:text-4xl font-['Playfair_Display'] font-semibold">
                        Own a Spa? List Your Business with Us!
                    </h2>
                    <p class="mt-3 text-sm text-white/90 md:text-base">
                        Reach more customers and manage bookings easily.
                    </p>
                    @auth
                        @role('customer')
                            <button type="button" onclick="openBusinessInfo()"
                                class="inline-flex items-center justify-center gap-2 mt-7 bg-white text-[#6F5430] px-8 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition active:translate-y-0.5">
                                <i class="fa-solid fa-arrow-right"></i>
                                Get Started
                            </button>
                        @else
                            <a href="{{ route('register.business') }}"
                                class="inline-flex items-center justify-center gap-2 mt-7 bg-white text-[#6F5430] px-8 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition active:translate-y-0.5">
                                <i class="fa-solid fa-arrow-right"></i>
                                Get Started
                            </a>
                        @endrole
                    @else
                        <a href="{{ route('register.business') }}"
                            class="inline-flex items-center justify-center gap-2 mt-7 bg-white text-[#6F5430] px-8 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition active:translate-y-0.5">
                            <i class="fa-solid fa-arrow-right"></i>
                            Get Started
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </section>
</main>

<!-- ================= FOOTER ================= -->
<footer class="relative mt-16">
    <div class="absolute inset-0">
        <img src="{{ asset('images/footers.png') }}" class="object-cover w-full h-full" alt="Footer">
        <div class="absolute inset-0 bg-black/65"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-black/30 to-black/70"></div>
    </div>
    <div class="relative px-6 mx-auto text-white py-14 max-w-7xl">
        <div class="grid gap-10 md:grid-cols-12">
            <div class="md:col-span-5">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/1.png') }}" alt="Levictas" class="h-10 rounded-md ring-1 ring-white/10">
                    <div>
                        <h3 class="font-['Playfair_Display'] text-2xl font-semibold">Levictas</h3>
                        <p class="mt-1 text-xs tracking-[0.18em] uppercase text-white/70">Spa & Wellness Sanctuary</p>
                    </div>
                </div>
                <p class="max-w-md mt-5 text-sm text-white/75">
                    Find trusted spas and reserve appointments with ease — your relaxation journey starts here.
                </p>
                <div class="flex items-center gap-3 mt-6">
                    <a href="#" class="inline-flex items-center justify-center w-10 h-10 transition rounded-xl bg-white/10 ring-1 ring-white/10 hover:bg-white/15">
                        <i class="fa-brands fa-facebook-f"></i>
                    </a>
                    <a href="#" class="inline-flex items-center justify-center w-10 h-10 transition rounded-xl bg-white/10 ring-1 ring-white/10 hover:bg-white/15">
                        <i class="fa-brands fa-instagram"></i>
                    </a>
                    <a href="#" class="inline-flex items-center justify-center w-10 h-10 transition rounded-xl bg-white/10 ring-1 ring-white/10 hover:bg-white/15">
                        <i class="fa-brands fa-x-twitter"></i>
                    </a>
                </div>
            </div>
            <div class="md:col-span-7">
                <div class="grid gap-8 sm:grid-cols-3">
                    <div>
                        <p class="text-sm font-semibold tracking-wide">Company</p>
                        <div class="mt-4 space-y-2 text-sm text-white/75">
                            <a class="block transition hover:text-white" href="#">About</a>
                            <a class="block transition hover:text-white" href="#">Contact</a>
                            <a class="block transition hover:text-white" href="#">FAQ</a>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-semibold tracking-wide">Legal</p>
                        <div class="mt-4 space-y-2 text-sm text-white/75">
                            <a class="block transition hover:text-white" href="#">Terms</a>
                            <a class="block transition hover:text-white" href="#">Privacy</a>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-semibold tracking-wide">Get Started</p>
                        <div class="mt-4 space-y-2 text-sm text-white/75">
                            <a class="block transition hover:text-white" href="{{ route('register') }}">Register</a>
                            <a class="block transition hover:text-white" href="{{ route('login') }}">Login</a>
                            <a class="block transition hover:text-white" href="{{ route('booking') }}">Book Now</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex flex-col gap-3 pt-6 mt-12 border-t border-white/10 md:flex-row md:items-center md:justify-between">
            <p class="text-xs text-white/60">© {{ date('Y') }} Levictas. All rights reserved.</p>
            <p class="text-xs text-white/55">Made with care for comfort & wellness.</p>
        </div>
    </div>
</footer>

{{-- These stay inline because they use Blade/Laravel session syntax --}}
@if(session('booking_error'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        showSpaToast(@json(session('booking_error')), 'error');
    });
</script>
@endif

@if(session('success'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        showSpaToast(@json(session('success')), 'success');
    });
</script>
@endif

@if($errors->hasBag('customerProfile') && $errors->getBag('customerProfile')->any())
<script>
    document.addEventListener('DOMContentLoaded', function () {
        openProfileModal();
    });
</script>
@endif

@if(session('error'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        showSpaToast(@json(session('error')), 'error');
    });
</script>
@endif

</body>
</html>
