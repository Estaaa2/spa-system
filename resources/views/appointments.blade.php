@extends('layouts.app')

@section('title', 'Appointments')

@section('content')
@php
    $user = auth()->user();

    $canEdit    = $user?->hasBranchPermission('edit appointments') ?? false;
    $canDelete  = $user?->hasBranchPermission('delete appointments') ?? false;
    $canRequestReassignment = $user?->hasBranchPermission('request appointment reassignment') ?? false;
    $showActions = $canEdit || $canDelete;
    $showUpcomingActions = $showActions || $canRequestReassignment;

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

    // Used when $booking->status is not one of the five known values.
    $statusFallback = 'bg-gray-100 text-gray-700';

    // The button classes are defined here to ensure consistent styling across 
    // the different action buttons in the appointments table. 
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'process'  => $btnBase . ' bg-amber-700 text-white hover:bg-amber-800',
        'edit'     => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                    . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'cancel'   => $btnBase . ' border border-amber-300 bg-white text-amber-800 hover:bg-amber-50 '
                    . 'dark:border-amber-700 dark:bg-gray-800 dark:text-amber-300 dark:hover:bg-amber-900/20',
        'remove'   => $btnBase . ' bg-red-700 text-white hover:bg-red-800',
        'reassign' => $btnBase . ' border border-amber-300 bg-white text-amber-800 hover:bg-amber-50 '
                    . 'dark:border-amber-700 dark:bg-gray-800 dark:text-amber-300 dark:hover:bg-amber-900/20',
    ];

    $btnLabel = [
        'process'  => 'Process',
        'edit'     => 'Edit',
        'cancel'   => 'Cancel Appt',
        'remove'   => 'Remove',
        'reassign' => "Can't Make It?",
    ];

    $reassignPendingBadge = 'px-3 py-1.5 text-sm text-amber-700 bg-amber-100 rounded-lg dark:bg-amber-900/30 dark:text-amber-300';
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">

    {{-- ── Header + live status bar ── --}}
    <div class="space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-full
                        bg-white border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700
                        text-gray-500 dark:text-gray-400 select-none">
                <span id="liveIndicatorDot"
                      class="inline-block w-2 h-2 transition-colors duration-300 bg-gray-300 rounded-full dark:bg-gray-600"></span>
                <span id="liveIndicatorLabel" role="status" aria-live="polite">Connecting…</span>
            </div>
        </div>

        <x-page-header
            title="Appointments"
            subtitle="Monitor bookings, process arrivals, and track payments for today’s operations."
        />
    </div>

    {{-- ── Summary cards ── --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Today's Appointments</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 id="stat-today" class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">{{ $summary['today_total'] }}</h3>
                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">Scheduled today</span>
            </div>
        </div>

        <div class="p-4 border shadow-sm sm:p-5 bg-amber-50 border-amber-200 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800">
            <p class="text-xs font-semibold tracking-wide uppercase text-amber-700 dark:text-amber-300">Needs Action</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 id="stat-pending" class="text-2xl font-semibold sm:text-3xl text-amber-900 dark:text-amber-200">{{ $summary['pending_today'] }}</h3>
                <span class="text-xs sm:text-sm text-amber-700 dark:text-amber-300">Pending check-ins</span>
            </div>
        </div>

        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Upcoming</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 id="stat-upcoming" class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">{{ $summary['upcoming_total'] }}</h3>
                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">Future reservations</span>
            </div>
        </div>

        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Collected Today</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 id="stat-collected" class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                    ₱{{ number_format($summary['collected_today'], 2) }}
                </h3>
                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">Recorded payments</span>
            </div>
        </div>
    </div>

    {{-- ── Needs Attention (hidden when no pending appointments) ── --}}
    <div id="needsAttentionSection"
         class="{{ $todayPending->count() === 0 ? 'hidden' : '' }}
                overflow-hidden bg-white border shadow-sm border-amber-200 rounded-2xl
                dark:bg-gray-800 dark:border-amber-800">

        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-4 border-b sm:px-6 border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/10">
            <div>
                <h2 class="text-base font-semibold text-amber-900 dark:text-amber-200">Needs Attention Right Now</h2>
                <p class="text-sm text-amber-700 dark:text-amber-300">
                    Pending appointments should be processed first — mark them as ongoing or cancelled and record payment received.
                </p>
            </div>
            <span id="pendingBadge"
                  class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                {{ $summary['pending_today'] }} Pending
            </span>
        </div>

        <div class="p-4 sm:p-6">
            <div id="pendingList" class="space-y-4">
                @forelse($todayPending as $booking)
                    <div class="p-4 border rounded-2xl border-amber-200 bg-amber-50/60 dark:border-amber-800 dark:bg-amber-900/10">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
                                            class="{{ $btn['process'] }}">
                                        <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>{{ $btnLabel['process'] }}
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

    @include('appointments.partials.reassignment-requests-modal')

    @if($canRequestReassignment)
        @include('partials.reassignment-flag-modal')
    @endif

    {{-- ── Today's Appointments ── --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex items-center justify-between px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Today's Appointments</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Operational view for all appointments scheduled for today.</p>
            </div>
        </div>

        <div class="md:overflow-x-auto">
            <table role="table" class="rt min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Customer</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Service</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Therapist</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Time</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Payment</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Status</th>
                        @if($showActions)
                            <th role="columnheader" class="px-6 py-3 text-xs font-medium text-center text-gray-500 uppercase dark:text-gray-400">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody role="rowgroup" id="todayTbody" class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                    @forelse($todayAppointments as $booking)
                        <tr role="row"
                            data-booking-id="{{ $booking->id }}"
                            data-status="{{ $booking->status }}"
                            class="hover:bg-gray-50 dark:hover:bg-gray-900/40
                                   {{ $booking->status === 'pending' ? 'bg-amber-50/50 dark:bg-amber-900/10' : '' }}">
                            <td role="cell" data-label="Customer" class="px-6 py-4">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $booking->customer_name ?? 'Walk-in Customer' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $booking->customer_email ?? 'No email' }}</p>
                                </div>
                            </td>
                            <td role="cell" data-label="Service" class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $booking->treatment_label }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $booking->service_type_label }}</p>
                            </td>
                            <td role="cell" data-label="Therapist" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ $booking->therapist->name ?? 'Not Assigned' }}
                            </td>
                            <td role="cell" data-label="Time" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ \Carbon\Carbon::parse($booking->start_time)->format('h:i A') }}
                                – {{ \Carbon\Carbon::parse($booking->end_time)->format('h:i A') }}
                            </td>
                            <td role="cell" data-label="Payment" class="px-6 py-4">
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    <p>Total: ₱{{ number_format($booking->resolved_total_amount, 2) }}</p>
                                    <p>Paid: ₱{{ number_format($booking->resolved_amount_paid, 2) }}</p>
                                    <p class="{{ $booking->resolved_balance_amount > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-700 dark:text-emerald-300' }}">
                                        Due: ₱{{ number_format($booking->resolved_balance_amount, 2) }}
                                    </p>
                                </div>
                            </td>
                            <td role="cell" data-label="Status" class="px-6 py-4">
                                <div class="flex flex-wrap gap-2 md:flex-col">
                                    <span class="inline-flex w-fit items-center px-3 py-1 text-xs font-medium rounded-full
                                                 {{ $statusClasses[$booking->status] ?? $statusFallback }}">
                                        {{ ucfirst($booking->status) }}
                                    </span>
                                    <span class="inline-flex w-fit items-center px-3 py-1 text-xs font-medium rounded-full
                                                 {{ $sourceClasses[$booking->booking_source ?? ''] ?? $sourceClasses[''] }}">
                                        {{ strtoupper($booking->booking_source ?: 'STAFF') }}
                                    </span>
                                </div>
                            </td>
                            @if($showActions)
                                <td role="cell" data-label="Actions" class="px-6 py-4 text-center rt-actions">
                                    <div class="flex flex-wrap gap-2 md:justify-center">
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
                                                        class="{{ $btn['process'] }}">
                                                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>{{ $btnLabel['process'] }}
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
                                                        class="{{ $btn['cancel'] }}">
                                                    <i class="fa-solid fa-ban" aria-hidden="true"></i>{{ $btnLabel['cancel'] }}
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
                                                    class="{{ $btn['edit'] }}">
                                                <i class="fa-solid fa-pen" aria-hidden="true"></i>{{ $btnLabel['edit'] }}
                                            </button>
                                        @endif
                                        @if($canDelete)
                                            <button type="button" onclick="openDeleteModal({{ $booking->id }})"
                                                    class="{{ $btn['remove'] }}">
                                                <i class="fa-solid fa-trash" aria-hidden="true"></i>{{ $btnLabel['remove'] }}
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr role="row" id="todayEmptyRow">
                            <td role="cell" colspan="{{ $showActions ? 7 : 6 }}"
                                class="px-6 py-10 text-sm text-center text-gray-500 rt-empty dark:text-gray-400">
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
        <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Upcoming Reservations</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Future bookings that are already lined up for the branch.</p>
        </div>

        <div class="md:overflow-x-auto">
            <table role="table" class="rt min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Customer</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Service</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Date</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Therapist</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Status</th>
                        @if($showUpcomingActions)
                            <th role="columnheader" class="px-6 py-3 text-xs font-medium text-center text-gray-500 uppercase dark:text-gray-400">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody role="rowgroup" id="upcomingTbody" class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                    @forelse($upcomingAppointments as $booking)
                        <tr role="row"
                            data-booking-id="{{ $booking->id }}"
                            data-status="{{ $booking->status }}"
                            class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td role="cell" data-label="Customer" class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                {{ $booking->customer_name ?? 'Walk-in Customer' }}
                            </td>
                            <td role="cell" data-label="Service" class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $booking->treatment_label }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $booking->service_type_label }}</p>
                            </td>
                            <td role="cell" data-label="Date" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ $booking->appointment_date?->format('M d, Y') }}
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ \Carbon\Carbon::parse($booking->start_time)->format('h:i A') }}
                                </div>
                            </td>
                            <td role="cell" data-label="Therapist" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ $booking->therapist->name ?? 'Not Assigned' }}
                            </td>
                            <td role="cell" data-label="Status" class="px-6 py-4">
                                <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full
                                             {{ $statusClasses[$booking->status] ?? $statusFallback }}">
                                    {{ ucfirst($booking->status) }}
                                </span>
                            </td>
                            @if($showUpcomingActions)
                                <td role="cell" data-label="Actions" class="px-6 py-4 text-center rt-actions">
                                    <div class="flex flex-wrap gap-2 md:justify-center">
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
                                                    class="{{ $btn['edit'] }}">
                                                <i class="fa-solid fa-pen" aria-hidden="true"></i>{{ $btnLabel['edit'] }}
                                            </button>
                                        @endif
                                        @if($canDelete)
                                            <button type="button" onclick="openDeleteModal({{ $booking->id }})"
                                                    class="{{ $btn['remove'] }}">
                                                <i class="fa-solid fa-trash" aria-hidden="true"></i>{{ $btnLabel['remove'] }}
                                            </button>
                                        @endif
                                        @if($canRequestReassignment && in_array($booking->status, ['reserved', 'pending']))
                                            @if($booking->has_pending_reassignment)
                                                <span class="{{ $reassignPendingBadge }}">
                                                    <i class="fa-solid fa-clock" aria-hidden="true"></i> Pending
                                                </span>
                                            @else
                                                <button type="button" onclick="openReassignFlagModal(this)"
                                                        data-reassign-flag-btn="{{ $booking->id }}"
                                                        data-id="{{ $booking->id }}"
                                                        data-customer="{{ $booking->customer_name ?? 'Walk-in Customer' }}"
                                                        data-treatment="{{ $booking->treatment_label }}"
                                                        data-date="{{ $booking->appointment_date?->format('M d, Y') }}"
                                                        data-time="{{ \Carbon\Carbon::parse($booking->start_time)->format('h:i A') }}"
                                                        data-badge-class="px-3 py-1.5 text-sm text-amber-700 bg-amber-100 rounded-lg dark:bg-amber-900/30 dark:text-amber-300"
                                                        data-badge-label="Pending"
                                                        class="{{ $btn['reassign'] }}">
                                                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>{{ $btnLabel['reassign'] }}
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr role="row" id="upcomingEmptyRow">
                            <td role="cell" colspan="{{ $showUpcomingActions ? 6 : 5 }}"
                                class="px-6 py-10 text-sm text-center text-gray-500 rt-empty dark:text-gray-400">
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
        <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">History</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Completed, cancelled, and past records for reference.</p>
        </div>
        <div class="md:overflow-x-auto">
            <table role="table" class="rt min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Customer</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Service</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Date</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Payment</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Status</th>
                    </tr>
                </thead>
                <tbody role="rowgroup" class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                    @forelse($historyAppointments as $booking)
                        <tr role="row" class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td role="cell" data-label="Customer" class="px-6 py-4">
                                <p class="font-medium text-gray-900 dark:text-white">{{ $booking->customer_name ?? 'Walk-in Customer' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $booking->customer_email ?? 'No email' }}</p>
                            </td>
                            <td role="cell" data-label="Service" class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $booking->treatment_label }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $booking->service_type_label }}</p>
                            </td>
                            <td role="cell" data-label="Date" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ $booking->appointment_date?->format('M d, Y') }}
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ \Carbon\Carbon::parse($booking->start_time)->format('h:i A') }}
                                    – {{ \Carbon\Carbon::parse($booking->end_time)->format('h:i A') }}
                                </div>
                            </td>
                            <td role="cell" data-label="Payment" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                <div>Total: ₱{{ number_format($booking->resolved_total_amount, 2) }}</div>
                                <div>Paid: ₱{{ number_format($booking->resolved_amount_paid, 2) }}</div>
                            </td>
                            <td role="cell" data-label="Status" class="px-6 py-4">
                                <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full
                                             {{ $statusClasses[$booking->status] ?? $statusFallback }}">
                                    {{ ucfirst($booking->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr role="row">
                            <td role="cell" colspan="5" class="px-6 py-10 text-sm text-center text-gray-500 rt-empty dark:text-gray-400">
                                No historical records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
            {{ $historyAppointments->links() }}
        </div>
    </div>

</div>{{-- end max-w-7xl --}}


{{-- ═══════════════════════════════════════════════════
     PROCESS MODAL
     ═══════════════════════════════════════════════════ --}}
@if($canEdit)
<div id="processModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="processModalTitle"
             class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div>
                    <h2 id="processModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Process Appointment</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Record customer payment and continue the appointment flow.</p>
                </div>
                <button type="button" onclick="closeProcessModal()" aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <form id="processForm" method="POST" class="px-4 py-6 space-y-4 sm:px-6">
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
                    <label for="process_status" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Next Status</label>
                    {{-- Options are populated by openProcessModal(); see notes on finding 21. --}}
                    <select id="process_status" name="status"
                            class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white"></select>
                </div>
                <div id="process_amount_wrapper">
                    <label for="process_amount_paid" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Amount Collected Now</label>
                    <input type="number" step="0.01" min="0" id="process_amount_paid" name="amount_paid"
                           class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <p id="process_hint" class="mt-2 text-xs text-gray-500 dark:text-gray-400"></p>
                </div>
                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                    <button type="button" onclick="closeProcessModal()"
                            class="min-h-[44px] px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                        Close
                    </button>
                    <button type="submit"
                            class="min-h-[44px] rounded-xl bg-[#8B7355] px-4 py-2 text-sm font-medium text-white hover:bg-[#7A6348]">
                        Save Action
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════
     EDIT MODAL
     ═══════════════════════════════════════════════════ --}}
@if($canEdit)
@php
    $allTreatments = \App\Models\Treatment::orderBy('name')->get();
    $allPackages   = \App\Models\Package::orderBy('name')->get();
@endphp
<div id="editModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="editModalTitle"
             class="w-full max-w-2xl bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div>
                    <h2 id="editModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Edit Appointment</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Update appointment details, therapist assignment, and schedule.</p>
                </div>
                <button type="button" onclick="closeEditModal()" aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <form id="editForm" method="POST" class="px-4 py-6 space-y-4 sm:px-6">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <input type="hidden" id="edit_status" name="status">
                    <div class="md:col-span-2">
                        <label for="edit_customer_name" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Customer Name</label>
                        <input type="text" id="edit_customer_name" name="customer_name" readonly
                            class="w-full min-h-[44px] px-3 py-2 text-sm bg-gray-100 border border-gray-300 cursor-not-allowed rounded-xl dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Customer name cannot be changed here.</p>
                    </div>
                    <div>
                        <label for="edit_customer_email" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Customer Email</label>
                        <input type="email" id="edit_customer_email" name="customer_email" readonly
                            class="w-full min-h-[44px] px-3 py-2 text-sm bg-gray-100 border border-gray-300 cursor-not-allowed rounded-xl dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Customer email cannot be changed here.</p>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="edit_customer_phone" class="text-sm font-medium text-gray-700 dark:text-gray-300">Customer Phone</label>
                            {{-- -my-3 keeps the 44px tap target without making this label row
                                 taller than the plain label opposite it, which was pushing the
                                 phone input out of alignment with the email input. --}}
                            <button type="button" id="edit_phone_toggle" onclick="togglePhoneEdit()"
                                    class="inline-flex items-center min-h-[44px] -my-3 px-2 text-xs font-semibold text-[#8B7355] hover:text-[#6F5430] dark:text-[#C4A97D] dark:hover:text-[#D8C09A]">
                                <i class="mr-1 fa-solid fa-pen" aria-hidden="true"></i>Edit
                            </button>
                        </div>
                        <input type="text" id="edit_customer_phone" name="customer_phone" readonly
                            class="w-full min-h-[44px] px-3 py-2 text-sm bg-gray-100 border border-gray-300 cursor-not-allowed rounded-xl dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
                        <p id="edit_phone_hint" class="hidden mt-1 text-xs text-amber-700 dark:text-amber-400">
                            Only change this if the customer lost access to their previous number.
                        </p>
                    </div>
                    <div>
                        <label for="edit_service_type" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Service Type</label>
                        <select id="edit_service_type" name="service_type"
                                class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="in_branch">In Branch</option>
                            <option value="in_home">In Home</option>
                        </select>
                    </div>
                    <div>
                        <label for="edit_treatment" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Treatment / Package</label>
                        <select id="edit_treatment" name="treatment"
                                class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
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
                    {{-- Shown only for in-home service, mirroring booking.blade.php's
                         #customer_address_container behaviour. --}}
                    <div class="hidden md:col-span-2" id="edit_customer_address_container">
                        <label for="edit_customer_address" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Customer Address</label>
                        <input type="text" id="edit_customer_address" name="customer_address"
                               class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label for="edit_appointment_date" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Appointment Date</label>
                        <input type="date" id="edit_appointment_date" name="appointment_date"
                               class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label for="edit_start_time" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Start Time</label>
                        {{-- step=60 keeps the browser from rendering a seconds spinner. --}}
                        <input type="time" step="60" id="edit_start_time" name="start_time"
                               class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div class="md:col-span-2">
                        <label for="edit_therapist_id" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Therapist</label>
                        <select id="edit_therapist_id" name="therapist_id"
                                class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="">— No Therapist Assigned —</option>
                            @foreach($therapists as $therapist)
                                <option value="{{ $therapist->id }}" data-branch="{{ $therapist->branch_id }}">
                                    {{ $therapist->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                    <button type="button" onclick="closeEditModal()"
                            class="min-h-[44px] px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                        Cancel
                    </button>
                    <button type="submit"
                            class="min-h-[44px] rounded-xl bg-[#8B7355] px-4 py-2 text-sm font-medium text-white hover:bg-[#7A6348]">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════
     DELETE MODAL
     ═══════════════════════════════════════════════════ --}}
@if($canDelete)
<div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="alertdialog" aria-modal="true" aria-labelledby="deleteModalTitle" aria-describedby="deleteModalDesc"
             class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <h2 id="deleteModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Remove Appointment</h2>
            <p id="deleteModalDesc" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                This permanently deletes the appointment record. To keep the record but stop the
                appointment from going ahead, close this and use “Cancel Appt” instead.
            </p>
            <div class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">
                <button type="button" onclick="closeDeleteModal()"
                        class="min-h-[44px] px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                    Keep Appointment
                </button>
                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="w-full min-h-[44px] px-4 py-2 text-sm font-medium text-white bg-red-700 rounded-xl hover:bg-red-800 sm:w-auto">
                        Yes, Remove
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif


{{-- ═══════════════════════════════════════════════════
     SCRIPTS
     ═══════════════════════════════════════════════════ --}}
<script>
// Everything is inside one IIFE. Functions that inline onclick= attributes call
// are explicitly attached to window at the bottom of each block. Nothing is
// declared at top-level script scope, so an @included partial that happens to
// define esc()/php()/STATUS_CLASSES can no longer collide and kill this file.
(function () {
    'use strict';

    // ── Permission flags passed from Blade to JS ──────────────────────────
    const CAN_EDIT   = {{ $canEdit   ? 'true' : 'false' }};
    const CAN_DELETE = {{ $canDelete ? 'true' : 'false' }};
    const CAN_REQUEST_REASSIGNMENT = {{ $canRequestReassignment ? 'true' : 'false' }};
    const SHOW_ACTIONS = CAN_EDIT || CAN_DELETE;
    const SHOW_UPCOMING_ACTIONS = SHOW_ACTIONS || CAN_REQUEST_REASSIGNMENT;
    const TODAY_COLS  = SHOW_ACTIONS ? 7 : 6;
    const UPCOMING_COLS = SHOW_UPCOMING_ACTIONS ? 6 : 5;

    // Handed over from the Blade arrays above — one definition, two consumers.
    const STATUS_CLASSES  = @json($statusClasses);
    const SOURCE_CLASSES  = @json($sourceClasses);
    const STATUS_FALLBACK = @json($statusFallback);
    const BTN             = @json($btn);
    const BTN_LABEL       = @json($btnLabel);
    const REASSIGN_BADGE  = @json($reassignPendingBadge);

    // Named routes, confirmed against `php artisan route:list --name=appointment`.
    // Each takes a single parameter, so positional binding works regardless of
    // whether the segment is {booking} or {id}.
    const ROUTE_UPDATE = @json(route('appointments.update', '__ID__'));
    const ROUTE_STATUS = @json(route('appointments.updateStatus', '__ID__'));
    const ROUTE_DELETE = @json(route('appointments.destroy', '__ID__'));

    function routeFor(template, id) { return template.replace('__ID__', encodeURIComponent(id)); }

    function srcClass(src) { return SOURCE_CLASSES[src] ?? SOURCE_CLASSES['']; }
    function stClass(st)   { return STATUS_CLASSES[st]  ?? STATUS_FALLBACK; }
    function esc(s)        { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
    function php(n)        { return '₱' + Number(n).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}); }
    function ucfirst(s)    { return String(s || '').charAt(0).toUpperCase() + String(s || '').slice(1); }

    // Local calendar date, matching booking.blade.php's todayString().
    // toISOString() returns UTC, which is yesterday in Manila between 00:00–08:00.
    function todayString() {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
    }


    // ════════════════════════════════════════════════════════════════
    // SHARED MODAL BEHAVIOUR (open/close, Escape, focus, backdrop)
    // ════════════════════════════════════════════════════════════════
    const MODAL_IDS = ['processModal', 'editModal', 'deleteModal'];
    const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    let isModalOpen  = false;   // pause table rebuilds while a modal is open
    let lastFocused  = null;    // element to restore focus to on close

    function refreshModalState() {
        // Any modal on the page, including ones the reassignment partials add.
        isModalOpen = document.querySelector('[id$="Modal"]:not(.hidden)') !== null;
    }

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
        refreshModalState();
        const target = (focusSelector && el.querySelector(focusSelector))
            || el.querySelector(FOCUSABLE);
        if (target) target.focus();
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('hidden');
        refreshModalState();
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

    // Clicking the dark overlay closes the delete modal only. The process and
    // edit modals hold typed input, and a stray backdrop tap on a phone would
    // discard it with no warning. Escape and the Close button still work there.
    ['deleteModal'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('click', e => { if (e.target === el) closeModal(id); });
    });


    // ════════════════════════════════════════════════════════════════
    // PROCESS / EDIT / DELETE MODALS
    // ════════════════════════════════════════════════════════════════

@if($canEdit)
    // Only these two statuses can reach the Process button, but the fallback
    // guarantees the select is never empty (which would submit status=).
    const PROCESS_OPTIONS = {
        pending: [
            { value: 'ongoing',   label: 'Mark as Ongoing (customer has arrived)' },
            { value: 'cancelled', label: 'Cancel (customer called / no-show)' },
        ],
        ongoing: [
            { value: 'cancelled', label: 'Cancel (customer did not proceed / no-show)' },
        ],
    };

    function togglePaymentField() {
        const statusSelect  = document.getElementById('process_status');
        const amountWrapper = document.getElementById('process_amount_wrapper');
        amountWrapper.classList.toggle('hidden', statusSelect.value === 'cancelled');
    }

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
        const currentStatus = d.status || 'pending';

        amountInput.value = Number(d.due || 0) > 0 ? Number(d.due || 0).toFixed(2) : '';

        let opts = PROCESS_OPTIONS[currentStatus];
        if (!opts) {
            console.warn(`openProcessModal: unexpected status "${currentStatus}", falling back to the pending options.`);
            opts = PROCESS_OPTIONS.pending;
        }
        statusSelect.innerHTML = '';
        opts.forEach(o => {
            const opt = document.createElement('option');
            opt.value = o.value;
            opt.textContent = o.label;
            statusSelect.appendChild(opt);
        });

        hint.textContent = (d.source || '') === 'online'
            ? 'This online booking already paid a deposit. Record only the remaining balance collected at the branch.'
            : 'Record the amount collected directly by the receptionist for this appointment.';

        togglePaymentField();

        document.getElementById('processForm').action = routeFor(ROUTE_STATUS, d.id);
        openModal('processModal', '#process_status');
    }

    function closeProcessModal() { closeModal('processModal'); }

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
            div.setAttribute('role', 'alert');
            div.textContent = message;
            field.parentNode.insertBefore(div, field.nextSibling);
        }
    }

    function validateAppointmentDate() {
        const statusSelect     = document.getElementById('edit_status');
        const appointmentDate  = document.getElementById('edit_appointment_date').value;
        const today            = todayString();
        clearEditErrors();
        if (!appointmentDate) { showFieldError('edit_appointment_date','Please select an appointment date.'); return false; }
        if (['completed','ongoing'].includes(statusSelect.value) && appointmentDate > today) {
            showFieldError('edit_appointment_date', `Cannot mark as "${statusSelect.value}" for a future date.`);
            statusSelect.value = 'reserved';
            return false;
        }
        return true;
    }

    // Address is only meaningful for in-home service. Mirrors booking.blade.php:
    // toggles visibility and the required flag, and does not clear the stored value.
    function syncEditAddressVisibility() {
        const isHome    = document.getElementById('edit_service_type').value === 'in_home';
        const container = document.getElementById('edit_customer_address_container');
        const input     = document.getElementById('edit_customer_address');
        container.classList.toggle('hidden', !isHome);
        input.required = isHome;
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
        // start_time arrives as H:i:s; <input type="time"> renders a seconds
        // spinner if it is given one, so trim to H:i.
        document.getElementById('edit_start_time').value       = (d.startTime || '').slice(0, 5);
        document.getElementById('edit_status').value           = d.status          || '';
        syncEditAddressVisibility();
        const dateInput = document.getElementById('edit_appointment_date');
        dateInput.min   = todayString();
        dateInput.value = d.appointmentDate || '';
        const therapistSelect = document.getElementById('edit_therapist_id');
        Array.from(therapistSelect.options).forEach(opt => {
            if (opt.value === '') return;
            opt.hidden = opt.dataset.branch != d.branchId;
        });
        therapistSelect.value = d.therapistId || '';

        // Always re-lock the phone field for each newly opened booking, so a
        // previously-unlocked phone from a different customer's edit session
        // never carries over into this one.
        const phoneInput  = document.getElementById('edit_customer_phone');
        const phoneToggle = document.getElementById('edit_phone_toggle');
        const phoneHint   = document.getElementById('edit_phone_hint');
        phoneInput.setAttribute('readonly', true);
        phoneInput.classList.add('bg-gray-100', 'cursor-not-allowed', 'dark:bg-gray-900', 'dark:text-gray-400');
        phoneInput.classList.remove('bg-white', 'dark:bg-gray-700', 'dark:text-white');
        phoneToggle.innerHTML = '<i class="mr-1 fa-solid fa-pen" aria-hidden="true"></i>Edit';
        phoneHint.classList.add('hidden');

        document.getElementById('editForm').action = routeFor(ROUTE_UPDATE, d.id);
        openModal('editModal', '#edit_service_type');
    }

    function validateEditPhone() {
        const phoneInput = document.getElementById('edit_customer_phone');
        const phone = phoneInput.value.trim();

        // Only enforce the format if the field was actually unlocked and
        // possibly changed — a locked, untouched value is already known-good
        // from when the booking was created.
        if (phoneInput.hasAttribute('readonly')) return true;

        if (!/^09\d{9}$/.test(phone)) {
            showFieldError('edit_customer_phone', 'Enter a valid 11-digit phone number (09xxxxxxxxx).');
            return false;
        }
        return true;
    }

    function togglePhoneEdit() {
        const input = document.getElementById('edit_customer_phone');
        const btn   = document.getElementById('edit_phone_toggle');
        const hint  = document.getElementById('edit_phone_hint');
        const isLocked = input.hasAttribute('readonly');

        if (isLocked) {
            input.removeAttribute('readonly');
            input.classList.remove('bg-gray-100', 'cursor-not-allowed', 'dark:bg-gray-900', 'dark:text-gray-400');
            input.classList.add('bg-white', 'dark:bg-gray-700', 'dark:text-white');
            input.focus();
            btn.innerHTML = '<i class="mr-1 fa-solid fa-lock" aria-hidden="true"></i>Lock';
            hint.classList.remove('hidden');
        } else {
            input.setAttribute('readonly', true);
            input.classList.add('bg-gray-100', 'cursor-not-allowed', 'dark:bg-gray-900', 'dark:text-gray-400');
            input.classList.remove('bg-white', 'dark:bg-gray-700', 'dark:text-white');
            btn.innerHTML = '<i class="mr-1 fa-solid fa-pen" aria-hidden="true"></i>Edit';
            hint.classList.add('hidden');
        }
    }

    function closeEditModal() { closeModal('editModal'); clearEditErrors(); }

    // The edit form's fields are static, so these listeners are attached once
    // rather than re-attached on every openEditModal() call.
    function attachEditEventListeners() {
        const statusSelect = document.getElementById('edit_status');
        const dateInput    = document.getElementById('edit_appointment_date');
        const editForm     = document.getElementById('editForm');
        const phoneInput   = document.getElementById('edit_customer_phone');
        const processSelect = document.getElementById('process_status');

        statusSelect?.addEventListener('change', () => { clearEditErrors(); validateAppointmentDate(); });
        dateInput?.addEventListener('change',    () => { clearEditErrors(); validateAppointmentDate(); });
        phoneInput?.addEventListener('input', function () { this.value = this.value.replace(/\D/g, '').slice(0, 11); });
        editForm?.addEventListener('submit', e => {
            clearEditErrors();
            const dateOk  = validateAppointmentDate();
            const phoneOk = validateEditPhone();
            if (!dateOk || !phoneOk) {
                e.preventDefault();
                return false;
            }
        });
        editForm?.querySelectorAll('input, select').forEach(inp => inp.addEventListener('focus', clearEditErrors));
        processSelect?.addEventListener('change', togglePaymentField);
        document.getElementById('edit_service_type')
            ?.addEventListener('change', syncEditAddressVisibility);
    }

    window.openProcessModal  = openProcessModal;
    window.closeProcessModal = closeProcessModal;
    window.openEditModal     = openEditModal;
    window.closeEditModal    = closeEditModal;
    window.togglePhoneEdit   = togglePhoneEdit;
