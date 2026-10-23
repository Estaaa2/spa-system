@extends('layouts.app')

@section('title', 'Attendance & Leave')
@section('content')
@php
    // ── Tokens — same shapes as the payroll pages (appointments.blade.php canon) ──
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800 '
             . 'disabled:opacity-60 disabled:cursor-not-allowed';
    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'edit'    => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'neutral' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
        'warn'    => $btnBase . ' bg-amber-600 text-white hover:bg-amber-700',
        'remove'  => $btnBase . ' bg-red-700 text-white hover:bg-red-800',
    ];
    $inputClass = 'w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl bg-white text-gray-900 '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:border-gray-600 dark:bg-gray-700 dark:text-white';
    $labelClass = 'block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300';
    $card       = 'overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $cardHead   = 'px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700';
    $th         = 'px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400';
    $badgeBase  = 'inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-medium rounded-full';
    $closeBtn   = 'inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 '
                . 'hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200';

    // ── Status maps — ONE source, used by Blade and emitted to JS via @json ──
    $statusMeta = [
        'present'  => ['label' => 'Present',  'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
        'late'     => ['label' => 'Late',     'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'],
        'absent'   => ['label' => 'Absent',   'class' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
        'on_leave' => ['label' => 'On leave', 'class' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'],
    ];
    $leaveStatusMeta = [
        'pending'  => ['label' => 'Pending',  'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'],
        'approved' => ['label' => 'Approved', 'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
        'rejected' => ['label' => 'Rejected', 'class' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
    ];
    $leaveTypeLabels = [
        'sick' => 'Sick', 'emergency' => 'Emergency', 'vacation' => 'Vacation',
        'personal' => 'Personal', 'other' => 'Other',
    ];
    $fallbackBadge = 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';

    // Where an attendance record came from, in words a spa owner understands.
    $sourceText = function ($record) {
        return match ($record->source) {
            'self'   => 'Clocked in by staff',
            'manual' => 'Recorded by ' . ($record->markedBy?->name ?: 'a manager'),
            'system' => 'From approved leave',
            default  => ucfirst((string) $record->source),
        };
    };
    $isAutoFlag = fn ($record) => $record->remarks && str_contains($record->remarks, 'Auto-flag');

    $today      = today();
    $leaveMax   = today()->addDays(60)->toDateString();   // LeaveRequestController::store rule
    $initialTab = request('tab') === 'leave' ? 'leave' : 'attendance';
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl" x-data="attendancePage('{{ $initialTab }}')">

    <x-page-header
        title="Attendance & Leave"
        subtitle="Clock in and out, review your team's attendance, and manage time-off requests."
    />

    {{-- Section switcher — same pattern as payroll/setup.blade.php; always visible --}}
    <div role="tablist" aria-label="Attendance and leave sections"
         @keydown.arrow-right.prevent="cycle(1)" @keydown.arrow-left.prevent="cycle(-1)"
         class="flex gap-1 p-1.5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        @foreach(['attendance' => ['fa-clock', 'Attendance'], 'leave' => ['fa-calendar-days', 'Leave Requests']] as $key => [$icon, $label])
            <button type="button" @click="setTab('{{ $key }}')"
                    role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
                    :aria-selected="tab === '{{ $key }}' ? 'true' : 'false'"
                    :tabindex="tab === '{{ $key }}' ? 0 : -1"
                    :class="tab === '{{ $key }}' ? 'bg-[#8B7355] text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                    class="flex items-center justify-center flex-1 min-w-0 gap-2 px-3 min-h-[44px] text-sm font-medium transition rounded-xl">
                <i class="text-xs fa-solid {{ $icon }}" aria-hidden="true"></i>
                <span>{{ $label }}</span>
                @if($key === 'leave' && $canApproveLeave)
                    <span id="pendingTabBadge" class="hidden {{ $badgeBase }} bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"></span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- ════════════════════════════════════════════════════════
         ATTENDANCE TAB
    ═══════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'attendance'" x-cloak role="tabpanel" id="panel-attendance" aria-labelledby="tab-attendance" class="space-y-6">

        @if($myStaff)
            {{-- My attendance today --}}
            <div class="p-4 sm:p-6 {{ $card }}">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">My Attendance Today</p>
                        @if(!$myToday || !$myToday->time_in)
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Not clocked in yet</p>
                        @else
                            <p class="mt-1 text-lg font-semibold {{ $myToday->time_out ? 'text-gray-900 dark:text-white' : 'text-emerald-700 dark:text-emerald-400' }}">
                                @if($myToday->time_out)
                                    {{ \Carbon\Carbon::parse($myToday->time_in)->format('h:i A') }} – {{ \Carbon\Carbon::parse($myToday->time_out)->format('h:i A') }}
                                @else
                                    Clocked in at {{ \Carbon\Carbon::parse($myToday->time_in)->format('h:i A') }}
                                @endif
                            </p>
                            <span class="mt-1 {{ $badgeBase }} {{ $statusMeta[$myToday->status]['class'] ?? $fallbackBadge }}">
                                {{ $statusMeta[$myToday->status]['label'] ?? ucfirst(str_replace('_', ' ', $myToday->status)) }}
                            </span>
                        @endif
                    </div>

                    <div class="flex gap-2">
                        @if(!$myToday || !$myToday->time_in)
                            <form method="POST" action="{{ route('attendance.clock-in') }}">
                                @csrf
                                <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto">
                                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Clock In
                                </button>
                            </form>
                        @elseif(!$myToday->time_out)
                            {{-- Clocking out ends the day; only an attendance editor can undo it, so confirm first. --}}
                            <button type="button" class="w-full {{ $btn['remove'] }} sm:w-auto" onclick="openClockOutModal()">
                                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Clock Out
                            </button>
                        @else
                            <span class="inline-flex items-center min-h-[44px] px-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                                <i class="mr-1.5 fa-solid fa-circle-check" aria-hidden="true"></i> Day complete
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- My recent history --}}
            <div class="{{ $card }}">
                <div class="{{ $cardHead }}">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">My Last 14 Days</h2>
                </div>
                <div class="md:overflow-x-auto">
                    <table role="table" class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">
                        <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                            <tr role="row">
                                <th role="columnheader" scope="col" class="{{ $th }}">Date</th>
                                <th role="columnheader" scope="col" class="{{ $th }}">Status</th>
                                <th role="columnheader" scope="col" class="{{ $th }}">Time In</th>
                                <th role="columnheader" scope="col" class="{{ $th }}">Time Out</th>
                            </tr>
                        </thead>
                        <tbody role="rowgroup" class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                            @forelse($myHistory as $row)
                                <tr role="row">
                                    <td role="cell" data-label="Date" class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ \Carbon\Carbon::parse($row->date)->format('D, M j') }}</td>
                                    <td role="cell" data-label="Status" class="px-6 py-3">
                                        <span class="{{ $badgeBase }} {{ $statusMeta[$row->status]['class'] ?? $fallbackBadge }}">
                                            {{ $statusMeta[$row->status]['label'] ?? ucfirst(str_replace('_', ' ', $row->status)) }}
                                        </span>
                                        @if($row->auto_closed)
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Clock-out recorded automatically — you may have forgotten to clock out.</p>
                                        @endif
                                        @if($isAutoFlag($row))
                                            <p class="mt-1 text-xs text-amber-700 dark:text-amber-300"><i class="mr-1 fa-solid fa-triangle-exclamation" aria-hidden="true"></i>{{ $row->remarks }}</p>
                                        @endif
                                    </td>
                                    <td role="cell" data-label="Time In" class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->time_in ? \Carbon\Carbon::parse($row->time_in)->format('h:i A') : '—' }}</td>
                                    <td role="cell" data-label="Time Out" class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->time_out ? \Carbon\Carbon::parse($row->time_out)->format('h:i A') : '—' }}</td>
                                </tr>
                            @empty
                                <tr role="row">
                                    <td role="cell" colspan="4" class="px-6 py-12 text-sm text-center text-gray-500 rt-empty dark:text-gray-400">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="mb-3 text-4xl text-gray-400 fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                                            <p>No attendance history yet.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Team roster (view attendance) --}}
        @if($canViewRoster)
            <div class="{{ $card }}">
                <div class="flex flex-col gap-3 {{ $cardHead }} sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Branch Roster</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $date->format('l, F j, Y') }}</p>
                    </div>
                    <form method="GET" action="{{ route('attendance.index') }}" class="flex items-end gap-2" aria-label="Choose roster date">
                        <div>
                            <label for="roster_date" class="{{ $labelClass }}">Date</label>
                            <input type="date" id="roster_date" name="date" value="{{ $date->toDateString() }}" max="{{ $today->toDateString() }}" class="{{ $inputClass }} sm:w-44">
                        </div>
                        <button type="submit" class="{{ $btn['neutral'] }}">
                            <i class="text-xs fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Show</span>
                        </button>
                    </form>
                </div>
                <div class="md:overflow-x-auto">
                    <table role="table" class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">
                        <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                            <tr role="row">
                                <th role="columnheader" scope="col" class="{{ $th }}">Staff</th>
                                <th role="columnheader" scope="col" class="{{ $th }}">Status</th>
                                <th role="columnheader" scope="col" class="{{ $th }}">Time In</th>
                                <th role="columnheader" scope="col" class="{{ $th }}">Time Out</th>
                                <th role="columnheader" scope="col" class="{{ $th }}">Recorded</th>
                                @if($canEditRoster)<th role="columnheader" scope="col" class="{{ $th }}">Actions</th>@endif
                            </tr>
                        </thead>
                        <tbody role="rowgroup" class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                            @forelse($staffList as $staff)
                                @php
                                    $record = $staff->attendance->first();
                                    $roleName = $staff->user?->getRoleNames()->first();
                                    $recordRow = [
                                        'staff_id'   => $staff->id,
                                        'name'       => $staff->user->name ?? '',
                                        'date'       => $date->toDateString(),
                                        'date_label' => $date->format('l, F j, Y'),
                                        'status'     => $record->status ?? 'present',
                                        'time_in'    => $record?->time_in ? \Carbon\Carbon::parse($record->time_in)->format('H:i') : '',
                                        'time_out'   => $record?->time_out ? \Carbon\Carbon::parse($record->time_out)->format('H:i') : '',
                                        'remarks'    => $record->remarks ?? '',
                                    ];
                                @endphp
                                <tr role="row">
                                    <td role="cell" data-label="Staff" class="px-6 py-4">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $staff->user->name ?? '—' }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ \App\Services\Payroll\PayrollPermissions::roleLabel($roleName) }}</p>
                                    </td>
                                    <td role="cell" data-label="Status" class="px-6 py-4">
                                        @if($record)
                                            <span class="{{ $badgeBase }} {{ $statusMeta[$record->status]['class'] ?? $fallbackBadge }}">
                                                {{ $statusMeta[$record->status]['label'] ?? ucfirst(str_replace('_', ' ', $record->status)) }}
                                            </span>
                                            @if($isAutoFlag($record))
                                                <p class="mt-1 text-xs text-amber-700 dark:text-amber-300"><i class="mr-1 fa-solid fa-triangle-exclamation" aria-hidden="true"></i>{{ $record->remarks }}</p>
                                            @endif
                                        @else
                                            <span class="text-xs italic text-gray-500 dark:text-gray-400">No record</span>
                                        @endif
                                    </td>
                                    <td role="cell" data-label="Time In" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $record?->time_in ? \Carbon\Carbon::parse($record->time_in)->format('h:i A') : '—' }}
                                    </td>
                                    <td role="cell" data-label="Time Out" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $record?->time_out ? \Carbon\Carbon::parse($record->time_out)->format('h:i A') : '—' }}
                                        @if($record?->auto_closed)
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Recorded automatically</p>
                                        @endif
                                    </td>
                                    <td role="cell" data-label="Recorded" class="px-6 py-4 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $record ? $sourceText($record) : '—' }}
                                    </td>
                                    @if($canEditRoster)
                                        <td role="cell" data-label="Actions" class="px-6 py-4 rt-actions">
                                            <button type="button" class="{{ $btn['edit'] }}" onclick="openRecordModal(this)"
                                                data-record='@json($recordRow)'>
                                                <i class="text-xs fa-solid fa-pen" aria-hidden="true"></i><span>{{ $record ? 'Edit' : 'Record' }}</span>
                                            </button>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr role="row">
                                    <td role="cell" colspan="{{ $canEditRoster ? 6 : 5 }}" class="px-6 py-12 text-sm text-center text-gray-500 rt-empty dark:text-gray-400">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="mb-3 text-4xl text-gray-400 fa-solid fa-user-group" aria-hidden="true"></i>
                                            <p>No active staff found for this branch.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if(!$myStaff && !$canViewRoster)
            <div class="p-6 text-sm text-center text-gray-500 {{ $card }} dark:text-gray-400">
                No staff profile is linked to your account, so there is no attendance to show.
            </div>
        @endif
    </div>

    {{-- ════════════════════════════════════════════════════════
         LEAVE TAB
    ═══════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'leave'" x-cloak role="tabpanel" id="panel-leave" aria-labelledby="tab-leave" class="space-y-6">
        @if($canApproveLeave)
            <div id="pendingLeaveSection" class="hidden overflow-hidden bg-white border shadow-sm border-amber-200 rounded-2xl dark:bg-gray-800 dark:border-amber-800">
                <div class="flex items-center justify-between gap-3 px-4 py-4 border-b sm:px-6 border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/10">
                    <h2 class="text-base font-semibold text-amber-900 dark:text-amber-200">Pending Leave Approvals</h2>
                    <span id="pendingLeaveBadge" class="{{ $badgeBase }} bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">0 pending</span>
                </div>
                <div id="pendingLeaveList" class="p-4 space-y-4 sm:p-6" aria-live="polite"></div>
            </div>
        @endif

        <div class="{{ $card }}">
            <div class="flex flex-col gap-3 {{ $cardHead }} sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">My Leave Requests</h2>
                @if($canRequestLeave)
                    <button type="button" class="w-full {{ $btn['primary'] }} sm:w-auto" onclick="openRequestLeaveModal()">
                        <i class="text-xs fa-solid fa-plus" aria-hidden="true"></i> Request Leave
                    </button>
                @endif
            </div>
            <div id="myLeaveList" class="divide-y divide-gray-200 dark:divide-gray-700" aria-live="polite">
                @forelse($myLeaveRequests as $r)
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $leaveTypeLabels[$r->leave_type] ?? ucfirst($r->leave_type) }} —
                                {{ $r->start_date->format('M j, Y') }}@if(!$r->start_date->eq($r->end_date)) to {{ $r->end_date->format('M j, Y') }}@endif
                            </p>
                            <p class="text-xs text-gray-500 break-words dark:text-gray-400">{{ $r->reason }}</p>
                            @if($r->status === 'rejected' && $r->rejection_reason)
                                <p class="mt-1 text-xs text-red-700 dark:text-red-400">Rejected: {{ $r->rejection_reason }}</p>
                            @endif
                        </div>
                        <span class="{{ $badgeBase }} {{ $leaveStatusMeta[$r->status]['class'] ?? $fallbackBadge }}">{{ $leaveStatusMeta[$r->status]['label'] ?? ucfirst($r->status) }}</span>
                    </div>
                @empty
                    <div class="px-6 py-12 text-sm text-center text-gray-500 dark:text-gray-400">
                        <div class="flex flex-col items-center justify-center">
                            <i class="mb-3 text-4xl text-gray-400 fa-solid fa-calendar-xmark" aria-hidden="true"></i>
                            <p>You haven't requested any leave yet.</p>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════
     CLOCK OUT CONFIRMATION
     ═══════════════════════════════════════════════════ --}}
