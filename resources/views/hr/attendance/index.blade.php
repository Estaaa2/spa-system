@extends('layouts.app')
@section('content')
<div class="p-6 mx-auto max-w-7xl">

    <x-page-header title="Staff Attendance" subtitle="Mark and track daily staff attendance."/>

    {{-- Date Picker Card --}}
    <div class="p-5 mt-6 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-wrap items-center justify-between gap-4">

            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-[#F6EFE6]">
                    <i class="fa-solid fa-calendar-days text-[#8B7355]"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-800 dark:text-white">Attendance Date</p>
                    <p class="text-xs text-gray-500">Select a date to load or update attendance</p>
                </div>
            </div>

            <form method="GET" action="{{ route('hr.attendance') }}" class="flex items-center gap-3">
                <input type="date" name="date" value="{{ $date->toDateString() }}"
                    class="px-4 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"/>
                <button type="submit"
                    class="px-5 py-2 text-sm font-semibold text-white bg-[#8B7355] rounded-xl hover:bg-[#7A6348] transition shadow-sm">
                    <i class="mr-1.5 fa-solid fa-magnifying-glass"></i> Load
                </button>
            </form>

        </div>
    </div>

    {{-- Attendance Form --}}
    @can('manage attendance')
    <form action="{{ route('hr.attendance.store') }}" method="POST" class="mt-4">
        @csrf
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">

        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">

            {{-- Table Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <div>
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white">
                        Attendance for
                        <span class="text-[#8B7355]">{{ $date->format('l, F d Y') }}</span>
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $staffList->count() }} active staff member(s)</p>
                </div>

                {{-- Legend --}}
                <div class="items-center hidden gap-4 text-xs md:flex">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                        <span class="text-gray-500">Present</span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                        <span class="text-gray-500">Absent</span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-yellow-500"></span>
                        <span class="text-gray-500">Late</span>
                    </span>
                </div>
            </div>

            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-500 uppercase">
                            Staff Member
                        </th>
                        <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-500 uppercase">
                            Role
                        </th>
                        <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-500 uppercase">
                            Status
                        </th>
                        <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-500 uppercase">
                            Remarks
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse($staffList as $index => $member)
                    @php $existing = $member->attendance->first(); @endphp
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900"
                        id="row-{{ $index }}">

                        {{-- Staff --}}
                        <td class="px-6 py-4">
                            <input type="hidden" name="attendance[{{ $index }}][staff_id]" value="{{ $member->id }}">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center flex-shrink-0 w-9 h-9 rounded-full bg-[#8B7355] text-white font-semibold text-sm">
                                    {{ strtoupper(substr($member->user->name ?? 'S', 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800 dark:text-white">
                                        {{ $member->user->name }}
                                    </p>
                                    <p class="text-xs text-gray-500">{{ $member->user->email }}</p>
                                </div>
                            </div>
                        </td>

                        {{-- Role --}}
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-[#F6EFE6] text-[#6F5430]">
                                {{ ucfirst($member->user->getRoleNames()->first() ?? 'N/A') }}
                            </span>
                        </td>

                        {{-- Status --}}
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                {{-- Visual status dot --}}
                                <span id="dot-{{ $index }}" class="w-2.5 h-2.5 rounded-full flex-shrink-0
                                    {{ ($existing?->status ?? 'present') === 'present' ? 'bg-green-500' :
                                    (($existing?->status) === 'absent' ? 'bg-red-500' : 'bg-yellow-500') }}">
                                </span>
                                <select name="attendance[{{ $index }}][status]"
                                    onchange="updateDot({{ $index }}, this.value)"
                                    class="w-32 px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="present" {{ ($existing?->status ?? 'present') === 'present' ? 'selected' : '' }}>Present</option>
                                    <option value="absent"  {{ ($existing?->status) === 'absent'  ? 'selected' : '' }}>Absent</option>
                                    <option value="late"    {{ ($existing?->status) === 'late'    ? 'selected' : '' }}>Late</option>
                                </select>
                            </div>
                        </td>

                        {{-- Remarks --}}
                        <td class="px-6 py-4">
                            <input type="text"
                                name="attendance[{{ $index }}][remarks]"
                                value="{{ $existing?->remarks }}"
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

            {{-- Footer with Save button inside the container --}}
            @if($staffList->isNotEmpty())
            <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50 dark:bg-gray-900 dark:border-gray-700">
                <div class="text-xs text-gray-400">
                    <i class="mr-1 fa-solid fa-circle-info text-[#8B7355]"></i>
                    Changes are saved per day. You can update attendance anytime.
                </div>
                <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-xl hover:opacity-90 transition shadow-sm hover:shadow-md active:translate-y-0.5">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Attendance — {{ $date->format('M d, Y') }}
                </button>
            </div>
            @endif

        </div>
    </form>
    @endcan

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
