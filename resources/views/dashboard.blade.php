@extends('layouts.app')

@section('content')
@php
    $user = auth()->user();
    $now  = now();

    $statusClasses = [
        'reserved'  => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'pending'   => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'ongoing'   => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'completed' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
        'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    ];

    $sourceClasses = [
        'online'  => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
        'walk_in' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
        'staff'   => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
        ''        => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    ];

    // Resolve all permission flags once here to avoid hitting the DB on every
    // @can check inside the template loops.
    $canKpis            = $user->hasBranchPermission('view dashboard kpis');
    $canRevenue         = $user->hasBranchPermission('view dashboard revenue');
    $canTimeline        = $user->hasBranchPermission('view dashboard timeline');
    $canTherapistStatus = $user->hasBranchPermission('view dashboard therapist status');
    $canAlerts          = $user->hasBranchPermission('view dashboard alerts');
    $canBookingBtn      = $user->hasBranchPermission('view dashboard booking button');
    $canMyToday         = $user->hasBranchPermission('view dashboard my today');
@endphp

<div class="p-6 mx-auto space-y-6 max-w-7xl">

    {{-- ════════════════════════════════════════════════════════════════════
         HEADER
    ═══════════════════════════════════════════════════════════════════════ --}}
    <x-page-header
        title="Dashboard"
        :subtitle="$canMyToday ? $user->first_name . '\'s Schedule' : 'Overview of branch operations and activity.'"
    >
        <x-slot name="right">
            @if($canBookingBtn)
            <a href="{{ route('booking') }}"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-xl
                    bg-gradient-to-r from-[#8B7355] to-[#6F5430] shadow-sm hover:opacity-90 transition-opacity active:translate-y-0.5">
                <i class="fa-solid fa-plus text-xs"></i>
                New Booking
            </a>
            @endif
        </x-slot>
    </x-page-header>

    {{-- ════════════════════════════════════════════════════════════════════
         THERAPIST PERSONAL VIEW  (view dashboard my today)
         Visible only to therapist role by default. Owners/managers
         can also be granted this via the roles & permissions editor.
    ═══════════════════════════════════════════════════════════════════════ --}}
    @if($canMyToday)

        {{-- Personal stat cards --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">

            <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">My Today</p>
                    <div class="flex items-center justify-center w-8 h-8 rounded-xl bg-[#8B7355]/10">
                        <i class="fa-solid fa-calendar-day text-[#8B7355] text-sm"></i>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $myStats['total'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Appointments assigned</p>
            </div>

            <div class="p-5 border shadow-sm bg-emerald-50 border-emerald-200 rounded-2xl dark:bg-emerald-900/10 dark:border-emerald-800">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold tracking-wide uppercase text-emerald-700 dark:text-emerald-400">Ongoing</p>
                    <div class="flex items-center justify-center w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                        <i class="fa-solid fa-spinner text-emerald-600 dark:text-emerald-400 text-sm"></i>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-bold text-emerald-900 dark:text-emerald-200">{{ $myStats['ongoing'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">In session right now</p>
            </div>

            <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Completed</p>
                    <div class="flex items-center justify-center w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800">
                        <i class="fa-solid fa-circle-check text-slate-500 text-sm"></i>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $myStats['completed'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Done today</p>
            </div>

            <div class="p-5 border shadow-sm bg-blue-50 border-blue-200 rounded-2xl dark:bg-blue-900/10 dark:border-blue-800">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold tracking-wide uppercase text-blue-700 dark:text-blue-400">Remaining</p>
                    <div class="flex items-center justify-center w-8 h-8 rounded-xl bg-blue-100 dark:bg-blue-900/30">
                        <i class="fa-regular fa-clock text-blue-600 dark:text-blue-400 text-sm"></i>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-bold text-blue-900 dark:text-blue-200">{{ $myStats['remaining'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-blue-700 dark:text-blue-400">Still queued</p>
            </div>

        </div>

        {{-- Personal schedule timeline --}}
        <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 overflow-hidden">

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">My Schedule Today</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Your appointments for {{ $now->format('M d, Y') }}</p>
                </div>
                @can('view schedule')
                <a href="{{ route('schedule.index') }}"
                   class="text-xs font-medium text-[#8B7355] hover:text-[#6F5430] transition-colors">
                    Full schedule →
                </a>
                @endcan
            </div>

            @if($myTodayAppointments->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-500">
                    <i class="fa-regular fa-calendar-check text-3xl mb-3"></i>
                    <p class="text-sm font-medium">No appointments assigned to you today.</p>
                    <p class="text-xs mt-1 text-gray-400">Check your upcoming schedule below.</p>
                </div>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($myTodayAppointments as $booking)
                    @php
                        $startC = \Carbon\Carbon::parse($booking->start_time);
                        $endC   = \Carbon\Carbon::parse($booking->end_time);
                        $isNow  = $booking->status === 'ongoing';
                        $isPast = in_array($booking->status, ['completed', 'cancelled']);
                    @endphp
                    <div class="flex items-start gap-4 px-6 py-4 transition-colors
                                hover:bg-gray-50 dark:hover:bg-gray-900/40
                                {{ $isNow ? 'bg-emerald-50/60 dark:bg-emerald-900/10' : '' }}
                                {{ $isPast ? 'opacity-60' : '' }}">

                        {{-- Time column --}}
                        <div class="flex-shrink-0 w-16 text-right">
                            <p class="text-xs font-bold text-gray-800 dark:text-white tabular-nums">{{ $startC->format('h:i') }}</p>
                            <p class="text-[10px] font-semibold text-gray-400">{{ $startC->format('A') }}</p>
                            <div class="mt-1 mx-auto w-px h-4 bg-gray-200 dark:bg-gray-700"></div>
                            <p class="text-[10px] text-gray-400 tabular-nums">{{ $endC->format('h:i A') }}</p>
                        </div>

                        {{-- Status dot --}}
                        <div class="flex-shrink-0 mt-1.5">
                            <div class="w-2.5 h-2.5 rounded-full border-2
                                {{ $isNow                               ? 'bg-emerald-500 border-emerald-500 ring-2 ring-emerald-200 dark:ring-emerald-800' : '' }}
                                {{ $booking->status === 'pending'       ? 'bg-amber-400  border-amber-400'  : '' }}
                                {{ $booking->status === 'reserved'      ? 'bg-blue-400   border-blue-400'   : '' }}
                                {{ $booking->status === 'completed'     ? 'bg-gray-300   border-gray-300'   : '' }}
                                {{ $booking->status === 'cancelled'     ? 'bg-red-300    border-red-300'    : '' }}">
                            </div>
                        </div>

                        {{-- Details --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                        {{ $booking->customer_name ?? 'Walk-in Customer' }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ $booking->treatment_display ?? '—' }}
                                    </p>
                                    @if($booking->service_type === 'in_home' && $booking->customer_address)
                                    <p class="text-[10px] text-violet-600 dark:text-violet-400 mt-0.5 flex items-center gap-1">
                                        <i class="fa-solid fa-house"></i>
                                        Home service · {{ $booking->customer_address }}
                                    </p>
                                    @endif
                                </div>
                                <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 text-[10px] font-semibold rounded-full
                                    {{ $statusClasses[$booking->status] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($booking->status) }}
                                </span>
                            </div>
                            @if($booking->customer_phone)
                            <p class="text-[10px] text-gray-400 mt-1.5 flex items-center gap-1">
                                <i class="fa-solid fa-phone text-[#8B7355]"></i>
                                {{ $booking->customer_phone }}
                            </p>
                            @endif
                        </div>

                    </div>
                    @endforeach
                </div>
            @endif

            {{-- Next upcoming outside today --}}
            @if($myNextAppointment)
            <div class="px-6 py-3 border-t border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/20">
                <p class="text-[10px] font-semibold tracking-wide text-gray-400 uppercase">Next Upcoming</p>
                <div class="flex items-center justify-between mt-1">
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                            {{ $myNextAppointment->customer_name ?? 'Walk-in Customer' }}
                            <span class="text-gray-400 font-normal mx-1">·</span>
                            {{ $myNextAppointment->treatment_display ?? '—' }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ \Carbon\Carbon::parse($myNextAppointment->appointment_date)->format('D, M j') }}
                            at {{ \Carbon\Carbon::parse($myNextAppointment->start_time)->format('h:i A') }}
                        </p>
                    </div>
                    <span class="text-xs font-medium px-2 py-1 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                        Reserved
                    </span>
                </div>
            </div>
            @endif

        </div>

    @endif {{-- end my today --}}


    {{-- ════════════════════════════════════════════════════════════════════
         KPI CARDS  (view dashboard kpis)
         Owner, Manager, Receptionist, Finance, HR by default.
    ═══════════════════════════════════════════════════════════════════════ --}}
    @if($canKpis)

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">

            <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Today</p>
                    <div class="flex items-center justify-center w-8 h-8 rounded-xl bg-[#8B7355]/10">
                        <i class="fa-solid fa-calendar-day text-[#8B7355] text-sm"></i>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $todayCount ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Total appointments</p>
            </div>

            <div class="p-5 border shadow-sm bg-emerald-50 border-emerald-200 rounded-2xl dark:bg-emerald-900/10 dark:border-emerald-800">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold tracking-wide uppercase text-emerald-700 dark:text-emerald-400">Ongoing</p>
                    <div class="flex items-center justify-center w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                        <i class="fa-solid fa-spinner text-emerald-600 dark:text-emerald-400 text-sm"></i>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-bold text-emerald-900 dark:text-emerald-200">{{ $ongoingToday ?? 0 }}</p>
                <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">In service right now</p>
            </div>

            <div class="p-5 border shadow-sm bg-amber-50 border-amber-200 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold tracking-wide uppercase text-amber-700 dark:text-amber-400">Pending</p>
                    <div class="flex items-center justify-center w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-900/30">
                        <i class="fa-solid fa-circle-exclamation text-amber-600 dark:text-amber-400 text-sm"></i>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-bold text-amber-900 dark:text-amber-200">{{ $pendingToday ?? 0 }}</p>
                <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">Needs check-in</p>
            </div>

            {{-- 4th card: revenue users get the collected amount, others get upcoming week --}}
            @if($canRevenue)
            <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Collected</p>
                    <div class="flex items-center justify-center w-8 h-8 rounded-xl bg-[#8B7355]/10">
                        <i class="fa-solid fa-peso-sign text-[#8B7355] text-sm"></i>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">₱{{ number_format($collectedToday ?? 0, 0) }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Revenue today</p>
            </div>
            @else
            <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Upcoming</p>
                    <div class="flex items-center justify-center w-8 h-8 rounded-xl bg-[#8B7355]/10">
                        <i class="fa-solid fa-calendar-week text-[#8B7355] text-sm"></i>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $upcomingWeek ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Next 7 days</p>
            </div>
            @endif

        </div>

        {{-- Secondary stat row --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">

            <div class="p-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Completed</p>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $completedToday ?? 0 }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Done today</p>
            </div>

            <div class="p-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Reserved</p>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $reservedToday ?? 0 }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Confirmed, not yet started</p>
            </div>

            @if($canRevenue)
            {{-- Revenue users also get upcoming + source split here --}}
            <div class="p-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Upcoming</p>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $upcomingWeek ?? 0 }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Next 7 days</p>
            </div>

            <div class="p-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Today's Source</p>
                <div class="flex items-end gap-3 mt-2">
                    <div>
                        <p class="text-2xl font-bold text-violet-700 dark:text-violet-400">{{ $onlineToday ?? 0 }}</p>
                        <p class="text-[10px] font-semibold text-violet-600 dark:text-violet-400 uppercase">Online</p>
                    </div>
                    <span class="mb-1 text-gray-300 dark:text-gray-600 text-lg font-light">/</span>
                    <div>
                        <p class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $walkInToday ?? 0 }}</p>
                        <p class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase">Walk-in</p>
                    </div>
                </div>
            </div>
            @else
            {{-- Non-revenue users get cancelled + a blank spacer --}}
            <div class="p-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Cancelled</p>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $cancelledToday ?? 0 }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Cancelled today</p>
            </div>
            <div></div>
            @endif

        </div>

    @endif {{-- end kpis --}}


    {{-- ════════════════════════════════════════════════════════════════════
         TIMELINE + THERAPIST STATUS
         Timeline:          view dashboard timeline      (owner, manager, receptionist)
         Therapist panel:   view dashboard therapist status (owner, manager)
    ═══════════════════════════════════════════════════════════════════════ --}}
    @if($canTimeline || $canTherapistStatus)
    <div class="grid gap-6 {{ ($canTimeline && $canTherapistStatus) ? 'lg:grid-cols-5' : '' }}">

        {{-- ── Today's Appointment Timeline ── --}}
        @if($canTimeline)
        <div class="{{ $canTherapistStatus ? 'lg:col-span-3' : '' }} bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 overflow-hidden">

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Today's Schedule</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">All branch appointments · {{ $now->format('M d, Y') }}</p>
                </div>
                @can('view appointments')
                <a href="{{ route('appointments.index') }}"
                   class="text-xs font-medium text-[#8B7355] hover:text-[#6F5430] transition-colors">
                    Full list →
                </a>
                @endcan
            </div>

            @if($todayAppointments->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-500">
                    <i class="fa-regular fa-calendar-xmark text-3xl mb-3"></i>
                    <p class="text-sm">No appointments scheduled today.</p>
                    @if($canBookingBtn)
                    <a href="{{ route('booking') }}"
                       class="mt-4 inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white rounded-xl
                              bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:opacity-90 transition">
                        <i class="fa-solid fa-plus text-[10px]"></i> Add Booking
                    </a>
                    @endif
                </div>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-700 max-h-[460px] overflow-y-auto">
                    @foreach($todayAppointments as $booking)
                    @php
                        $startC = \Carbon\Carbon::parse($booking->start_time);
                        $endC   = \Carbon\Carbon::parse($booking->end_time);
                        $isNow  = $booking->status === 'ongoing';
                        $isPast = in_array($booking->status, ['completed', 'cancelled']);
                    @endphp
                    <div class="flex items-start gap-4 px-6 py-4 transition-colors
                                hover:bg-gray-50 dark:hover:bg-gray-900/40
                                {{ $isNow  ? 'bg-emerald-50/60 dark:bg-emerald-900/10' : '' }}
                                {{ $isPast ? 'opacity-60' : '' }}">

                        <div class="flex-shrink-0 w-16 text-right">
                            <p class="text-xs font-bold text-gray-800 dark:text-white tabular-nums">{{ $startC->format('h:i') }}</p>
                            <p class="text-[10px] font-semibold text-gray-400">{{ $startC->format('A') }}</p>
                            <div class="mt-1 mx-auto w-px h-4 bg-gray-200 dark:bg-gray-700"></div>
                            <p class="text-[10px] text-gray-400 tabular-nums">{{ $endC->format('h:i A') }}</p>
                        </div>

                        <div class="flex-shrink-0 mt-1.5">
                            <div class="w-2.5 h-2.5 rounded-full border-2
                                {{ $isNow                               ? 'bg-emerald-500 border-emerald-500 ring-2 ring-emerald-200 dark:ring-emerald-800' : '' }}
                                {{ $booking->status === 'pending'       ? 'bg-amber-400  border-amber-400'  : '' }}
                                {{ $booking->status === 'reserved'      ? 'bg-blue-400   border-blue-400'   : '' }}
                                {{ $booking->status === 'completed'     ? 'bg-gray-300   border-gray-300'   : '' }}
                                {{ $booking->status === 'cancelled'     ? 'bg-red-300    border-red-300'    : '' }}">
                            </div>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                        {{ $booking->customer_name ?? 'Walk-in Customer' }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                        {{ $booking->treatment_display ?? '—' }}
                                    </p>
                                </div>
                                <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 text-[10px] font-semibold rounded-full
                                    {{ $statusClasses[$booking->status] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($booking->status) }}
                                </span>
                            </div>
                            <div class="flex items-center gap-3 mt-1.5">
                                <span class="text-[10px] text-gray-400 flex items-center gap-1">
                                    <i class="fa-solid fa-user-nurse text-[#8B7355]"></i>
                                    {{ $booking->therapist
                                        ? trim($booking->therapist->first_name . ' ' . $booking->therapist->last_name)
                                        : 'Unassigned' }}
                                </span>
                                <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium rounded-full
                                    {{ $sourceClasses[$booking->booking_source ?? ''] ?? $sourceClasses[''] }}">
                                    {{ strtoupper($booking->booking_source ?: 'STAFF') }}
                                </span>
                            </div>
                        </div>

                    </div>
                    @endforeach
                </div>
            @endif

            @if($nextAppointment)
            <div class="px-6 py-3 border-t border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/20">
                <p class="text-[10px] font-semibold tracking-wide text-gray-400 uppercase">Next Upcoming</p>
                <div class="flex items-center justify-between mt-1">
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                            {{ $nextAppointment->customer_name ?? 'Walk-in' }}
                            <span class="text-gray-400 font-normal mx-1">·</span>
                            {{ $nextAppointment->treatment_display ?? '—' }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ \Carbon\Carbon::parse($nextAppointment->appointment_date)->format('D, M j') }}
                            at {{ \Carbon\Carbon::parse($nextAppointment->start_time)->format('h:i A') }}
                        </p>
                    </div>
                    <span class="text-xs font-medium px-2 py-1 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                        Reserved
                    </span>
                </div>
            </div>
            @endif

        </div>
        @endif

        {{-- ── Therapist Status Panel ── --}}
        @if($canTherapistStatus)
        <div class="{{ $canTimeline ? 'lg:col-span-2' : '' }} bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 overflow-hidden">

            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Therapist Status</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Today's workload per therapist</p>
            </div>

            @if($therapists->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-500">
                    <i class="fa-solid fa-user-nurse text-3xl mb-3"></i>
                    <p class="text-sm">No active therapists assigned.</p>
                </div>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-700 max-h-[460px] overflow-y-auto">
                    @foreach($therapists as $therapist)
                    @php
                        $total     = $therapist->total_today ?? 0;
                        $ongoing   = $therapist->ongoing_count ?? 0;
                        $done      = $therapist->completed_count ?? 0;
                        $remaining = $therapist->remaining_count ?? 0;
                        $capacity  = 8;
                        $loadPct   = min(round(($total / max($capacity, 1)) * 100), 100);

                        $loadColor = match(true) {
                            $loadPct >= 100 => 'bg-red-500',
                            $loadPct >= 75  => 'bg-amber-400',
                            $loadPct >= 40  => 'bg-[#8B7355]',
                            default          => 'bg-emerald-500',
                        };

                        $statusLabel = match(true) {
                            $ongoing > 0   => 'In Session',
                            $remaining > 0 => 'Available',
                            $done > 0      => 'Finished',
                            default        => 'Free',
                        };

                        $statusBadge = match($statusLabel) {
                            'In Session' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                            'Available'  => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                            'Finished'   => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                            default      => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
                        };
                    @endphp
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="flex items-center justify-center w-9 h-9 rounded-full bg-[#8B7355]/15 text-[#8B7355] flex-shrink-0 text-sm font-bold">
                                    {{ strtoupper(substr($therapist->first_name ?? '?', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                        {{ trim(($therapist->first_name ?? '') . ' ' . ($therapist->last_name ?? '')) }}
                                    </p>
                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 truncate">{{ $therapist->email }}</p>
                                </div>
                            </div>
                            <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 text-[10px] font-semibold rounded-full {{ $statusBadge }}">
                                {{ $statusLabel }}
                            </span>
                        </div>

                        <div class="mt-3">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[10px] text-gray-400">{{ $total }} / {{ $capacity }} appointments</span>
                                <span class="text-[10px] font-semibold text-gray-500">{{ $loadPct }}%</span>
                            </div>
                            <div class="w-full h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="{{ $loadColor }} h-full rounded-full transition-all duration-500"
                                     style="width: {{ $loadPct }}%"></div>
                            </div>
                            <div class="flex items-center gap-3 mt-1.5">
                                <span class="text-[10px] text-emerald-600 dark:text-emerald-400">
                                    <i class="fa-solid fa-circle-check"></i> {{ $done }} done
                                </span>
                                @if($ongoing > 0)
                                <span class="text-[10px] text-emerald-700 font-semibold dark:text-emerald-400">
                                    <i class="fa-solid fa-spinner"></i> {{ $ongoing }} active
                                </span>
                                @endif
                                @if($remaining > 0)
                                <span class="text-[10px] text-blue-600 dark:text-blue-400">
                                    <i class="fa-regular fa-clock"></i> {{ $remaining }} queued
                                </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif

        </div>
        @endif

    </div>
    @endif {{-- end timeline / therapist row --}}


    {{-- ════════════════════════════════════════════════════════════════════
         BOTTOM ROW: Alerts · Breakdown · Quick Actions
         Alerts:     view dashboard alerts  (owner, manager, receptionist)
         Breakdown:  view dashboard revenue (owner, manager, finance)
         Quick Actions: always visible, individual links gated by @can
    ═══════════════════════════════════════════════════════════════════════ --}}
    @php
        $bottomCount = 1; // quick actions always present
        if ($canAlerts)  $bottomCount++;
        if ($canRevenue) $bottomCount++;
        $bottomGrid = match($bottomCount) {
            1 => '',
            2 => 'md:grid-cols-2',
            default => 'md:grid-cols-3',
        };
    @endphp

    <div class="grid gap-6 {{ $bottomGrid }}">

        {{-- ── Alerts ── --}}
        @if($canAlerts)
        <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Alerts</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Operational issues right now</p>
            </div>
            <div class="p-5 space-y-3">

                {{-- Late check-ins --}}
                @php $late = $lateAppointments ?? 0; @endphp
                <div class="flex items-center gap-3 p-3 rounded-xl
                    {{ $late > 0 ? 'bg-amber-50 ring-1 ring-amber-200 dark:bg-amber-900/10 dark:ring-amber-800' : 'bg-gray-50 dark:bg-gray-700/30' }}">
                    <div class="flex items-center justify-center w-9 h-9 rounded-xl flex-shrink-0
                        {{ $late > 0 ? 'bg-amber-100 dark:bg-amber-900/30' : 'bg-gray-100 dark:bg-gray-700' }}">
                        <i class="fa-solid fa-clock-rotate-left text-sm {{ $late > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400' }}"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold {{ $late > 0 ? 'text-amber-900 dark:text-amber-200' : 'text-gray-500 dark:text-gray-400' }}">
                            {{ $late }} Late Check-in{{ $late !== 1 ? 's' : '' }}
                        </p>
                        <p class="text-xs {{ $late > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400' }}">
                            {{ $late > 0 ? 'Pending past their start time' : 'All on time' }}
                        </p>
                    </div>
                </div>

                {{-- Cancellations today --}}
                @php $cancelled = $noShows ?? 0; @endphp
                <div class="flex items-center gap-3 p-3 rounded-xl
                    {{ $cancelled > 0 ? 'bg-red-50 ring-1 ring-red-200 dark:bg-red-900/10 dark:ring-red-800' : 'bg-gray-50 dark:bg-gray-700/30' }}">
                    <div class="flex items-center justify-center w-9 h-9 rounded-xl flex-shrink-0
                        {{ $cancelled > 0 ? 'bg-red-100 dark:bg-red-900/30' : 'bg-gray-100 dark:bg-gray-700' }}">
                        <i class="fa-solid fa-user-xmark text-sm {{ $cancelled > 0 ? 'text-red-500' : 'text-gray-400' }}"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold {{ $cancelled > 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">
                            {{ $cancelled }} Cancellation{{ $cancelled !== 1 ? 's' : '' }} Today
                        </p>
                        <p class="text-xs {{ $cancelled > 0 ? 'text-red-500 dark:text-red-400' : 'text-gray-400' }}">
                            {{ $cancelled > 0 ? 'Slots freed up today' : 'No cancellations' }}
                        </p>
                    </div>
                </div>

                {{-- Overloaded therapists --}}
                @php $overloaded = $overbookedTherapists ?? 0; @endphp
                <div class="flex items-center gap-3 p-3 rounded-xl
                    {{ $overloaded > 0 ? 'bg-red-50 ring-1 ring-red-200 dark:bg-red-900/10 dark:ring-red-800' : 'bg-gray-50 dark:bg-gray-700/30' }}">
                    <div class="flex items-center justify-center w-9 h-9 rounded-xl flex-shrink-0
                        {{ $overloaded > 0 ? 'bg-red-100 dark:bg-red-900/30' : 'bg-gray-100 dark:bg-gray-700' }}">
                        <i class="fa-solid fa-user-nurse text-sm {{ $overloaded > 0 ? 'text-red-500' : 'text-gray-400' }}"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold {{ $overloaded > 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">
                            {{ $overloaded }} Overloaded
                        </p>
                        <p class="text-xs {{ $overloaded > 0 ? 'text-red-500 dark:text-red-400' : 'text-gray-400' }}">
                            {{ $overloaded > 0 ? 'Therapist(s) over 8 bookings' : 'All loads normal' }}
                        </p>
                    </div>
                </div>

                @if($late === 0 && $cancelled === 0 && $overloaded === 0)
                <div class="flex items-center justify-center gap-2 pt-1">
                    <i class="fa-solid fa-circle-check text-emerald-500 text-sm"></i>
                    <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Everything looks good!</span>
                </div>
                @endif

            </div>
        </div>
        @endif

        {{-- ── Today's Breakdown ── --}}
        @if($canRevenue)
        <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Today's Breakdown</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Service + status summary</p>
            </div>
            <div class="p-5 space-y-4">

                <div>
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Top Service</p>
                    <div class="flex items-center gap-3 mt-2 p-3 bg-[#8B7355]/5 rounded-xl ring-1 ring-[#8B7355]/20">
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-[#8B7355]/15 flex-shrink-0">
                            <i class="fa-solid fa-spa text-[#8B7355] text-sm"></i>
                        </div>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate">
                            {{ $topServiceLabel ?? 'No bookings yet' }}
                        </p>
                    </div>
                </div>

                @php
                    $statusBars = [
                        ['label' => 'Completed', 'count' => $completedToday ?? 0, 'color' => 'bg-slate-400'],
                        ['label' => 'Ongoing',   'count' => $ongoingToday   ?? 0, 'color' => 'bg-emerald-500'],
                        ['label' => 'Pending',   'count' => $pendingToday   ?? 0, 'color' => 'bg-amber-400'],
                        ['label' => 'Reserved',  'count' => $reservedToday  ?? 0, 'color' => 'bg-blue-400'],
                        ['label' => 'Cancelled', 'count' => $cancelledToday ?? 0, 'color' => 'bg-red-400'],
                    ];
                    $total = $todayCount ?? 0;
                @endphp
                <div>
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400 mb-2">Status Split</p>
                    <div class="space-y-2">
                        @foreach($statusBars as $s)
                        @if($s['count'] > 0)
                        <div class="flex items-center gap-3">
                            <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400 w-16 flex-shrink-0">{{ $s['label'] }}</span>
                            <div class="flex-1 h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="{{ $s['color'] }} h-full rounded-full"
                                     style="width: {{ $total > 0 ? round(($s['count'] / $total) * 100) : 0 }}%"></div>
                            </div>
                            <span class="text-[10px] font-semibold text-gray-600 dark:text-gray-400 w-4 text-right">{{ $s['count'] }}</span>
                        </div>
                        @endif
                        @endforeach
                        @if($total === 0)
                        <p class="text-xs text-gray-400 italic">No appointments yet today.</p>
                        @endif
                    </div>
                </div>

            </div>
        </div>
        @endif

        {{-- ── Quick Actions — always present, links gated by individual @can ── --}}
        <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Quick Actions</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Jump to common pages</p>
            </div>
            <div class="p-4 grid grid-cols-2 gap-2">

                @can('book appointments')
                <a href="{{ route('booking') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-calendar-plus text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">New Booking</span>
                </a>
                @endcan

                @can('view appointments')
                <a href="{{ route('appointments.index') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-calendar-check text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">Appointments</span>
                </a>
                @endcan

                @can('view schedule')
                <a href="{{ route('schedule.index') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-table-cells text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">Schedule</span>
                </a>
                @endcan

                @can('view services')
                <a href="{{ route('services.index') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-spa text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">Services</span>
                </a>
                @endcan

                @can('view staff')
                <a href="{{ route('staff.index') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-users text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">Staff</span>
                </a>
                @endcan

                @can('view attendance')
                <a href="{{ route('attendance.index') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-clipboard-user text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">Attendance</span>
                </a>
                @endcan

                @can('view reports')
                <a href="{{ route('reports.index') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-chart-bar text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">Reports</span>
                </a>
                @endcan

                @can('view revenue')
                <a href="{{ route('revenue.index') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-peso-sign text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">Revenue</span>
                </a>
                @endcan

                @can('view decision support')
                <a href="{{ route('decision-support.index') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-lightbulb text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">Insights</span>
                </a>
                @endcan

                @can('view hiring')
                <a href="{{ route('hiring.index') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-user-plus text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">Hiring</span>
                </a>
                @endcan

            </div>
        </div>

    </div>{{-- end bottom row --}}

</div>

@endsection