@extends('layouts.app')

@section('title', 'Attendance & Leave')
@section('content')
@php
    $statusClasses = [
        'present'  => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        'late'     => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
        'absent'   => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        'on_leave' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
    ];
    $leaveStatusClasses = [
        'pending'  => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'approved' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    ];
    $leaveTypeLabels = [
        'sick' => 'Sick', 'emergency' => 'Emergency', 'vacation' => 'Vacation',
        'personal' => 'Personal', 'other' => 'Other',
    ];
@endphp

<div class="p-6 mx-auto space-y-6 max-w-7xl" x-data="{ tab: new URLSearchParams(window.location.search).get('tab') || 'attendance' }">

    <x-page-header
        title="Attendance & Leave"
        subtitle="Clock in and out, review your team's attendance, and manage time-off requests."
    />


    {{-- ════════════════════════════════════════════════════════
         ATTENDANCE TAB
    ═══════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'attendance'" x-cloak class="space-y-6">

        {{-- ── My Attendance ── --}}
        @if($myStaff)
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">My Attendance Today</p>
                    @if(!$myToday || !$myToday->time_in)
                        <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Not clocked in yet</p>
                    @elseif(!$myToday->time_out)
                        <p class="mt-1 text-lg font-semibold text-emerald-700 dark:text-emerald-400">
                            Clocked in at {{ \Carbon\Carbon::parse($myToday->time_in)->format('h:i A') }}
                        </p>
                        <span class="inline-flex items-center px-2 py-0.5 mt-1 text-xs font-medium rounded-full {{ $statusClasses[$myToday->status] ?? '' }}">
                            {{ ucfirst(str_replace('_', ' ', $myToday->status)) }}
                        </span>
                    @else
                        <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                            {{ \Carbon\Carbon::parse($myToday->time_in)->format('h:i A') }} – {{ \Carbon\Carbon::parse($myToday->time_out)->format('h:i A') }}
                        </p>
                        <span class="inline-flex items-center px-2 py-0.5 mt-1 text-xs font-medium rounded-full {{ $statusClasses[$myToday->status] ?? '' }}">
                            {{ ucfirst(str_replace('_', ' ', $myToday->status)) }}
                        </span>
                    @endif
                </div>

                <div class="flex gap-2">
                    @if(!$myToday || !$myToday->time_in)
                        <form method="POST" action="{{ route('attendance.clock-in') }}">
                            @csrf
                            <button type="submit" class="px-5 py-2.5 text-sm font-semibold text-white rounded-xl bg-[#8B7355] hover:bg-[#7A6348] transition">
                                <i class="mr-1.5 fa-solid fa-right-to-bracket"></i> Clock In
                            </button>
                        </form>
                    @elseif(!$myToday->time_out)
                        <form method="POST" action="{{ route('attendance.clock-out') }}">
                            @csrf
                            <button type="submit" class="px-5 py-2.5 text-sm font-semibold text-white bg-red-600 rounded-xl hover:bg-red-700 transition">
                                <i class="mr-1.5 fa-solid fa-right-from-bracket"></i> Clock Out
                            </button>
                        </form>
                    @else
                        <span class="px-4 py-2.5 text-sm font-medium text-gray-400 dark:text-gray-500">Day complete</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- My recent history --}}
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">My Last 14 Days</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Time In</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Time Out</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                        @forelse($myHistory as $row)
                            <tr>
                                <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ \Carbon\Carbon::parse($row->date)->format('D, M j') }}</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full {{ $statusClasses[$row->status] ?? '' }}">
                                        {{ ucfirst(str_replace('_', ' ', $row->status)) }}
                                    </span>
                                    @if($row->auto_closed)
                                        <span class="ml-1 text-[10px] text-gray-400" title="Clock-out was auto-recorded — you may have forgotten to clock out.">(auto)</span>
                                    @endif
                                    @if($row->remarks && str_contains($row->remarks, 'Auto-flag'))
                                        <span class="ml-1 text-[10px] text-amber-500" title="{{ $row->remarks }}"><i class="fa-solid fa-triangle-exclamation"></i></span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->time_in ? \Carbon\Carbon::parse($row->time_in)->format('h:i A') : '—' }}</td>
                                <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->time_out ? \Carbon\Carbon::parse($row->time_out)->format('h:i A') : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-8 text-sm text-center text-gray-400">No attendance history yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- ── Team roster (view attendance) ── --}}
        @if($canViewRoster)
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Branch Roster</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $date->format('l, F j, Y') }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="inline-flex gap-1 p-1 border border-gray-200 bg-gray-50 rounded-xl dark:bg-gray-900/40 dark:border-gray-600">
                        <button type="button" @click="tab = 'attendance'"
                                :class="tab === 'attendance' ? 'bg-[#8B7355] text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700'"
                                class="flex items-center justify-center gap-1.5 px-4 py-1.5 text-xs font-semibold transition rounded-lg">
                            <i class="fa-solid fa-clock"></i>
                            Attendance
                        </button>
                        <button type="button" @click="tab = 'leave'"
                                :class="tab === 'leave' ? 'bg-[#8B7355] text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700'"
                                class="flex items-center justify-center gap-1.5 px-4 py-1.5 text-xs font-semibold transition rounded-lg">
                            <i class="fa-solid fa-calendar-days"></i>
                            Leave Requests
                        </button>
                    </div>

                    <form method="GET" action="{{ route('attendance.index') }}" class="flex items-center gap-2">
                        <input type="date" name="date" value="{{ $date->toDateString() }}"
                            class="px-3 py-2 text-sm border border-gray-200 rounded-xl dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <button type="submit" class="px-4 py-2 text-sm font-semibold text-white rounded-xl bg-[#8B7355] hover:bg-[#7A6348]">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Staff</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Role</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Time In</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Time Out</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Source</th>
                            @if($canEditRoster)
                                <th class="px-6 py-3 text-xs font-medium text-center text-gray-500 uppercase">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                        @forelse($staffList as $staff)
                            @php $record = $staff->attendance->first(); @endphp
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center flex-shrink-0 w-9 h-9 rounded-full bg-[#8B7355] text-white font-semibold text-sm">
                                            {{ strtoupper(substr($staff->user->name ?? 'S', 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $staff->user->name ?? '—' }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $staff->user->email ?? '' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-[#F6EFE6] text-[#6F5430] dark:bg-gray-700 dark:text-gray-200">
                                        {{ ucfirst($staff->user->getRoleNames()->first() ?? 'N/A') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($record)
                                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full {{ $statusClasses[$record->status] ?? '' }}">
                                            {{ ucfirst(str_replace('_', ' ', $record->status)) }}
                                        </span>
                                        @if($record->remarks && str_contains($record->remarks, 'Auto-flag'))
                                            <span class="ml-1 text-[10px] text-amber-500" title="{{ $record->remarks }}"><i class="fa-solid fa-triangle-exclamation"></i></span>
                                        @endif
                                    @else
                                        <span class="text-xs italic text-gray-400">No record</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $record?->time_in ? \Carbon\Carbon::parse($record->time_in)->format('h:i A') : '—' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $record?->time_out ? \Carbon\Carbon::parse($record->time_out)->format('h:i A') : '—' }}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $record ? ucfirst($record->source) : '—' }}
                                </td>
                                @if($canEditRoster)
                                    <td class="px-6 py-4 text-center">
                                        <button type="button" onclick="openRecordModal(this)"
                                            data-staff-id="{{ $staff->id }}"
                                            data-staff-name="{{ $staff->user->name ?? '' }}"
                                            data-date="{{ $date->toDateString() }}"
                                            data-status="{{ $record->status ?? 'present' }}"
                                            data-time-in="{{ $record?->time_in ? \Carbon\Carbon::parse($record->time_in)->format('H:i') : '' }}"
                                            data-time-out="{{ $record?->time_out ? \Carbon\Carbon::parse($record->time_out)->format('H:i') : '' }}"
                                            data-remarks="{{ $record->remarks ?? '' }}"
                                            class="px-3 py-1.5 text-sm text-white rounded-lg bg-[#8B7355] hover:bg-[#7A6348]">
                                            {{ $record ? 'Edit' : 'Record' }}
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-10 text-sm text-center text-gray-400">No active staff found for this branch.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>

    {{-- ════════════════════════════════════════════════════════
         LEAVE TAB
    ═══════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'leave'" x-cloak class="space-y-6">
        @if($canApproveLeave)
        <div id="pendingLeaveSection" class="hidden overflow-hidden bg-white border shadow-sm border-amber-200 rounded-2xl dark:bg-gray-800 dark:border-amber-800">
            <div class="flex items-center justify-between px-6 py-4 border-b border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/10">
                <h2 class="text-base font-semibold text-amber-900 dark:text-amber-200">Pending Leave Approvals</h2>
                <span id="pendingLeaveBadge" class="px-3 py-1 text-xs font-semibold rounded-full text-amber-800 bg-amber-100 dark:bg-amber-900/40 dark:text-amber-300">0 Pending</span>
            </div>
            <div id="pendingLeaveList" class="p-6 space-y-4"></div>
        </div>
        @endif

        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">My Leave Requests</h2>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="inline-flex gap-1 p-1 border border-gray-200 bg-gray-50 rounded-xl dark:bg-gray-900/40 dark:border-gray-600">
                        <button type="button" @click="tab = 'attendance'"
                                :class="tab === 'attendance' ? 'bg-[#8B7355] text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700'"
                                class="flex items-center justify-center gap-1.5 px-4 py-1.5 text-xs font-semibold transition rounded-lg">
                            <i class="fa-solid fa-clock"></i>
                            Attendance
                        </button>
                        <button type="button" @click="tab = 'leave'"
                                :class="tab === 'leave' ? 'bg-[#8B7355] text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700'"
                                class="flex items-center justify-center gap-1.5 px-4 py-1.5 text-xs font-semibold transition rounded-lg">
                            <i class="fa-solid fa-calendar-days"></i>
                            Leave Requests
                        </button>
                    </div>

                    <button type="button" onclick="openRequestLeaveModal()"
                        class="px-4 py-2 text-sm font-semibold text-white rounded-xl bg-[#8B7355] hover:bg-[#7A6348] transition">
                        <i class="mr-1.5 fa-solid fa-plus"></i> Request Leave
                    </button>
                </div>
            </div>
            <div id="myLeaveList" class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($myLeaveRequests as $r)
                    <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $leaveTypeLabels[$r->leave_type] ?? ucfirst($r->leave_type) }} —
                                {{ $r->start_date->format('M j, Y') }}
                                @if(!$r->start_date->eq($r->end_date)) to {{ $r->end_date->format('M j, Y') }} @endif
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $r->reason }}</p>
                            @if($r->status === 'rejected' && $r->rejection_reason)
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">Rejected: {{ $r->rejection_reason }}</p>
                            @endif
                        </div>
                        <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $leaveStatusClasses[$r->status] ?? '' }}">
                            {{ ucfirst($r->status) }}
                        </span>
                    </div>
                @empty
                    <p class="px-6 py-8 text-sm text-center text-gray-400">You haven't requested any leave yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════
     RECORD / CORRECT ATTENDANCE MODAL (manager/reception/HR/owner)
     ═══════════════════════════════════════════════════ --}}
