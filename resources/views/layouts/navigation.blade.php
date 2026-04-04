@php
    use Illuminate\Support\Str;

    $user = Auth::user();
    $spa  = $user?->spa;

    if ($user?->hasRole('owner')) {
        $branches = $spa?->branches ?? collect();
    } else {
        $branches = $spa?->branches
            ? $spa->branches->where('id', $user->branch_id)
            : collect();
    }

    $firstBranch     = $branches->first();
    $currentBranchId = session('current_branch_id');
    $currentBranch   = $branches->firstWhere('id', $currentBranchId) ?? $firstBranch;

    $can = fn($permission) => $user?->hasBranchPermission($permission) ?? false;

    $suiteEnabled = (($spa?->business_tier ?? null) === 'professional')
        && (bool) ($currentBranch?->has_workforce_finance_suite ?? false);

    $canWorkforceFinanceSuiteSettings =
        $user?->hasRole('owner') &&
        (($spa?->business_tier ?? null) === 'professional');

    // Dashboard
    $canDashboard = $user?->hasAnyRole(['owner', 'manager', 'therapist', 'receptionist']);

    // Operations
    $canBooking      = $can('book appointments');
    $canAppointments = $can('view appointments');
    $canSchedule     = $can('view schedule');

    $canAttendanceLeave =
        $can('view attendance') ||
        $can('edit attendance') ||
        $can('view leave requests') ||
        $can('create leave requests') ||
        $can('edit leave requests') ||
        $can('delete leave requests');

    $showOperations = $canBooking || $canAppointments || $canSchedule || (!$suiteEnabled && $canAttendanceLeave);

    // People
    $canStaffAccounts =
        $can('view staff') ||
        $can('create staff') ||
        $can('edit staff') ||
        $can('delete staff');

    $canHiring =
        $can('view hiring') ||
        $can('create hiring') ||
        $can('edit hiring') ||
        $can('delete hiring');

    $canApplicants =
        $can('view applications') ||
        $can('edit applications') ||
        $can('delete applications');

    $canInterviews =
        $can('view interviews') ||
        $can('create interviews') ||
        $can('edit interviews') ||
        $can('delete interviews');

    $showPeople = $suiteEnabled && (
        $canStaffAccounts ||
        $canAttendanceLeave ||
        $canHiring ||
        $canApplicants ||
        $canInterviews
    );

    // Management
    $canServices =
        $can('view services') ||
        $can('create treatments') ||
        $can('edit treatments') ||
        $can('delete treatments') ||
        $can('create packages') ||
        $can('edit packages') ||
        $can('delete packages');

    $canBranches =
        $can('view branches') ||
        $can('create branches') ||
        $can('edit branches') ||
        $can('delete branches');

    $canManagementStaff = !$suiteEnabled && $canStaffAccounts;

    $showManagement = $canServices || $canManagementStaff || $canBranches;

    // Finance
    $canPayroll = $can('view payroll') || $can('edit payroll');
    $canRevenue = $can('view revenue');
    $canBilling =
        $can('view billing') ||
        $can('create billing') ||
        $can('edit billing') ||
        $can('delete billing');

    $showFinance = $suiteEnabled && ($canPayroll || $canRevenue || $canBilling);

    // Insights
    $canDecisionSupport = $can('view decision support');
    $canReports         = $can('view reports');
    $showInsights       = $canDecisionSupport || $canReports;

    // Inventory
    $canProductInventory =
        $can('view product inventory') ||
        $can('create product inventory') ||
        $can('edit product inventory') ||
        $can('delete product inventory');

    $canProductLogs = $can('view product logs');

    $showInventory = $canProductInventory || $canProductLogs;

    $brandHref = route('dashboard');
@endphp

