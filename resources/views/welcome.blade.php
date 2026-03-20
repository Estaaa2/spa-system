<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Levictas | Spa & Wellness</title>

    @vite(['resources/css/app.css','resources/css/landing.css', 'resources/js/app.js'])

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>

<body class="bg-[#F6EFE6] text-gray-800 selection:bg-[#D2A85B]/30 selection:text-[#3C2F23]">

<nav class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 nav-glass" id="topNav">
    <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <div class="flex items-center">
                <a href="{{ url('/') }}" class="flex items-center space-x-3 group">
                    <img src="{{ asset('images/1.png') }}" alt="Levictas" class="w-auto h-10 rounded-md ring-1 ring-black/5">
                    <div class="flex flex-col leading-tight">
                        <span class="text-2xl font-semibold text-[#2D3748] font-['Playfair_Display'] tracking-wide group-hover:text-[#6F5430] transition">
                            Levictas
                        </span>
                        <span class="text-[10px] tracking-[0.18em] text-gray-500 uppercase">
                            Spa & Wellness Sanctuary
                        </span>
                    </div>
                </a>
            </div>

            <div class="items-center hidden space-x-2 md:flex">
                <a href="{{ url('/') }}"
                   class="relative px-4 py-2 text-sm font-medium rounded-full transition
                   {{ request()->is('/') ? 'text-[#6F5430] bg-white/60 ring-1 ring-black/5' : 'text-gray-700 hover:text-[#8B7355] hover:bg-white/50' }}">
                    Home
                </a>

                @guest
                    <a href="{{ route('login') }}"
                    class="relative px-4 py-2 text-sm font-medium rounded-full transition
                    {{ request()->is('login') ? 'text-[#6F5430] bg-white/60 ring-1 ring-black/5' : 'text-gray-700 hover:text-[#8B7355] hover:bg-white/50' }}">
                        Login
                    </a>

                    <a href="{{ route('register') }}"
                    class="relative px-4 py-2 text-sm font-medium rounded-full transition
                    {{ request()->is('register') ? 'text-[#6F5430] bg-white/60 ring-1 ring-black/5' : 'text-gray-700 hover:text-[#8B7355] hover:bg-white/50' }}">
                        Register
                    </a>

                    <a href="{{ route('register.business') }}"
                    class="booking-btn ml-3 px-6 py-2.5 text-sm font-semibold text-white rounded-full transition-all duration-300 shadow-lg hover:shadow-xl active:translate-y-0.5">
                        Join as a Partner
                    </a>

                @else
                    @role('customer')
                    <div class="flex items-center gap-3">

                        <!-- Navigation Links -->
                        <div class="flex items-center gap-1">
                            <a href="#" onclick="openAppointmentsModal()"
                                class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 hover:text-[#8B7355]">
                                My Appointments
                            </a>
                            <a href="#" onclick="openScheduleModal()"
                                class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 hover:text-[#8B7355]">
                                My Schedule
                            </a>
                        </div>

                        <!-- Profile Dropdown -->
                        <div class="relative" id="profileDropdownWrapper">
                            <button type="button" id="profileDropdownBtn"
                                class="flex items-center gap-2 px-3 py-2 transition rounded-full hover:bg-white/60 ring-1 ring-black/5">
                                <div class="flex items-center justify-center w-8 h-8 bg-[#8B7355] text-white rounded-full text-xs font-semibold">
                                    {{ strtoupper(substr(auth()->user()?->name ?? 'Guest', 0, 1)) }}
                                </div>
                                <i class="fa-solid fa-chevron-down text-[10px] text-gray-400 transition-transform duration-200" id="profileChevron"></i>
                            </button>

                            <!-- Dropdown Menu -->
                            <div id="profileDropdownMenu"
                                class="absolute right-0 z-50 hidden w-48 mt-2 overflow-hidden bg-white shadow-xl rounded-2xl ring-1 ring-black/10">

                                <div class="px-4 py-3 border-b border-black/5 bg-[#F6EFE6]/60">
                                    <p class="text-xs font-semibold text-[#3C2F23] truncate">{{ auth()->user()?->name ?? 'Guest' }}</p>
                                    <p class="text-[11px] text-gray-400 truncate">{{ auth()->user()?->email ?? '' }}</p>
                                </div>

                                <div class="py-1">
                                    <button type="button"
                                        onclick="closeProfileDropdown(); openProfileModal();"
                                        class="flex items-center gap-3 w-full px-4 py-2.5 text-sm text-gray-700 hover:bg-[#F6EFE6] transition">
                                        <i class="fa-solid fa-user text-[#8B7355] w-4"></i>
                                        Profile
                                    </button>

                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit"
                                            class="flex items-center gap-3 w-full px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 transition">
                                            <i class="w-4 fa-solid fa-right-from-bracket"></i>
                                            Logout
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endrole
                @endguest
            </div>

            <div class="md:hidden">
                <button type="button" id="mobile-menu-button"
                        class="relative p-2 text-gray-700 transition-colors duration-200 rounded-xl hover:bg-white/60 ring-1 ring-black/5">
                    <i class="text-xl fas fa-bars"></i>
                </button>
            </div>
        </div>
    </div>
        @auth
        <!-- Profile Modal -->
        <div id="profileModal" class="fixed inset-0 z-[130] hidden">
            <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeProfileModal()"></div>

            <div class="relative mx-auto w-[92%] max-w-lg mt-10 sm:mt-16">
                <div class="overflow-hidden bg-white shadow-2xl rounded-3xl ring-1 ring-black/10">

                    @auth
                        <!-- Header -->
                        <div class="relative px-6 py-8 bg-gradient-to-br from-[#6F5430] to-[#8B7355] text-white text-center">
                            <!-- Avatar -->
                            <div class="flex items-center justify-center w-16 h-16 mx-auto text-2xl font-bold rounded-full bg-white/20 ring-2 ring-white/30">
                                {{ strtoupper(substr(auth()->user()?->name ?? 'Guest', 0, 1)) }}
                            </div>
                            <h3 class="mt-3 text-lg font-semibold font-['Playfair_Display']">
                                {{ auth()->user()?->name ?? 'Guest' }}
                            </h3>
                            <p class="mt-1 text-xs tracking-wide uppercase text-white/70">Customer Account</p>

                            <button onclick="closeProfileModal()"
                                class="absolute flex items-center justify-center w-8 h-8 transition top-4 right-4 rounded-xl bg-white/10 hover:bg-white/20">
                                <i class="text-sm fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    @endauth

                    <!-- Content -->
                    <div class="p-6 space-y-4">

                        <!-- Full Name -->
                        <div class="flex items-center gap-4 p-4 rounded-2xl bg-[#F6EFE6]/50 ring-1 ring-black/5">
                            <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-[#8B7355]/10">
                                <i class="fa-solid fa-user text-[#8B7355] text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] text-gray-400 uppercase tracking-wide">Full Name</p>
                                <p class="text-sm font-semibold text-[#3C2F23] truncate">{{ auth()->user()?->name ?? 'Guest' }}</p>
                            </div>
                        </div>

                        <!-- Email — partially masked -->
                        <div class="flex items-center gap-4 p-4 rounded-2xl bg-[#F6EFE6]/50 ring-1 ring-black/5">
                            <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-[#8B7355]/10">
                                <i class="fa-solid fa-envelope text-[#8B7355] text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[11px] text-gray-400 uppercase tracking-wide">Email Address</p>
                                @php
                                    $email = auth()->user()?->email ?? '';
                                    $parts = explode('@', $email);
                                    $name = $parts[0];
                                    $domain = $parts[1] ?? '';
                                    $maskedName = strlen($name) > 3
                                        ? substr($name, 0, 2) . str_repeat('*', strlen($name) - 2)
                                        : str_repeat('*', strlen($name));
                                    $maskedEmail = $maskedName . '@' . $domain;
                                @endphp
                                <div class="flex items-center gap-2">
                                    <p id="emailDisplay" class="text-sm font-semibold text-[#3C2F23] truncate">{{ $maskedEmail }}</p>
                                    <button type="button" id="emailToggleBtn" onclick="toggleEmail(this)"
                                        data-masked="{{ $maskedEmail }}"
                                        data-real="{{ $email }}"
                                        class="text-[#8B7355] hover:text-[#6F5430] transition flex-shrink-0"
                                        title="Show/Hide email">
                                        <i id="emailToggleIcon" class="text-xs fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Email Verified Status -->
                        <div class="flex items-center gap-4 p-4 rounded-2xl bg-[#F6EFE6]/50 ring-1 ring-black/5">
                            <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-[#8B7355]/10">
                                <i class="fa-solid fa-shield-halved text-[#8B7355] text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-[11px] text-gray-400 uppercase tracking-wide">Account Status</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    @if(auth()->user()?->hasVerifiedEmail())
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-green-600">
                                            <i class="fa-solid fa-circle-check"></i> Verified
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-500">
                                            <i class="fa-solid fa-circle-exclamation"></i> Unverified
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 pb-6">
                        <button onclick="closeProfileModal()"
                            class="w-full py-3 rounded-xl text-sm font-semibold text-white booking-btn shadow-md hover:shadow-lg transition active:translate-y-0.5">
                            Close
                        </button>
                    </div>
                </div>
                <div class="h-10">

                </div>
            </div>
        </div>
        @endauth

    <div id="mobile-menu" class="hidden bg-[#F6EFE6]/95 border-t border-black/10 shadow-lg md:hidden">
        <div class="px-3 pt-3 pb-5 space-y-2">
            <a href="{{ url('/') }}"
               class="block px-4 py-3 rounded-xl text-base font-medium transition
               {{ request()->is('/') ? 'bg-white/70 text-[#6F5430] ring-1 ring-black/5' : 'text-gray-700 hover:bg-white/60' }}">
                Home
            </a>

            @guest
                <a href="{{ route('login') }}" class="block px-4 py-3 text-base font-medium rounded-xl hover:bg-white/60">Login</a>
                <a href="{{ route('register') }}" class="block px-4 py-3 text-base font-medium rounded-xl hover:bg-white/60">Register</a>
                <a href="{{ route('register.business') }}" class="block px-4 py-3 text-base font-medium rounded-xl hover:bg-white/60">Join as a Partner</a>
            @else
                @role('customer')

                    <a href="#" class="block px-4 py-3 text-base font-medium rounded-xl hover:bg-white/60">Profile</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full px-4 py-3 text-base font-medium text-left rounded-xl hover:bg-white/60">
                            Logout
                        </button>
                    </form>
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
            <div class="absolute inset-0 bg-gradient-to-b from-black/30 via-black/35 to-[#F6EFE6]"></div>
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

            <form action="{{ route('booking') }}" method="GET"
                  class="grid max-w-4xl grid-cols-1 gap-4 p-4 mx-auto mt-10 shadow-2xl bg-white/90 rounded-2xl ring-1 ring-black/5 md:grid-cols-12">
                <div class="flex items-center gap-3 px-4 py-3 bg-white border border-black/10 md:col-span-8 rounded-xl">
                    <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-[#F6EFE6] ring-1 ring-black/5">
                        <i class="fa-solid fa-location-dot text-[#8B7355]"></i>
                    </span>
                    <input type="text" name="city" placeholder="Where are you at?"
                        class="w-full text-sm bg-transparent border-0 focus:ring-0 soft-ring placeholder:text-gray-400">
                </div>

                <button class="md:col-span-4 booking-btn text-white rounded-xl font-semibold hover:opacity-95 transition shadow-lg active:translate-y-0.5">
                    <span class="inline-flex items-center justify-center gap-2 py-3">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Search
                    </span>
                </button>
            </form>
        </div>

        <div class="h-10 bg-gradient-to-b from-transparent to-[#F6EFE6]"></div>
    </section>

    <!-- ================= MY APPOINTMENTS MODAL ================= -->
    <div id="appointmentsModal" class="fixed inset-0 z-[120] hidden">
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeAppointmentsModal()"></div>

        <div class="relative mx-auto w-[92%] max-w-2xl mt-10 sm:mt-16">
            <div class="overflow-hidden bg-white shadow-2xl rounded-3xl ring-1 ring-black/10">

                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-black/5">
                    <h3 class="text-lg font-semibold text-[#3C2F23]">My Appointments</h3>
                    <button onclick="closeAppointmentsModal()"
                        class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5">
                        <i class="text-lg text-gray-700 fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- Tabs -->
                <div class="flex border-b border-black/5">
                    @foreach(['upcoming' => 'Upcoming', 'past' => 'Past', 'cancelled' => 'Cancelled'] as $key => $label)
                    <button onclick="switchTab('{{ $key }}')"
                        id="tab-{{ $key }}"
                        class="flex-1 py-3 text-sm font-semibold transition border-b-2
                        {{ $key === 'upcoming' ? 'border-[#8B7355] text-[#8B7355]' : 'border-transparent text-gray-500 hover:text-[#8B7355]' }}">
                        {{ $label }}
                        <span id="tab-count-{{ $key }}"
                            class="ml-1 px-2 py-0.5 text-xs rounded-full bg-[#F6EFE6] text-[#6F5430]">0</span>
                    </button>
                    @endforeach
                </div>

                <!-- Tab Content -->
                <div class="overflow-y-auto max-h-[60vh] p-6" id="appointmentsContent">
                    <div class="flex items-center justify-center py-12">
                        <i class="text-2xl text-gray-300 fa-solid fa-spinner fa-spin"></i>
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
            <div class="overflow-hidden bg-white shadow-2xl rounded-3xl ring-1 ring-black/10">

                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-black/5">
                    <div class="flex items-center gap-3">
                        <button onclick="changeMonth(-1)"
                            class="flex items-center justify-center w-8 h-8 transition rounded-lg hover:bg-black/5">
                            <i class="text-sm fa-solid fa-chevron-left"></i>
                        </button>
                        <h3 id="calendarTitle" class="text-lg font-semibold text-[#3C2F23]">March 2026</h3>
                        <button onclick="changeMonth(1)"
                            class="flex items-center justify-center w-8 h-8 transition rounded-lg hover:bg-black/5">
                            <i class="text-sm fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                    <button onclick="closeScheduleModal()"
                        class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5">
                        <i class="text-lg text-gray-700 fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- Calendar -->
                <div class="p-6">
                    <!-- Day headers -->
                    <div class="grid grid-cols-7 mb-2">
                        @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
                        <div class="py-2 text-xs font-semibold text-center text-gray-400">{{ $day }}</div>
                        @endforeach
                    </div>
                    <!-- Calendar grid -->
                    <div id="calendarGrid" class="grid grid-cols-7 gap-1"></div>

                    <!-- Selected day bookings -->
                    <div id="selectedDayBookings" class="hidden mt-6 space-y-3">
                        <h4 id="selectedDayTitle" class="text-sm font-semibold text-[#3C2F23]"></h4>
                        <div id="selectedDayContent"></div>
                    </div>
                </div>
            </div>
            <div class="h-10"></div>
        </div>
    </div>

    <!-- ================= FEATURED SPAS ================= -->
    <section class="py-20">
        <div class="px-6 mx-auto mt-10 max-w-7xl">
            <div class="text-center">
                <div class="flex items-center justify-center gap-6">
                    <span class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355]"></span>
                    <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] font-semibold">
                        Featured Spas
                    </h2>
                    <span class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355]"></span>
                </div>
                <p class="mt-3 text-sm text-gray-600">
                    Curated picks for a premium relaxation experience.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 mt-12 sm:grid-cols-2 lg:grid-cols-4">
                @forelse($spas as $spa)
                    @foreach($spa->branches as $branch)
                        @if($spa->verification_status === 'verified' && $branch->profile?->is_listed)
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

                                $thumb = $coverPhoto;

                                // ✅ Build branches array with treatments + packages
                                $branchesData = $spa->branches->map(fn($b) => [
                                    'id'               => $b->id,
                                    'name'             => $b->name,
                                    'location'         => $b->location ?? '',
                                    'has_home_service' => (bool) ($b->has_home_service ?? false),
                                    'treatments'       => $b->treatments->map(fn($t) => [
                                        'id'    => $t->id,
                                        'name'  => $t->name,
                                        'price' => $t->price,
                                    ])->values()->toArray(),
                                    'packages' => ($b->packages ?? collect())->map(fn($p) => [
                                        'id'   => $p->id,
                                        'name' => $p->name,
                                    ])->values()->toArray(),
                                ])->values()->toArray();

                                $spaPayload = [
                                    'id'         => $spa->id,
                                    'name'       => $spa->name,
                                    'tag'        => 'Featured Spa',
                                    'desc'       => $profile->description ?? '',
                                    'price_note' => $lowestPrice ? number_format($lowestPrice, 2) : null,
                                    'photos'     => $photos,
                                    'address'    => $profile->address ?? $branch->location ?? 'Location unavailable',
                                    'phone'      => $profile->phone ?? '',
                                    'lat'        => $profile->latitude,
                                    'lng'        => $profile->longitude,
                                    'branches'   => $branchesData,
                                    'amenities'  => $profile->amenities ?? [],
                                ];
                            @endphp

                            <button type="button"
                                class="w-full overflow-hidden text-left transition bg-white shadow-sm group rounded-3xl ring-1 ring-black/5 hover:shadow-2xl"
                                data-open-spa-modal
                                data-spa='@json($spaPayload)'>
                                <div class="relative overflow-hidden">
                                    <img src="{{ $thumb }}" class="h-56 w-full object-cover transition duration-500 group-hover:scale-[1.04]" alt="{{ $spa->name }}">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-black/0 to-transparent"></div>

                                    <div class="absolute top-3 left-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-[#6F5430]/90 text-white text-[11px] font-semibold backdrop-blur-sm">
                                        <i class="fa-solid fa-star text-[#F5C842] text-[10px]"></i>
                                        Featured
                                    </div>

                                </div>
                                <div class="p-5">
                                    <h3 class="text-[15px] font-semibold text-[#3C2F23] leading-tight">{{ $spa->name }}</h3>
                                    @php
                                    function addressSummary($fullAddress) {
                                        if (!$fullAddress) return 'Location unavailable';
                                        $parts = array_map('trim', explode(',', $fullAddress));
                                        if (count($parts) < 3) return $fullAddress;
                                        $withoutZipCountry = array_slice($parts, 0, count($parts) - 2);
                                        $summary = implode(', ', array_slice($withoutZipCountry, -3));
                                        return $summary;
                                    }
                                    @endphp
                                    <p class="mt-1 text-xs text-gray-500">{{ addressSummary($spaPayload['address']) }}</p>
                                    <p class="mt-3 text-sm text-gray-600 line-clamp-2">{{ $spaPayload['desc'] ?? 'No description yet.' }}</p>
                                </div>
                            </button>
                        @endif
                    @endforeach
                @empty
                    <div class="text-center text-gray-600 col-span-full">No spas found yet.</div>
                @endforelse
            </div>
        </div>
    </section>

    <!-- ================= SPA MODAL ================= -->
    <div id="spaModal" class="fixed inset-0 z-[100] hidden">

        <!-- Overlay -->
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" data-close-spa-modal></div>

        <!-- Modal container -->
        <div class="relative mx-auto w-[92%] max-w-5xl mt-8 mb-8">
            <div class="overflow-hidden bg-white shadow-2xl rounded-3xl flex flex-col max-h-[90vh]">

                <!-- Header -->
                <div class="sticky top-0 z-10 flex items-center justify-between px-6 py-4 bg-white border-b">
                    <div>
                        <h3 id="spaModalName" class="text-2xl font-bold tracking-tight text-gray-900">Spa Name</h3>
                        <div class="flex items-center gap-1 text-sm text-gray-500 mt-0.5">
                            <span id="spaModalTag">Featured Spa</span>
                            <span class="mx-1">·</span>
                            <span id="spaModalAddressSummary" class="font-medium underline">Location</span>
                        </div>
                    </div>

                    <button data-close-spa-modal class="p-2 text-gray-900 transition-colors rounded-full hover:bg-gray-100">
                        <i class="text-lg fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="overflow-y-auto">

                    <!-- Photo gallery -->
                    <div class="p-6">
                        <div class="grid grid-cols-4 grid-rows-2 gap-2 h-[400px] rounded-2xl overflow-hidden">
                            <div class="col-span-2 row-span-2 bg-gray-200">
                                <img id="spaModalMainPhoto" src="" class="object-cover w-full h-full transition-opacity cursor-pointer hover:opacity-90">
                            </div>
                            <div class="col-span-1 row-span-1 bg-gray-200">
                                <img id="gallery_1" class="object-cover w-full h-full transition-opacity hover:opacity-90">
                            </div>
                            <div class="col-span-1 row-span-1 bg-gray-200">
                                <img id="gallery_2" class="object-cover w-full h-full transition-opacity hover:opacity-90">
                            </div>
                            <div class="col-span-1 row-span-1 bg-gray-200">
                                <img id="gallery_3" class="object-cover w-full h-full transition-opacity hover:opacity-90">
                            </div>
                            <div class="relative col-span-1 row-span-1 bg-gray-200">
                                <img id="gallery_4" class="object-cover w-full h-full transition-opacity hover:opacity-90">
                                <div id="spaModalGalleryCount" class="absolute inset-0 flex items-center justify-center font-semibold text-white bg-black/40">
                                    + View All
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="grid gap-8 px-6 pb-6 md:grid-cols-3">

                        <!-- Left / Main content -->
                        <div class="space-y-8 md:col-span-2">

                            <!-- About -->
                            <div>
                                <h4 class="mb-3 text-xl font-semibold text-gray-900">About this spa</h4>
                                <p id="spaModalDesc" class="leading-relaxed text-gray-600"></p>
                            </div>

                            <hr class="border-gray-200">

                            <!-- Amenities -->
                            <div>
                                @php
                                    $amenityIcons = [
                                        'aircon' => 'fa-fan',
                                        'private_rooms' => 'fa-door-closed',
                                        'shower' => 'fa-shower',
                                        'parking' => 'fa-car',
                                        'wifi' => 'fa-wifi',
                                        'locker' => 'fa-lock',
                                        'pet_friendly' => 'fa-dog',
                                        'sauna' => 'fa-hot-tub-person',
                                        // add more as needed
                                    ];
                                    $amenities = $profile->amenities ?? [];
                                @endphp
                                <h4 class="mb-4 text-xl font-semibold text-gray-900">What this place offers</h4>
                                @foreach($amenities as $a)
                                    <div class="flex items-center gap-2 mt-2">
                                        <i class="fa-solid {{ $amenityIcons[$a] ?? 'fa-star' }} text-[#8B7355]"></i> 
                                        <span class="mr-4 text-sm text-gray-700 capitalize">{{ str_replace('_', ' ', $a) }}</span>
                                    </div>
                                @endforeach
                            </div>

                        </div>

                        <!-- Right / Sidebar -->
                        <div class="md:col-span-1">
                            <div class="sticky p-6 space-y-4 border shadow-sm rounded-2xl top-4">

                                <!-- Address -->
                                <div class="flex items-start gap-3">
                                    <i class="mt-1 text-gray-900 fa-solid fa-location-dot"></i>
                                    <div>
                                        <p class="font-semibold">Address</p>
                                        <p id="spaModalAddress" class="text-sm text-gray-500"></p>
                                    </div>
                                </div>

                                <!-- Contact -->
                                <div class="flex items-start gap-3">
                                    <i class="mt-1 text-gray-900 fa-solid fa-phone"></i>
                                    <div>
                                        <p class="font-semibold">Contact</p>
                                        <p id="spaModalPhone" class="text-sm text-gray-500"></p>
                                    </div>
                                </div>

                                <!-- Price -->
                                <div class="flex items-center gap-3">
                                    <i class="mt-1 text-gray-900 fa-solid fa-tag"></i>
                                    <div>
                                        <p class="font-semibold">Price</p>
                                        <p id="spaModalPrice" class="text-sm text-gray-500"></p>
                                    </div>
                                </div>

                                <!-- Map -->
                                <div id="spaModalMap" class="w-full h-[180px] rounded-xl border bg-gray-50 overflow-hidden"></div>

                                <!-- Reserve button -->
                                <button
                                type="button"
                                id="openBookingModalBtn"
                                class="block w-full mt-4 text-center booking-btn text-white py-3 rounded-xl text-sm font-semibold shadow-md hover:shadow-lg transition active:translate-y-0.5">
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
    <div id="bookingModal" class="fixed inset-0 z-[110] hidden">
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" data-close-booking-modal></div>

        <div class="relative mx-auto w-[92%] max-w-xl mt-10 sm:mt-16">
            <div class="overflow-hidden bg-white shadow-2xl rounded-3xl ring-1 ring-black/10">
                <div class="flex items-center justify-between px-6 py-4 border-b border-black/5">
                    <div>
                        <h3 class="text-lg font-semibold text-[#3C2F23]">Book Appointment</h3>
                        <p id="bookingSpaMeta" class="mt-1 text-xs text-gray-500">Spa • Branch Location</p>
                    </div>

                    <button type="button"
                            class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5"
                            data-close-booking-modal
                            aria-label="Close">
                        <i class="text-lg text-gray-700 fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-6">
                    @auth
                        <form method="POST" action="{{ route('bookings.online.store') }}" class="space-y-4">
                            @csrf

                            {{-- Set by JS --}}
                            <input type="hidden" name="spa_id" id="bookingSpaIdInput">
                            <input type="hidden" name="branch_id" id="bookingBranchIdInput">

                            {{-- Row 1: Service Type + Branch --}}
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600">Service Type</label>
                                    <select name="service_type" id="bookingServiceType" required
                                            class="w-full mt-1 rounded-xl border-black/10 ring-1 ring-black/5 focus:ring-2 focus:ring-[#8B7355]/40">
                                        <option value="">Select type</option>
                                        <option value="in_branch">In-Branch</option>
                                        <option value="in_home">Home Service</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-600">Branch</label>
                                    <select id="bookingBranchSelect"
                                            class="w-full mt-1 rounded-xl border-black/10 ring-1 ring-black/5 focus:ring-2 focus:ring-[#8B7355]/40">
                                        <option value="">Select service type first</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Row 2: Treatment --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-600">Treatment</label>
                                <select name="treatment" id="bookingTreatmentSelect" required
                                        class="w-full mt-1 rounded-xl border-black/10 ring-1 ring-black/5 focus:ring-2 focus:ring-[#8B7355]/40">
                                    <option value="">Select treatment</option>
                                </select>
                            </div>

                            {{-- Row 3: Date + Time --}}
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600">Appointment Date</label>
                                    <input type="date" name="appointment_date" id="bookingDateInput" required
                                        class="w-full mt-1 rounded-xl border-black/10 ring-1 ring-black/5 focus:ring-2 focus:ring-[#8B7355]/40">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600">Start Time</label>
                                    <input type="time" name="start_time" id="bookingTimeInput" required
                                        class="w-full mt-1 rounded-xl border-black/10 ring-1 ring-black/5 focus:ring-2 focus:ring-[#8B7355]/40">
                                </div>
                            </div>

                            {{-- Row 4: Phone --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-600">Phone (optional)</label>
                                <input type="text" name="customer_phone"
                                    class="w-full mt-1 rounded-xl border-black/10 ring-1 ring-black/5 focus:ring-2 focus:ring-[#8B7355]/40"
                                    placeholder="09xxxxxxxxx">
                            </div>

                            {{-- Row 5: Address (hidden until Home Service selected) --}}
                            <div id="addressWrapper" class="hidden">
                                <label class="block text-xs font-semibold text-gray-600">
                                    Home Address <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="customer_address" id="bookingAddressInput"
                                    class="w-full mt-1 rounded-xl border-black/10 ring-1 ring-black/5 focus:ring-2 focus:ring-[#8B7355]/40"
                                    placeholder="Enter your full address">
                                <p class="mt-1 text-[11px] text-gray-500">Required for home service bookings.</p>
                            </div>

                            <button type="submit"
                                    class="w-full booking-btn text-white py-3 rounded-xl text-sm font-semibold shadow-md hover:shadow-lg transition active:translate-y-0.5">
                                Confirm Booking
                            </button>
                        </form>
                    @else
                        <div class="p-4 rounded-2xl bg-[#F6EFE6]/70 ring-1 ring-black/5">
                            <p class="text-sm text-gray-700">
                                Please log in to book an appointment.
                            </p>

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

    <!-- ================= HOW IT WORKS ================= -->
    <section class="bg-[#EFE3D6] py-20">
        <div class="px-6 mx-auto max-w-7xl">
            <div class="text-center">
                <div class="flex items-center justify-center gap-6">
                    <span class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355]"></span>
                    <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] font-semibold">
                        How It Works
                    </h2>
                    <span class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355]"></span>
                </div>

                <p class="mt-3 text-sm text-gray-600">
                    Book in minutes with a simple flow.
                </p>
            </div>

            <div class="grid gap-6 mt-12 md:grid-cols-3">
                <div class="p-8 text-center transition shadow-sm bg-white/70 rounded-3xl ring-1 ring-black/5 hover:shadow-lg">
                    <div class="flex items-center justify-center w-14 h-14 mx-auto rounded-2xl bg-[#F6EFE6] ring-1 ring-black/5">
                        <i class="fa-solid fa-location-dot text-2xl text-[#8B7355]"></i>
                    </div>
                    <h3 class="mt-5 font-semibold text-[#3C2F23]">Find Your Spa</h3>
                    <p class="mt-2 text-sm text-gray-600">Browse verified spas near you.</p>
                </div>

                <div class="p-8 text-center transition shadow-sm bg-white/70 rounded-3xl ring-1 ring-black/5 hover:shadow-lg">
                    <div class="flex items-center justify-center w-14 h-14 mx-auto rounded-2xl bg-[#F6EFE6] ring-1 ring-black/5">
                        <i class="fa-solid fa-list-check text-2xl text-[#8B7355]"></i>
                    </div>
                    <h3 class="mt-5 font-semibold text-[#3C2F23]">Choose Service</h3>
                    <p class="mt-2 text-sm text-gray-600">Select the service you want.</p>
                </div>

                <div class="p-8 text-center transition shadow-sm bg-white/70 rounded-3xl ring-1 ring-black/5 hover:shadow-lg">
                    <div class="flex items-center justify-center w-14 h-14 mx-auto rounded-2xl bg-[#F6EFE6] ring-1 ring-black/5">
                        <i class="fa-solid fa-spa text-2xl text-[#8B7355]"></i>
                    </div>
                    <h3 class="mt-5 font-semibold text-[#3C2F23]">Relax & Enjoy</h3>
                    <p class="mt-2 text-sm text-gray-600">Arrive and unwind.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= WHY BOOK ================= -->
    <section class="py-20">
        <div class="px-6 mx-auto max-w-7xl">
            <div class="text-center">
                <div class="flex items-center justify-center gap-6">
                    <span class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355]"></span>
                    <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] font-semibold">
                        Why Book With Us
                    </h2>
                    <span class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355]"></span>
                </div>

                <p class="mt-3 text-sm text-gray-600">
                    Built for convenience and trust.
                </p>
            </div>

            <div class="grid gap-6 mt-12 md:grid-cols-4">
                <div class="transition bg-white shadow-sm p-7 rounded-3xl ring-1 ring-black/5 hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] ring-1 ring-black/5">
                        <i class="fa-solid fa-check text-xl text-[#8B7355]"></i>
                    </div>
                    <h4 class="mt-5 font-semibold text-[#3C2F23]">Verified Spas</h4>
                    <p class="mt-2 text-sm text-gray-600">Only trusted listings appear on the platform.</p>
                </div>

                <div class="transition bg-white shadow-sm p-7 rounded-3xl ring-1 ring-black/5 hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] ring-1 ring-black/5">
                        <i class="fa-solid fa-calendar-check text-xl text-[#8B7355]"></i>
                    </div>
                    <h4 class="mt-5 font-semibold text-[#3C2F23]">Easy Booking</h4>
                    <p class="mt-2 text-sm text-gray-600">Reserve quickly with clear scheduling.</p>
                </div>

                <div class="transition bg-white shadow-sm p-7 rounded-3xl ring-1 ring-black/5 hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] ring-1 ring-black/5">
                        <i class="fa-solid fa-user-nurse text-xl text-[#8B7355]"></i>
                    </div>
                    <h4 class="mt-5 font-semibold text-[#3C2F23]">Expert Therapists</h4>
                    <p class="mt-2 text-sm text-gray-600">Quality care from professional practitioners.</p>
                </div>

                <div class="transition bg-white shadow-sm p-7 rounded-3xl ring-1 ring-black/5 hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] ring-1 ring-black/5">
                        <i class="fa-solid fa-lock text-xl text-[#8B7355]"></i>
                    </div>
                    <h4 class="mt-5 font-semibold text-[#3C2F23]">Secure Payments</h4>
                    <p class="mt-2 text-sm text-gray-600">Safe checkout experience and privacy-focused flow.</p>
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

                    <a href="{{ route('register') }}"
                       class="inline-flex items-center justify-center gap-2 mt-7 bg-white text-[#6F5430] px-8 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition active:translate-y-0.5">
                        <i class="fa-solid fa-arrow-right"></i>
                        Get Started
                    </a>
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
                        <p class="mt-1 text-xs tracking-[0.18em] uppercase text-white/70">
                            Spa & Wellness Sanctuary
                        </p>
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