@if($canEditRoster)
<div id="recordAttendanceModal" class="fixed inset-0 z-50 hidden p-4 bg-black/50">
    <div class="w-full max-w-md mx-auto mt-24 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Record Attendance</h2>
            <button type="button" onclick="closeRecordModal()" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <form id="recordAttendanceForm" method="POST" class="px-6 py-6 space-y-4">
            @csrf
            <p class="text-sm font-medium text-gray-800 dark:text-white" id="record_staff_name"></p>
            <input type="hidden" name="date" id="record_date">

            <div>
                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <select name="status" id="record_status" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="present">Present</option>
                    <option value="late">Late</option>
                    <option value="absent">Absent</option>
                    <option value="on_leave">On Leave</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Time In</label>
                    <input type="time" name="time_in" id="record_time_in" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Time Out</label>
                    <input type="time" name="time_out" id="record_time_out" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>
            </div>

            <div>
                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Remarks</label>
                <input type="text" name="remarks" id="record_remarks" maxlength="500" placeholder="Optional"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeRecordModal()" class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white rounded-xl bg-[#8B7355] hover:bg-[#7A6348]">Save</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════
     REQUEST LEAVE MODAL (everyone)
     ═══════════════════════════════════════════════════ --}}
<div id="requestLeaveModal" class="fixed inset-0 z-50 hidden p-4 bg-black/50">
    <div class="w-full max-w-md mx-auto mt-16 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Request Leave</h2>
            <button type="button" onclick="closeRequestLeaveModal()" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <form id="requestLeaveForm" class="px-6 py-6 space-y-4">
            <div>
                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Leave Type</label>
                <select id="leave_type" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="sick">Sick</option>
                    <option value="emergency">Emergency</option>
                    <option value="vacation">Vacation</option>
                    <option value="personal">Personal</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Start Date</label>
                    <input type="date" id="leave_start_date" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">End Date</label>
                    <input type="date" id="leave_end_date" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>
            </div>
            <div>
                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Reason <span class="text-red-500">*</span></label>
                <textarea id="leave_reason" rows="3" minlength="10" maxlength="1000"
                    class="w-full px-3 py-2 text-sm border border-gray-300 resize-none rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    placeholder="Minimum 10 characters"></textarea>
            </div>
            <p class="text-xs text-gray-400">
                <i class="fa-solid fa-circle-info"></i>
                If any of your appointments fall within these dates, they'll be automatically flagged for reassignment once your manager approves this.
            </p>
            <div id="requestLeaveError" class="hidden p-3 text-sm text-red-600 rounded-xl bg-red-50 ring-1 ring-red-200 dark:bg-red-900/20 dark:ring-red-800"></div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeRequestLeaveModal()" class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200">Cancel</button>
                <button type="submit" id="requestLeaveSubmitBtn" class="px-4 py-2 text-sm font-medium text-white rounded-xl bg-[#8B7355] hover:bg-[#7A6348]">Submit Request</button>
            </div>
        </form>

        <div id="requestLeaveConfirmation" class="hidden px-6 py-6 space-y-4">
            <div class="flex flex-col items-center text-center">
                <div class="flex items-center justify-center w-12 h-12 mb-3 rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                    <i class="text-xl fa-solid fa-check text-emerald-600 dark:text-emerald-400"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Leave Request Submitted</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" id="requestLeaveConfirmDates"></p>
            </div>

            <div class="p-3 text-sm rounded-xl bg-amber-50 text-amber-800 dark:bg-amber-900/10 dark:text-amber-300">
                Your manager will review this request. You'll be notified once it's approved or rejected.
            </div>

            <div id="requestLeaveConfirmAffectedLoading" class="text-xs text-center text-gray-400">Checking your appointments in this window…</div>
            <div id="requestLeaveConfirmAffectedNone" class="hidden text-xs text-center text-gray-400">None of your appointments fall within these dates.</div>
            <div id="requestLeaveConfirmAffectedWrapper" class="hidden p-3 border border-red-200 rounded-xl bg-red-50 dark:border-red-800 dark:bg-red-900/10">
                <p class="text-xs font-semibold text-red-800 dark:text-red-300">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span id="requestLeaveConfirmAffectedCount">0</span> of your appointment(s) fall in this window
                </p>
                <div id="requestLeaveConfirmAffectedList" class="mt-2 space-y-1 text-xs text-red-700 dark:text-red-400"></div>
                <p class="mt-2 text-[11px] text-red-600 dark:text-red-400">
                    These will be automatically flagged for reassignment once your leave is approved — no action needed from you.
                </p>
            </div>

            <button type="button" onclick="closeRequestLeaveModal()"
                    class="w-full px-4 py-2 text-sm font-medium text-white rounded-xl bg-[#8B7355] hover:bg-[#7A6348]">
                Got it
            </button>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════
     LEAVE REVIEW MODAL (approve/reject)
     ═══════════════════════════════════════════════════ --}}
