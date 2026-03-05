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
                        <div class="flex items-center gap-6">

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
                            <div class="relative">
                                <button type="button"
                                    data-profile-btn
                                    class="flex items-center justify-center w-10 h-10 overflow-hidden rounded-full ring-1 ring-black/5">

                                    <div class="flex items-center justify-center w-10 h-10 bg-[#8B7355] text-white rounded-full">
                                        <i class="fa-solid fa-user"></i>
                                    </div>
                                </button>

                                <div id="profileDropdown"
                                    class="absolute right-0 hidden w-48 mt-2 bg-white divide-y divide-gray-100 rounded-md shadow-lg ring-1 ring-black ring-opacity-5">

                                    <div class="py-1">
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Profile
                                        </a>

                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Settings
                                        </a>

                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit"
                                                class="block w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100">
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

    <!-- ================= FEATURED SPAS (Dynamic from DB) ================= -->
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
                @php
                $branch = $spa->branches->first();
                $location = $branch?->location ?? 'No location yet';

                $thumb = $spa->logo
                    ? asset('storage/'.$spa->logo)
                    : asset('images/2nd.png');

                // ✅ mark first branch as main
                $branches = $spa->branches->values()->map(function($b, $i) {
                    return [
                        'id' => $b->id,
                        'name' => $b->name,
                        'location' => $b->location,
                        'is_main' => $i === 0,
                        'has_home_service' => (bool) $b->has_home_service,
                    ];
                });

                $spaPayload = [
                    'id' => $spa->id,
                    'name' => $spa->name,
                    'location' => $location,
                    'rating' => 0,
                    'reviews' => 0,
                    'tag' => 'Featured',
                    'treatments' => \App\Models\Treatment::withoutGlobalScopes()
                    ->where('spa_id', $spa->id)
                    ->get(['id', 'name', 'duration'])
                    ->toArray(),
                    'packages' => \App\Models\Package::withoutGlobalScopes()
                    ->where('spa_id', $spa->id)
                    ->get(['id', 'name', 'total_duration'])  // ← total_duration not duration
                    ->toArray(),
                    'price_note' => 'From ₱0',
                    'desc' => $spa->description ?? '',
                    'photos' => [$thumb],
                    'branches' => $branches,
                ];
            @endphp

                <button type="button"
                    class="w-full overflow-hidden text-left transition bg-white shadow-sm group rounded-3xl ring-1 ring-black/5 hover:shadow-2xl"
                    data-open-spa-modal
                    data-spa='@json($spaPayload)'>
                    <div class="relative overflow-hidden">
                        <img src="{{ $thumb }}" class="h-56 w-full object-cover transition duration-500 group-hover:scale-[1.04]" alt="{{ $spa->name }}">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-black/0 to-transparent"></div>
                    </div>

                    <div class="p-5">
                        <h3 class="text-[15px] font-semibold text-[#3C2F23] leading-tight">{{ $spa->name }}</h3>
                        <p class="mt-1 text-xs text-gray-500">{{ $location }}</p>
                        <p class="mt-3 text-sm text-gray-600 line-clamp-2">{{ $spa->description ?? 'No description yet.' }}</p>
                    </div>
                </button>
            @empty
                <div class="text-center text-gray-600 col-span-full">No spas found yet.</div>
            @endforelse
        </div>
    </div>
