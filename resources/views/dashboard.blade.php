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

    $statusDotClasses = [
        'ongoing'   => 'bg-emerald-500 border-emerald-500 ring-2 ring-emerald-200 dark:ring-emerald-800',
        'pending'   => 'bg-amber-400 border-amber-400',
        'reserved'  => 'bg-blue-400 border-blue-400',
        'completed' => 'bg-gray-300 border-gray-300',
        'cancelled' => 'bg-red-300 border-red-300',
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

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">

    {{-- ════════════════════════════════════════════════════════════════════
         HEADER
    ═══════════════════════════════════════════════════════════════════════ --}}
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
    </div>

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
@endif

@if(!$pendingDeploymentResponse && $myStaff)
    @if($myOpenDeployment)
        {{-- They already have an open request in flight — just show status --}}
        <div class="flex items-center gap-3 px-6 py-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-[#8B7355]/10 dark:bg-[#C4A97D]/10 shrink-0">
                <i class="text-sm fa-solid fa-right-left text-[#8B7355] dark:text-[#C4A97D]"></i>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                    Transfer request {{ $myOpenDeployment->status === 'approved' ? 'approved' : 'pending' }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    To {{ $myOpenDeployment->toBranch->name ?? '—' }} · starts {{ \Carbon\Carbon::parse($myOpenDeployment->start_date)->format('M d, Y') }}
                    @if($myOpenDeployment->status === 'pending') — awaiting HR/Owner approval @endif
                </p>
            </div>
        </div>
    @else
        {{-- No open request — offer to file one --}}
        <div class="flex items-center justify-between gap-4 px-6 py-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Want to work at another branch?</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Submit a transfer request for HR/Owner to review.</p>
            </div>
            <button type="button" onclick="openSelfRequestModal()"
                class="shrink-0 inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-xl hover:bg-[#7A6348]">
                <i class="mr-1.5 fa-solid fa-paper-plane"></i>Request Transfer
            </button>
        </div>
    @endif
@endif

@if($myStaff)
    <div id="selfRequestModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black bg-opacity-50">
        <div class="flex items-center justify-center min-h-screen px-4 py-8">
            <div class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
                <form action="{{ route('branch-deployments.self-request') }}" method="POST">
                    @csrf
                    <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Request Branch Transfer</h3>
                            <button type="button" onclick="closeSelfRequestModal()" class="text-gray-500 dark:text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <i class="text-lg fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block mb-1.5 text-sm font-medium text-gray-900 dark:text-white">
                                Target Branch <span class="text-red-500">*</span>
                            </label>
                            <select name="to_branch_id" required
                                class="block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="">Select target branch</option>
                                @foreach($transferBranches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }} — {{ $branch->location }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block mb-1.5 text-sm font-medium text-gray-900 dark:text-white">
                                Start Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="start_date" required min="{{ now()->toDateString() }}"
                                class="block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>

                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl dark:bg-gray-700">
                            <input type="checkbox" id="selfIsPermanent" name="is_permanent" value="1"
                                class="w-4 h-4 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355]"
                                onchange="document.getElementById('selfEndDateWrapper').classList.toggle('hidden', this.checked)">
                            <label for="selfIsPermanent" class="text-sm font-medium text-gray-900 cursor-pointer dark:text-white">
                                Permanent transfer
                            </label>
                        </div>

                        <div id="selfEndDateWrapper">
                            <label class="block mb-1.5 text-sm font-medium text-gray-900 dark:text-white">
                                End Date <span class="font-normal text-gray-500 dark:text-gray-400">(optional)</span>
                            </label>
                            <input type="date" name="end_date"
                                class="block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>

                        <div>
                            <label class="block mb-1.5 text-sm font-medium text-gray-900 dark:text-white">
                                Reason <span class="text-red-500">*</span>
                            </label>
                            <textarea name="notes" rows="3" required
                                placeholder="Why are you requesting this transfer?"
                                class="block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"></textarea>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Required for self-requested transfers.</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                        <button type="button" onclick="closeSelfRequestModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-5 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-xl hover:bg-[#7A6348]">
                            <i class="mr-1.5 fa-solid fa-paper-plane"></i>Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function openSelfRequestModal()  { document.getElementById('selfRequestModal').classList.remove('hidden'); }
    function closeSelfRequestModal() { document.getElementById('selfRequestModal').classList.add('hidden'); }
    </script>
@endif

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

    {{-- ════════════════════════════════════════════════════════════════════
         THERAPIST PERSONAL VIEW  (view dashboard my today)
    ═══════════════════════════════════════════════════════════════════════ --}}
    @if($canMyToday)

        {{-- Attendance Status --}}
        <div class="p-4 border sm:p-5 rounded-2xl {{ $myAttendanceToday && $myAttendanceToday->time_in && !$myAttendanceToday->time_out ? 'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/10 dark:border-emerald-800' : 'bg-amber-50 border-amber-200 dark:bg-amber-900/10 dark:border-amber-800' }}">
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

        {{-- Personal stat cards --}}
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">

            <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between gap-2">
                    <p class="min-w-0 truncate text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">My Today</p>
                    <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-[#8B7355]/10 dark:bg-[#C4A97D]/10">
                        <i class="fa-solid fa-calendar-day text-[#8B7355] dark:text-[#C4A97D] text-sm"></i>
                    </div>
                </div>
                <p id="my-stat-total" class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $myStats['total'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Appointments assigned</p>
            </div>

            <div class="p-4 border shadow-sm sm:p-5 bg-emerald-50 border-emerald-200 rounded-2xl dark:bg-emerald-900/10 dark:border-emerald-800">
                <div class="flex items-center justify-between gap-2">
                    <p class="min-w-0 truncate text-xs font-semibold tracking-wide uppercase text-emerald-700 dark:text-emerald-400">Ongoing</p>
                    <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                        <i class="text-sm fa-solid fa-spinner text-emerald-600 dark:text-emerald-400"></i>
                    </div>
                </div>
                <p id="my-stat-ongoing" class="mt-3 text-3xl font-bold text-emerald-900 dark:text-emerald-200">{{ $myStats['ongoing'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">In session right now</p>
            </div>

            <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between gap-2">
                    <p class="min-w-0 truncate text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Completed</p>
                    <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800">
                        <i class="text-sm fa-solid fa-circle-check text-slate-500"></i>
                    </div>
                </div>
                <p id="my-stat-completed" class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $myStats['completed'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Done today</p>
            </div>

            <div class="p-4 border border-blue-200 shadow-sm sm:p-5 bg-blue-50 rounded-2xl dark:bg-blue-900/10 dark:border-blue-800">
                <div class="flex items-center justify-between gap-2">
                    <p class="min-w-0 truncate text-xs font-semibold tracking-wide text-blue-700 uppercase dark:text-blue-400">Remaining</p>
                    <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 bg-blue-100 rounded-xl dark:bg-blue-900/30">
                        <i class="text-sm text-blue-600 fa-regular fa-clock dark:text-blue-400"></i>
                    </div>
                </div>
                <p id="my-stat-remaining" class="mt-3 text-3xl font-bold text-blue-900 dark:text-blue-200">{{ $myStats['remaining'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-blue-700 dark:text-blue-400">Still queued</p>
            </div>

        </div>

        {{-- Personal schedule timeline --}}
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">

            <div class="flex items-center justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">My Schedule Today</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Your appointments for {{ $now->format('M d, Y') }}</p>
                </div>
                @if($canViewSchedule)
                <a href="{{ route('schedule.index') }}"
                   class="text-xs font-medium text-[#8B7355] dark:text-[#C4A97D] hover:text-[#6F5430] dark:hover:text-[#D8C29B] transition-colors">
                    Full schedule →
                </a>
                @endif
            </div>

            <div id="my-timeline-body" class="divide-y divide-gray-100 dark:divide-gray-700">
                @if($myTodayAppointments->isEmpty())
                    <div class="flex flex-col items-center justify-center py-16 text-gray-500 dark:text-gray-400">
                        <i class="mb-3 text-3xl fa-regular fa-calendar-check"></i>
                        <p class="text-sm font-medium">No appointments assigned to you today.</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Check your upcoming schedule below.</p>
                    </div>
                @else
                    @foreach($myTodayAppointments as $booking)
                        @php
                            $startC = \Carbon\Carbon::parse($booking->start_time);
                            $endC   = \Carbon\Carbon::parse($booking->end_time);
                            $isNow  = $booking->status === 'ongoing';
                            $isPast = in_array($booking->status, ['completed', 'cancelled']);
                        @endphp
                        <div class="flex items-start gap-4 px-4 py-4 sm:px-6 transition-colors
                                    hover:bg-gray-50 dark:hover:bg-gray-900/40
                                    {{ $isNow ? 'bg-emerald-50/60 dark:bg-emerald-900/10' : '' }}
                                    {{ $isPast ? 'opacity-60' : '' }}">
                            <div class="flex-shrink-0 w-16 text-right">
                                <p class="text-xs font-bold text-gray-800 dark:text-white tabular-nums">{{ $startC->format('h:i') }}</p>
                                <p class="text-[11px] font-semibold text-gray-500 dark:text-gray-400">{{ $startC->format('A') }}</p>
                                <div class="w-px h-4 mx-auto mt-1 bg-gray-200 dark:bg-gray-700"></div>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 tabular-nums">{{ $endC->format('h:i A') }}</p>
                            </div>
                            <div class="flex-shrink-0 mt-1.5">
                                <div class="w-2.5 h-2.5 rounded-full border-2 {{ $statusDotClasses[$booking->status] ?? '' }}"></div>
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
                                        @if($booking->service_type === 'in_home' && $booking->customer_address)
                                        <p class="text-[11px] text-violet-600 dark:text-violet-400 mt-0.5 flex items-center gap-1">
                                            <i class="fa-solid fa-house"></i>
                                            Home service · {{ $booking->customer_address }}
                                        </p>
                                        @endif
                                    </div>
                                    <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-full
                                        {{ $statusClasses[$booking->status] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ ucfirst($booking->status) }}
                                    </span>
                                </div>
                                @if($booking->customer_phone)
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1.5 flex items-center gap-1">
                                    <i class="fa-solid fa-phone text-[#8B7355] dark:text-[#C4A97D]"></i>
                                    {{ $booking->customer_phone }}
                                </p>
                                @endif
                                @if(in_array($booking->status, ['reserved', 'pending']))
                                    @if($booking->has_pending_reassignment)
                                    <span class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold text-amber-700 bg-amber-100 rounded-lg dark:bg-amber-900/30 dark:text-amber-300">
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
                                            class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                                        <i class="fa-solid fa-triangle-exclamation"></i> Can't Make It?
                                    </button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            {{-- Next upcoming outside today --}}
            <div id="my-next-wrapper" class="{{ $myNextAppointment ? '' : 'hidden' }} px-4 py-3 sm:px-6 border-t border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/20">
                <p class="text-[11px] font-semibold tracking-wide text-gray-500 dark:text-gray-400 uppercase">Next Upcoming</p>
                <div class="flex items-center justify-between mt-1">
                    <div>
                        <p id="my-next-name" class="text-sm font-medium text-gray-800 dark:text-gray-200">
                            {{ ($myNextAppointment->customer_name ?? 'Walk-in Customer') }}
                            <span class="mx-1 font-normal text-gray-500 dark:text-gray-400">·</span>
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

        @include('partials.reassignment-flag-modal')

    @endif {{-- end my today --}}


    {{-- ════════════════════════════════════════════════════════════════════
         KPI CARDS  (view dashboard kpis)
    ═══════════════════════════════════════════════════════════════════════ --}}
    @if($canKpis)

        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">

            <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between gap-2">
                    <p class="min-w-0 truncate text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Today</p>
                    <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-[#8B7355]/10 dark:bg-[#C4A97D]/10">
                        <i class="fa-solid fa-calendar-day text-[#8B7355] dark:text-[#C4A97D] text-sm"></i>
                    </div>
                </div>
                <p id="kpi-today-count" class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $todayCount ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Total appointments</p>
            </div>

            <div class="p-4 border shadow-sm sm:p-5 bg-emerald-50 border-emerald-200 rounded-2xl dark:bg-emerald-900/10 dark:border-emerald-800">
                <div class="flex items-center justify-between gap-2">
                    <p class="min-w-0 truncate text-xs font-semibold tracking-wide uppercase text-emerald-700 dark:text-emerald-400">Ongoing</p>
                    <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                        <i class="text-sm fa-solid fa-spinner text-emerald-600 dark:text-emerald-400"></i>
                    </div>
                </div>
                <p id="kpi-ongoing" class="mt-3 text-3xl font-bold text-emerald-900 dark:text-emerald-200">{{ $ongoingToday ?? 0 }}</p>
                <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">In service right now</p>
            </div>

            <div class="p-4 border shadow-sm sm:p-5 bg-amber-50 border-amber-200 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800">
                <div class="flex items-center justify-between gap-2">
                    <p class="min-w-0 truncate text-xs font-semibold tracking-wide uppercase text-amber-700 dark:text-amber-400">Pending</p>
                    <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-900/30">
                        <i class="text-sm fa-solid fa-circle-exclamation text-amber-600 dark:text-amber-400"></i>
                    </div>
                </div>
                <p id="kpi-pending" class="mt-3 text-3xl font-bold text-amber-900 dark:text-amber-200">{{ $pendingToday ?? 0 }}</p>
                <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">Needs check-in</p>
            </div>

            @if($canRevenue)
            <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between gap-2">
                    <p class="min-w-0 truncate text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Collected</p>
                    <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-[#8B7355]/10 dark:bg-[#C4A97D]/10">
                        <i class="fa-solid fa-peso-sign text-[#8B7355] dark:text-[#C4A97D] text-sm"></i>
                    </div>
                </div>
                <p id="kpi-collected" class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">₱{{ number_format($collectedToday ?? 0, 0) }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Revenue today</p>
            </div>
            @else
            <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between gap-2">
                    <p class="min-w-0 truncate text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Upcoming</p>
                    <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-[#8B7355]/10 dark:bg-[#C4A97D]/10">
                        <i class="fa-solid fa-calendar-week text-[#8B7355] dark:text-[#C4A97D] text-sm"></i>
                    </div>
                </div>
                <p id="kpi-upcoming-week" class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $upcomingWeek ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Next 7 days</p>
            </div>
            @endif

        </div>

        {{-- Secondary stat row --}}
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">

            <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Completed</p>
                <p id="kpi-completed" class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $completedToday ?? 0 }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Done today</p>
            </div>

            <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Reserved</p>
                <p id="kpi-reserved" class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $reservedToday ?? 0 }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Confirmed, not yet started</p>
            </div>

            @if($canRevenue)
            <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Upcoming</p>
                <p id="kpi-upcoming-week-2" class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $upcomingWeek ?? 0 }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Next 7 days</p>
            </div>

            <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Today's Source</p>
                <div class="flex items-end gap-2 mt-2">
                    <div>
                        <p id="kpi-online" class="text-2xl font-bold text-violet-700 dark:text-violet-400">{{ $onlineToday ?? 0 }}</p>
                        <p class="text-[11px] font-semibold text-violet-600 dark:text-violet-400 uppercase whitespace-nowrap">Online</p>
                    </div>
                    <span class="mb-1 text-lg font-light text-gray-300 dark:text-gray-600">/</span>
                    <div>
                        <p id="kpi-walkin" class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $walkInToday ?? 0 }}</p>
                        <p class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase whitespace-nowrap">Walk-in</p>
                    </div>
                </div>
            </div>
            @else
            <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Cancelled</p>
                <p id="kpi-cancelled" class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $cancelledToday ?? 0 }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Cancelled today</p>
            </div>
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

            <div class="flex items-center justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Today's Schedule</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">All branch appointments · {{ $now->format('M d, Y') }}</p>
                </div>
                @if($canViewAppointments)
                <a href="{{ route('appointments.index') }}"
                   class="text-xs font-medium text-[#8B7355] dark:text-[#C4A97D] hover:text-[#6F5430] dark:hover:text-[#D8C29B] transition-colors">
                    Full list →
                </a>
                @endif
            </div>

            {{-- Timeline body --}}
            <div id="timeline-body" class="divide-y divide-gray-100 dark:divide-gray-700 max-h-[460px] overflow-y-auto">
                @if($todayAppointments->isEmpty())
                    <div id="timeline-empty" class="flex flex-col items-center justify-center py-16 text-gray-500 dark:text-gray-400">
                        <i class="mb-3 text-3xl fa-regular fa-calendar-xmark"></i>
                        <p class="text-sm">No appointments scheduled today.</p>
                        @if($canBookingBtn)
                        <a href="{{ route('booking') }}"
                           class="mt-4 inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white rounded-xl
                                  bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:opacity-90 transition">
                            <i class="fa-solid fa-plus text-[11px]"></i> Add Booking
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
                    <div class="flex items-start gap-4 px-4 py-4 sm:px-6 transition-colors
                                hover:bg-gray-50 dark:hover:bg-gray-900/40
                                {{ $isNow ? 'bg-emerald-50/60 dark:bg-emerald-900/10' : '' }}
                                {{ $isPast ? 'opacity-60' : '' }}">
                        <div class="flex-shrink-0 w-16 text-right">
                            <p class="text-xs font-bold text-gray-800 dark:text-white tabular-nums">{{ $startC->format('h:i') }}</p>
                            <p class="text-[11px] font-semibold text-gray-500 dark:text-gray-400">{{ $startC->format('A') }}</p>
                            <div class="w-px h-4 mx-auto mt-1 bg-gray-200 dark:bg-gray-700"></div>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 tabular-nums">{{ $endC->format('h:i A') }}</p>
                        </div>
                        <div class="flex-shrink-0 mt-1.5">
                            <div class="w-2.5 h-2.5 rounded-full border-2 {{ $statusDotClasses[$booking->status] ?? '' }}"></div>
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
                                <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-full
                                    {{ $statusClasses[$booking->status] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($booking->status) }}
                                </span>
                            </div>
                            <div class="flex items-center gap-3 mt-1.5">
                                <span class="text-[11px] text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                    <i class="fa-solid fa-user-nurse text-[#8B7355] dark:text-[#C4A97D]"></i>
                                    {{ $booking->therapist
                                        ? trim($booking->therapist->first_name . ' ' . $booking->therapist->last_name)
                                        : 'Unassigned' }}
                                </span>
                                <span class="inline-flex items-center px-1.5 py-0.5 text-[11px] font-medium rounded-full
                                    {{ $sourceClasses[$booking->booking_source ?? ''] ?? $sourceClasses[''] }}">
                                    {{ strtoupper($booking->booking_source ?: 'STAFF') }}
                                </span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                @endif
            </div>

            <div id="next-appointment-wrapper" class="{{ $nextAppointment ? '' : 'hidden' }} px-4 py-3 sm:px-6 border-t border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/20">
                <p class="text-[11px] font-semibold tracking-wide text-gray-500 dark:text-gray-400 uppercase">Next Upcoming</p>
                <div class="flex items-center justify-between mt-1">
                    <div>
                        <p id="next-appointment-name" class="text-sm font-medium text-gray-800 dark:text-gray-200">
                            @if($nextAppointment)
                                {{ $nextAppointment->customer_name ?? 'Walk-in' }}
                                <span class="mx-1 font-normal text-gray-500 dark:text-gray-400">·</span>
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

            <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Therapist Status</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Today's workload per therapist</p>
            </div>

            <div id="therapist-panel-body" class="divide-y divide-gray-100 dark:divide-gray-700 max-h-[460px] overflow-y-auto">
                @if($therapists->isEmpty())
                    <div class="flex flex-col items-center justify-center py-16 text-gray-500 dark:text-gray-400">
                        <i class="mb-3 text-3xl fa-solid fa-user-nurse"></i>
                        <p class="text-sm">No active therapists assigned.</p>
                    </div>
                @else
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
                        <div class="px-4 py-4 sm:px-6">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center min-w-0 gap-3">
                                    <div class="flex items-center justify-center w-9 h-9 rounded-full bg-[#8B7355]/15 dark:bg-[#C4A97D]/15 text-[#8B7355] dark:text-[#C4A97D] flex-shrink-0 text-sm font-bold">
                                        {{ strtoupper(substr($therapist->first_name ?? '?', 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 truncate dark:text-white">
                                            {{ trim(($therapist->first_name ?? '') . ' ' . ($therapist->last_name ?? '')) }}
                                        </p>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">{{ $therapist->email }}</p>
                                    </div>
                                </div>
                                <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-full {{ $statusBadge }}">
                                    {{ $statusLabel }}
                                </span>
                            </div>
                            <div class="mt-3">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ $total }} / {{ $capacity }} appointments</span>
                                    <span class="text-[11px] font-semibold text-gray-500">{{ $loadPct }}%</span>
                                </div>
                                <div class="w-full h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                    <div class="{{ $loadColor }} h-full rounded-full transition-all duration-500"
                                         style="width: {{ $loadPct }}%"></div>
                                </div>
                                <div class="flex items-center gap-3 mt-1.5">
                                    <span class="text-[11px] text-emerald-600 dark:text-emerald-400">
                                        <i class="fa-solid fa-circle-check"></i> {{ $done }} done
                                    </span>
                                    @if($ongoing > 0)
                                    <span class="text-[11px] text-emerald-700 font-semibold dark:text-emerald-400">
                                        <i class="fa-solid fa-spinner"></i> {{ $ongoing }} active
                                    </span>
                                    @endif
                                    @if($remaining > 0)
                                    <span class="text-[11px] text-blue-600 dark:text-blue-400">
                                        <i class="fa-regular fa-clock"></i> {{ $remaining }} queued
                                    </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
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
        $late            = $lateAppointments      ?? 0;
        $cancelled       = $noShows               ?? 0;
        $overloaded      = $overbookedTherapists  ?? 0;
        $pendingLeave    = $pendingLeaveRequests  ?? 0;
        $pendingReassign = $pendingReassignments  ?? 0;

        $alertCardBase     = 'flex items-center gap-3 p-3 rounded-xl cursor-pointer hover:opacity-80 transition-opacity';
        $alertIconWrapBase = 'flex items-center justify-center w-9 h-9 rounded-xl flex-shrink-0';

        $alertTones = [
            'amber' => [
                'card'     => 'bg-amber-50 ring-1 ring-amber-200 dark:bg-amber-900/10 dark:ring-amber-800',
                'iconWrap' => 'bg-amber-100 dark:bg-amber-900/30',
                'icon'     => 'text-amber-600 dark:text-amber-400',
                'title'    => 'text-amber-900 dark:text-amber-200',
                'sub'      => 'text-amber-600 dark:text-amber-400',
            ],
            'red' => [
                'card'     => 'bg-red-50 ring-1 ring-red-200 dark:bg-red-900/10 dark:ring-red-800',
                'iconWrap' => 'bg-red-100 dark:bg-red-900/30',
                'icon'     => 'text-red-500',
                'title'    => 'text-red-700 dark:text-red-400',
                'sub'      => 'text-red-500 dark:text-red-400',
            ],
            'idle' => [
                'card'     => 'bg-gray-50 dark:bg-gray-700/30',
                'iconWrap' => 'bg-gray-100 dark:bg-gray-700',
                'icon'     => 'text-gray-500 dark:text-gray-400',
                'title'    => 'text-gray-500 dark:text-gray-400',
                'sub'      => 'text-gray-500 dark:text-gray-400',
            ],
        ];

        $alertDefs = [
            ['key'=>'late','field'=>'late_appointments','tone'=>'amber','icon'=>'fa-clock-rotate-left',
             'href'=>route('appointments.index'),'count'=>$late,
             'singular'=>'Late Check-in','plural'=>'Late Check-ins',
             'activeSub'=>'Pending past their start time','idleSub'=>'All on time'],
            ['key'=>'noshow','field'=>'no_shows','tone'=>'red','icon'=>'fa-user-xmark',
             'href'=>route('appointments.index'),'count'=>$cancelled,
             'singular'=>'Cancellation Today','plural'=>'Cancellations Today',
             'activeSub'=>'Slots freed up today','idleSub'=>'No cancellations'],
            ['key'=>'overloaded','field'=>'overbooked_therapists','tone'=>'red','icon'=>'fa-user-nurse',
             'href'=>route('schedule.index'),'count'=>$overloaded,
             'singular'=>'Overloaded','plural'=>'Overloaded',
             'activeSub'=>'Therapist(s) over 8 bookings','idleSub'=>'All loads normal'],
            ['key'=>'pending-leave','field'=>'pending_leave_requests','tone'=>'amber','icon'=>'fa-calendar-days',
             'href'=>route('attendance.index').'?tab=leave','count'=>$pendingLeave,
             'singular'=>'Leave Request','plural'=>'Leave Requests',
             'activeSub'=>'Awaiting your review','idleSub'=>'None pending'],
            ['key'=>'pending-reassign','field'=>'pending_reassignments','tone'=>'red','icon'=>'fa-triangle-exclamation',
             'href'=>route('appointments.index'),'count'=>$pendingReassign,
             'singular'=>'Reassignment Request','plural'=>'Reassignment Requests',
             'activeSub'=>'Needs a replacement therapist','idleSub'=>'None pending'],
        ];

        $anyAlertActive = collect($alertDefs)->contains(fn ($a) => $a['count'] > 0);

        $quickActions = array_values(array_filter([
            $canBookAppointments ? ['href'=>route('booking'),            'icon'=>'fa-calendar-plus',  'label'=>'New Booking']  : null,
            $canViewAppointments ? ['href'=>route('appointments.index'), 'icon'=>'fa-calendar-check', 'label'=>'Appointments'] : null,
            $canViewSchedule     ? ['href'=>route('schedule.index'),     'icon'=>'fa-table-cells',    'label'=>'Schedule']     : null,
            $canViewAttendance   ? ['href'=>route('attendance.index'),   'icon'=>'fa-clipboard-user', 'label'=>'Attendance']   : null,
            // $canViewServices ? ['href'=>route('services.index'),         'icon'=>'fa-spa',       'label'=>'Services'] : null,
            // $canViewStaff    ? ['href'=>route('staff.index'),            'icon'=>'fa-users',     'label'=>'Staff']    : null,
            // $canViewReports  ? ['href'=>route('reports.index'),          'icon'=>'fa-chart-bar', 'label'=>'Reports']  : null,
            // $canViewRevenue  ? ['href'=>route('revenue.index'),          'icon'=>'fa-peso-sign', 'label'=>'Revenue']  : null,
            // $canViewDSS      ? ['href'=>route('decision-support.index'), 'icon'=>'fa-lightbulb', 'label'=>'Insights'] : null,
            // $canViewHiring   ? ['href'=>route('hiring.index'),           'icon'=>'fa-user-plus', 'label'=>'Hiring']   : null,
        ]));

        $bottomCount = ($canAlerts ? 1 : 0) + ($canRevenue ? 1 : 0) + (count($quickActions) ? 1 : 0);
        $bottomGrid  = match($bottomCount) {
            0, 1    => '',
            2       => 'md:grid-cols-2',
            default => 'md:grid-cols-3',
        };
    @endphp

    @if($bottomCount > 0)
    <div class="grid gap-6 {{ $bottomGrid }}">

        {{-- ── Alerts ── --}}
        @if($canAlerts)
        <div id="alertsWidget" class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Alerts</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Operational issues right now</p>
            </div>
            <div id="alerts-body" class="p-4 space-y-3 sm:p-5">

                @foreach($alertDefs as $a)
                @php $tone = $alertTones[$a['count'] > 0 ? $a['tone'] : 'idle']; @endphp
                <a href="{{ $a['href'] }}" id="alert-{{ $a['key'] }}" class="{{ $alertCardBase }} {{ $tone['card'] }}">
                    <div id="alert-{{ $a['key'] }}-iconwrap" class="{{ $alertIconWrapBase }} {{ $tone['iconWrap'] }}">
                        <i id="alert-{{ $a['key'] }}-icon" class="fa-solid {{ $a['icon'] }} text-sm {{ $tone['icon'] }}"></i>
                    </div>
                    <div class="min-w-0">
                        <p id="alert-{{ $a['key'] }}-title" class="text-sm font-semibold {{ $tone['title'] }}">
                            {{ $a['count'] }} {{ $a['count'] !== 1 ? $a['plural'] : $a['singular'] }}
                        </p>
                        <p id="alert-{{ $a['key'] }}-sub" class="text-xs {{ $tone['sub'] }}">
                            {{ $a['count'] > 0 ? $a['activeSub'] : $a['idleSub'] }}
                        </p>
                    </div>
                </a>
                @endforeach

                <div id="alert-all-good" class="{{ $anyAlertActive ? 'hidden' : '' }} flex items-center justify-center gap-2 pt-1">
                    <i class="text-sm fa-solid fa-circle-check text-emerald-500"></i>
                    <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Everything looks good!</span>
                </div>


            </div>
        </div>
        @endif

        {{-- ── Today's Breakdown ── --}}
        @if($canRevenue)
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Today's Breakdown</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Service + status summary</p>
            </div>
            <div class="p-4 space-y-4 sm:p-5">

                <div>
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Top Service</p>
                    <div class="flex items-center gap-3 mt-2 p-3 bg-[#8B7355]/5 dark:bg-[#C4A97D]/5 rounded-xl ring-1 ring-[#8B7355]/20 dark:ring-[#C4A97D]/20">
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-[#8B7355]/15 dark:bg-[#C4A97D]/15 flex-shrink-0">
                            <i class="fa-solid fa-spa text-[#8B7355] dark:text-[#C4A97D] text-sm"></i>
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
                            <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400 w-16 flex-shrink-0">{{ $s['label'] }}</span>
                            <div class="flex-1 h-2 overflow-hidden bg-gray-100 rounded-full dark:bg-gray-700">
                                <div id="{{ $s['id'] }}-fill" class="{{ $s['color'] }} h-full rounded-full transition-all duration-500"
                                     style="width: {{ $total > 0 ? round(($s['count'] / $total) * 100) : 0 }}%"></div>
                            </div>
                            <span id="{{ $s['id'] }}-count" class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 w-4 text-right">{{ $s['count'] }}</span>
                        </div>
                        @endforeach
                        <p id="breakdown-empty" class="{{ $total === 0 ? '' : 'hidden' }} text-xs text-gray-500 dark:text-gray-400 italic">No appointments yet today.</p>
                    </div>
                </div>

            </div>
        </div>
        @endif

        {{-- ── Quick Actions ── --}}
        @if(count($quickActions))
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Quick Actions</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Start a common task</p>
            </div>
            <div class="grid grid-cols-2 gap-2 p-4 sm:p-5">
                @foreach($quickActions as $qa)
                <a href="{{ $qa['href'] }}"
                   class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-[#8B7355]/5 dark:hover:bg-[#C4A97D]/5 hover:border-[#8B7355]/30 dark:hover:border-[#C4A97D]/30 transition-colors group">
                    <i class="fa-solid {{ $qa['icon'] }} text-[#8B7355] dark:text-[#C4A97D] text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 text-center">{{ $qa['label'] }}</span>
                </a>
                @endforeach
            </div>
        </div>
        @endif

    </div>{{-- end bottom row --}}
    @endif

</div>{{-- end max-w-7xl --}}


{{-- ════════════════════════════════════════════════════════════════════════
     LIVE POLLING SCRIPT
═════════════════════════════════════════════════════════════════════════ --}}
<script>
(function () {
    const POLL_MS  = 30000; // 30 seconds
    const LIVE_URL = '{{ route('dashboard.live-data') }}';
    const dot   = document.getElementById('liveIndicatorDot');
    const label = document.getElementById('liveIndicatorLabel');
    const STATUS_CLASSES     = @json($statusClasses);
    const SOURCE_CLASSES     = @json($sourceClasses);
    const STATUS_DOT_CLASSES = @json($statusDotClasses);
    const BOOKING_URL = @json($canBookingBtn ? route('booking') : null);

    let lastUpdatedAt = null;
    let tickTimer     = null;
    let pollTimer     = null;

    function setLiveStatus(state) {
        const map = {
            ok:         { dot: 'bg-emerald-400', label: 'Live'          },
            error:      { dot: 'bg-red-400',     label: 'Reconnecting…' },
            connecting: { dot: 'bg-gray-300 dark:bg-gray-600', label: 'Connecting…' },
        };
        const st = map[state] ?? map.connecting;
        if (dot)   dot.className = `inline-block w-2 h-2 rounded-full transition-colors duration-300 ${st.dot}`;
        if (label) label.textContent = st.label;
    }

    function timeAgo(date) {
        const sec = Math.round((Date.now() - date.getTime()) / 1000);
        if (sec < 10) return 'just now';
        if (sec < 60) return `${sec}s ago`;
        return `${Math.round(sec / 60)}m ago`;
    }

    function startTickTimer() {
        if (tickTimer) clearInterval(tickTimer);
        tickTimer = setInterval(() => {
            if (lastUpdatedAt && label) label.textContent = `Updated ${timeAgo(lastUpdatedAt)}`;
        }, 10000);
    }

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

    // Rewrites a scroll container without throwing the reader back to the top.
    function replaceKeepingScroll(el, html) {
        const top = el.scrollTop;
        el.innerHTML = html;
        el.scrollTop = top;
    }

    function stClass(st)   { return STATUS_CLASSES[st] ?? 'bg-gray-100 text-gray-600'; }
    function srcClass(src) { return SOURCE_CLASSES[src] ?? SOURCE_CLASSES['']; }
    function dotClass(st)  { return STATUS_DOT_CLASSES[st] ?? ''; }

    function updateKpis(kpis) {
        set('kpi-today-count',     kpis.today_count);
        set('kpi-ongoing',         kpis.ongoing_today);
        set('kpi-pending',         kpis.pending_today);
        set('kpi-reserved',        kpis.reserved_today);
        set('kpi-completed',       kpis.completed_today);
        set('kpi-cancelled',       kpis.cancelled_today);
        set('kpi-upcoming-week',   kpis.upcoming_week);
        set('kpi-upcoming-week-2', kpis.upcoming_week);
    }

    function updateRevenue(rev) {
        set('kpi-collected',         php(rev.collected_today));
        set('kpi-online',            rev.online_today);
        set('kpi-walkin',            rev.walk_in_today);
        set('breakdown-top-service', rev.top_service_label ?? 'No bookings yet');
    }

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
            if (cnt)  cnt.textContent = count;
            if (fill) fill.style.width = (total > 0 ? Math.round((count / total) * 100) : 0) + '%';
        });
        const emptyEl = document.getElementById('breakdown-empty');
        if (emptyEl) emptyEl.classList.toggle('hidden', total > 0);
    }

    const ALERT_DEFS          = @json($alertDefs);
    const ALERT_TONES         = @json($alertTones);
    const ALERT_CARD_BASE     = @json($alertCardBase);
    const ALERT_ICONWRAP_BASE = @json($alertIconWrapBase);

    function updateAlerts(alerts) {
        let anyActive = false;
        ALERT_DEFS.forEach(def => {
            const n = alerts[def.field] ?? 0;
            const active = n > 0;
            if (active) anyActive = true;

            const tone = ALERT_TONES[active ? def.tone : 'idle'];
            const id   = 'alert-' + def.key;

            const card = document.getElementById(id);
            if (card) card.className = ALERT_CARD_BASE + ' ' + tone.card;

            const wrap = document.getElementById(id + '-iconwrap');
            if (wrap) wrap.className = ALERT_ICONWRAP_BASE + ' ' + tone.iconWrap;

            const icon = document.getElementById(id + '-icon');
            if (icon) icon.className = 'fa-solid ' + def.icon + ' text-sm ' + tone.icon;

            const title = document.getElementById(id + '-title');
            if (title) {
                title.textContent = n + ' ' + (n !== 1 ? def.plural : def.singular);
                title.className   = 'text-sm font-semibold ' + tone.title;
            }

            const sub = document.getElementById(id + '-sub');
            if (sub) {
                sub.textContent = active ? def.activeSub : def.idleSub;
                sub.className   = 'text-xs ' + tone.sub;
            }
        });
        const allGood = document.getElementById('alert-all-good');
        if (allGood) allGood.classList.toggle('hidden', anyActive);
    }

    function buildTimelineRow(b, opts) {
        const o       = opts || {};
        const isNow   = b.status === 'ongoing';
        const isPast  = b.status === 'completed' || b.status === 'cancelled';
        const rowBg   = isNow ? 'bg-emerald-50/60 dark:bg-emerald-900/10' : '';
        const opacity = isPast ? 'opacity-60' : '';

        const homeLine = (b.service_type === 'in_home' && b.customer_address) ? `
            <p class="text-[11px] text-violet-600 dark:text-violet-400 mt-0.5 flex items-center gap-1">
                <i class="fa-solid fa-house"></i> Home service · ${esc(b.customer_address)}
            </p>` : '';

        const metaRow = o.showTherapist ? `
            <div class="flex items-center gap-3 mt-1.5">
                <span class="text-[11px] text-gray-500 dark:text-gray-400 flex items-center gap-1">
                    <i class="fa-solid fa-user-nurse text-[#8B7355] dark:text-[#C4A97D]"></i>
                    ${esc(b.therapist_name)}
                </span>
                <span class="inline-flex items-center px-1.5 py-0.5 text-[11px] font-medium rounded-full ${srcClass(b.booking_source)}">
                    ${(b.booking_source || 'STAFF').toUpperCase()}
                </span>
            </div>` : '';

        const phoneLine = b.customer_phone ? `
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1.5 flex items-center gap-1">
                <i class="fa-solid fa-phone text-[#8B7355] dark:text-[#C4A97D]"></i> ${esc(b.customer_phone)}
            </p>` : '';

        let reassign = '';
        if (o.showReassign && (b.status === 'reserved' || b.status === 'pending')) {
            reassign = b.has_pending_reassignment
                ? `<span class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold text-amber-700 bg-amber-100 rounded-lg dark:bg-amber-900/30 dark:text-amber-300">
                        <i class="fa-solid fa-clock"></i> Reassignment Pending
                   </span>`
                : `<button type="button" onclick="openReassignFlagModal(this)"
                        data-reassign-flag-btn="${b.id}" data-id="${b.id}"
                        data-customer="${esc(b.customer_name)}" data-treatment="${esc(b.treatment_display)}"
                        data-date="${esc(b.appointment_date_fmt)}" data-time="${esc(b.start_time_fmt)}"
                        class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                        <i class="fa-solid fa-triangle-exclamation"></i> Can't Make It?
                   </button>`;
        }

        return `
        <div class="flex items-start gap-4 px-4 py-4 sm:px-6 transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/40 ${rowBg} ${opacity}">
            <div class="flex-shrink-0 w-16 text-right">
                <p class="text-xs font-bold text-gray-800 dark:text-white tabular-nums">${esc(b.start_time_fmt)}</p>
                <p class="text-[11px] font-semibold text-gray-500 dark:text-gray-400">${esc(b.start_ampm)}</p>
                <div class="w-px h-4 mx-auto mt-1 bg-gray-200 dark:bg-gray-700"></div>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 tabular-nums">${esc(b.end_time_fmt)}</p>
            </div>
            <div class="flex-shrink-0 mt-1.5">
                <div class="w-2.5 h-2.5 rounded-full border-2 ${dotClass(b.status)}"></div>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate dark:text-white">${esc(b.customer_name)}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">${esc(b.treatment_display)}</p>
                        ${homeLine}
                    </div>
                    <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-full ${stClass(b.status)}">
                        ${b.status.charAt(0).toUpperCase() + b.status.slice(1)}
                    </span>
                </div>
                ${metaRow}
                ${phoneLine}
                ${reassign}
            </div>
        </div>`;
    }

    function updateTimeline(data) {
        const body = document.getElementById('timeline-body');
        if (!body) return;

        if (!data.appointments.length) {
            const cta = BOOKING_URL ? `
                <a href="${BOOKING_URL}"
                   class="mt-4 inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white rounded-xl
                          bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:opacity-90 transition">
                    <i class="fa-solid fa-plus text-[11px]"></i> Add Booking
                </a>` : '';
            replaceKeepingScroll(body, `
                <div id="timeline-empty" class="flex flex-col items-center justify-center py-16 text-gray-500 dark:text-gray-400">
                    <i class="mb-3 text-3xl fa-regular fa-calendar-xmark"></i>
                    <p class="text-sm">No appointments scheduled today.</p>
                    ${cta}
                </div>`);
        } else {
            replaceKeepingScroll(body,
                data.appointments.map(b => buildTimelineRow(b, { showTherapist: true })).join(''));
        }

        const wrapper = document.getElementById('next-appointment-wrapper');
        const nameEl  = document.getElementById('next-appointment-name');
        const dateEl  = document.getElementById('next-appointment-date');
        const next    = data.next_appointment;

        if (wrapper) wrapper.classList.toggle('hidden', !next);
        if (next) {
            if (nameEl) nameEl.innerHTML = `${esc(next.customer_name)} <span class="mx-1 font-normal text-gray-500 dark:text-gray-400">·</span> ${esc(next.treatment_display)}`;
            if (dateEl) dateEl.textContent = `${next.appointment_date_fmt} at ${next.appointment_date_at}`;
        }
    }

    function updateTherapistPanel(therapists) {
        const body = document.getElementById('therapist-panel-body');
        if (!body) return;

        if (!therapists.length) {
            replaceKeepingScroll(body, `
                <div class="flex flex-col items-center justify-center py-16 text-gray-500 dark:text-gray-400">
                    <i class="mb-3 text-3xl fa-solid fa-user-nurse"></i>
                    <p class="text-sm">No active therapists assigned.</p>
                </div>`);
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

            const initial  = (t.first_name ?? '?').charAt(0).toUpperCase();
            const fullName = ((t.first_name ?? '') + ' ' + (t.last_name ?? '')).trim();

            return `
            <div class="px-4 py-4 sm:px-6">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center min-w-0 gap-3">
                        <div class="flex items-center justify-center w-9 h-9 rounded-full bg-[#8B7355]/15 dark:bg-[#C4A97D]/15 text-[#8B7355] dark:text-[#C4A97D] flex-shrink-0 text-sm font-bold">
                            ${initial}
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate dark:text-white">${esc(fullName)}</p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">${esc(t.email)}</p>
                        </div>
                    </div>
                    <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-full ${statusBadge}">
                        ${statusLabel}
                    </span>
                </div>
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[11px] text-gray-500 dark:text-gray-400">${total} / ${capacity} appointments</span>
                        <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400">${loadPct}%</span>
                    </div>
                    <div class="w-full h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                        <div class="${loadColor} h-full rounded-full transition-all duration-500" style="width: ${loadPct}%"></div>
                    </div>
                    <div class="flex items-center gap-3 mt-1.5">
                        <span class="text-[11px] text-emerald-600 dark:text-emerald-400">
                            <i class="fa-solid fa-circle-check"></i> ${done} done
                        </span>
                        ${ongoing > 0 ? `<span class="text-[11px] text-emerald-700 font-semibold dark:text-emerald-400"><i class="fa-solid fa-spinner"></i> ${ongoing} active</span>` : ''}
                        ${remaining > 0 ? `<span class="text-[11px] text-blue-600 dark:text-blue-400"><i class="fa-regular fa-clock"></i> ${remaining} queued</span>` : ''}
                    </div>
                </div>
            </div>`;
        });

        replaceKeepingScroll(body, rows.join(''));
    }

    function updateMyToday(data) {
        set('my-stat-total',     data.stats.total);
        set('my-stat-ongoing',   data.stats.ongoing);
        set('my-stat-completed', data.stats.completed);
        set('my-stat-remaining', data.stats.remaining);

        const body = document.getElementById('my-timeline-body');
        if (body) {
            if (!data.appointments.length) {
                replaceKeepingScroll(body, `
                    <div class="flex flex-col items-center justify-center py-16 text-gray-500 dark:text-gray-400">
                        <i class="mb-3 text-3xl fa-regular fa-calendar-check"></i>
                        <p class="text-sm font-medium">No appointments assigned to you today.</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Check your upcoming schedule below.</p>
                    </div>`);
            } else {
                replaceKeepingScroll(body,
                    data.appointments.map(b => buildTimelineRow(b, { showReassign: true })).join(''));
            }
        }

        const wrapper = document.getElementById('my-next-wrapper');
        const nameEl  = document.getElementById('my-next-name');
        const dateEl  = document.getElementById('my-next-date');
        const next    = data.next_appointment;

        if (wrapper) wrapper.classList.toggle('hidden', !next);
        if (next) {
            if (nameEl) nameEl.innerHTML = `${esc(next.customer_name)} <span class="mx-1 font-normal text-gray-500 dark:text-gray-400">·</span> ${esc(next.treatment_display)}`;
            if (dateEl) dateEl.textContent = `${next.appointment_date_fmt} at ${next.appointment_date_at}`;
        }
    }

    async function poll() {
        if (document.hidden) return;

        try {
            const res = await fetch(LIVE_URL, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);

            const data = await res.json();

            if (data.kpis)       { updateKpis(data.kpis); updateBreakdownBars(data.kpis); }
            if (data.revenue)    { updateRevenue(data.revenue); }
            if (data.alerts)     { updateAlerts(data.alerts); }
            if (data.timeline)   { updateTimeline(data.timeline); }
            if (data.therapists) { updateTherapistPanel(data.therapists); }
            if (data.my_today)   { updateMyToday(data.my_today); }

            lastUpdatedAt = new Date();
            setLiveStatus('ok');
            if (label) label.textContent = 'Live — just now';

        } catch (err) {
            // Silent fail — the page still shows the server-rendered data.
            console.warn('Dashboard poll failed:', err.message);
            setLiveStatus('error');
        }
    }

    lastUpdatedAt = new Date();
    setLiveStatus('ok');
    if (label) label.textContent = 'Live — just now';

    pollTimer = setInterval(poll, POLL_MS);
    startTickTimer();

    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
    window.addEventListener('pagehide', () => {
        if (pollTimer) clearInterval(pollTimer);
        if (tickTimer) clearInterval(tickTimer);
    });

    @if(($pendingLeaveRequests ?? 0) > 0 || ($pendingReassignments ?? 0) > 0)
    setTimeout(function () {
        const widget = document.getElementById('alertsWidget');
        if (!widget) return;
        const rect = widget.getBoundingClientRect();
        const alreadyVisible = rect.top >= 0 && rect.bottom <= window.innerHeight;
        if (!alreadyVisible) widget.scrollIntoView({ behavior: 'smooth', block: 'center' });
        widget.classList.add('animate-pulse');
        setTimeout(() => widget.classList.remove('animate-pulse'), 1500);
    }, 500);
    @endif

}());
</script>

@endsection 