@if($canApproveLeave)
<div id="leaveReviewModal" class="fixed inset-0 z-50 hidden p-4 bg-black/50">
    <div class="w-full max-w-md mx-auto mt-16 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Review Leave Request</h2>
            <button type="button" onclick="closeLeaveReviewModal()" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <div class="px-6 py-6 space-y-4">
            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-900/40">
                <p class="text-sm font-semibold text-gray-900 dark:text-white" id="leave_review_name"></p>
                <p class="text-sm text-gray-500 dark:text-gray-400" id="leave_review_dates"></p>
                <p class="mt-2 text-sm italic text-gray-600 dark:text-gray-300" id="leave_review_reason"></p>
            </div>
            <div id="leave_affected_loading" class="hidden text-xs text-gray-400">Checking affected appointments…</div>
            <div id="leave_affected_none" class="hidden text-xs text-gray-400">No appointments fall within this leave window.</div>
            <div id="leave_affected_wrapper" class="hidden p-3 border border-red-200 rounded-xl bg-red-50 dark:border-red-800 dark:bg-red-900/10">
                <p class="text-xs font-semibold text-red-800 dark:text-red-300">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span id="leave_affected_count">0</span> appointment(s) will need reassignment
                </p>
                <div id="leave_affected_list" class="mt-2 space-y-2"></div>
                <p class="mt-2 text-[11px] text-red-600 dark:text-red-400">
                    Pick a replacement for any of these now, or leave blank to resolve later from the Appointments page.
                </p>
            </div>
            <div id="leaveReviewError" class="hidden p-3 text-sm text-red-600 rounded-xl bg-red-50 ring-1 ring-red-200 dark:bg-red-900/20 dark:ring-red-800"></div>
            <div class="flex justify-between gap-2 pt-2">
                <button type="button" onclick="openLeaveRejectReason()" class="px-4 py-2 text-sm text-white bg-red-600 rounded-xl hover:bg-red-700">Reject</button>
                <button type="button" id="leaveApproveBtn" onclick="approveLeave()" class="px-4 py-2 text-sm font-medium text-white rounded-xl bg-[#8B7355] hover:bg-[#7A6348] disabled:opacity-50 disabled:cursor-not-allowed">Approve</button>
            </div>
        </div>
    </div>