</section>

    <!-- ================= SPA MODAL ================= -->
    <div id="spaModal" class="fixed inset-0 z-[100] hidden">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" data-close-spa-modal></div>

        <!-- Modal Panel -->
        <div class="relative mx-auto w-[92%] max-w-4xl mt-10 sm:mt-16">
            <div class="overflow-hidden bg-white shadow-2xl rounded-3xl ring-1 ring-black/10">
                <!-- Header -->
                <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-black/5">
                <div class="min-w-0">
                    <h3 id="spaModalName" class="text-lg font-semibold text-[#3C2F23] truncate">Spa Name</h3>

                    <div class="flex flex-wrap items-center mt-1 text-xs text-gray-500 gap-x-2 gap-y-1">
                        <span id="spaModalMeta" class="truncate">Location • Rating</span>

                        <span class="text-gray-300">•</span>

                        <!-- ✅ Branch pill -->
                        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-[#F6EFE6] ring-1 ring-black/5">
                            <i class="fa-solid fa-location-dot text-[#8B7355] text-xs"></i>

                            <!-- This will show only if selected branch is main -->
                            <span id="spaModalBranchBadge"
                                class="hidden text-[10px] font-semibold tracking-[0.18em] uppercase text-[#6F5430]">
                                Main
                            </span>

                            <!-- ✅ Select wrapper (hide native arrow, use our chevron) -->
                            <div class="relative">
                                <select
                                    id="spaModalBranchSelect"
                                    class="appearance-none bg-transparent border-0 p-0 pr-6 text-xs font-semibold text-[#6F5430] focus:ring-0 focus:outline-none cursor-pointer"
                                >
                                    <option value="">Select branch</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button"
                        class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5"
                        data-close-spa-modal aria-label="Close">
                    <i class="text-lg text-gray-700 fa-solid fa-xmark"></i>
                </button>
            </div>

                <!-- Content -->
                <div class="grid gap-0 md:grid-cols-12">
                    <!-- Photos (Top/Left) -->
                    <div class="md:col-span-7 bg-black/5">
                        <div class="relative">
                            <img id="spaModalMainPhoto" src="" alt="Spa photo" class="w-full h-[320px] md:h-[420px] object-cover">

                            <!-- Prev/Next -->
                            <button type="button" id="spaPrevPhoto"
                                    class="absolute flex items-center justify-center w-10 h-10 transition -translate-y-1/2 rounded-full shadow left-3 top-1/2 bg-white/90 ring-1 ring-black/10 hover:bg-white">
                                <i class="text-gray-800 fa-solid fa-chevron-left"></i>
                            </button>

                            <button type="button" id="spaNextPhoto"
                                    class="absolute flex items-center justify-center w-10 h-10 transition -translate-y-1/2 rounded-full shadow right-3 top-1/2 bg-white/90 ring-1 ring-black/10 hover:bg-white">
                                <i class="text-gray-800 fa-solid fa-chevron-right"></i>
                            </button>

                            <!-- Counter -->
                            <div class="absolute bottom-3 right-3 px-3 py-1.5 text-xs font-semibold text-white bg-black/45 rounded-full ring-1 ring-white/10">
                                <span id="spaPhotoCounter">1 / 1</span>
                            </div>
                        </div>

                        <!-- Thumbnails -->
                        <div id="spaModalThumbs" class="flex gap-2 p-4 overflow-x-auto bg-white border-t border-black/5">
                            <!-- injected by JS -->
                        </div>
                    </div>

                    <!-- Info (Bottom/Right) -->
                    <div class="p-6 md:col-span-5">
                        <div class="flex items-center justify-between">
                            <div class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-[#6F5430] bg-[#F6EFE6] rounded-full ring-1 ring-black/5">
                                <i class="fa-solid fa-spa"></i>
                                <span id="spaModalTag">Featured</span>
                            </div>

                            <div class="text-sm text-[#3C2F23]">
                                <i class="fa-solid fa-spa  text-[#D2A85B]"></i>
                                <span id="spaModalRating" class="font-semibold">4.8</span>
                                <span id="spaModalReviews" class="text-gray-500">(0)</span>
                            </div>
                        </div>

                        <p id="spaModalDesc" class="mt-4 text-sm leading-relaxed text-gray-600">
                            Description here...
                        </p>

                        <div class="mt-6 p-4 rounded-2xl bg-[#F6EFE6]/70 ring-1 ring-black/5">
                            <p class="text-sm text-gray-700">
                                <span id="spaModalPrice" class="font-semibold text-[#3C2F23]">From ₱0</span>
                                <span class="text-gray-500"> / session</span>
                            </p>

                            <button
                            type="button"
                            id="openBookingModalBtn"
                            class="block w-full mt-4 text-center booking-btn text-white py-3 rounded-xl text-sm font-semibold shadow-md hover:shadow-lg transition active:translate-y-0.5">
                            Reserve An Appointment
                            </button>
                        </div>

                        <!-- Optional: amenities placeholders (layout only) -->
                        <div class="mt-6">
                            <p class="text-xs font-semibold tracking-wide text-gray-700 uppercase">What this spa offers</p>
                            <div class="grid grid-cols-2 gap-3 mt-3 text-sm text-gray-600">
                                <div class="flex items-center gap-2"><i class="fa-solid fa-bath text-[#8B7355]"></i> Clean Rooms</div>
                                <div class="flex items-center gap-2"><i class="fa-solid fa-user-nurse text-[#8B7355]"></i> Pro Therapists</div>
                                <div class="flex items-center gap-2"><i class="fa-solid fa-mug-hot text-[#8B7355]"></i> Welcome Tea</div>
                                <div class="flex items-center gap-2"><i class="fa-solid fa-lock text-[#8B7355]"></i> Safe & Private</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile safe bottom spacing -->
            <div class="h-10"></div>
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
    const onScroll = () => {
        if (window.scrollY > 10) nav.classList.add('nav-scrolled');
        else nav.classList.remove('nav-scrolled');
    };
    window.addEventListener('scroll', onScroll);
    onScroll();

    // ---------------- Profile dropdown ----------------
    const profileBtn = document.querySelector('[data-profile-btn]');
    const profileDropdown = document.getElementById('profileDropdown');
    profileBtn?.addEventListener('click', () => profileDropdown?.classList.toggle('hidden'));

    // ---------------- Shared state ----------------
    let selectedSpa = null;
    let preferredBranchId = null;

    // ---------------- SPA MODAL ----------------
    const spaModal = document.getElementById('spaModal');
    const openBtns = document.querySelectorAll('[data-open-spa-modal]');
    const closeSpaBtns = document.querySelectorAll('[data-close-spa-modal]');

    const elName = document.getElementById('spaModalName');
    const elMeta = document.getElementById('spaModalMeta');
    const elTag = document.getElementById('spaModalTag');
    const elRating = document.getElementById('spaModalRating');
    const elReviews = document.getElementById('spaModalReviews');
    const elDesc = document.getElementById('spaModalDesc');
    const elPrice = document.getElementById('spaModalPrice');

    const elMainPhoto = document.getElementById('spaModalMainPhoto');
    const elThumbs = document.getElementById('spaModalThumbs');
    const elCounter = document.getElementById('spaPhotoCounter');
    const prevBtn = document.getElementById('spaPrevPhoto');
    const nextBtn = document.getElementById('spaNextPhoto');

    const spaModalBranchSelect = document.getElementById('spaModalBranchSelect');
    const spaModalBranchBadge = document.getElementById('spaModalBranchBadge');

    let photos = [];
    let photoIndex = 0;

    function setPhoto(i) {
        if (!photos.length) return;
        photoIndex = (i + photos.length) % photos.length;
        elMainPhoto.src = photos[photoIndex];
        elCounter.textContent = `${photoIndex + 1} / ${photos.length}`;

        [...elThumbs.querySelectorAll('button')].forEach((b, idx) => {
            b.classList.toggle('ring-2', idx === photoIndex);
            b.classList.toggle('ring-[#8B7355]', idx === photoIndex);
        });
    }

    function setMainBadge(isMain) {
        if (!spaModalBranchBadge) return;
        if (isMain) spaModalBranchBadge.classList.remove('hidden');
        else spaModalBranchBadge.classList.add('hidden');
    }

    function fillSpaBranchDropdown(data) {
        if (!spaModalBranchSelect) return;

        const branches = Array.isArray(data.branches) ? data.branches : [];
        spaModalBranchSelect.innerHTML = `<option value="">Select branch</option>`;

        branches.forEach((b) => {
            const opt = document.createElement('option');
            opt.value = String(b.id);
            opt.textContent = `${b.name} — ${b.location}`;
            opt.dataset.main = b.is_main ? '1' : '0';
            spaModalBranchSelect.appendChild(opt);
        });

        if (branches.length) {
            preferredBranchId = String(branches[0].id);
            spaModalBranchSelect.value = preferredBranchId;
            elMeta.textContent = `${branches[0].location ?? ''} • ${data.rating ?? '-'} ★`;
            setMainBadge(!!branches[0].is_main);
        } else {
            preferredBranchId = null;
            setMainBadge(false);
        }
    }

    function openSpaModal(data) {
        selectedSpa = data;
        preferredBranchId = null;

        elName.textContent = data.name ?? 'Spa';
        elMeta.textContent = `${data.location ?? ''} • ${data.rating ?? '-'} ★`;
        elTag.textContent = data.tag ?? 'Featured';
        elRating.textContent = (data.rating ?? '-');
        elReviews.textContent = `(${data.reviews ?? 0})`;
        elDesc.textContent = data.desc ?? '';
        elPrice.textContent = data.price_note ?? '';

        photos = Array.isArray(data.photos) ? data.photos : [];
        elThumbs.innerHTML = '';

        photos.forEach((src, idx) => {
            const thumb = document.createElement('button');
            thumb.type = 'button';
            thumb.className = 'shrink-0 w-20 h-14 rounded-xl overflow-hidden ring-1 ring-black/10 hover:opacity-90 transition';
            thumb.innerHTML = `<img src="${src}" class="object-cover w-full h-full" alt="thumb">`;
            thumb.addEventListener('click', () => setPhoto(idx));
            elThumbs.appendChild(thumb);
        });

        setPhoto(0);
        fillSpaBranchDropdown(data);

        spaModal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeSpaModal() {
        spaModal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    openBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const raw = btn.getAttribute('data-spa');
            try {
                const data = JSON.parse(raw);
                openSpaModal(data);
            } catch (e) {
                console.error('Invalid spa data', e);
            }
        });
    });

    closeSpaBtns.forEach(btn => btn.addEventListener('click', closeSpaModal));
    prevBtn?.addEventListener('click', () => setPhoto(photoIndex - 1));
    nextBtn?.addEventListener('click', () => setPhoto(photoIndex + 1));

    spaModalBranchSelect?.addEventListener('change', () => {
        if (!selectedSpa) return;

        const branches = Array.isArray(selectedSpa.branches) ? selectedSpa.branches : [];
        const chosenId = spaModalBranchSelect.value ? String(spaModalBranchSelect.value) : null;
        preferredBranchId = chosenId;

        const chosen = branches.find(b => String(b.id) === chosenId);
        if (chosen) {
            elMeta.textContent = `${chosen.location ?? ''} • ${selectedSpa.rating ?? '-'} ★`;
            setMainBadge(!!chosen.is_main);
        } else {
            setMainBadge(false);
        }
    });

    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !spaModal.classList.contains('hidden')) closeSpaModal();
    });

    // ---------------- BOOKING MODAL ----------------
    const bookingModal = document.getElementById('bookingModal');
    const openBookingBtn = document.getElementById('openBookingModalBtn');
    const closeBookingBtns = document.querySelectorAll('[data-close-booking-modal]');

    const bookingSpaMeta = document.getElementById('bookingSpaMeta');
    const bookingSpaIdInput = document.getElementById('bookingSpaIdInput');
    const bookingBranchIdInput = document.getElementById('bookingBranchIdInput');

    function populateTreatments() {
        const treatmentSelect = document.querySelector('select[name="treatment"]');
        if (!treatmentSelect || !selectedSpa) return;

        treatmentSelect.innerHTML = '<option value="">Select treatment</option>';

        (selectedSpa.treatments ?? []).forEach(t => {
            const opt = document.createElement('option');
            opt.value = `treatment_${t.id}`;
            opt.textContent = t.name;
            treatmentSelect.appendChild(opt);
        });

        (selectedSpa.packages ?? []).forEach(p => {
            const opt = document.createElement('option');
            opt.value = `package_${p.id}`;
            opt.textContent = `${p.name} (Package)`;
            treatmentSelect.appendChild(opt);
        });
    }

    function populateBranchDropdown(filterHomeService = false) {
        const branchSelect = document.getElementById('bookingBranchSelect');
        if (!branchSelect || !selectedSpa) return;

        const branches = Array.isArray(selectedSpa.branches) ? selectedSpa.branches : [];
        const filtered = filterHomeService
            ? branches.filter(b => b.has_home_service)
            : branches;

        branchSelect.innerHTML = '<option value="">Select branch</option>';

        filtered.forEach(b => {
            const opt = document.createElement('option');
            opt.value = String(b.id);
            opt.textContent = `${b.name} — ${b.location}`;
            branchSelect.appendChild(opt);
        });

        // ✅ Always auto-select first and sync hidden input
        if (filtered.length) {
            branchSelect.value = String(filtered[0].id);
            if (bookingBranchIdInput) bookingBranchIdInput.value = String(filtered[0].id);
        } else {
            branchSelect.value = '';
            if (bookingBranchIdInput) bookingBranchIdInput.value = '';
        }

        return filtered;
    }

    function openBookingModal() {
        if (!selectedSpa) return;

        const branches = Array.isArray(selectedSpa.branches) ? selectedSpa.branches : [];
        const chosen = preferredBranchId
            ? branches.find(b => String(b.id) === String(preferredBranchId))
            : (branches[0] ?? null);

        bookingSpaMeta.textContent = `${selectedSpa.name ?? 'Spa'} • ${chosen?.location ?? selectedSpa.location ?? ''}`;

        // ✅ Set spa_id immediately
        if (bookingSpaIdInput) bookingSpaIdInput.value = selectedSpa.id ?? '';

        // ✅ Populate treatments
        populateTreatments();

        // ✅ Reset service type + address
        const serviceTypeSelect = document.getElementById('bookingServiceType');
        const addressWrapper = document.getElementById('addressWrapper');
        const addressInput = document.getElementById('bookingAddressInput');

        if (serviceTypeSelect) serviceTypeSelect.value = '';
        if (addressWrapper) addressWrapper.classList.add('hidden');
        if (addressInput) {
            addressInput.required = false;
            addressInput.value = '';
        }

        // ✅ Set min date to today to prevent past date selection
        const dateInput = document.getElementById('bookingDateInput');
        if (dateInput) {
            const today = new Date().toISOString().split('T')[0];
            dateInput.min = today;
            dateInput.value = '';
        }

        // ✅ Populate branch dropdown (all branches by default) and set hidden input
        populateBranchDropdown(false);

        bookingModal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    // ✅ Service type change — filter branches + toggle address
    document.getElementById('bookingServiceType')?.addEventListener('change', function () {
        const selected = this.value;
        const addressWrapper = document.getElementById('addressWrapper');
        const addressInput = document.getElementById('bookingAddressInput');

        // Toggle address field
        if (selected === 'in_home') {
            addressWrapper?.classList.remove('hidden');
            if (addressInput) addressInput.required = true;
        } else {
            addressWrapper?.classList.add('hidden');
            if (addressInput) {
                addressInput.required = false;
                addressInput.value = '';
            }
        }

        // ✅ Filter branches and auto-sync hidden branch_id input
        const filtered = populateBranchDropdown(selected === 'in_home');

        // ✅ Warn if no home service branches available
        if (selected === 'in_home' && (!filtered || filtered.length === 0)) {
            const branchSelect = document.getElementById('bookingBranchSelect');
            if (branchSelect) {
                branchSelect.innerHTML = '<option value="">No branches offer home service</option>';
            }
            if (bookingBranchIdInput) bookingBranchIdInput.value = '';
        }
    });

    // ✅ Manual branch change — sync hidden input
    document.getElementById('bookingBranchSelect')?.addEventListener('change', function () {
        if (bookingBranchIdInput) bookingBranchIdInput.value = this.value;
    });

    function closeBookingModal() {
        bookingModal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    openBookingBtn?.addEventListener('click', openBookingModal);
    closeBookingBtns.forEach(b => b.addEventListener('click', closeBookingModal));

    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && bookingModal && !bookingModal.classList.contains('hidden')) {
            closeBookingModal();
        }
    });

    // ================= MY APPOINTMENTS =================
