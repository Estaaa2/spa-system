{{--
    Landing navbar, shared by welcome.blade.php and layouts/public.blade.php
    (landing footer Phase 1). The customer actions open modals that only exist
    on the landing page; <x-landing.nav-action> turns them into /?open= links
    on other pages. Behaviour (mobile menu, scroll glass, profile dropdown,
    logout modal) lives in resources/js/landing-nav.js.
--}}
@php($onLanding = request()->routeIs('landing.page'))
<nav class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 nav-glass" id="topNav">
    <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <div class="flex items-center">
                <a href="{{ url('/') }}" class="flex items-center space-x-3 group">
                    <img src="{{ asset('images/1.png') }}" alt="Levictas"
                        class="w-auto h-10 rounded-md ring-1 ring-black/5">
                    <div class="flex flex-col leading-tight">
                        <span
                            class="text-2xl font-semibold text-[#2D3748] dark:text-white font-['Playfair_Display'] tracking-wide group-hover:text-[#6F5430] dark:group-hover:text-[#C4A97D] transition">
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

                {{-- Mobile app Phase 2 (Q2 A): for every visitor, logged in or not. --}}
                <a href="{{ route('mobile-app') }}"
                    class="relative px-4 py-2 text-sm font-medium rounded-full transition
               {{ request()->routeIs('mobile-app') ? 'text-[#6F5430] dark:text-[#C4A97D] bg-white/60 dark:bg-gray-800/60 ring-1 ring-black/5 dark:ring-white/10' : 'text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#C4A97D] hover:bg-white/50 dark:hover:bg-gray-800/50' }}">
                    Get the App
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
                                <x-landing.nav-action open="appointments" action="openAppointmentsModal()"
                                    class="relative flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#C4A97D]">
                                    My Appointments
                                    {{-- Filled by welcome.js, so it only exists on the landing page. --}}
                                    @if ($onLanding)
                                        <span id="myAppointmentsBadge"
                                            class="hidden absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] items-center justify-center px-1 text-[10px] font-bold text-white bg-red-500 rounded-full ring-2 ring-[#F6EFE6] dark:ring-gray-900">
                                            0
                                        </span>
                                    @endif
                                </x-landing.nav-action>
                                <x-landing.nav-action open="schedule" action="openScheduleModal()"
                                    class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#C4A97D]">
                                    My Schedule
                                </x-landing.nav-action>
                            </div>

                            <div class="relative" id="profileDropdownWrapper">
                                <button type="button" id="profileDropdownBtn"
                                    class="flex items-center gap-2 px-3 py-2 transition rounded-full hover:bg-white/60 dark:hover:bg-gray-800/60 ring-1 ring-black/5 dark:ring-white/10">
                                    @if (auth()->user()?->profilePhotoUrl())
                                        <img src="{{ auth()->user()->profilePhotoUrl() }}" alt=""
                                            class="object-cover w-8 h-8 rounded-full shrink-0 ring-1 ring-black/10 dark:ring-white/10">
                                    @else
                                        <div
                                            class="flex items-center justify-center w-8 h-8 bg-[#8B7355] text-white rounded-full text-xs font-semibold leading-none shrink-0">
                                            {{ strtoupper(substr(auth()->user()?->name ?? 'Guest', 0, 1)) }}
                                        </div>
                                    @endif
                                    <i class="fa-solid fa-chevron-down text-[10px] text-gray-400 transition-transform duration-200"
                                        id="profileChevron"></i>
                                </button>

                                <div id="profileDropdownMenu"
                                    class="absolute right-0 z-50 hidden w-48 mt-2 overflow-hidden bg-white shadow-xl dark:bg-gray-800 rounded-2xl ring-1 ring-black/10 dark:ring-white/10">
                                    <div
                                        class="px-4 py-3 border-b border-black/5 dark:border-white/10 bg-[#F6EFE6]/60 dark:bg-gray-900/40">
                                        <p data-account-name class="text-xs font-semibold text-[#3C2F23] dark:text-white truncate">
                                            {{ auth()->user()?->name ?? 'Guest' }}</p>
                                        <p class="text-[11px] text-gray-400 truncate">{{ auth()->user()?->email ?? '' }}
                                        </p>
                                    </div>
                                    <div class="py-1">
                                        <x-landing.nav-action open="profile" action="closeProfileDropdown(); openProfileModal();"
                                            class="flex items-center gap-3 w-full px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-[#F6EFE6] dark:hover:bg-gray-700 transition">
                                            <i class="fa-solid fa-user text-[#8B7355] w-4"></i>
                                            Profile
                                        </x-landing.nav-action>
                                        <button type="button" onclick="closeProfileDropdown(); openLogoutModal();"
                                            class="flex items-center gap-3 w-full px-4 py-2.5 text-sm text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                                            <i class="w-4 fa-solid fa-right-from-bracket"></i>
                                            Logout
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        {{-- Owner, manager, staff and admin. LandingPageRedirect keeps them off /,
                             but the public pages are open to everyone (Phase 1, Q2-A). --}}
                        <button type="button" onclick="openLogoutModal()"
                            class="relative px-4 py-2 text-sm font-medium rounded-full transition text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#C4A97D] hover:bg-white/50 dark:hover:bg-gray-800/50">
                            Logout
                        </button>

                        <a href="{{ \App\Support\BusinessHome::url(auth()->user()) }}"
                            class="booking-btn ml-3 px-6 py-2.5 text-sm font-semibold text-white rounded-full transition-all duration-300 shadow-lg hover:shadow-xl active:translate-y-0.5">
                            Go to Dashboard
                        </a>
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

    <div id="mobile-menu"
        class="hidden bg-[#F6EFE6]/95 dark:bg-gray-900/95 border-t border-black/10 dark:border-white/10 shadow-lg md:hidden">
        <div class="px-3 pt-3 pb-5 space-y-2">
            <a href="{{ url('/') }}"
                class="block px-4 py-3 rounded-xl text-sm font-medium transition
           {{ request()->is('/') ? 'bg-white/70 dark:bg-gray-800/70 text-[#6F5430] dark:text-[#C4A97D] ring-1 ring-black/5 dark:ring-white/10' : 'text-gray-700 dark:text-gray-300 hover:bg-white/60 dark:hover:bg-gray-800/60' }}">
                Home
            </a>
            <a href="{{ route('mobile-app') }}"
                class="block px-4 py-3 rounded-xl text-sm font-medium transition
           {{ request()->routeIs('mobile-app') ? 'bg-white/70 dark:bg-gray-800/70 text-[#6F5430] dark:text-[#C4A97D] ring-1 ring-black/5 dark:ring-white/10' : 'text-gray-700 dark:text-gray-300 hover:bg-white/60 dark:hover:bg-gray-800/60' }}">
                <i class="mr-2 fa-solid fa-mobile-screen" aria-hidden="true"></i>Get the App
            </a>
            @guest
                <a href="{{ route('login') }}"
                    class="block px-4 py-3 text-base font-medium text-gray-700 dark:text-gray-300 rounded-xl hover:bg-white/60 dark:hover:bg-gray-800/60">Login</a>
                <a href="{{ route('register') }}"
                    class="block px-4 py-3 text-base font-medium text-gray-700 dark:text-gray-300 rounded-xl hover:bg-white/60 dark:hover:bg-gray-800/60">Register</a>
                <a href="{{ route('register.business') }}"
                    class="block px-4 py-3 text-base font-medium text-gray-700 dark:text-gray-300 rounded-xl hover:bg-white/60 dark:hover:bg-gray-800/60">Join
                    as a Partner</a>
            @else
                @role('customer')
                    <x-landing.nav-action open="appointments" action="openAppointmentsModal()"
                        class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#C4A97D]">
                        My Appointments
                        @if ($onLanding)
                            <span id="myAppointmentsBadgeMobile"
                                class="hidden items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-white bg-red-500 rounded-full">
                                0
                            </span>
                        @endif
                    </x-landing.nav-action>
                    <x-landing.nav-action open="schedule" action="openScheduleModal()"
                        class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#C4A97D]">My
                        Schedule</x-landing.nav-action>
                    <x-landing.nav-action open="profile" action="openProfileModal();"
                        class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#C4A97D]">Profile</x-landing.nav-action>
                    <button type="button" onclick="openLogoutModal()"
                        class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#C4A97D]">
                        Logout
                    </button>
                @else
                    <a href="{{ \App\Support\BusinessHome::url(auth()->user()) }}"
                        class="block px-4 py-3 text-base font-medium text-gray-700 dark:text-gray-300 rounded-xl hover:bg-white/60 dark:hover:bg-gray-800/60">Go
                        to Dashboard</a>
                    <button type="button" onclick="openLogoutModal()"
                        class="block w-full px-4 py-3 text-base font-medium text-left text-gray-700 dark:text-gray-300 rounded-xl hover:bg-white/60 dark:hover:bg-gray-800/60">Logout</button>
                @endrole
            @endguest
        </div>
    </div>
</nav>
