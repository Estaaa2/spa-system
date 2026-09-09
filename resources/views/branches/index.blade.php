@extends('layouts.app')

@section('title', 'Branches Management')
@section('content')
{{-- Toggled from JS, so it can't be Tailwind classes — the JIT never sees them.
     Same block appointments.blade.php uses for its edit-modal field errors.
     darkMode is 'media', so there is no .dark class on <html> to hook. --}}
<style>
    .has-error { border-color: #ef4444 !important; }
    @media (prefers-color-scheme: dark) {
        .has-error { border-color: #f87171 !important; }
    }
</style>

@php
    $canUseProfessionalSuite = ($spa->business_tier ?? null) === 'professional';
    $branchLimit             = 2;
    $branchCount             = $branches->count();
    $hasUnlimitedBranches    = $canUseProfessionalSuite;
    $hasReachedBranchLimit   = !$hasUnlimitedBranches && $branchCount >= $branchLimit;
    $remainingBranchSlots    = max($branchLimit - $branchCount, 0);

    // Shared button shape. Same base as appointments.blade.php so controls on this
    // page match the rest of the staff side: 44px minimum, rounded-xl, focus-visible ring.
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'primary'  => $btnBase . ' text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#7A6348] hover:to-[#5E4728]',
        'neutral'  => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                    . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'remove'   => $btnBase . ' bg-red-700 text-white hover:bg-red-800',
        'disabled' => $btnBase . ' bg-gray-200 text-gray-400 cursor-not-allowed dark:bg-gray-700 dark:text-gray-500',
    ];
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">

    <x-page-header
        title="Branches"
        subtitle="Manage all branches for your spa, including branch setup, public listing, and optional branch-level features."
    />

    {{-- ── Plan Limit Notice ──────────────────────────────────────────── --}}
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
                <a href="{{ route('owner.subscription.index') }}" class="{{ $btn['primary'] }} w-full shrink-0 md:w-auto">
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    Upgrade Subscription
                </a>
            </div>
        </div>
    @endif

    {{-- ── Stats Row ──────────────────────────────────────────────────── --}}
    {{-- Two-up on phones, three across from lg — same shape as the summary rows on
         appointments and dashboard. The third card spans both columns on mobile so
         it doesn't sit half-width and orphaned on its own row. --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Total Branches</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">{{ $branchCount }}</h3>
                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">All active branches</span>
            </div>
        </div>

        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Branches Listed</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                    {{ $branches->filter(fn($b) => (bool) optional($b->profile)->is_listed)->count() }}
                </h3>
                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">On the public listing</span>
            </div>
        </div>

        <div class="col-span-2 p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl lg:col-span-1 dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                {{ $canUseProfessionalSuite ? 'Suite Enabled' : 'Branch Plan Limit' }}
            </p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                    {{ $canUseProfessionalSuite ? $branches->where('has_workforce_finance_suite', true)->count() : $remainingBranchSlots }}
                </h3>
                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                    {{ $canUseProfessionalSuite ? 'Using Workforce & Finance Suite' : 'Remaining slots on Basic' }}
                </span>
            </div>
        </div>
    </div>

    {{-- ── Branch Directory ────────────────────────────────────────────── --}}
    <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">

        <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Branch Directory</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        View, update, and manage all branches for {{ $spa->name }}.
                    </p>
                </div>

                @if($hasReachedBranchLimit)
                    <button type="button" disabled class="{{ $btn['disabled'] }} w-full shrink-0 md:w-auto">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        Branch Limit Reached
                    </button>
                @else
                    <button type="button" onclick="openCreateModal()" class="{{ $btn['primary'] }} w-full shrink-0 md:w-auto">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        Add New Branch
                    </button>
                @endif
            </div>
        </div>

        @if($branches->count() > 0)

            {{-- Cards grid — matches the roles-permissions page pattern --}}
            <div class="grid gap-4 p-4 sm:p-6 md:grid-cols-2 xl:grid-cols-3">

                @foreach($branches as $branch)
                @php
                    $isListed  = (bool) optional($branch->profile)->is_listed;
                    $hasSuite  = $canUseProfessionalSuite && $branch->has_workforce_finance_suite;
                    $isCurrent = session('current_branch_id') == $branch->id;
                    $canRemove = !$branch->is_main && $branch->users_count == 0;

                    // Shown as visible text in place of the button when removal is blocked,
                    // so the reason reaches touch and screen-reader users too.
                    $removeBlockedReason = $branch->is_main
                        ? 'Main branch — cannot be removed'
                        : 'Has assigned staff — cannot be removed';
                @endphp

                <div class="p-5 transition-colors border rounded-2xl
                {{ $isCurrent
                    ? 'bg-blue-50/80 border-blue-300 dark:bg-blue-900/20 dark:border-blue-600'
                    : 'bg-gray-50/70 border-gray-200 dark:bg-gray-900/20 dark:border-gray-700' }}">

                    {{-- Header: icon + name + status badge --}}
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="flex items-center justify-center w-10 h-10 shrink-0 rounded-xl
                                {{ $branch->is_main ? 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400' : 'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                                <i class="fa-solid {{ $branch->is_main ? 'fa-crown' : 'fa-store' }}" aria-hidden="true"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                    {{ $branch->name }}
                                </h3>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                    <i class="fa-solid fa-location-dot text-[#8B7355] text-xs" aria-hidden="true"></i>
                                    {{ $branch->location }}
                                </p>
                            </div>
                        </div>

                        {{-- Status badge: Main or Currently Viewing --}}
                        @if($branch->is_main)
                            <span class="shrink-0 px-2.5 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                Main Branch
                            </span>
                        @elseif($isCurrent)
                            <span class="shrink-0 px-2.5 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                Currently Viewing
                            </span>
                        @endif
                    </div>

                    {{-- Stats grid — matches roles-permissions mini-stat boxes --}}
                    <div class="grid grid-cols-2 gap-3 mt-5">
                        <div class="p-3 bg-white border border-gray-200 rounded-xl dark:bg-gray-800 dark:border-gray-700">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Staff assigned</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $branch->users_count }}
                            </p>
                        </div>

                        <div class="p-3 bg-white border border-gray-200 rounded-xl dark:bg-gray-800 dark:border-gray-700">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Public listing</p>
                            <p class="mt-1 text-sm font-semibold flex items-center gap-1.5
                                {{ $isListed ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-gray-500' }}">
                                <span class="w-1.5 h-1.5 rounded-full inline-block {{ $isListed ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600' }}" aria-hidden="true"></span>
                                {{ $isListed ? 'Listed' : 'Unlisted' }}
                            </p>
                        </div>
                    </div>

                    {{-- Suite badge (professional only) --}}
                    @if($canUseProfessionalSuite)
                        <div class="mt-3">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-medium rounded-full
                                {{ $hasSuite ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                                <i class="fa-solid fa-briefcase" aria-hidden="true"></i>
                                Suite {{ $hasSuite ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>
                    @endif

                    {{-- Footer: tab action links + remove --}}
                    {{-- The three edit links are always the same width and structure, and the
                         remove row is always one line tall — a button when removal is allowed,
                         a line of explanatory text when it isn't — so card heights stay equal. --}}
                    <div class="pt-4 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700">

                        {{-- Three tab direct-links in a row --}}
                        <div class="grid grid-cols-3 gap-2">
                            <a href="{{ route('branches.edit', $branch->id) }}?tab=general"
                               class="inline-flex items-center justify-center gap-1.5 min-h-[44px] px-2 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-xl transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-600 dark:focus-visible:ring-offset-gray-800">
                                <i class="fa-solid fa-pen text-[#8B7355] text-[11px]" aria-hidden="true"></i>
                                General
                            </a>
                            <a href="{{ route('branches.edit', $branch->id) }}?tab=hours"
                               class="inline-flex items-center justify-center gap-1.5 min-h-[44px] px-2 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-xl transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-600 dark:focus-visible:ring-offset-gray-800">
                                <i class="fa-solid fa-clock text-[#8B7355] text-[11px]" aria-hidden="true"></i>
                                Hours
                            </a>
                            <a href="{{ route('branches.edit', $branch->id) }}?tab=profile"
                               class="inline-flex items-center justify-center gap-1.5 min-h-[44px] px-2 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-xl transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-600 dark:focus-visible:ring-offset-gray-800">
                                <i class="fa-solid fa-image text-[#8B7355] text-[11px]" aria-hidden="true"></i>
                                Profile
                            </a>
                        </div>

                        @if($canRemove)
                            <button type="button"
                                onclick="openDeleteModal({{ $branch->id }}, {{ Js::from($branch->name) }})"
                                class="w-full min-h-[44px] inline-flex items-center justify-center gap-1.5 text-xs font-medium text-red-600 transition-colors rounded-xl hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:text-red-400 dark:hover:bg-red-900/20 dark:focus-visible:ring-offset-gray-800">
                                <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                Remove Branch
                            </button>
                        @else
                            {{-- Not a button. A disabled button can't be focused, so a tooltip
                                 would never reach keyboard, screen-reader or touch users. --}}
                            <p class="w-full min-h-[44px] inline-flex items-center justify-center gap-1.5 text-xs font-medium text-gray-400 dark:text-gray-500">
                                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                {{ $removeBlockedReason }}
                            </p>
                        @endif

                    </div>
                </div>

                @endforeach
            </div>

        @else
            <div class="px-4 text-center py-14 sm:px-6">
                <div class="flex items-center justify-center w-16 h-16 mx-auto text-[#8B7355] bg-[#8B7355]/10 rounded-full">
                    <i class="text-2xl fa-solid fa-store" aria-hidden="true"></i>
                </div>
                <h3 class="mt-4 text-base font-semibold text-gray-900 dark:text-white">No branches yet</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Get started by creating your first branch for this spa.
                </p>
                <div class="mt-6">
                    <button type="button" onclick="openCreateModal()" class="{{ $btn['primary'] }} w-full sm:w-auto">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        Add New Branch
                    </button>
                </div>
            </div>
        @endif

    </div>
</div>

{{-- ════════════════════════════════════════════════════════════════════════
     CREATE BRANCH MODAL
═════════════════════════════════════════════════════════════════════════ --}}
<div id="branchModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="modalTitle"
             class="w-full max-w-2xl bg-white shadow-xl rounded-2xl dark:bg-gray-800">

            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div>
                    <h2 id="modalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Add New Branch</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Create a new branch and set its initial operating setup.</p>
                </div>
                <button type="button" onclick="closeModal()" aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200 dark:focus-visible:ring-offset-gray-800">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <form id="branchForm" method="POST" action="{{ route('branches.store') }}">
                @csrf

                <div class="px-4 py-6 space-y-6 sm:px-6">

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Branch Name *</label>
                        <input type="text" id="name" name="name" required
                               aria-describedby="name_help name_error"
                               class="block w-full mt-2 min-h-[44px] border-gray-300 rounded-xl shadow-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                        <p id="name_error" class="hidden mt-1 text-xs font-medium text-red-600 dark:text-red-400"></p>
                        <p id="name_help" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Give your branch a clear and recognizable name.</p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="locationSelect" class="text-sm font-medium text-gray-700 dark:text-gray-300">Location / City *</label>
                            {{-- -my-3 keeps the 44px tap target without making this label row
                                 taller than a plain label. Same trick as appointments' phone field. --}}
                            <button type="button" id="toggleLocationMode"
                                class="-my-3 inline-flex items-center min-h-[44px] px-2 text-[11px] font-semibold text-[#8B7355] underline transition-colors rounded-xl hover:text-[#6F5430] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800">
                                Type manually
                            </button>
                        </div>

                        <div id="locationDropdownWrapper">
                            <select id="locationSelect" aria-describedby="location_help location_error"
                                class="block w-full mt-1 min-h-[44px] border-gray-300 rounded-xl shadow-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
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
                            <label for="locationManualInput" class="sr-only">Location / City, typed manually</label>
                            <input type="text" id="locationManualInput" aria-describedby="location_help location_error"
                                class="block w-full min-h-[44px] border-gray-300 rounded-xl shadow-sm focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                placeholder="Type city / area manually"/>
                        </div>

                        <input type="hidden" name="location" id="locationValue"/>
                        <p id="location_error" class="hidden mt-1 text-xs font-medium text-red-600 dark:text-red-400"></p>
                        <p id="location_help" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Choose from the list or type a more specific location manually.</p>
                    </div>

                    <div>
                        <div class="flex items-center">
                            <input type="hidden" name="is_main" value="0" id="is_main_hidden">
                            <input type="checkbox" id="is_main" name="is_main"
                                   class="w-4 h-4 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355] dark:bg-gray-700 dark:border-gray-600">
                            <label for="is_main" id="is_main_label" class="block ml-2 text-sm text-gray-700 dark:text-gray-300">
                                Set as main branch
                            </label>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">The main branch acts as the spa's primary location. Only one branch can be marked as main at a time.</p>
                    </div>

                    <div>
                        <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Operating Hours</h3>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @php $daysOfWeek = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday']; @endphp
                            @foreach($daysOfWeek as $index => $day)
                            <div class="p-4 bg-white shadow-sm dark:bg-gray-800 rounded-2xl ring-1 ring-black/5 dark:ring-white/10" id="new_card_{{ $index }}">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $day }}</h4>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="hidden" name="hours[{{ $index }}][is_closed]" value="0" />
                                        <input type="checkbox"
                                            name="hours[{{ $index }}][is_closed]"
                                            value="1"
                                            data-closed-toggle="{{ $index }}"
                                            class="w-4 h-4 rounded text-[#8B7355] border-gray-300 focus:ring-[#8B7355]/40"
                                            onchange="toggleTimeInputs(this, 'new_opening_{{ $index }}', 'new_closing_{{ $index }}')"/>
                                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-300">Closed</span>
                                    </label>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="new_opening_{{ $index }}" class="block mb-1 text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Opens</label>
                                        <input type="time" id="new_opening_{{ $index }}"
                                            name="hours[{{ $index }}][opening_time]" value="09:00"
                                            class="w-full min-h-[44px] px-3 py-2 text-sm text-gray-900 bg-white border border-gray-200 dark:border-gray-600 rounded-xl dark:bg-gray-700 dark:text-white">
                                        <input type="hidden" name="hours[{{ $index }}][day_of_week]" value="{{ $day }}">
                                    </div>
                                    <div>
                                        <label for="new_closing_{{ $index }}" class="block mb-1 text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Closes</label>
                                        <input type="time" id="new_closing_{{ $index }}"
                                            name="hours[{{ $index }}][closing_time]" value="18:00"
                                            class="w-full min-h-[44px] px-3 py-2 text-sm text-gray-900 bg-white border border-gray-200 dark:border-gray-600 rounded-xl dark:bg-gray-700 dark:text-white">
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                </div>

                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 sm:px-6 rounded-b-2xl dark:bg-gray-700/50 dark:border-gray-700">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeModal()" class="{{ $btn['neutral'] }} w-full sm:w-auto">
                            Cancel
                        </button>
                        <button type="submit" id="submitBtn" class="{{ $btn['primary'] }} w-full sm:w-auto">
                            Create Branch
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Remove Confirmation Modal ──────────────────────────────────────────── --}}
<div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="alertdialog" aria-modal="true" aria-labelledby="deleteModalTitle" aria-describedby="deleteModalDesc"
             class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <h2 id="deleteModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Remove Branch</h2>
            <p id="deleteBranchName" class="mt-2 text-sm font-medium text-gray-900 dark:text-white"></p>
            <p id="deleteModalDesc" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                This branch will be removed from active branch lists. Its record is kept, so
                bookings and reports that already reference it are not lost.
            </p>
            <p class="mt-2 text-sm font-medium text-amber-600 dark:text-amber-400">
                Make sure this branch has no assigned staff before removing it.
            </p>
            <div class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">
                <button type="button" onclick="closeDeleteModal()" class="{{ $btn['neutral'] }} w-full sm:w-auto">
                    Keep Branch
                </button>
                <button type="button" onclick="confirmDelete()" id="deleteConfirmBtn" class="{{ $btn['remove'] }} w-full sm:w-auto">
                    Yes, Remove
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Everything lives inside one IIFE so this page's helpers can't collide with a
// same-named function on the layout or an included partial. Functions that inline
// onclick= attributes call are attached to window at the bottom.
(function () {
    'use strict';

    let deleteBranchId = null;
    let isSubmitting   = false;
    let isManualMode   = false;

    const HAS_NO_BRANCHES          = {{ $branches->count() === 0 ? 'true' : 'false' }};
    const HAS_REACHED_BRANCH_LIMIT = {{ $hasReachedBranchLimit ? 'true' : 'false' }};
    const CSRF_TOKEN               = @json(csrf_token());

    // Named route with a placeholder id, so this survives the route group moving.
    const ROUTE_DELETE = @json(route('branches.destroy', '__ID__'));
    function routeFor(template, id) { return template.replace('__ID__', encodeURIComponent(id)); }

    // showSpaToast is defined elsewhere (welcome.js) and is not guaranteed to be
    // loaded on the staff layout. Without this fallback, the first toast call
    // throws inside the submit handler and the whole create flow dies silently.
    function notify(message, type) {
        if (typeof window.showSpaToast === 'function') {
            window.showSpaToast(message, type);
        } else {
            window.alert(message);
        }
    }

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


    // ── Inline field errors ───────────────────────────────────────────────
    // Maps a server validation key to the input(s) it should mark and the <p>
    // that shows the message. Anything not listed here still reaches the user
    // through the toast fallback in showErrors().
    const ERROR_FIELDS = {
        name:     { inputs: ['name'],                                 target: 'name_error' },
        location: { inputs: ['locationSelect', 'locationManualInput'], target: 'location_error' },
    };

    function clearErrors() {
        Object.values(ERROR_FIELDS).forEach(field => {
            const p = document.getElementById(field.target);
            if (p) { p.textContent = ''; p.classList.add('hidden'); }
            field.inputs.forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;
                el.removeAttribute('aria-invalid');
                el.classList.remove('has-error');
            });
        });
    }

    function showErrors(errors) {
        clearErrors();
        let firstInvalid = null;
        const unmapped   = [];

        for (const key in errors) {
            const message = Array.isArray(errors[key]) ? errors[key][0] : String(errors[key]);
            // "hours.3.opening_time" and "hours" both belong to the hours block.
            const field = ERROR_FIELDS[key] || ERROR_FIELDS[key.split('.')[0]];

            if (!field) { unmapped.push(message); continue; }

            const p = document.getElementById(field.target);
            if (p) { p.textContent = message; p.classList.remove('hidden'); }

            field.inputs.forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;
                el.setAttribute('aria-invalid', 'true');
                el.classList.add('has-error');
                if (!firstInvalid && el.offsetParent !== null) firstInvalid = el;
            });
        }

        if (unmapped.length) notify(unmapped.join(' '), 'error');
        if (firstInvalid) {
            firstInvalid.focus();
            firstInvalid.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }


    // ── Modal plumbing ────────────────────────────────────────────────────
    // Same contract as appointments.blade.php: Escape closes, Tab stays inside,
    // focus returns to whatever opened the dialog.
    const MODAL_IDS = ['branchModal', 'deleteModal'];
    const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    let lastFocused = null;

    function topmostOpenModal() {
        const open = MODAL_IDS
            .map(id => document.getElementById(id))
            .filter(el => el && !el.classList.contains('hidden'));
        return open.length ? open[open.length - 1] : null;
    }

    function openModal(id, focusSelector) {
        const el = document.getElementById(id);
        if (!el) return;
        lastFocused = document.activeElement;
        el.classList.remove('hidden');
        const target = (focusSelector && el.querySelector(focusSelector)) || el.querySelector(FOCUSABLE);
        if (target) target.focus();
    }

    function closeModal_(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('hidden');
        if (lastFocused && document.contains(lastFocused)) lastFocused.focus();
        lastFocused = null;
    }

    document.addEventListener('keydown', function (e) {
        const modal = topmostOpenModal();
        if (!modal) return;

        if (e.key === 'Escape') {
            e.preventDefault();
            if (modal.id === 'branchModal') closeModal(); else closeDeleteModal();
            return;
        }
        if (e.key !== 'Tab') return;

        const items = Array.from(modal.querySelectorAll(FOCUSABLE)).filter(el => el.offsetParent !== null);
        if (!items.length) return;
        const first = items[0];
        const last  = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault(); last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault(); first.focus();
        }
    });

    // Backdrop click closes the confirm dialog only. The create modal holds a long
    // typed form, and a stray tap on a phone would discard it with no warning.
    (function () {
        const el = document.getElementById('deleteModal');
        if (!el) return;
        el.addEventListener('click', e => { if (e.target === el) closeDeleteModal(); });
    })();


    // ── Create modal ──────────────────────────────────────────────────────
    function openCreateModal() {
        if (HAS_REACHED_BRANCH_LIMIT) {
            notify('Your Basic plan allows only up to 2 branches. Upgrade your subscription to add more.', 'error');
            return;
        }

        const form = document.getElementById('branchForm');
        form.reset();
        clearErrors();

        locationSelect.value = ''; locationManualInput.value = ''; locationValue.value = '';
        isManualMode = false;
        dropdownWrapper.classList.remove('hidden');
        inputWrapper.classList.add('hidden');
        toggleBtn.textContent = 'Type manually';

        // form.reset() unchecks the "Closed" boxes but fires no change event, so the
        // readonly/greyed styling from the previous open would stay behind. Re-run
        // the toggle for every day to put the time fields back in sync.
        document.querySelectorAll('[data-closed-toggle]').forEach(cb => {
            const i = cb.getAttribute('data-closed-toggle');
            toggleTimeInputs(cb, 'new_opening_' + i, 'new_closing_' + i);
        });

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

        openModal('branchModal', '#name');
    }

    function toggleTimeInputs(checkbox, openingId, closingId) {
        const opening = document.getElementById(openingId);
        const closing = document.getElementById(closingId);
        if (!opening || !closing) return;
        if (checkbox.checked) {
            opening.setAttribute('readonly', true); closing.setAttribute('readonly', true);
            opening.classList.add('opacity-50', 'cursor-not-allowed');
            closing.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            opening.removeAttribute('readonly'); closing.removeAttribute('readonly');
            opening.classList.remove('opacity-50', 'cursor-not-allowed');
            closing.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    function closeModal() {
        closeModal_('branchModal');
        isSubmitting = false;
    }


    // ── Remove modal ──────────────────────────────────────────────────────
    function openDeleteModal(branchId, branchName) {
        deleteBranchId = branchId;
        document.getElementById('deleteBranchName').textContent = 'Remove "' + branchName + '"?';
        openModal('deleteModal', '#deleteConfirmBtn');
    }

    function closeDeleteModal() {
        closeModal_('deleteModal');
        deleteBranchId = null;
    }

    function confirmDelete() {
        if (!deleteBranchId) return;
        const button = document.getElementById('deleteConfirmBtn');
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Removing...';
        button.disabled  = true;

        fetch(routeFor(ROUTE_DELETE, deleteBranchId), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            sessionStorage.setItem('toast_type',    data.success ? 'success' : 'error');
            sessionStorage.setItem('toast_message', data.message || (data.success ? 'Branch removed.' : 'Failed to remove.'));
            window.location.reload();
        })
        .catch(() => {
            sessionStorage.setItem('toast_type', 'error');
            sessionStorage.setItem('toast_message', 'Remove error');
            window.location.reload();
        });
    }


    // ── Submit ────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('branchForm');

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            if (HAS_REACHED_BRANCH_LIMIT) {
                notify('Your Basic plan allows only up to 2 branches. Upgrade your subscription to add more.', 'error');
                return;
            }

            if (!locationValue.value.trim()) {
                showErrors({ location: ['Please select or enter a location.'] });
                return;
            }

            if (isSubmitting) return;
            isSubmitting = true;
            clearErrors();

            const button       = document.getElementById('submitBtn');
            const originalText = button.innerHTML;
            button.innerHTML   = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving...';
            button.disabled    = true;

            const formData = new FormData(form);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: formData,
                });
                const data = await response.json();

                if (!response.ok) {
                    if (data.errors) {
                        showErrors(data.errors);
                    } else {
                        notify(data.message || 'Validation failed.', 'error');
                    }
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

        // Show any pending toasts after reload
        const toastType = sessionStorage.getItem('toast_type');
        const toastMsg  = sessionStorage.getItem('toast_message');
        if (toastType && toastMsg) {
            notify(toastMsg, toastType);
            sessionStorage.removeItem('toast_type');
            sessionStorage.removeItem('toast_message');
        }
    });


    // Inline onclick= attributes in the markup above resolve against window.
    window.openCreateModal   = openCreateModal;
    window.closeModal        = closeModal;
    window.toggleTimeInputs  = toggleTimeInputs;
    window.openDeleteModal   = openDeleteModal;
    window.closeDeleteModal  = closeDeleteModal;
    window.confirmDelete     = confirmDelete;
})();
</script>
@endsection