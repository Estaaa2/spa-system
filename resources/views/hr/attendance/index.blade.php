@extends('layouts.app')
@section('content')
@php
    $user = auth()->user();

    $canViewAttendance = $user?->hasBranchPermission('view attendance') ?? false;
    $canEditAttendance = $user?->hasBranchPermission('edit attendance') ?? false;

    $rows = $staffList->map(function ($member, $index) {
        $existing = $member->attendance->first();
        $status = $existing?->status ?? 'present';

        return (object) [
            'index' => $index,
            'member' => $member,
            'existing' => $existing,
            'status' => $status,
        ];
    });

    $summary = [
        'total' => $rows->count(),
        'present' => $rows->where('status', 'present')->count(),
        'absent' => $rows->where('status', 'absent')->count(),
        'late' => $rows->where('status', 'late')->count(),
    ];

    $statusBadgeClasses = [
        'present' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        'absent'  => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        'late'    => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
    ];
@endphp

<div class="p-6 mx-auto space-y-6 max-w-7xl">

    <x-page-header title="Staff Attendance" subtitle="Mark and track daily staff attendance for the selected branch." />

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Staff Count</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-gray-900 dark:text-white">{{ $summary['total'] }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">For this branch</span>
            </div>
        </div>

        <div class="p-5 border shadow-sm bg-green-50 border-green-200 rounded-2xl dark:bg-green-900/10 dark:border-green-800">
            <p class="text-xs font-semibold tracking-wide uppercase text-green-700 dark:text-green-300">Present</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-green-900 dark:text-green-200">{{ $summary['present'] }}</h3>
                <span class="text-sm text-green-700 dark:text-green-300">Marked present</span>
            </div>
        </div>

        <div class="p-5 border shadow-sm bg-red-50 border-red-200 rounded-2xl dark:bg-red-900/10 dark:border-red-800">
            <p class="text-xs font-semibold tracking-wide uppercase text-red-700 dark:text-red-300">Absent</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-red-900 dark:text-red-200">{{ $summary['absent'] }}</h3>
                <span class="text-sm text-red-700 dark:text-red-300">Marked absent</span>
            </div>
        </div>

        <div class="p-5 border shadow-sm bg-yellow-50 border-yellow-200 rounded-2xl dark:bg-yellow-900/10 dark:border-yellow-800">
            <p class="text-xs font-semibold tracking-wide uppercase text-yellow-700 dark:text-yellow-300">Late</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-yellow-900 dark:text-yellow-200">{{ $summary['late'] }}</h3>
                <span class="text-sm text-yellow-700 dark:text-yellow-300">Marked late</span>
            </div>
        </div>
    </div>

    {{-- Date Picker --}}
    <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-wrap items-center justify-between gap-4">

            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-[#F6EFE6] dark:bg-gray-700">
                    <i class="fa-solid fa-calendar-days text-[#8B7355]"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-800 dark:text-white">Attendance Date</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Select a date to load or update attendance records</p>
                </div>
            </div>

            <form method="GET" action="{{ route('attendance.index') }}" class="flex items-center gap-3">
                <input type="date" name="date" value="{{ $date->toDateString() }}"
                    class="px-4 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"/>
                <button type="submit"
                    class="px-5 py-2 text-sm font-semibold text-white bg-[#8B7355] rounded-xl hover:bg-[#7A6348] transition shadow-sm">
                    <i class="mr-1.5 fa-solid fa-magnifying-glass"></i> Load
                </button>
            </form>

        </div>
    </div>

    {{-- Attendance Table / Form --}}
    @if($canEditAttendance)
    <form action="{{ route('attendance.store') }}" method="POST">
        @csrf
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">

        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">

            <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Attendance for <span class="text-[#8B7355]">{{ $date->format('l, F d, Y') }}</span>
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $staffList->count() }} active staff member(s)</p>
                </div>

                <div class="flex flex-wrap items-center gap-4 text-xs">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                        <span class="text-gray-500 dark:text-gray-400">Present</span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                        <span class="text-gray-500 dark:text-gray-400">Absent</span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-yellow-500"></span>
                        <span class="text-gray-500 dark:text-gray-400">Late</span>
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-500 uppercase">Staff Member</th>
                            <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-500 uppercase">Role</th>
                            <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-500 uppercase">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @forelse($rows as $row)
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900/40" id="row-{{ $row->index }}">
                            <td class="px-6 py-4">
                                <input type="hidden" name="attendance[{{ $row->index }}][staff_id]" value="{{ $row->member->id }}">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center flex-shrink-0 w-9 h-9 rounded-full bg-[#8B7355] text-white font-semibold text-sm">
                                        {{ strtoupper(substr($row->member->user->name ?? 'S', 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800 dark:text-white">
                                            {{ $row->member->user->name }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row->member->user->email }}</p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-[#F6EFE6] text-[#6F5430] dark:bg-gray-700 dark:text-gray-200">
                                    {{ ucfirst($row->member->user->getRoleNames()->first() ?? 'N/A') }}
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span id="dot-{{ $row->index }}"
                                        class="w-2.5 h-2.5 rounded-full flex-shrink-0
                                        {{ $row->status === 'present' ? 'bg-green-500' : ($row->status === 'absent' ? 'bg-red-500' : 'bg-yellow-500') }}">
                                    </span>

                                    <select name="attendance[{{ $row->index }}][status]"
                                        onchange="updateDot({{ $row->index }}, this.value)"
                                        class="w-32 px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        <option value="present" {{ $row->status === 'present' ? 'selected' : '' }}>Present</option>
                                        <option value="absent" {{ $row->status === 'absent' ? 'selected' : '' }}>Absent</option>
                                        <option value="late" {{ $row->status === 'late' ? 'selected' : '' }}>Late</option>
                                    </select>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <input type="text"
                                    name="attendance[{{ $row->index }}][remarks]"
                                    value="{{ $row->existing?->remarks }}"
                                    placeholder="Optional remarks..."
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"/>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-16 text-center">
                                <i class="block mb-3 text-4xl text-gray-200 fa-solid fa-users"></i>
                                <p class="text-sm text-gray-400">No active staff found for this branch.</p>
                                <p class="mt-1 text-xs text-gray-300">
                                    Add staff members in
                                    <a href="{{ route('staff.index') }}" class="text-[#8B7355] underline">Staff Management</a>.
                                </p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($staffList->isNotEmpty())
            <div class="flex flex-col gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 md:flex-row md:items-center md:justify-between dark:bg-gray-900/40 dark:border-gray-700">
                <div class="text-xs text-gray-400 dark:text-gray-500">
                    <i class="mr-1 fa-solid fa-circle-info text-[#8B7355]"></i>
                    Changes are saved per day. You can update attendance anytime.
                </div>
                <button type="submit"
                    class="inline-flex items-center justify-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-xl hover:opacity-90 transition shadow-sm hover:shadow-md active:translate-y-0.5">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Attendance — {{ $date->format('M d, Y') }}
                </button>
            </div>
            @endif
        </div>
    </form>

    @elseif($canViewAttendance)
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">

        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Attendance for <span class="text-[#8B7355]">{{ $date->format('l, F d, Y') }}</span>
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $staffList->count() }} active staff member(s)</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-500 uppercase">Staff Member</th>
                        <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-500 uppercase">Role</th>
                        <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-500 uppercase">Remarks</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse($rows as $row)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900/40">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center flex-shrink-0 w-9 h-9 rounded-full bg-[#8B7355] text-white font-semibold text-sm">
                                    {{ strtoupper(substr($row->member->user->name ?? 'S', 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800 dark:text-white">
                                        {{ $row->member->user->name }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row->member->user->email }}</p>
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-[#F6EFE6] text-[#6F5430] dark:bg-gray-700 dark:text-gray-200">
                                {{ ucfirst($row->member->user->getRoleNames()->first() ?? 'N/A') }}
                            </span>
                        </td>

                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $statusBadgeClasses[$row->status] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                                {{ ucfirst($row->status) }}
                            </span>
                        </td>

                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                            {{ $row->existing?->remarks ?: '—' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-16 text-center">
                            <i class="block mb-3 text-4xl text-gray-200 fa-solid fa-users"></i>
                            <p class="text-sm text-gray-400">No active staff found for this branch.</p>
                            <p class="mt-1 text-xs text-gray-300">
                                Add staff members in
                                <a href="{{ route('staff.index') }}" class="text-[#8B7355] underline">Staff Management</a>.
                            </p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>

<script>
function updateDot(index, status) {
    const dot = document.getElementById('dot-' + index);
    if (!dot) return;

    dot.className = 'w-2.5 h-2.5 rounded-full flex-shrink-0 ';
    if (status === 'present') dot.className += 'bg-green-500';
    else if (status === 'absent') dot.className += 'bg-red-500';
    else dot.className += 'bg-yellow-500';
}
</script>
@endsection