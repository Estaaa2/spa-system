<div x-data="sidebar()" class="flex h-screen bg-gray-100 dark:bg-gray-900">

    <!-- MOBILE TOPBAR -->
    <div
        class="fixed top-0 z-40 flex items-center justify-between w-full px-4 py-3 bg-white border-b md:hidden dark:bg-gray-800 dark:border-gray-700">
        <button @click="open = true" class="text-gray-700 dark:text-gray-200">
            <i class="text-xl fa-solid fa-bars"></i>
        </button>

        <!-- Mobile Branch Switcher -->
        <div class="relative">
            <button @click="mobileBranchesOpen = !mobileBranchesOpen"
                class="flex items-center space-x-2 text-gray-700 dark:text-gray-200">
                <span class="text-sm font-medium truncate max-w-[120px]" x-text="selectedBranch"></span>
                <i class="text-xs fa-solid fa-chevron-down" :class="mobileBranchesOpen ? 'rotate-180' : ''"></i>
            </button>

            <!-- Mobile Dropdown -->
            <div x-show="mobileBranchesOpen" @click.outside="mobileBranchesOpen = false"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="transform opacity-0 scale-95"
                x-transition:enter-end="transform opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="transform opacity-100 scale-100"
                x-transition:leave-end="transform opacity-0 scale-95"
                class="absolute right-0 z-50 w-56 mt-2 origin-top-right bg-white rounded-md shadow-lg dark:bg-gray-800 ring-1 ring-black ring-opacity-5">
                <div class="py-1" role="menu" aria-orientation="vertical">
                    <div class="px-4 py-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                        SWITCH BRANCH
                    </div>

                    @foreach (Auth::user()->spa->branches ?? [] as $branch)
                        <button
                            @click="
                            selectedBranch = '{{ addslashes($branch->name) }}';
                            selectedBranchId = {{ $branch->id }};
                            mobileBranchesOpen = false;
                            switchBranch({{ $branch->id }});
                        "
                            :class="selectedBranchId == {{ $branch->id }} ? 'bg-gray-100 dark:bg-gray-700' : ''"
                            class="flex items-center w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                            @if ($branch->is_main)
                                <i class="w-4 mr-2 text-yellow-500 fa-solid fa-crown" title="Main Branch"></i>
                            @else
                                <i class="w-4 mr-2 text-gray-400 fa-solid fa-store"></i>
                            @endif
                            <div class="flex-1 min-w-0">
                                <span class="truncate">{{ $branch->name }}</span>
                                @if ($branch->location)
                                    <p class="text-xs text-gray-500 truncate dark:text-gray-400">
                                        {{ Str::limit($branch->location, 20) }}
                                    </p>
                                @endif
                            </div>
                            <span x-show="selectedBranchId == {{ $branch->id }}"
                                class="ml-2 text-blue-600 dark:text-blue-400">
                                <i class="fa-solid fa-check"></i>
                            </span>
                        </button>
                    @endforeach

                    <div class="px-4 py-2 text-xs text-gray-500 border-t dark:text-gray-400 dark:border-gray-700">
                        <a href="{{ route('branches.index') }}"
                            class="flex items-center text-blue-600 hover:text-blue-800 dark:text-blue-400">
                            <i class="w-4 mr-1 fa-solid fa-cog"></i>
                            Manage Branches
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SIDEBAR -->
    <aside
        class="fixed inset-y-0 left-0 z-40 w-64 transition-transform duration-200 transform bg-white border-r dark:bg-gray-800 dark:border-gray-700 md:translate-x-0"
        :class="open ? 'translate-x-0' : '-translate-x-full'">
        <div class="flex flex-col h-full">
            <!-- Brand with Branch Switcher -->
            <div class="flex-shrink-0 border-b dark:border-gray-700">
                <!-- Spa Brand -->
                <div class="px-6 py-4">
                    <a href="{{ url('/dashboard') }}" class="flex items-center space-x-3">
                        <img src="{{ asset('images/1.png') }}" class="h-10 rounded-md" alt="Levictas">
                        <div>
                            <span
                                class="text-2xl font-semibold text-[#8B7355] dark:text-white font-['Playfair_Display']">
                                {{ Auth::user()->spa->name ?? 'Spa Management' }}
                            </span>
                            <p class="text-xs tracking-widest text-gray-500 dark:text-gray-400">
                                SPA & WELLNESS
                            </p>
                        </div>
                    </a>
                </div>

                @php
                    $spa = Auth::user()->spa;
                    $branchesCount = $spa ? $spa->branches()->count() : 0;
                @endphp

                <!-- BRANCH SWITCHER -->
                @if (Auth::user()->spa && Auth::user()->spa->branches->count() > 0)
                    <div class="px-6 pb-4">
                        <div class="relative">
                            <!-- Branch Switcher Button -->
                            <button @click="branchesDropdown = !branchesDropdown"
                                class="flex items-center justify-between w-full px-4 py-3 text-sm text-left transition-colors rounded-lg bg-gray-50 hover:bg-gray-100 dark:bg-gray-700/50 dark:hover:bg-gray-700 dark:text-gray-200">
                                <div class="flex items-center flex-1 min-w-0">
                                    <i
                                        class="flex-shrink-0 mr-3 text-gray-500 fa-solid fa-location-dot dark:text-gray-400"></i>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium truncate" x-text="selectedBranch"></p>
                                        <p class="text-xs text-gray-500 truncate dark:text-gray-400">
                                            {{ Auth::user()->spa->branches->count() }}
                                            {{ Str::plural('branch', Auth::user()->spa->branches->count()) }} available
                                        </p>
                                    </div>
                                </div>
                                <i class="flex-shrink-0 ml-2 text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                                    :class="branchesDropdown ? 'transform rotate-180' : ''"></i>
                            </button>

                            <!-- Branch Switcher Dropdown -->
                            <div x-show="branchesDropdown" @click.outside="branchesDropdown = false"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="absolute left-0 right-0 z-50 mx-6 mt-1 overflow-y-auto origin-top bg-white rounded-lg shadow-lg dark:bg-gray-800 ring-1 ring-black ring-opacity-5 max-h-96">
                                <div class="py-2">
                                    <!-- Dropdown Header -->
                                    <div
                                        class="flex items-center justify-between px-4 py-2 border-b dark:border-gray-700">
                                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                            SELECT BRANCH
                                        </span>
                                        <span class="text-xs text-gray-400 dark:text-gray-500">
                                            {{ Auth::user()->spa->branches->count() }} total
                                        </span>
                                    </div>

                                    <!-- Branches List -->
                                    <div class="py-1">
                                        @foreach (Auth::user()->spa->branches as $branch)
                                            <button
                                                @click="
                                            selectedBranch = '{{ addslashes($branch->name) }}';
                                            selectedBranchId = {{ $branch->id }};
                                            branchesDropdown = false;
                                            switchBranch({{ $branch->id }});
                                        "
                                                class="flex items-center w-full px-4 py-3 text-sm text-left hover:bg-gray-50 dark:hover:bg-gray-700 group"
                                                :class="selectedBranchId == {{ $branch->id }} ?
                                                    'bg-blue-50 dark:bg-blue-900/20' : ''">
                                                <div class="flex items-center flex-1 min-w-0">
                                                    <div class="flex-shrink-0 mr-3">
                                                        @if ($branch->is_main)
                                                            <div class="relative">
                                                                <i class="text-yellow-500 fa-solid fa-store"
                                                                    title="Main Branch"></i>
                                                                <i
                                                                    class="absolute text-xs -top-1 -right-1 fa-solid fa-crown"></i>
                                                            </div>
                                                        @else
                                                            <i class="text-gray-400 fa-solid fa-store"></i>
                                                        @endif
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="font-medium text-gray-900 truncate dark:text-white"
                                                            :class="selectedBranchId == {{ $branch->id }} ?
                                                                'text-blue-600 dark:text-blue-400' : ''">
                                                            {{ $branch->name }}
                                                        </p>
                                                        @if ($branch->location)
                                                            <p
                                                                class="text-xs text-gray-500 truncate dark:text-gray-400">
                                                                {{ Str::limit($branch->location, 25) }}
                                                            </p>
                                                        @endif
                                                        @if ($branch->phone)
                                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                                {{ $branch->phone }}
                                                            </p>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="flex items-end">
                                                    <span x-show="selectedBranchId == {{ $branch->id }}"
                                                        class="text-blue-600 dark:text-blue-400">
                                                        <i class="fa-solid fa-check"></i>
                                                    </span>
                                                </div>
                                            </button>
                                        @endforeach
                                    </div>

                                    <!-- Branch Management Link -->
                                    <div class="pt-1 mt-1 border-t dark:border-gray-700">
                                        <a href="{{ route('branches.index') }}"
                                            class="flex items-center justify-center px-4 py-2 text-sm text-center text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">
                                            <i class="w-4 mr-2 fa-solid fa-cog"></i>
                                            Manage All Branches
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- No branches message -->
                    <div class="px-6 pb-4">
                        <div class="px-4 py-3 text-sm text-center rounded-lg bg-yellow-50 dark:bg-yellow-900/20">
                            <p class="text-yellow-800 dark:text-yellow-200">
                                <i class="mr-2 fa-solid fa-exclamation-triangle"></i>
                                No branches set up
                            </p>
                            <p class="mt-1 text-xs text-yellow-700 dark:text-yellow-300">
                                You need to create at least one branch to use the system
                            </p>
                            @if (\Illuminate\Support\Facades\Route::has('setup.branches'))
                                <a href="{{ route('setup.branches') }}"
                                    class="inline-flex items-center justify-center w-full px-3 py-2 mt-2 text-xs font-medium text-white bg-yellow-600 rounded-lg hover:bg-yellow-700 dark:bg-yellow-700 dark:hover:bg-yellow-600">
                                    <i class="mr-1 fa-solid fa-plus"></i>
                                    Create First Branch
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <!-- Navigation (scrollable) -->
            <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
                <!-- Dashboard -->
                <div class="mb-2">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        Dashboard
                    </x-nav-link>
                </div>

                <!-- Operations -->
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
                        <x-nav-link :href="route('staff.availability')" :active="request()->routeIs('staff.availability')">
                            Staff Availability
                        </x-nav-link>
                    </div>
                </div>

                <!-- Management -->
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
                        <x-nav-link :href="route('staff.index')" :active="request()->routeIs('staff.index*')">
                            Staff
                        </x-nav-link>
                        <x-nav-link :href="route('branches.index')" :active="request()->routeIs('branches.*')">
                            Branches
                        </x-nav-link>
                    </div>
                </div>

                <!-- Insights -->
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

                <!-- Administration -->
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
                <button @click="showLogoutModal = false"
                    class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                    <i class="text-xl fa-solid fa-times"></i>
                </button>
            </div>

            <div class="px-6 py-6">
                <div class="flex items-start">
                    <div
                        class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-red-100 rounded-full dark:bg-red-900/30">
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
    function switchBranch(branchId) {
        const button = event.target.closest('button');
        const originalContent = button.innerHTML;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        button.disabled = true;

        fetch('/branch/switch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    branch_id: branchId
                })
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showToast('Branch switched successfully', 'success');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(data.message || 'Failed to switch branch', 'error');
                    button.innerHTML = originalContent;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
                button.innerHTML = originalContent;
                button.disabled = false;
            });
    }

    function showToast(message, type = 'info') {
        const existingToasts = document.querySelectorAll('.branch-toast');
        existingToasts.forEach(toast => toast.remove());

        const toast = document.createElement('div');
        toast.className = `branch-toast fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg transform transition-all duration-300 translate-x-0 ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;

        toast.innerHTML = `
        <div class="flex items-center">
            <i class="mr-2 fa-solid ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    function sidebar() {
        return {
            open: false,
            showLogoutModal: false,
            operationsOpen: false,
            managementOpen: false,
            insightsOpen: false,
            administrationOpen: false,
            branchesDropdown: false,
            mobileBranchesOpen: false,
            selectedBranch: @json($currentBranch?->name ?? ($firstBranch?->name ?? 'Select Branch')),
            selectedBranchId: @json($currentBranchId ?? ($firstBranch?->id ?? null)),
        };
    }

    document.addEventListener('alpine:init', () => {
        window.switchBranch = switchBranch;
        window.showToast = showToast;
    });
</script>

<style>
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

    .dark .overflow-y-auto::-webkit-scrollbar-track {
        background: #374151;
    }

    .dark .overflow-y-auto::-webkit-scrollbar-thumb {
        background: #6b7280;
    }

    .transition {
        transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
        transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        transition-duration: 150ms;
    }

    [x-collapse] {
        overflow: hidden;
        transition: all 0.3s ease-in-out;
    }

    .branch-toast {
        animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .relative .fa-crown {
        font-size: 0.5rem;
        filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.3));
    }
</style>