</div>

<div id="leaveRejectReasonModal" class="fixed inset-0 z-[60] hidden p-4 bg-black/50">
    <div class="w-full max-w-md mx-auto mt-32 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Reject Leave Request</h3>
        </div>
        <div class="px-6 py-4">
            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Reason <span class="text-red-500">*</span></label>
            <textarea id="leave_reject_reason" rows="3" minlength="5" maxlength="500"
                class="w-full px-3 py-2 text-sm border border-gray-300 resize-none rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white"></textarea>
        </div>
        <div class="flex justify-end gap-2 px-6 py-4 bg-gray-50 dark:bg-gray-900/40 rounded-b-2xl">
            <button type="button" onclick="closeLeaveRejectReason()" class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200">Cancel</button>
            <button type="button" onclick="confirmRejectLeave()" class="px-4 py-2 text-sm text-white bg-red-600 rounded-xl hover:bg-red-700">Confirm Reject</button>
        </div>
    </div>
</div>
@endif

<script>
function csrf() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''; }
function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }

// ── Record / correct attendance modal ──────────────────────────────────
@if($canEditRoster)
function openRecordModal(btn) {
    const d = btn.dataset;
    document.getElementById('record_staff_name').textContent = d.staffName;
    document.getElementById('record_date').value = d.date;
    document.getElementById('record_status').value = d.status || 'present';
    document.getElementById('record_time_in').value = d.timeIn || '';
    document.getElementById('record_time_out').value = d.timeOut || '';
    document.getElementById('record_remarks').value = d.remarks || '';
    document.getElementById('recordAttendanceForm').action = '/attendance/' + d.staffId + '/record';
    document.getElementById('recordAttendanceModal').classList.remove('hidden');
}
function closeRecordModal() { document.getElementById('recordAttendanceModal').classList.add('hidden'); }
@endif

