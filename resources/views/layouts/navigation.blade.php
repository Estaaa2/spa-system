@php
    use Illuminate\Support\Str;

    $user = Auth::user();
    $spa = $user?->spa;

    if ($user?->hasRole('owner')) {
        $branches = $spa?->branches ?? collect();
    } else {
        $branches = $spa?->branches ? $spa->branches->where('id', $user->branch_id) : collect();
    }

    $firstBranch = $branches->first();
    $currentBranchId = session('current_branch_id');
    $currentBranch = $branches->firstWhere('id', $currentBranchId) ?? $firstBranch;

    $can = fn($permission) => $user?->hasBranchPermission($permission) ?? false;

    $suiteEnabled =
        ($spa?->business_tier ?? null) === 'professional' &&
        (bool) ($currentBranch?->has_workforce_finance_suite ?? false);

    $canWorkforceFinanceSuiteSettings = $user?->hasRole('owner') && ($spa?->business_tier ?? null) === 'professional';

    // Same check the role('owner') directive performs, in a form the section wrappers can reuse.
    $isOwner = (bool) ($user?->hasRole('owner') ?? false);

    // Dashboard
    $canDashboard = $user?->can('view business dashboard');

    // Operations
    $canBooking = $can('book appointments');
    $canAppointments = $can('view appointments');
    $canSchedule = $can('view schedule');

    $canAttendanceLeave =
        $can('view attendance') ||
        $can('edit attendance') ||
        $can('view leave requests') ||
        $can('create leave requests') ||
        $can('edit leave requests') ||
        $can('delete leave requests');

    $showOperations = $canBooking || $canAppointments || $canSchedule || (!$suiteEnabled && $canAttendanceLeave);
    $canViewPerformance = $user?->hasRole('therapist') ?? false;

    // People
    $canStaffAccounts = $can('view staff') || $can('create staff') || $can('edit staff') || $can('delete staff');

    $canHiring = $can('view hiring') || $can('create hiring') || $can('edit hiring') || $can('delete hiring');

    $canApplicants = $can('view applications') || $can('edit applications') || $can('delete applications');

    $canInterviews =
        $can('view interviews') || $can('create interviews') || $can('edit interviews') || $can('delete interviews');

    $showPeople =
        $suiteEnabled && ($canStaffAccounts || $canAttendanceLeave || $canHiring || $canApplicants || $canInterviews);

    // Services (was "Management")
    $canServices =
        $can('view services') ||
        $can('create treatments') ||
        $can('edit treatments') ||
        $can('delete treatments') ||
        $can('create packages') ||
        $can('edit packages') ||
        $can('delete packages');

    $canBranches = $can('view branches') || $can('create branches') || $can('edit branches') || $can('delete branches');

    $canManagementStaff = !$suiteEnabled && $canStaffAccounts;

    $showServices = $canServices || $canManagementStaff;

    // ── Scope split ──────────────────────────────────────────────────────────
    // "This Branch" = branch-scoped settings; content follows the branch switcher.
    // "Business"    = spa-wide; the switcher does not affect it.
    //
    // branches.index is reachable from exactly one of the two, never both:
    // owners reach it as "All Branches" under Business, everyone else as
    // "Branch Details" under This Branch. BranchController::index() already
    // filters non-owners down to their own branch, so the label matches reality.
    $canEditBranchSettings = $can('edit branches');

    $showThisBranch = $isOwner || $canBranches || $canEditBranchSettings;
    $showBusiness   = $isOwner;

    // Finance
    $canPayroll = $can('view payroll') || $can('edit payroll');
    $canRevenue = $can('view revenue');
    $canBilling = $can('view billing') || $can('create billing') || $can('edit billing') || $can('delete billing');

    $showFinance = $suiteEnabled && ($canPayroll || $canRevenue || $canBilling);

    // Insights
    $canDecisionSupport = $can('view decision support');
    $canReports = $can('view reports');
    $showInsights = $canDecisionSupport || $canReports;

    // Inventory
    $canProductInventory =
        $can('view product inventory') ||
        $can('create product inventory') ||
        $can('edit product inventory') ||
        $can('delete product inventory');

    $canProductLogs = $can('view product logs');

    $showInventory = $canProductInventory || $canProductLogs;

    // Single source for "which collapsible section owns the current route".
    // Consumed twice: by the collapsed-section dot below, and by the sidebar
    // Alpine component via @json. Never hand-duplicate these route matches.
    $sectionRoutes = [
        'operations' => request()->routeIs('booking')
            || request()->routeIs('appointments.*')
            || request()->routeIs('schedule.*')
            || request()->routeIs('therapist.performance')
            || (!$suiteEnabled && request()->routeIs('attendance.*')),

        'people' => $suiteEnabled && (
            request()->routeIs('hiring.*')
            || request()->routeIs('applications.*')
            || request()->routeIs('interviews.*')
            || request()->routeIs('staff.*')
            || request()->routeIs('deployment.*')
            || request()->routeIs('payroll.*')
            || request()->routeIs('attendance.*')
        ),

        'services' => request()->routeIs('services.*')
            || (!$suiteEnabled && request()->routeIs('staff.*')),

        'finance' => request()->routeIs('revenue.*')
            || request()->routeIs('billing.*'),

        'insights' => request()->routeIs('decision-support.*')
            || request()->routeIs('reports.*'),

        'inventory' => request()->routeIs('inventory.*'),

        'thisBranch' => request()->routeIs('owner.roles-permissions.*')
            || (!$isOwner && request()->routeIs('branches.*')),

        'business' => request()->routeIs('owner.spa-profile.*')
            || request()->routeIs('owner.subscription.*')
            || request()->routeIs('owner.workforce-finance-suite.*')
            || ($isOwner && request()->routeIs('branches.*')),
    ];

    $activeSection = collect($sectionRoutes)->filter()->keys()->first();

    $brandHref = route('dashboard');

    $mobileContextLabel = $currentBranch?->name ?? $spa?->name ?? 'Levictas';
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

    [x-collapse] {
        overflow: hidden;
        transition: all 0.3s ease-in-out;
    }

    .relative .fa-crown {
        font-size: 0.5rem;
        filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.3));
    }
