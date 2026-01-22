<div x-data="{
    open: false,
    showLogoutModal: false,
    operationsOpen: false,
    managementOpen: false,
    insightsOpen: false,
    administrationOpen: false
}" class="flex min-h-screen bg-gray-100 dark:bg-gray-900">

    <!-- MOBILE TOPBAR -->
    <div class="fixed top-0 z-40 flex items-center justify-between w-full px-4 py-3 bg-white border-b md:hidden dark:bg-gray-800 dark:border-gray-700">
        <button @click="open = true" class="text-gray-700 dark:text-gray-200">
            <i class="text-xl fa-solid fa-bars"></i>
        </button>

        <span class="text-lg font-semibold text-gray-800 dark:text-white">
            <img src="{{ asset('images/1.png') }}" class="h-10 rounded-md" alt="Levictas">
        </span>
    </div>

    <!-- SIDEBAR -->
    <aside
        class="fixed inset-y-0 left-0 z-40 w-64 bg-white border-r md:relative md:translate-x-0 dark:bg-gray-800 dark:border-gray-700"
        :class="open ? 'translate-x-0' : '-translate-x-screen'"
    >
        <div class="flex flex-col h-full">
            <!-- Brand -->
            <div class="flex-shrink-0 px-6 py-5 border-b dark:border-gray-700">
                <a href="{{ url('/') }}" class="flex items-center space-x-3">
                    <img src="{{ asset('images/1.png') }}" class="h-10 rounded-md" alt="Levictas">
                    <div>
                        <span class="text-xl font-semibold text-gray-800 dark:text-white font-['Playfair_Display']">
                            {{ Auth::user()->spa->name ?? 'Spa Management' }}
                        </span>
                        <p class="text-xs tracking-widest text-gray-500 dark:text-gray-400">
                            SPA & WELLNESS
                        </p>
                    </div>
                </a>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
                <!-- 1. Dashboard -->
                <div class="mb-2">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        Dashboard
                    </x-nav-link>
                </div>

                <!-- 2. Operations -->
                <div class="mb-2">
                    <button @click="operationsOpen = !operationsOpen"
                            class="flex items-center justify-between w-full px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                        <span>Operations</span>
                        <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                           :class="operationsOpen ? 'transform rotate-180' : ''"></i>
                    </button>

                    <div x-show="operationsOpen" x-collapse class="ml-4 space-y-1">
                        <x-nav-link :href="route('booking')" :active="request()->routeIs('booking')">
                            Book an Appointment
                        </x-nav-link>
                        <x-nav-link :href="route('appointments.index')" :active="request()->routeIs('appointments.*')">
                            Appointments
                        </x-nav-link>
                        <x-nav-link :href="route('schedule.index')" :active="request()->routeIs('schedule.*')">
                            Schedule
                        </x-nav-link>
                        <x-nav-link :href="route('staff-availability.index')" :active="request()->routeIs('staff-availability.*')">
                            Staff Availability
                        </x-nav-link>
                    </div>
                </div>

                <!-- 3. Management -->
                <div class="mb-2">
                    <button @click="managementOpen = !managementOpen"
                            class="flex items-center justify-between w-full px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                        <span>Management</span>
                        <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                           :class="managementOpen ? 'transform rotate-180' : ''"></i>
                    </button>

                    <div x-show="managementOpen" x-collapse class="ml-4 space-y-1">
                        <x-nav-link :href="route('services.index')" :active="request()->routeIs('services.*')">
                            Services
                        </x-nav-link>
                        <x-nav-link :href="route('staff.index')" :active="request()->routeIs('staff.*')">
                            Staff
                        </x-nav-link>
                        <x-nav-link :href="route('branches.index')" :active="request()->routeIs('branches.*')">
                            Branches
                        </x-nav-link>
                    </div>
                </div>

                <!-- 4. Insights -->
                <div class="mb-2">
                    <button @click="insightsOpen = !insightsOpen"
                            class="flex items-center justify-between w-full px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                        <span>Insights</span>
                        <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                           :class="insightsOpen ? 'transform rotate-180' : ''"></i>
                    </button>

                    <div x-show="insightsOpen" x-collapse class="ml-4 space-y-1">
                        <x-nav-link :href="route('decision-support.index')" :active="request()->routeIs('decision-support.*')">
                            Decision Support
                        </x-nav-link>
                        <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                            Reports
                        </x-nav-link>
                    </div>
                </div>

                <!-- 5. Administration (Owner/Admin Only) -->
                @can('view-admin-section')
                <div class="mb-2">
                    <button @click="administrationOpen = !administrationOpen"
                            class="flex items-center justify-between w-full px-4 py-3 text-sm font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                        <span>Administration</span>
                        <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                           :class="administrationOpen ? 'transform rotate-180' : ''"></i>
                    </button>

                    <div x-show="administrationOpen" x-collapse class="ml-4 space-y-1">
                        <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                            Users
                        </x-nav-link>
                        <x-nav-link :href="route('roles-permissions.index')" :active="request()->routeIs('roles-permissions.*')">
                            Roles & Permissions
                        </x-nav-link>
                        <x-nav-link :href="route('settings.index')" :active="request()->routeIs('settings.*')">
                            Settings
                        </x-nav-link>
                    </div>
                </div>
                @endcan

            </nav>

            <!-- User Info and Logout - Fixed at bottom -->
            <div class="flex-shrink-0 p-3 border-t dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-800 dark:text-white">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-gray-500 truncate dark:text-gray-400">{{ Auth::user()->email }}</p>
                    </div>

                    <!-- Logout Button -->
                    <button type="button"
                            @click="showLogoutModal = true"
                            class="flex items-center justify-center text-gray-600 transition-colors rounded-full w-9 h-9 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                            title="Log Out">
                        <i class="text-lg fa-solid fa-right-from-bracket"></i>
                    </button>
                </div>
            </div>
        </div>
    </aside>

    <!-- OVERLAY for Mobile Menu -->
    <div
        x-show="open"
        @click="open = false"
        class="fixed inset-0 z-40 bg-black bg-opacity-40 md:hidden"
    ></div>

    <!-- Logout Confirmation Modal -->
    <div x-show="showLogoutModal"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50 dark:bg-opacity-70">
        <div class="w-full max-w-md overflow-hidden bg-white rounded-lg shadow-xl dark:bg-gray-800"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95">

            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Confirm Logout
                </h3>
                <button @click="showLogoutModal = false"
                        class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                    <i class="text-xl fa-solid fa-times"></i>
                </button>
            </div>

            <!-- Modal Body -->
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

            <!-- Modal Footer -->
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

    <!-- MAIN CONTENT - Scrollable only this area -->
    <main class="flex-1 overflow-y-auto">
        <div class="p-4 pt-16 md:p-4 md:pt-4">
            @yield('content')
        </div>
    </main>