@if($myStaff && $myToday && $myToday->time_in && !$myToday->time_out)
<div id="clockOutModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="clockOutTitle" class="w-full max-w-md bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <form method="POST" action="{{ route('attendance.clock-out') }}">
                @csrf
                <div class="flex items-start justify-between gap-3 {{ $cardHead }}">
                    <h2 id="clockOutTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Clock out now?</h2>
                    <button type="button" onclick="closeModal('clockOutModal')" aria-label="Close dialog" class="{{ $closeBtn }}">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="px-4 py-6 space-y-2 text-sm text-gray-700 sm:px-6 dark:text-gray-300">
                    <p>Your time out will be recorded as <strong id="clockOutTime"></strong>.</p>
                    <p class="text-gray-500 dark:text-gray-400">This ends your day. Only a manager or HR can change it afterwards.</p>
                </div>
                <div class="flex flex-col-reverse gap-2 px-4 py-4 border-t border-gray-200 sm:flex-row sm:justify-end sm:px-6 dark:border-gray-700">
                    <button type="button" class="{{ $btn['neutral'] }}" onclick="closeModal('clockOutModal')">Cancel</button>
                    <button type="submit" class="{{ $btn['remove'] }}"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Clock Out</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════
     RECORD / CORRECT ATTENDANCE (edit attendance)
     ═══════════════════════════════════════════════════ --}}
