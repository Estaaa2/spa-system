@php
    $adminUser = Auth::user();

    // Pending-verification count for the Registered Spas badge. Same condition
    // RegisteredSpaController::index() uses for its "Pending Verification" list.
    $pendingSpaCount = $adminUser?->can('view registered spas')
        ? \App\Models\Spa::where('verification_status', 'pending')->count()
        : 0;
@endphp

<style>
    @media (max-width: 767.98px) {
        #app-sidebar {
            transform: translateX(-100%);
        }

        #app-sidebar.is-open {
            transform: translateX(0);
        }
    }

    /* Defensive: harmless if app.blade.php already declares this in <head>. */
    [x-cloak] {
        display: none !important;
    }

    .overflow-y-auto::-webkit-scrollbar {
        width: 6px;
    }

    .overflow-y-auto::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }

    .overflow-y-auto::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }

    /* Firefox has no ::-webkit-scrollbar; these two properties are its equivalent. */
    .overflow-y-auto {
        scrollbar-width: thin;
        scrollbar-color: #c1c1c1 #f1f1f1;
    }

    @media (prefers-color-scheme: dark) {
        .overflow-y-auto::-webkit-scrollbar-track {
            background: #374151;
        }

        .overflow-y-auto::-webkit-scrollbar-thumb {
            background: #6b7280;
        }

        .overflow-y-auto {
            scrollbar-color: #6b7280 #374151;
        }
    }
</style>