let allAppointments = [];
let currentTab = 'upcoming';

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
        allAppointments.filter(b => ['reserved','confirmed'].includes(b.status) && b.date_raw >= today).length;
    document.getElementById('tab-count-past').textContent =
        allAppointments.filter(b => b.status === 'completed' || (['reserved','confirmed'].includes(b.status) && b.date_raw < today)).length;
    document.getElementById('tab-count-cancelled').textContent =
        allAppointments.filter(b => b.status === 'cancelled').length;
}

function switchTab(tab) {
    currentTab = tab;
    ['upcoming','past','cancelled'].forEach(t => {
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
    const today = new Date().toISOString().split('T')[0];
    let filtered = [];

    if (tab === 'upcoming') {
        filtered = allAppointments.filter(b =>
            ['reserved','confirmed'].includes(b.status) && b.date_raw >= today);
    } else if (tab === 'past') {
        filtered = allAppointments.filter(b =>
            b.status === 'completed' || (['reserved','confirmed'].includes(b.status) && b.date_raw < today));
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
                    <i class="fa-solid fa-spa text-[#8B7355]"></i>
                    ${b.treatment}
                </div>
                <div class="flex items-center gap-1">
                    <i class="fa-solid fa-user-nurse text-[#8B7355]"></i>
                    ${b.therapist}
                </div>
                <div class="flex items-center gap-1">
                    <i class="fa-solid fa-calendar text-[#8B7355]"></i>
                    ${b.date}
                </div>
                <div class="flex items-center gap-1">
                    <i class="fa-solid fa-clock text-[#8B7355]"></i>
                    ${formatTime(b.start_time)} – ${formatTime(b.end_time)} • ${b.therapist}
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

// ================= MY SCHEDULE (CALENDAR) =================
let scheduleBookings = [];
let calendarDate = new Date();

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
    const year = calendarDate.getFullYear();
    const month = calendarDate.getMonth();
    const today = new Date().toISOString().split('T')[0];

    document.getElementById('calendarTitle').textContent =
        calendarDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    // Get dates that have bookings
    const bookedDates = new Set(scheduleBookings.map(b => b.date_raw));

    const grid = document.getElementById('calendarGrid');
    grid.innerHTML = '';

    // Empty cells before first day
    for (let i = 0; i < firstDay; i++) {
        grid.innerHTML += `<div></div>`;
    }

    // Day cells
    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${year}-${String(month + 1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const isToday = dateStr === today;
        const hasBooking = bookedDates.has(dateStr);
        const isPast = dateStr < today;

        grid.innerHTML += `
            <button onclick="selectDay('${dateStr}')"
                class="relative flex flex-col items-center justify-center h-10 rounded-xl text-sm transition
                ${isToday ? 'bg-[#8B7355] text-white font-bold' : ''}
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
    const h = parseInt(hour);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 || 12;
    return `${h12}:${minute} ${ampm}`;
}

</script>

</body>
</html>