@if($canEditRoster)
<div id="recordAttendanceModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="recordTitle" class="w-full max-w-md bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <form id="recordAttendanceForm" method="POST">
                @csrf
                <input type="hidden" name="date" id="record_date">
                <div class="flex items-start justify-between gap-3 {{ $cardHead }}">
                    <div>
                        <h2 id="recordTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Record Attendance</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><span id="record_staff_name"></span> · <span id="record_date_label"></span></p>
                    </div>
                    <button type="button" onclick="closeModal('recordAttendanceModal')" aria-label="Close dialog" class="{{ $closeBtn }}">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="px-4 py-6 space-y-4 sm:px-6">
                    <div>
                        <label for="record_status" class="{{ $labelClass }}">Status</label>
                        <select name="status" id="record_status" class="{{ $inputClass }}" onchange="syncRecordTimes()">
                            @foreach($statusMeta as $value => $meta)
                                <option value="{{ $value }}">{{ $meta['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="record_times" class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="record_time_in" class="{{ $labelClass }}">Time in</label>
                            <input type="time" name="time_in" id="record_time_in" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label for="record_time_out" class="{{ $labelClass }}">Time out</label>
                            <input type="time" name="time_out" id="record_time_out" class="{{ $inputClass }}">
                        </div>
                    </div>
                    <p id="record_times_note" class="hidden text-xs text-gray-500 dark:text-gray-400">No clock times for an absent or on-leave day.</p>
                    <div>
                        <label for="record_remarks" class="{{ $labelClass }}">Remarks <span class="font-normal text-gray-500">(optional)</span></label>
                        <input type="text" name="remarks" id="record_remarks" maxlength="500" class="{{ $inputClass }}">
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-2 px-4 py-4 border-t border-gray-200 sm:flex-row sm:justify-end sm:px-6 dark:border-gray-700">
                    <button type="button" class="{{ $btn['neutral'] }}" onclick="closeModal('recordAttendanceModal')">Cancel</button>
                    <button type="submit" class="{{ $btn['primary'] }}"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════
     REQUEST LEAVE (create leave requests)
     ═══════════════════════════════════════════════════ --}}
@if($canRequestLeave)
<div id="requestLeaveModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="requestLeaveTitle" class="w-full max-w-md bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 {{ $cardHead }}">
                <h2 id="requestLeaveTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Request Leave</h2>
                <button type="button" onclick="closeModal('requestLeaveModal')" aria-label="Close dialog" class="{{ $closeBtn }}">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <form id="requestLeaveForm" class="px-4 py-6 space-y-4 sm:px-6" novalidate>
                <div>
                    <label for="leave_type" class="{{ $labelClass }}">Leave type</label>
                    <select id="leave_type" class="{{ $inputClass }}">
                        @foreach($leaveTypeLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="leave_start_date" class="{{ $labelClass }}">Start date</label>
                        <input type="date" id="leave_start_date" required min="{{ $today->toDateString() }}" max="{{ $leaveMax }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label for="leave_end_date" class="{{ $labelClass }}">End date</label>
                        <input type="date" id="leave_end_date" required min="{{ $today->toDateString() }}" max="{{ $leaveMax }}" class="{{ $inputClass }}">
                    </div>
                </div>
                <p class="-mt-2 text-xs text-gray-500 dark:text-gray-400">From today up to {{ \Carbon\Carbon::parse($leaveMax)->format('M j, Y') }}.</p>
                <div>
                    <label for="leave_reason" class="{{ $labelClass }}">Reason <span class="text-red-600" aria-hidden="true">*</span></label>
                    <textarea id="leave_reason" rows="3" minlength="10" maxlength="1000" required class="{{ $inputClass }} resize-none" aria-describedby="leave_reason_hint"></textarea>
                    <p id="leave_reason_hint" class="mt-1 text-xs text-gray-500 dark:text-gray-400">At least 10 characters.</p>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    <i class="mr-1 fa-solid fa-circle-info" aria-hidden="true"></i>
                    Appointments you have on these dates are flagged for reassignment once the leave is approved.
                </p>
                <div id="requestLeaveError" role="alert" class="hidden p-3 text-sm text-red-700 rounded-xl bg-red-50 ring-1 ring-red-200 dark:bg-red-900/20 dark:text-red-300 dark:ring-red-800"></div>
                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                    <button type="button" class="{{ $btn['neutral'] }}" onclick="closeModal('requestLeaveModal')">Cancel</button>
                    <button type="submit" id="requestLeaveSubmitBtn" class="{{ $btn['primary'] }}">Submit Request</button>
                </div>
            </form>
            <div id="requestLeaveConfirmation" class="hidden px-4 py-6 space-y-4 sm:px-6" aria-live="polite">
                <div class="flex flex-col items-center text-center">
                    <div class="flex items-center justify-center w-12 h-12 mb-3 rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                        <i class="text-xl fa-solid fa-check text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i>
                    </div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Leave Request Submitted</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" id="requestLeaveConfirmDates"></p>
                </div>
                <div class="p-3 text-sm rounded-xl bg-amber-50 text-amber-800 dark:bg-amber-900/10 dark:text-amber-300">
                    Your manager will review this request. You'll be notified once it's approved or rejected.
                </div>
                <p id="requestLeaveConfirmAffectedLoading" class="text-xs text-center text-gray-500 dark:text-gray-400">Checking your appointments in this window…</p>
                <p id="requestLeaveConfirmAffectedNone" class="hidden text-xs text-center text-gray-500 dark:text-gray-400">None of your appointments fall within these dates.</p>
                <div id="requestLeaveConfirmAffectedWrapper" class="hidden p-3 border border-red-200 rounded-xl bg-red-50 dark:border-red-800 dark:bg-red-900/10">
                    <p class="text-xs font-semibold text-red-800 dark:text-red-300">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <span id="requestLeaveConfirmAffectedCount">0</span> of your appointment(s) fall in this window
                    </p>
                    <div id="requestLeaveConfirmAffectedList" class="mt-2 space-y-1 text-xs text-red-700 dark:text-red-400"></div>
                    <p class="mt-2 text-xs text-red-700 dark:text-red-400">They will be flagged for reassignment once your leave is approved — no action needed from you.</p>
                </div>
                <button type="button" class="w-full {{ $btn['primary'] }}" onclick="closeModal('requestLeaveModal')">Got it</button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════
     LEAVE REVIEW (edit leave requests)
     ═══════════════════════════════════════════════════ --}}
