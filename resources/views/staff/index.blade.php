@extends('layouts.app')

@section('title', 'Staff Management')
@section('content')
@php
    $user = auth()->user();
    $spa = $user?->spa;

    $hasManpower = $hasManpower
        ?? ($spa?->hasFeature('manpower') ?? false);

    $staffLimit = $staffLimit
        ?? ($spa?->planLimit('staff') ?? 0);

    $canCreateStaff = $user?->hasBranchPermission('create staff') ?? false;
    $canEditStaff = $user?->hasBranchPermission('edit staff') ?? false;
    $canDeleteStaff = $user?->hasBranchPermission('delete staff') ?? false;
    // Work schedule (staff scheduling Phase 2): everyone on this page (view staff)
    // can open it read-only, so the Actions column always shows.
    $canEditSchedules = $user?->hasBranchPermission('edit staff schedules') ?? false;
    $showActions = true;

    $staffCount = $activeStaffCount
    ?? $staff->where('employment_status', 'active')->count();

    $pausedStaffCount = $pausedStaffCount ?? 0;
    $isOwner = (bool) ($user?->hasRole('owner') ?? false);

    $hasReachedStaffLimit = $staffLimit <= 0 || $staffCount >= $staffLimit;
    $remainingStaffSlots = max($staffLimit - $staffCount, 0);

    $roleCounts = [
        'manager' => 0,
        'therapist' => 0,
        'receptionist' => 0,
        'hr' => 0,
        'finance' => 0,
        'hr_head' => 0,
        'finance_head' => 0,
    ];

    // One pass over the collection resolves the role for every member and keeps
    // it, so the table rows below don't call getRoleNames() a second time.
    $memberRoles = [];
    foreach (($allStaff ?? $staff) as $member) {
        $role = $member->user?->getRoleNames()->first();
        $memberRoles[$member->id] = $role;
        if ($role && array_key_exists($role, $roleCounts)) {
            $roleCounts[$role]++;
        }
    }
    $professionalRolesCount = $roleCounts['hr'] + $roleCounts['finance'] + $roleCounts['hr_head'] + $roleCounts['finance_head'];
    $headRoles = \App\Services\Payroll\PayrollPermissions::HEAD_ROLES;

    // Role badge colours. Hoisted out of the row loop so the array is built once
    // instead of once per staff member. Families follow the canon status palette
    // (§6): emerald not green, amber not yellow, violet not purple.
    $roleColors = [
        'manager'      => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'therapist'    => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'receptionist' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'hr'           => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
        'finance'      => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
        'hr_head'      => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
        'finance_head' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
    ];
    $roleColorFallback = 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';

    // Button tokens, same shape as appointments.blade.php's $btn map so the two
    // pages produce byte-identical controls.
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'edit'    => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'neutral' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
        'remove'  => $btnBase . ' bg-red-700 text-white hover:bg-red-800',
        'upgrade' => $btnBase . ' text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:opacity-90',
    ];

    // Work-schedule day status → chip classes (canon palette §6), single source for the
    // JS-built schedule modal (§5). Keys match App\Services\Staff\ScheduledDay.
    $scheduleStatusClasses = [
        'working'  => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'rest_day' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
        'day_off'  => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'on_leave' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'closed'   => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    ];

    // Shared by the create form and the JS-built edit form.
    $inputClass = 'block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 '
                . 'dark:placeholder-gray-400 dark:text-white';
