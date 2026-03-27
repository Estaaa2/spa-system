@extends('layouts.app')

@section('title', 'Branches Management')

@section('content')
@php
    $canUseProfessionalSuite = ($spa->business_tier ?? null) === 'professional';

    $branchLimit = 2;
    $branchCount = $branches->count();
    $hasUnlimitedBranches = $canUseProfessionalSuite;
    $hasReachedBranchLimit = !$hasUnlimitedBranches && $branchCount >= $branchLimit;
    $remainingBranchSlots = max($branchLimit - $branchCount, 0);
@endphp

<div class="p-6 space-y-6">
    <x-page-header
        title="Branches"
        subtitle="Manage all branches for your spa, including branch setup, public listing, and optional branch-level features."
    />

    {{-- Basic Plan Branch Limit Notice --}}
    @if(!$hasUnlimitedBranches)
        <div class="p-4 border border-amber-200 rounded-2xl bg-amber-50 dark:bg-amber-900/10 dark:border-amber-800">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-sm font-semibold tracking-wide uppercase text-amber-800 dark:text-amber-300">
                        Basic Plan Branch Limit
                    </h2>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                        Your spa can only have up to <span class="font-semibold">{{ $branchLimit }}</span> branches on the Basic plan.
                        @if($hasReachedBranchLimit)
                            You have already reached the limit.
                        @else
                            You still have <span class="font-semibold">{{ $remainingBranchSlots }}</span> branch slot(s) remaining.
                        @endif
                    </p>
                </div>

                <a href="{{ route('owner.subscription.index') }}"
                   class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg focus:ring-4 focus:ring-[#8B7355]/30">
                    <i class="mr-2 fa-solid fa-arrow-up-right-from-square"></i>
                    Upgrade Subscription
                </a>
            </div>
        </div>
    @endif

    {{-- Current Branch Info Card --}}
    @if(session('current_branch_id'))
        @php
            $currentBranch = Auth::user()->spa->branches->firstWhere('id', session('current_branch_id'));
        @endphp
        @if($currentBranch)
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-start gap-4">
                <div class="flex items-center justify-center w-12 h-12 text-white rounded-full bg-[#8B7355]">
                    <i class="fas fa-location-dot"></i>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Currently Viewing Branch</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        <span class="font-medium">{{ $currentBranch->name }}</span>
                        <span class="mx-1 text-gray-400">•</span>
                        {{ $currentBranch->location }}
                    </p>
                    <p class="mt-2 text-xs text-blue-600 dark:text-blue-400">
                        Use the branch switcher in the sidebar to change your current branch context.
                    </p>
                </div>
            </div>
        </div>
        @endif
    @endif

    {{-- Branch Overview --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Branches</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $branchCount }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">All active branches under this spa</p>
        </div>

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Branches Listed</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                {{ $branches->filter(fn($branch) => (bool) optional($branch->profile)->is_listed)->count() }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Branches currently visible on the public listing
            </p>
        </div>

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ $canUseProfessionalSuite ? 'Suite Enabled' : 'Branch Plan Limit' }}
            </p>
            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                {{ $canUseProfessionalSuite ? $branches->where('has_workforce_finance_suite', true)->count() : $remainingBranchSlots }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ $canUseProfessionalSuite
                    ? 'Branches using Workforce & Finance Suite'
                    : 'Remaining branch slots on your Basic plan' }}
            </p>
        </div>
    </div>

    {{-- Branch Directory --}}
    <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-6 py-5 border-b dark:border-gray-700">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Branch Directory</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        View, update, and organize all branches for {{ Auth::user()->spa->name }}.
                    </p>
                </div>

                @if($hasReachedBranchLimit)
                    <button type="button"
                            disabled
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-400 bg-gray-200 rounded-lg cursor-not-allowed dark:bg-gray-700 dark:text-gray-500">
                        <i class="mr-2 fa-solid fa-lock"></i>
                        Branch Limit Reached
                    </button>
                @else
                    <button onclick="openCreateModal()"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg focus:ring-4 focus:ring-[#8B7355]/30">
                        <i class="mr-2 fa-solid fa-plus"></i>
                        Add New Branch
                    </button>
                @endif
            </div>
        </div>

        @if($branches->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Branch</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Location</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Users</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Workforce & Finance Suite</th>
                            <th class="px-6 py-3 text-xs font-medium text-center text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @foreach($branches as $branch)
                            <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center w-10 h-10 text-white rounded-full bg-[#8B7355]">
                                            @if($branch->is_main)
                                                <i class="fas fa-crown"></i>
                                            @else
                                                <i class="fas fa-store"></i>
                                            @endif
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">
                                                {{ $branch->name }}
                                            </p>

                                            <div class="flex flex-wrap gap-2 mt-1">
                                                @if($branch->is_main)
                                                    <span class="px-2 py-1 text-[11px] font-medium text-yellow-700 bg-yellow-100 rounded-full dark:bg-yellow-900/30 dark:text-yellow-300">
                                                        Main Branch
                                                    </span>
                                                @endif

                                                @if(session('current_branch_id') == $branch->id)
                                                    <span class="px-2 py-1 text-[11px] font-medium text-blue-600 bg-blue-100 rounded-full dark:bg-blue-900/30 dark:text-blue-300">
                                                        Current
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $branch->location }}</span>
                                </td>

                                <td class="px-6 py-4">
                                    <span class="px-3 py-1.5 text-xs font-medium rounded-full
                                        {{ $branch->users_count > 0 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                        {{ $branch->users_count }} {{ Str::plural('user', $branch->users_count) }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    @if(($spa->business_tier ?? null) === 'professional')
                                        @if($branch->has_workforce_finance_suite)
                                            <span class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-100 rounded-full dark:bg-indigo-900/30 dark:text-indigo-300">
                                                Enabled
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-300">
                                                Disabled
                                            </span>
                                        @endif
                                    @else
                                        <span class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-yellow-700 bg-yellow-100 rounded-full dark:bg-yellow-900/30 dark:text-yellow-300">
                                            For Professional Only
                                        </span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-center">
                                    <div class="flex justify-center gap-2">
                                        <a href="{{ route('branches.edit', $branch->id) }}"
                                            class="px-3 py-1.5 text-sm font-medium text-white bg-yellow-500 rounded-lg hover:bg-yellow-600"
                                            title="Edit">
                                            Edit
                                        </a>

                                        @if(!$branch->is_main && $branch->users_count == 0)
                                            <button onclick="deleteBranch({{ $branch->id }}, '{{ addslashes($branch->name) }}')"
                                                    class="px-3 py-1.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700"
                                                    title="Remove">
                                                Remove
                                            </button>
                                        @else
                                            <button disabled
                                                    class="px-3 py-1.5 text-sm text-gray-400 bg-gray-200 rounded-lg cursor-not-allowed dark:bg-gray-700 dark:text-gray-500"
                                                    title="{{ $branch->is_main ? 'Cannot remove main branch' : 'Branch has users assigned' }}">
                                                Remove
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col items-center justify-between gap-3 sm:flex-row">
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Showing <span class="font-medium">{{ $branchCount }}</span> branches
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                        <span class="inline-flex items-center">
                            <i class="mr-1 text-yellow-500 fa-solid fa-crown"></i>
                            Main: {{ $branches->where('is_main', true)->count() }}
                        </span>
                        <span class="inline-flex items-center">
                            <i class="mr-1 text-gray-400 fa-solid fa-store"></i>
                            Regular: {{ $branches->where('is_main', false)->count() }}
                        </span>
                        @if($canUseProfessionalSuite)
                            <span class="inline-flex items-center">
                                <i class="mr-1 text-indigo-500 fa-solid fa-briefcase"></i>
                                Suite Enabled: {{ $branches->where('has_workforce_finance_suite', true)->count() }}
                            </span>
                        @else
                            <span class="inline-flex items-center">
                                <i class="mr-1 text-amber-500 fa-solid fa-layer-group"></i>
                                Remaining Slots: {{ $remainingBranchSlots }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="px-6 text-center py-14">
                <div class="flex items-center justify-center w-16 h-16 mx-auto text-[#8B7355] bg-[#8B7355]/10 rounded-full">
                    <i class="text-2xl fa-solid fa-store"></i>
                </div>
                <h3 class="mt-4 text-base font-semibold text-gray-900 dark:text-white">No branches yet</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Get started by creating your first branch for this spa.
                </p>
                <div class="mt-6">
                    <button onclick="openCreateModal()"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg focus:ring-4 focus:ring-[#8B7355]/30">
                        <i class="mr-2 fa-solid fa-plus"></i>
                        Add New Branch
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Create Branch Modal -->
<div id="branchModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75"></div>

        <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white shadow-xl rounded-2xl dark:bg-gray-800 sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <form id="branchForm" method="POST">
                @csrf

                <div class="px-6 py-4 bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 id="modalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">
                                Add New Branch
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Create a new branch and set its initial operating setup.
                            </p>
                        </div>
                        <button type="button" onclick="closeModal()"
                                class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                            <i class="text-xl fa-solid fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="px-6 py-5 space-y-6">
                    <div class="space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Branch Name *
                            </label>
                            <input type="text" id="name" name="name" required
                                   class="block w-full mt-2 border-gray-300 rounded-xl shadow-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Give your branch a clear and recognizable name.
                            </p>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Location / City *</label>
                                <button type="button" id="toggleLocationMode"
                                    class="text-[10px] font-semibold text-[#8B7355] hover:text-[#6F5430] transition underline">
                                    Type manually
                                </button>
                            </div>

                            <div id="locationDropdownWrapper">
                                <select id="locationSelect"
                                    class="block w-full mt-1 border-gray-300 rounded-xl shadow-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                                    <option value="">Select by city or location.</option>
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
                                    placeholder="Type city / area manually"/>
                            </div>

                            <input type="hidden" name="location" id="locationValue"/>

                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Choose from the list or type a more specific location manually.
                            </p>
                        </div>

                        <div class="flex items-center">
                            <input type="hidden" name="is_main" value="0" id="is_main_hidden">
                            <input type="checkbox" id="is_main" name="is_main"
                                   class="w-4 h-4 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355] dark:bg-gray-700 dark:border-gray-600">
                            <label for="is_main" id="is_main_label" class="block ml-2 text-sm text-gray-700 dark:text-gray-300">
                                Set as main branch
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            The main branch acts as the spa’s primary location. Only one branch can be marked as main at a time.
                        </p>
                    </div>

                    <div>
                        <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Operating Hours</h3>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            @php
                                $daysOfWeek = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                            @endphp

                            @foreach($daysOfWeek as $index => $day)
                            <div class="p-4 bg-white shadow-sm dark:bg-gray-800 rounded-2xl ring-1 ring-black/5 dark:ring-white/10" id="new_card_{{ $index }}">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $day }}</h4>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="hidden" name="hours[{{ $index }}][is_closed]" value="0" />
                                        <input type="checkbox"
                                            name="hours[{{ $index }}][is_closed]"
                                            value="1"
                                            class="w-4 h-4 rounded text-[#8B7355] border-gray-300 focus:ring-[#8B7355]/40"
                                            onchange="toggleTimeInputs(this, 'new_opening_{{ $index }}', 'new_closing_{{ $index }}')"
                                        />
                                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-300">Closed</span>
                                    </label>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block mb-1 text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Opens</label>
                                        <input type="time"
                                            id="new_opening_{{ $index }}"
                                            name="hours[{{ $index }}][opening_time]"
                                            value="09:00"
                                            class="w-full px-3 py-2 text-sm text-gray-900 bg-white border border-gray-200 dark:border-gray-600 rounded-xl dark:bg-gray-700 dark:text-white">
                                        <input type="hidden" name="hours[{{ $index }}][day_of_week]" value="{{ $day }}">
                                    </div>
                                    <div>
                                        <label class="block mb-1 text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Closes</label>
                                        <input type="time"
                                            id="new_closing_{{ $index }}"
                                            name="hours[{{ $index }}][closing_time]"
                                            value="18:00"
                                            class="w-full px-3 py-2 text-sm text-gray-900 bg-white border border-gray-200 dark:border-gray-600 rounded-xl dark:bg-gray-700 dark:text-white">
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t bg-gray-50 dark:bg-gray-700/50 dark:border-gray-700">
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 transition-colors bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-600 dark:border-gray-500 dark:text-white dark:hover:bg-gray-500">
                            Cancel
                        </button>
                        <button type="submit" id="submitBtn"
                                class="px-4 py-2 text-sm font-medium text-white transition-colors bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg focus:ring-4 focus:ring-[#8B7355]/30">
                            Save Branch
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Remove Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75"></div>

        <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white shadow-xl rounded-2xl dark:bg-gray-800 sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="px-6 py-4 bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Remove Branch
                    </h3>
                    <button type="button" onclick="closeDeleteModal()"
                            class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <i class="text-xl fa-solid fa-times"></i>
                    </button>
                </div>
            </div>

            <div class="px-6 py-5">
                <div class="flex items-start">
                    <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-red-100 rounded-full dark:bg-red-900/30">
                        <i class="text-xl text-red-600 fa-solid fa-box-archive dark:text-red-400"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-lg font-medium text-gray-900 dark:text-white" id="deleteBranchName">
                            Remove Branch?
                        </h4>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            This branch will be removed from active branch lists.
                        </p>
                        <p class="mt-2 text-sm font-medium text-amber-600 dark:text-amber-400">
                            Make sure this branch has no assigned users before removing it.
                        </p>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-t bg-gray-50 dark:bg-gray-700/50 dark:border-gray-700">
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeDeleteModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 transition-colors bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-600 dark:border-gray-500 dark:text-white dark:hover:bg-gray-500">
                        Cancel
                    </button>
                    <button type="button" onclick="confirmDelete()" id="deleteConfirmBtn"
                            class="px-4 py-2 text-sm font-medium text-white transition-colors bg-red-600 rounded-lg hover:bg-red-700 focus:ring-4 focus:ring-red-300 dark:bg-red-700 dark:hover:bg-red-600">
                        Remove Branch
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentBranchId = null;
let deleteBranchId  = null;
let isSubmitting    = false;
let isEditMode      = false;
let isManualMode    = false;

const HAS_NO_BRANCHES = {{ $branches->count() === 0 ? 'true' : 'false' }};
const HAS_REACHED_BRANCH_LIMIT = {{ $hasReachedBranchLimit ? 'true' : 'false' }};

const toggleBtn           = document.getElementById('toggleLocationMode');
const dropdownWrapper     = document.getElementById('locationDropdownWrapper');
const inputWrapper        = document.getElementById('locationInputWrapper');
const locationSelect      = document.getElementById('locationSelect');
const locationManualInput = document.getElementById('locationManualInput');
const locationValue       = document.getElementById('locationValue');

locationSelect.addEventListener('change', () => {
    locationValue.value = locationSelect.value;
});

locationManualInput.addEventListener('input', () => {
    locationValue.value = locationManualInput.value;
});

toggleBtn.addEventListener('click', () => {
    isManualMode = !isManualMode;

    if (isManualMode) {
        dropdownWrapper.classList.add('hidden');
        inputWrapper.classList.remove('hidden');
        locationManualInput.focus();
        locationValue.value   = locationManualInput.value;
        toggleBtn.textContent = 'Pick from list';
    } else {
        dropdownWrapper.classList.remove('hidden');
        inputWrapper.classList.add('hidden');
        locationValue.value   = locationSelect.value;
        toggleBtn.textContent = 'Type manually';
    }
});

function openCreateModal() {
    if (HAS_REACHED_BRANCH_LIMIT) {
        showSpaToast('Your Basic plan allows only up to 2 branches. Upgrade your subscription to add more.', 'error');
        return;
    }

    isEditMode = false;

    const form = document.getElementById('branchForm');
    form.reset();

    locationSelect.value      = '';
    locationManualInput.value = '';
    locationValue.value       = '';
    isManualMode              = false;
    dropdownWrapper.classList.remove('hidden');
    inputWrapper.classList.add('hidden');
    toggleBtn.textContent = 'Type manually';

    document.getElementById('modalTitle').textContent = 'Add New Branch';
    form.action = '{{ route("branches.store") }}';

    const isMainCheckbox = document.getElementById('is_main');
    const isMainLabel    = document.getElementById('is_main_label');

    if (HAS_NO_BRANCHES) {
        isMainCheckbox.checked  = true;
        isMainCheckbox.disabled = true;
        if (isMainLabel) isMainLabel.textContent = 'Set as main branch (required for first branch)';
    } else {
        isMainCheckbox.checked  = false;
        isMainCheckbox.disabled = false;
        if (isMainLabel) isMainLabel.textContent = 'Set as main branch';
    }

    document.getElementById('submitBtn').textContent = 'Create Branch';
    document.getElementById('branchModal').classList.remove('hidden');
}

function toggleTimeInputs(checkbox, openingId, closingId) {
    const opening = document.getElementById(openingId);
    const closing = document.getElementById(closingId);

    if (checkbox.checked) {
        opening.setAttribute('readonly', true);
        closing.setAttribute('readonly', true);
        opening.classList.add('opacity-50', 'cursor-not-allowed');
        closing.classList.add('opacity-50', 'cursor-not-allowed');
    } else {
        opening.removeAttribute('readonly');
        closing.removeAttribute('readonly');
        opening.classList.remove('opacity-50', 'cursor-not-allowed');
        closing.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}

function closeModal() {
    document.getElementById('branchModal').classList.add('hidden');
    currentBranchId = null;
    isSubmitting    = false;
}

function deleteBranch(branchId, branchName) {
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

    const button       = document.getElementById('deleteConfirmBtn');
    const originalText = button.innerHTML;
    button.innerHTML   = '<i class="fa-solid fa-spinner fa-spin"></i> Archiving...';
    button.disabled    = true;

    fetch(`/branches/${deleteBranchId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            sessionStorage.setItem('toast_type', 'success');
            sessionStorage.setItem('toast_message', 'Branch removed successfully');
            window.location.reload();
        } else {
            sessionStorage.setItem('toast_type', 'error');
            sessionStorage.setItem('toast_message', data.message || 'Failed to remove branch');
            window.location.reload();
        }
    })
    .catch(() => {
        sessionStorage.setItem('toast_type', 'error');
        sessionStorage.setItem('toast_message', 'Remove error');
        window.location.reload();
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled  = false;
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('branchForm');

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        if (HAS_REACHED_BRANCH_LIMIT) {
            showSpaToast('Your Basic plan allows only up to 2 branches. Upgrade your subscription to add more.', 'error');
            return;
        }

        if (!locationValue.value.trim()) {
            if (isManualMode) {
                locationManualInput.focus();
            } else {
                locationSelect.focus();
            }
            showSpaToast('Please select or enter a location.', 'error');
            return;
        }

        if (isSubmitting) return;
        isSubmitting = true;

        const button       = document.getElementById('submitBtn');
        const originalText = button.innerHTML;
        button.innerHTML   = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        button.disabled    = true;

        const formData = new FormData(form);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: formData,
            });

            const data = await response.json();

            if (!response.ok) {
                let msgs = [];
                if (data.errors) {
                    for (const key in data.errors) msgs.push(data.errors[key][0]);
                } else {
                    msgs.push(data.message || 'Validation failed.');
                }
                showSpaToast(msgs.join(' '), 'error');
                return;
            }

            if (data.success) {
                sessionStorage.setItem('toast_type', 'success');
                sessionStorage.setItem('toast_message', data.message || 'Saved successfully');
                window.location.reload();
            } else {
                sessionStorage.setItem('toast_type', 'error');
                sessionStorage.setItem('toast_message', data.message || 'Operation failed');
                window.location.reload();
            }

        } catch (err) {
            console.error(err);
            sessionStorage.setItem('toast_type', 'error');
            sessionStorage.setItem('toast_message', 'An error occurred. Please try again.');
            window.location.reload();
        } finally {
            button.innerHTML = originalText;
            button.disabled  = false;
            isSubmitting     = false;
        }
    });
});
</script>

@endsection