@if($canApproveLeave)
<div id="leaveReviewModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="leaveReviewTitle" class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 {{ $cardHead }}">
                <h2 id="leaveReviewTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Review Leave Request</h2>
                <button type="button" onclick="closeModal('leaveReviewModal')" aria-label="Close dialog" class="{{ $closeBtn }}">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="px-4 py-6 space-y-4 sm:px-6">
                <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-900/40">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white" id="leave_review_name"></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400" id="leave_review_dates"></p>
                    <p class="mt-2 text-sm text-gray-700 break-words dark:text-gray-300" id="leave_review_reason"></p>
                </div>
                <p id="leave_affected_loading" class="hidden text-xs text-gray-500 dark:text-gray-400">Checking affected appointments…</p>
                <p id="leave_affected_none" class="hidden text-xs text-gray-500 dark:text-gray-400">No appointments fall within this leave window.</p>
                <div id="leave_affected_wrapper" class="hidden p-3 border border-red-200 rounded-xl bg-red-50 dark:border-red-800 dark:bg-red-900/10">
                    <p class="text-xs font-semibold text-red-800 dark:text-red-300">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <span id="leave_affected_count">0</span> appointment(s) will need reassignment
                    </p>
                    <div id="leave_affected_list" class="mt-2 space-y-2"></div>
                    <p class="mt-2 text-xs text-red-700 dark:text-red-400">Pick a replacement now, or leave it blank and resolve it later on the Appointments page.</p>
                </div>
                <div id="leaveReviewError" role="alert" class="hidden p-3 text-sm text-red-700 rounded-xl bg-red-50 ring-1 ring-red-200 dark:bg-red-900/20 dark:text-red-300 dark:ring-red-800"></div>
            </div>
            <div class="flex flex-col-reverse gap-2 px-4 py-4 border-t border-gray-200 sm:flex-row sm:justify-between sm:px-6 dark:border-gray-700">
                <button type="button" class="{{ $btn['remove'] }}" onclick="openLeaveRejectReason()"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Reject</button>
                <button type="button" id="leaveApproveBtn" class="{{ $btn['primary'] }}" onclick="approveLeave()"><i class="fa-solid fa-check" aria-hidden="true"></i> <span>Approve</span></button>
            </div>
        </div>
    </div>