<div x-data="sidebar" @keydown.escape.window="open = false" class="flex h-screen bg-gray-100 dark:bg-gray-900">

    <a href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[60] focus:px-4 focus:py-2 focus:rounded-lg focus:bg-white focus:text-[#6F5430] focus:ring-2 focus:ring-[#8B7355] dark:focus:bg-gray-800 dark:focus:text-[#C4A97D]">
        Skip to content
    </a>

    <!-- MOBILE TOPBAR -->
    <div
        class="fixed top-0 z-40 flex items-center justify-between w-full px-2 bg-white border-b h-14 md:hidden dark:bg-gray-800 dark:border-gray-700">
        <button type="button" @click="open = !open"
            aria-label="Toggle navigation" aria-controls="app-sidebar"
            :aria-expanded="open ? 'true' : 'false'"
            class="inline-flex items-center justify-center flex-shrink-0 text-gray-700 rounded-lg w-11 h-11 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
            <i class="text-xl fa-solid fa-bars"></i>
        </button>

        <!-- Mobile Title -->
        <div class="flex items-center min-w-0 gap-2 pr-3">
            <i class="text-sm fa-solid fa-shield-halved text-[#8B7355] dark:text-[#C4A97D]"></i>
            <span class="text-sm font-medium text-gray-700 truncate dark:text-gray-200">Admin Panel</span>
        </div>
    </div>

    <!-- SIDEBAR -->
    {{-- z-50 so the mobile overlay (z-40) can sit above the topbar without covering the drawer. --}}
    <aside id="app-sidebar"
        class="fixed inset-y-0 left-0 z-50 w-64 transition-transform duration-200 bg-white border-r dark:bg-gray-800 dark:border-gray-700"
        :class="open ? 'is-open' : ''">
        <div class="flex flex-col h-full">

            <!-- Brand -->
            <div class="flex-shrink-0 border-b dark:border-gray-700">
                <div class="flex items-start justify-between gap-2 py-4 pl-6 pr-3 md:pr-6">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center flex-1 min-w-0 space-x-3">
                        <img src="{{ asset('images/1.png') }}" class="h-10 rounded-md" alt="Levictas">
                        <div class="min-w-0">
                            <span
                                class="text-2xl font-semibold text-[#8B7355] dark:text-white font-['Playfair_Display']">
                                Admin Panel
                            </span>
                            <p class="text-xs tracking-widest text-gray-500 dark:text-gray-400">
                                SYSTEM MANAGEMENT
                            </p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Navigation -->
            <nav aria-label="Main" class="flex-1 px-4 py-4 overflow-y-auto">

                <p class="px-4 pb-1 text-[11px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                    Administration
                </p>

                <div class="space-y-1">
                    <!-- Admin Dashboard -->
                    @can('view admin dashboard')
                        <div class="mb-1">
                            <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                                <i class="fa-solid fa-gauge-high w-4 mr-2 text-[#8B7355] dark:text-[#C4A97D]"></i>
                                Dashboard
                            </x-nav-link>
                        </div>
                    @endcan

                    @can('view registered spas')
                        <div class="mb-1">
                            <x-nav-link :href="route('admin.registered-spas.index')" :active="request()->routeIs('admin.registered-spas.*')">
                                <i class="fa-solid fa-spa w-4 mr-2 text-[#8B7355] dark:text-[#C4A97D]"></i>
                                Registered Spas
                                @if ($pendingSpaCount > 0)
                                    <span
                                        class="ml-2 text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                        {{ $pendingSpaCount }}<span class="sr-only"> pending verification</span>
                                    </span>
                                @endif
                            </x-nav-link>
                        </div>
                    @endcan

                    @can('view registered spas')
                        <div class="mb-1">
                            <x-nav-link :href="route('admin.subscriptions.index')" :active="request()->routeIs('admin.subscriptions.*')">
                                <i class="fa-solid fa-receipt w-4 mr-2 text-[#8B7355] dark:text-[#C4A97D]"></i>
                                Subscriptions
                            </x-nav-link>
                        </div>
                    @endcan

                    @can('view registered users')
                        <div class="mb-1">
                            <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                                <i class="fa-solid fa-users w-4 mr-2 text-[#8B7355] dark:text-[#C4A97D]"></i>
                                Registered Users
                            </x-nav-link>
                        </div>
                    @endcan

                    @can('view system roles')
                        <div class="mb-1">
                            <x-nav-link :href="route('admin.roles-permissions.index')" :active="request()->routeIs('admin.roles-permissions.*')">
                                <i class="fa-solid fa-key w-4 mr-2 text-[#8B7355] dark:text-[#C4A97D]"></i>
                                Roles &amp; Permissions
                            </x-nav-link>
                        </div>
                    @endcan
                </div>

            </nav>

            <!-- ACCOUNT & LOGOUT -->
            <div class="flex-shrink-0 p-3 border-t dark:border-gray-700">
                <div class="flex items-center justify-between gap-1">
                    {{-- Account. The identity block itself is the link to the profile page. --}}
                    <a href="{{ route('profile.edit') }}"
                       @if (request()->routeIs('profile.*')) aria-current="page" @endif
                       class="flex items-center flex-1 min-w-0 gap-3 px-2 py-1.5 -mx-1 transition-colors rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 {{ request()->routeIs('profile.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                        <i class="flex-shrink-0 text-gray-500 fa-solid fa-user-gear dark:text-gray-400"></i>
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-medium text-gray-800 truncate dark:text-white">{{ Auth::user()->name }}</span>
                            <span class="block text-xs text-gray-500 truncate dark:text-gray-400">{{ Auth::user()->email }}</span>
                        </span>
                    </a>
                    <button @click="showLogoutModal = true"
                            class="flex items-center justify-center w-8 h-8 text-gray-600 transition-colors rounded-lg hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                            title="Logout">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </button>
                </div>
            </div>

        </div>
    </aside>

    <!-- OVERLAY for Mobile -->
    <div x-show="open" x-cloak @click="open = false" aria-hidden="true"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-40 bg-black/40 md:hidden"></div>

    <!-- Logout Confirmation Modal -->
    <div x-show="showLogoutModal" x-cloak
        @keydown.escape.window="showLogoutModal = false"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 dark:bg-black/70">
        <div class="w-[80%] max-w-sm overflow-hidden bg-white dark:bg-gray-800 shadow-2xl rounded-3xl ring-1 ring-black/10 dark:ring-white/10"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform scale-95"
            x-transition:enter-end="opacity-100 transform scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform scale-100"
            x-transition:leave-end="opacity-0 transform scale-95">

            <div class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Confirm Logout</h3>
                <button @click="showLogoutModal = false"
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
                        <h4 class="font-semibold text-gray-900 dark:text-white">Are you sure?</h4>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            You will be logged out of your account and redirected to the home page.
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex gap-2 px-6 pb-6">
                <button @click="showLogoutModal = false"
                    class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                    Cancel
                </button>
                <form method="POST" action="{{ route('logout') }}" id="logoutForm" class="flex-1">
                    @csrf
                    <button type="submit"
                        class="w-full py-2.5 rounded-xl text-sm font-semibold text-white bg-red-600 hover:bg-red-700 focus:ring-4 focus:ring-red-300 dark:focus:ring-red-900 transition">
                        Yes, Logout
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <main id="main-content" tabindex="-1" class="relative flex-1 h-screen overflow-y-auto md:ml-64">
        <div class="pt-14 md:pt-0">
            @yield('content')
        </div>
    </main>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('sidebar', () => ({
        open: false,
        showLogoutModal: false,
    }));
});
</script>