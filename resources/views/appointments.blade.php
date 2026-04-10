@extends('layouts.app')

@section('content')
@php
    $user = auth()->user();

    $canEdit    = $user?->hasBranchPermission('edit appointments') ?? false;
    $canDelete  = $user?->hasBranchPermission('delete appointments') ?? false;
    $showActions = $canEdit || $canDelete;

    $statusClasses = [
        'reserved'  => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'pending'   => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'ongoing'   => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'completed' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
        'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    ];

    $sourceClasses = [
        'online'  => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
        'walk_in' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        'staff'   => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        ''        => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    ];
@endphp

<div class="p-6 mx-auto space-y-6 max-w-7xl">

    {{-- ── Header + live status bar ── --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-full
                    bg-white border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700
                    text-gray-500 dark:text-gray-400 select-none">
            <span id="liveIndicatorDot"
                  class="inline-block w-2 h-2 rounded-full bg-gray-300 dark:bg-gray-600 transition-colors duration-300"></span>
            <span id="liveIndicatorLabel">Connecting…</span>
        </div>
    </div>

    <x-page-header
        title="Appointments"
        subtitle="Monitor bookings, process arrivals, and track payments for today’s operations."
    />

    {{-- ── Summary cards ── --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Today's Appointments</p>
            <div class="flex items-end justify-between mt-3">
                <h3 id="stat-today" class="text-3xl font-semibold text-gray-900 dark:text-white">{{ $summary['today_total'] }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Scheduled today</span>
            </div>
        </div>

        <div class="p-5 border shadow-sm bg-amber-50 border-amber-200 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800">
            <p class="text-xs font-semibold tracking-wide uppercase text-amber-700 dark:text-amber-300">Needs Action</p>
            <div class="flex items-end justify-between mt-3">
                <h3 id="stat-pending" class="text-3xl font-semibold text-amber-900 dark:text-amber-200">{{ $summary['pending_today'] }}</h3>
                <span class="text-sm text-amber-700 dark:text-amber-300">Pending check-ins</span>
            </div>
        </div>

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Upcoming</p>
            <div class="flex items-end justify-between mt-3">
                <h3 id="stat-upcoming" class="text-3xl font-semibold text-gray-900 dark:text-white">{{ $summary['upcoming_total'] }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Future reservations</span>
            </div>
        </div>

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Collected Today</p>
            <div class="flex items-end justify-between mt-3">
                <h3 id="stat-collected" class="text-3xl font-semibold text-gray-900 dark:text-white">
                    ₱{{ number_format($summary['collected_today'], 2) }}
                </h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Recorded payments</span>
            </div>
        </div>
    </div>

    {{-- ── Needs Attention (hidden when no pending appointments) ── --}}
    <div id="needsAttentionSection"
         class="{{ $todayPending->count() === 0 ? 'hidden' : '' }}
                overflow-hidden bg-white border shadow-sm border-amber-200 rounded-2xl
                dark:bg-gray-800 dark:border-amber-800">

        <div class="flex items-center justify-between px-6 py-4 border-b border-amber-200
                    bg-amber-50 dark:border-amber-800 dark:bg-amber-900/10">
            <div>
                <h2 class="text-lg font-semibold text-amber-900 dark:text-amber-200">Needs Attention Right Now</h2>
                <p class="text-sm text-amber-700 dark:text-amber-300">
                    Pending appointments should be processed first — mark them as ongoing or cancelled and record payment received.
                </p>
            </div>
            <span id="pendingBadge"
                  class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full
                         bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                {{ $todayPending->total() }} Pending
            </span>
        </div>

        <div class="p-6">
            <div id="pendingList" class="space-y-4">
                @forelse($todayPending as $booking)
                    <div class="p-4 border rounded-2xl border-amber-200 bg-amber-50/60
                                dark:border-amber-800 dark:bg-amber-900/10">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="grid flex-1 grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Customer</p>
                                    <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $booking->customer_name ?? 'Walk-in Customer' }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $booking->customer_phone ?? 'No contact number' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Service</p>
                                    <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $booking->treatment_label }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $booking->service_type_label }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Schedule</p>
                                    <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $booking->appointment_date?->format('M d, Y') }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ \Carbon\Carbon::parse($booking->start_time)->format('h:i A') }}
                                        – {{ \Carbon\Carbon::parse($booking->end_time)->format('h:i A') }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Payment</p>
                                    <p class="mt-1 font-medium text-gray-900 dark:text-white">
                                        Paid: ₱{{ number_format($booking->resolved_amount_paid, 2) }}
                                    </p>
                                    <p class="text-sm text-amber-700 dark:text-amber-300">
                                        Remaining: ₱{{ number_format($booking->resolved_balance_amount, 2) }}
                                    </p>
                                </div>
                            </div>

                            @if($canEdit)
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full
                                                 {{ $sourceClasses[$booking->booking_source ?? ''] ?? $sourceClasses[''] }}">
                                        {{ strtoupper($booking->booking_source ?: 'STAFF') }}
                                    </span>
                                    <button type="button"
                                            onclick="openProcessModal(this)"
                                            data-id="{{ $booking->id }}"
                                            data-customer="{{ $booking->customer_name }}"
                                            data-treatment="{{ $booking->treatment_label }}"
                                            data-source="{{ $booking->booking_source }}"
                                            data-total="{{ $booking->resolved_total_amount }}"
                                            data-paid="{{ $booking->resolved_amount_paid }}"
                                            data-due="{{ $booking->resolved_balance_amount }}"
                                            data-status="{{ $booking->status }}"
                                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white
                                                   rounded-xl bg-amber-600 hover:bg-amber-700">
                                        Process Now
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    {{-- empty state kept for server-render, JS hides the whole section instead --}}
                @endforelse
            </div>
        </div>
    </div>

    {{-- ── Today's Appointments ── --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Today's Appointments</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Operational view for all appointments scheduled for today.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Customer</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Service</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Therapist</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Time</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Payment</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                        @if($showActions)
                            <th class="px-6 py-3 text-xs font-medium text-center text-gray-500 uppercase">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody id="todayTbody" class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                    @forelse($todayAppointments as $booking)
                        <tr data-booking-id="{{ $booking->id }}"
                            class="hover:bg-gray-50 dark:hover:bg-gray-900/40
                                   {{ $booking->status === 'pending' ? 'bg-amber-50/50 dark:bg-amber-900/10' : '' }}">
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $booking->customer_name ?? 'Walk-in Customer' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $booking->customer_email ?? 'No email' }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $booking->treatment_label }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $booking->service_type_label }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ $booking->therapist->name ?? 'Not Assigned' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ \Carbon\Carbon::parse($booking->start_time)->format('h:i A') }}
                                – {{ \Carbon\Carbon::parse($booking->end_time)->format('h:i A') }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    <p>Total: ₱{{ number_format($booking->resolved_total_amount, 2) }}</p>
                                    <p>Paid: ₱{{ number_format($booking->resolved_amount_paid, 2) }}</p>
                                    <p class="{{ $booking->resolved_balance_amount > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-700 dark:text-emerald-300' }}">
                                        Due: ₱{{ number_format($booking->resolved_balance_amount, 2) }}
                                    </p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-2">
                                    <span class="inline-flex w-fit items-center px-3 py-1 text-xs font-medium rounded-full
                                                 {{ $statusClasses[$booking->status] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ ucfirst($booking->status) }}
                                    </span>
                                    <span class="inline-flex w-fit items-center px-3 py-1 text-xs font-medium rounded-full
                                                 {{ $sourceClasses[$booking->booking_source ?? ''] ?? $sourceClasses[''] }}">
                                        {{ strtoupper($booking->booking_source ?: 'STAFF') }}
                                    </span>
                                </div>
                            </td>
                            @if($showActions)
                                <td class="px-6 py-4 text-center">
                                    <div class="flex flex-wrap justify-center gap-2">
                                        @if($canEdit)
                                            @if($booking->status === 'pending')
                                                <button type="button"
                                                        onclick="openProcessModal(this)"
                                                        data-id="{{ $booking->id }}"
                                                        data-customer="{{ $booking->customer_name }}"
                                                        data-treatment="{{ $booking->treatment_label }}"
                                                        data-source="{{ $booking->booking_source }}"
                                                        data-total="{{ $booking->resolved_total_amount }}"
                                                        data-paid="{{ $booking->resolved_amount_paid }}"
                                                        data-due="{{ $booking->resolved_balance_amount }}"
                                                        data-status="{{ $booking->status }}"
                                                        class="px-3 py-1.5 text-sm text-white bg-amber-600 rounded-lg hover:bg-amber-700">
                                                    Process
                                                </button>
                                            @endif
                                            @if($booking->status === 'ongoing')
                                                <button type="button"
                                                        onclick="openProcessModal(this)"
                                                        data-id="{{ $booking->id }}"
                                                        data-customer="{{ $booking->customer_name }}"
                                                        data-treatment="{{ $booking->treatment_label }}"
                                                        data-source="{{ $booking->booking_source }}"
                                                        data-total="{{ $booking->resolved_total_amount }}"
                                                        data-paid="{{ $booking->resolved_amount_paid }}"
                                                        data-due="{{ $booking->resolved_balance_amount }}"
                                                        data-status="{{ $booking->status }}"
                                                        class="px-3 py-1.5 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700">
                                                    Cancel
                                                </button>
                                            @endif
                                            <button type="button"
                                                    onclick="openEditModal(this)"
                                                    data-id="{{ $booking->id }}"
                                                    data-customer-name="{{ $booking->customer_name }}"
                                                    data-customer-email="{{ $booking->customer_email }}"
                                                    data-customer-phone="{{ $booking->customer_phone }}"
                                                    data-customer-address="{{ $booking->customer_address }}"
                                                    data-service-type="{{ $booking->service_type }}"
                                                    data-treatment="{{ $booking->treatment }}"
                                                    data-therapist-id="{{ $booking->therapist_id }}"
                                                    data-branch-id="{{ $booking->branch_id }}"
                                                    data-appointment-date="{{ $booking->appointment_date?->format('Y-m-d') }}"
                                                    data-start-time="{{ $booking->start_time }}"
                                                    data-status="{{ $booking->status }}"
                                                    class="px-3 py-1 text-sm text-white bg-yellow-500 rounded hover:bg-yellow-600">
                                                Edit
                                            </button>
                                        @endif
                                        @if($canDelete)
                                            <button onclick="openDeleteModal({{ $booking->id }})"
                                                    class="px-3 py-1 text-sm text-white bg-red-600 rounded hover:bg-red-700">
                                                Remove
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr id="todayEmptyRow">
                            <td colspan="{{ $showActions ? 7 : 6 }}"
                                class="px-6 py-10 text-sm text-center text-gray-500 dark:text-gray-400">
                                No appointments scheduled for today.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Upcoming Reservations ── --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Upcoming Reservations</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Future bookings that are already lined up for the branch.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Customer</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Service</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Therapist</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                        @if($showActions)
                            <th class="px-6 py-3 text-xs font-medium text-center text-gray-500 uppercase">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody id="upcomingTbody" class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                    @forelse($upcomingAppointments as $booking)
                        <tr data-booking-id="{{ $booking->id }}"
                            class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                {{ $booking->customer_name ?? 'Walk-in Customer' }}
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $booking->treatment_label }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $booking->service_type_label }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ $booking->appointment_date?->format('M d, Y') }}
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ \Carbon\Carbon::parse($booking->start_time)->format('h:i A') }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ $booking->therapist->name ?? 'Not Assigned' }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full
                                             {{ $statusClasses[$booking->status] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ ucfirst($booking->status) }}
                                </span>
                            </td>
                            @if($showActions)
                                <td class="px-6 py-4 text-center">
                                    <div class="flex flex-wrap justify-center gap-2">
                                        @if($canEdit)
                                            <button type="button"
                                                    onclick="openEditModal(this)"
                                                    data-id="{{ $booking->id }}"
                                                    data-customer-name="{{ $booking->customer_name }}"
                                                    data-customer-email="{{ $booking->customer_email }}"
                                                    data-customer-phone="{{ $booking->customer_phone }}"
                                                    data-customer-address="{{ $booking->customer_address }}"
                                                    data-service-type="{{ $booking->service_type }}"
                                                    data-treatment="{{ $booking->treatment }}"
                                                    data-therapist-id="{{ $booking->therapist_id }}"
                                                    data-branch-id="{{ $booking->branch_id }}"
                                                    data-appointment-date="{{ $booking->appointment_date?->format('Y-m-d') }}"
                                                    data-start-time="{{ $booking->start_time }}"
                                                    data-status="{{ $booking->status }}"
                                                    class="px-3 py-1.5 text-sm text-white bg-yellow-500 rounded-lg hover:bg-yellow-600">
                                                Edit
                                            </button>
                                        @endif
                                        @if($canDelete)
                                            <button onclick="openDeleteModal({{ $booking->id }})"
                                                    class="px-3 py-1.5 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700">
                                                Remove
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr id="upcomingEmptyRow">
                            <td colspan="{{ $showActions ? 6 : 5 }}"
                                class="px-6 py-10 text-sm text-center text-gray-500 dark:text-gray-400">
                                No upcoming reservations found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── History (static — no live updates needed) ── --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">History</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Completed, cancelled, and past records for reference.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Customer</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Service</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Payment</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                    @forelse($historyAppointments as $booking)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td class="px-6 py-4">
                                <p class="font-medium text-gray-900 dark:text-white">{{ $booking->customer_name ?? 'Walk-in Customer' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $booking->customer_email ?? 'No email' }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $booking->treatment_label }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $booking->service_type_label }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ $booking->appointment_date?->format('M d, Y') }}
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ \Carbon\Carbon::parse($booking->start_time)->format('h:i A') }}
                                    – {{ \Carbon\Carbon::parse($booking->end_time)->format('h:i A') }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                <div>Total: ₱{{ number_format($booking->resolved_total_amount, 2) }}</div>
                                <div>Paid: ₱{{ number_format($booking->resolved_amount_paid, 2) }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full
                                             {{ $statusClasses[$booking->status] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ ucfirst($booking->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-sm text-center text-gray-500 dark:text-gray-400">
                                No historical records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $historyAppointments->links() }}
        </div>
    </div>

</div>{{-- end max-w-7xl --}}


{{-- ═══════════════════════════════════════════════════
     PROCESS MODAL (unchanged from original)
     ═══════════════════════════════════════════════════ --}}
@if($canEdit)
<div id="processModal" class="fixed inset-0 z-50 hidden p-4 bg-black/50">
    <div class="w-full max-w-lg mx-auto mt-16 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Process Appointment</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Record customer payment and continue the appointment flow.</p>
            </div>
            <button type="button" onclick="closeProcessModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">✕</button>
        </div>
        <form id="processForm" method="POST" class="px-6 py-6 space-y-4">
            @csrf
            @method('PUT')
            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-900/40">
                <p class="text-sm font-semibold text-gray-900 dark:text-white" id="process_customer"></p>
                <p class="text-sm text-gray-500 dark:text-gray-400" id="process_treatment"></p>
                <div class="grid grid-cols-3 gap-3 mt-3 text-sm">
                    <div>
                        <p class="text-gray-500 dark:text-gray-400">Total</p>
                        <p class="font-medium text-gray-900 dark:text-white" id="process_total"></p>
                    </div>
                    <div>
                        <p class="text-gray-500 dark:text-gray-400">Already Paid</p>
                        <p class="font-medium text-gray-900 dark:text-white" id="process_paid"></p>
                    </div>
                    <div>
                        <p class="text-gray-500 dark:text-gray-400">Remaining</p>
                        <p class="font-medium text-amber-700 dark:text-amber-300" id="process_due"></p>
                    </div>
                </div>
            </div>
            <div>
                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Next Status</label>
                <select id="process_status" name="status"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="ongoing">Mark as Ongoing</option>
                    <option value="cancelled">Cancel Appointment</option>
                </select>
            </div>
            <div id="process_amount_wrapper">
                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Amount Collected Now</label>
                <input type="number" step="0.01" min="0" id="process_amount_paid" name="amount_paid"
                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                <p id="process_hint" class="mt-2 text-xs text-gray-500 dark:text-gray-400"></p>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeProcessModal()"
                        class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                    Close
                </button>
                <button type="submit"
                        class="rounded-xl bg-[#8B7355] px-4 py-2 text-sm font-medium text-white hover:bg-[#7A6348]">
                    Save Action
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════
     EDIT MODAL (unchanged from original)
     ═══════════════════════════════════════════════════ --}}
@if($canEdit)
@php
    $allTreatments = \App\Models\Treatment::orderBy('name')->get();
    $allPackages   = \App\Models\Package::orderBy('name')->get();
@endphp
<div id="editModal" class="fixed inset-0 z-50 hidden p-4 bg-black/50">
    <div class="w-full max-w-2xl mx-auto mt-12 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Edit Appointment</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Update appointment details, therapist assignment, and schedule.</p>
            </div>
            <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">✕</button>
        </div>
        <form id="editForm" method="POST" class="px-6 py-6 space-y-4">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <input type="hidden" id="edit_status" name="status">
                <div class="md:col-span-2">
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Customer Name</label>
                    <input type="text" id="edit_customer_name" name="customer_name"
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Customer Email</label>
                    <input type="email" id="edit_customer_email" name="customer_email"
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Customer Phone</label>
                    <input type="text" id="edit_customer_phone" name="customer_phone"
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>
                <div class="md:col-span-2">
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Customer Address</label>
                    <input type="text" id="edit_customer_address" name="customer_address"
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Service Type</label>
                    <select id="edit_service_type" name="service_type"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="in_branch">In Branch</option>
                        <option value="in_home">In Home</option>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Treatment / Package</label>
                    <select id="edit_treatment" name="treatment"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        @if($allTreatments->isNotEmpty())
                            <optgroup label="Treatments">
                                @foreach($allTreatments as $treatment)
                                    <option value="treatment_{{ $treatment->id }}">{{ $treatment->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if($allPackages->isNotEmpty())
                            <optgroup label="Packages">
                                @foreach($allPackages as $package)
                                    <option value="package_{{ $package->id }}">{{ $package->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Therapist</label>
                    <select id="edit_therapist_id" name="therapist_id"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">— No Therapist Assigned —</option>
                        @foreach($therapists as $therapist)
                            <option value="{{ $therapist->id }}" data-branch="{{ $therapist->branch_id }}">
                                {{ $therapist->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Appointment Date</label>
                    <input type="date" id="edit_appointment_date" name="appointment_date"
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Start Time</label>
                    <input type="time" id="edit_start_time" name="start_time"
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeEditModal()"
                        class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                    Cancel
                </button>
                <button type="submit"
                        class="rounded-xl bg-[#8B7355] px-4 py-2 text-sm font-medium text-white hover:bg-[#7A6348]">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════
     DELETE MODAL (unchanged from original)
     ═══════════════════════════════════════════════════ --}}
@if($canDelete)
<div id="deleteModal" class="fixed inset-0 z-50 hidden p-4 bg-black/50">
    <div class="w-full max-w-md p-6 mx-auto mt-24 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Remove Appointment</h2>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">This will remove the selected appointment record.</p>
        <div class="flex justify-end gap-2 mt-6">
            <button type="button" onclick="closeDeleteModal()"
                    class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                Cancel
            </button>
            <form id="deleteForm" method="POST">
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


{{-- ═══════════════════════════════════════════════════
     SCRIPTS
     ═══════════════════════════════════════════════════ --}}
<script>

// ── Permission flags passed from Blade to JS ──────────────────────────────
const CAN_EDIT   = {{ $canEdit   ? 'true' : 'false' }};
const CAN_DELETE = {{ $canDelete ? 'true' : 'false' }};
const SHOW_ACTIONS = CAN_EDIT || CAN_DELETE;
const TODAY_COLS  = SHOW_ACTIONS ? 7 : 6;
const UPCOMING_COLS = SHOW_ACTIONS ? 6 : 5;

// ── Status / source class maps (mirrors PHP $statusClasses) ───────────────
const STATUS_CLASSES = {
    reserved:  'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    pending:   'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    ongoing:   'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    completed: 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
};
const SOURCE_CLASSES = {
    online:  'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
    walk_in: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    staff:   'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    '':      'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
};

function srcClass(src) { return SOURCE_CLASSES[src] ?? SOURCE_CLASSES['']; }
function stClass(st)   { return STATUS_CLASSES[st]  ?? 'bg-gray-100 text-gray-700'; }
function esc(s)        { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
function php(n)        { return '₱' + Number(n).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}); }


// ════════════════════════════════════════════════════════════════
// EXISTING MODAL JS (process / edit / delete — preserved exactly)
// ════════════════════════════════════════════════════════════════

@if($canEdit)
function openProcessModal(btn) {
    const d = btn.dataset;
    document.getElementById('process_customer').textContent  = d.customer  || 'Customer';
    document.getElementById('process_treatment').textContent = d.treatment || '';
    document.getElementById('process_total').textContent     = '₱' + Number(d.total || 0).toFixed(2);
    document.getElementById('process_paid').textContent      = '₱' + Number(d.paid  || 0).toFixed(2);
    document.getElementById('process_due').textContent       = '₱' + Number(d.due   || 0).toFixed(2);

    const amountInput   = document.getElementById('process_amount_paid');
    const hint          = document.getElementById('process_hint');
    const statusSelect  = document.getElementById('process_status');
    const amountWrapper = document.getElementById('process_amount_wrapper');
    const currentStatus = d.status || 'pending';

    amountInput.value = Number(d.due || 0) > 0 ? Number(d.due || 0).toFixed(2) : '';

    statusSelect.innerHTML = '';
    if (currentStatus === 'pending') {
        statusSelect.innerHTML = `
            <option value="ongoing">Mark as Ongoing (customer has arrived)</option>
            <option value="cancelled">Cancel (customer called / no-show)</option>`;
    } else if (currentStatus === 'ongoing') {
        statusSelect.innerHTML = `
            <option value="cancelled">Cancel (customer did not proceed / no-show)</option>`;
    }

    hint.textContent = (d.source || '') === 'online'
        ? 'This online booking already paid a deposit. Record only the remaining balance collected at the branch.'
        : 'Record the amount collected directly by the receptionist for this appointment.';

    const togglePaymentField = () => {
        amountWrapper.style.display = statusSelect.value === 'cancelled' ? 'none' : 'block';
    };
    statusSelect.onchange = togglePaymentField;
    togglePaymentField();

    document.getElementById('processForm').action = '/appointments/' + d.id + '/status';
    document.getElementById('processModal').classList.remove('hidden');
}
function closeProcessModal() { document.getElementById('processModal').classList.add('hidden'); }

function clearEditErrors() {
    document.querySelectorAll('.edit-field-error').forEach(e => e.remove());
    document.querySelectorAll('.has-error').forEach(i => i.classList.remove('has-error','border-red-500','dark:border-red-500'));
}
function showFieldError(fieldId, message) {
    const existing = document.getElementById(`error-${fieldId}`);
    if (existing) existing.remove();
    const field = document.getElementById(fieldId);
    if (field) {
        field.classList.add('has-error','border-red-500','dark:border-red-500');
        const div = document.createElement('div');
        div.id = `error-${fieldId}`;
        div.className = 'edit-field-error text-red-600 text-xs mt-1 dark:text-red-400';
        div.textContent = message;
        field.parentNode.insertBefore(div, field.nextSibling);
    }
}
function validateAppointmentDate() {
    const statusSelect     = document.getElementById('edit_status');
    const appointmentDate  = document.getElementById('edit_appointment_date').value;
    const today            = new Date().toISOString().split('T')[0];
    clearEditErrors();
    if (!appointmentDate) { showFieldError('edit_appointment_date','Please select an appointment date.'); return false; }
    if (['completed','ongoing'].includes(statusSelect.value) && appointmentDate > today) {
        showFieldError('edit_status', `Cannot mark as "${statusSelect.value}" for a future date.`);
        statusSelect.value = 'reserved';
        return false;
    }
    return true;
}
function showConfirmationDialog(title, message, onConfirm, onCancel) {
    const existing = document.getElementById('confirmationModal');
    if (existing) existing.remove();
    document.body.insertAdjacentHTML('beforeend', `
        <div id="confirmationModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black bg-opacity-50">
            <div class="w-full max-w-md bg-white rounded-lg shadow-xl dark:bg-gray-800">
                <div class="px-6 py-4 border-b dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">${title}</h3>
                </div>
                <div class="px-6 py-4"><p class="text-sm text-gray-600 dark:text-gray-300">${message}</p></div>
                <div class="flex justify-end gap-3 px-6 py-4 rounded-b-lg bg-gray-50 dark:bg-gray-700/50">
                    <button id="confirmCancelBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-600 dark:text-white dark:border-gray-500">Cancel</button>
                    <button id="confirmOkBtn" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">Yes, Complete</button>
                </div>
            </div>
        </div>`);
    const modal = document.getElementById('confirmationModal');
    document.getElementById('confirmCancelBtn').onclick = () => { modal.remove(); if(onCancel) onCancel(); };
    document.getElementById('confirmOkBtn').onclick     = () => { modal.remove(); if(onConfirm) onConfirm(); };
    modal.onclick = e => { if(e.target===modal){ modal.remove(); if(onCancel) onCancel(); } };
    return false;
}
function openEditModal(btn) {
    const d = btn.dataset;
    clearEditErrors();
    document.getElementById('edit_customer_name').value    = d.customerName    || '';
    document.getElementById('edit_customer_email').value   = d.customerEmail   || '';
    document.getElementById('edit_customer_phone').value   = d.customerPhone   || '';
    document.getElementById('edit_customer_address').value = d.customerAddress || '';
    document.getElementById('edit_service_type').value     = d.serviceType     || '';
    document.getElementById('edit_treatment').value        = d.treatment       || '';
    document.getElementById('edit_start_time').value       = d.startTime       || '';
    document.getElementById('edit_status').value           = d.status          || '';
    const dateInput = document.getElementById('edit_appointment_date');
    dateInput.min   = new Date().toISOString().split('T')[0];
    dateInput.value = d.appointmentDate || '';
    const therapistSelect = document.getElementById('edit_therapist_id');
    Array.from(therapistSelect.options).forEach(opt => {
        if (opt.value === '') return;
        opt.hidden = opt.dataset.branch != d.branchId;
    });
    therapistSelect.value = d.therapistId || '';
    document.getElementById('editForm').action = '/appointments/' + d.id;
    document.getElementById('editModal').classList.remove('hidden');
    attachEditEventListeners();
}
function attachEditEventListeners() {
    const statusSelect = document.getElementById('edit_status');
    const dateInput    = document.getElementById('edit_appointment_date');
    const editForm     = document.getElementById('editForm');
    if (window._editStatusListener) {
        statusSelect?.removeEventListener('change', window._editStatusListener);
        dateInput?.removeEventListener('change', window._editDateListener);
        editForm?.removeEventListener('submit', window._editSubmitListener);
    }
    window._editStatusListener  = () => { clearEditErrors(); validateAppointmentDate(); };
    window._editDateListener    = () => { clearEditErrors(); validateAppointmentDate(); };
    window._editSubmitListener  = e  => { clearEditErrors(); if(!validateAppointmentDate()){ e.preventDefault(); return false; } };
    statusSelect?.addEventListener('change', window._editStatusListener);
    dateInput?.addEventListener('change',    window._editDateListener);
    editForm?.addEventListener('submit',     window._editSubmitListener);
    editForm?.querySelectorAll('input, select').forEach(inp => inp.addEventListener('focus', clearEditErrors));
}
function closeEditModal() { document.getElementById('editModal').classList.add('hidden'); clearEditErrors(); }
@endif

@if($canDelete)
function openDeleteModal(id) {
    document.getElementById('deleteForm').action = '/appointments/' + id;
    document.getElementById('deleteModal').classList.remove('hidden');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.add('hidden'); }
@endif

document.addEventListener('DOMContentLoaded', function () {
    @if($canEdit) attachEditEventListeners(); @endif
});


// ════════════════════════════════════════════════════════════════
// LIVE POLLING
// ════════════════════════════════════════════════════════════════
(function () {
    const POLL_INTERVAL_MS = 20000; // 20 seconds
    const LIVE_URL         = '{{ route('appointments.live-data') }}';

    const dot   = document.getElementById('liveIndicatorDot');
    const label = document.getElementById('liveIndicatorLabel');

    let lastUpdatedAt  = null;  // Date object
    let prevStatuses   = {};    // { id: status } — detect changes for row flash
    let isModalOpen    = false; // pause table rebuilds while a modal is open
    let tickTimer      = null;

    // Track modal open state so we don't rebuild rows while staff has a form open
    document.querySelectorAll('[id$="Modal"]').forEach(el => {
        const obs = new MutationObserver(() => {
            isModalOpen = document.querySelectorAll(
                '#processModal:not(.hidden), #editModal:not(.hidden), #deleteModal:not(.hidden)'
            ).length > 0;
        });
        obs.observe(el, { attributes: true, attributeFilter: ['class'] });
    });

    // ── Helpers ──────────────────────────────────────────────────────────────
    function setLiveStatus(state) {
        // state: 'ok' | 'error' | 'connecting'
        const map = {
            ok:         { dot: 'bg-emerald-400',  label: 'Live'           },
            error:      { dot: 'bg-red-400',       label: 'Reconnecting…' },
            connecting: { dot: 'bg-gray-300 dark:bg-gray-600', label: 'Connecting…' },
        };
        const s = map[state] ?? map.connecting;
        dot.className   = `inline-block w-2 h-2 rounded-full transition-colors duration-300 ${s.dot}`;
        label.textContent = s.label;
    }

    function timeAgo(date) {
        const sec = Math.round((Date.now() - date.getTime()) / 1000);
        if (sec < 10)  return 'just now';
        if (sec < 60)  return `${sec}s ago`;
        return `${Math.round(sec/60)}m ago`;
    }

    function startTickTimer() {
        if (tickTimer) clearInterval(tickTimer);
        tickTimer = setInterval(() => {
            if (lastUpdatedAt) label.textContent = `Updated ${timeAgo(lastUpdatedAt)}`;
        }, 10000);
    }

    // ── Update summary cards ─────────────────────────────────────────────────
    function updateSummary(s) {
        document.getElementById('stat-today').textContent     = s.today_total;
        document.getElementById('stat-pending').textContent   = s.pending_today;
        document.getElementById('stat-upcoming').textContent  = s.upcoming_total;
        document.getElementById('stat-collected').textContent = php(s.collected_today);
    }

    // ── Build HTML helpers ────────────────────────────────────────────────────
    function processBtn(b) {
        if (!CAN_EDIT) return '';
        if (b.status === 'pending') {
            return `<button type="button" onclick="openProcessModal(this)"
                data-id="${b.id}" data-customer="${esc(b.customer_name)}"
                data-treatment="${esc(b.treatment_label)}" data-source="${esc(b.booking_source)}"
                data-total="${b.resolved_total_amount}" data-paid="${b.resolved_amount_paid}"
                data-due="${b.resolved_balance_amount}" data-status="${b.status}"
                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white rounded-xl bg-amber-600 hover:bg-amber-700">
                Process Now
            </button>`;
        }
        return '';
    }

    function editBtn(b) {
        if (!CAN_EDIT) return '';
        return `<button type="button" onclick="openEditModal(this)"
            data-id="${b.id}"
            data-customer-name="${esc(b.customer_name)}"
            data-customer-email="${esc(b.customer_email)}"
            data-customer-phone="${esc(b.customer_phone)}"
            data-customer-address="${esc(b.customer_address)}"
            data-service-type="${esc(b.service_type)}"
            data-treatment="${esc(b.treatment)}"
            data-therapist-id="${b.therapist_id ?? ''}"
            data-branch-id="${b.branch_id ?? ''}"
            data-appointment-date="${esc(b.appointment_date_raw)}"
            data-start-time="${esc(b.start_time)}"
            data-status="${b.status}"
            class="px-3 py-1 text-sm text-white bg-yellow-500 rounded hover:bg-yellow-600">
            Edit
        </button>`;
    }

    function deleteBtn(id) {
        if (!CAN_DELETE) return '';
        return `<button onclick="openDeleteModal(${id})"
            class="px-3 py-1 text-sm text-white bg-red-600 rounded hover:bg-red-700">
            Remove
        </button>`;
    }

    // ── Rebuild "Needs Attention" ─────────────────────────────────────────────
    function updateNeedsAttention(pending) {
        const section = document.getElementById('needsAttentionSection');
        const list    = document.getElementById('pendingList');
        const badge   = document.getElementById('pendingBadge');

        if (!pending.length) {
            section.classList.add('hidden');
            return;
        }

        section.classList.remove('hidden');
        badge.textContent = `${pending.length} Pending`;

        list.innerHTML = pending.map(b => `
            <div class="p-4 border rounded-2xl border-amber-200 bg-amber-50/60 dark:border-amber-800 dark:bg-amber-900/10">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="grid flex-1 grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Customer</p>
                            <p class="mt-1 font-medium text-gray-900 dark:text-white">${esc(b.customer_name)}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">${esc(b.customer_phone) || 'No contact number'}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Service</p>
                            <p class="mt-1 font-medium text-gray-900 dark:text-white">${esc(b.treatment_label)}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">${esc(b.service_type_label)}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Schedule</p>
                            <p class="mt-1 font-medium text-gray-900 dark:text-white">${esc(b.appointment_date)}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">${esc(b.start_time_fmt)} – ${esc(b.end_time_fmt)}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Payment</p>
                            <p class="mt-1 font-medium text-gray-900 dark:text-white">Paid: ${php(b.resolved_amount_paid)}</p>
                            <p class="text-sm text-amber-700 dark:text-amber-300">Remaining: ${php(b.resolved_balance_amount)}</p>
                        </div>
                    </div>
                    ${CAN_EDIT ? `
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full ${srcClass(b.booking_source)}">
                            ${(b.booking_source || 'STAFF').toUpperCase()}
                        </span>
                        ${processBtn(b)}
                    </div>` : ''}
                </div>
            </div>`).join('');
    }

    // ── Rebuild Today table ───────────────────────────────────────────────────
    function updateTodayTable(appointments) {
        const tbody = document.getElementById('todayTbody');
        if (!appointments.length) {
            tbody.innerHTML = `<tr id="todayEmptyRow">
                <td colspan="${TODAY_COLS}" class="px-6 py-10 text-sm text-center text-gray-500 dark:text-gray-400">
                    No appointments scheduled for today.
                </td></tr>`;
            return;
        }

        tbody.innerHTML = appointments.map(b => {
            const isPending   = b.status === 'pending';
            const rowBg       = isPending ? 'bg-amber-50/50 dark:bg-amber-900/10' : '';
            const dueColor    = b.resolved_balance_amount > 0
                ? 'text-amber-700 dark:text-amber-300'
                : 'text-emerald-700 dark:text-emerald-300';

            const actionButtons = SHOW_ACTIONS ? `
                <td class="px-6 py-4 text-center">
                    <div class="flex flex-wrap justify-center gap-2">
                        ${CAN_EDIT && isPending ? `
                            <button type="button" onclick="openProcessModal(this)"
                                data-id="${b.id}" data-customer="${esc(b.customer_name)}"
                                data-treatment="${esc(b.treatment_label)}" data-source="${esc(b.booking_source)}"
                                data-total="${b.resolved_total_amount}" data-paid="${b.resolved_amount_paid}"
                                data-due="${b.resolved_balance_amount}" data-status="${b.status}"
                                class="px-3 py-1.5 text-sm text-white bg-amber-600 rounded-lg hover:bg-amber-700">
                                Process
                            </button>` : ''}
                        ${CAN_EDIT && b.status === 'ongoing' ? `
                            <button type="button" onclick="openProcessModal(this)"
                                data-id="${b.id}" data-customer="${esc(b.customer_name)}"
                                data-treatment="${esc(b.treatment_label)}" data-source="${esc(b.booking_source)}"
                                data-total="${b.resolved_total_amount}" data-paid="${b.resolved_amount_paid}"
                                data-due="${b.resolved_balance_amount}" data-status="${b.status}"
                                class="px-3 py-1.5 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700">
                                Cancel
                            </button>` : ''}
                        ${editBtn(b)}
                        ${deleteBtn(b.id)}
                    </div>
                </td>` : '';

            return `
                <tr data-booking-id="${b.id}"
                    class="hover:bg-gray-50 dark:hover:bg-gray-900/40 ${rowBg} row-updated">
                    <td class="px-6 py-4">
                        <p class="font-medium text-gray-900 dark:text-white">${esc(b.customer_name)}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">${esc(b.customer_email) || 'No email'}</p>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">${esc(b.treatment_label)}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">${esc(b.service_type_label)}</p>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">${esc(b.therapist_name)}</td>
                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                        ${esc(b.start_time_fmt)} – ${esc(b.end_time_fmt)}
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                            <p>Total: ${php(b.resolved_total_amount)}</p>
                            <p>Paid: ${php(b.resolved_amount_paid)}</p>
                            <p class="${dueColor}">Due: ${php(b.resolved_balance_amount)}</p>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex flex-col gap-2">
                            <span class="inline-flex w-fit items-center px-3 py-1 text-xs font-medium rounded-full ${stClass(b.status)}">
                                ${b.status.charAt(0).toUpperCase() + b.status.slice(1)}
                            </span>
                            <span class="inline-flex w-fit items-center px-3 py-1 text-xs font-medium rounded-full ${srcClass(b.booking_source)}">
                                ${(b.booking_source || 'STAFF').toUpperCase()}
                            </span>
                        </div>
                    </td>
                    ${actionButtons}
                </tr>`;
        }).join('');
    }

    // ── Rebuild Upcoming table ────────────────────────────────────────────────
    function updateUpcomingTable(appointments) {
        const tbody = document.getElementById('upcomingTbody');
        if (!appointments.length) {
            tbody.innerHTML = `<tr id="upcomingEmptyRow">
                <td colspan="${UPCOMING_COLS}" class="px-6 py-10 text-sm text-center text-gray-500 dark:text-gray-400">
                    No upcoming reservations found.
                </td></tr>`;
            return;
        }

        tbody.innerHTML = appointments.map(b => {
            const actionButtons = SHOW_ACTIONS ? `
                <td class="px-6 py-4 text-center">
                    <div class="flex flex-wrap justify-center gap-2">
                        ${editBtn(b)}
                        ${deleteBtn(b.id)}
                    </div>
                </td>` : '';

            return `
                <tr data-booking-id="${b.id}" class="hover:bg-gray-50 dark:hover:bg-gray-900/40 row-updated">
                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">${esc(b.customer_name)}</td>
                    <td class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">${esc(b.treatment_label)}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">${esc(b.service_type_label)}</p>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                        ${esc(b.appointment_date)}
                        <div class="text-xs text-gray-500 dark:text-gray-400">${esc(b.start_time_fmt)}</div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">${esc(b.therapist_name)}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full ${stClass(b.status)}">
                            ${b.status.charAt(0).toUpperCase() + b.status.slice(1)}
                        </span>
                    </td>
                    ${actionButtons}
                </tr>`;
        }).join('');
    }

    // ── Flash animation on status change ─────────────────────────────────────
    function flashChangedRows(appointments) {
        appointments.forEach(b => {
            const prev = prevStatuses[b.id];
            if (prev && prev !== b.status) {
                const row = document.querySelector(`[data-booking-id="${b.id}"]`);
                if (row) {
                    row.classList.add('flash-update');
                    setTimeout(() => row.classList.remove('flash-update'), 1500);
                }
            }
            prevStatuses[b.id] = b.status;
        });
    }

    // ── Main poll function ────────────────────────────────────────────────────
    async function poll() {
        try {
            const res  = await fetch(LIVE_URL, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const data = await res.json();

            // Always update summary + needs attention
            updateSummary(data.summary);
            updateNeedsAttention(data.pending_appointments);

            // Only rebuild table rows if no modal is open
            if (!isModalOpen) {
                const all = [...data.today_appointments, ...data.upcoming_appointments];
                flashChangedRows(all);
                updateTodayTable(data.today_appointments);
                updateUpcomingTable(data.upcoming_appointments);
            }

            lastUpdatedAt = new Date();
            setLiveStatus('ok');
            label.textContent = 'Live — just now';

        } catch (err) {
            console.error('Live poll failed:', err);
            setLiveStatus('error');
        }
    }

    // ── Seed prevStatuses from server-rendered rows ───────────────────────────
    document.querySelectorAll('[data-booking-id]').forEach(row => {
        const statusEl = row.querySelector('[class*="bg-amber-100"], [class*="bg-blue-100"], [class*="bg-emerald-100"], [class*="bg-red-100"], [class*="bg-slate-100"]');
        if (statusEl) {
            const text = statusEl.textContent.trim().toLowerCase();
            prevStatuses[row.dataset.bookingId] = text;
        }
    });

    // ── Start ─────────────────────────────────────────────────────────────────
    setLiveStatus('connecting');
    poll(); // immediate first run
    setInterval(poll, POLL_INTERVAL_MS);
    startTickTimer();

}()); // end live polling IIFE
</script>

<style>
/* Row flash on status change */
@keyframes rowFlash {
    0%   { background-color: rgba(234, 179, 8, 0.25); }
    100% { background-color: transparent; }
}
.flash-update { animation: rowFlash 1.5s ease-out; }

/* Edit modal field errors */
.has-error { border-color: #ef4444 !important; }
.dark .has-error { border-color: #f87171 !important; }
.edit-field-error { animation: fadeIn 0.2s ease-in-out; }
@keyframes fadeIn {
    from { opacity:0; transform: translateY(-4px); }
    to   { opacity:1; transform: translateY(0); }
}
</style>
@endsection