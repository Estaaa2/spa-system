@extends('layouts.app')
@section('content')
@php
    $user = auth()->user();
    $spa = $user?->spa;
    $isProfessional = $spa?->isProfessional() ?? false;
    $canViewStaff = $user?->hasBranchPermission('view staff') ?? false;
    $canCreateStaff = $user?->hasBranchPermission('create staff') ?? false;
    $canEditStaff = $user?->hasBranchPermission('edit staff') ?? false;
    $canDeleteStaff = $user?->hasBranchPermission('delete staff') ?? false;
    $showActions = $canEditStaff || $canDeleteStaff;
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
    foreach ($staff as $member) {
        $role = $member->user?->getRoleNames()->first();
        if ($role && array_key_exists($role, $roleCounts)) {
            $roleCounts[$role]++;
        }
    }
    $professionalRolesCount = $roleCounts['hr'] + $roleCounts['finance'];
@endphp
<div class="p-6 mx-auto space-y-6 max-w-7xl">
    <x-page-header
        title="Staff Management"
        subtitle="Add, edit, and manage your spa staff members."
    />
    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Total Staff</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-gray-900 dark:text-white">{{ $staffCount }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Active members</span>
            </div>
        </div>
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Therapists</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-gray-900 dark:text-white">{{ $roleCounts['therapist'] }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Service staff</span>
            </div>
        </div>
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Managers</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-gray-900 dark:text-white">{{ $roleCounts['manager'] }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Leadership roles</span>
            </div>
        </div>
        <div class="p-5 border shadow-sm rounded-2xl {{ $hasSuite ? 'bg-indigo-50 border-indigo-200 dark:bg-indigo-900/10 dark:border-indigo-800' : 'bg-amber-50 border-amber-200 dark:bg-amber-900/10 dark:border-amber-800' }}">
            <p class="text-xs font-semibold tracking-wide uppercase {{ $isProfessional ? 'text-indigo-700 dark:text-indigo-300' : 'text-amber-700 dark:text-amber-300' }}">
                {{ $hasSuite ? 'Professional Roles' : 'Suite Status' }}
            </p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold {{ $isProfessional ? 'text-indigo-900 dark:text-indigo-200' : 'text-amber-900 dark:text-amber-200' }}">
                    {{ $hasSuite ? $professionalRolesCount : 'Disabled' }}
                </h3>
                <span class="text-sm {{ $isProfessional ? 'text-indigo-700 dark:text-indigo-300' : 'text-amber-700 dark:text-amber-300' }}">
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
                <a href="{{ route('owner.subscription.index') }}"
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-white rounded-xl bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:opacity-90">
                    <i class="mr-2 fa-solid fa-arrow-up-right-from-square"></i>
                    Upgrade Subscription
                </a>
            </div>
        </div>
    @endif
    {{-- Add New Staff --}}
    @if($canCreateStaff)
        @if($hasReachedStaffLimit)
            <div class="overflow-hidden bg-white border border-red-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-red-800">
                <div class="px-6 py-5">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-red-700 dark:text-red-300">Staff Limit Reached</h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                This branch already has {{ $staffCount }} staff account(s), which is the maximum allowed for the Basic plan.
                                Upgrade your subscription to add more staff members.
                            </p>
                        </div>
                        <a href="{{ route('owner.subscription.index') }}"
                           class="inline-flex items-center px-4 py-2 text-sm font-medium text-white rounded-xl bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:opacity-90">
                            <i class="mr-2 fa-solid fa-crown"></i>
                            Unlock Unlimited Staff
                        </a>
                    </div>
                </div>
            </div>
        @else
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add New Staff Member</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Create a new staff account and assign the appropriate role for this branch.
                        </p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        @if(!$isProfessional)
                            <div class="px-3 py-2 text-xs text-right rounded-xl bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                                <i class="mr-1 fa-solid fa-lock"></i>
                                HR & Finance roles require Professional
                            </div>
                        @endif
                        @if(!$hasUnlimitedStaff)
                            <div class="px-3 py-2 text-xs text-right text-gray-700 bg-gray-100 rounded-xl dark:bg-gray-700 dark:text-gray-300">
                                {{ $remainingStaffSlots }} of {{ $staffLimit }} slot(s) remaining
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="p-6">
                <form action="{{ route('staff.store') }}" method="POST" id="addStaffForm" class="space-y-5">
                    @csrf

                    {{-- Row 1: Name Fields --}}
                    <div>
                        <p class="mb-2 text-sm font-semibold text-gray-700 dark:text-white">
                            Full Name <span class="text-red-500">*</span>
                        </p>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div>
                                <label class="block mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">First Name</label>
                                <input
                                    type="text"
                                    name="first_name"
                                    required
                                    class="block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                                    placeholder="e.g. Juan"
                                    value="{{ old('first_name') }}"
                                >
                                @error('first_name')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                                    Middle Name <span class="font-normal text-gray-400">(optional)</span>
                                </label>
                                <input
                                    type="text"
                                    name="middle_name"
                                    class="block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                                    placeholder="e.g. Santos"
                                    value="{{ old('middle_name') }}"
                                >
                            </div>
                            <div>
                                <label class="block mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">Last Name</label>
                                <input
                                    type="text"
                                    name="last_name"
                                    required
                                    class="block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                                    placeholder="e.g. Dela Cruz"
                                    value="{{ old('last_name') }}"
                                >
                                @error('last_name')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Row 2: Email and Role --}}
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="email"
                                name="email"
                                required
                                class="block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                                placeholder="staff@example.com"
                                value="{{ old('email') }}"
                            >
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                                Staff Assignment <span class="text-red-500">*</span>
                            </label>
                            <select
                                name="roles"
                                required
                                class="block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                            >
                                <option value="">Assign Staff Role</option>
                                <optgroup label="Spa Staff">
                                    <option value="therapist" {{ old('roles') == 'therapist' ? 'selected' : '' }}>Therapist</option>
                                    <option value="receptionist" {{ old('roles') == 'receptionist' ? 'selected' : '' }}>Receptionist</option>
                                    <option value="manager" {{ old('roles') == 'manager' ? 'selected' : '' }}>Manager</option>
                                </optgroup>
                                @if($hasSuite)
                                <optgroup label="Professional Roles ✦">
                                    <option value="hr" {{ old('roles') == 'hr' ? 'selected' : '' }}>HR</option>
                                    <option value="finance" {{ old('roles') == 'finance' ? 'selected' : '' }}>Finance</option>
                                </optgroup>
                                @endif
                            </select>
                            @error('roles')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @if(!$hasSuite)
                                <p class="mt-1 text-xs text-gray-400">
                                    <i class="fa-solid fa-lock text-[#8B7355]"></i>
                                    HR & Finance roles require the Workforce & Finance Suite to be enabled on this branch.
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="flex justify-end pt-1">
                        <button
                            type="submit"
                            class="px-5 py-2.5 text-sm font-medium text-white bg-[#8B7355] rounded-xl hover:bg-[#7A6348] focus:ring-4 focus:outline-none focus:ring-[#8B7355]/40"
                        >
                            <i class="mr-1.5 fa-solid fa-user-plus"></i>
                            Add Staff Member
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    @endif
    {{-- Staff Directory --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Staff Directory</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    View assigned roles and manage staff accounts for this branch.
                </p>
            </div>
            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ $staffCount }} staff member(s)
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Staff Member</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Role</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Assigned Branch</th>
                        @if($showActions)
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse($staff as $member)
                    <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-10 h-10 text-sm font-semibold text-white rounded-full bg-[#8B7355]">
                                    {{ strtoupper(substr($member->user->first_name ?? 'S', 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ trim($member->user->first_name . ' ' . ($member->user->middle_name ? $member->user->middle_name . ' ' : '') . $member->user->last_name) }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $member->user->email ?? 'No email' }}
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $role = $member->user?->getRoleNames()->first();
                                $roleColors = [
                                    'manager'      => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                    'therapist'    => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                                    'receptionist' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
                                    'hr'           => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
                                    'finance'      => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
                                ];
                                $colorClass = $roleColors[$role] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                            @endphp
                            @if($role)
                                <span class="px-3 py-1 text-xs font-medium rounded-full {{ $colorClass }}">
                                    {{ ucfirst($role) }}
                                    @if(in_array($role, ['hr', 'finance']))
                                        <i class="fa-solid fa-star text-[10px] ml-0.5"></i>
                                    @endif
                                </span>
                            @else
                                <span class="px-3 py-1 text-xs font-medium text-gray-500 bg-gray-200 rounded-full dark:bg-gray-700 dark:text-gray-300">
                                    No role
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($member->branch)
                                <div class="text-sm text-gray-800 dark:text-white">
                                    {{ $member->branch->name }}
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $member->branch->location }}
                                    </p>
                                </div>
                            @else
                                <span class="text-sm text-gray-400 dark:text-gray-500">No branch assigned</span>
                            @endif
                        </td>
                        @if($showActions)
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                @if($canEditStaff)
                                <button
                                    type="button"
                                    onclick="editStaff({{ $member->id }}, {{ $isProfessional ? 'true' : 'false' }})"
                                    class="px-3 py-1.5 text-sm text-white bg-yellow-500 rounded-lg hover:bg-yellow-600"
                                >
                                    Edit
                                </button>
                                @endif
                                @if($canDeleteStaff)
                                <button
                                    type="button"
                                    onclick='openDeleteModal({{ $member->id }}, @json(trim($member->user->first_name . " " . $member->user->last_name)))'
                                    class="px-3 py-1.5 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700"
                                >
                                    Remove
                                </button>
                                @endif
                            </div>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $showActions ? 4 : 3 }}" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            <div class="flex flex-col items-center justify-center">
                                <i class="mb-3 text-4xl text-gray-400 fas fa-users"></i>
                                <p class="mb-2 text-gray-600 dark:text-gray-400">No staff members found</p>
                                <p class="text-sm text-gray-500 dark:text-gray-500">
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
<div id="editModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>
        <div class="inline-block w-full max-w-md my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <form id="editStaffForm" method="POST">
                @csrf
                @method('PUT')
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit Staff Member</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update the assigned role for this staff member.</p>
                        </div>
                        <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="px-6 py-5">
                    <div class="space-y-4" id="editFormContent"></div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900">
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeEditModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 shadow-sm rounded-xl hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] border border-transparent rounded-xl shadow-sm hover:bg-[#7A6348]">
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
<div id="deleteModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50">
    <div class="w-full max-w-md p-6 mx-auto mt-24 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Confirm Remove</h2>
            <button type="button" onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <p class="text-gray-500 dark:text-gray-400">
            Are you sure you want to remove
            <span id="deleteStaffName" class="font-semibold text-gray-800 dark:text-white"></span>?
            This action cannot be undone.
        </p>
        <div class="flex justify-end gap-2 mt-6">
            <button type="button" onclick="closeDeleteModal()"
                class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200">
                Cancel
            </button>
            <form id="deleteStaffForm" method="POST">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-sm text-white bg-red-600 rounded-xl hover:bg-red-700">
                    Yes, Remove
                </button>
            </form>
        </div>
    </div>
</div>
@endif
<script>
const staffBaseUrl = @json(url('/staff'));
const isProfessional = {{ $isProfessional ? 'true' : 'false' }};
@if($canDeleteStaff)
function openDeleteModal(id, name) {
    document.getElementById('deleteStaffName').textContent = name ?? '';
    document.getElementById('deleteStaffForm').action = `${staffBaseUrl}/${id}`;
    document.getElementById('deleteModal').classList.remove('hidden');
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}
@endif
@if($canEditStaff)
function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
function editStaff(staffId, isPro = false) {
    const professionalOptions = isPro ? `
        <optgroup label="Professional Roles ✦">
            <option value="hr">HR</option>
            <option value="finance">Finance</option>
        </optgroup>
    ` : '';
    document.getElementById('editFormContent').innerHTML = `
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Role *</label>
            <select
                name="roles"
                required
                class="block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            >
                <option value="">Select Role</option>
                <optgroup label="Spa Staff">
                    <option value="therapist">Therapist</option>
                    <option value="receptionist">Receptionist</option>
                    <option value="manager">Manager</option>
                </optgroup>
                ${professionalOptions}
            </select>
            ${
                !isPro
                    ? `<p class="mt-2 text-xs text-gray-400">
                        <i class="fa-solid fa-lock text-[#8B7355]"></i>
                        HR & Finance roles require the Professional plan.
                       </p>`
                    : ''
            }
        </div>
    `;
    document.getElementById('editStaffForm').action = `${staffBaseUrl}/${staffId}`;
    document.getElementById('editModal').classList.remove('hidden');
}
@endif
</script>
@endsection
