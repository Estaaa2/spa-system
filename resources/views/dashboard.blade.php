@extends('layouts.app')

@section('title', 'Dashboard')
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

    // Resolve all permission flags once using hasBranchPermission() so that
    // branch-level overrides stored in branch_role_permissions are respected.
    $canKpis            = $user->hasBranchPermission('view dashboard kpis');
    $canRevenue         = $user->hasBranchPermission('view dashboard revenue');
    $canTimeline        = $user->hasBranchPermission('view dashboard timeline');
    $canTherapistStatus = $user->hasBranchPermission('view dashboard therapist status');
    $canAlerts          = $user->hasBranchPermission('view dashboard alerts');
    $canBookingBtn      = $user->hasBranchPermission('view dashboard booking button');
    $canMyToday         = $user->hasBranchPermission('view dashboard my today');

    // Quick Actions permission flags — also branch-aware.
    $canBookAppointments  = $user->hasBranchPermission('book appointments');
    $canViewAppointments  = $user->hasBranchPermission('view appointments');
    $canViewSchedule      = $user->hasBranchPermission('view schedule');
    $canViewServices      = $user->hasBranchPermission('view services');
    $canViewStaff         = $user->hasBranchPermission('view staff');
    $canViewAttendance    = $user->hasBranchPermission('view attendance');
    $canViewReports       = $user->hasBranchPermission('view reports');
    $canViewRevenue       = $user->hasBranchPermission('view revenue');
    $canViewDSS           = $user->hasBranchPermission('view decision support');
    $canViewHiring        = $user->hasBranchPermission('view hiring');
@endphp