@endphp
<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">
    <x-page-header
        title="Staff Management"
        subtitle="Add, edit, and manage your spa staff members."
    />
    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Total Staff</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">{{ $staffCount }}</h3>
                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">Active members</span>
            </div>
        </div>
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Therapists</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">{{ $roleCounts['therapist'] }}</h3>
                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">Service staff</span>
            </div>
        </div>
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Managers</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">{{ $roleCounts['manager'] }}</h3>
                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">Leadership roles</span>
            </div>
        </div>
        {{-- The Premium plan's manpower feature controls HR and Finance role availability. --}}
        <div class="p-4 border shadow-sm sm:p-5 rounded-2xl
            {{ $hasManpower
                ? 'bg-indigo-50 border-indigo-200 dark:bg-indigo-900/10 dark:border-indigo-800'
                : 'bg-amber-50 border-amber-200 dark:bg-amber-900/10 dark:border-amber-800' }}">
            <p class="text-xs font-semibold tracking-wide uppercase
                {{ $hasManpower
                    ? 'text-indigo-700 dark:text-indigo-300'
                    : 'text-amber-700 dark:text-amber-300' }}">
                {{ $hasManpower ? 'Premium Roles' : 'Premium Feature' }}
            </p>

            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold sm:text-3xl
                    {{ $hasManpower
                        ? 'text-indigo-900 dark:text-indigo-200'
                        : 'text-amber-900 dark:text-amber-200' }}">
                    {{ $hasManpower ? $professionalRolesCount : 'Unavailable' }}
                </h3>

                <span class="text-xs sm:text-sm
                    {{ $hasManpower
                        ? 'text-indigo-700 dark:text-indigo-300'
                        : 'text-amber-700 dark:text-amber-300' }}">
                    {{ $hasManpower ? 'HR & Finance roles' : 'Premium plan required' }}
                </span>
            </div>
        </div>
    </div>
    {{-- Staff paused by the plan limit --}}
    @if($pausedStaffCount > 0)
        <div class="p-4 border border-amber-200 rounded-2xl bg-amber-50 dark:bg-amber-900/10 dark:border-amber-800" role="status">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-sm font-semibold tracking-wide uppercase text-amber-800 dark:text-amber-300">
                        {{ $pausedStaffCount }} Staff Paused
                    </h2>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                        Your spa has more staff than the {{ ucfirst($spa?->currentPlan() ?? 'current') }} plan allows.
                        Paused staff cannot sign in and are not offered for bookings. Nothing was deleted.
                        @unless($isOwner)
                            Ask the spa owner to choose who stays active.
                        @endunless
                    </p>
                </div>

                @if($isOwner)
                    <a href="{{ route('owner.subscription.limits') }}" class="w-full {{ $btn['upgrade'] }} sm:w-auto">
                        <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                        Choose Who Stays Active
                    </a>
                @endif
            </div>
        </div>
    @endif
    {{-- Add New Staff --}}
    @if($canCreateStaff)
        @if($hasReachedStaffLimit)
            <div class="overflow-hidden bg-white border border-red-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-red-800">
                <div class="p-4 sm:p-5">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-red-700 dark:text-red-300">
                                Staff Limit Reached
                            </h2>

                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Your {{ ucfirst($spa?->currentPlan() ?? 'current') }} plan allows
                                {{ $staffLimit }} active staff members across all branches.
                                @if($isOwner)
                                    Upgrade your plan to add more staff.
                                @else
                                    Ask the spa owner to upgrade the plan to add more staff.
                                @endif
                            </p>
                        </div>

                        @if($isOwner)
                            <a href="{{ route('owner.subscription.index') }}"
                            class="w-full {{ $btn['upgrade'] }} sm:w-auto">
                                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                View Plans
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @else
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Add New Staff Member</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Create a new staff account and assign the appropriate role for this branch.
                        </p>
                    </div>
                    <div class="flex flex-col items-start gap-2 sm:items-end">
                        @if(!$hasManpower)
                            <div class="px-3 py-2 text-xs rounded-xl bg-amber-50 text-amber-700 sm:text-right dark:bg-amber-900/20 dark:text-amber-300">
                                <i class="mr-1 fa-solid fa-lock" aria-hidden="true"></i>
                                HR &amp; Finance roles require the Premium plan.
                            </div>
                        @endif
                        @if($staffLimit > 0 && !$hasReachedStaffLimit)
                            <div class="px-3 py-2 text-xs text-gray-700 bg-gray-100 rounded-xl sm:text-right dark:bg-gray-700 dark:text-gray-300">
                                {{ $remainingStaffSlots }} of {{ $staffLimit }} slot(s) remaining
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="p-4 sm:p-5">
                <form action="{{ route('staff.store') }}" method="POST" id="addStaffForm" class="space-y-5">
                    @csrf

                    {{-- Row 1: Name Fields --}}
                    <div>
                        <p class="mb-2 text-sm font-semibold text-gray-700 dark:text-white">
                            Full Name <x-required-mark />
                        </p>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div>
                                <label for="first_name" class="block mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">First Name</label>
                                <input
                                    type="text"
                                    id="first_name"
                                    name="first_name"
                                    required
                                    class="{{ $inputClass }}"
                                    placeholder="e.g. Juan"
                                    value="{{ old('first_name') }}"
                                >
                                @error('first_name')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="middle_name" class="block mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                                    Middle Name <span class="font-normal text-gray-500 dark:text-gray-400">(optional)</span>
                                </label>
                                <input
                                    type="text"
                                    id="middle_name"
                                    name="middle_name"
                                    class="{{ $inputClass }}"
                                    placeholder="e.g. Santos"
                                    value="{{ old('middle_name') }}"
                                >
                                @error('middle_name')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="last_name" class="block mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">Last Name</label>
                                <input
                                    type="text"
                                    id="last_name"
                                    name="last_name"
                                    required
                                    class="{{ $inputClass }}"
                                    placeholder="e.g. Dela Cruz"
                                    value="{{ old('last_name') }}"
                                >
                                @error('last_name')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Row 2: Email and Role --}}
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                                Email Address <x-required-mark />
                            </label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                required
                                class="{{ $inputClass }}"
                                placeholder="staff@example.com"
                                value="{{ old('email') }}"
                            >
                            @error('email')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="roles" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                                Staff Assignment <x-required-mark />
                            </label>
                            <select
                                id="roles"
                                name="roles"
                                required
                                class="{{ $inputClass }}"
                            >
                                <option value="">Assign Staff Role</option>
                                <optgroup label="Spa Staff">
                                    <option value="therapist" {{ old('roles') == 'therapist' ? 'selected' : '' }}>Therapist</option>
                                    <option value="receptionist" {{ old('roles') == 'receptionist' ? 'selected' : '' }}>Receptionist</option>
                                    <option value="manager" {{ old('roles') == 'manager' ? 'selected' : '' }}>Manager</option>
                                </optgroup>
                                {{-- HR and Finance roles are available only with the Premium plan. --}}
                                @if($hasManpower)
                                    <optgroup label="Premium Roles">
                                        <option value="hr" {{ old('roles') === 'hr' ? 'selected' : '' }}>
                                            HR
                                        </option>
                                        <option value="finance" {{ old('roles') === 'finance' ? 'selected' : '' }}>
                                            Finance
                                        </option>
                                    </optgroup>
                                    {{-- Central roles: one of each per spa, assigned by the owner only. --}}
                                    @if($isOwner)
                                        <optgroup label="All-Branch Roles">
                                            <option value="hr_head" {{ old('roles') === 'hr_head' ? 'selected' : '' }}>HR Head</option>
                                            <option value="finance_head" {{ old('roles') === 'finance_head' ? 'selected' : '' }}>Finance Head</option>
                                        </optgroup>
                                    @endif
                                @endif
                            </select>
                            @error('roles')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                            @if(!$hasManpower)
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    <i class="fa-solid fa-lock text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                                    HR &amp; Finance roles require the Premium plan.
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- Q37: for small spas where e.g. the receptionist also gives treatments. --}}
                    <div id="addPerformsWrapper" class="{{ old('roles', 'therapist') === 'therapist' ? 'hidden' : '' }}">
                        <label class="flex items-start gap-3 p-3 border border-gray-200 cursor-pointer rounded-xl dark:border-gray-700">
                            <input type="hidden" name="performs_treatments" value="0">
                            <input type="checkbox" name="performs_treatments" value="1" @checked(old('performs_treatments'))
                                   class="mt-0.5 w-5 h-5 border-gray-300 rounded text-[#8B7355] focus:ring-[#8B7355] dark:border-gray-600 dark:bg-gray-700">
                            <span class="text-sm">
                                <span class="font-medium text-gray-900 dark:text-white">Also performs treatments</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">Turn on if this person also gives treatments. They can then be assigned appointments, appear in the therapist workload, and get My Today, My Performance and "Can't make it". Their role's permissions don't change. For online home-service bookings they also verify their identity on their profile.</span>
                            </span>
                        </label>
                    </div>

                    <div class="flex pt-1 sm:justify-end">
                        <button type="submit" id="addStaffSubmit" class="w-full {{ $btn['primary'] }} sm:w-auto">
                            <i id="addStaffIcon" class="fa-solid fa-user-plus" aria-hidden="true"></i>
                            <span id="addStaffLabel">Add Staff Member</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    @endif
    {{-- Staff Directory --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-col gap-4 px-4 py-4 border-b border-gray-200 lg:flex-row lg:items-start lg:justify-between sm:px-6 dark:border-gray-700">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Staff Directory</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    View assigned roles and manage staff accounts for this branch.
                </p>
            </div>
            {{-- Filters (table filters workstream; same bar as Pay Runs) --}}
            <x-table-filters :action="route('staff.index')" :shown="$staff->count()" :total="($allStaff ?? $staff)->count()"
                             :active="$activeFilters ?? 0" noun="staff member"
                             :search="$filters['q'] ?? ''" search-placeholder="Name or email">
                <x-table-filters.select name="role" label="Role" all="All roles" :options="$roleOptions ?? []" :value="$filters['role'] ?? null" width="sm:w-40" />
                <x-table-filters.select name="status" label="Status" all="All statuses"
                    :options="['active' => 'Active', 'paused' => 'Paused (plan limit)']" :value="$filters['status'] ?? null" width="sm:w-44" />
            </x-table-filters>
        </div>
        <div class="md:overflow-x-auto">
            {{-- .rt collapses this table into stacked cards below md. The explicit
                 role="" attributes are required because changing display away from
                 table strips the native table semantics. --}}
            <table role="table" class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Staff Member</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Role</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Assigned Branch</th>
                        @if($showActions)
                            <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody role="rowgroup" class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse($staff as $member)
                    @php
                        $role = $memberRoles[$member->id] ?? null;
                        $colorClass = $roleColors[$role] ?? $roleColorFallback;

                        // Every read of ->user is null-safe: a soft-deleted or orphaned
                        // account would otherwise render a blank name and hand an empty
                        // string to the delete confirmation.
                        $displayName = trim(
                            ($member->user?->first_name ?? '') . ' '
                            . ($member->user?->middle_name ? $member->user->middle_name . ' ' : '')
                            . ($member->user?->last_name ?? '')
                        );
                        $displayName = $displayName !== '' ? $displayName : 'Unnamed staff account';
                        $shortName = trim(($member->user?->first_name ?? '') . ' ' . ($member->user?->last_name ?? ''));
                        $shortName = $shortName !== '' ? $shortName : 'this staff account';
                        $initial = strtoupper(substr($member->user?->first_name ?? 'S', 0, 1));
                    @endphp
                    <tr role="row" class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                        <td role="cell" data-label="Staff Member" class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-10 h-10 text-sm font-semibold text-white rounded-full bg-[#8B7355] shrink-0">
                                    {{ $initial }}
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ $displayName }}
                                        @if($member->plan_paused_at)
                                            <span class="ml-1 px-2 py-0.5 text-[11px] font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                                Paused (plan limit)
                                            </span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $member->user?->email ?? 'No email' }}
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td role="cell" data-label="Role" class="px-6 py-4">
                            @if($role)
                                <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-medium rounded-full {{ $colorClass }}">
                                    {{ \App\Services\Payroll\PayrollPermissions::roleLabel($role) }}
                                    @if(in_array($role, ['hr', 'finance', 'hr_head', 'finance_head']))
                                        <i class="fa-solid fa-star text-[10px]" aria-hidden="true"></i>
                                    @endif
                                </span>
                            @else
                                <span class="px-3 py-1 text-xs font-medium rounded-full {{ $roleColorFallback }}">
                                    No role
                                </span>
                            @endif
                            {{-- Treatment providers: verified status for online home service (Q36).
                                 Only Verified / Not verified; reasons stay between the staff member and the admin. --}}
                            @if($role === 'therapist' || $member->performs_treatments)
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @if($role !== 'therapist')
                                        <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">Does treatments</span>
                                    @endif
                                    @if($member->user?->isIdentityVerified())
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300"
                                              title="Can receive online home-service bookings">
                                            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Verified
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                                              title="Won't be assigned online home-service bookings until verified">
                                            Not verified
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td role="cell" data-label="Assigned Branch" class="px-6 py-4">
                            @if($member->branch)
                                <div class="text-sm text-gray-900 dark:text-white">
                                    {{ $member->branch->name }}
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $member->branch->location }}
                                    </p>
                                </div>
                            @else
                                <span class="text-sm text-gray-500 dark:text-gray-400">No branch assigned</span>
                            @endif
                        </td>
                        @if($showActions)
                        <td role="cell" data-label="Actions" class="px-6 py-4 rt-actions">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    onclick='openWorkScheduleModal({{ $member->id }}, @json($shortName))'
                                    class="{{ $btn['edit'] }}"
                                    aria-label="Work schedule for {{ $shortName }}"
                                >
                                    <i class="text-xs fa-solid fa-calendar-days" aria-hidden="true"></i>
                                    <span>Schedule</span>
                                </button>
                                @if($canEditStaff && ($isOwner || ! in_array($role, $headRoles, true)))
                                <button
                                    type="button"
                                    onclick='editStaff({{ $member->id }}, {{ $hasManpower ? 'true' : 'false' }}, @json($role), @json($displayName), {{ $member->performs_treatments ? 'true' : 'false' }})'
                                    class="{{ $btn['edit'] }}"
                                >
                                    <i class="text-xs fa-solid fa-pen" aria-hidden="true"></i>
                                    <span>Edit</span>
                                </button>
                                @endif
                                @if($canDeleteStaff && ($isOwner || ! in_array($role, $headRoles, true)))
                                <button
                                    type="button"
                                    onclick='openDeleteModal({{ $member->id }}, @json($shortName))'
                                    class="{{ $btn['remove'] }}"
                                >
                                    <i class="text-xs fa-solid fa-trash" aria-hidden="true"></i>
                                    <span>Remove</span>
                                </button>
                                @endif
                            </div>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr role="row">
                        <td role="cell" colspan="{{ $showActions ? 4 : 3 }}" class="px-6 py-12 text-center text-gray-500 rt-empty dark:text-gray-400">
                            <div class="flex flex-col items-center justify-center">
                                <i class="mb-3 text-4xl text-gray-400 fa-solid fa-users" aria-hidden="true"></i>
                                <p class="mb-2 text-gray-600 dark:text-gray-400">No staff members found</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    @if (($activeFilters ?? 0) > 0)
                                        No staff member matches these filters.
                                    @else
                                        {{ $canCreateStaff ? 'Add your first staff member using the form above.' : 'No staff members are available for this branch yet.' }}
                                    @endif
                                </p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
{{-- Edit Modal --}}
@if($canEditStaff)
<div id="editModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="editModalTitle"
             class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <form id="editStaffForm" method="POST">
                @csrf
                @method('PUT')
                <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div>
                        <h2 id="editModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Edit Staff Member</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Updating the role for <span id="editStaffName" class="font-medium text-gray-900 dark:text-white"></span>.
                        </p>
                    </div>
                    <button type="button" onclick="closeEditModal()" aria-label="Close dialog"
                            class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="px-4 py-6 sm:px-6">
                    <div class="space-y-4" id="editFormContent"></div>
                </div>
                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeEditModal()" class="w-full {{ $btn['neutral'] }} sm:w-auto">
                            Cancel
                        </button>
                        <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto">
                            Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
{{-- Delete Modal --}}
@if($canDeleteStaff)
<div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="alertdialog" aria-modal="true" aria-labelledby="deleteModalTitle" aria-describedby="deleteModalDesc"
             class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <h2 id="deleteModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Remove Staff Member</h2>
            <p id="deleteModalDesc" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                This permanently removes
                <span id="deleteStaffName" class="font-medium text-gray-900 dark:text-white"></span>
                from this branch. This action cannot be undone.
            </p>
            <div class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">
                <button type="button" onclick="closeDeleteModal()" class="w-full {{ $btn['neutral'] }} sm:w-auto">
                    Keep Staff Member
                </button>
                <form id="deleteStaffForm" method="POST" class="sm:w-auto">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full {{ $btn['remove'] }} sm:w-auto">
                        Yes, Remove
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif
{{-- Work Schedule Modal (staff scheduling Phase 2). Content is built by JS from
     GET staff.schedule.show; read-only unless the response says can_edit. --}}