</div>

<div id="leaveRejectReasonModal" class="fixed inset-0 z-[60] hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="leaveRejectTitle" class="w-full max-w-md bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 {{ $cardHead }}">
                <h2 id="leaveRejectTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Reject Leave Request</h2>
                <button type="button" onclick="closeModal('leaveRejectReasonModal')" aria-label="Close dialog" class="{{ $closeBtn }}">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="px-4 py-6 sm:px-6">
                <label for="leave_reject_reason" class="{{ $labelClass }}">Reason <span class="text-red-600" aria-hidden="true">*</span></label>
                <textarea id="leave_reject_reason" rows="3" minlength="5" maxlength="500" required class="{{ $inputClass }} resize-none" aria-describedby="leave_reject_hint"></textarea>
                <p id="leave_reject_hint" class="mt-1 text-xs text-gray-500 dark:text-gray-400">At least 5 characters. The staff member sees this reason.</p>
                <p id="leaveRejectError" role="alert" class="hidden mt-2 text-sm text-red-700 dark:text-red-300"></p>
            </div>
            <div class="flex flex-col-reverse gap-2 px-4 py-4 border-t border-gray-200 sm:flex-row sm:justify-end sm:px-6 dark:border-gray-700">
                <button type="button" class="{{ $btn['neutral'] }}" onclick="closeModal('leaveRejectReasonModal')">Cancel</button>
                <button type="button" id="leaveRejectBtn" class="{{ $btn['remove'] }}" onclick="confirmRejectLeave()">Confirm Reject</button>
            </div>
        </div>
    </div>
</div>
@endif