// ── Request leave modal ─────────────────────────────────────────────────
function openRequestLeaveModal() {
    document.getElementById('requestLeaveForm').reset();
    document.getElementById('requestLeaveError').classList.add('hidden');
    document.getElementById('requestLeaveForm').classList.remove('hidden');
    document.getElementById('requestLeaveConfirmation').classList.add('hidden');
    document.getElementById('requestLeaveModal').classList.remove('hidden');
}
function closeRequestLeaveModal() {
    document.getElementById('requestLeaveModal').classList.add('hidden');
    document.getElementById('requestLeaveForm').classList.remove('hidden');
    document.getElementById('requestLeaveConfirmation').classList.add('hidden');
}

document.getElementById('requestLeaveForm')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const errorBox = document.getElementById('requestLeaveError');
    const btn = document.getElementById('requestLeaveSubmitBtn');
    const reason = document.getElementById('leave_reason').value.trim();

    if (reason.length < 10) {
        errorBox.textContent = 'Reason must be at least 10 characters.';
        errorBox.classList.remove('hidden');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Submitting…';

    try {
        const res = await fetch('{{ route('leave-requests.store') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
            body: JSON.stringify({
                leave_type: document.getElementById('leave_type').value,
                start_date: document.getElementById('leave_start_date').value,
                end_date: document.getElementById('leave_end_date').value,
                reason: reason,
            }),
        });
        const data = await res.json();

        if (!res.ok) {
            errorBox.textContent = data.message ?? 'Something went wrong.';
            errorBox.classList.remove('hidden');
            return;
        }

        // Swap the form for an explicit confirmation panel instead of a
        // toast — a toast disappears in a few seconds and this message
        // (your appointments in this window will be auto-flagged) is
        // easy to miss that way, especially for a first-time user.
        document.getElementById('requestLeaveForm').classList.add('hidden');
        document.getElementById('requestLeaveConfirmation').classList.remove('hidden');
        document.getElementById('requestLeaveConfirmDates').textContent =
            document.getElementById('leave_start_date').value + ' to ' + document.getElementById('leave_end_date').value;

        document.getElementById('requestLeaveConfirmAffectedWrapper').classList.add('hidden');
        document.getElementById('requestLeaveConfirmAffectedNone').classList.add('hidden');
        document.getElementById('requestLeaveConfirmAffectedLoading').classList.remove('hidden');

        loadMyLeaveRequests();

        const leaveId = data.data?.id;
        if (leaveId) {
            try {
                const previewRes = await fetch(`/leave-requests/${leaveId}/affected-bookings`, { headers: { 'Accept': 'application/json' } });
                const bookings = previewRes.ok ? await previewRes.json() : [];
                document.getElementById('requestLeaveConfirmAffectedLoading').classList.add('hidden');

                if (bookings.length) {
                    document.getElementById('requestLeaveConfirmAffectedCount').textContent = bookings.length;
                    document.getElementById('requestLeaveConfirmAffectedList').innerHTML = bookings.map(b =>
                        `<div>• ${esc(b.customer_name)} — ${esc(b.appointment_date)} at ${esc(b.start_time_fmt)}</div>`
                    ).join('');
                    document.getElementById('requestLeaveConfirmAffectedWrapper').classList.remove('hidden');
                } else {
                    document.getElementById('requestLeaveConfirmAffectedNone').classList.remove('hidden');
                }
            } catch (err) {
                document.getElementById('requestLeaveConfirmAffectedLoading').classList.add('hidden');
            }
        } else {
            document.getElementById('requestLeaveConfirmAffectedLoading').classList.add('hidden');
        }

    } catch (err) {
        errorBox.textContent = 'Network error. Please try again.';
        errorBox.classList.remove('hidden');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Submit Request';
    }
});