</div>

<style>
    [x-cloak] {
        display: none !important;
    }

    /* Smooth transitions */
    .transition {
        transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
        transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        transition-duration: 150ms;
    }

    /* Dropdown animation */
    [x-collapse] {
        overflow: hidden;
        transition: all 0.3s ease-in-out;
    }
</style>

<script>
    // Initialize collapse functionality if not using Alpine.js x-collapse
    document.addEventListener('alpine:init', () => {
        if (!Alpine.hasPlugin('collapse')) {
            Alpine.plugin('collapse', (Alpine) => {
                Alpine.directive('collapse', (el, { modifiers }) => {
                    let duration = modifiers.includes('slow') ? 500 :
                                   modifiers.includes('fast') ? 150 : 300;

                    let hide = () => {
                        el.style.height = el.scrollHeight + 'px';
                        el.offsetHeight; // force reflow
                        el.style.height = '0px';
                        el.style.opacity = '0';
                    };

                    let show = () => {
                        el.style.height = '0px';
                        el.offsetHeight; // force reflow
                        el.style.height = el.scrollHeight + 'px';
                        el.style.opacity = '1';

                        setTimeout(() => {
                            if (getComputedStyle(el).height !== '0px') {
                                el.style.height = 'auto';
                            }
                        }, duration);
                    };

                    el.style.transition = `height ${duration}ms ease, opacity ${duration}ms ease`;

                    if (!el._x_isShown) {
                        hide();
                    }

                    el._x_isShown = Alpine.reactive({ value: el._x_isShown });

                    Alpine.effect(() => {
                        if (el._x_isShown.value) {
                            show();
                        } else {
                            hide();
                        }
                    });
                });
            });
        }
    });
</script>
