<!-- resources/views/branches/index.blade.php -->

@extends('layouts.app')

@section('title', 'Branches')

@section('content')
@php
    $canUseProfessionalSuite = ($spa->business_tier ?? null) === 'professional';

    $branchLimit          = 2;
    $branchCount          = $branches->count();
    $hasUnlimitedBranches = $canUseProfessionalSuite;
    $hasReachedBranchLimit = !$hasUnlimitedBranches && $branchCount >= $branchLimit;
    $remainingBranchSlots  = max($branchLimit - $branchCount, 0);
@endphp

<div class="p-6 mx-auto space-y-6 max-w-7xl">

    <x-page-header
        title="Branches"
        subtitle="Manage all branches for your spa — general info, operating hours, and public profile."
    />

    {{-- ── Plan Limit Notice ──────────────────────────────────────────────── --}}
    @if(!$hasUnlimitedBranches)
        <div class="p-4 border border-amber-200 rounded-2xl bg-amber-50 dark:bg-amber-900/10 dark:border-amber-800">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-sm font-semibold tracking-wide uppercase text-amber-800 dark:text-amber-300">
                        Basic Plan — Branch Limit
                    </h2>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                        Your plan supports up to <span class="font-semibold">{{ $branchLimit }}</span> branches.
                        @if($hasReachedBranchLimit)
                            You have reached the limit.
                        @else
                            You have <span class="font-semibold">{{ $remainingBranchSlots }}</span> slot(s) remaining.
                        @endif
                    </p>
                </div>
                <a href="{{ route('owner.subscription.index') }}"
                   class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg">
                    <i class="mr-2 fa-solid fa-arrow-up-right-from-square"></i>
                    Upgrade Plan
                </a>
            </div>
        </div>
    @endif

    {{-- ── Stats Row ───────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Total Branches</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ $branchCount }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">All branches under this spa</p>
        </div>

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Publicly Listed</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                {{ $branches->filter(fn($b) => (bool) optional($b->profile)->is_listed)->count() }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Visible on the landing page</p>
        </div>

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                {{ $canUseProfessionalSuite ? 'Suite Enabled' : 'Slots Remaining' }}
            </p>
            <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                {{ $canUseProfessionalSuite
                    ? $branches->where('has_workforce_finance_suite', true)->count()
                    : $remainingBranchSlots }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ $canUseProfessionalSuite ? 'Branches with Workforce & Finance Suite' : 'Available on your Basic plan' }}
            </p>
        </div>

    </div>

    {{-- ── Branch Directory ─────────────────────────────────────────────────── --}}
    <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">

        <div class="flex items-center justify-between px-6 py-5 border-b dark:border-gray-700">
            <div>
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Branch Directory</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                    {{ $branchCount }} {{ Str::plural('branch', $branchCount) }} for {{ $spa->name }}
                </p>
            </div>

            @if($hasReachedBranchLimit)
                <button type="button" disabled
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-400 bg-gray-100 rounded-lg cursor-not-allowed dark:bg-gray-700 dark:text-gray-500">
                    <i class="mr-2 fa-solid fa-lock"></i>
                    Limit Reached
                </button>
            @else
                <button onclick="openCreateModal()"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg hover:opacity-90 transition">
                    <i class="mr-2 fa-solid fa-plus"></i>
                    Add Branch
                </button>
            @endif
        </div>

        @if($branches->count() > 0)

            <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2 xl:grid-cols-3">

                @foreach($branches as $branch)
                @php
                    $isListed  = (bool) optional($branch->profile)->is_listed;
                    $hasSuite  = $canUseProfessionalSuite && $branch->has_workforce_finance_suite;
                    $isCurrent = session('current_branch_id') == $branch->id;
                    $canDelete = !$branch->is_main && $branch->users_count == 0;
                @endphp

                <div class="flex flex-col overflow-hidden border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-800/50">

                    {{-- Card header --}}
                    <div class="flex items-start gap-4 p-5 border-b border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 rounded-xl
                            {{ $branch->is_main ? 'bg-amber-100 dark:bg-amber-900/30' : 'bg-gray-100 dark:bg-gray-700' }}">
                            <i class="fa-solid {{ $branch->is_main ? 'fa-crown text-amber-500' : 'fa-store text-gray-500 dark:text-gray-400' }}"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-900 truncate dark:text-white">{{ $branch->name }}</p>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400 truncate">
                                <i class="mr-1 fa-solid fa-location-dot text-[#8B7355] text-xs"></i>
                                {{ $branch->location }}
                            </p>
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                @if($branch->is_main)
                                    <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                        Main Branch
                                    </span>
                                @endif
                                @if($isCurrent)
                                    <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                        Currently Viewing
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Card stats --}}
                    <div class="grid grid-cols-3 divide-x divide-gray-100 dark:divide-gray-700 px-0">
                        <div class="flex flex-col items-center py-3">
                            <p class="text-base font-semibold text-gray-800 dark:text-white">{{ $branch->users_count }}</p>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">Staff</p>
                        </div>
                        <div class="flex flex-col items-center py-3">
                            <span class="inline-flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full {{ $isListed ? 'bg-green-500' : 'bg-gray-300' }}"></span>
                                <p class="text-[10px] font-semibold {{ $isListed ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-gray-500' }}">
                                    {{ $isListed ? 'Listed' : 'Unlisted' }}
                                </p>
                            </span>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">Public</p>
                        </div>
                        <div class="flex flex-col items-center py-3">
                            @if($canUseProfessionalSuite)
                                <span class="inline-flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $hasSuite ? 'bg-indigo-500' : 'bg-gray-300' }}"></span>
                                    <p class="text-[10px] font-semibold {{ $hasSuite ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400' }}">
                                        {{ $hasSuite ? 'On' : 'Off' }}
                                    </p>
                                </span>
                                <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">Suite</p>
                            @else
                                <span class="text-[10px] text-gray-400">—</span>
                                <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">Suite</p>
                            @endif
                        </div>
                    </div>

                    {{-- Card actions — direct tab links --}}
                    <div class="p-4 mt-auto border-t border-gray-100 dark:border-gray-700">
                        <div class="grid grid-cols-3 gap-2">
                            <a href="{{ route('branches.edit', $branch->id) }}?tab=general"
                                class="flex flex-col items-center gap-1 px-2 py-2.5 text-center text-xs font-medium text-gray-700 transition rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                                <i class="fa-solid fa-pen text-[#8B7355] text-sm"></i>
                                General
                            </a>
                            <a href="{{ route('branches.edit', $branch->id) }}?tab=hours"
                                class="flex flex-col items-center gap-1 px-2 py-2.5 text-center text-xs font-medium text-gray-700 transition rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                                <i class="fa-solid fa-clock text-[#8B7355] text-sm"></i>
                                Hours
                            </a>
                            <a href="{{ route('branches.edit', $branch->id) }}?tab=profile"
                                class="flex flex-col items-center gap-1 px-2 py-2.5 text-center text-xs font-medium text-gray-700 transition rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                                <i class="fa-solid fa-image text-[#8B7355] text-sm"></i>
                                Profile
                            </a>
                        </div>

                        @if($canDelete)
                            <button onclick="openDeleteModal({{ $branch->id }}, '{{ addslashes($branch->name) }}')"
                                class="w-full mt-2 py-1.5 text-xs font-medium text-red-600 transition rounded-xl hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">
                                <i class="mr-1 fa-solid fa-trash-can"></i>
                                Remove Branch
                            </button>
                        @endif
                    </div>

                </div>
                @endforeach

            </div>

        @else
            <div class="flex flex-col items-center py-16 text-center">
                <div class="flex items-center justify-center w-16 h-16 mb-4 rounded-2xl bg-[#F6EFE6] dark:bg-gray-700">
                    <i class="fa-solid fa-store text-2xl text-[#8B7355]"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">No branches yet</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Get started by adding your first branch.</p>
                <button onclick="openCreateModal()"
                    class="inline-flex items-center gap-2 mt-6 px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg hover:opacity-90">
                    <i class="fa-solid fa-plus"></i>
                    Add First Branch
                </button>
            </div>
        @endif

    </div>
</div>

{{-- ════════════════════════════════════════════════════════════════════════════
     CREATE BRANCH MODAL (unchanged from original)
═════════════════════════════════════════════════════════════════════════════ --}}
<div id="branchModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75"></div>

        <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white shadow-xl rounded-2xl dark:bg-gray-800 sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <form id="branchForm" method="POST">
                @csrf

                <div class="px-6 py-4 bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Add New Branch</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Set the branch name, location, and initial operating hours.
                            </p>
                        </div>
                        <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
                            <i class="text-xl fa-solid fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="px-6 py-5 space-y-5">

                    {{-- Name --}}
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Branch Name *</label>
                        <input type="text" id="name" name="name" required
                               class="block w-full mt-2 border-gray-300 rounded-xl shadow-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                    </div>

                    {{-- Location --}}
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Location / City *</label>
                            <button type="button" id="toggleLocationMode"
                                class="text-[10px] font-semibold text-[#8B7355] hover:text-[#6F5430] underline">
                                Type manually
                            </button>
                        </div>

                        <div id="locationDropdownWrapper">
                            <select id="locationSelect"
                                class="block w-full border-gray-300 rounded-xl shadow-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                                <option value="">Select city or location</option>
                                <optgroup label="Cities">
                                    <option value="Bacoor">Bacoor</option>
                                    <option value="Cavite City">Cavite City</option>
                                    <option value="Dasmariñas">Dasmariñas</option>
                                    <option value="General Trias">General Trias</option>
                                    <option value="Imus">Imus</option>
                                    <option value="Carmona">Carmona</option>
                                    <option value="Tagaytay">Tagaytay</option>
                                    <option value="Trece Martires">Trece Martires</option>
                                </optgroup>
                                <optgroup label="Municipalities">
                                    <option value="Alfonso">Alfonso</option>
                                    <option value="Amadeo">Amadeo</option>
                                    <option value="Carmen">Carmen</option>
                                    <option value="General Emilio Aguinaldo">General Emilio Aguinaldo</option>
                                    <option value="General Mariano Alvarez">General Mariano Alvarez</option>
                                    <option value="Indang">Indang</option>
                                    <option value="Kawit">Kawit</option>
                                    <option value="Magallanes">Magallanes</option>
                                    <option value="Maragondon">Maragondon</option>
                                    <option value="Mendez">Mendez</option>
                                    <option value="Naic">Naic</option>
                                    <option value="Noveleta">Noveleta</option>
                                    <option value="Rosario">Rosario</option>
                                    <option value="Silang">Silang</option>
                                    <option value="Tanza">Tanza</option>
                                    <option value="Ternate">Ternate</option>
                                </optgroup>
                            </select>
                        </div>

                        <div id="locationInputWrapper" class="hidden mt-1">
                            <input type="text" id="locationManualInput"
                                class="block w-full border-gray-300 rounded-xl shadow-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                placeholder="Type city / area manually">
                        </div>

                        <input type="hidden" name="location" id="locationValue">
                    </div>

                    {{-- Main branch checkbox --}}
                    <div class="flex items-start gap-3">
                        <input type="hidden" name="is_main" value="0">
                        <input type="checkbox" id="is_main" name="is_main" value="1"
                               class="mt-0.5 w-4 h-4 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355] dark:bg-gray-700">
                        <div>
                            <label for="is_main" id="is_main_label" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Set as main branch
                            </label>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                The main branch is the spa's primary location.
                            </p>
                        </div>
                    </div>

                    {{-- Operating Hours --}}
                    <div>
                        <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Initial Operating Hours</h3>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            @php $daysOfWeek = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday']; @endphp
                            @foreach($daysOfWeek as $index => $day)
                            <div class="p-4 bg-white shadow-sm dark:bg-gray-800 rounded-2xl ring-1 ring-black/5 dark:ring-white/10" id="new_card_{{ $index }}">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $day }}</h4>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="hidden" name="hours[{{ $index }}][is_closed]" value="0" />
                                        <input type="checkbox"
                                            name="hours[{{ $index }}][is_closed]" value="1"
                                            class="w-4 h-4 rounded text-[#8B7355] border-gray-300"
                                            onchange="toggleTimeInputs(this, 'new_opening_{{ $index }}', 'new_closing_{{ $index }}')"/>
                                        <span class="text-xs font-semibold text-gray-500">Closed</span>
                                    </label>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block mb-1 text-[10px] font-semibold text-gray-500 uppercase tracking-wide">Opens</label>
                                        <input type="time" id="new_opening_{{ $index }}"
                                            name="hours[{{ $index }}][opening_time]" value="09:00"
                                            class="w-full px-3 py-2 text-sm text-gray-900 bg-white border border-gray-200 dark:border-gray-600 rounded-xl dark:bg-gray-700 dark:text-white">
                                        <input type="hidden" name="hours[{{ $index }}][day_of_week]" value="{{ $day }}">
                                    </div>
                                    <div>
                                        <label class="block mb-1 text-[10px] font-semibold text-gray-500 uppercase tracking-wide">Closes</label>
                                        <input type="time" id="new_closing_{{ $index }}"
                                            name="hours[{{ $index }}][closing_time]" value="18:00"
                                            class="w-full px-3 py-2 text-sm text-gray-900 bg-white border border-gray-200 dark:border-gray-600 rounded-xl dark:bg-gray-700 dark:text-white">
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                </div>

                <div class="px-6 py-4 border-t bg-gray-50 dark:bg-gray-700/50 dark:border-gray-700">
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-600 dark:text-white dark:border-gray-500">
                            Cancel
                        </button>
                        <button type="submit" id="submitBtn"
                            class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg">
                            Create Branch
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- DELETE MODAL --}}
<div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75"></div>
        <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white shadow-xl rounded-2xl dark:bg-gray-800 sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="px-6 py-4 border-b dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Remove Branch</h3>
                    <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="text-xl fa-solid fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="px-6 py-5">
                <div class="flex items-start gap-4">
                    <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-red-100 rounded-full dark:bg-red-900/30">
                        <i class="text-xl text-red-600 fa-solid fa-trash-can dark:text-red-400"></i>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-white" id="deleteBranchName">Remove Branch?</h4>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            This branch will be permanently removed. Make sure it has no assigned users first.
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50 dark:bg-gray-700/50 dark:border-gray-700">
                <button onclick="closeDeleteModal()"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-600 dark:text-white dark:border-gray-500">
                    Cancel
                </button>
                <button onclick="confirmDelete()" id="deleteConfirmBtn"
                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">
                    Remove Branch
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let deleteBranchId = null;
let isSubmitting   = false;
let isManualMode   = false;