// ── My leave requests (poll) ────────────────────────────────────────────
const leaveStatusClasses = {
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    approved: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    rejected: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
};

async function loadMyLeaveRequests() {
    try {
        const res = await fetch('{{ route('leave-requests.mine') }}', { headers: { 'Accept': 'application/json' } });
        if (!res.ok) return;
        const list = await res.json();
        const container = document.getElementById('myLeaveList');
        if (!list.length) {
            container.innerHTML = '<p class="px-6 py-8 text-sm text-center text-gray-400">You haven\'t requested any leave yet.</p>';
            return;
        }
        container.innerHTML = list.map(r => `
            <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">${esc(r.leave_type)} — ${esc(r.start_date)}${r.start_date !== r.end_date ? ' to ' + esc(r.end_date) : ''}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">${esc(r.reason)}</p>
                    ${r.status === 'rejected' && r.rejection_reason ? `<p class="mt-1 text-xs text-red-600 dark:text-red-400">Rejected: ${esc(r.rejection_reason)}</p>` : ''}
                </div>
                <span class="px-2.5 py-1 text-xs font-semibold rounded-full ${leaveStatusClasses[r.status] ?? ''}">${r.status.charAt(0).toUpperCase() + r.status.slice(1)}</span>
            </div>`).join('');
    } catch (err) { console.warn('My leave poll failed:', err); }
}