@endif

@if($canDelete)
    function openDeleteModal(id) {
        document.getElementById('deleteForm').action = routeFor(ROUTE_DELETE, id);
        openModal('deleteModal');
    }
    function closeDeleteModal() { closeModal('deleteModal'); }

    window.openDeleteModal  = openDeleteModal;
    window.closeDeleteModal = closeDeleteModal;
@endif

    document.addEventListener('DOMContentLoaded', function () {
        @if($canEdit) attachEditEventListeners(); @endif
        refreshModalState();
    });


    // ════════════════════════════════════════════════════════════════
    // LIVE POLLING
    // ════════════════════════════════════════════════════════════════
    const POLL_INTERVAL_MS = 30000; // 30 seconds
    const LIVE_URL         = '{{ route('appointments.live-data') }}';

    const dot   = document.getElementById('liveIndicatorDot');
    const label = document.getElementById('liveIndicatorLabel');

    let lastUpdatedAt = null;  // Date object
    let prevStatuses  = {};    // { id: status } — detect changes for row flash
    let tickTimer     = null;

    // Signature of the pending-card list. The two tables no longer use a
    // whole-table signature — they reconcile row by row instead.
    const painted = { pending: null };

    // A simple hash of a string, used to detect when a row's HTML has changed.
    function sigOf(str) {
        let h = 5381;
        for (let i = 0; i < str.length; i++) h = ((h << 5) + h + str.charCodeAt(i)) | 0;
        return (h >>> 0).toString(36);
    }

    function rowFromHtml(html) {
        const tmp = document.createElement('tbody');
        tmp.innerHTML = html.trim();
        return tmp.firstElementChild;
    }

    function patchRows(tbody, rows, emptyHtml) {
        if (!tbody) return;

        if (!rows.length) {
            if (tbody.dataset.rowsState !== 'empty') {
                tbody.innerHTML = emptyHtml;
                tbody.dataset.rowsState = 'empty';
            }
            return;
        }
        if (tbody.dataset.rowsState === 'empty') tbody.innerHTML = '';
        tbody.dataset.rowsState = 'rows';

        const existing = new Map();
        Array.from(tbody.children).forEach(tr => {
            if (tr.dataset && tr.dataset.bookingId) existing.set(tr.dataset.bookingId, tr);
        });

        let anchor = tbody.firstElementChild;

        rows.forEach(r => {
            let node = existing.get(r.id);
            if (node) {
                existing.delete(r.id);
                if (node.dataset.sig !== r.sig) {
                    // Server-rendered rows carry no data-sig, so each row is
                    // rebuilt once on the first poll, then stays put.
                    const fresh = rowFromHtml(r.html);
                    fresh.dataset.sig = r.sig;
                    if (anchor === node) anchor = node.nextElementSibling;
                    node.replaceWith(fresh);
                    node = fresh;
                } else if (anchor === node) {
                    anchor = node.nextElementSibling;
                    return;
                }
            } else {
                node = rowFromHtml(r.html);
                node.dataset.sig = r.sig;
            }
            if (node !== anchor) tbody.insertBefore(node, anchor);
            else anchor = node.nextElementSibling;
        });

        existing.forEach(n => n.remove());
    }

    // Every modal on the page, including the ones the reassignment partials
    // add, so a table rebuild can never pull a row out from under an open form.
    const TRACKED_MODALS = Array.from(document.querySelectorAll('[id$="Modal"]'));

    const modalObserver = new MutationObserver(refreshModalState);
    TRACKED_MODALS.forEach(el => {
        modalObserver.observe(el, { attributes: true, attributeFilter: ['class'] });
    });

    // ── Helpers ──────────────────────────────────────────────────────────────
    function setLiveStatus(state) {
        // state: 'ok' | 'error' | 'connecting'
        const map = {
            ok:         { dot: 'bg-emerald-500', label: 'Live'          },
            error:      { dot: 'bg-red-500',     label: 'Reconnecting…' },
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
    // Every Process button on the page comes from here, so the card in
    // "Needs Attention" and the row in the table can no longer diverge.
    function processBtn(b) {
        if (!CAN_EDIT) return '';
        if (b.status === 'pending') {
            return `<button type="button" onclick="openProcessModal(this)"
                data-id="${b.id}" data-customer="${esc(b.customer_name)}"
                data-treatment="${esc(b.treatment_label)}" data-source="${esc(b.booking_source)}"
                data-total="${b.resolved_total_amount}" data-paid="${b.resolved_amount_paid}"
                data-due="${b.resolved_balance_amount}" data-status="${b.status}"
                class="${BTN.process}">
                <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>${BTN_LABEL.process}
            </button>`;
        }
        if (b.status === 'ongoing') {
            return `<button type="button" onclick="openProcessModal(this)"
                data-id="${b.id}" data-customer="${esc(b.customer_name)}"
                data-treatment="${esc(b.treatment_label)}" data-source="${esc(b.booking_source)}"
                data-total="${b.resolved_total_amount}" data-paid="${b.resolved_amount_paid}"
                data-due="${b.resolved_balance_amount}" data-status="${b.status}"
                class="${BTN.cancel}">
                <i class="fa-solid fa-ban" aria-hidden="true"></i>${BTN_LABEL.cancel}
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
            class="${BTN.edit}">
            <i class="fa-solid fa-pen" aria-hidden="true"></i>${BTN_LABEL.edit}
        </button>`;
    }

    function deleteBtn(id) {
        if (!CAN_DELETE) return '';
        return `<button type="button" onclick="openDeleteModal(${id})"
            class="${BTN.remove}">
            <i class="fa-solid fa-trash" aria-hidden="true"></i>${BTN_LABEL.remove}
        </button>`;
    }

    function reassignBtn(b) {
        if (!CAN_REQUEST_REASSIGNMENT) return '';
        if (!['reserved', 'pending'].includes(b.status)) return '';
        if (b.has_pending_reassignment) {
            return `<span class="${REASSIGN_BADGE}">
                <i class="fa-solid fa-clock" aria-hidden="true"></i> Pending
            </span>`;
        }
        return `<button type="button" onclick="openReassignFlagModal(this)"
            data-reassign-flag-btn="${b.id}" data-id="${b.id}"
            data-customer="${esc(b.customer_name)}" data-treatment="${esc(b.treatment_label)}"
            data-date="${esc(b.appointment_date)}" data-time="${esc(b.start_time_fmt)}"
            data-badge-class="px-3 py-1.5 text-sm text-amber-700 bg-amber-100 rounded-lg dark:bg-amber-900/30 dark:text-amber-300"
            data-badge-label="Pending"
            class="${BTN.reassign}">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>${BTN_LABEL.reassign}
        </button>`;
    }

    // ── Rebuild "Needs Attention" ─────────────────────────────────────────────
    function updateNeedsAttention(pending, summary) {
        const section = document.getElementById('needsAttentionSection');
        const list    = document.getElementById('pendingList');
        const badge   = document.getElementById('pendingBadge');

        if (!pending.length) {
            section.classList.add('hidden');
            painted.pending = null;
            return;
        }

        section.classList.remove('hidden');

        const total = summary?.pending_today ?? pending.length;
        badge.textContent = `${total} Pending`;

        if (isModalOpen) return;

        const sig = JSON.stringify(pending);
        if (sig === painted.pending) return;
        painted.pending = sig;

        list.innerHTML = pending.map(b => `
            <div class="p-4 border rounded-2xl border-amber-200 bg-amber-50/60 dark:border-amber-800 dark:bg-amber-900/10">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
    const TODAY_EMPTY_HTML = `<tr role="row" id="todayEmptyRow">
        <td role="cell" colspan="${TODAY_COLS}" class="px-6 py-10 text-sm text-center text-gray-500 rt-empty dark:text-gray-400">
            No appointments scheduled for today.
        </td></tr>`;

    function updateTodayTable(appointments) {
        patchRows(
            document.getElementById('todayTbody'),
            appointments.map(b => ({ id: String(b.id), sig: sigOf(JSON.stringify(b)), html: todayRowHtml(b) })),
            TODAY_EMPTY_HTML
        );
    }

    function todayRowHtml(b) {
        const isPending = b.status === 'pending';
        const rowBg     = isPending ? 'bg-amber-50/50 dark:bg-amber-900/10' : '';
        const dueColor  = b.resolved_balance_amount > 0
            ? 'text-amber-700 dark:text-amber-300'
            : 'text-emerald-700 dark:text-emerald-300';

        const actionCell = SHOW_ACTIONS ? `
            <td role="cell" data-label="Actions" class="px-6 py-4 text-center rt-actions">
                <div class="flex flex-wrap gap-2 md:justify-center">
                    ${processBtn(b)}
                    ${editBtn(b)}
                    ${deleteBtn(b.id)}
                </div>
            </td>` : '';

        return `
            <tr role="row" data-booking-id="${b.id}" data-status="${b.status}"
                class="hover:bg-gray-50 dark:hover:bg-gray-900/40 ${rowBg}">
                <td role="cell" data-label="Customer" class="px-6 py-4">
                    <p class="font-medium text-gray-900 dark:text-white">${esc(b.customer_name)}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">${esc(b.customer_email) || 'No email'}</p>
                </td>
                <td role="cell" data-label="Service" class="px-6 py-4">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">${esc(b.treatment_label)}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">${esc(b.service_type_label)}</p>
                </td>
                <td role="cell" data-label="Therapist" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">${esc(b.therapist_name)}</td>
                <td role="cell" data-label="Time" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                    ${esc(b.start_time_fmt)} – ${esc(b.end_time_fmt)}
                </td>
                <td role="cell" data-label="Payment" class="px-6 py-4">
                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        <p>Total: ${php(b.resolved_total_amount)}</p>
                        <p>Paid: ${php(b.resolved_amount_paid)}</p>
                        <p class="${dueColor}">Due: ${php(b.resolved_balance_amount)}</p>
                    </div>
                </td>
                <td role="cell" data-label="Status" class="px-6 py-4">
                    <div class="flex flex-wrap gap-2 md:flex-col">
                        <span class="inline-flex w-fit items-center px-3 py-1 text-xs font-medium rounded-full ${stClass(b.status)}">
                            ${ucfirst(b.status)}
                        </span>
                        <span class="inline-flex w-fit items-center px-3 py-1 text-xs font-medium rounded-full ${srcClass(b.booking_source)}">
                            ${(b.booking_source || 'STAFF').toUpperCase()}
                        </span>
                    </div>
                </td>
                ${actionCell}
            </tr>`;
    }

    // ── Rebuild Upcoming table ────────────────────────────────────────────────
    const UPCOMING_EMPTY_HTML = `<tr role="row" id="upcomingEmptyRow">
        <td role="cell" colspan="${UPCOMING_COLS}" class="px-6 py-10 text-sm text-center text-gray-500 rt-empty dark:text-gray-400">
            No upcoming reservations found.
        </td></tr>`;

    function updateUpcomingTable(appointments) {
        patchRows(
            document.getElementById('upcomingTbody'),
            appointments.map(b => ({ id: String(b.id), sig: sigOf(JSON.stringify(b)), html: upcomingRowHtml(b) })),
            UPCOMING_EMPTY_HTML
        );
    }

    function upcomingRowHtml(b) {
        const actionCell = SHOW_UPCOMING_ACTIONS ? `
            <td role="cell" data-label="Actions" class="px-6 py-4 text-center rt-actions">
                <div class="flex flex-wrap gap-2 md:justify-center">
                    ${editBtn(b)}
                    ${deleteBtn(b.id)}
                    ${reassignBtn(b)}
                </div>
            </td>` : '';

        return `
            <tr role="row" data-booking-id="${b.id}" data-status="${b.status}"
                class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                <td role="cell" data-label="Customer" class="px-6 py-4 text-sm text-gray-900 dark:text-white">${esc(b.customer_name)}</td>
                <td role="cell" data-label="Service" class="px-6 py-4">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">${esc(b.treatment_label)}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">${esc(b.service_type_label)}</p>
                </td>
                <td role="cell" data-label="Date" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                    ${esc(b.appointment_date)}
                    <div class="text-xs text-gray-500 dark:text-gray-400">${esc(b.start_time_fmt)}</div>
                </td>
                <td role="cell" data-label="Therapist" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">${esc(b.therapist_name)}</td>
                <td role="cell" data-label="Status" class="px-6 py-4">
                    <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full ${stClass(b.status)}">
                        ${ucfirst(b.status)}
                    </span>
                </td>
                ${actionCell}
            </tr>`;
    }

    // ── Flash animation on status change ─────────────────────────────────────
    // Returns the ids whose status moved since the previous poll, and records
    // the new statuses. Called before the tables are rebuilt; the flash class
    // is applied after, so it lands on the row that actually survives.
    function collectStatusChanges(appointments) {
        const changed = [];
        appointments.forEach(b => {
            const prev = prevStatuses[b.id];
            if (prev && prev !== b.status) changed.push(String(b.id));
            prevStatuses[b.id] = b.status;
        });
        return changed;
    }

    function applyFlash(tbody, changedIds) {
        if (!tbody || !changedIds.length) return;
        changedIds.forEach(id => {
            const row = tbody.querySelector(`[data-booking-id="${id}"]`);
            if (row) {
                row.classList.add('flash-update');
                setTimeout(() => row.classList.remove('flash-update'), 1500);
            }
        });
    }

    // ── Main poll function ────────────────────────────────────────────────────
    async function poll() {
        try {
            const res = await fetch(LIVE_URL, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const data = await res.json();

            updateSummary(data.summary);
            updateNeedsAttention(data.pending_appointments, data.summary);

            // Only rebuild table rows if no modal is open
            if (!isModalOpen) {
                const changed = collectStatusChanges([...data.today_appointments, ...data.upcoming_appointments]);
                updateTodayTable(data.today_appointments);
                updateUpcomingTable(data.upcoming_appointments);
                applyFlash(document.getElementById('todayTbody'), changed);
                applyFlash(document.getElementById('upcomingTbody'), changed);
            }

            lastUpdatedAt = new Date();
            setLiveStatus('ok');
            label.textContent = 'Live — just now';

        } catch (err) {
            console.error('Live poll failed:', err);
            setLiveStatus('error');
        }
    }

    // ── Seed prevStatuses from the server-rendered rows ───────────────────────
    // Read from data-status rather than sniffing Tailwind colour classes, so
    // recolouring a status badge can't break change detection.
    document.querySelectorAll('[data-booking-id][data-status]').forEach(row => {
        prevStatuses[row.dataset.bookingId] = row.dataset.status;
    });

    // ── Start ─────────────────────────────────────────────────────────────────
    // The server render is already current, so the first poll waits a full
    // interval instead of firing immediately and doubling every page load.
    lastUpdatedAt = new Date();
    setLiveStatus('ok');
    label.textContent = 'Live — just now';
    setInterval(poll, POLL_INTERVAL_MS);
    startTickTimer();

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

/* Row flash on status change */
@keyframes rowFlash {
    0%   { background-color: rgba(234, 179, 8, 0.25); }
    100% { background-color: transparent; }
}
.flash-update { animation: rowFlash 1.5s ease-out; }

/* Edit modal field errors.
   darkMode is 'media', so there is no .dark class on <html> to hook. */
.has-error { border-color: #ef4444 !important; }
@media (prefers-color-scheme: dark) {
    .has-error { border-color: #f87171 !important; }
}
.edit-field-error { animation: fadeIn 0.2s ease-in-out; }
@keyframes fadeIn {
    from { opacity:0; transform: translateY(-4px); }
    to   { opacity:1; transform: translateY(0); }
}

@media (prefers-reduced-motion: reduce) {
    .flash-update,
    .edit-field-error { animation: none; }
}
</style>
@endsection