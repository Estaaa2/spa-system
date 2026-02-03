<div x-data="sidebar()" class="flex h-screen bg-gray-100 dark:bg-gray-900">

    <!-- MOBILE TOPBAR -->
    <div
        class="fixed top-0 z-40 flex items-center justify-between w-full px-4 py-3 bg-white border-b md:hidden dark:bg-gray-800 dark:border-gray-700">
        <button @click="open = true" class="text-gray-700 dark:text-gray-200">
            <i class="text-xl fa-solid fa-bars"></i>
        </button>

        <!-- Mobile Title -->
        <div class="flex items-center gap-2">
            <i class="text-[#8B7355] fa-solid fa-shield-halved"></i>
            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Admin Panel</span>
        </div>
    </div>

    <!-- SIDEBAR -->
    <aside
        class="fixed inset-y-0 left-0 z-40 w-64 transition-transform duration-200 transform bg-white border-r dark:bg-gray-800 dark:border-gray-700 md:translate-x-0"
        :class="open ? 'translate-x-0' : '-translate-x-full'">
        <div class="flex flex-col h-full">

            <!-- Brand -->
            <div class="flex-shrink-0 border-b dark:border-gray-700">
                <div class="px-6 py-4">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center space-x-3">
                        <img src="{{ asset('images/1.png') }}" class="h-10 rounded-md" alt="Levictas">
                        <div>
                            <span class="text-xl font-semibold text-[#8B7355] dark:text-white font-['Playfair_Display']">
                                Admin Panel
                            </span>
                            <p class="text-xs tracking-widest text-gray-500 dark:text-gray-400">
                                SYSTEM MANAGEMENT
                            </p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Navigation (scrollable) -->
            <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">

                <!-- Admin Dashboard -->
                @can('view admin dashboard')
                <div class="mb-2">
                    <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                        Dashboard
                    </x-nav-link>
                </div>
                @endcan

                <!-- Administration -->
                <div class="mb-2">
                    <div class="space-y-1">
                        @can('manage users')
                        <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                            Users
                        </x-nav-link>
                        @endcan

                        @can('manage roles')
                        <x-nav-link :href="route('roles-permissions.index')" :active="request()->routeIs('roles-permissions.*')">
                            Roles & Permissions
                        </x-nav-link>
                        @endcan

                        @can('manage settings')
                        <x-nav-link :href="route('settings.index')" :active="request()->routeIs('settings.*')">
                            Settings
                        </x-nav-link>
                        @endcan
                    </div>
                </div>

            </nav>

            <!-- USER INFO pinned at bottom -->
            <div class="flex-shrink-0 p-3 border-t dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-800 dark:text-white">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-gray-500 truncate dark:text-gray-400">{{ Auth::user()->email }}</p>
                    </div>

                    <button type="button" @click="showLogoutModal = true"
                        class="flex items-center justify-center text-gray-600 transition-colors rounded-full w-9 h-9 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                        title="Log Out">
                        <i class="text-lg fa-solid fa-right-from-bracket"></i>
                    </button>
                </div>
            </div>

        </div>
    </aside>

    <!-- OVERLAY for Mobile -->
    <div x-show="open" @click="open = false" class="fixed inset-0 z-30 bg-black bg-opacity-40 md:hidden"></div>

    <!-- Logout Confirmation Modal -->
    <div x-show="showLogoutModal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50 dark:bg-opacity-70">
        <div class="w-full max-w-md overflow-hidden bg-white rounded-lg shadow-xl dark:bg-gray-800"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform scale-95"
            x-transition:enter-end="opacity-100 transform scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform scale-100"
            x-transition:leave-end="opacity-0 transform scale-95">

            <div class="flex items-center justify-between px-6 py-4 border-b dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Confirm Logout
                </h3>
                <button @click="showLogoutModal = false" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                    <i class="text-xl fa-solid fa-times"></i>
                </button>
            </div>

            <div class="px-6 py-6">
                <div class="flex items-start">
                    <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-red-100 rounded-full dark:bg-red-900/30">
                        <i class="text-xl text-red-600 fa-solid fa-right-from-bracket dark:text-red-400"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-lg font-medium text-gray-900 dark:text-white">Are you sure?</h4>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            You will be logged out of your account and redirected to the home page.
                        </p>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50">
                <div class="flex justify-end space-x-3">
                    <button @click="showLogoutModal = false"
                        class="px-4 py-2 text-sm font-medium text-gray-700 transition-colors bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-600 dark:border-gray-500 dark:text-white dark:hover:bg-gray-500">
                        Cancel
                    </button>
                    <form method="POST" action="{{ route('logout') }}" id="logoutForm">
                        @csrf
                        <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white transition-colors bg-red-600 rounded-lg hover:bg-red-700 focus:ring-4 focus:ring-red-300 dark:focus:ring-red-900">
                            Yes, Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT (only this scrolls) -->
    <main class="flex-1 h-screen overflow-y-auto md:ml-64">
        <div class="p-4 pt-16 md:p-4 md:pt-4">
            @yield('content')
        </div>
    </main>

</div>

<script>
function sidebar() {
    return {
        open: false,
        showLogoutModal: false,
    };
}
</script>

<style>
[x-cloak] { display: none !important; }

.overflow-y-auto::-webkit-scrollbar { width: 6px; }
.overflow-y-auto::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 3px; }
.overflow-y-auto::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 3px; }
.dark .overflow-y-auto::-webkit-scrollbar-track { background: #374151; }
.dark .overflow-y-auto::-webkit-scrollbar-thumb { background: #6b7280; }

.transition {
    transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}
</style>
