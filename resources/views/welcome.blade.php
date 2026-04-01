<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Levictas | Spa & Wellness</title>

    @vite(['resources/css/app.css','resources/css/landing.css', 'resources/js/app.js', 'resources/js/welcome.js'])

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">

</head>

{{-- data-fallback-image lets welcome.js read the asset URL without needing inline Blade --}}
<body class="bg-[#F6EFE6] text-gray-800 selection:bg-[#D2A85B]/30 selection:text-[#3C2F23]"
      data-fallback-image="{{ asset('storage/branch_profiles/emptyspa.jpg') }}">

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

                        <div class="relative" id="profileDropdownWrapper">
                            <button type="button" id="profileDropdownBtn"
                                class="flex items-center gap-2 px-3 py-2 transition rounded-full hover:bg-white/60 ring-1 ring-black/5">
                                <div class="flex items-center justify-center w-8 h-8 bg-[#8B7355] text-white rounded-full text-xs font-semibold leading-none shrink-0">
                                    {{ strtoupper(substr(auth()->user()?->name ?? 'Guest', 0, 1)) }}
                                </div>
                                <i class="fa-solid fa-chevron-down text-[10px] text-gray-400 transition-transform duration-200" id="profileChevron"></i>
                            </button>

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
    <div id="profileModal" class="fixed inset-0 z-[130] hidden">
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeProfileModal()"></div>
        <div class="relative mx-auto w-[92%] max-w-lg mt-10 sm:mt-16">
            <div class="overflow-hidden bg-white shadow-2xl rounded-3xl ring-1 ring-black/10">
                <div class="relative px-6 py-8 bg-gradient-to-br from-[#6F5430] to-[#8B7355] text-white text-center">
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
                <div class="p-6 space-y-4">
                    <div class="flex items-center gap-4 p-4 rounded-2xl bg-[#F6EFE6]/50 ring-1 ring-black/5">
                        <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-[#8B7355]/10">
                            <i class="fa-solid fa-user text-[#8B7355] text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[11px] text-gray-400 uppercase tracking-wide">Full Name</p>
                            <p class="text-sm font-semibold text-[#3C2F23] truncate">{{ auth()->user()?->name ?? 'Guest' }}</p>
                        </div>
                    </div>
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
                                    class="text-[#8B7355] hover:text-[#6F5430] transition flex-shrink-0">
                                    <i id="emailToggleIcon" class="text-xs fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
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
                <div class="px-6 pb-6">
                    <button onclick="closeProfileModal()"
                        class="w-full py-3 rounded-xl text-sm font-semibold text-white booking-btn shadow-md hover:shadow-lg transition active:translate-y-0.5">
                        Close
                    </button>
                </div>
            </div>
            <div class="h-10"></div>
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

            <form action="{{ url('/') }}" method="GET"
                  class="grid max-w-4xl grid-cols-1 gap-4 p-4 mx-auto mt-10 shadow-2xl bg-white/90 rounded-2xl ring-1 ring-black/5 md:grid-cols-12">
                <div class="flex items-center gap-3 px-4 py-3 bg-white border border-black/10 md:col-span-8 rounded-xl">
                    <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-[#F6EFE6] ring-1 ring-black/5">
                        <i class="fa-solid fa-location-dot text-[#8B7355]"></i>
                    </span>
                    <input
                        type="text"
                        name="city"
                        value="{{ $city ?? '' }}"
                        placeholder="Search by city or location..."
                        class="w-full text-sm bg-transparent border-0 focus:ring-0 soft-ring placeholder:text-gray-400"
                        autocomplete="off"
                    >
                    @if(!empty($city))
                        <a href="{{ url('/') }}" class="flex-shrink-0 text-gray-400 transition hover:text-red-400" title="Clear search">
                            <i class="text-sm fa-solid fa-xmark"></i>
                        </a>
                    @endif
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
                <div class="flex items-center justify-between px-6 py-4 border-b border-black/5">
                    <h3 class="text-lg font-semibold text-[#3C2F23]">My Appointments</h3>
                    <button onclick="closeAppointmentsModal()"
                        class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5">
                        <i class="text-lg text-gray-700 fa-solid fa-xmark"></i>
                    </button>
                </div>
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
                <div class="p-6">
                    <div class="grid grid-cols-7 mb-2">
                        @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
                        <div class="py-2 text-xs font-semibold text-center text-gray-400">{{ $day }}</div>
                        @endforeach
                    </div>
                    <div id="calendarGrid" class="grid grid-cols-7 gap-1"></div>
                    <div id="selectedDayBookings" class="hidden mt-6 space-y-3">
                        <h4 id="selectedDayTitle" class="text-sm font-semibold text-[#3C2F23]"></h4>
                        <div id="selectedDayContent"></div>
                    </div>
                </div>
            </div>
            <div class="h-10"></div>
        </div>
    </div>

    <!-- ================= BOOKING DETAILS MODAL ================= -->
    <div id="bookingDetailsModal" class="fixed inset-0 z-[125] hidden">
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeBookingDetailsModal()"></div>
        <div class="relative mx-auto w-[92%] max-w-lg mt-10 sm:mt-16">
            <div class="overflow-hidden bg-white shadow-2xl rounded-3xl ring-1 ring-black/10">
                <div class="flex items-center justify-between px-6 py-4 border-b border-black/5">
                    <h3 class="text-lg font-semibold text-[#3C2F23]">Booking Details</h3>
                    <button type="button" onclick="closeBookingDetailsModal()"
                        class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5">
                        <i class="text-lg text-gray-700 fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <!-- Spa Info -->
                    <div class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60">
                        <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 bg-white rounded-lg ring-1 ring-black/5">
                            <i class="fa-solid fa-spa text-[#8B7355] text-sm"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Spa & Branch</p>
                            <p id="detailSpaName" class="text-sm font-semibold text-[#3C2F23]"></p>
                        </div>
                    </div>
                    <!-- Treatment -->
                    <div class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60">
                        <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 bg-white rounded-lg ring-1 ring-black/5">
                            <i class="fa-solid fa-list-check text-[#8B7355] text-sm"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Treatment</p>
                            <p id="detailTreatment" class="text-sm font-semibold text-[#3C2F23]"></p>
                        </div>
                    </div>
                    <!-- Date & Time -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60">
                            <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 bg-white rounded-lg ring-1 ring-black/5">
                                <i class="fa-solid fa-calendar text-[#8B7355] text-sm"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Date</p>
                                <p id="detailDate" class="text-sm font-semibold text-[#3C2F23]"></p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60">
                            <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 bg-white rounded-lg ring-1 ring-black/5">
                                <i class="fa-solid fa-clock text-[#8B7355] text-sm"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Time</p>
                                <p id="detailTime" class="text-sm font-semibold text-[#3C2F23]"></p>
                            </div>
                        </div>
                    </div>
                    <!-- Therapist -->
                    <div class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60">
                        <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 bg-white rounded-lg ring-1 ring-black/5">
                            <i class="fa-solid fa-user-nurse text-[#8B7355] text-sm"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Therapist</p>
                            <p id="detailTherapist" class="text-sm font-semibold text-[#3C2F23]"></p>
                        </div>
                    </div>
                    <!-- Status -->
                    <div class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60">
                        <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 bg-white rounded-lg ring-1 ring-black/5">
                            <i class="fa-solid fa-circle-info text-[#8B7355] text-sm"></i>
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
                        class="w-full py-3 rounded-xl text-sm font-semibold text-[#8B7355] border border-[#8B7355] hover:bg-[#F6EFE6] transition">
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
            <div class="overflow-hidden bg-white shadow-2xl rounded-3xl ring-1 ring-black/10">
                <div class="flex items-center justify-between px-6 py-4 border-b border-black/5">
                    <div>
                        <h3 class="text-lg font-semibold text-[#3C2F23]">Request Reschedule</h3>
                        <p class="mt-0.5 text-xs text-gray-500">Please provide a valid reason for rescheduling.</p>
                    </div>
                    <button type="button" onclick="closeRescheduleModal()"
                        class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5">
                        <i class="text-lg text-gray-700 fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <input type="hidden" id="rescheduleBookingId">

                    <!-- Current Schedule -->
                    <div class="p-3 rounded-xl bg-[#F6EFE6]/60 ring-1 ring-black/5">
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Current Schedule</p>
                        <p id="rescheduleCurrentSchedule" class="text-sm text-[#3C2F23] font-medium"></p>
                    </div>

                    <!-- New Date -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600">
                            New Preferred Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" id="rescheduleDate"
                            class="w-full mt-1 rounded-xl border-black/10 ring-1 ring-black/5 focus:ring-2 focus:ring-[#8B7355]/40"
                            required>
                    </div>

                    <!-- New Time -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600">
                            New Preferred Time <span class="text-red-500">*</span>
                        </label>
                        <input type="time" id="rescheduleTime"
                            class="w-full mt-1 rounded-xl border-black/10 ring-1 ring-black/5 focus:ring-2 focus:ring-[#8B7355]/40"
                            required>
                        {{-- ADD THIS LINE --}}
                        <p id="rescheduleTimeError" class="hidden mt-1 text-[11px] text-red-500">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <span id="rescheduleTimeErrorText"></span>
                        </p>
                    </div>

                    <!-- Reason -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600">
                            Reason for Rescheduling <span class="text-red-500">*</span>
                        </label>
                        <textarea id="rescheduleReason" rows="4"
                            placeholder="Please explain why you need to reschedule (minimum 10 characters)..."
                            class="w-full mt-1 rounded-xl border-black/10 ring-1 ring-black/5 focus:ring-2 focus:ring-[#8B7355]/40 text-sm resize-none"
                            required></textarea>
                        <p id="rescheduleReasonCount" class="mt-1 text-[11px] text-gray-400">0 / 1000 characters</p>
                    </div>

                    <!-- Error message -->
                    <div id="rescheduleError" class="hidden p-3 text-sm text-red-600 rounded-xl bg-red-50 ring-1 ring-red-200">
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

    <!-- ================= FEATURED SPAS ================= -->
    <section class="py-5">
        <div class="px-6 mx-auto mt-5 max-w-7xl">
            <div class="text-center">
                <div class="flex items-center justify-center gap-6">
                    <span class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355]"></span>
                    <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] font-semibold">Featured Spas</h2>
                    <span class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355]"></span>
                </div>
                <p class="mt-3 text-sm text-gray-600">
                    @if(!empty($city))
                        Featured spas in <span class="font-semibold text-[#8B7355]">{{ $city }}</span>
                    @else
                        Curated picks for a premium relaxation experience.
                    @endif
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 mt-5 sm:grid-cols-2 lg:grid-cols-4">
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

                                $branchTreatments = \App\Models\Treatment::withoutGlobalScopes()
                                    ->where('branch_id', $branch->id)
                                    ->where('spa_id', $spa->id)
                                    ->get()
                                    ->map(fn($t) => [
                                        'id'           => $t->id,
                                        'name'         => $t->name,
                                        'price'        => $t->price,
                                        'duration'     => $t->duration,
                                        'service_type' => $t->service_type,
                                        'type'         => 'treatment',
                                    ])
                                    ->values()
                                    ->toArray();

                                $branchPackages = \App\Models\Package::withoutGlobalScopes()
                                    ->where('branch_id', $branch->id)
                                    ->where('spa_id', $spa->id)
                                    ->get()
                                    ->map(fn($p) => [
                                        'id'           => $p->id,
                                        'name'         => $p->name,
                                        'price'        => $p->price ?? null,
                                        'duration'     => $p->duration ?? null,
                                        'service_type' => $p->service_type ?? 'in_branch_only',
                                        'type'         => 'package',
                                    ])
                                    ->values()
                                    ->toArray();

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
                                    'amenities'       => $profile->amenities ?? [],
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
                                        $addr = $spaPayload['address'] ?? '';
                                        $addrParts = array_map('trim', explode(',', $addr));
                                        $addrSummary = count($addrParts) >= 3
                                            ? implode(', ', array_slice(array_slice($addrParts, 0, count($addrParts) - 2), -3))
                                            : ($addr ?: 'Location unavailable');
                                    @endphp
                                    <p class="mt-1 text-xs text-gray-500">{{ $addrSummary }}</p>
                                    <p class="mt-3 text-sm text-gray-600 line-clamp-2">{{ $spaPayload['desc'] ?? 'No description yet.' }}</p>
                                </div>
                            </button>
                        @endif
                    @endforeach
                @empty
                @endforelse

                @if($featuredCount === 0)
                    <div class="py-16 text-center col-span-full">
                        <div class="flex items-center justify-center w-14 h-14 mx-auto mb-4 rounded-2xl bg-[#F6EFE6] ring-1 ring-black/5">
                            <i class="fa-solid fa-star text-xl text-[#8B7355]"></i>
                        </div>
                        <p class="font-semibold text-[#3C2F23]">No featured spas found</p>
                        <p class="mt-1 text-sm text-gray-500">
                            @if(!empty($city))
                                No featured spas match "{{ $city }}". Try a different location.
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
                        <span class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355]"></span>
                        <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] font-semibold">Other Spas in Cavite</h2>
                        <span class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355]"></span>
                    </div>
                    <p class="mt-3 text-sm text-gray-600">
                        @if(!empty($city))
                            Verified spas in <span class="font-semibold text-[#8B7355]">{{ $city }}</span>
                        @else
                            Explore more verified wellness destinations.
                        @endif
                    </p>
                </div>

                @php
                    $hasListedBasic = $basicSpas->flatMap->branches->contains(fn($b) => $b->profile?->is_listed);
                @endphp

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

                                        $branchTreatments = \App\Models\Treatment::withoutGlobalScopes()
                                            ->where('branch_id', $branch->id)
                                            ->where('spa_id', $spa->id)
                                            ->get()
                                            ->map(fn($t) => [
                                                'id'           => $t->id,
                                                'name'         => $t->name,
                                                'price'        => $t->price,
                                                'duration'     => $t->duration,
                                                'service_type' => $t->service_type,
                                                'type'         => 'treatment',
                                            ])
                                            ->values()
                                            ->toArray();

                                        $branchPackages = \App\Models\Package::withoutGlobalScopes()
                                            ->where('branch_id', $branch->id)
                                            ->where('spa_id', $spa->id)
                                            ->get()
                                            ->map(fn($p) => [
                                                'id'           => $p->id,
                                                'name'         => $p->name,
                                                'price'        => $p->price ?? null,
                                                'duration'     => $p->duration ?? null,
                                                'service_type' => $p->service_type ?? 'in_branch_only',
                                                'type'         => 'package',
                                            ])
                                            ->values()
                                            ->toArray();

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
                                            'amenities'       => $profile->amenities ?? [],
                                        ];
                                    @endphp

                                    <button type="button"
                                        class="w-full overflow-hidden text-left transition bg-white shadow-sm group rounded-3xl ring-1 ring-black/5 hover:shadow-xl"
                                        data-open-spa-modal
                                        data-spa='@json($spaPayload)'>
                                        <div class="relative overflow-hidden">
                                            <img src="{{ $coverPhoto }}" class="h-48 w-full object-cover transition duration-500 group-hover:scale-[1.04]" alt="{{ $spa->name }}">
                                            <div class="absolute inset-0 bg-gradient-to-t from-black/35 via-black/0 to-transparent"></div>
                                            <div class="absolute top-3 left-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/80 text-[#6F5430] text-[11px] font-semibold backdrop-blur-sm ring-1 ring-black/5">
                                                <i class="fa-solid fa-spa text-[#8B7355] text-[10px]"></i>
                                                Verified
                                            </div>
                                        </div>
                                        <div class="p-4">
                                            <h3 class="text-[15px] font-semibold text-[#3C2F23] leading-tight">{{ $spa->name }}</h3>
                                            @php
                                                $addr = $spaPayload['address'] ?? '';
                                                $addrParts = array_map('trim', explode(',', $addr));
                                                $addrSummary = count($addrParts) >= 3
                                                    ? implode(', ', array_slice(array_slice($addrParts, 0, count($addrParts) - 2), -3))
                                                    : ($addr ?: 'Location unavailable');
                                            @endphp
                                            <p class="mt-1 text-xs text-gray-900">{{ $addrSummary }}</p>
                                            @if($lowestPrice)
                                                <p class="mt-2 text-xs font-medium text-[#8B7355]">
                                                    Starts at ₱{{ number_format($lowestPrice, 2) }}
                                                </p>
                                            @endif
                                            <p class="mt-2 text-sm text-gray-500 line-clamp-2">
                                                {{ $spaPayload['desc'] ?: 'No description yet.' }}
                                            </p>
                                        </div>
                                    </button>
                                @endif
                            @endforeach
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-16 mt-12 border border-dashed border-[#C4A97D]/40 rounded-3xl bg-white/50">
                        <div class="flex items-center justify-center w-16 h-16 mb-5 rounded-2xl bg-[#F6EFE6] ring-1 ring-black/5">
                            <i class="fa-solid fa-spa text-2xl text-[#8B7355]"></i>
                        </div>
                        @if(!empty($city))
                            <h3 class="text-lg font-semibold font-['Playfair_Display'] text-[#3C2F23]">No spas found in "{{ $city }}"</h3>
                            <p class="max-w-xs mt-2 text-sm text-center text-gray-500">
                                Try searching a nearby city or browse all available spas.
                            </p>
                            <a href="{{ url('/') }}"
                                class="inline-flex items-center gap-2 mt-6 px-6 py-2.5 text-sm font-semibold text-white rounded-xl booking-btn shadow-md hover:shadow-lg transition active:translate-y-0.5">
                                <i class="text-xs fa-solid fa-arrow-left"></i>
                                Browse All Spas
                            </a>
                        @else
                            <h3 class="text-lg font-semibold font-['Playfair_Display'] text-[#3C2F23]">No spas listed yet</h3>
                            <p class="max-w-xs mt-2 text-sm text-center text-gray-500">
                                Be the first to list your spa and reach customers looking for wellness experiences.
                            </p>
                            <a href="{{ route('register.business') }}"
                                class="inline-flex items-center gap-2 mt-6 px-6 py-2.5 text-sm font-semibold text-white rounded-xl booking-btn shadow-md hover:shadow-lg transition active:translate-y-0.5">
                                <i class="text-xs fa-solid fa-plus"></i>
                                List Your Spa
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </section>
    </section>

    <!-- ================= SPA MODAL ================= -->
    <div id="spaModal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" data-close-spa-modal></div>
        <div class="relative mx-auto w-[92%] max-w-5xl mt-8 mb-8">
            <div class="overflow-hidden bg-white shadow-2xl rounded-3xl flex flex-col max-h-[90vh]">
                <div class="sticky top-0 z-10 flex items-center justify-between px-6 py-4 border-b bg-white/95 backdrop-blur-sm border-black/5">
                    <div>
                        <h3 id="spaModalName" class="text-2xl font-['Playfair_Display'] font-bold tracking-tight text-[#3C2F23]">Spa Name</h3>
                        <div class="flex items-center gap-1.5 text-sm text-gray-500 mt-1">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#F6EFE6] text-[#6F5430] text-xs font-semibold ring-1 ring-[#8B7355]/20">
                                <i class="fa-solid fa-star text-[#D2A85B] text-[10px]"></i>
                                <span id="spaModalTag">Featured Spa</span>
                            </span>
                            <span class="text-gray-300">·</span>
                            <i class="fa-solid fa-location-dot text-[#8B7355] text-xs"></i>
                            <span id="spaModalAddressSummary" class="font-medium text-[#6F5430] underline underline-offset-2 decoration-dotted">Location</span>
                        </div>
                    </div>
                    <button data-close-spa-modal
                        class="flex items-center justify-center w-9 h-9 text-gray-500 transition rounded-xl hover:bg-[#F6EFE6] hover:text-[#3C2F23] ring-1 ring-black/5">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="overflow-y-auto">
                    <div class="p-6">
                        <div class="grid grid-cols-4 grid-rows-2 gap-2 h-[380px] rounded-2xl overflow-hidden">
                            <div class="relative col-span-2 row-span-2 bg-gray-100 cursor-pointer group">
                                <img id="spaModalMainPhoto" src="" class="object-cover w-full h-full transition duration-500 group-hover:scale-[1.02]">
                                <div class="absolute inset-0 transition opacity-0 bg-gradient-to-t from-black/20 to-transparent group-hover:opacity-100"></div>
                            </div>
                            <div class="col-span-1 row-span-1 overflow-hidden bg-gray-100">
                                <img id="gallery_1" class="object-cover w-full h-full transition duration-300 cursor-pointer hover:scale-105">
                            </div>
                            <div class="col-span-1 row-span-1 overflow-hidden bg-gray-100">
                                <img id="gallery_2" class="object-cover w-full h-full transition duration-300 cursor-pointer hover:scale-105">
                            </div>
                            <div class="col-span-1 row-span-1 overflow-hidden bg-gray-100">
                                <img id="gallery_3" class="object-cover w-full h-full transition duration-300 cursor-pointer hover:scale-105">
                            </div>
                            <div class="relative col-span-1 row-span-1 overflow-hidden bg-gray-100 cursor-pointer group">
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
                                <h4 class="mb-2 text-xl font-['Playfair_Display'] font-semibold text-[#3C2F23]">About this spa</h4>
                                <p id="spaModalDesc" class="text-sm leading-relaxed text-gray-600"></p>
                            </div>
                            <hr class="border-[#E8DDD0]">
                            <div>
                                <h4 class="mb-4 text-xl font-['Playfair_Display'] font-semibold text-[#3C2F23]">What this place offers</h4>
                                <div id="spaModalAmenities">
                                    <p class="text-sm italic text-gray-400">No amenities listed yet.</p>
                                </div>
                            </div>
                        </div>
                        <div class="md:col-span-1">
                            <div class="sticky top-4 p-5 space-y-3 border border-[#E8DDD0] shadow-sm rounded-2xl bg-[#FDFAF6]">
                                <div class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-white ring-1 ring-black/5 flex-shrink-0 mt-0.5">
                                        <i class="fa-solid fa-location-dot text-[#8B7355] text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Address</p>
                                        <p id="spaModalAddress" class="mt-0.5 text-sm text-[#3C2F23] leading-snug"></p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-white ring-1 ring-black/5 flex-shrink-0 mt-0.5">
                                        <i class="fa-solid fa-phone text-[#8B7355] text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Contact</p>
                                        <p id="spaModalPhone" class="mt-0.5 text-sm text-[#3C2F23]"></p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-white ring-1 ring-black/5 flex-shrink-0 mt-0.5">
                                        <i class="fa-solid fa-tag text-[#8B7355] text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Price</p>
                                        <p id="spaModalPrice" class="mt-0.5 text-sm font-semibold text-[#6F5430]"></p>
                                    </div>
                                </div>
                                <div id="spaModalMap" class="w-full h-[170px] rounded-xl border border-[#E8DDD0] bg-[#F6EFE6] overflow-hidden shadow-inner"></div>
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
    <div id="bookingModal" class="fixed inset-0 z-[110] hidden">
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" data-close-booking-modal></div>
        <div class="relative mx-auto w-[92%] max-w-2xl mt-10 sm:mt-16">
            <div class="overflow-hidden bg-white shadow-2xl rounded-3xl ring-1 ring-black/10">
                <div class="flex items-center justify-between px-6 py-4 border-b border-black/5">
                    <div>
                        <h3 class="text-lg font-semibold text-[#3C2F23]">Make a Reservation</h3>
                        <p id="bookingSpaMeta" class="mt-1 text-xs text-gray-500">Spa • Branch</p>
                    </div>
                    <button type="button"
                            class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5"
                            data-close-booking-modal aria-label="Close">
                        <i class="text-lg text-gray-700 fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="overflow-y-auto max-h-[80vh] p-6">
                    @auth
                        <form method="POST" action="{{ route('bookings.online.checkout') }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="spa_id" id="bookingSpaIdInput">
                            <input type="hidden" name="branch_id" id="bookingBranchIdInput">

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600">Full Name</label>
                                    <input type="text" name="customer_name" id="bookingCustomerName"
                                        value="{{ auth()->user()->name }}" readonly
                                        class="w-full mt-1 text-gray-700 bg-gray-100 rounded-xl border-black/10 ring-1 ring-black/5">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600">Email</label>
                                    <input type="email" name="customer_email" id="bookingCustomerEmail"
                                        value="{{ auth()->user()->email }}" readonly
                                        class="w-full mt-1 text-gray-700 bg-gray-100 rounded-xl border-black/10 ring-1 ring-black/5">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600">Phone Number</label>
                                <input type="text" name="customer_phone" id="bookingCustomerPhone"
                                    placeholder="09xxxxxxxxx"
                                    class="w-full mt-1 rounded-xl border-black/10 ring-1 ring-black/5 focus:ring-2 focus:ring-[#8B7355]/40"
                                    required>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600">Treatment / Package</label>
                                <select name="treatment" id="bookingTreatmentSelect"
                                    class="w-full mt-1 rounded-xl border-black/10 ring-1 ring-black/5 focus:ring-2 focus:ring-[#8B7355]/40"
                                    required>
                                    <option value="">Select treatment or package</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600">Service Type</label>
                                <select name="service_type" id="bookingServiceType"
                                    class="w-full mt-1 rounded-xl border-black/10 ring-1 ring-black/5 focus:ring-2 focus:ring-[#8B7355]/40"
                                    required>
                                    <option value="">Select service type</option>
                                </select>
                                <p id="bookingServiceTypeHint" class="mt-1 text-[11px] text-gray-500"></p>
                            </div>

                            <div id="addressWrapper" class="hidden">
                                <label class="block text-xs font-semibold text-gray-600">
                                    Home Address <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="customer_address" id="bookingAddressInput"
                                    class="w-full mt-1 rounded-xl border-black/10 ring-1 ring-black/5 focus:ring-2 focus:ring-[#8B7355]/40"
                                    placeholder="Enter your full address">
                                <p class="mt-1 text-[11px] text-gray-500">Required for home service bookings.</p>
                            </div>

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
                                    <p id="bookingTimeError" class="hidden mt-1 text-[11px] text-red-500">
                                        <i class="fa-solid fa-circle-exclamation"></i>
                                        Selected time has already passed. Please choose a future time.
                                    </p>
                                </div>
                            </div>

                            @if($errors->has('start_time'))
                                <div class="p-3 text-sm text-red-600 rounded-xl bg-red-50 ring-1 ring-red-200">
                                    <i class="mr-1 fa-solid fa-circle-exclamation"></i>
                                    {{ $errors->first('start_time') }}
                                </div>
                            @endif

                            <!-- Terms & Agreements -->
                            <div class="p-4 border border-[#E8DDD0] rounded-xl bg-[#FDFAF6] space-y-3">
                                <label class="flex items-center gap-3 pt-1 cursor-pointer group">
                                    <input type="checkbox" id="bookingTermsCheckbox" name="terms_agreed" value="1"
                                        class="w-4 h-4 rounded accent-[#8B7355] cursor-pointer flex-shrink-0" required>
                                    <span class="text-xs font-medium text-gray-700 group-hover:text-[#6F5430] transition">
                                        I have read and agree to the
                                        <button type="button" onclick="openTermsModal()"
                                            class="text-[#8B7355] underline underline-offset-2 hover:text-[#6F5430] transition font-semibold">
                                            terms and conditions
                                        </button>.
                                    </span>
                                </label>
                            </div>

                            <button type="submit" id="bookingSubmitBtn"
                                    class="w-full booking-btn text-white py-3 rounded-xl text-sm font-semibold shadow-md hover:shadow-lg transition active:translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:translate-y-0">
                                Reserve An Appointment
                            </button>
                        </form>
                    @else
                        <div class="p-4 rounded-2xl bg-[#F6EFE6]/70 ring-1 ring-black/5">
                            <p class="text-sm text-gray-700">Please log in to book an appointment.</p>
                            <a href="{{ route('login') }}"
                            class="block mt-4 text-center booking-btn text-white py-3 rounded-xl text-sm font-semibold shadow-md hover:shadow-lg transition active:translate-y-0.5">
                                Login to Continue
                            </a>
                        </div>
                    @endauth
                </div>
<!-- ================= TERMS MODAL ================= -->
<div id="termsModal" class="fixed inset-0 z-[120] hidden">
    <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeTermsModal()"></div>
    <div class="relative mx-auto w-[92%] max-w-lg mt-10 sm:mt-16">
        <div class="overflow-hidden bg-white shadow-2xl rounded-3xl ring-1 ring-black/10">
            <div class="flex items-center justify-between px-6 py-4 border-b border-black/5">
                <h3 class="text-lg font-semibold text-[#3C2F23]">Terms & Conditions</h3>
                <button type="button" onclick="closeTermsModal()"
                    class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5">
                    <i class="text-lg text-gray-700 fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="overflow-y-auto max-h-[60vh] p-6">
                <ul class="space-y-4 text-xs leading-relaxed text-gray-600">
                    <li class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/50">
                        <i class="fa-solid fa-circle-check text-[#8B7355] mt-0.5 flex-shrink-0 text-sm"></i>
                        <div>
                            <p class="font-semibold text-[#3C2F23] mb-1">Downpayment</p>
                            <p>A 20% non-refundable downpayment is required to confirm your reservation. The remaining 80% is payable at the spa on the day of your appointment.</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/50">
                        <i class="fa-solid fa-circle-check text-[#8B7355] mt-0.5 flex-shrink-0 text-sm"></i>
                        <div>
                            <p class="font-semibold text-[#3C2F23] mb-1">Cancellation</p>
                            <p>Cancellations must be made at least 24 hours before your appointment. The 20% downpayment is non-refundable regardless of cancellation timing.</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/50">
                        <i class="fa-solid fa-circle-check text-[#8B7355] mt-0.5 flex-shrink-0 text-sm"></i>
                        <div>
                            <p class="font-semibold text-[#3C2F23] mb-1">No-Show Policy</p>
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
                    <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] font-semibold">How It Works</h2>
                    <span class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355]"></span>
                </div>
                <p class="mt-3 text-sm text-gray-600">Book in minutes with a simple flow.</p>
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
                    <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] font-semibold">Why Book With Us</h2>
                    <span class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355]"></span>
                </div>
                <p class="mt-3 text-sm text-gray-600">Built for convenience and trust.</p>
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

@if(session('error'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        showSpaToast(@json(session('error')), 'error');
    });
</script>
@endif

</body>
</html>