<script>
// ---------------- Mobile menu ----------------
const btn = document.getElementById('mobile-menu-button');
const menu = document.getElementById('mobile-menu');
btn?.addEventListener('click', () => menu.classList.toggle('hidden'));


// ---------------- Nav scroll effect ----------------
const nav = document.getElementById('topNav');
window.addEventListener('scroll', () => {
    if (window.scrollY > 10) nav?.classList.add('nav-scrolled');
    else nav?.classList.remove('nav-scrolled');
});


// ---------------- Shared state ----------------
let selectedSpa      = null;
let preferredBranchId = null;
let spaMap           = null;


// =====================================================
// PROFILE DROPDOWN
// =====================================================
const profileDropdownBtn  = document.getElementById('profileDropdownBtn');
const profileDropdownMenu = document.getElementById('profileDropdownMenu');
const profileChevron      = document.getElementById('profileChevron');

function closeProfileDropdown() {
    profileDropdownMenu?.classList.add('hidden');
    profileChevron?.classList.remove('rotate-180');
}

profileDropdownBtn?.addEventListener('click', function (e) {
    e.stopPropagation();
    const isHidden = profileDropdownMenu.classList.contains('hidden');
    if (isHidden) {
        profileDropdownMenu.classList.remove('hidden');
        profileChevron?.classList.add('rotate-180');
    } else {
        closeProfileDropdown();
    }
});