@if($canApproveLeave)
let currentLeaveReview = null;

async function loadPendingLeave() {
    try {
        const res = await fetch('{{ route('leave-requests.index') }}', { headers: { 'Accept': 'application/json' } });
        if (!res.ok) return;
        const list = await res.json();
        const section = document.getElementById('pendingLeaveSection');
        const container = document.getElementById('pendingLeaveList');
        const badge = document.getElementById('pendingLeaveBadge');

        if (!list.length) { section.classList.add('hidden'); return; }
        section.classList.remove('hidden');
        badge.textContent = list.length + ' Pending';

        container.innerHTML = list.map(r => `
            <div class="p-4 border rounded-2xl border-amber-200 bg-amber-50/60 dark:border-amber-800 dark:bg-amber-900/10">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="grid flex-1 grid-cols-1 gap-3 md:grid-cols-3">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Staff</p>
                            <p class="mt-1 font-medium text-gray-900 dark:text-white">${esc(r.user_name)} <span class="text-xs text-gray-400">(${esc(r.role)})</span></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Dates</p>
                            <p class="mt-1 font-medium text-gray-900 dark:text-white">${esc(r.start_date)} – ${esc(r.end_date)} (${r.days}d)</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Type</p>
                            <p class="mt-1 font-medium text-gray-900 dark:text-white">${esc(r.leave_type)}</p>
                        </div>
                    </div>
                    <button type="button" onclick='openLeaveReviewModal(${JSON.stringify(r)})'
                        class="flex-shrink-0 px-4 py-2 text-sm font-medium text-white rounded-xl bg-amber-600 hover:bg-amber-700">Review</button>
                </div>
            </div>`).join('');
    } catch (err) { console.warn('Pending leave poll failed:', err); }
}