</style>

<div x-data="sidebar" @keydown.escape.window="open = false" class="flex h-screen bg-gray-100 dark:bg-gray-900">

    <a href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[60] focus:px-4 focus:py-2 focus:rounded-lg focus:bg-white focus:text-[#6F5430] focus:ring-2 focus:ring-[#8B7355] dark:focus:bg-gray-800 dark:focus:text-[#C4A97D]">
        Skip to content
    </a>

    <!-- MOBILE TOPBAR -->
    <div
        class="fixed top-0 z-40 flex items-center justify-between w-full h-14 px-2 bg-white border-b md:hidden dark:bg-gray-800 dark:border-gray-700">
        <button type="button" @click="open = !open"
            aria-label="Toggle navigation" aria-controls="app-sidebar"
            :aria-expanded="open ? 'true' : 'false'"
            class="inline-flex items-center justify-center flex-shrink-0 text-gray-700 rounded-lg w-11 h-11 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
            <i class="text-xl fa-solid fa-bars"></i>
        </button>

        <!-- Mobile Branch Switcher -->
        @role('owner')
            <div class="relative pr-2">
                <button @click="mobileBranchesOpen = !mobileBranchesOpen"
                    class="flex items-center px-2 space-x-2 text-gray-700 rounded-lg h-11 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <span class="text-sm font-medium truncate max-w-[120px]" x-text="selectedBranch"></span>
                    <i class="text-xs fa-solid fa-chevron-down" :class="mobileBranchesOpen ? 'rotate-180' : ''"></i>
                </button>

                <div x-show="mobileBranchesOpen" @click.outside="mobileBranchesOpen = false" x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="transform opacity-0 scale-95"
                    x-transition:enter-end="transform opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="transform opacity-100 scale-100"
                    x-transition:leave-end="transform opacity-0 scale-95"
                    class="absolute right-0 z-50 w-56 mt-2 origin-top-right bg-white rounded-md shadow-lg dark:bg-gray-800 ring-1 ring-black/5">
                    <div class="py-1" role="menu">
                        <div class="px-4 py-1 text-xs font-medium text-gray-500 dark:text-gray-400">SWITCH BRANCH</div>

                        @foreach ($branches as $branch)
                            <button
                                @click="
                                selectedBranch = {{ Js::from($branch->name) }};
                                selectedBranchId = {{ $branch->id }};
                                mobileBranchesOpen = false;
                                switchBranch({{ $branch->id }});
                            "
                                :class="selectedBranchId == {{ $branch->id }} ? 'bg-gray-100 dark:bg-gray-700' : ''"
                                class="flex items-center w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                                @if ($branch->is_main)
                                    <i class="w-4 mr-2 text-yellow-500 fa-solid fa-crown" title="Main Branch"></i>
                                @else
                                    <i class="w-4 mr-2 text-gray-500 fa-solid fa-store dark:text-gray-400"></i>
                                @endif

                                <div class="flex-1 min-w-0">
                                    <span class="truncate">{{ $branch->name }}</span>
                                    @if ($branch->location)
                                        <p class="text-xs text-gray-500 truncate dark:text-gray-400">
                                            {{ Str::limit($branch->location, 20) }}</p>
                                    @endif
                                </div>

                                @if (($spa?->business_tier ?? null) === 'professional')
                                    <span
                                        class="ml-2 text-[10px] px-2 py-0.5 rounded-full {{ $branch->has_workforce_finance_suite ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                                        {{ $branch->has_workforce_finance_suite ? 'Suite' : 'Basic' }}
                                    </span>
                                @endif

                                <span x-show="selectedBranchId == {{ $branch->id }}"
                                    class="ml-2 text-blue-600 dark:text-blue-400">
                                    <i class="fa-solid fa-check"></i>
                                </span>
                            </button>
                        @endforeach

                        @if ($canBranches)
                            <div class="px-4 py-2 text-xs text-gray-500 border-t dark:text-gray-400 dark:border-gray-700">
                                <a href="{{ route('branches.index') }}"
                                    class="flex items-center text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                    <i class="w-4 mr-1 fa-solid fa-cog"></i>
                                    Manage Branches
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @else
            {{-- Non-owners have no switcher, so the bar would otherwise be empty. --}}
            <div class="flex items-center min-w-0 gap-2 pr-3">
                <i class="text-sm text-gray-500 fa-solid fa-location-dot dark:text-gray-400"></i>
                <span class="text-sm font-medium text-gray-700 truncate dark:text-gray-200">{{ $mobileContextLabel }}</span>
            </div>
        @endrole
    </div>

    <!-- SIDEBAR -->
    {{-- z-50 so the mobile overlay (z-40) can sit above the topbar without covering the drawer. --}}
    <aside id="app-sidebar"
        class="fixed inset-y-0 left-0 z-50 w-64 transition-transform duration-200 bg-white border-r dark:bg-gray-800 dark:border-gray-700"
        :class="open ? 'is-open' : ''">
        <div class="flex flex-col h-full">

            <!-- Brand with Branch Switcher -->
            <div class="flex-shrink-0 border-b dark:border-gray-700">
                <div class="flex items-start justify-between gap-2 py-4 pl-6 pr-3 md:pr-6">
                    <a href="{{ $brandHref }}" class="flex items-center flex-1 min-w-0 space-x-3">
                        <img src="{{ asset('images/1.png') }}" class="h-10 rounded-md" alt="Levictas">
                        <div class="min-w-0">
                            <span
                                class="text-2xl font-semibold text-[#8B7355] dark:text-white font-['Playfair_Display']">
                                {{ $spa?->name ?? 'Spa Management' }}
                            </span>
                            <p class="text-xs tracking-widest text-gray-500 dark:text-gray-400">SPA | WELLNESS</p>
                        </div>
                    </a>

                    <button type="button" @click="open = false" aria-label="Close navigation"
                        class="inline-flex items-center justify-center flex-shrink-0 -mt-1 text-gray-700 rounded-lg w-11 h-11 md:hidden hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                        <i class="text-lg fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- BRANCH SWITCHER -->
                @if ($branches->isNotEmpty())
                    <div class="px-6 pb-4">
                        <div class="relative">
                            <button @click="branchesDropdown = !branchesDropdown"
                                class="flex items-center justify-between w-full px-4 py-3 text-sm text-left transition-colors rounded-lg bg-gray-50 hover:bg-gray-100 dark:bg-gray-700/50 dark:hover:bg-gray-700 dark:text-gray-200">
                                <div class="flex items-center flex-1 min-w-0">
                                    <i
                                        class="flex-shrink-0 mr-3 text-gray-500 fa-solid fa-location-dot dark:text-gray-400"></i>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium truncate" x-text="selectedBranch"></p>
                                        <p class="text-xs text-gray-500 truncate dark:text-gray-400">
                                            {{ $branches->count() }} {{ Str::plural('branch', $branches->count()) }}
                                            available
                                        </p>
                                    </div>
                                </div>
                                <i class="flex-shrink-0 ml-2 text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                                    :class="branchesDropdown ? 'transform rotate-180' : ''"></i>
                            </button>

                            <div x-show="branchesDropdown" @click.outside="branchesDropdown = false" x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="absolute left-0 right-0 z-50 mx-6 mt-1 overflow-y-auto origin-top bg-white rounded-lg shadow-lg dark:bg-gray-800 ring-1 ring-black/5 max-h-96">
                                <div class="py-2">
                                    <div
                                        class="flex items-center justify-between px-4 py-2 border-b dark:border-gray-700">
                                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">SELECT
                                            BRANCH</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $branches->count() }}
                                            total</span>
                                    </div>

                                    <div class="py-1">
                                        @foreach ($branches as $branch)
                                            <button
                                                @click="
                                                    selectedBranch = {{ Js::from($branch->name) }};
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
                                                            <i class="text-gray-500 fa-solid fa-store dark:text-gray-400"></i>
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
                                                                {{ Str::limit($branch->location, 25) }}</p>
                                                        @endif

                                                        @if ($branch->phone)
                                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                                {{ $branch->phone }}</p>
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-2">
                                                    @if (($spa?->business_tier ?? null) === 'professional')
                                                        <span
                                                            class="text-[10px] px-2 py-0.5 rounded-full {{ $branch->has_workforce_finance_suite ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                                                            {{ $branch->has_workforce_finance_suite ? 'Suite' : 'Basic' }}
                                                        </span>
                                                    @endif

                                                    <span x-show="selectedBranchId == {{ $branch->id }}"
                                                        class="text-blue-600 dark:text-blue-400">
                                                        <i class="fa-solid fa-check"></i>
                                                    </span>
                                                </div>
                                            </button>
                                        @endforeach
                                    </div>

                                    @if ($canBranches)
                                        <div class="pt-1 mt-1 border-t dark:border-gray-700">
                                            <a href="{{ route('branches.index') }}"
                                                class="flex items-center justify-center px-4 py-2 text-sm text-center text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">
                                                <i class="w-4 mr-2 fa-solid fa-cog"></i>
                                                Manage All Branches
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @else
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

            <!-- Navigation -->
            <nav aria-label="Main" class="flex-1 px-4 py-4 overflow-y-auto">

                {{-- Everything under this label follows the branch switcher above. --}}
                <p class="px-4 pb-1 text-[11px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                    Branch
                </p>

                <div class="space-y-1">
                    <!-- Dashboard -->
                    @if ($canDashboard)
                        <div class="mb-1">
                            <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                                <i class="fa-solid fa-gauge-high w-4 mr-2 text-[#8B7355] dark:text-[#C4A97D]"></i>
                                Dashboard
                            </x-nav-link>
                        </div>
                    @endif

                @if ($showOperations)
                    <div class="mb-1">
                        <button @click="toggleSection('operations')" type="button"
                            aria-controls="nav-section-operations"
                            :aria-expanded="isOpen('operations') ? 'true' : 'false'"
                            class="flex items-center justify-between w-full min-h-[44px] px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-calendar-check w-4 text-[#8B7355] dark:text-[#C4A97D]"></i>
                                Operations
                            </span>
                            <span class="flex items-center gap-2">
                                @if ($activeSection === 'operations')
                                    {{-- Collapsed sections would otherwise hide the fact that the current page lives inside. --}}
                                    <span x-show="!isOpen('operations')" x-cloak aria-hidden="true"
                                        class="w-1.5 h-1.5 rounded-full bg-[#8B7355] dark:bg-[#C4A97D]"></span>
                                @endif
                                <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                                    :class="isOpen('operations') ? 'transform rotate-180' : ''"></i>
                            </span>
                        </button>

                        <div x-show="isOpen('operations')" x-collapse id="nav-section-operations" class="ml-4 space-y-1">
                            @if ($canBooking)
                                <x-nav-link :href="route('booking')" :active="request()->routeIs('booking')">
                                    Book an Appointment
                                </x-nav-link>
                            @endif
                            @if ($canAppointments)
                                <x-nav-link :href="route('appointments.index')" :active="request()->routeIs('appointments.*')">
                                    Appointments
                                </x-nav-link>
                            @endif
                            @if ($canSchedule)
                                <x-nav-link :href="route('schedule.index')" :active="request()->routeIs('schedule.*')">
                                    Schedule
                                </x-nav-link>
                            @endif
                            @if ($canViewPerformance)
                                <x-nav-link :href="route('therapist.performance')" :active="request()->routeIs('therapist.performance')">
                                    My Performance
                                </x-nav-link>
                            @endif
                            @if (!$suiteEnabled && $canAttendanceLeave)
                                <x-nav-link :href="route('attendance.index')" :active="request()->routeIs('attendance.*')">
                                    Attendance &amp; Leave
                                </x-nav-link>
                            @endif

                        </div>
                    </div>
                @endif

                @if ($showPeople)
                    <div class="mb-1">
                        <button @click="toggleSection('people')" type="button"
                            aria-controls="nav-section-people"
                            :aria-expanded="isOpen('people') ? 'true' : 'false'"
                            class="flex items-center justify-between w-full min-h-[44px] px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-users w-4 text-[#8B7355] dark:text-[#C4A97D]"></i>
                                Manpower
                            </span>
                            <span class="flex items-center gap-2">
                                @if ($activeSection === 'people')
                                    {{-- Collapsed sections would otherwise hide the fact that the current page lives inside. --}}
                                    <span x-show="!isOpen('people')" x-cloak aria-hidden="true"
                                        class="w-1.5 h-1.5 rounded-full bg-[#8B7355] dark:bg-[#C4A97D]"></span>
                                @endif
                                <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                                    :class="isOpen('people') ? 'transform rotate-180' : ''"></i>
                            </span>
                        </button>

                        <div x-show="isOpen('people')" x-collapse id="nav-section-people" class="ml-4 space-y-1">
                            @if ($canHiring)
                                <x-nav-link :href="route('hiring.index')" :active="request()->routeIs('hiring.*')">
                                    Application Form
                                </x-nav-link>
                            @endif
                            @if ($canApplicants)
                                <x-nav-link :href="route('applications.index')" :active="request()->routeIs('applications.*')">
                                    Applicants
                                </x-nav-link>
                            @endif
                            @if ($canInterviews)
                                <x-nav-link :href="route('interviews.index')" :active="request()->routeIs('interviews.*')">
                                    Interviews
                                </x-nav-link>
                            @endif
                            @if ($canStaffAccounts)
                                <x-nav-link :href="route('staff.index')" :active="request()->routeIs('staff.*')">
                                    Staff
                                </x-nav-link>
                            @endif
                            @if ($canHiring)
                                <x-nav-link :href="route('deployment.index')" :active="request()->routeIs('deployment.*')">
                                    Staff Deployment
                                </x-nav-link>
                            @endif
                            @if ($canAttendanceLeave)
                                <x-nav-link :href="route('attendance.index')" :active="request()->routeIs('attendance.*')">
                                    Attendance &amp; Leave
                                </x-nav-link>
                            @endif
                            @if ($canPayroll)
                                <x-nav-link :href="route('payroll.index')" :active="request()->routeIs('payroll.*')">
                                    Payroll
                                </x-nav-link>
                            @endif

                        </div>
                    </div>
                @endif

                @if ($showServices)
                    <div class="mb-1">
                        <button @click="toggleSection('services')" type="button"
                            aria-controls="nav-section-services"
                            :aria-expanded="isOpen('services') ? 'true' : 'false'"
                            class="flex items-center justify-between w-full min-h-[44px] px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-spa w-4 text-[#8B7355] dark:text-[#C4A97D]"></i>
                                Services
                            </span>
                            <span class="flex items-center gap-2">
                                @if ($activeSection === 'services')
                                    {{-- Collapsed sections would otherwise hide the fact that the current page lives inside. --}}
                                    <span x-show="!isOpen('services')" x-cloak aria-hidden="true"
                                        class="w-1.5 h-1.5 rounded-full bg-[#8B7355] dark:bg-[#C4A97D]"></span>
                                @endif
                                <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                                    :class="isOpen('services') ? 'transform rotate-180' : ''"></i>
                            </span>
                        </button>

                        <div x-show="isOpen('services')" x-collapse id="nav-section-services" class="ml-4 space-y-1">
                            @if ($canServices)
                                <x-nav-link :href="route('services.index')" :active="request()->routeIs('services.*')">
                                    Services
                                </x-nav-link>
                            @endif
                            @if ($canManagementStaff)
                                <x-nav-link :href="route('staff.index')" :active="request()->routeIs('staff.*')">
                                    Staff
                                </x-nav-link>
                            @endif

                        </div>
                    </div>
                @endif

                @if ($showFinance)
                    <div class="mb-1">
                        <button @click="toggleSection('finance')" type="button"
                            aria-controls="nav-section-finance"
                            :aria-expanded="isOpen('finance') ? 'true' : 'false'"
                            class="flex items-center justify-between w-full min-h-[44px] px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-wallet w-4 text-[#8B7355] dark:text-[#C4A97D]"></i>
                                Finance
                            </span>
                            <span class="flex items-center gap-2">
                                @if ($activeSection === 'finance')
                                    {{-- Collapsed sections would otherwise hide the fact that the current page lives inside. --}}
                                    <span x-show="!isOpen('finance')" x-cloak aria-hidden="true"
                                        class="w-1.5 h-1.5 rounded-full bg-[#8B7355] dark:bg-[#C4A97D]"></span>
                                @endif
                                <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                                    :class="isOpen('finance') ? 'transform rotate-180' : ''"></i>
                            </span>
                        </button>

                        <div x-show="isOpen('finance')" x-collapse id="nav-section-finance" class="ml-4 space-y-1">
                            @if ($canRevenue)
                                <x-nav-link :href="route('revenue.index')" :active="request()->routeIs('revenue.*')">
                                    Revenue
                                </x-nav-link>
                            @endif
                            @if ($canBilling)
                                <x-nav-link :href="route('billing.index')" :active="request()->routeIs('billing.*')">
                                    Billing &amp; Expenses
                                </x-nav-link>
                            @endif

                        </div>
                    </div>
                @endif

                @if ($showInsights)
                    <div class="mb-1">
                        <button @click="toggleSection('insights')" type="button"
                            aria-controls="nav-section-insights"
                            :aria-expanded="isOpen('insights') ? 'true' : 'false'"
                            class="flex items-center justify-between w-full min-h-[44px] px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-chart-line w-4 text-[#8B7355] dark:text-[#C4A97D]"></i>
                                Insights
                            </span>
                            <span class="flex items-center gap-2">
                                @if ($activeSection === 'insights')
                                    {{-- Collapsed sections would otherwise hide the fact that the current page lives inside. --}}
                                    <span x-show="!isOpen('insights')" x-cloak aria-hidden="true"
                                        class="w-1.5 h-1.5 rounded-full bg-[#8B7355] dark:bg-[#C4A97D]"></span>
                                @endif
                                <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                                    :class="isOpen('insights') ? 'transform rotate-180' : ''"></i>
                            </span>
                        </button>

                        <div x-show="isOpen('insights')" x-collapse id="nav-section-insights" class="ml-4 space-y-1">
                            @if ($canDecisionSupport)
                                <x-nav-link :href="route('decision-support.index')" :active="request()->routeIs('decision-support.*')">
                                    Decision Support
                                </x-nav-link>
                            @endif
                            @if ($canReports)
                                <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                                    Reports
                                </x-nav-link>
                            @endif

                        </div>
                    </div>
                @endif

                @if ($showInventory)
                    <div class="mb-1">
                        <button @click="toggleSection('inventory')" type="button"
                            aria-controls="nav-section-inventory"
                            :aria-expanded="isOpen('inventory') ? 'true' : 'false'"
                            class="flex items-center justify-between w-full min-h-[44px] px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-boxes-stacked w-4 text-[#8B7355] dark:text-[#C4A97D]"></i>
                                Inventory
                            </span>
                            <span class="flex items-center gap-2">
                                @if ($activeSection === 'inventory')
                                    {{-- Collapsed sections would otherwise hide the fact that the current page lives inside. --}}
                                    <span x-show="!isOpen('inventory')" x-cloak aria-hidden="true"
                                        class="w-1.5 h-1.5 rounded-full bg-[#8B7355] dark:bg-[#C4A97D]"></span>
                                @endif
                                <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                                    :class="isOpen('inventory') ? 'transform rotate-180' : ''"></i>
                            </span>
                        </button>

                        <div x-show="isOpen('inventory')" x-collapse id="nav-section-inventory" class="ml-4 space-y-1">
                            @if ($canProductInventory)
                                <x-nav-link :href="route('inventory.products')" :active="request()->routeIs('inventory.products')">
                                    Product Inventory
                                </x-nav-link>
                            @endif
                            @if ($canProductLogs)
                                <x-nav-link :href="route('inventory.logs')" :active="request()->routeIs('inventory.logs')">
                                    Product Logs
                                </x-nav-link>
                            @endif

                        </div>
                    </div>
                @endif

                @if ($showThisBranch)
                    <div class="mb-1">
                        <button @click="toggleSection('thisBranch')" type="button"
                            aria-controls="nav-section-thisBranch"
                            :aria-expanded="isOpen('thisBranch') ? 'true' : 'false'"
                            class="flex items-center justify-between w-full min-h-[44px] px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-store w-4 text-[#8B7355] dark:text-[#C4A97D]"></i>
                                This Branch
                            </span>
                            <span class="flex items-center gap-2">
                                @if ($activeSection === 'thisBranch')
                                    {{-- Collapsed sections would otherwise hide the fact that the current page lives inside. --}}
                                    <span x-show="!isOpen('thisBranch')" x-cloak aria-hidden="true"
                                        class="w-1.5 h-1.5 rounded-full bg-[#8B7355] dark:bg-[#C4A97D]"></span>
                                @endif
                                <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                                    :class="isOpen('thisBranch') ? 'transform rotate-180' : ''"></i>
                            </span>
                        </button>

                        <div x-show="isOpen('thisBranch')" x-collapse id="nav-section-thisBranch" class="ml-4 space-y-1">
                            @if ($canEditBranchSettings && $currentBranch)
                                <x-nav-link :href="route('branches.edit', $currentBranch)" :active="request()->routeIs('branches.edit')">
                                    Branch Settings
                                </x-nav-link>
                            @endif

                            @role('owner')
                                <x-nav-link :href="route('owner.roles-permissions.index')" :active="request()->routeIs('owner.roles-permissions.*')">
                                    Roles &amp; Permissions
                                </x-nav-link>
                            @endrole

                        </div>
                    </div>
                @endif
                </div>

                @if ($showBusiness)
                    {{-- Hard break: nothing below here reacts to the branch switcher. --}}
                    <hr class="my-4 border-gray-200 dark:border-gray-700">

                    <p class="px-4 pb-1 text-[11px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                        Spa-wide
                    </p>

                    <div class="space-y-1">

                @if ($showBusiness)
                    <div class="mb-1">
                        <button @click="toggleSection('business')" type="button"
                            aria-controls="nav-section-business"
                            :aria-expanded="isOpen('business') ? 'true' : 'false'"
                            class="flex items-center justify-between w-full min-h-[44px] px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-building w-4 text-[#8B7355] dark:text-[#C4A97D]"></i>
                                Business
                            </span>
                            <span class="flex items-center gap-2">
                                @if ($activeSection === 'business')
                                    {{-- Collapsed sections would otherwise hide the fact that the current page lives inside. --}}
                                    <span x-show="!isOpen('business')" x-cloak aria-hidden="true"
                                        class="w-1.5 h-1.5 rounded-full bg-[#8B7355] dark:bg-[#C4A97D]"></span>
                                @endif
                                <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                                    :class="isOpen('business') ? 'transform rotate-180' : ''"></i>
                            </span>
                        </button>

                        <div x-show="isOpen('business')" x-collapse id="nav-section-business" class="ml-4 space-y-1">
                            @if ($canBranches)
                                <x-nav-link :href="route('branches.index')" :active="request()->routeIs('branches.index')">
                                    All Branches
                                </x-nav-link>
                            @endif

                            @role('owner')
                                <x-nav-link :href="route('owner.spa-profile.edit')" :active="request()->routeIs('owner.spa-profile.*')">
                                    Spa Profile
                                </x-nav-link>
                            @endrole

                            @role('owner')
                                <x-nav-link :href="route('owner.subscription.index')" :active="request()->routeIs('owner.subscription.*')">
                                    Subscription &amp; Billing
                                </x-nav-link>
                            @endrole

                            @if ($canWorkforceFinanceSuiteSettings)
                                <x-nav-link :href="route('owner.workforce-finance-suite.index')" :active="request()->routeIs('owner.workforce-finance-suite.*')">
                                    Workforce &amp; Finance Suite
                                </x-nav-link>
                            @endif

                        </div>
                    </div>
                @endif
                    </div>
                @endif

            </nav>

            <!-- ACCOUNT & LOGOUT -->
            <div class="flex-shrink-0 p-3 border-t dark:border-gray-700">
                @auth
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
                @else
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Not logged in</p>
                        <a href="{{ route('login') }}"
                        class="inline-flex items-center justify-center w-full px-4 py-2 mt-2 text-sm font-medium text-white transition-colors rounded-lg bg-[#8B7355] hover:bg-[#6F5430]">
                            <i class="mr-2 fa-solid fa-sign-in-alt"></i>
                            Login
                        </a>
                    </div>
                @endauth
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
                    <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-full">
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
                <form method="POST" action="{{ route('logout') }}" id="logoutForm"
                      x-on:submit="clearSidebarState()" class="flex-1">
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
    <main id="main-content" tabindex="-1" class="flex-1 h-screen overflow-y-auto md:ml-64">
        <div class="pt-14 md:pt-0">
            @yield('content')
        </div>
    </main>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('sidebar', () => {
        const STORAGE_KEY = 'levictas_sidebar_open_section_{{ auth()->id() ?? "guest" }}';

        // Emitted from $sectionRoutes in the PHP block at the top of this file —
        // the one place these route matches are written. Do not re-derive them here.
        const routeSection = @json($activeSection);

        let saved = null;
        try { saved = localStorage.getItem(STORAGE_KEY) || null; } catch (e) { saved = null; }

        return {
            open: false,
            mobileBranchesOpen: false,
            branchesDropdown: false,
            showLogoutModal: false,

            selectedBranch: {{ Js::from($currentBranch?->name ?? 'Select Branch') }},
            selectedBranchId: {{ $currentBranch?->id ?? 'null' }},

            openSection: routeSection || saved,

            init() {
                if (routeSection) {
                    this.openSection = routeSection;
                    this.persist();
                }
            },

            isOpen(name) {
                return this.openSection === name;
            },

            toggleSection(name) {
                this.openSection = (this.openSection === name) ? null : name;
                this.persist();
            },

            persist() {
                try {
                    if (this.openSection) {
                        localStorage.setItem(STORAGE_KEY, this.openSection);
                    } else {
                        localStorage.removeItem(STORAGE_KEY);
                    }
                } catch (e) {}
            },

            clearSidebarState() {
                try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
            },

            switchBranch(branchId) {
                fetch('/branch/switch', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ branch_id: branchId }),
                })
                
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showSpaToast('Branch switched successfully', 'success');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showSpaToast(data.message || 'Failed to switch branch', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showSpaToast('An error occurred. Please try again.', 'error');
                });
            }
        };
    });
});
</script>