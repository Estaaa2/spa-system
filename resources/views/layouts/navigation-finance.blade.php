@php
    $user = Auth::user();
    $spa  = $user?->spa;

    // Finance permissions
    $canRevenue          = $user?->can('view revenue') ?? false;
    $canBilling          = $user?->can('view billing') ?? false;
    $canFinanceInventory = $user?->can('view finance inventory') ?? false;
    $showFinance         = $canRevenue || $canBilling || $canFinanceInventory;

    $canReports          = $user?->can('view reports') ?? false;
    $canDecisionSupport  = $user?->can('view decision support') ?? false;
    $showInsights        = $canReports || $canDecisionSupport;
@endphp

<div x-data="{
    open: false,
    showLogoutModal: false,
    financeOpen: false,
    insightsOpen: false,
    settingsOpen: false,
}" class="flex h-screen bg-gray-100 dark:bg-gray-900">

    {{-- MOBILE TOPBAR --}}
    <div class="fixed top-0 z-40 flex items-center justify-between w-full px-4 py-3 bg-white border-b md:hidden dark:bg-gray-800 dark:border-gray-700">
        <button @click="open = true" class="text-gray-700 dark:text-gray-200">
            <i class="text-xl fa-solid fa-bars"></i>
        </button>
        <div class="flex items-center gap-2">
            <span class="text-sm font-medium truncate max-w-[120px] text-gray-700 dark:text-gray-200">
                {{ $spa?->name }}
            </span>
            <span class="px-2 py-0.5 text-[10px] font-semibold text-orange-700 bg-orange-100 rounded-full">
                Finance
            </span>
        </div>
    </div>

    {{-- SIDEBAR --}}
    <aside class="fixed inset-y-0 left-0 z-40 w-64 transition-transform duration-200 transform bg-white border-r dark:bg-gray-800 dark:border-gray-700 md:translate-x-0"
        :class="open ? 'translate-x-0' : '-translate-x-full'">
        <div class="flex flex-col h-full">

            {{-- Brand --}}
            <div class="flex-shrink-0 border-b dark:border-gray-700">
                <div class="px-6 py-4">
                    <a href="{{ route('finance.dashboard') }}" class="flex items-center space-x-3">
                        <img src="{{ asset('images/1.png') }}" class="h-10 rounded-md" alt="Levictas">
                        <div>
                            <span class="text-2xl font-semibold text-[#8B7355] dark:text-white font-['Playfair_Display']">
                                {{ $spa?->name ?? 'Finance' }}
                            </span>
                            <p class="text-xs tracking-widest text-gray-500 dark:text-gray-400">
                                SPA | WELLNESS
                            </p>
                        </div>
                    </a>
                </div>
            </div>

            {{-- Navigation --}}
            <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">

                {{-- Dashboard --}}
                @can('view finance dashboard')
                <div class="mb-1 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                    <x-nav-link :href="route('finance.dashboard')" :active="request()->routeIs('finance.dashboard')">
                        <i class="fa-solid fa-gauge-high w-4 mr-1 text-[#8B7355]"></i>
                        Dashboard
                    </x-nav-link>
                </div>
                @endcan

                {{-- Finance Section --}}
                @if($showFinance)
                <div class="mb-1">
                    <button @click="financeOpen = !financeOpen"
                        class="flex items-center justify-between w-full px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                        <span class="flex items-center gap-2">
                            <i class="fa-solid fa-coins w-4 text-[#8B7355]"></i>
                            Finance
                        </span>
                        <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                            :class="financeOpen ? 'transform rotate-180' : ''"></i>
                    </button>

                    <div x-show="financeOpen" x-collapse class="ml-4 space-y-1">
                        @can('view revenue')
                        <x-nav-link :href="route('finance.revenue')" :active="request()->routeIs('finance.revenue')">
                            <i class="fa-solid fa-chart-line w-4 mr-1 text-[#8B7355]"></i>
                            Revenue
                        </x-nav-link>
                        @endcan

                        @can('view billing')
                        <x-nav-link :href="route('finance.billing')" :active="request()->routeIs('finance.billing')">
                            <i class="fa-solid fa-file-invoice-dollar w-4 mr-1 text-[#8B7355]"></i>
                            Billing
                        </x-nav-link>
                        @endcan

                        @can('view finance inventory')
                        <x-nav-link :href="route('finance.inventory')" :active="request()->routeIs('finance.inventory')">
                            <i class="fa-solid fa-boxes-stacked w-4 mr-1 text-[#8B7355]"></i>
                            Inventory
                        </x-nav-link>
                        @endcan
                    </div>
                </div>
                @endif

                {{-- Insights Section --}}
                @if($showInsights)
                <div class="mb-1">
                    <button @click="insightsOpen = !insightsOpen"
                        class="flex items-center justify-between w-full px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                        <span class="flex items-center gap-2">
                            <i class="fa-solid fa-chart-line w-4 text-[#8B7355]"></i>
                            Insights
                        </span>
                        <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                            :class="insightsOpen ? 'transform rotate-180' : ''"></i>
                    </button>

                    <div x-show="insightsOpen" x-collapse class="ml-4 space-y-1">
                        @can('view decision support')
                        <x-nav-link :href="route('decision-support.index')" :active="request()->routeIs('decision-support.*')">
                            <i class="fa-solid fa-brain w-4 mr-1 text-[#8B7355]"></i>
                            Decision Support
                        </x-nav-link>
                        @endcan

                        @can('view reports')
                        <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                            <i class="fa-solid fa-chart-bar w-4 mr-1 text-[#8B7355]"></i>
                            Reports
                        </x-nav-link>
                        @endcan
                    </div>
                </div>
                @endif

                {{-- Settings --}}
                <div class="mb-1">
                    <button @click="settingsOpen = !settingsOpen"
                        class="flex items-center justify-between w-full px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                        <span class="flex items-center gap-2">
                            <i class="fa-solid fa-gear w-4 text-[#8B7355]"></i>
                            Settings
                        </span>
                        <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                            :class="settingsOpen ? 'transform rotate-180' : ''"></i>
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
                        <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $user?->name }}</p>
                        <p class="text-xs text-gray-500 truncate dark:text-gray-400">{{ $user?->email }}</p>
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

    {{-- Mobile Overlay --}}
    <div x-show="open" @click="open = false" class="fixed inset-0 z-30 bg-black bg-opacity-40 md:hidden"></div>

    {{-- Logout Modal --}}
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
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Confirm Logout</h3>
                <button @click="showLogoutModal = false"
                    class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
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
                            You will be logged out of your Finance account.
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
                    <form method="POST" action="{{ route('logout') }}">
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

    {{-- Main Content --}}
    <main class="flex-1 h-screen overflow-y-auto md:ml-64">
        <div class="p-4 pt-16 md:p-4 md:pt-4">
            @yield('content')
        </div>
    </main>

</div>

<style>
    [x-cloak] { display: none !important; }

    .overflow-y-auto::-webkit-scrollbar { width: 6px; }
    .overflow-y-auto::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 3px; }
    .overflow-y-auto::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 3px; }
    .dark .overflow-y-auto::-webkit-scrollbar-track { background: #374151; }
    .dark .overflow-y-auto::-webkit-scrollbar-thumb { background: #6b7280; }

    [x-collapse] {
        overflow: hidden;
        transition: all 0.3s ease-in-out;
    }
</style>