async function openLeaveReviewModal(r) {
    currentLeaveReview = r;
    document.getElementById('leave_review_name').textContent = r.user_name + ' — ' + r.leave_type;
    document.getElementById('leave_review_dates').textContent = r.start_date + ' to ' + r.end_date + ' (' + r.days + ' day(s))';
    document.getElementById('leave_review_reason').textContent = '"' + r.reason + '"';
    document.getElementById('leaveReviewError').classList.add('hidden');
    document.getElementById('leaveReviewModal').classList.remove('hidden');

    document.getElementById('leave_affected_wrapper').classList.add('hidden');
    document.getElementById('leave_affected_none').classList.add('hidden');
    document.getElementById('leave_affected_loading').classList.remove('hidden');

    try {
        const res = await fetch(`/leave-requests/${r.id}/affected-bookings`, { headers: { 'Accept': 'application/json' } });
        const bookings = res.ok ? await res.json() : [];
        document.getElementById('leave_affected_loading').classList.add('hidden');

        if (bookings.length) {
            document.getElementById('leave_affected_count').textContent = bookings.length;
            document.getElementById('leave_affected_list').innerHTML = bookings.map(b => {
                const options = (b.available_therapists || []).map(t =>
                    `<option value="${t.id}" ${Number(b.recommended_id) === Number(t.id) ? 'selected' : ''}>${esc(t.name)}</option>`
                ).join('');
                const hasPicker = Array.isArray(b.available_therapists);
                return `
                <div class="p-2 text-xs bg-white border border-red-100 rounded-lg dark:bg-gray-800 dark:border-red-900/40">
                    <div class="font-medium text-gray-800 dark:text-gray-200">${esc(b.customer_name)} — ${esc(b.appointment_date)} at ${esc(b.start_time_fmt)}</div>
                    ${hasPicker ? `
                    <select data-affected-booking-id="${b.id}" class="w-full px-2 py-1 mt-1 text-xs border border-gray-300 rounded-lg dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">— Leave unassigned, resolve later —</option>
                        ${options}
                    </select>` : ''}
                </div>`;
            }).join('');
            document.getElementById('leave_affected_wrapper').classList.remove('hidden');
        } else {
            document.getElementById('leave_affected_none').classList.remove('hidden');
        }
    } catch (err) {
        document.getElementById('leave_affected_loading').classList.add('hidden');
        console.warn('Affected bookings fetch failed:', err);
    }
}
function closeLeaveReviewModal() { document.getElementById('leaveReviewModal').classList.add('hidden'); currentLeaveReview = null; }

async function approveLeave() {
    if (!currentLeaveReview) return;
    const errorBox = document.getElementById('leaveReviewError');
    const btn = document.getElementById('leaveApproveBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Approving…'; }

    const reassignments = Array.from(document.querySelectorAll('[data-affected-booking-id]'))
        .filter(sel => sel.value)
        .map(sel => ({ booking_id: Number(sel.dataset.affectedBookingId), new_therapist_id: Number(sel.value) }));

    try {
        const res = await fetch(`/leave-requests/${currentLeaveReview.id}/approve`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
            body: JSON.stringify({ reassignments }),
        });
        const data = await res.json();
        if (!res.ok) { errorBox.textContent = data.message ?? 'Something went wrong.'; errorBox.classList.remove('hidden'); return; }
        closeLeaveReviewModal();
        if (typeof showSpaToast === 'function') showSpaToast(data.message, 'success');
        loadPendingLeave();
    } catch (err) {
        errorBox.textContent = 'Network error.';
        errorBox.classList.remove('hidden');
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Approve'; }
    }
}

function openLeaveRejectReason() { document.getElementById('leave_reject_reason').value = ''; document.getElementById('leaveRejectReasonModal').classList.remove('hidden'); }
function closeLeaveRejectReason() { document.getElementById('leaveRejectReasonModal').classList.add('hidden'); }

async function confirmRejectLeave() {
    if (!currentLeaveReview) return;
    const reason = document.getElementById('leave_reject_reason').value.trim();
    if (reason.length < 5) { if (typeof showSpaToast === 'function') showSpaToast('Reason must be at least 5 characters.', 'error'); return; }

    try {
        const res = await fetch(`/leave-requests/${currentLeaveReview.id}/reject`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
            body: JSON.stringify({ rejection_reason: reason }),
        });
        const data = await res.json();
        if (!res.ok) { if (typeof showSpaToast === 'function') showSpaToast(data.message ?? 'Something went wrong.', 'error'); return; }
        closeLeaveRejectReason();
        closeLeaveReviewModal();
        if (typeof showSpaToast === 'function') showSpaToast(data.message, 'success');
        loadPendingLeave();
    } catch (err) { if (typeof showSpaToast === 'function') showSpaToast('Network error.', 'error'); }
}

loadPendingLeave();
setInterval(loadPendingLeave, 30000);
@endif

loadMyLeaveRequests();
setInterval(loadMyLeaveRequests, 30000);
</script>

@endsection