<div x-data="sidebar()" class="flex h-screen bg-gray-100 dark:bg-gray-900">

    <!-- MOBILE TOPBAR -->
    <div class="fixed top-0 z-40 flex items-center justify-between w-full px-4 py-3 bg-white border-b md:hidden dark:bg-gray-800 dark:border-gray-700">
        <button @click="open = true" class="text-gray-700 dark:text-gray-200">
            <i class="text-xl fa-solid fa-bars"></i>
        </button>

        <!-- Mobile Branch Switcher -->
        @role('owner')
        <div class="relative">
            <button @click="mobileBranchesOpen = !mobileBranchesOpen"
                class="flex items-center space-x-2 text-gray-700 dark:text-gray-200">
                <span class="text-sm font-medium truncate max-w-[120px]" x-text="selectedBranch"></span>
                <i class="text-xs fa-solid fa-chevron-down" :class="mobileBranchesOpen ? 'rotate-180' : ''"></i>
            </button>

            <div x-show="mobileBranchesOpen" @click.outside="mobileBranchesOpen = false"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="transform opacity-0 scale-95"
                x-transition:enter-end="transform opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="transform opacity-100 scale-100"
                x-transition:leave-end="transform opacity-0 scale-95"
                class="absolute right-0 z-50 w-56 mt-2 origin-top-right bg-white rounded-md shadow-lg dark:bg-gray-800 ring-1 ring-black ring-opacity-5">
                <div class="py-1" role="menu">
                    <div class="px-4 py-1 text-xs font-medium text-gray-500 dark:text-gray-400">SWITCH BRANCH</div>

                    @foreach ($branches as $branch)
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
                                    <p class="text-xs text-gray-500 truncate dark:text-gray-400">{{ Str::limit($branch->location, 20) }}</p>
                                @endif
                            </div>

                            @if(($spa?->business_tier ?? null) === 'professional')
                                <span class="ml-2 text-[10px] px-2 py-0.5 rounded-full {{ $branch->has_workforce_finance_suite ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                                    {{ $branch->has_workforce_finance_suite ? 'Suite' : 'Basic' }}
                                </span>
                            @endif

                            <span x-show="selectedBranchId == {{ $branch->id }}" class="ml-2 text-blue-600 dark:text-blue-400">
                                <i class="fa-solid fa-check"></i>
                            </span>
                        </button>
                    @endforeach

                    @if($canBranches)
                        <div class="px-4 py-2 text-xs text-gray-500 border-t dark:text-gray-400 dark:border-gray-700">
                            <a href="{{ route('branches.index') }}" class="flex items-center text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                <i class="w-4 mr-1 fa-solid fa-cog"></i>
                                Manage Branches
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @endrole
    </div>

    <!-- SIDEBAR -->
    <aside
        class="fixed inset-y-0 left-0 z-40 w-64 transition-transform duration-200 transform bg-white border-r dark:bg-gray-800 dark:border-gray-700 md:translate-x-0"
        :class="open ? 'translate-x-0' : '-translate-x-full'">
        <div class="flex flex-col h-full">

            <!-- Brand with Branch Switcher -->
            <div class="flex-shrink-0 border-b dark:border-gray-700">
                <div class="px-6 py-4">
                    <a href="{{ $brandHref }}" class="flex items-center space-x-3">
                        <img src="{{ asset('images/1.png') }}" class="h-10 rounded-md" alt="Levictas">
                        <div>
                            <span class="text-2xl font-semibold text-[#8B7355] dark:text-white font-['Playfair_Display']">
                                {{ $spa?->name ?? 'Spa Management' }}
                            </span>
                            <p class="text-xs tracking-widest text-gray-500 dark:text-gray-400">SPA | WELLNESS</p>
                        </div>
                    </a>
                </div>

                <!-- BRANCH SWITCHER -->
                @if ($branches->isNotEmpty())
                    <div class="px-6 pb-4">
                        <div class="relative">
                            <button @click="branchesDropdown = !branchesDropdown"
                                class="flex items-center justify-between w-full px-4 py-3 text-sm text-left transition-colors rounded-lg bg-gray-50 hover:bg-gray-100 dark:bg-gray-700/50 dark:hover:bg-gray-700 dark:text-gray-200">
                                <div class="flex items-center flex-1 min-w-0">
                                    <i class="flex-shrink-0 mr-3 text-gray-500 fa-solid fa-location-dot dark:text-gray-400"></i>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium truncate" x-text="selectedBranch"></p>
                                        <p class="text-xs text-gray-500 truncate dark:text-gray-400">
                                            {{ $branches->count() }} {{ Str::plural('branch', $branches->count()) }} available
                                        </p>
                                    </div>
                                </div>
                                <i class="flex-shrink-0 ml-2 text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                                   :class="branchesDropdown ? 'transform rotate-180' : ''"></i>
                            </button>

                            <div x-show="branchesDropdown" @click.outside="branchesDropdown = false"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="absolute left-0 right-0 z-50 mx-6 mt-1 overflow-y-auto origin-top bg-white rounded-lg shadow-lg dark:bg-gray-800 ring-1 ring-black ring-opacity-5 max-h-96">
                                <div class="py-2">
                                    <div class="flex items-center justify-between px-4 py-2 border-b dark:border-gray-700">
                                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">SELECT BRANCH</span>
                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $branches->count() }} total</span>
                                    </div>

                                    <div class="py-1">
                                        @foreach ($branches as $branch)
                                            <button
                                                @click="
                                                    selectedBranch = '{{ addslashes($branch->name) }}';
                                                    selectedBranchId = {{ $branch->id }};
                                                    branchesDropdown = false;
                                                    switchBranch({{ $branch->id }});
                                                "
                                                class="flex items-center w-full px-4 py-3 text-sm text-left hover:bg-gray-50 dark:hover:bg-gray-700 group"
                                                :class="selectedBranchId == {{ $branch->id }} ? 'bg-blue-50 dark:bg-blue-900/20' : ''">
                                                <div class="flex items-center flex-1 min-w-0">
                                                    <div class="flex-shrink-0 mr-3">
                                                        @if ($branch->is_main)
                                                            <div class="relative">
                                                                <i class="text-yellow-500 fa-solid fa-store" title="Main Branch"></i>
                                                                <i class="absolute text-xs -top-1 -right-1 fa-solid fa-crown"></i>
                                                            </div>
                                                        @else
                                                            <i class="text-gray-400 fa-solid fa-store"></i>
                                                        @endif
                                                    </div>

                                                    <div class="flex-1 min-w-0">
                                                        <p class="font-medium text-gray-900 truncate dark:text-white"
                                                            :class="selectedBranchId == {{ $branch->id }} ? 'text-blue-600 dark:text-blue-400' : ''">
                                                            {{ $branch->name }}
                                                        </p>

                                                        @if ($branch->location)
                                                            <p class="text-xs text-gray-500 truncate dark:text-gray-400">{{ Str::limit($branch->location, 25) }}</p>
                                                        @endif

                                                        @if ($branch->phone)
                                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $branch->phone }}</p>
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-2">
                                                    @if(($spa?->business_tier ?? null) === 'professional')
                                                        <span class="text-[10px] px-2 py-0.5 rounded-full {{ $branch->has_workforce_finance_suite ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                                                            {{ $branch->has_workforce_finance_suite ? 'Suite' : 'Basic' }}
                                                        </span>
                                                    @endif

                                                    <span x-show="selectedBranchId == {{ $branch->id }}" class="text-blue-600 dark:text-blue-400">
                                                        <i class="fa-solid fa-check"></i>
                                                    </span>
                                                </div>
                                            </button>
                                        @endforeach
                                    </div>

                                    @if($canBranches)
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
            <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">

                <!-- Dashboard -->
                @if($canDashboard)
                    <div class="mb-1 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                            <i class="fa-solid fa-gauge-high w-4 mr-1 text-[#8B7355]"></i>
                            Dashboard
                        </x-nav-link>
                    </div>
                @endif

                <!-- Operations -->
                @if($showOperations)
                    <div class="mb-1">
                        <button @click="operationsOpen = !operationsOpen"
                            class="flex items-center justify-between w-full px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-calendar-check w-4 text-[#8B7355]"></i>
                                Operations
                            </span>
                            <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                               :class="operationsOpen ? 'transform rotate-180' : ''"></i>
                        </button>

                        <div x-show="operationsOpen" x-collapse class="ml-4 space-y-1">
                            @if($canBooking)
                                <x-nav-link :href="route('booking')" :active="request()->routeIs('booking')">
                                    Book an Appointment
                                </x-nav-link>
                            @endif

                            @if($canAppointments)
                                <x-nav-link :href="route('appointments.index')" :active="request()->routeIs('appointments.*')">
                                    Appointments
                                </x-nav-link>
                            @endif

                            @if($canSchedule)
                                <x-nav-link :href="route('schedule.index')" :active="request()->routeIs('schedule.*')">
                                    Schedule
                                </x-nav-link>
                            @endif

                            @if(!$suiteEnabled && $canAttendanceLeave)
                                <x-nav-link :href="route('attendance.index')" :active="request()->routeIs('attendance.*')">
                                    Attendance &amp; Leave
                                </x-nav-link>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- People -->
                @if($showPeople)
                    <div class="mb-1">
                        <button @click="peopleOpen = !peopleOpen"
                            class="flex items-center justify-between w-full px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-users w-4 text-[#8B7355]"></i>
                                Manpower
                            </span>
                            <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                               :class="peopleOpen ? 'transform rotate-180' : ''"></i>
                        </button>

                        <div x-show="peopleOpen" x-collapse class="ml-4 space-y-1">
                            @if($canHiring)
                                <x-nav-link :href="route('hiring.index')" :active="request()->routeIs('hiring.*')">
                                    Hiring
                                </x-nav-link>
                            @endif

                            @if($canApplicants)
                                <x-nav-link :href="route('applications.index')" :active="request()->routeIs('applications.*')">
                                    Applicants
                                </x-nav-link>
                            @endif

                            @if($canInterviews)
                                <x-nav-link :href="route('interviews.index')" :active="request()->routeIs('interviews.*')">
                                    Interviews
                                </x-nav-link>
                            @endif

                            @if($canStaffAccounts)
                                <x-nav-link :href="route('staff.index')" :active="request()->routeIs('staff.*')">
                                    Staff Accounts
                                </x-nav-link>
                            @endif

                            @if($canAttendanceLeave)
                                <x-nav-link :href="route('attendance.index')" :active="request()->routeIs('attendance.index*')">
                                    Attendance &amp; Leave
                                </x-nav-link>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Management -->
                @if($showManagement)
                    <div class="mb-1">
                        <button @click="managementOpen = !managementOpen"
                            class="flex items-center justify-between w-full px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-briefcase w-4 text-[#8B7355]"></i>
                                Management
                            </span>
                            <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                               :class="managementOpen ? 'transform rotate-180' : ''"></i>
                        </button>

                        <div x-show="managementOpen" x-collapse class="ml-4 space-y-1">
                            @if($canServices)
                                <x-nav-link :href="route('services.index')" :active="request()->routeIs('services.*')">
                                    Services
                                </x-nav-link>
                            @endif

                            @if($canManagementStaff)
                                <x-nav-link :href="route('staff.index')" :active="request()->routeIs('staff.*')">
                                    Staff
                                </x-nav-link>
                            @endif

                            @if($canBranches)
                                <x-nav-link :href="route('branches.index')" :active="request()->routeIs('branches.*')">
                                    Branches
                                </x-nav-link>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Finance -->
                @if($showFinance)
                    <div class="mb-1">
                        <button @click="financeOpen = !financeOpen"
                            class="flex items-center justify-between w-full px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-wallet w-4 text-[#8B7355]"></i>
                                Finance
                            </span>
                            <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                               :class="financeOpen ? 'transform rotate-180' : ''"></i>
                        </button>

                        <div x-show="financeOpen" x-collapse class="ml-4 space-y-1">
                            @if($canPayroll)
                                <x-nav-link :href="route('payroll.index')" :active="request()->routeIs('payroll.*')">
                                    Payroll
                                </x-nav-link>
                            @endif

                            @if($canRevenue)
                                <x-nav-link :href="route('revenue.index')" :active="request()->routeIs('revenue.*')">
                                    Revenue
                                </x-nav-link>
                            @endif

                            @if($canBilling)
                                <x-nav-link :href="route('billing.index')" :active="request()->routeIs('billing.*')">
                                    Billing &amp; Expenses
                                </x-nav-link>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Insights -->
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
                            @if($canDecisionSupport)
                                <x-nav-link :href="route('decision-support.index')" :active="request()->routeIs('decision-support.*')">
                                    Decision Support
                                </x-nav-link>
                            @endif

                            @if($canReports)
                                <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                                    Reports
                                </x-nav-link>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Inventory -->
                @if($showInventory)
                    <div class="mb-1">
                        <button @click="inventoryOpen = !inventoryOpen"
                            class="flex items-center justify-between w-full px-4 py-3 font-medium text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-boxes-stacked w-4 text-[#8B7355]"></i>
                                Inventory
                            </span>
                            <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                               :class="inventoryOpen ? 'transform rotate-180' : ''"></i>
                        </button>

                        <div x-show="inventoryOpen" x-collapse class="ml-4 space-y-1">
                            @if($canProductInventory)
                                <x-nav-link :href="route('inventory.products')" :active="request()->routeIs('inventory.products')">
                                    Product Inventory
                                </x-nav-link>
                            @endif

                            @if($canProductLogs)
                                <x-nav-link :href="route('inventory.logs')" :active="request()->routeIs('inventory.logs')">
                                    Product Logs
                                </x-nav-link>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Settings -->
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
                            User Profile
                        </x-nav-link>

                        @role('owner')
                        <x-nav-link :href="route('owner.spa-profile.edit')" :active="request()->routeIs('owner.spa-profile.*')">
                            Spa Profile
                        </x-nav-link>
                        @endrole

                        @if($canWorkforceFinanceSuiteSettings)
                            <x-nav-link :href="route('owner.workforce-finance-suite.index')" :active="request()->routeIs('owner.workforce-finance-suite.*')">
                                Workforce &amp; Finance Suite
                            </x-nav-link>
                        @endif

                        @role('owner')
                        <x-nav-link :href="route('owner.subscription.index')" :active="request()->routeIs('owner.subscription.*')">
                            Subscription &amp; Billing
                        </x-nav-link>
                        @endrole

                        @role('owner')
                        <x-nav-link :href="route('owner.roles-permissions.index')" :active="request()->routeIs('owner.roles-permissions.*')">
                            Roles &amp; Permissions
                        </x-nav-link>
                        @endrole
                    </div>
                </div>

            </nav>

            <!-- USER INFO -->
            <div class="flex-shrink-0 p-3 border-t dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        @auth
                            <p class="text-sm font-medium text-gray-800 dark:text-white">{{ Auth::user()->name }}</p>
                            <p class="text-xs text-gray-500 truncate dark:text-gray-400">{{ Auth::user()->email }}</p>
                        @else
                            <p class="text-sm font-medium text-gray-800 dark:text-white">Guest User</p>
                            <p class="text-xs text-gray-500 truncate dark:text-gray-400">Not logged in</p>
                        @endauth
                    </div>
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
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Confirm Logout</h3>
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

    <!-- MAIN CONTENT -->
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
            credentials: 'same-origin',
            body: JSON.stringify({ branch_id: branchId })
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
                button.innerHTML = originalContent;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showSpaToast('An error occurred. Please try again.', 'error');
            button.innerHTML = originalContent;
            button.disabled = false;
        });
    }

    function sidebar() {
        return {
            open: false,
            showLogoutModal: false,
            operationsOpen: false,
            peopleOpen: false,
            managementOpen: false,
            financeOpen: false,
            insightsOpen: false,
            branchesDropdown: false,
            mobileBranchesOpen: false,
            inventoryOpen: false,
            settingsOpen: false,
            selectedBranch: @json($currentBranch?->name ?? ($firstBranch?->name ?? 'Select Branch')),
            selectedBranchId: @json($currentBranch?->id ?? ($firstBranch?->id ?? null)),
        };
    }

    document.addEventListener('alpine:init', () => {
        window.switchBranch = switchBranch;
    });
</script>

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

    .relative .fa-crown {
        font-size: 0.5rem;
        filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.3));
    }
</style>
