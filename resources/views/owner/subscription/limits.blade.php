@extends('layouts.app')

@section('title', 'Plan Limits')

@section('content')
@php
    $planName = config("plans.{$planKey}.name", ucfirst($planKey));
    $branchCount = $branches->count();
    $branchesOver = max(0, $branchCount - count($activeBranchIds));
    $activeStaff = count($keepIds);
    $pausedStaff = $staff->count() - $activeStaff;
    $otherBranchSlots = max(0, $branchLimit - 1);

    $roleLabels = [
        'therapist' => 'Therapist',
        'receptionist' => 'Receptionist',
        'manager' => 'Manager',
        'hr' => 'HR',
        'finance' => 'Finance',
    ];

    $blockedLabels = [
        'branch' => 'Branch paused',
        'role' => 'Premium role',
    ];

    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348] disabled:cursor-not-allowed disabled:opacity-50',
        'neutral' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
    ];

    $checkClass = 'w-5 h-5 rounded border-gray-300 text-[#8B7355] focus:ring-[#8B7355] disabled:opacity-40 dark:border-gray-600 dark:bg-gray-700';
    $thClass = 'px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase sm:px-6 dark:text-gray-400';
@endphp

<div class="max-w-6xl p-4 mx-auto space-y-6 sm:p-6">

    <x-page-header
        title="Plan Limits"
        subtitle="Choose which branches and staff stay active when your spa has more than your plan allows."
    />

    {{-- ── Summary ────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Current Plan</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">{{ $planName }}</h3>
                <a href="{{ route('owner.subscription.index') }}" class="text-xs font-semibold text-[#8B7355] underline hover:text-[#6F5430] sm:text-sm dark:text-[#C4A97D]">Change plan</a>
            </div>
        </div>

        <div class="p-4 border shadow-sm sm:p-5 rounded-2xl {{ $branchesOver > 0 ? 'bg-amber-50 border-amber-200 dark:bg-amber-900/10 dark:border-amber-800' : 'bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700' }}">
            <p class="text-xs font-semibold tracking-wide uppercase {{ $branchesOver > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500 dark:text-gray-400' }}">Branches</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">{{ count($activeBranchIds) }} / {{ $branchLimit }}</h3>
                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                    {{ $branchesOver > 0 ? $branchesOver . ' paused' : 'All running' }}
                </span>
            </div>
        </div>

        <div class="col-span-2 p-4 border shadow-sm sm:p-5 rounded-2xl lg:col-span-1 {{ $pausedStaff > 0 ? 'bg-amber-50 border-amber-200 dark:bg-amber-900/10 dark:border-amber-800' : 'bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700' }}">
            <p class="text-xs font-semibold tracking-wide uppercase {{ $pausedStaff > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500 dark:text-gray-400' }}">Active Staff</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">{{ $activeStaff }} / {{ $staffLimit }}</h3>
                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                    {{ $pausedStaff > 0 ? $pausedStaff . ' paused' : 'Within limit' }}
                </span>
            </div>
        </div>
    </div>

    <div class="p-4 text-sm border border-blue-200 rounded-2xl bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-900/10 dark:text-blue-300">
        <p class="font-semibold">How this works</p>
        <ul class="mt-2 space-y-1 list-disc list-inside">
            <li>Nothing is deleted. Paused branches are hidden from customers; paused staff cannot sign in.</li>
            <li>Tick who should stay active. Empty slots are filled automatically: oldest branches, then longest-serving staff.</li>
            <li>Upgrading or freeing a slot brings paused branches and staff back automatically.</li>
            <li>Swapping (pausing someone active to activate someone paused) is allowed once per billing period. Filling a free slot never counts as a swap.</li>
        </ul>
    </div>

    @if ($isOverLimits)
        @if ($canSwap)
            <div class="flex items-start gap-3 p-4 text-sm border rounded-2xl border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/10 dark:text-emerald-300" role="status">
                <i class="mt-0.5 fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                <p>You have <strong>1 swap</strong> available this billing period. Choose carefully: after saving a swap, your choices are fixed until your next period starts.</p>
            </div>
        @else
            <div class="flex items-start gap-3 p-4 text-sm border rounded-2xl border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/10 dark:text-amber-300" role="status">
                <i class="mt-0.5 fa-solid fa-lock" aria-hidden="true"></i>
                <p>
                    You have used this period's swap.
                    @if ($nextSwapAt)
                        Your next swap is available on <strong>{{ $nextSwapAt->format('F d, Y') }}</strong>.
                    @endif
                    You can still fill free slots, or upgrade to run more.
                </p>
            </div>
        @endif
    @endif

    {{-- ── Bookings that need a new therapist ─────────────────────────── --}}
    @if ($affectedBookings->isNotEmpty())
        <section class="overflow-hidden bg-white border shadow-sm border-amber-200 rounded-2xl dark:border-amber-800 dark:bg-gray-800">
            <div class="px-4 py-4 border-b border-amber-200 bg-amber-50 sm:px-6 dark:border-amber-800 dark:bg-amber-900/10">
                <h2 class="text-base font-semibold text-amber-900 dark:text-amber-200">
                    <i class="mr-1 fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    {{ $affectedBookings->count() }} upcoming booking(s) are assigned to paused therapists
                </h2>
                <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                    These bookings were not changed. Reassign them, or keep the therapist active below.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="{{ $thClass }}">Date</th>
                            <th class="{{ $thClass }}">Customer</th>
                            <th class="{{ $thClass }}">Therapist</th>
                            <th class="{{ $thClass }}">Branch</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($affectedBookings as $booking)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900 sm:px-6 dark:text-white">
                                    {{ \Illuminate\Support\Carbon::parse($booking->appointment_date)->format('M d, Y') }}
                                    · {{ \Illuminate\Support\Carbon::parse($booking->start_time)->format('g:i A') }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 sm:px-6 dark:text-gray-300">{{ $booking->customer_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 sm:px-6 dark:text-gray-300">
                                    {{ trim(($booking->therapist?->first_name ?? '') . ' ' . ($booking->therapist?->last_name ?? '')) ?: '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 sm:px-6 dark:text-gray-300">{{ $booking->branch?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-gray-200 sm:px-6 dark:border-gray-700">
                <a href="{{ route('appointments.index') }}" class="{{ $btn['neutral'] }}">
                    <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                    Open Appointments
                </a>
            </div>
        </section>
    @endif

    <form method="POST" action="{{ route('owner.subscription.limits.update') }}" id="planLimitsForm" class="space-y-6">
        @csrf
        @method('PUT')

        @if ($errors->any())
            <div class="px-4 py-3 text-sm text-red-700 border border-red-200 rounded-2xl bg-red-50 dark:border-red-800 dark:bg-red-900/10 dark:text-red-300" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- ── Branches ───────────────────────────────────────────────── --}}
        <section class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-col gap-2 px-4 py-4 border-b border-gray-200 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-gray-700">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Branches</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        The main branch always stays active. Pick up to {{ $otherBranchSlots }} other branch(es).
                    </p>
                </div>
                <span class="text-sm text-gray-500 shrink-0 dark:text-gray-400" aria-live="polite">
                    <span id="branchPicked">0</span> of {{ $otherBranchSlots }} picked
                </span>
            </div>

            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($branches as $branch)
                    @php
                        $isRunning = in_array((int) $branch->id, $activeBranchIds, true);
                    @endphp
                    <li class="flex items-center gap-4 px-4 py-3 sm:px-6">
                        <input
                            type="checkbox"
                            id="branch_{{ $branch->id }}"
                            @if (! $branch->is_main) name="branches[]" value="{{ $branch->id }}" data-group="branch" @endif
                            class="{{ $checkClass }}"
                            @checked($branch->is_main || (old() ? in_array($branch->id, old('branches', [])) : $branch->keep_active))
                            @disabled($branch->is_main)
                        >
                        <label for="branch_{{ $branch->id }}" class="flex flex-col flex-1 min-w-0 gap-1 cursor-pointer sm:flex-row sm:items-center sm:justify-between">
                            <span class="min-w-0">
                                <span class="block font-medium text-gray-900 truncate dark:text-white">
                                    {{ $branch->name }}
                                    @if ($branch->is_main)
                                        <span class="ml-1 px-2 py-0.5 text-[11px] font-semibold rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">Main</span>
                                    @endif
                                </span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $branch->users_count }} staff assigned</span>
                            </span>
                            <span class="shrink-0 px-2.5 py-1 text-xs font-semibold rounded-full w-fit {{ $isRunning ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                                {{ $isRunning ? 'Active' : 'Paused' }}
                            </span>
                        </label>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- ── Staff ──────────────────────────────────────────────────── --}}
        <section class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-col gap-2 px-4 py-4 border-b border-gray-200 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-gray-700">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Staff</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Pick up to {{ $staffLimit }}. Staff in a paused branch, and HR or Finance staff on Basic, stay paused. After changing branches, save first to pick staff from them.
                    </p>
                </div>
                <span class="text-sm text-gray-500 shrink-0 dark:text-gray-400" aria-live="polite">
                    <span id="staffPicked">0</span> of {{ $staffLimit }} picked
                </span>
            </div>

            @if ($staff->isEmpty())
                <p class="px-4 py-8 text-sm text-center text-gray-500 sm:px-6 dark:text-gray-400">No staff yet.</p>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($staff as $member)
                        @php
                            $reason = $blocked[$member->id] ?? null;
                            $isActive = in_array((int) $member->id, $keepIds, true);
                            $role = $member->user?->roles->pluck('name')->first();
                            $name = trim(($member->user?->first_name ?? '') . ' ' . ($member->user?->last_name ?? '')) ?: 'Unnamed staff';
                        @endphp
                        <li class="flex items-center gap-4 px-4 py-3 sm:px-6">
                            <input
                                type="checkbox"
                                id="staff_{{ $member->id }}"
                                name="staff[]"
                                value="{{ $member->id }}"
                                data-group="staff"
                                class="{{ $checkClass }}"
                                @checked(! $reason && (old() ? in_array($member->id, old('staff', [])) : $member->keep_active))
                                @disabled((bool) $reason)
                            >
                            <label for="staff_{{ $member->id }}" class="flex flex-col flex-1 min-w-0 gap-1 cursor-pointer sm:flex-row sm:items-center sm:justify-between">
                                <span class="min-w-0">
                                    <span class="block font-medium text-gray-900 truncate dark:text-white">{{ $name }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">
                                        {{ $roleLabels[$role] ?? ucfirst((string) $role) }} · {{ $member->branch?->name ?? 'No branch' }}
                                    </span>
                                </span>
                                <span class="flex flex-wrap gap-2 shrink-0">
                                    @if ($reason)
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                            {{ $blockedLabels[$reason] }}
                                        </span>
                                    @endif
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $isActive ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' }}">
                                        {{ $isActive ? 'Active' : 'Paused' }}
                                    </span>
                                </span>
                            </label>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <a href="{{ route('owner.subscription.index') }}" class="{{ $btn['neutral'] }} w-full sm:w-auto">Back to Billing</a>
            <button type="submit" id="planLimitsSave" class="{{ $btn['primary'] }} w-full sm:w-auto">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                Save Choices
            </button>
        </div>
    </form>
</div>

<script>
(function () {
    'use strict';

    const LIMITS = { branch: {{ (int) $otherBranchSlots }}, staff: {{ (int) $staffLimit }} };
    const COUNTERS = { branch: document.getElementById('branchPicked'), staff: document.getElementById('staffPicked') };
    const form = document.getElementById('planLimitsForm');
    const save = document.getElementById('planLimitsSave');

    if (!form) return;

    function boxes(group) {
        return Array.from(form.querySelectorAll('input[data-group="' + group + '"]'));
    }

    // Once a group is full, the unticked boxes are disabled so the owner
    // cannot pick more than the plan allows. Boxes disabled by the server
    // (paused branch, Premium role) stay disabled.
    function refresh(group) {
        const all = boxes(group);
        const picked = all.filter(b => b.checked).length;
        const full = picked >= LIMITS[group];

        if (COUNTERS[group]) COUNTERS[group].textContent = picked;

        all.forEach(b => {
            if (b.dataset.serverDisabled === '1') return;
            b.disabled = !b.checked && full;
        });
    }

    ['branch', 'staff'].forEach(group => {
        boxes(group).forEach(b => {
            if (b.disabled) b.dataset.serverDisabled = '1';
            b.addEventListener('change', () => refresh(group));
        });
        refresh(group);
    });

    form.addEventListener('submit', function (e) {
        if (save.disabled) { e.preventDefault(); return; }
        save.disabled = true;
    });
})();
</script>
@endsection