<script>
function attendancePage(initial) {
    const TABS = ['attendance', 'leave'];
    return {
        tab: initial,
        setTab(t) {
            this.tab = t;
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', t);
                window.history.replaceState({}, '', url);
            } catch (e) {}
        },
        cycle(step) {
            const next = TABS[(TABS.indexOf(this.tab) + step + TABS.length) % TABS.length];
            this.setTab(next);
            this.$nextTick(() => document.getElementById('tab-' + next)?.focus());
        },
    };
}

(function () {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    const el = id => document.getElementById(id);
    const toast = (msg, type) => { if (typeof showSpaToast === 'function') showSpaToast(msg, type); };

    // ONE source for these maps: emitted from the PHP arrays above.
    const LEAVE_STATUS = @json($leaveStatusMeta);
    const LEAVE_TYPES  = @json($leaveTypeLabels);
    const BADGE        = @json($badgeBase);
    const FALLBACK     = @json($fallbackBadge);

    // ── Shared modal behaviour (same contract as the payroll pages) ──────
    const MODAL_IDS = ['clockOutModal', 'recordAttendanceModal', 'requestLeaveModal', 'leaveReviewModal', 'leaveRejectReasonModal'];
    const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    const returnFocus = {};
    let currentLeaveReview = null;

    function topmostOpenModal() {
        for (let i = MODAL_IDS.length - 1; i >= 0; i--) {
            const m = el(MODAL_IDS[i]);
            if (m && !m.classList.contains('hidden')) return m;
        }
        return null;
    }
    function openModal(id, focusSelector) {
        const m = el(id);
        if (!m) return;
        returnFocus[id] = document.activeElement;
        m.classList.remove('hidden');
        const target = (focusSelector && m.querySelector(focusSelector)) || m.querySelector(FOCUSABLE);
        if (target) target.focus();
    }
    function closeModal(id) {
        const m = el(id);
        if (!m) return;
        m.classList.add('hidden');
        const back = returnFocus[id];
        if (back && document.contains(back)) back.focus();
        delete returnFocus[id];
        if (id === 'leaveReviewModal') currentLeaveReview = null;
    }
    window.closeModal = closeModal;

    document.addEventListener('keydown', function (e) {
        const modal = topmostOpenModal();
        if (!modal) return;
        if (e.key === 'Escape') { e.preventDefault(); closeModal(modal.id); return; }
        if (e.key !== 'Tab') return;
        const items = Array.from(modal.querySelectorAll(FOCUSABLE)).filter(x => x.offsetParent !== null);
        if (!items.length) return;
        const first = items[0], last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
    MODAL_IDS.forEach(id => {
        const m = el(id);
        if (m) m.addEventListener('click', e => { if (e.target === m) closeModal(id); });
    });

    // ── Clock out ────────────────────────────────────────────────────────
    window.openClockOutModal = function () {
        const t = el('clockOutTime');
        if (t) t.textContent = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        openModal('clockOutModal', 'button[type="submit"]');
    };

    // ── Record / correct attendance ──────────────────────────────────────
    window.syncRecordTimes = function () {
        const status = el('record_status');
        if (!status) return;
        const noTimes = ['absent', 'on_leave'].includes(status.value);
        el('record_times').classList.toggle('hidden', noTimes);
        el('record_times_note').classList.toggle('hidden', !noTimes);
        el('record_time_in').disabled = noTimes;
        el('record_time_out').disabled = noTimes;
    };
    window.openRecordModal = function (btn) {
        const d = JSON.parse(btn.getAttribute('data-record') || '{}');
        el('record_staff_name').textContent = d.name || '';
        el('record_date_label').textContent = d.date_label || d.date || '';
        el('record_date').value = d.date || '';
        el('record_status').value = d.status || 'present';
        el('record_time_in').value = d.time_in || '';
        el('record_time_out').value = d.time_out || '';
        el('record_remarks').value = d.remarks || '';
        el('recordAttendanceForm').action = @json(url('/attendance')) + '/' + encodeURIComponent(d.staff_id) + '/record';
        window.syncRecordTimes();
        openModal('recordAttendanceModal', '#record_status');
    };

    // ── Request leave ────────────────────────────────────────────────────
    const fmtDate = ymd => ymd ? new Date(ymd + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';

    window.openRequestLeaveModal = function () {
        el('requestLeaveForm').reset();
        el('requestLeaveError').classList.add('hidden');
        el('requestLeaveForm').classList.remove('hidden');
        el('requestLeaveConfirmation').classList.add('hidden');
        el('leave_end_date').min = el('leave_start_date').min;
        openModal('requestLeaveModal', '#leave_type');
    };
    el('leave_start_date')?.addEventListener('change', function () {
        const end = el('leave_end_date');
        end.min = this.value || this.min;
        if (end.value && this.value && end.value < this.value) end.value = this.value;
    });
    el('requestLeaveForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const errorBox = el('requestLeaveError');
        const btn = el('requestLeaveSubmitBtn');
        const start = el('leave_start_date'), end = el('leave_end_date');
        const reason = el('leave_reason').value.trim();
        const fail = msg => { errorBox.textContent = msg; errorBox.classList.remove('hidden'); };

        if (!start.value || !end.value) return fail('Choose a start and an end date.');
        if (start.value < start.min || end.value > end.max) return fail('Leave must be from today up to ' + fmtDate(end.max) + '.');
        if (end.value < start.value) return fail('The end date cannot be before the start date.');
        if (reason.length < 10) return fail('Reason must be at least 10 characters.');

        btn.disabled = true;
        btn.textContent = 'Submitting…';
        try {
            const res = await fetch(@json(route('leave-requests.store')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                body: JSON.stringify({ leave_type: el('leave_type').value, start_date: start.value, end_date: end.value, reason }),
            });
            const data = await res.json();
            if (!res.ok) return fail(data.message ?? 'Something went wrong.');

            // A confirmation panel instead of a toast: the reassignment note is easy to miss in a toast.
            el('requestLeaveForm').classList.add('hidden');
            el('requestLeaveConfirmation').classList.remove('hidden');
            el('requestLeaveConfirmDates').textContent = fmtDate(start.value) + (start.value !== end.value ? ' to ' + fmtDate(end.value) : '');
            el('requestLeaveConfirmAffectedWrapper').classList.add('hidden');
            el('requestLeaveConfirmAffectedNone').classList.add('hidden');
            el('requestLeaveConfirmAffectedLoading').classList.remove('hidden');
            loadMyLeaveRequests();

            const leaveId = data.data?.id;
            let bookings = [];
            if (leaveId) {
                try {
                    const r = await fetch(@json(url('/leave-requests')) + '/' + encodeURIComponent(leaveId) + '/affected-bookings', { headers: { 'Accept': 'application/json' } });
                    bookings = r.ok ? await r.json() : [];
                } catch (err) {}
            }
            el('requestLeaveConfirmAffectedLoading').classList.add('hidden');
            if (bookings.length) {
                el('requestLeaveConfirmAffectedCount').textContent = bookings.length;
                el('requestLeaveConfirmAffectedList').innerHTML = bookings.map(b =>
                    `<div>• ${esc(b.customer_name)} — ${esc(b.appointment_date)} at ${esc(b.start_time_fmt)}</div>`).join('');
                el('requestLeaveConfirmAffectedWrapper').classList.remove('hidden');
            } else {
                el('requestLeaveConfirmAffectedNone').classList.remove('hidden');
            }
            el('requestLeaveConfirmation').querySelector('button')?.focus();
        } catch (err) {
            fail('Network error. Please try again.');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Submit Request';
        }
    });

    // ── My leave requests (poll) ─────────────────────────────────────────
    async function loadMyLeaveRequests() {
        try {
            const res = await fetch(@json(route('leave-requests.mine')), { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const list = await res.json();
            const container = el('myLeaveList');
            if (!list.length) {
                container.innerHTML = `
                    <div class="px-6 py-12 text-sm text-center text-gray-500 dark:text-gray-400">
                        <div class="flex flex-col items-center justify-center">
                            <i class="mb-3 text-4xl text-gray-400 fa-solid fa-calendar-xmark" aria-hidden="true"></i>
                            <p>You haven't requested any leave yet.</p>
                        </div>
                    </div>`;
                return;
            }
            container.innerHTML = list.map(r => {
                const st = LEAVE_STATUS[r.status] || { label: r.status, class: FALLBACK };
                return `
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">${esc(LEAVE_TYPES[r.leave_type] || r.leave_type)} — ${esc(r.start_date)}${r.start_date !== r.end_date ? ' to ' + esc(r.end_date) : ''}</p>
                        <p class="text-xs text-gray-500 break-words dark:text-gray-400">${esc(r.reason)}</p>
                        ${r.status === 'rejected' && r.rejection_reason ? `<p class="mt-1 text-xs text-red-700 dark:text-red-400">Rejected: ${esc(r.rejection_reason)}</p>` : ''}
                    </div>
                    <span class="${esc(BADGE)} ${esc(st.class)}">${esc(st.label)}</span>
                </div>`;
            }).join('');
        } catch (err) { console.warn('My leave poll failed:', err); }
    }

    @if($canApproveLeave)
    // ── Pending approvals (poll) ─────────────────────────────────────────
    const pending = new Map();   // id → request; the Review button carries only the id (no inline JSON)

    async function loadPendingLeave() {
        try {
            const res = await fetch(@json(route('leave-requests.index')), { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const list = await res.json();
            const section = el('pendingLeaveSection');
            const tabBadge = el('pendingTabBadge');
            pending.clear();
            list.forEach(r => pending.set(String(r.id), r));

            if (tabBadge) {
                tabBadge.textContent = list.length;
                tabBadge.classList.toggle('hidden', !list.length);
            }
            if (!list.length) { section.classList.add('hidden'); return; }
            section.classList.remove('hidden');
            el('pendingLeaveBadge').textContent = list.length + ' pending';

            el('pendingLeaveList').innerHTML = list.map(r => `
                <div class="p-4 border rounded-2xl border-amber-200 bg-amber-50/60 dark:border-amber-800 dark:bg-amber-900/10">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <dl class="grid flex-1 grid-cols-1 gap-3 md:grid-cols-3">
                            <div>
                                <dt class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Staff</dt>
                                <dd class="mt-1 font-medium text-gray-900 dark:text-white">${esc(r.user_name)} <span class="text-xs text-gray-500 dark:text-gray-400">(${esc(r.role)})</span></dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Dates</dt>
                                <dd class="mt-1 font-medium text-gray-900 dark:text-white">${esc(r.start_date)} – ${esc(r.end_date)} (${Number(r.days)} day${Number(r.days) === 1 ? '' : 's'})</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Type</dt>
                                <dd class="mt-1 font-medium text-gray-900 dark:text-white">${esc(LEAVE_TYPES[r.leave_type] || r.leave_type)}</dd>
                            </div>
                        </dl>
                        <button type="button" data-review-id="${esc(r.id)}" class="leave-review-btn inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm font-medium text-white rounded-xl bg-amber-600 hover:bg-amber-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800">Review</button>
                    </div>
                </div>`).join('');
        } catch (err) { console.warn('Pending leave poll failed:', err); }
    }

    el('pendingLeaveList')?.addEventListener('click', e => {
        const b = e.target.closest('[data-review-id]');
        if (b && pending.has(b.dataset.reviewId)) openLeaveReviewModal(pending.get(b.dataset.reviewId));
    });

    async function openLeaveReviewModal(r) {
        currentLeaveReview = r;
        el('leave_review_name').textContent = r.user_name + ' — ' + (LEAVE_TYPES[r.leave_type] || r.leave_type);
        el('leave_review_dates').textContent = r.start_date + ' to ' + r.end_date + ' (' + r.days + ' day' + (Number(r.days) === 1 ? '' : 's') + ')';
        el('leave_review_reason').textContent = r.reason;
        el('leaveReviewError').classList.add('hidden');
        el('leave_affected_wrapper').classList.add('hidden');
        el('leave_affected_none').classList.add('hidden');
        el('leave_affected_loading').classList.remove('hidden');
        openModal('leaveReviewModal', '#leaveApproveBtn');

        try {
            const res = await fetch(@json(url('/leave-requests')) + '/' + encodeURIComponent(r.id) + '/affected-bookings', { headers: { 'Accept': 'application/json' } });
            const bookings = res.ok ? await res.json() : [];
            el('leave_affected_loading').classList.add('hidden');
            if (!bookings.length) { el('leave_affected_none').classList.remove('hidden'); return; }

            el('leave_affected_count').textContent = bookings.length;
            el('leave_affected_list').innerHTML = bookings.map(b => {
                const hasPicker = Array.isArray(b.available_therapists);
                const options = (b.available_therapists || []).map(t =>
                    `<option value="${esc(t.id)}" ${Number(b.recommended_id) === Number(t.id) ? 'selected' : ''}>${esc(t.name)}</option>`).join('');
                return `
                <div class="p-3 text-xs bg-white border border-red-100 rounded-xl dark:bg-gray-800 dark:border-red-900/40">
                    <p class="font-medium text-gray-800 dark:text-gray-200">${esc(b.customer_name)} — ${esc(b.appointment_date)} at ${esc(b.start_time_fmt)}</p>
                    ${hasPicker ? `
                    <label for="affected_${esc(b.id)}" class="sr-only">Replacement therapist for ${esc(b.customer_name)}</label>
                    <select id="affected_${esc(b.id)}" data-affected-booking-id="${esc(b.id)}" class="w-full min-h-[44px] px-3 py-2 mt-2 text-sm border border-gray-300 rounded-xl bg-white text-gray-900 focus:ring-[#8B7355] focus:border-[#8B7355] dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">— Leave unassigned, resolve later —</option>
                        ${options}
                    </select>` : ''}
                </div>`;
            }).join('');
            el('leave_affected_wrapper').classList.remove('hidden');
        } catch (err) {
            el('leave_affected_loading').classList.add('hidden');
            console.warn('Affected bookings fetch failed:', err);
        }
    }

    window.approveLeave = async function () {
        if (!currentLeaveReview) return;
        const errorBox = el('leaveReviewError');
        const btn = el('leaveApproveBtn');
        btn.disabled = true;
        btn.querySelector('span').textContent = 'Approving…';
        const reassignments = Array.from(document.querySelectorAll('[data-affected-booking-id]'))
            .filter(s => s.value)
            .map(s => ({ booking_id: Number(s.dataset.affectedBookingId), new_therapist_id: Number(s.value) }));
        try {
            const res = await fetch(@json(url('/leave-requests')) + '/' + encodeURIComponent(currentLeaveReview.id) + '/approve', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                body: JSON.stringify({ reassignments }),
            });
            const data = await res.json();
            if (!res.ok) { errorBox.textContent = data.message ?? 'Something went wrong.'; errorBox.classList.remove('hidden'); return; }
            closeModal('leaveReviewModal');
            toast(data.message, 'success');
            loadPendingLeave();
        } catch (err) {
            errorBox.textContent = 'Network error. Please try again.';
            errorBox.classList.remove('hidden');
        } finally {
            btn.disabled = false;
            btn.querySelector('span').textContent = 'Approve';
        }
    };

    window.openLeaveRejectReason = function () {
        el('leave_reject_reason').value = '';
        el('leaveRejectError').classList.add('hidden');
        openModal('leaveRejectReasonModal', '#leave_reject_reason');
    };

    window.confirmRejectLeave = async function () {
        if (!currentLeaveReview) return;
        const reason = el('leave_reject_reason').value.trim();
        const err = el('leaveRejectError');
        if (reason.length < 5) { err.textContent = 'Reason must be at least 5 characters.'; err.classList.remove('hidden'); return; }
        const btn = el('leaveRejectBtn');
        btn.disabled = true;
        try {
            const res = await fetch(@json(url('/leave-requests')) + '/' + encodeURIComponent(currentLeaveReview.id) + '/reject', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                body: JSON.stringify({ rejection_reason: reason }),
            });
            const data = await res.json();
            if (!res.ok) { err.textContent = data.message ?? 'Something went wrong.'; err.classList.remove('hidden'); return; }
            closeModal('leaveRejectReasonModal');
            closeModal('leaveReviewModal');
            toast(data.message, 'success');
            loadPendingLeave();
        } catch (e) {
            err.textContent = 'Network error. Please try again.';
            err.classList.remove('hidden');
        } finally {
            btn.disabled = false;
        }
    };

    loadPendingLeave();
    setInterval(loadPendingLeave, 30000);
    @endif

    loadMyLeaveRequests();
    setInterval(loadMyLeaveRequests, 30000);
})();
</script>
@endsection