<div class="p-6 mx-auto space-y-6 max-w-7xl">

    {{-- ════════════════════════════════════════════════════════════════════
         HEADER
    ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-full
                    bg-white border border-gray-200 shadow-sm dark:bg-gray-800 dark:border-gray-700
                    text-gray-500 dark:text-gray-400 select-none">
            <span id="liveIndicatorDot"
                class="inline-block w-2 h-2 transition-colors duration-300 bg-gray-300 rounded-full dark:bg-gray-600"></span>
            <span id="liveIndicatorLabel">Connecting…</span>
        </div>
    </div>

    <x-page-header
        title="Dashboard"
        :subtitle="$canMyToday ? $user->first_name . '\'s Schedule' : 'Overview of branch operations and activity.'"
    >
        <x-slot name="right">
            @if($canBookingBtn)
            <a href="{{ route('booking') }}"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-xl
                      bg-gradient-to-r from-[#8B7355] to-[#6F5430] shadow-sm hover:opacity-90 transition-opacity active:translate-y-0.5">
                <i class="text-xs fa-solid fa-plus"></i>
                New Booking
            </a>
            @endif
        </x-slot>
    </x-page-header>

    @if($pendingDeploymentResponse)
    <div id="deployment-response-card" class="overflow-hidden bg-white border shadow-sm border-amber-200 rounded-2xl dark:bg-gray-800 dark:border-amber-800/40">
        <div class="flex items-center gap-3 px-6 py-4 border-b border-amber-100 dark:border-amber-900/30 bg-amber-50/60 dark:bg-amber-900/10">
            <div class="flex items-center justify-center flex-shrink-0 w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-900/30">
                <i class="text-sm fa-solid fa-right-left text-amber-600 dark:text-amber-400"></i>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-amber-900 dark:text-amber-200">Branch Deployment Awaiting Your Response</h2>
                <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">
                    {{ $pendingDeploymentResponse->status === 'approved' ? 'Approved deployment' : 'Deployment request' }}
                    to {{ $pendingDeploymentResponse->toBranch->name ?? 'another branch' }}
                    — starts {{ \Carbon\Carbon::parse($pendingDeploymentResponse->start_date)->format('M d, Y') }}
                </p>
            </div>
        </div>

        <div class="px-6 py-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <p><span class="font-medium text-gray-800 dark:text-gray-200">From:</span> {{ $pendingDeploymentResponse->fromBranch->name ?? '—' }}</p>
                    <p><span class="font-medium text-gray-800 dark:text-gray-200">To:</span> {{ $pendingDeploymentResponse->toBranch->name ?? '—' }}</p>
                    @if($pendingDeploymentResponse->is_permanent)
                        <p class="mt-1 text-xs text-gray-500">Permanent reassignment</p>
                    @else
                        <p class="mt-1 text-xs text-gray-500">Ends {{ $pendingDeploymentResponse->end_date ? \Carbon\Carbon::parse($pendingDeploymentResponse->end_date)->format('M d, Y') : 'Open-ended' }}</p>
                    @endif
                    @if(!empty($pendingDeploymentResponse->notes))
                        <p class="mt-1 text-xs italic text-gray-500">"{{ $pendingDeploymentResponse->notes }}"</p>
                    @endif
                </div>

                <div class="flex items-center flex-shrink-0 gap-2">
                    <button type="button" id="deployment-decline-toggle"
                            class="px-4 py-2 text-xs font-semibold text-red-600 transition-colors border border-red-200 bg-red-50 rounded-xl hover:bg-red-100 dark:bg-red-900/10 dark:border-red-800 dark:text-red-400">
                        Decline
                    </button>
                    <form method="POST" action="{{ route('branch-deployments.staff-respond', $pendingDeploymentResponse) }}">
                        @csrf
                        <input type="hidden" name="response" value="accepted">
                        <button type="submit"
                                class="px-4 py-2 text-xs font-semibold text-white rounded-xl bg-gradient-to-r from-[#8B7355] to-[#6F5430] shadow-sm hover:opacity-90 transition-opacity">
                            Accept
                        </button>
                    </form>
                </div>
            </div>

            {{-- Decline reason panel — hidden until "Decline" is clicked --}}
            <div id="deployment-decline-panel" class="hidden pt-4 mt-4 border-t border-gray-200 border-dashed dark:border-gray-700">
                <form method="POST" action="{{ route('branch-deployments.staff-respond', $pendingDeploymentResponse) }}">
                    @csrf
                    <input type="hidden" name="response" value="declined">
                    <label for="decline_reason" class="text-xs font-semibold text-gray-600 dark:text-gray-400">
                        Please tell us why you're declining (required):
                    </label>
                    <textarea name="decline_reason" id="decline_reason" rows="2" required maxlength="1000"
                              class="mt-1.5 w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 focus:ring-[#8B7355] focus:border-[#8B7355]"
                              placeholder="e.g. Personal circumstances prevent relocation at this time"></textarea>
                    <div class="flex justify-end gap-2 mt-2">
                        <button type="button" id="deployment-decline-cancel"
                                class="px-3 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-1.5 text-xs font-semibold text-white bg-red-600 rounded-xl hover:bg-red-700 transition-colors">
                            Confirm Decline
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const toggleBtn = document.getElementById('deployment-decline-toggle');
            const cancelBtn = document.getElementById('deployment-decline-cancel');
            const panel     = document.getElementById('deployment-decline-panel');
            if (toggleBtn && panel) {
                toggleBtn.addEventListener('click', () => panel.classList.remove('hidden'));
            }
            if (cancelBtn && panel) {
                cancelBtn.addEventListener('click', () => panel.classList.add('hidden'));
            }
        }());
    </script>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════
         THERAPIST PERSONAL VIEW  (view dashboard my today)
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
                <p id="my-stat-total" class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $myStats['total'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Appointments assigned</p>
            </div>

            <div class="p-5 border shadow-sm bg-emerald-50 border-emerald-200 rounded-2xl dark:bg-emerald-900/10 dark:border-emerald-800">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold tracking-wide uppercase text-emerald-700 dark:text-emerald-400">Ongoing</p>
                    <div class="flex items-center justify-center w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                        <i class="text-sm fa-solid fa-spinner text-emerald-600 dark:text-emerald-400"></i>
                    </div>
                </div>
                <p id="my-stat-ongoing" class="mt-3 text-3xl font-bold text-emerald-900 dark:text-emerald-200">{{ $myStats['ongoing'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">In session right now</p>
            </div>

            <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Completed</p>
                    <div class="flex items-center justify-center w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800">
                        <i class="text-sm fa-solid fa-circle-check text-slate-500"></i>
                    </div>
                </div>
                <p id="my-stat-completed" class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $myStats['completed'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Done today</p>
            </div>

            <div class="p-5 border border-blue-200 shadow-sm bg-blue-50 rounded-2xl dark:bg-blue-900/10 dark:border-blue-800">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold tracking-wide text-blue-700 uppercase dark:text-blue-400">Remaining</p>
                    <div class="flex items-center justify-center w-8 h-8 bg-blue-100 rounded-xl dark:bg-blue-900/30">
                        <i class="text-sm text-blue-600 fa-regular fa-clock dark:text-blue-400"></i>
                    </div>
                </div>
                <p id="my-stat-remaining" class="mt-3 text-3xl font-bold text-blue-900 dark:text-blue-200">{{ $myStats['remaining'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-blue-700 dark:text-blue-400">Still queued</p>
            </div>

            <div class="p-4 border rounded-2xl {{ $myAttendanceToday && $myAttendanceToday->time_in && !$myAttendanceToday->time_out ? 'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/10 dark:border-emerald-800' : 'bg-amber-50 border-amber-200 dark:bg-amber-900/10 dark:border-amber-800' }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        @if(!$myAttendanceToday || !$myAttendanceToday->time_in)
                            <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">You haven't clocked in yet today</p>
                            <p class="text-xs text-amber-700 dark:text-amber-300">Clock in when you arrive so your attendance is recorded.</p>
                        @elseif(!$myAttendanceToday->time_out)
                            <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">Clocked in at {{ \Carbon\Carbon::parse($myAttendanceToday->time_in)->format('h:i A') }}</p>
                            <p class="text-xs text-emerald-700 dark:text-emerald-300">Don't forget to clock out before you leave.</p>
                        @else
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                Clocked {{ \Carbon\Carbon::parse($myAttendanceToday->time_in)->format('h:i A') }} – {{ \Carbon\Carbon::parse($myAttendanceToday->time_out)->format('h:i A') }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Today's attendance is complete.</p>
                        @endif
                    </div>
                    @if(!$myAttendanceToday || !$myAttendanceToday->time_in)
                        <form method="POST" action="{{ route('attendance.clock-in') }}">
                            @csrf
                            <button type="submit" class="px-4 py-2 text-sm font-semibold text-white rounded-xl bg-[#8B7355] hover:bg-[#7A6348]">Clock In</button>
                        </form>
                    @elseif(!$myAttendanceToday->time_out)
                        <form method="POST" action="{{ route('attendance.clock-out') }}">
                            @csrf
                            <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-xl hover:bg-red-700">Clock Out</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- Personal schedule timeline --}}
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">My Schedule Today</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Your appointments for {{ $now->format('M d, Y') }}</p>
                </div>
                @if($canViewSchedule)
                <a href="{{ route('schedule.index') }}"
                   class="text-xs font-medium text-[#8B7355] hover:text-[#6F5430] transition-colors">
                    Full schedule →
                </a>
                @endif
            </div>

            <div id="my-timeline-body">
                @if($myTodayAppointments->isEmpty())
                    <div class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-500">
                        <i class="mb-3 text-3xl fa-regular fa-calendar-check"></i>
                        <p class="text-sm font-medium">No appointments assigned to you today.</p>
                        <p class="mt-1 text-xs text-gray-400">Check your upcoming schedule below.</p>
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
                            <div class="flex-shrink-0 w-16 text-right">
                                <p class="text-xs font-bold text-gray-800 dark:text-white tabular-nums">{{ $startC->format('h:i') }}</p>
                                <p class="text-[10px] font-semibold text-gray-400">{{ $startC->format('A') }}</p>
                                <div class="w-px h-4 mx-auto mt-1 bg-gray-200 dark:bg-gray-700"></div>
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
                                        <p class="text-sm font-semibold text-gray-900 truncate dark:text-white">
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
                                @if(in_array($booking->status, ['reserved', 'pending']))
                                    @if($booking->has_pending_reassignment)
                                    <span class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] font-semibold text-amber-700 bg-amber-100 rounded-lg dark:bg-amber-900/30 dark:text-amber-300">
                                        <i class="fa-solid fa-clock"></i> Reassignment Pending
                                    </span>
                                    @else
                                    <button type="button" onclick="openReassignFlagModal(this)"
                                            data-reassign-flag-btn="{{ $booking->id }}"
                                            data-id="{{ $booking->id }}"
                                            data-customer="{{ $booking->customer_name ?? 'Walk-in Customer' }}"
                                            data-treatment="{{ $booking->treatment_display ?? '—' }}"
                                            data-date="{{ \Carbon\Carbon::parse($booking->appointment_date)->format('M d, Y') }}"
                                            data-time="{{ $startC->format('h:i A') }}"
                                            class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                                        <i class="fa-solid fa-triangle-exclamation"></i> Can't Make It?
                                    </button>
                                    @endif
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Next upcoming outside today --}}
            <div id="my-next-wrapper" class="{{ $myNextAppointment ? '' : 'hidden' }} px-6 py-3 border-t border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/20">
                <p class="text-[10px] font-semibold tracking-wide text-gray-400 uppercase">Next Upcoming</p>
                <div class="flex items-center justify-between mt-1">
                    <div>
                        <p id="my-next-name" class="text-sm font-medium text-gray-800 dark:text-gray-200">
                            {{ ($myNextAppointment->customer_name ?? 'Walk-in Customer') }}
                            <span class="mx-1 font-normal text-gray-400">·</span>
                            {{ $myNextAppointment->treatment_display ?? '—' }}
                        </p>
                        <p id="my-next-date" class="text-xs text-gray-500 dark:text-gray-400">
                            @if($myNextAppointment)
                                {{ \Carbon\Carbon::parse($myNextAppointment->appointment_date)->format('D, M j') }}
                                at {{ \Carbon\Carbon::parse($myNextAppointment->start_time)->format('h:i A') }}
                            @endif
                        </p>
                    </div>
                    <span class="px-2 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded-full dark:bg-blue-900/40 dark:text-blue-300">
                        Reserved
                    </span>
                </div>
            </div>

        </div>

        @include('dashboard.partials.reassignment-flag-modal')

    @endif {{-- end my today --}}


    {{-- ════════════════════════════════════════════════════════════════════
         KPI CARDS  (view dashboard kpis)
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
                <p id="kpi-today-count" class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $todayCount ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Total appointments</p>
            </div>

            <div class="p-5 border shadow-sm bg-emerald-50 border-emerald-200 rounded-2xl dark:bg-emerald-900/10 dark:border-emerald-800">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold tracking-wide uppercase text-emerald-700 dark:text-emerald-400">Ongoing</p>
                    <div class="flex items-center justify-center w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                        <i class="text-sm fa-solid fa-spinner text-emerald-600 dark:text-emerald-400"></i>
                    </div>
                </div>
                <p id="kpi-ongoing" class="mt-3 text-3xl font-bold text-emerald-900 dark:text-emerald-200">{{ $ongoingToday ?? 0 }}</p>
                <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">In service right now</p>
            </div>

            <div class="p-5 border shadow-sm bg-amber-50 border-amber-200 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold tracking-wide uppercase text-amber-700 dark:text-amber-400">Pending</p>
                    <div class="flex items-center justify-center w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-900/30">
                        <i class="text-sm fa-solid fa-circle-exclamation text-amber-600 dark:text-amber-400"></i>
                    </div>
                </div>
                <p id="kpi-pending" class="mt-3 text-3xl font-bold text-amber-900 dark:text-amber-200">{{ $pendingToday ?? 0 }}</p>
                <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">Needs check-in</p>
            </div>

            @if($canRevenue)
            <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Collected</p>
                    <div class="flex items-center justify-center w-8 h-8 rounded-xl bg-[#8B7355]/10">
                        <i class="fa-solid fa-peso-sign text-[#8B7355] text-sm"></i>
                    </div>
                </div>
                <p id="kpi-collected" class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">₱{{ number_format($collectedToday ?? 0, 0) }}</p>
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
                <p id="kpi-upcoming-week" class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $upcomingWeek ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Next 7 days</p>
            </div>
            @endif

        </div>

        {{-- Secondary stat row --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">

            <div class="p-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Completed</p>
                <p id="kpi-completed" class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $completedToday ?? 0 }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Done today</p>
            </div>

            <div class="p-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Reserved</p>
                <p id="kpi-reserved" class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $reservedToday ?? 0 }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Confirmed, not yet started</p>
            </div>

            @if($canRevenue)
            <div class="p-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Upcoming</p>
                <p id="kpi-upcoming-week-2" class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $upcomingWeek ?? 0 }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Next 7 days</p>
            </div>

            <div class="p-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Today's Source</p>
                <div class="flex items-end gap-3 mt-2">
                    <div>
                        <p id="kpi-online" class="text-2xl font-bold text-violet-700 dark:text-violet-400">{{ $onlineToday ?? 0 }}</p>
                        <p class="text-[10px] font-semibold text-violet-600 dark:text-violet-400 uppercase">Online</p>
                    </div>
                    <span class="mb-1 text-lg font-light text-gray-300 dark:text-gray-600">/</span>
                    <div>
                        <p id="kpi-walkin" class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $walkInToday ?? 0 }}</p>
                        <p class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase">Walk-in</p>
                    </div>
                </div>
            </div>
            @else
            <div class="p-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Cancelled</p>
                <p id="kpi-cancelled" class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $cancelledToday ?? 0 }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Cancelled today</p>
            </div>
            <div></div>
            @endif

        </div>

    @endif {{-- end kpis --}}


    {{-- ════════════════════════════════════════════════════════════════════
         TIMELINE + THERAPIST STATUS
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
                @if($canViewAppointments)
                <a href="{{ route('appointments.index') }}"
                   class="text-xs font-medium text-[#8B7355] hover:text-[#6F5430] transition-colors">
                    Full list →
                </a>
                @endif
            </div>

            <div id="timeline-body" class="divide-y divide-gray-100 dark:divide-gray-700 max-h-[460px] overflow-y-auto">
                @if($todayAppointments->isEmpty())
                    <div id="timeline-empty" class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-500">
                        <i class="mb-3 text-3xl fa-regular fa-calendar-xmark"></i>
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
                    @foreach($todayAppointments as $booking)
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
                        <div class="flex-shrink-0 w-16 text-right">
                            <p class="text-xs font-bold text-gray-800 dark:text-white tabular-nums">{{ $startC->format('h:i') }}</p>
                            <p class="text-[10px] font-semibold text-gray-400">{{ $startC->format('A') }}</p>
                            <div class="w-px h-4 mx-auto mt-1 bg-gray-200 dark:bg-gray-700"></div>
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
                                    <p class="text-sm font-semibold text-gray-900 truncate dark:text-white">
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
                @endif
            </div>

            <div id="next-appointment-wrapper" class="{{ $nextAppointment ? '' : 'hidden' }} px-6 py-3 border-t border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/20">
                <p class="text-[10px] font-semibold tracking-wide text-gray-400 uppercase">Next Upcoming</p>
                <div class="flex items-center justify-between mt-1">
                    <div>
                        <p id="next-appointment-name" class="text-sm font-medium text-gray-800 dark:text-gray-200">
                            @if($nextAppointment)
                                {{ $nextAppointment->customer_name ?? 'Walk-in' }}
                                <span class="mx-1 font-normal text-gray-400">·</span>
                                {{ $nextAppointment->treatment_display ?? '—' }}
                            @endif
                        </p>
                        <p id="next-appointment-date" class="text-xs text-gray-500 dark:text-gray-400">
                            @if($nextAppointment)
                                {{ \Carbon\Carbon::parse($nextAppointment->appointment_date)->format('D, M j') }}
                                at {{ \Carbon\Carbon::parse($nextAppointment->start_time)->format('h:i A') }}
                            @endif
                        </p>
                    </div>
                    <span class="px-2 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded-full dark:bg-blue-900/40 dark:text-blue-300">
                        Reserved
                    </span>
                </div>
            </div>

        </div>
        @endif

        {{-- ── Therapist Status Panel ── --}}
        @if($canTherapistStatus)
        <div class="{{ $canTimeline ? 'lg:col-span-2' : '' }} bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 overflow-hidden">

            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Therapist Status</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Today's workload per therapist</p>
            </div>

            <div id="therapist-panel-body">
                @if($therapists->isEmpty())
                    <div class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-500">
                        <i class="mb-3 text-3xl fa-solid fa-user-nurse"></i>
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
                                <div class="flex items-center min-w-0 gap-3">
                                    <div class="flex items-center justify-center w-9 h-9 rounded-full bg-[#8B7355]/15 text-[#8B7355] flex-shrink-0 text-sm font-bold">
                                        {{ strtoupper(substr($therapist->first_name ?? '?', 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 truncate dark:text-white">
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

        </div>
        @endif

    </div>
    @endif {{-- end timeline / therapist row --}}


    {{-- ════════════════════════════════════════════════════════════════════
         BOTTOM ROW: Alerts · Breakdown · Quick Actions
    ═══════════════════════════════════════════════════════════════════════ --}}
    @php
        $bottomCount = 1;
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
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Alerts</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Operational issues right now</p>
            </div>
            <div id="alerts-body" class="p-5 space-y-3">

                @php $late = $lateAppointments ?? 0; @endphp
                <div id="alert-late" class="flex items-center gap-3 p-3 rounded-xl
                    {{ $late > 0 ? 'bg-amber-50 ring-1 ring-amber-200 dark:bg-amber-900/10 dark:ring-amber-800' : 'bg-gray-50 dark:bg-gray-700/30' }}">
                    <div class="flex items-center justify-center w-9 h-9 rounded-xl flex-shrink-0
                        {{ $late > 0 ? 'bg-amber-100 dark:bg-amber-900/30' : 'bg-gray-100 dark:bg-gray-700' }}">
                        <i class="fa-solid fa-clock-rotate-left text-sm {{ $late > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400' }}"></i>
                    </div>
                    <div>
                        <p id="alert-late-title" class="text-sm font-semibold {{ $late > 0 ? 'text-amber-900 dark:text-amber-200' : 'text-gray-500 dark:text-gray-400' }}">
                            {{ $late }} Late Check-in{{ $late !== 1 ? 's' : '' }}
                        </p>
                        <p id="alert-late-sub" class="text-xs {{ $late > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400' }}">
                            {{ $late > 0 ? 'Pending past their start time' : 'All on time' }}
                        </p>
                    </div>
                </div>

                @php $cancelled = $noShows ?? 0; @endphp
                <div id="alert-noshow" class="flex items-center gap-3 p-3 rounded-xl
                    {{ $cancelled > 0 ? 'bg-red-50 ring-1 ring-red-200 dark:bg-red-900/10 dark:ring-red-800' : 'bg-gray-50 dark:bg-gray-700/30' }}">
                    <div class="flex items-center justify-center w-9 h-9 rounded-xl flex-shrink-0
                        {{ $cancelled > 0 ? 'bg-red-100 dark:bg-red-900/30' : 'bg-gray-100 dark:bg-gray-700' }}">
                        <i class="fa-solid fa-user-xmark text-sm {{ $cancelled > 0 ? 'text-red-500' : 'text-gray-400' }}"></i>
                    </div>
                    <div>
                        <p id="alert-noshow-title" class="text-sm font-semibold {{ $cancelled > 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">
                            {{ $cancelled }} Cancellation{{ $cancelled !== 1 ? 's' : '' }} Today
                        </p>
                        <p id="alert-noshow-sub" class="text-xs {{ $cancelled > 0 ? 'text-red-500 dark:text-red-400' : 'text-gray-400' }}">
                            {{ $cancelled > 0 ? 'Slots freed up today' : 'No cancellations' }}
                        </p>
                    </div>
                </div>

                @php $overloaded = $overbookedTherapists ?? 0; @endphp
                <div id="alert-overloaded" class="flex items-center gap-3 p-3 rounded-xl
                    {{ $overloaded > 0 ? 'bg-red-50 ring-1 ring-red-200 dark:bg-red-900/10 dark:ring-red-800' : 'bg-gray-50 dark:bg-gray-700/30' }}">
                    <div class="flex items-center justify-center w-9 h-9 rounded-xl flex-shrink-0
                        {{ $overloaded > 0 ? 'bg-red-100 dark:bg-red-900/30' : 'bg-gray-100 dark:bg-gray-700' }}">
                        <i class="fa-solid fa-user-nurse text-sm {{ $overloaded > 0 ? 'text-red-500' : 'text-gray-400' }}"></i>
                    </div>
                    <div>
                        <p id="alert-overloaded-title" class="text-sm font-semibold {{ $overloaded > 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">
                            {{ $overloaded }} Overloaded
                        </p>
                        <p id="alert-overloaded-sub" class="text-xs {{ $overloaded > 0 ? 'text-red-500 dark:text-red-400' : 'text-gray-400' }}">
                            {{ $overloaded > 0 ? 'Therapist(s) over 8 bookings' : 'All loads normal' }}
                        </p>
                    </div>
                </div>

                <div id="alert-all-good" class="{{ ($late === 0 && $cancelled === 0 && $overloaded === 0) ? '' : 'hidden' }} flex items-center justify-center gap-2 pt-1">
                    <i class="text-sm fa-solid fa-circle-check text-emerald-500"></i>
                    <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Everything looks good!</span>
                </div>

            </div>
        </div>
        @endif

        {{-- ── Today's Breakdown ── --}}
        @if($canRevenue)
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
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
                        <p id="breakdown-top-service" class="text-sm font-semibold text-gray-800 truncate dark:text-gray-200">
                            {{ $topServiceLabel ?? 'No bookings yet' }}
                        </p>
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Status Split</p>
                    <div id="breakdown-bars" class="space-y-2">
                        @php
                            $statusBars = [
                                ['id' => 'bar-completed', 'label' => 'Completed', 'count' => $completedToday ?? 0, 'color' => 'bg-slate-400'],
                                ['id' => 'bar-ongoing',   'label' => 'Ongoing',   'count' => $ongoingToday   ?? 0, 'color' => 'bg-emerald-500'],
                                ['id' => 'bar-pending',   'label' => 'Pending',   'count' => $pendingToday   ?? 0, 'color' => 'bg-amber-400'],
                                ['id' => 'bar-reserved',  'label' => 'Reserved',  'count' => $reservedToday  ?? 0, 'color' => 'bg-blue-400'],
                                ['id' => 'bar-cancelled', 'label' => 'Cancelled', 'count' => $cancelledToday ?? 0, 'color' => 'bg-red-400'],
                            ];
                            $total = $todayCount ?? 0;
                        @endphp
                        @foreach($statusBars as $s)
                        <div id="{{ $s['id'] }}-row" class="{{ $s['count'] > 0 ? '' : 'hidden' }} flex items-center gap-3">
                            <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400 w-16 flex-shrink-0">{{ $s['label'] }}</span>
                            <div class="flex-1 h-2 overflow-hidden bg-gray-100 rounded-full dark:bg-gray-700">
                                <div id="{{ $s['id'] }}-fill" class="{{ $s['color'] }} h-full rounded-full transition-all duration-500"
                                     style="width: {{ $total > 0 ? round(($s['count'] / $total) * 100) : 0 }}%"></div>
                            </div>
                            <span id="{{ $s['id'] }}-count" class="text-[10px] font-semibold text-gray-600 dark:text-gray-400 w-4 text-right">{{ $s['count'] }}</span>
                        </div>
                        @endforeach
                        <p id="breakdown-empty" class="{{ $total === 0 ? '' : 'hidden' }} text-xs text-gray-400 italic">No appointments yet today.</p>
                    </div>
                </div>

            </div>
        </div>
        @endif

        {{-- ── Quick Actions ── --}}
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Quick Actions</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Jump to common pages</p>
            </div>
            <div class="grid grid-cols-2 gap-2 p-4">

                @if($canBookAppointments)
                <a href="{{ route('booking') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-calendar-plus text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">New Booking</span>
                </a>
                @endif

                @if($canViewAppointments)
                <a href="{{ route('appointments.index') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-calendar-check text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">Appointments</span>
                </a>
                @endif

                @if($canViewSchedule)
                <a href="{{ route('schedule.index') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-table-cells text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">Schedule</span>
                </a>
                @endif

                @if($canViewServices)
                <a href="{{ route('services.index') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-spa text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">Services</span>
                </a>
                @endif

                @if($canViewStaff)
                <a href="{{ route('staff.index') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-users text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">Staff</span>
                </a>
                @endif

                @if($canViewAttendance)
                <a href="{{ route('attendance.index') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-clipboard-user text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">Attendance</span>
                </a>
                @endif

                @if($canViewReports)
                <a href="{{ route('reports.index') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-chart-bar text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">Reports</span>
                </a>
                @endif

                @if($canViewRevenue)
                <a href="{{ route('revenue.index') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-peso-sign text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">Revenue</span>
                </a>
                @endif

                @if($canViewDSS)
                <a href="{{ route('decision-support.index') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-lightbulb text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">Insights</span>
                </a>
                @endif

                @if($canViewHiring)
                <a href="{{ route('hiring.index') }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 hover:border-[#8B7355]/30 transition-colors group">
                    <i class="fa-solid fa-user-plus text-[#8B7355] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">Hiring</span>
                </a>
                @endif

            </div>
        </div>

    </div>{{-- end bottom row --}}

</div>{{-- end max-w-7xl --}}


{{-- ════════════════════════════════════════════════════════════════════════
     LIVE POLLING SCRIPT
     Polls /dashboard/live-data every 60 seconds and patches the DOM
     in-place. Only updates elements that exist on the page — sections
     hidden by permissions are simply skipped.
═════════════════════════════════════════════════════════════════════════ --}}
<script>
(function () {
    const POLL_MS  = 30000; // 30 seconds
    const LIVE_URL = '{{ route('dashboard.live-data') }}';
    const dot   = document.getElementById('liveIndicatorDot');
    const label = document.getElementById('liveIndicatorLabel');

    let lastUpdatedAt = null;
    let tickTimer     = null;

    // ── Helpers ──────────────────────────────────────────────────────────────
    function setLiveStatus(state) {
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
        return `${Math.round(sec / 60)}m ago`;
    }

    function startTickTimer() {
        if (tickTimer) clearInterval(tickTimer);
        tickTimer = setInterval(() => {
            if (lastUpdatedAt) label.textContent = `Updated ${timeAgo(lastUpdatedAt)}`;
        }, 10000);
    }

    // ── Tiny helpers ─────────────────────────────────────────────────────────
    function set(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    function php(n) {
        return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function esc(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // ── Status / source CSS class maps (mirrors Blade $statusClasses) ────────
    const STATUS_CLASSES = {
        reserved:  'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        pending:   'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        ongoing:   'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        completed: 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
        cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    };
    const SOURCE_CLASSES = {
        online:  'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
        walk_in: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
        staff:   'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
        '':      'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    };
    function stClass(st)  { return STATUS_CLASSES[st]  ?? 'bg-gray-100 text-gray-600'; }
    function srcClass(src){ return SOURCE_CLASSES[src] ?? SOURCE_CLASSES['']; }

    // ── Dot color for timeline status dots ───────────────────────────────────
    function dotClass(status) {
        return {
            ongoing:   'bg-emerald-500 border-emerald-500 ring-2 ring-emerald-200 dark:ring-emerald-800',
            pending:   'bg-amber-400 border-amber-400',
            reserved:  'bg-blue-400 border-blue-400',
            completed: 'bg-gray-300 border-gray-300',
            cancelled: 'bg-red-300 border-red-300',
        }[status] ?? '';
    }

    // ── KPI updater ───────────────────────────────────────────────────────────
    function updateKpis(kpis) {
        set('kpi-today-count',   kpis.today_count);
        set('kpi-ongoing',       kpis.ongoing_today);
        set('kpi-pending',       kpis.pending_today);
        set('kpi-reserved',      kpis.reserved_today);
        set('kpi-completed',     kpis.completed_today);
        set('kpi-cancelled',     kpis.cancelled_today);
        set('kpi-upcoming-week', kpis.upcoming_week);
        set('kpi-upcoming-week-2', kpis.upcoming_week);
    }

    // ── Revenue updater ───────────────────────────────────────────────────────
    function updateRevenue(rev) {
        set('kpi-collected',      php(rev.collected_today));
        set('kpi-online',         rev.online_today);
        set('kpi-walkin',         rev.walk_in_today);
        set('breakdown-top-service', rev.top_service_label ?? 'No bookings yet');
    }

    // ── Breakdown status bars ─────────────────────────────────────────────────
    function updateBreakdownBars(kpis) {
        const total = kpis.today_count;
        const bars = [
            { id: 'bar-completed', count: kpis.completed_today },
            { id: 'bar-ongoing',   count: kpis.ongoing_today   },
            { id: 'bar-pending',   count: kpis.pending_today   },
            { id: 'bar-reserved',  count: kpis.reserved_today  },
            { id: 'bar-cancelled', count: kpis.cancelled_today },
        ];

        bars.forEach(({ id, count }) => {
            const row  = document.getElementById(id + '-row');
            const fill = document.getElementById(id + '-fill');
            const cnt  = document.getElementById(id + '-count');
            if (!row) return;

            row.classList.toggle('hidden', count === 0);
            if (cnt) cnt.textContent = count;
            if (fill) fill.style.width = (total > 0 ? Math.round((count / total) * 100) : 0) + '%';
        });

        const emptyEl = document.getElementById('breakdown-empty');
        if (emptyEl) emptyEl.classList.toggle('hidden', total > 0);
    }

    // ── Alerts updater ────────────────────────────────────────────────────────
    function updateAlerts(alerts) {
        const late      = alerts.late_appointments;
        const cancelled = alerts.no_shows;
        const overloaded= alerts.overbooked_therapists;

        // Late check-ins
        const lateEl = document.getElementById('alert-late');
        if (lateEl) {
            lateEl.className = 'flex items-center gap-3 p-3 rounded-xl ' +
                (late > 0 ? 'bg-amber-50 ring-1 ring-amber-200 dark:bg-amber-900/10 dark:ring-amber-800' : 'bg-gray-50 dark:bg-gray-700/30');
        }
        set('alert-late-title', late + ' Late Check-in' + (late !== 1 ? 's' : ''));
        const lateTitleEl = document.getElementById('alert-late-title');
        if (lateTitleEl) lateTitleEl.className = 'text-sm font-semibold ' + (late > 0 ? 'text-amber-900 dark:text-amber-200' : 'text-gray-500 dark:text-gray-400');
        set('alert-late-sub', late > 0 ? 'Pending past their start time' : 'All on time');
        const lateSubEl = document.getElementById('alert-late-sub');
        if (lateSubEl) lateSubEl.className = 'text-xs ' + (late > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400');

        // No-shows / cancellations
        const noshowEl = document.getElementById('alert-noshow');
        if (noshowEl) {
            noshowEl.className = 'flex items-center gap-3 p-3 rounded-xl ' +
                (cancelled > 0 ? 'bg-red-50 ring-1 ring-red-200 dark:bg-red-900/10 dark:ring-red-800' : 'bg-gray-50 dark:bg-gray-700/30');
        }
        set('alert-noshow-title', cancelled + ' Cancellation' + (cancelled !== 1 ? 's' : '') + ' Today');
        const noshowTitleEl = document.getElementById('alert-noshow-title');
        if (noshowTitleEl) noshowTitleEl.className = 'text-sm font-semibold ' + (cancelled > 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-500 dark:text-gray-400');
        set('alert-noshow-sub', cancelled > 0 ? 'Slots freed up today' : 'No cancellations');
        const noshowSubEl = document.getElementById('alert-noshow-sub');
        if (noshowSubEl) noshowSubEl.className = 'text-xs ' + (cancelled > 0 ? 'text-red-500 dark:text-red-400' : 'text-gray-400');

        // Overloaded therapists
        const overEl = document.getElementById('alert-overloaded');
        if (overEl) {
            overEl.className = 'flex items-center gap-3 p-3 rounded-xl ' +
                (overloaded > 0 ? 'bg-red-50 ring-1 ring-red-200 dark:bg-red-900/10 dark:ring-red-800' : 'bg-gray-50 dark:bg-gray-700/30');
        }
        set('alert-overloaded-title', overloaded + ' Overloaded');
        const overTitleEl = document.getElementById('alert-overloaded-title');
        if (overTitleEl) overTitleEl.className = 'text-sm font-semibold ' + (overloaded > 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-500 dark:text-gray-400');
        set('alert-overloaded-sub', overloaded > 0 ? 'Therapist(s) over 8 bookings' : 'All loads normal');
        const overSubEl = document.getElementById('alert-overloaded-sub');
        if (overSubEl) overSubEl.className = 'text-xs ' + (overloaded > 0 ? 'text-red-500 dark:text-red-400' : 'text-gray-400');

        // "All good" row
        const allGood = document.getElementById('alert-all-good');
        if (allGood) allGood.classList.toggle('hidden', late > 0 || cancelled > 0 || overloaded > 0);
    }

    // ── Timeline row HTML builder ─────────────────────────────────────────────
    function buildTimelineRow(b) {
        const isNow  = b.status === 'ongoing';
        const isPast = b.status === 'completed' || b.status === 'cancelled';
        const rowBg  = isNow ? 'bg-emerald-50/60 dark:bg-emerald-900/10' : '';
        const opacity= isPast ? 'opacity-60' : '';

        return `
        <div class="flex items-start gap-4 px-6 py-4 transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/40 ${rowBg} ${opacity}">
            <div class="flex-shrink-0 w-16 text-right">
                <p class="text-xs font-bold text-gray-800 dark:text-white tabular-nums">${esc(b.start_time_fmt)}</p>
                <p class="text-[10px] font-semibold text-gray-400">${esc(b.start_ampm)}</p>
                <div class="w-px h-4 mx-auto mt-1 bg-gray-200 dark:bg-gray-700"></div>
                <p class="text-[10px] text-gray-400 tabular-nums">${esc(b.end_time_fmt)}</p>
            </div>
            <div class="flex-shrink-0 mt-1.5">
                <div class="w-2.5 h-2.5 rounded-full border-2 ${dotClass(b.status)}"></div>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate dark:text-white">${esc(b.customer_name)}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">${esc(b.treatment_display)}</p>
                        ${b.service_type === 'in_home' && b.customer_address ? `
                        <p class="text-[10px] text-violet-600 dark:text-violet-400 mt-0.5 flex items-center gap-1">
                            <i class="fa-solid fa-house"></i> Home service · ${esc(b.customer_address)}
                        </p>` : ''}
                    </div>
                    <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 text-[10px] font-semibold rounded-full ${stClass(b.status)}">
                        ${b.status.charAt(0).toUpperCase() + b.status.slice(1)}
                    </span>
                </div>
                <div class="flex items-center gap-3 mt-1.5">
                    <span class="text-[10px] text-gray-400 flex items-center gap-1">
                        <i class="fa-solid fa-user-nurse text-[#8B7355]"></i>
                        ${esc(b.therapist_name)}
                    </span>
                    <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium rounded-full ${srcClass(b.booking_source)}">
                        ${(b.booking_source || 'STAFF').toUpperCase()}
                    </span>
                </div>
                ${b.customer_phone ? `
                <p class="text-[10px] text-gray-400 mt-1.5 flex items-center gap-1">
                    <i class="fa-solid fa-phone text-[#8B7355]"></i> ${esc(b.customer_phone)}
                </p>` : ''}
            </div>
        </div>`;
    }

    // ── Timeline updater ──────────────────────────────────────────────────────
    function updateTimeline(data) {
        const body = document.getElementById('timeline-body');
        if (!body) return;

        if (!data.appointments.length) {
            body.innerHTML = `
                <div class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-500">
                    <i class="mb-3 text-3xl fa-regular fa-calendar-xmark"></i>
                    <p class="text-sm">No appointments scheduled today.</p>
                </div>`;
        } else {
            body.innerHTML = '<div class="divide-y divide-gray-100 dark:divide-gray-700 max-h-[460px] overflow-y-auto">'
                + data.appointments.map(buildTimelineRow).join('')
                + '</div>';
        }

        // Next upcoming footer
        const wrapper  = document.getElementById('next-appointment-wrapper');
        const nameEl   = document.getElementById('next-appointment-name');
        const dateEl   = document.getElementById('next-appointment-date');
        const next     = data.next_appointment;

        if (wrapper) wrapper.classList.toggle('hidden', !next);
        if (next) {
            if (nameEl) nameEl.innerHTML = `${esc(next.customer_name)} <span class="mx-1 font-normal text-gray-400">·</span> ${esc(next.treatment_display)}`;
            if (dateEl) dateEl.textContent = `${next.appointment_date_fmt} at ${next.appointment_date_at}`;
        }
    }

    // ── Therapist panel updater ───────────────────────────────────────────────
    function updateTherapistPanel(therapists) {
        const body = document.getElementById('therapist-panel-body');
        if (!body) return;

        if (!therapists.length) {
            body.innerHTML = `
                <div class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-500">
                    <i class="mb-3 text-3xl fa-solid fa-user-nurse"></i>
                    <p class="text-sm">No active therapists assigned.</p>
                </div>`;
            return;
        }

        const capacity = 8;
        const rows = therapists.map(t => {
            const total     = t.total_today;
            const ongoing   = t.ongoing_count;
            const done      = t.completed_count;
            const remaining = t.remaining_count;
            const loadPct   = Math.min(Math.round((total / Math.max(capacity, 1)) * 100), 100);

            const loadColor = loadPct >= 100 ? 'bg-red-500'
                            : loadPct >= 75  ? 'bg-amber-400'
                            : loadPct >= 40  ? 'bg-[#8B7355]'
                            : 'bg-emerald-500';

            const statusLabel = ongoing > 0   ? 'In Session'
                              : remaining > 0 ? 'Available'
                              : done > 0      ? 'Finished'
                              : 'Free';

            const statusBadge = {
                'In Session': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                'Available':  'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                'Finished':   'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                'Free':       'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
            }[statusLabel] ?? 'bg-gray-100 text-gray-500';

            const initial = (t.first_name ?? '?').charAt(0).toUpperCase();
            const fullName = ((t.first_name ?? '') + ' ' + (t.last_name ?? '')).trim();

            return `
            <div class="px-6 py-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center min-w-0 gap-3">
                        <div class="flex items-center justify-center w-9 h-9 rounded-full bg-[#8B7355]/15 text-[#8B7355] flex-shrink-0 text-sm font-bold">
                            ${initial}
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate dark:text-white">${esc(fullName)}</p>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 truncate">${esc(t.email)}</p>
                        </div>
                    </div>
                    <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 text-[10px] font-semibold rounded-full ${statusBadge}">
                        ${statusLabel}
                    </span>
                </div>
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] text-gray-400">${total} / ${capacity} appointments</span>
                        <span class="text-[10px] font-semibold text-gray-500">${loadPct}%</span>
                    </div>
                    <div class="w-full h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                        <div class="${loadColor} h-full rounded-full transition-all duration-500" style="width: ${loadPct}%"></div>
                    </div>
                    <div class="flex items-center gap-3 mt-1.5">
                        <span class="text-[10px] text-emerald-600 dark:text-emerald-400">
                            <i class="fa-solid fa-circle-check"></i> ${done} done
                        </span>
                        ${ongoing > 0 ? `<span class="text-[10px] text-emerald-700 font-semibold dark:text-emerald-400"><i class="fa-solid fa-spinner"></i> ${ongoing} active</span>` : ''}
                        ${remaining > 0 ? `<span class="text-[10px] text-blue-600 dark:text-blue-400"><i class="fa-regular fa-clock"></i> ${remaining} queued</span>` : ''}
                    </div>
                </div>
            </div>`;
        });

        body.innerHTML = `<div class="divide-y divide-gray-100 dark:divide-gray-700 max-h-[460px] overflow-y-auto">${rows.join('')}</div>`;
    }

    // ── My Today updater ──────────────────────────────────────────────────────
    function updateMyToday(data) {
        set('my-stat-total',     data.stats.total);
        set('my-stat-ongoing',   data.stats.ongoing);
        set('my-stat-completed', data.stats.completed);
        set('my-stat-remaining', data.stats.remaining);

        const body = document.getElementById('my-timeline-body');
        if (body) {
            if (!data.appointments.length) {
                body.innerHTML = `
                    <div class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-500">
                        <i class="mb-3 text-3xl fa-regular fa-calendar-check"></i>
                        <p class="text-sm font-medium">No appointments assigned to you today.</p>
                        <p class="mt-1 text-xs text-gray-400">Check your upcoming schedule below.</p>
                    </div>`;
            } else {
                body.innerHTML = '<div class="divide-y divide-gray-100 dark:divide-gray-700">'
                    + data.appointments.map(b => {
                        const isNow  = b.status === 'ongoing';
                        const isPast = b.status === 'completed' || b.status === 'cancelled';
                        return `
                        <div class="flex items-start gap-4 px-6 py-4 transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/40
                                    ${isNow ? 'bg-emerald-50/60 dark:bg-emerald-900/10' : ''} ${isPast ? 'opacity-60' : ''}">
                            <div class="flex-shrink-0 w-16 text-right">
                                <p class="text-xs font-bold text-gray-800 dark:text-white tabular-nums">${esc(b.start_time_fmt)}</p>
                                <p class="text-[10px] font-semibold text-gray-400">${esc(b.start_ampm)}</p>
                                <div class="w-px h-4 mx-auto mt-1 bg-gray-200 dark:bg-gray-700"></div>
                                <p class="text-[10px] text-gray-400 tabular-nums">${esc(b.end_time_fmt)}</p>
                            </div>
                            <div class="flex-shrink-0 mt-1.5">
                                <div class="w-2.5 h-2.5 rounded-full border-2 ${dotClass(b.status)}"></div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 truncate dark:text-white">${esc(b.customer_name)}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">${esc(b.treatment_display)}</p>
                                        ${b.service_type === 'in_home' && b.customer_address ? `
                                        <p class="text-[10px] text-violet-600 dark:text-violet-400 mt-0.5 flex items-center gap-1">
                                            <i class="fa-solid fa-house"></i> Home service · ${esc(b.customer_address)}
                                        </p>` : ''}
                                    </div>
                                    <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 text-[10px] font-semibold rounded-full ${stClass(b.status)}">
                                        ${b.status.charAt(0).toUpperCase() + b.status.slice(1)}
                                    </span>
                                </div>
                                ${b.customer_phone ? `
                                <p class="text-[10px] text-gray-400 mt-1.5 flex items-center gap-1">
                                    <i class="fa-solid fa-phone text-[#8B7355]"></i> ${esc(b.customer_phone)}
                                </p>` : ''}
                                ${(b.status === 'reserved' || b.status === 'pending') ? (
                                    b.has_pending_reassignment
                                        ? `<span class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] font-semibold text-amber-700 bg-amber-100 rounded-lg dark:bg-amber-900/30 dark:text-amber-300">
                                                <i class="fa-solid fa-clock"></i> Reassignment Pending
                                           </span>`
                                        : `<button type="button" onclick="openReassignFlagModal(this)"
                                                data-reassign-flag-btn="${b.id}" data-id="${b.id}"
                                                data-customer="${esc(b.customer_name)}" data-treatment="${esc(b.treatment_display)}"
                                                data-date="${esc(b.appointment_date_fmt)}" data-time="${esc(b.start_time_fmt)}"
                                                class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                                                <i class="fa-solid fa-triangle-exclamation"></i> Can't Make It?
                                           </button>`
                                ) : ''}
                            </div>
                        </div>`;
                    }).join('')
                    + '</div>';
            }
        }

        // My next appointment footer
        const wrapper = document.getElementById('my-next-wrapper');
        const nameEl  = document.getElementById('my-next-name');
        const dateEl  = document.getElementById('my-next-date');
        const next    = data.next_appointment;

        if (wrapper) wrapper.classList.toggle('hidden', !next);
        if (next) {
            if (nameEl) nameEl.innerHTML = `${esc(next.customer_name)} <span class="mx-1 font-normal text-gray-400">·</span> ${esc(next.treatment_display)}`;
            if (dateEl) dateEl.textContent = `${next.appointment_date_fmt} at ${next.appointment_date_at}`;
        }
    }

    // ── Main poll ─────────────────────────────────────────────────────────────
    async function poll() {
        try {
            const res = await fetch(LIVE_URL, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!res.ok) throw new Error('HTTP ' + res.status);

            const data = await res.json();

            if (data.kpis)       { updateKpis(data.kpis); updateBreakdownBars(data.kpis); }
            if (data.revenue)    { updateRevenue(data.revenue); updateBreakdownBars(data.kpis ?? {}); }
            if (data.alerts)     { updateAlerts(data.alerts); }
            if (data.timeline)   { updateTimeline(data.timeline); }
            if (data.therapists) { updateTherapistPanel(data.therapists); }
            if (data.my_today)   { updateMyToday(data.my_today); }

            lastUpdatedAt     = new Date();         // ← add
            setLiveStatus('ok');                    // ← add
            label.textContent = 'Live — just now'; // ← add

        } catch (err) {
            // Silent fail — the page still shows stale server-rendered data.
            console.warn('Dashboard poll failed:', err.message);
            setLiveStatus('error');                 // ← add
        }
    }
    // Start polling after an initial delay so the first load isn't double-hit.
    setLiveStatus('connecting');
    poll(); // immediate first run
    setInterval(poll, POLL_MS);
    startTickTimer();

}());
</script>

@endsection