<div id="workScheduleModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="workScheduleModalTitle"
             class="w-full max-w-3xl bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div>
                    <h2 id="workScheduleModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Work Schedule</h2>
                    <p id="workScheduleModalSubtitle" class="mt-1 text-sm text-gray-500 dark:text-gray-400"></p>
                </div>
                <button type="button" onclick="closeWorkScheduleModal()" aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="px-4 py-6 space-y-6 sm:px-6">
                <div id="scheduleFlash" aria-live="polite" class="hidden space-y-2"></div>
                <div id="scheduleBody" class="space-y-6"></div>
            </div>
            <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" onclick="closeWorkScheduleModal()" class="w-full {{ $btn['neutral'] }} sm:w-auto">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
// Everything lives in one IIFE. Functions that inline onclick= attributes call
// are attached to window explicitly at the bottom of each block, so nothing
// leaks into global scope where an @included partial could collide with it.
(function () {
    'use strict';

    // VERIFY: named routes assumed from the existing route('staff.store') call,
    // i.e. a resource route. Confirm with `php artisan route:list --name=staff`.
    const ROUTE_UPDATE  = @json(route('staff.update', '__ID__'));
    const ROUTE_DESTROY = @json(route('staff.destroy', '__ID__'));

    const INPUT_CLASS = @json($inputClass);

    function routeFor(template, id) {
        return template.replace('__ID__', encodeURIComponent(id));
    }

    function esc(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // ════════════════════════════════════════════════════════════════
    // SHARED MODAL BEHAVIOUR (open/close, Escape, focus, backdrop)
    // ════════════════════════════════════════════════════════════════
    const MODAL_IDS = ['editModal', 'workScheduleModal', 'deleteModal'];
    const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    let lastFocused = null;   // element to restore focus to on close

    function topmostOpenModal() {
        for (let i = MODAL_IDS.length - 1; i >= 0; i--) {
            const el = document.getElementById(MODAL_IDS[i]);
            if (el && !el.classList.contains('hidden')) return el;
        }
        return null;
    }

    function openModal(id, focusSelector) {
        const el = document.getElementById(id);
        if (!el) return;
        lastFocused = document.activeElement;
        el.classList.remove('hidden');
        const target = (focusSelector && el.querySelector(focusSelector))
            || el.querySelector(FOCUSABLE);
        if (target) target.focus();
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('hidden');
        if (lastFocused && document.contains(lastFocused)) lastFocused.focus();
        lastFocused = null;
    }

    // Escape closes the topmost modal; Tab is kept inside it.
    document.addEventListener('keydown', function (e) {
        const modal = topmostOpenModal();
        if (!modal) return;

        if (e.key === 'Escape') {
            e.preventDefault();
            closeModal(modal.id);
            return;
        }
        if (e.key !== 'Tab') return;

        const items = Array.from(modal.querySelectorAll(FOCUSABLE))
            .filter(el => el.offsetParent !== null);
        if (!items.length) return;
        const first = items[0];
        const last  = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault(); last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault(); first.focus();
        }
    });

    // Backdrop click closes the delete confirmation only. The edit modal holds a
    // chosen value, and a stray tap on a phone would discard it with no warning.
    ['deleteModal'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('click', e => { if (e.target === el) closeModal(id); });
    });

@if($canDeleteStaff)
    // ════════════════════════════════════════════════════════════════
    // DELETE
    // ════════════════════════════════════════════════════════════════
    function openDeleteModal(id, name) {
        document.getElementById('deleteStaffName').textContent = name || 'this staff account';
        document.getElementById('deleteStaffForm').action = routeFor(ROUTE_DESTROY, id);
        openModal('deleteModal', 'button[type="button"]');
    }
    function closeDeleteModal() { closeModal('deleteModal'); }

    window.openDeleteModal  = openDeleteModal;
    window.closeDeleteModal = closeDeleteModal;
@endif

@if($canEditStaff)
    // ════════════════════════════════════════════════════════════════
    // EDIT
    // ════════════════════════════════════════════════════════════════

    // HR and Finance options are available only when the spa has the
    // Premium plan's manpower feature.
    const IS_OWNER = @json($isOwner);

    function editStaff(staffId, hasManpower, currentRole, staffName, performsTreatments) {
        hasManpower = hasManpower === true;
        currentRole = currentRole || '';

        const sel = role => currentRole === role ? ' selected' : '';

        const businessRoleOptions = hasManpower ? `
            <optgroup label="Premium Roles">
                <option value="hr"${sel('hr')}>HR</option>
                <option value="finance"${sel('finance')}>Finance</option>
            </optgroup>
            ${IS_OWNER ? `
            <optgroup label="All-Branch Roles">
                <option value="hr_head"${sel('hr_head')}>HR Head</option>
                <option value="finance_head"${sel('finance_head')}>Finance Head</option>
            </optgroup>` : ''}
        ` : '';

        // If the member's current role isn't in the list this select can offer,
        // the placeholder stays selected — better than silently showing a role
        // the form can't actually submit.
        document.getElementById('editFormContent').innerHTML = `
            <div>
                <label for="editRoleSelect" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Role <x-required-mark /></label>
                <select id="editRoleSelect" name="roles" required class="${esc(INPUT_CLASS)}">
                    <option value=""${currentRole ? '' : ' selected'}>Select Role</option>
                    <optgroup label="Spa Staff">
                        <option value="therapist"${sel('therapist')}>Therapist</option>
                        <option value="receptionist"${sel('receptionist')}>Receptionist</option>
                        <option value="manager"${sel('manager')}>Manager</option>
                    </optgroup>
                    ${businessRoleOptions}
                </select>
                ${
                    !hasManpower
                        ? `<p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            <i class="fa-solid fa-lock text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                            HR &amp; Finance roles require the Premium plan.
                           </p>`
                        : ''
                }
            </div>
            <div id="editPerformsWrapper" class="${currentRole === 'therapist' ? 'hidden' : ''}">
                <label class="flex items-start gap-3 p-3 border border-gray-200 cursor-pointer rounded-xl dark:border-gray-700">
                    <input type="hidden" name="performs_treatments" value="0">
                    <input type="checkbox" name="performs_treatments" value="1"${performsTreatments === true ? ' checked' : ''}
                           class="mt-0.5 w-5 h-5 border-gray-300 rounded text-[#8B7355] focus:ring-[#8B7355] dark:border-gray-600 dark:bg-gray-700">
                    <span class="text-sm">
                        <span class="font-medium text-gray-900 dark:text-white">Also performs treatments</span>
                        <span class="block text-xs text-gray-500 dark:text-gray-400">Turn on if this person also gives treatments. They can then be assigned appointments, appear in the therapist workload, and get My Today, My Performance and "Can't make it". Their role's permissions don't change. Therapists always can.</span>
                    </span>
                </label>
            </div>
        `;

        // The checkbox only matters for roles other than Therapist.
        document.getElementById('editRoleSelect').addEventListener('change', function () {
            document.getElementById('editPerformsWrapper').classList.toggle('hidden', this.value === 'therapist');
        });

        document.getElementById('editStaffName').textContent = staffName || 'this staff member';
        document.getElementById('editStaffForm').action = routeFor(ROUTE_UPDATE, staffId);
        openModal('editModal', '#editRoleSelect');
    }
    function closeEditModal() { closeModal('editModal'); }

    window.editStaff      = editStaff;
    window.closeEditModal = closeEditModal;
@endif

@if($canCreateStaff)
    // ════════════════════════════════════════════════════════════════
    // ADD-STAFF DOUBLE-SUBMIT GUARD
    // A slow POST plus an impatient second click would create two accounts.
    // ════════════════════════════════════════════════════════════════
    const addForm = document.getElementById('addStaffForm');

    // "Also performs treatments" only matters for roles other than Therapist.
    document.getElementById('roles')?.addEventListener('change', function () {
        document.getElementById('addPerformsWrapper')?.classList.toggle('hidden', this.value === 'therapist');
    });

    if (addForm) {
        let submitting = false;
        addForm.addEventListener('submit', function (e) {
            if (submitting) { e.preventDefault(); return; }
            if (!addForm.checkValidity()) return;   // let the browser show its own errors
            submitting = true;

            const btn   = document.getElementById('addStaffSubmit');
            const icon  = document.getElementById('addStaffIcon');
            const label = document.getElementById('addStaffLabel');
            if (btn)   { btn.disabled = true; btn.classList.add('opacity-70', 'cursor-not-allowed'); }
            if (icon)  { icon.className = 'fa-solid fa-spinner fa-spin'; }
            if (label) { label.textContent = 'Adding…'; }
        });
    }
@endif

    // ════════════════════════════════════════════════════════════════
    // WORK SCHEDULE (staff scheduling Phase 2)
    // Read-only for anyone on this page; editable when the server says can_edit
    // (edit staff schedules; HR Head / Finance Head rows are owner-only).
    // ════════════════════════════════════════════════════════════════
    const SCHEDULE_ROUTES = {
        show:      @json(route('staff.schedule.show', '__ID__')),
        update:    @json(route('staff.schedule.update', '__ID__')),
        ovStore:   @json(route('staff.schedule.overrides.store', '__ID__')),
        ovDestroy: @json(route('staff.schedule.overrides.destroy', ['__ID__', '__OV__'])),
    };
    const SCHEDULE_STATUS = @json($scheduleStatusClasses);
    const BTN = @json($btn);
    const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    let scheduleStaffId = null;
    let scheduleData = null;
    let scheduleBusy = false;

    function fmtTime(hm) {
        if (!hm) return '';
        const [h, m] = hm.split(':').map(Number);
        const ap = h >= 12 ? 'PM' : 'AM';
        return `${((h + 11) % 12) + 1}:${String(m).padStart(2, '0')} ${ap}`;
    }

    function flash(kind, message, list) {
        const box = document.getElementById('scheduleFlash');
        if (!message && !(list && list.length)) { box.innerHTML = ''; box.classList.add('hidden'); return; }
        box.classList.remove('hidden');
        const tone = {
            success: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/10 dark:text-emerald-300',
            warning: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/10 dark:text-amber-300',
            error:   'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/10 dark:text-red-300',
        }[kind];
        const icon = { success: 'fa-circle-check', warning: 'fa-triangle-exclamation', error: 'fa-circle-exclamation' }[kind];
        box.insertAdjacentHTML('beforeend', `
            <div class="p-3 text-sm border rounded-xl ${tone}" role="${kind === 'error' ? 'alert' : 'status'}">
                ${message ? `<p class="font-medium"><i class="fa-solid ${icon} mr-1" aria-hidden="true"></i>${esc(message)}</p>` : ''}
                ${list && list.length ? `<ul class="mt-1 space-y-1 list-disc list-inside">${list.map(w => `<li>${esc(w)}</li>`).join('')}</ul>` : ''}
            </div>`);
    }

    async function scheduleRequest(method, url, body) {
        const res = await fetch(url, {
            method,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : undefined,
        });
        let data = {};
        try { data = await res.json(); } catch (e) { /* non-JSON error page */ }
        return { ok: res.ok, status: res.status, data };
    }

    function errorList(data) {
        return data && data.errors ? Object.values(data.errors).flat() : [];
    }

    async function openWorkScheduleModal(staffId, name) {
        scheduleStaffId = staffId;
        scheduleData = null;
        document.getElementById('workScheduleModalSubtitle').textContent = name || '';
        flash(null, null);
        document.getElementById('scheduleBody').innerHTML = `
            <p class="py-8 text-sm text-center text-gray-500 dark:text-gray-400">
                <i class="mr-1 fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Loading schedule…
            </p>`;
        openModal('workScheduleModal', 'button[aria-label="Close dialog"]');

        const r = await scheduleRequest('GET', routeFor(SCHEDULE_ROUTES.show, staffId));
        if (!r.ok) {
            document.getElementById('scheduleBody').innerHTML = '';
            flash('error', r.data.message || 'Could not load the schedule. Please try again.');
            return;
        }
        renderSchedule(r.data);
    }
    function closeWorkScheduleModal() { closeModal('workScheduleModal'); }

    function chip(status, label) {
        return `<span class="inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-full ${SCHEDULE_STATUS[status] || SCHEDULE_STATUS.closed}">${esc(label)}</span>`;
    }

    function renderSchedule(d) {
        scheduleData = d;
        const st = d.staff;
        document.getElementById('workScheduleModalSubtitle').textContent =
            [st.name, st.branch_name].filter(Boolean).join(' · ');

        const editingDays = d.editing ? d.editing.days : defaultDays(d);
        const effectiveFrom = d.editing ? d.editing.effective_from : d.today;

        document.getElementById('scheduleBody').innerHTML = `
            ${summaryHtml(d)}
            ${weekHtml(d)}
            ${d.can_edit ? patternFormHtml(d, editingDays, effectiveFrom) : patternReadHtml(d, editingDays)}
            ${overridesHtml(d)}
        `;
        if (d.can_edit) wireForms(d);
    }

    // No schedule yet: start the editor from the pay-profile rest days, if any.
    function defaultDays(d) {
        const rest = d.payroll_rest_days || [];
        const out = {};
        WEEKDAYS.forEach(w => { out[w] = { mode: rest.includes(w) ? 'rest' : 'branch', start: null, end: null }; });
        return out;
    }

    function summaryHtml(d) {
        const first = esc(d.staff.first_name);
        let html;
        if (!d.has_schedule) {
            html = `<div class="p-3 text-sm border rounded-xl border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-900/10 dark:text-blue-300">
                <i class="mr-1 fa-solid fa-circle-info" aria-hidden="true"></i>
                No work schedule yet. ${first} works the branch's opening hours on every open day, with no rest day, until one is set.
            </div>`;
        } else {
            const cur = d.current
                ? `In effect since <span class="font-medium text-gray-900 dark:text-white">${esc(d.current.effective_from_label)}</span> · Rest days: <span class="font-medium text-gray-900 dark:text-white">${esc(d.current.rest_days.join(', ') || 'none')}</span>`
                : 'No pattern in effect today (branch hours apply).';
            const up = d.upcoming.map(u => `<li>From <span class="font-medium text-gray-900 dark:text-white">${esc(u.effective_from_label)}</span>: rest days ${esc(u.rest_days.join(', ') || 'none')}</li>`).join('');
            html = `<div class="text-sm text-gray-600 dark:text-gray-300">
                <p>${cur}</p>
                ${up ? `<ul class="mt-1 space-y-0.5">${up}</ul>` : ''}
            </div>`;
        }
        if (d.staff.is_head && !d.can_edit) {
            html += `<p class="text-xs text-gray-500 dark:text-gray-400"><i class="mr-1 fa-solid fa-lock" aria-hidden="true"></i>Only the owner can change the HR Head's and Finance Head's schedules.</p>`;
        }
        return `<section class="space-y-2">${html}</section>`;
    }

    function weekHtml(d) {
        const tiles = d.week.map(day => `
            <li class="p-2 border border-gray-200 rounded-xl dark:border-gray-700">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">${esc(day.date_label)}</p>
                <p class="mt-1">${chip(day.status, day.status === 'working' ? 'Working' : day.label)}</p>
                ${day.status === 'working' ? `<p class="mt-1 text-xs text-gray-700 dark:text-gray-200">${esc(day.label)}</p>` : ''}
                ${day.source === 'override' ? `<p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">One-off change</p>` : ''}
            </li>`).join('');
        return `<section>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Next 14 days</h3>
            <ul class="grid grid-cols-2 gap-2 mt-3 sm:grid-cols-4 lg:grid-cols-7">${tiles}</ul>
        </section>`;
    }

    function patternReadHtml(d, days) {
        const rows = WEEKDAYS.map(w => {
            const day = days[w], bh = d.branch_hours[w];
            const text = day.mode === 'rest' ? 'Rest day'
                : day.mode === 'custom' ? `${fmtTime(day.start)} – ${fmtTime(day.end)}`
                : `Branch hours (${bh.label})`;
            return `<li class="flex justify-between gap-3 py-2 text-sm"><span class="font-medium text-gray-900 dark:text-white">${w}</span><span class="text-gray-600 dark:text-gray-300">${esc(text)}</span></li>`;
        }).join('');
        return `<section>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Weekly pattern</h3>
            <ul class="mt-2 divide-y divide-gray-200 dark:divide-gray-700">${rows}</ul>
        </section>`;
    }

    function patternFormHtml(d, days, effectiveFrom) {
        const rows = WEEKDAYS.map(w => {
            const day = days[w], bh = d.branch_hours[w];
            const opt = (v, l) => `<option value="${v}"${day.mode === v ? ' selected' : ''}>${l}</option>`;
            return `
            <div class="grid items-center grid-cols-1 gap-2 py-3 sm:grid-cols-[7rem_11rem_1fr]" data-day="${w}">
                <label for="sched-mode-${w}" class="text-sm font-medium text-gray-900 dark:text-white">${w}</label>
                <select id="sched-mode-${w}" name="mode" class="${esc(INPUT_CLASS)}">
                    ${opt('branch', 'Branch hours')}${opt('custom', 'Custom hours')}${opt('rest', 'Rest day')}
                </select>
                <div>
                    <div class="flex items-center gap-2 sched-times${day.mode === 'custom' ? '' : ' hidden'}">
                        <input type="time" name="start" aria-label="${w} start time" value="${esc(day.start || bh.open || '')}" class="${esc(INPUT_CLASS)}">
                        <span class="text-sm text-gray-500 dark:text-gray-400">to</span>
                        <input type="time" name="end" aria-label="${w} end time" value="${esc(day.end || bh.close || '')}" class="${esc(INPUT_CLASS)}">
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 sched-hint">${bh.closed ? '<i class="mr-1 fa-solid fa-store-slash" aria-hidden="true"></i>Branch closed' : 'Branch: ' + esc(bh.label)}</p>
                </div>
            </div>`;
        }).join('');

        return `<section>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Weekly pattern</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">"Branch hours" follows the branch if its hours change. Custom hours may start before opening or end after closing.</p>
            <form id="schedulePatternForm" class="mt-2" novalidate>
                <div class="divide-y divide-gray-200 dark:divide-gray-700">${rows}</div>
                <div id="schedulePatternNotes" class="mt-3 space-y-2" aria-live="polite"></div>
                <div class="flex flex-col gap-3 mt-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <label for="schedEffectiveFrom" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Applies from <x-required-mark /></label>
                        <input type="date" id="schedEffectiveFrom" name="effective_from" required
                               min="${esc(d.today)}" max="${esc(d.max_date)}" value="${esc(effectiveFrom)}" class="${esc(INPUT_CLASS)}">
                    </div>
                    <button type="submit" class="w-full ${esc(BTN.primary)} sm:w-auto">
                        <i class="text-xs fa-solid fa-floppy-disk" aria-hidden="true"></i><span>Save Weekly Pattern</span>
                    </button>
                </div>
            </form>
        </section>`;
    }

    function overridesHtml(d) {
        const items = d.overrides.map(o => `
            <li class="flex items-center justify-between gap-3 py-2">
                <div class="text-sm">
                    <p class="font-medium text-gray-900 dark:text-white">${esc(o.date_label)}
                        <span class="ml-1">${chip(o.type === 'day_off' ? 'day_off' : 'working', o.label)}</span></p>
                    ${o.paired_date_label ? `<p class="text-xs text-gray-500 dark:text-gray-400">${o.type === 'day_off' ? 'Works instead on' : 'In place of the day off on'} ${esc(o.paired_date_label)}. Removing one removes both.</p>` : ''}
                    ${o.reason ? `<p class="text-xs text-gray-500 dark:text-gray-400">${esc(o.reason)}</p>` : ''}
                </div>
                ${d.can_edit ? `<button type="button" data-remove-override="${o.id}" class="${esc(BTN.neutral)}" aria-label="Remove the change on ${esc(o.date_label)}">
                    <i class="text-xs fa-solid fa-trash" aria-hidden="true"></i><span>Remove</span></button>` : ''}
            </li>`).join('');

        const list = items
            ? `<ul class="mt-2 divide-y divide-gray-200 dark:divide-gray-700">${items}</ul>`
            : `<p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No upcoming one-off changes.</p>`;

        const form = d.can_edit ? `
            <form id="scheduleOverrideForm" class="grid grid-cols-1 gap-3 p-3 mt-3 border border-gray-200 rounded-xl sm:grid-cols-2 dark:border-gray-700" novalidate>
                <div>
                    <label for="ovDate" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Date <x-required-mark /></label>
                    <input type="date" id="ovDate" name="date" required min="${esc(d.today)}" max="${esc(d.max_date)}" class="${esc(INPUT_CLASS)}">
                </div>
                <div>
                    <label for="ovType" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Change <x-required-mark /></label>
                    <select id="ovType" name="type" class="${esc(INPUT_CLASS)}">
                        <option value="day_off">Day off (unpaid)</option>
                        <option value="swap">Rest-day swap</option>
                        <option value="shift">Shift (changed or extra hours)</option>
                    </select>
                </div>
                <div id="ovSwap" class="hidden sm:col-span-2">
                    <label for="ovSwapDate" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Works instead on (a rest day in the same week) <x-required-mark /></label>
                    <input type="date" id="ovSwapDate" name="swap_work_date" min="${esc(d.today)}" max="${esc(d.max_date)}" class="${esc(INPUT_CLASS)}">
                </div>
                <div id="ovTimes" class="hidden sm:col-span-2">
                    <p id="ovTimesHint" class="hidden mb-2 text-xs text-gray-500 dark:text-gray-400">Hours on the swapped day (leave empty to use branch hours).</p>
                    <div class="flex items-center gap-2">
                        <input type="time" name="start_time" aria-label="Shift start time" class="${esc(INPUT_CLASS)}">
                        <span class="text-sm text-gray-500 dark:text-gray-400">to</span>
                        <input type="time" name="end_time" aria-label="Shift end time" class="${esc(INPUT_CLASS)}">
                    </div>
                </div>
                <div class="sm:col-span-2">
                    <label for="ovReason" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Reason (optional)</label>
                    <input type="text" id="ovReason" name="reason" maxlength="150" placeholder="e.g. Swapped rest day with Ana" class="${esc(INPUT_CLASS)}">
                </div>
                <div class="sm:col-span-2 sm:flex sm:justify-end">
                    <button type="submit" class="w-full ${esc(BTN.primary)} sm:w-auto">
                        <i class="text-xs fa-solid fa-plus" aria-hidden="true"></i><span>Add Change</span>
                    </button>
                </div>
            </form>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">A change applies right away and replaces any other change on the same date.
                <strong class="font-medium">Day off (unpaid):</strong> monthly-paid staff lose a day's pay; daily-paid aren't paid that day.
                <strong class="font-medium">Rest-day swap:</strong> the day off becomes that week's rest day and they work their usual rest day instead, with no change in pay.
                An extra shift on a rest day earns the rest-day premium.</p>` : '';

        return `<section>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">One-off changes</h3>
            ${list}${form}
        </section>`;
    }

    function readPattern() {
        const days = {};
        document.querySelectorAll('#schedulePatternForm [data-day]').forEach(row => {
            const w = row.dataset.day;
            const mode = row.querySelector('select[name="mode"]').value;
            days[w] = { mode };
            if (mode === 'custom') {
                days[w].start = row.querySelector('input[name="start"]').value || null;
                days[w].end = row.querySelector('input[name="end"]').value || null;
            }
        });
        return days;
    }

    // Live notes while editing (the server repeats them on save): Art. 91 rest day,
    // and workdays on days the branch is closed.
    function refreshPatternNotes() {
        const d = scheduleData, days = readPattern(), notes = [];
        if (!Object.values(days).some(x => x.mode === 'rest')) {
            notes.push('No rest day is set. The Labor Code (Art. 91) gives every employee at least 24 consecutive hours of rest after six consecutive workdays. You can still save.');
        }
        WEEKDAYS.forEach(w => {
            if (d.branch_hours[w].closed && days[w].mode !== 'rest') notes.push(`The branch is closed on ${w}s, so that day won't be worked.`);
        });
        document.getElementById('schedulePatternNotes').innerHTML = notes.map(n =>
            `<p class="p-3 text-sm border rounded-xl border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/10 dark:text-amber-300">
                <i class="mr-1 fa-solid fa-triangle-exclamation" aria-hidden="true"></i>${esc(n)}</p>`).join('');
    }

    function setBusy(form, busy) {
        scheduleBusy = busy;
        const b = form.querySelector('button[type="submit"]');
        if (b) { b.disabled = busy; b.classList.toggle('opacity-70', busy); b.classList.toggle('cursor-not-allowed', busy); }
    }

    function wireForms(d) {
        const pf = document.getElementById('schedulePatternForm');
        pf.querySelectorAll('[data-day] select[name="mode"]').forEach(sel => {
            sel.addEventListener('change', () => {
                sel.closest('[data-day]').querySelector('.sched-times').classList.toggle('hidden', sel.value !== 'custom');
                refreshPatternNotes();
            });
        });
        refreshPatternNotes();

        pf.addEventListener('submit', async e => {
            e.preventDefault();
            if (scheduleBusy) return;
            setBusy(pf, true);
            flash(null, null);
            const r = await scheduleRequest('PUT', routeFor(SCHEDULE_ROUTES.update, scheduleStaffId), {
                effective_from: document.getElementById('schedEffectiveFrom').value,
                days: readPattern(),
            });
            setBusy(pf, false);
            handleResult(r);
        });

        const of = document.getElementById('scheduleOverrideForm');
        const typeSel = document.getElementById('ovType');
        typeSel.addEventListener('change', () => {
            const t = typeSel.value;
            document.getElementById('ovTimes').classList.toggle('hidden', t === 'day_off');
            document.getElementById('ovSwap').classList.toggle('hidden', t !== 'swap');
            document.getElementById('ovTimesHint').classList.toggle('hidden', t !== 'swap');
        });
        of.addEventListener('submit', async e => {
            e.preventDefault();
            if (scheduleBusy) return;
            setBusy(of, true);
            flash(null, null);
            const fd = new FormData(of);
            const choice = fd.get('type');
            const body = { date: fd.get('date'), reason: fd.get('reason') || null };
            if (choice === 'shift') {
                body.type = 'shift';
                body.start_time = fd.get('start_time') || null; body.end_time = fd.get('end_time') || null;
            } else if (choice === 'swap') {
                body.type = 'day_off'; body.kind = 'swap';
                body.swap_work_date = fd.get('swap_work_date') || null;
                body.start_time = fd.get('start_time') || null; body.end_time = fd.get('end_time') || null;
            } else {
                body.type = 'day_off'; body.kind = 'unpaid';
            }
            const r = await scheduleRequest('POST', routeFor(SCHEDULE_ROUTES.ovStore, scheduleStaffId), body);
            setBusy(of, false);
            handleResult(r);
        });

        document.querySelectorAll('[data-remove-override]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (scheduleBusy) return;
                scheduleBusy = true;
                btn.disabled = true;
                flash(null, null);
                const url = routeFor(SCHEDULE_ROUTES.ovDestroy, scheduleStaffId).replace('__OV__', encodeURIComponent(btn.dataset.removeOverride));
                const r = await scheduleRequest('DELETE', url);
                scheduleBusy = false;
                handleResult(r);
            });
        });
    }

    function handleResult(r) {
        if (r.ok) {
            renderSchedule(r.data.schedule);
            flash('success', r.data.message);
            if (r.data.warnings && r.data.warnings.length) flash('warning', 'Please note:', r.data.warnings);
        } else {
            const errs = errorList(r.data);
            flash('error', r.status === 422 && errs.length ? 'Please fix the following:' : (r.data.message || 'Something went wrong. Please try again.'), errs);
        }
        document.getElementById('scheduleFlash').scrollIntoView({ block: 'nearest' });
    }

    window.openWorkScheduleModal  = openWorkScheduleModal;
    window.closeWorkScheduleModal = closeWorkScheduleModal;

}()); // end page script
</script>

<style>
@media (max-width: 767px) {
    .rt,
    .rt tbody,
    .rt tr,
    .rt td { display: block; width: 100%; }

    .rt thead { display: none; }

    .rt tr { padding: 0.75rem 1rem; }

    .rt td {
        padding: 0.375rem 0 !important;
        text-align: left !important;
    }

    .rt td[data-label]::before {
        content: attr(data-label);
        display: block;
        margin-bottom: 0.125rem;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .rt td.rt-actions { padding-top: 0.75rem !important; }

    .rt td.rt-empty {
        padding: 2rem 0 !important;
        text-align: center !important;
    }
}

@media (max-width: 767px) and (prefers-color-scheme: dark) {
    .rt td[data-label]::before { color: #9ca3af; }
}
</style>
@endsection