const HAS_NO_BRANCHES       = {{ $branches->count() === 0 ? 'true' : 'false' }};
const HAS_REACHED_BRANCH_LIMIT = {{ $hasReachedBranchLimit ? 'true' : 'false' }};

// ── Location toggle
const toggleBtn           = document.getElementById('toggleLocationMode');
const dropdownWrapper     = document.getElementById('locationDropdownWrapper');
const inputWrapper        = document.getElementById('locationInputWrapper');
const locationSelect      = document.getElementById('locationSelect');
const locationManualInput = document.getElementById('locationManualInput');
const locationValue       = document.getElementById('locationValue');

locationSelect.addEventListener('change', () => { locationValue.value = locationSelect.value; });
locationManualInput.addEventListener('input', () => { locationValue.value = locationManualInput.value; });

toggleBtn.addEventListener('click', () => {
    isManualMode = !isManualMode;
    dropdownWrapper.classList.toggle('hidden', isManualMode);
    inputWrapper.classList.toggle('hidden', !isManualMode);
    locationValue.value   = isManualMode ? locationManualInput.value : locationSelect.value;
    toggleBtn.textContent = isManualMode ? 'Pick from list' : 'Type manually';
    if (isManualMode) locationManualInput.focus();
});

// ── Create modal
function openCreateModal() {
    if (HAS_REACHED_BRANCH_LIMIT) {
        showSpaToast('Branch limit reached. Upgrade your plan to add more.', 'error');
        return;
    }
    const form = document.getElementById('branchForm');
    form.reset();
    locationSelect.value = ''; locationManualInput.value = ''; locationValue.value = '';
    isManualMode = false;
    dropdownWrapper.classList.remove('hidden');
    inputWrapper.classList.add('hidden');
    toggleBtn.textContent = 'Type manually';

    const isMainCheckbox = document.getElementById('is_main');
    const isMainLabel    = document.getElementById('is_main_label');
    if (HAS_NO_BRANCHES) {
        isMainCheckbox.checked = true; isMainCheckbox.disabled = true;
        isMainLabel.textContent = 'Set as main branch (required for first branch)';
    } else {
        isMainCheckbox.checked = false; isMainCheckbox.disabled = false;
        isMainLabel.textContent = 'Set as main branch';
    }

    form.action = '{{ route("branches.store") }}';
    document.getElementById('branchModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('branchModal').classList.add('hidden');
    isSubmitting = false;
}

function toggleTimeInputs(checkbox, openingId, closingId) {
    const opening = document.getElementById(openingId);
    const closing = document.getElementById(closingId);
    if (!opening || !closing) return;
    const closed = checkbox.checked;
    opening.disabled = closed; closing.disabled = closed;
    opening.classList.toggle('opacity-50', closed); opening.classList.toggle('cursor-not-allowed', closed);
    closing.classList.toggle('opacity-50', closed); closing.classList.toggle('cursor-not-allowed', closed);
}

// ── Delete modal
function openDeleteModal(branchId, branchName) {
    deleteBranchId = branchId;
    document.getElementById('deleteBranchName').textContent = `Remove "${branchName}"?`;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    deleteBranchId = null;
}

function confirmDelete() {
    if (!deleteBranchId) return;
    const btn = document.getElementById('deleteConfirmBtn');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Removing...';
    btn.disabled  = true;

    fetch(`/branches/${deleteBranchId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        sessionStorage.setItem('toast_type', data.success ? 'success' : 'error');
        sessionStorage.setItem('toast_message', data.message || (data.success ? 'Branch removed.' : 'Failed to remove.'));
        window.location.reload();
    })
    .catch(() => {
        sessionStorage.setItem('toast_type', 'error');
        sessionStorage.setItem('toast_message', 'A network error occurred.');
        window.location.reload();
    });
}

// ── Branch form submission
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('branchForm');
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (HAS_REACHED_BRANCH_LIMIT) { showSpaToast('Branch limit reached.', 'error'); return; }
        if (!locationValue.value.trim()) {
            showSpaToast('Please select or enter a location.', 'error');
            (isManualMode ? locationManualInput : locationSelect).focus();
            return;
        }
        if (isSubmitting) return;
        isSubmitting = true;
        const btn = document.getElementById('submitBtn');
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Creating...';
        btn.disabled  = true;

        try {
            const resp = await fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: new FormData(form),
            });
            const data = await resp.json();
            if (!resp.ok) {
                const msgs = data.errors ? Object.values(data.errors).flat() : [data.message || 'Validation failed.'];
                showSpaToast(msgs[0], 'error');
                return;
            }
            sessionStorage.setItem('toast_type', 'success');
            sessionStorage.setItem('toast_message', data.message || 'Branch created successfully.');
            window.location.reload();
        } catch (err) {
            showSpaToast('An error occurred. Please try again.', 'error');
        } finally {
            btn.innerHTML = original;
            btn.disabled  = false;
            isSubmitting  = false;
        }
    });

    // Show any pending toasts after reload
    const toastType = sessionStorage.getItem('toast_type');
    const toastMsg  = sessionStorage.getItem('toast_message');
    if (toastType && toastMsg) {
        showSpaToast(toastMsg, toastType);
        sessionStorage.removeItem('toast_type');
        sessionStorage.removeItem('toast_message');
    }
});
</script>
@endsection