document.addEventListener('click', function (e) {
    const wrapper = document.getElementById('profileDropdownWrapper');
    if (wrapper && !wrapper.contains(e.target)) closeProfileDropdown();
});


// =====================================================
// PROFILE MODAL
// =====================================================
function openProfileModal() {
    document.getElementById('profileModal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeProfileModal() {
    document.getElementById('profileModal').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');

    const btn     = document.getElementById('emailToggleBtn');
    const display = document.getElementById('emailDisplay');
    const icon    = document.getElementById('emailToggleIcon');
    if (btn && display && icon) {
        display.textContent = btn.dataset.masked;
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function toggleEmail() {
    const display = document.getElementById('emailDisplay');
    const btn     = document.getElementById('emailToggleBtn');
    const icon    = document.getElementById('emailToggleIcon');
    if (!display || !btn || !icon) return;

    const isHidden = icon.classList.contains('fa-eye');
    if (isHidden) {
        display.textContent = btn.dataset.real;
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        display.textContent = btn.dataset.masked;
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}


// =====================================================
// SPA MODAL — elements
// =====================================================
const spaModal     = document.getElementById('spaModal');
const closeSpaBtns = document.querySelectorAll('[data-close-spa-modal]');

let photos     = [];
let photoIndex = 0;


// ---------------- Photo viewer ----------------
function setPhoto(i) {
    const elMainPhoto = document.getElementById('spaModalMainPhoto');
    if (!photos.length || !elMainPhoto) return;

    photoIndex = (i + photos.length) % photos.length;
    elMainPhoto.src = photos[photoIndex];
}


// ---------------- Open Spa Modal ----------------
function openSpaModal(spaData) {
    selectedSpa = spaData;

    const elName           = document.getElementById('spaModalName');
    const elTag            = document.getElementById('spaModalTag');
    const elDesc           = document.getElementById('spaModalDesc');
    const elAddress        = document.getElementById('spaModalAddress');
    const elAddressSummary = document.getElementById('spaModalAddressSummary');
    const elPhone          = document.getElementById('spaModalPhone');
    const elPrice          = document.getElementById('spaModalPrice');
    const elMap            = document.getElementById('spaModalMap');
    const elMainPhoto      = document.getElementById('spaModalMainPhoto');
    const elAmenities = document.getElementById('spaModalAmenities');

    // Fill text content
    elName.textContent  = spaData.name    ?? 'Spa';
    elTag.textContent   = spaData.tag     ?? 'Featured Spa';
    elDesc.textContent  = spaData.desc    ?? '';
    elPhone.textContent = spaData.phone   ?? 'No contact info';
    elAddress.textContent = spaData.address ?? 'Address unavailable';
    elPrice.textContent = spaData.price_note
        ? `Starts at ₱${spaData.price_note}`
        : 'Prices vary per treatment';

    // Address summary helper
    function getAddressSummary(fullAddress) {
        if (!fullAddress) return 'Location unavailable';
        const parts = fullAddress.split(',').map(p => p.trim());
        if (parts.length < 3) return fullAddress;
        const withoutZipCountry = parts.slice(0, parts.length - 2);
        return withoutZipCountry.slice(-3).join(', ');
    }
    elAddressSummary.textContent = getAddressSummary(spaData.address);

    // Photos
    const fallbackImage = "{{ asset('storage/branch_profiles/emptyspa.jpg') }}";

    photos = Array.isArray(spaData.photos) && spaData.photos.length
        ? spaData.photos
        : [fallbackImage, fallbackImage, fallbackImage, fallbackImage, fallbackImage];

    if (elMainPhoto) {
        elMainPhoto.src = photos[0] || fallbackImage;
    }

    ['gallery_1', 'gallery_2', 'gallery_3', 'gallery_4'].forEach((id, i) => {
        const el = document.getElementById(id);
        if (el) {
            el.src = photos[i + 1] || fallbackImage;
        }
    });

    // Hide the gallery count overlay (no "View All" needed for now)
    const galleryCount = document.getElementById('spaModalGalleryCount');
    if (galleryCount) galleryCount.classList.add('hidden');

    // Show modal
    spaModal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');

    // Leaflet map
    if (spaMap) { spaMap.remove(); spaMap = null; }
    if (elMap && spaData.lat && spaData.lng) {
        setTimeout(() => {
            spaMap = L.map(elMap).setView([spaData.lat, spaData.lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(spaMap);
            L.marker([spaData.lat, spaData.lng])
                .addTo(spaMap)
                .bindPopup(spaData.name)
                .openPopup();
            spaMap.invalidateSize();
        }, 300);
    }

    photoIndex = 0;
}


// ---------------- Close Spa Modal ----------------
function closeSpaModal() {
    spaModal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}


// ---------------- Spa Modal Events ----------------
document.querySelectorAll('[data-open-spa-modal]').forEach(btn => {
    btn.addEventListener('click', () => {
        try {
            const data = JSON.parse(btn.getAttribute('data-spa'));
            openSpaModal(data);
        } catch (e) {
            console.error('Invalid spa data', e);
        }
    });
});

closeSpaBtns.forEach(btn => btn.addEventListener('click', closeSpaModal));


// =====================================================
// BOOKING MODAL — elements
// =====================================================
const bookingModal      = document.getElementById('bookingModal');
const openBookingBtn    = document.getElementById('openBookingModalBtn');
const closeBookingBtns  = document.querySelectorAll('[data-close-booking-modal]');
const bookingSpaMeta    = document.getElementById('bookingSpaMeta');
const bookingSpaIdInput = document.getElementById('bookingSpaIdInput');
const bookingBranchIdInput = document.getElementById('bookingBranchIdInput');
const serviceTypeSelect = document.getElementById('bookingServiceType');
const branchSelect      = document.getElementById('bookingBranchSelect');
const treatmentSelect   = document.getElementById('bookingTreatmentSelect');


// ---------------- Populate Branches ----------------
function populateBranchDropdown(filterHomeService = false) {
    if (!branchSelect || !selectedSpa) return;

    const branches = selectedSpa.branches ?? [];
    const filtered = filterHomeService
        ? branches.filter(b => b.has_home_service)
        : branches;

    branchSelect.innerHTML = '<option value="">Select branch</option>';

    filtered.forEach(b => {
        const opt = document.createElement('option');
        opt.value   = String(b.id);
        opt.textContent = b.location
            ? `${b.name} — ${b.location}`
            : b.name;
        branchSelect.appendChild(opt);
    });

    if (filtered.length) {
        branchSelect.value = String(filtered[0].id);
        if (bookingBranchIdInput) bookingBranchIdInput.value = filtered[0].id;
    }

    populateTreatmentsForBranch();
}


// ---------------- Populate Treatments for selected branch ----------------
function populateTreatmentsForBranch() {
    if (!treatmentSelect || !selectedSpa) return;

    const selectedBranchId = branchSelect?.value;
    const branch = (selectedSpa.branches ?? [])
        .find(b => String(b.id) === String(selectedBranchId));

    treatmentSelect.innerHTML = '<option value="">Select treatment</option>';

    if (!branch) return;

    (branch.treatments ?? []).forEach(t => {
        const opt = document.createElement('option');
        opt.value = `treatment_${t.id}`;
        opt.textContent = t.price
            ? `${t.name} — ₱${parseFloat(t.price).toLocaleString()}`
            : t.name;
        treatmentSelect.appendChild(opt);
    });

    (branch.packages ?? []).forEach(p => {
        const opt = document.createElement('option');
        opt.value = `package_${p.id}`;
        opt.textContent = `${p.name} (Package)`;
        treatmentSelect.appendChild(opt);
    });
}

// Alias kept for compatibility
function populateTreatments() {
    populateTreatmentsForBranch();
}


// ---------------- Service Type → filter branches ----------------
serviceTypeSelect?.addEventListener('change', function () {
    const isHome = this.value === 'in_home';

    populateBranchDropdown(isHome);

    // Show / hide address field
    const addressWrapper   = document.getElementById('addressWrapper');
    const addressInput     = document.getElementById('bookingAddressInput');
    if (addressWrapper) addressWrapper.classList.toggle('hidden', !isHome);
    if (addressInput)   addressInput.toggleAttribute('required', isHome);
});


// ---------------- Branch change → refresh treatments ----------------
branchSelect?.addEventListener('change', function () {
    if (bookingBranchIdInput) bookingBranchIdInput.value = this.value;
    populateTreatmentsForBranch();
});


// ---------------- Open Booking Modal ----------------
function openBookingModal() {
    if (!selectedSpa) return;

    const branches = selectedSpa.branches ?? [];
    const chosen   = branches[0];

    bookingSpaMeta.textContent = chosen?.location
        ? `${selectedSpa.name} • ${chosen.location}`
        : selectedSpa.name;

    if (bookingSpaIdInput) bookingSpaIdInput.value = selectedSpa.id ?? '';

    // Reset service type then populate branches + treatments fresh
    if (serviceTypeSelect) serviceTypeSelect.value = '';
    populateBranchDropdown(false);

    // Hide address field on fresh open
    const addressWrapper = document.getElementById('addressWrapper');
    const addressInput   = document.getElementById('bookingAddressInput');
    if (addressWrapper) addressWrapper.classList.add('hidden');
    if (addressInput)   addressInput.removeAttribute('required');

    bookingModal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}


// ---------------- Close Booking Modal ----------------
function closeBookingModal() {
    bookingModal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

openBookingBtn?.addEventListener('click', openBookingModal);
closeBookingBtns.forEach(b => b.addEventListener('click', closeBookingModal));


// =====================================================
// MY APPOINTMENTS MODAL
// =====================================================
let allAppointments = [];
let currentTab      = 'upcoming';

function openAppointmentsModal() {
    document.getElementById('appointmentsModal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    loadAppointments();
}

function closeAppointmentsModal() {
    document.getElementById('appointmentsModal').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

function loadAppointments() {
    fetch('/my-appointments')
        .then(r => r.json())
        .then(data => {
            allAppointments = data;
            updateTabCounts();
            renderTab(currentTab);
        });
}

function updateTabCounts() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('tab-count-upcoming').textContent =
        allAppointments.filter(b =>
            ['reserved', 'confirmed'].includes(b.status) && b.date_raw >= today).length;
    document.getElementById('tab-count-past').textContent =
        allAppointments.filter(b =>
            b.status === 'completed' ||
            (['reserved', 'confirmed'].includes(b.status) && b.date_raw < today)).length;
    document.getElementById('tab-count-cancelled').textContent =
        allAppointments.filter(b => b.status === 'cancelled').length;
}

function switchTab(tab) {
    currentTab = tab;
    ['upcoming', 'past', 'cancelled'].forEach(t => {
        const el = document.getElementById(`tab-${t}`);
        if (t === tab) {
            el.classList.add('border-[#8B7355]', 'text-[#8B7355]');
            el.classList.remove('border-transparent', 'text-gray-500');
        } else {
            el.classList.remove('border-[#8B7355]', 'text-[#8B7355]');
            el.classList.add('border-transparent', 'text-gray-500');
        }
    });
    renderTab(tab);
}

function renderTab(tab) {
    const today    = new Date().toISOString().split('T')[0];
    let filtered   = [];

    if (tab === 'upcoming') {
        filtered = allAppointments.filter(b =>
            ['reserved', 'confirmed'].includes(b.status) && b.date_raw >= today);
    } else if (tab === 'past') {
        filtered = allAppointments.filter(b =>
            b.status === 'completed' ||
            (['reserved', 'confirmed'].includes(b.status) && b.date_raw < today));
    } else {
        filtered = allAppointments.filter(b => b.status === 'cancelled');
    }

    const container = document.getElementById('appointmentsContent');

    if (!filtered.length) {
        container.innerHTML = `
            <div class="py-12 text-center text-gray-400">
                <i class="mb-3 text-3xl fa-solid fa-calendar-xmark"></i>
                <p class="text-sm">No ${tab} appointments</p>
            </div>`;
        return;
    }

    container.innerHTML = filtered.map(b => `
        <div class="p-4 mb-3 border border-black/5 rounded-2xl bg-[#F6EFE6]/40 ring-1 ring-black/5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="font-semibold text-[#3C2F23]">${b.spa_name}</p>
                    <p class="text-xs text-gray-500">${b.branch_name} • ${b.service_type}</p>
                </div>
                <span class="px-2 py-1 text-[10px] font-semibold rounded-full ${statusBadge(b.status)}">
                    ${b.status.charAt(0).toUpperCase() + b.status.slice(1)}
                </span>
            </div>
            <div class="grid grid-cols-2 gap-2 mt-3 text-xs text-gray-600">
                <div class="flex items-center gap-1">
                    <i class="fa-solid fa-spa text-[#8B7355]"></i> ${b.treatment}
                </div>
                <div class="flex items-center gap-1">
                    <i class="fa-solid fa-user-nurse text-[#8B7355]"></i> ${b.therapist}
                </div>
                <div class="flex items-center gap-1">
                    <i class="fa-solid fa-calendar text-[#8B7355]"></i> ${b.date}
                </div>
                <div class="flex items-center gap-1">
                    <i class="fa-solid fa-clock text-[#8B7355]"></i>
                    ${formatTime(b.start_time)} – ${formatTime(b.end_time)}
                </div>
            </div>
        </div>
    `).join('');
}

function statusBadge(status) {
    const map = {
        reserved:  'bg-blue-100 text-blue-700',
        confirmed: 'bg-green-100 text-green-700',
        completed: 'bg-gray-100 text-gray-600',
        cancelled: 'bg-red-100 text-red-600',
        pending:   'bg-yellow-100 text-yellow-700',
    };
    return map[status] ?? 'bg-gray-100 text-gray-600';
}


// =====================================================
// MY SCHEDULE (CALENDAR)
// =====================================================
let scheduleBookings = [];
let calendarDate     = new Date();

function openScheduleModal() {
    document.getElementById('scheduleModal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    loadSchedule();
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

function loadSchedule() {
    fetch('/my-schedule')
        .then(r => r.json())
        .then(data => {
            scheduleBookings = data;
            renderCalendar();
        });
}

function changeMonth(dir) {
    calendarDate.setMonth(calendarDate.getMonth() + dir);
    renderCalendar();
    document.getElementById('selectedDayBookings').classList.add('hidden');
}

function renderCalendar() {
    const year  = calendarDate.getFullYear();
    const month = calendarDate.getMonth();
    const today = new Date().toISOString().split('T')[0];

    document.getElementById('calendarTitle').textContent =
        calendarDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

    const firstDay    = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const bookedDates = new Set(scheduleBookings.map(b => b.date_raw));
    const grid        = document.getElementById('calendarGrid');
    grid.innerHTML    = '';

    for (let i = 0; i < firstDay; i++) grid.innerHTML += `<div></div>`;

    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr   = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const isToday   = dateStr === today;
        const hasBooking = bookedDates.has(dateStr);
        const isPast    = dateStr < today;

        grid.innerHTML += `
            <button onclick="selectDay('${dateStr}')"
                class="relative flex flex-col items-center justify-center h-10 rounded-xl text-sm transition
                ${isToday   ? 'bg-[#8B7355] text-white font-bold' : ''}
                ${hasBooking && !isToday ? 'bg-[#F6EFE6] text-[#6F5430] font-semibold ring-1 ring-[#8B7355]/30' : ''}
                ${isPast && !isToday ? 'text-gray-300 cursor-default' : 'hover:bg-[#F6EFE6]'}
                ${!hasBooking && !isToday && !isPast ? 'text-gray-700' : ''}">
                ${d}
                ${hasBooking ? `<span class="absolute bottom-1 w-1 h-1 rounded-full ${isToday ? 'bg-white' : 'bg-[#8B7355]'}"></span>` : ''}
            </button>`;
    }
}

function selectDay(dateStr) {
    const dayBookings = scheduleBookings.filter(b => b.date_raw === dateStr);
    if (!dayBookings.length) return;

    const title = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', {
        weekday: 'long', month: 'long', day: 'numeric'
    });

    document.getElementById('selectedDayTitle').textContent = title;
    document.getElementById('selectedDayContent').innerHTML = dayBookings.map(b => `
        <div class="p-3 mb-3 border border-black/5 rounded-xl bg-[#F6EFE6]/50 ring-1 ring-black/5">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-[#3C2F23]">${b.spa_name}</p>
                <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full ${statusBadge(b.status)}">
                    ${b.status}
                </span>
            </div>
            <p class="mt-1 text-xs text-gray-500">${b.branch_name} • ${b.treatment}</p>
            <p class="mt-1 text-xs text-gray-500">
                <i class="fa-solid fa-clock text-[#8B7355]"></i>
                ${formatTime(b.start_time)} – ${formatTime(b.end_time)} • ${b.therapist}
            </p>
        </div>
    `).join('');

    document.getElementById('selectedDayBookings').classList.remove('hidden');
}

function formatTime(timeStr) {
    if (!timeStr) return 'N/A';
    const [hour, minute] = timeStr.split(':');
    const h    = parseInt(hour);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12  = h % 12 || 12;
    return `${h12}:${minute} ${ampm}`;
}


// =====================================================
// ESCAPE KEY
// =====================================================
window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (!spaModal?.classList.contains('hidden'))     closeSpaModal();
        if (!bookingModal?.classList.contains('hidden')) closeBookingModal();
    }
});
</script>

</body>
</html>

