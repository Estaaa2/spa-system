@extends('layouts.app')

@section('title', 'Staff Management')
@section('content')
@php
    $user = auth()->user();
    $spa = $user?->spa;
    $isProfessional = $spa?->isProfessional() ?? false;
    $canCreateStaff = $user?->hasBranchPermission('create staff') ?? false;
    $canEditStaff = $user?->hasBranchPermission('edit staff') ?? false;
    $canDeleteStaff = $user?->hasBranchPermission('delete staff') ?? false;
    $showActions = $canEditStaff || $canDeleteStaff;

    // OUTSTANDING: hardcoded in the view. If StaffController enforces a different
    // number the UI and the server disagree silently. Left as-is on purpose.
    $staffLimit = 10;
    $staffCount = $staff->count();
    $hasUnlimitedStaff = $isProfessional;
    $hasReachedStaffLimit = !$hasUnlimitedStaff && $staffCount >= $staffLimit;
    $remainingStaffSlots = max($staffLimit - $staffCount, 0);

    $roleCounts = [
        'manager' => 0,
        'therapist' => 0,
        'receptionist' => 0,
        'hr' => 0,
        'finance' => 0,
    ];

    // One pass over the collection resolves the role for every member and keeps
    // it, so the table rows below don't call getRoleNames() a second time.
    $memberRoles = [];
    foreach ($staff as $member) {
        $role = $member->user?->getRoleNames()->first();
        $memberRoles[$member->id] = $role;
        if ($role && array_key_exists($role, $roleCounts)) {
            $roleCounts[$role]++;
        }
    }
    $professionalRolesCount = $roleCounts['hr'] + $roleCounts['finance'];

    // Role badge colours. Hoisted out of the row loop so the array is built once
    // instead of once per staff member. Families follow the canon status palette
    // (§6): emerald not green, amber not yellow, violet not purple.
    $roleColors = [
        'manager'      => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'therapist'    => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'receptionist' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'hr'           => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
        'finance'      => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
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
        {{-- OUTSTANDING: this card switches its background on $hasSuite and its text
             colours on $isProfessional. Preserved exactly as found — see notes. --}}
        <div class="p-4 border shadow-sm sm:p-5 rounded-2xl {{ $hasSuite ? 'bg-indigo-50 border-indigo-200 dark:bg-indigo-900/10 dark:border-indigo-800' : 'bg-amber-50 border-amber-200 dark:bg-amber-900/10 dark:border-amber-800' }}">
            <p class="text-xs font-semibold tracking-wide uppercase {{ $isProfessional ? 'text-indigo-700 dark:text-indigo-300' : 'text-amber-700 dark:text-amber-300' }}">
                {{ $hasSuite ? 'Professional Roles' : 'Suite Status' }}
            </p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold sm:text-3xl {{ $isProfessional ? 'text-indigo-900 dark:text-indigo-200' : 'text-amber-900 dark:text-amber-200' }}">
                    {{ $hasSuite ? $professionalRolesCount : 'Disabled' }}
                </h3>
                <span class="text-xs sm:text-sm {{ $isProfessional ? 'text-indigo-700 dark:text-indigo-300' : 'text-amber-700 dark:text-amber-300' }}">
                    {{ $hasSuite ? 'HR & Finance roles' : 'Suite Disabled' }}
                </span>
            </div>
        </div>
    </div>
    {{-- Staff Plan Limit Notice --}}
    @if(!$hasUnlimitedStaff)
        <div class="p-4 border border-amber-200 rounded-2xl bg-amber-50 dark:bg-amber-900/10 dark:border-amber-800">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-sm font-semibold tracking-wide uppercase text-amber-800 dark:text-amber-300">
                        Basic Plan Staff Limit
                    </h2>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                        This branch can only have up to <span class="font-semibold">{{ $staffLimit }}</span> staff accounts on the Basic plan.
                        @if($hasReachedStaffLimit)
                            You have already reached the limit.
                        @else
                            You still have <span class="font-semibold">{{ $remainingStaffSlots }}</span> staff slot(s) remaining.
                        @endif
                    </p>
                </div>
                <a href="{{ route('owner.subscription.index') }}" class="w-full {{ $btn['upgrade'] }} sm:w-auto">
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    Upgrade Subscription
                </a>
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
                            <h2 class="text-base font-semibold text-red-700 dark:text-red-300">Staff Limit Reached</h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                This branch already has {{ $staffCount }} staff account(s), which is the maximum allowed for the Basic plan.
                                Upgrade your subscription to add more staff members.
                            </p>
                        </div>
                        <a href="{{ route('owner.subscription.index') }}" class="w-full {{ $btn['upgrade'] }} sm:w-auto">
                            <i class="fa-solid fa-crown" aria-hidden="true"></i>
                            Unlock Unlimited Staff
                        </a>
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
                        @if(!$isProfessional)
                            <div class="px-3 py-2 text-xs rounded-xl bg-amber-50 text-amber-700 sm:text-right dark:bg-amber-900/20 dark:text-amber-300">
                                <i class="mr-1 fa-solid fa-lock" aria-hidden="true"></i>
                                HR &amp; Finance roles require Professional
                            </div>
                        @endif
                        @if(!$hasUnlimitedStaff)
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
                            Full Name <span class="text-red-500">*</span>
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
                                Email Address <span class="text-red-500">*</span>
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
                                Staff Assignment <span class="text-red-500">*</span>
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
                                {{-- OUTSTANDING: gated on $hasSuite here, on $isProfessional in the
                                     edit modal. Left divergent on purpose — see notes. --}}
                                @if($hasSuite)
                                <optgroup label="Professional Roles ✦">
                                    <option value="hr" {{ old('roles') == 'hr' ? 'selected' : '' }}>HR</option>
                                    <option value="finance" {{ old('roles') == 'finance' ? 'selected' : '' }}>Finance</option>
                                </optgroup>
                                @endif
                            </select>
                            @error('roles')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                            @if(!$hasSuite)
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    <i class="fa-solid fa-lock text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                                    HR &amp; Finance roles require the Workforce &amp; Finance Suite to be enabled on this branch.
                                </p>
                            @endif
                        </div>
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
        <div class="flex flex-col gap-1 px-4 py-4 border-b border-gray-200 sm:flex-row sm:items-center sm:justify-between sm:gap-3 sm:px-6 dark:border-gray-700">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Staff Directory</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    View assigned roles and manage staff accounts for this branch.
                </p>
            </div>
            <span class="text-sm text-gray-500 shrink-0 dark:text-gray-400">
                {{ $staffCount }} staff member(s)
            </span>
        </div>
        <div class="md:overflow-x-auto">
            {{-- .rt collapses this table into stacked cards below md. The explicit
                 role="" attributes are required because changing display away from
                 table strips the native table semantics. --}}
            <table role="table" class="rt min-w-full divide-y divide-gray-200 dark:divide-gray-700">
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
                                    {{ ucfirst($role) }}
                                    @if(in_array($role, ['hr', 'finance']))
                                        <i class="fa-solid fa-star text-[10px]" aria-hidden="true"></i>
                                    @endif
                                </span>
                            @else
                                <span class="px-3 py-1 text-xs font-medium rounded-full {{ $roleColorFallback }}">
                                    No role
                                </span>
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
                                @if($canEditStaff)
                                <button
                                    type="button"
                                    onclick='editStaff({{ $member->id }}, {{ $isProfessional ? 'true' : 'false' }}, @json($role), @json($displayName))'
                                    class="{{ $btn['edit'] }}"
                                >
                                    <i class="text-xs fa-solid fa-pen" aria-hidden="true"></i>
                                    <span>Edit</span>
                                </button>
                                @endif
                                @if($canDeleteStaff)
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
                                    {{ $canCreateStaff ? 'Add your first staff member using the form above.' : 'No staff members are available for this branch yet.' }}
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
    const MODAL_IDS = ['editModal', 'deleteModal'];
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

    // OUTSTANDING: isPro comes from $isProfessional at the call site, while the
    // create form gates the same optgroup on $hasSuite. Left divergent on purpose.
    function editStaff(staffId, isPro, currentRole, staffName) {
        isPro = isPro === true;
        currentRole = currentRole || '';

        const sel = role => currentRole === role ? ' selected' : '';

        const professionalOptions = isPro ? `
            <optgroup label="Professional Roles ✦">
                <option value="hr"${sel('hr')}>HR</option>
                <option value="finance"${sel('finance')}>Finance</option>
            </optgroup>
        ` : '';

        // If the member's current role isn't in the list this select can offer,
        // the placeholder stays selected — better than silently showing a role
        // the form can't actually submit.
        document.getElementById('editFormContent').innerHTML = `
            <div>
                <label for="editRoleSelect" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Role *</label>
                <select id="editRoleSelect" name="roles" required class="${esc(INPUT_CLASS)}">
                    <option value=""${currentRole ? '' : ' selected'}>Select Role</option>
                    <optgroup label="Spa Staff">
                        <option value="therapist"${sel('therapist')}>Therapist</option>
                        <option value="receptionist"${sel('receptionist')}>Receptionist</option>
                        <option value="manager"${sel('manager')}>Manager</option>
                    </optgroup>
                    ${professionalOptions}
                </select>
                ${
                    !isPro
                        ? `<p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            <i class="fa-solid fa-lock text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                            HR &amp; Finance roles require the Professional plan.
                           </p>`
                        : ''
                }
            </div>
        `;

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