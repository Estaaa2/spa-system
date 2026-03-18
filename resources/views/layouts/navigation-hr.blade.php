@php
    $user = Auth::user();
    $spa  = $user?->spa;
@endphp

<div x-data="{ open: false, settingsOpen: false, showLogoutModal: false }" class="flex h-screen bg-gray-100 dark:bg-gray-900">

    {{-- MOBILE TOPBAR --}}
    <div class="fixed top-0 z-40 flex items-center justify-between w-full px-4 py-3 bg-white border-b md:hidden dark:bg-gray-800 dark:border-gray-700">
        <button @click="open = true" class="text-gray-700 dark:text-gray-200">
            <i class="text-xl fa-solid fa-bars"></i>
        </button>
        <span class="text-sm font-semibold text-[#8B7355]">{{ $spa?->name }}</span>
    </div>

    {{-- SIDEBAR --}}
    <aside class="fixed inset-y-0 left-0 z-40 w-64 transition-transform duration-200 transform bg-white border-r dark:bg-gray-800 dark:border-gray-700 md:translate-x-0"
        :class="open ? 'translate-x-0' : '-translate-x-full'">
        <div class="flex flex-col h-full">

            {{-- Brand --}}
            <div class="flex-shrink-0 px-6 py-4 border-b dark:border-gray-700">
                <div class="flex items-center space-x-3">
                    <img src="{{ asset('images/1.png') }}" class="h-10 rounded-md" alt="Levictas">
                    <div>
                        <span class="text-xl font-semibold text-[#8B7355] font-['Playfair_Display']">
                            {{ $spa?->name ?? 'HR Portal' }}
                        </span>
                        <p class="text-xs tracking-widest text-gray-500 dark:text-gray-400">
                                SPA | WELLNESS
                        </p>
                    </div>
                </div>
            </div>

            {{-- Nav --}}
            <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">

                {{-- HR Dashboard --}}
                @can('view hr dashboard')
                <x-nav-link :href="route('hr.dashboard')" :active="request()->routeIs('hr.dashboard')">
                    <i class="fa-solid fa-gauge-high w-4 mr-2 text-[#8B7355]"></i>
                    Dashboard
                </x-nav-link>
                @endcan

                {{-- Hiring --}}
                @can('view hiring')
                <x-nav-link :href="route('hr.hiring')" :active="request()->routeIs('hr.hiring')">
                    <i class="fa-solid fa-bullhorn w-4 mr-2 text-[#8B7355]"></i>
                    Hiring
                </x-nav-link>
                @endcan

                {{-- Applications --}}
                @can('view applications')
                <x-nav-link :href="route('hr.applications')" :active="request()->routeIs('hr.applications')">
                    <i class="fa-solid fa-file-lines w-4 mr-2 text-[#8B7355]"></i>
                    Applications
                </x-nav-link>
                @endcan

                {{-- Interviews --}}
                @can('view interviews')
                <x-nav-link :href="route('hr.interviews')" :active="request()->routeIs('hr.interviews')">
                    <i class="fa-solid fa-comments w-4 mr-2 text-[#8B7355]"></i>
                    Interviews
                </x-nav-link>
                @endcan

                {{-- Attendance --}}
                @can('view attendance')
                <x-nav-link :href="route('hr.attendance')" :active="request()->routeIs('hr.attendance')">
                    <i class="fa-solid fa-clock w-4 mr-2 text-[#8B7355]"></i>
                    Attendance
                </x-nav-link>
                @endcan

                {{-- Payroll --}}
                @can('view payroll')
                <x-nav-link :href="route('hr.payroll')" :active="request()->routeIs('hr.payroll')">
                    <i class="fa-solid fa-money-bill-wave w-4 mr-2 text-[#8B7355]"></i>
                    Payroll
                </x-nav-link>
                @endcan

                {{-- Settings --}}
                <div class="mb-1">
                    <button @click="settingsOpen = !settingsOpen"
                        class="flex items-center justify-between w-full px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                        <span class="flex items-center gap-2">
                            <i class="fa-solid fa-gear w-4 text-[#8B7355]"></i>
                            Settings
                        </span>
                        <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                            :class="settingsOpen ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="settingsOpen" x-collapse class="ml-4 space-y-1">
                        <x-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">
                            Profile
                        </x-nav-link>
                    </div>
                </div>

            </nav>

            {{-- User Info --}}
            <div class="flex-shrink-0 p-3 border-t dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-800 dark:text-white">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-gray-500 truncate dark:text-gray-400">{{ Auth::user()->email }}</p>
                    </div>
                    <button type="button" @click="showLogoutModal = true"
                        class="flex items-center justify-center text-gray-600 transition-colors rounded-full w-9 h-9 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                        <i class="text-lg fa-solid fa-right-from-bracket"></i>
                    </button>
                </div>
            </div>

        </div>
    </aside>

    {{-- Overlay --}}
    <div x-show="open" @click="open = false" class="fixed inset-0 z-30 bg-black bg-opacity-40 md:hidden"></div>

    {{-- Logout Modal --}}
    <div x-show="showLogoutModal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50">
        <div class="w-full max-w-md overflow-hidden bg-white rounded-lg shadow-xl dark:bg-gray-800">
            <div class="flex items-center justify-between px-6 py-4 border-b dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Confirm Logout</h3>
                <button @click="showLogoutModal = false" class="text-gray-400 hover:text-gray-500">
                    <i class="text-xl fa-solid fa-times"></i>
                </button>
            </div>
            <div class="px-6 py-6">
                <p class="text-sm text-gray-500">You will be logged out of your HR account.</p>
            </div>
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50">
                <div class="flex justify-end space-x-3">
                    <button @click="showLogoutModal = false"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">
                            Yes, Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content --}}
    <main class="flex-1 h-screen overflow-y-auto md:ml-64">
        <div class="p-4 pt-16 md:p-4 md:pt-4">
            @yield('content')
        </div>
    </main>

</div>
<style>[x-cloak] { display: none !important; }</style>
