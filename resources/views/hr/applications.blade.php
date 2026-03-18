@extends('layouts.app')
@section('content')
<div class="p-6 mx-auto max-w-7xl">

    <x-page-header title="Applications" subtitle="List of all applicants from hiring."/>

    {{-- Filter Tabs --}}
    <div class="flex flex-wrap gap-2 mt-6">
        @foreach(['all' => 'All', 'pending' => 'Pending', 'interview' => 'Interview', 'approved' => 'Approved', 'hired' => 'Hired', 'rejected' => 'Rejected'] as $val => $label)
        <button onclick="filterStatus('{{ $val }}')" id="filter-{{ $val }}"
            class="px-3 py-1 text-xs font-semibold rounded-full border transition
            {{ $val === 'all' ? 'bg-[#8B7355] text-white border-[#8B7355]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#8B7355] hover:text-[#8B7355] dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600' }}">
            {{ $label }}
            @if($val !== 'all')
                <span class="ml-1">
                    ({{ $applicants->where('status', $val)->count() }})
                </span>
            @else
                <span class="ml-1">({{ $applicants->count() }})</span>
            @endif
        </button>
        @endforeach
    </div>

    {{-- Applicants Table --}}
    <div class="mt-4 overflow-hidden bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Applicant</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Position</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Contact</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Education</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Applied</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700" id="applicantsList">
                @forelse($applicants as $applicant)
                <tr class="transition applicant-row hover:bg-gray-50 dark:hover:bg-gray-900"
                    data-status="{{ $applicant->status }}">

                    {{-- Applicant --}}
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center flex-shrink-0 w-9 h-9 rounded-full bg-[#8B7355] text-white font-semibold text-sm">
                                {{ strtoupper(substr($applicant->full_name, 0, 1)) }}
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800 dark:text-white">
                                    {{ $applicant->full_name }}
                                </p>
                                <p class="text-xs text-gray-500">{{ $applicant->email }}</p>
                            </div>
                        </div>
                    </td>

                    {{-- Position --}}
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-[#F6EFE6] text-[#6F5430]">
                            {{ ucfirst($applicant->role ?? 'N/A') }}
                        </span>
                    </td>

                    {{-- Contact --}}
                    <td class="px-6 py-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $applicant->phone ?? 'N/A' }}</p>
                        @if($applicant->address)
                            <p class="text-xs text-gray-400 truncate max-w-[140px]">{{ $applicant->address }}</p>
                        @endif
                    </td>

                    {{-- Education --}}
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                        {{ $applicant->education ? ucwords(str_replace('_', ' ', $applicant->education)) : 'N/A' }}
                    </td>

                    {{-- Applied date --}}
                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                        {{ $applicant->created_at->format('M d, Y') }}
                    </td>

                    {{-- Status --}}
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full
                            {{ $applicant->status === 'pending'   ? 'bg-yellow-100 text-yellow-700'  :
                              ($applicant->status === 'interview' ? 'bg-blue-100 text-blue-700'      :
                              ($applicant->status === 'approved'  ? 'bg-green-100 text-green-700'    :
                              ($applicant->status === 'hired'     ? 'bg-teal-100 text-teal-700'      :
                               'bg-red-100 text-red-700'))) }}">
                            {{ ucfirst($applicant->status) }}
                        </span>
                    </td>

                    {{-- Action --}}
                    <td class="px-6 py-4">
                        @if($applicant->status === 'pending')
                            @can('manage applications')
                            <button onclick="openScheduleModal({{ $applicant->id }}, '{{ addslashes($applicant->full_name) }}')"
                                class="px-3 py-1.5 text-xs font-semibold text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348] transition">
                                <i class="mr-1 fa-solid fa-calendar-plus"></i> Schedule
                            </button>
                            @endcan
                        @elseif($applicant->status === 'interview')
                            <span class="text-xs font-medium text-blue-500">
                                <i class="mr-1 fa-solid fa-clock"></i> Interview set
                            </span>
                        @elseif($applicant->status === 'hired')
                            <span class="text-xs font-medium text-teal-600">
                                <i class="mr-1 fa-solid fa-check-circle"></i> Hired
                            </span>
                        @elseif($applicant->status === 'rejected')
                            <span class="text-xs text-red-400">
                                <i class="mr-1 fa-solid fa-times-circle"></i> Rejected
                            </span>
                        @else
                            <span class="text-xs text-gray-400">{{ ucfirst($applicant->status) }}</span>
                        @endif
                    </td>

                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-16 text-center">
                        <i class="block mb-3 text-4xl text-gray-200 fa-solid fa-users"></i>
                        <p class="text-sm text-gray-400">No applicants yet.</p>
                        <p class="mt-1 text-xs text-gray-300">
                            Go to
                            <a href="{{ route('hr.hiring') }}" class="text-[#8B7355] underline">Hiring</a>
                            to add applicants.
                        </p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

{{-- Schedule Interview Modal --}}
<div id="scheduleModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50">
    <div class="w-full max-w-md p-6 mx-auto mt-24 bg-white shadow-xl rounded-xl dark:bg-gray-800">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Schedule Interview</h2>
            <button onclick="closeScheduleModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <p id="scheduleApplicantName" class="mb-4 text-sm font-medium text-[#8B7355]"></p>
        <form id="scheduleForm" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block mb-1 text-xs font-semibold text-gray-600">Interview Date *</label>
                <input type="date" name="interview_date" required
                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:outline-none"/>
            </div>
            <div>
                <label class="block mb-1 text-xs font-semibold text-gray-600">Interview Time *</label>
                <input type="time" name="interview_time" required
                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:outline-none"/>
            </div>
            <div>
                <label class="block mb-1 text-xs font-semibold text-gray-600">Remarks</label>
                <textarea name="remarks" rows="2" placeholder="Optional notes..."
                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:outline-none"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeScheduleModal()"
                    class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
                <button type="submit"
                    class="px-4 py-2 text-sm font-semibold text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348]">
                    <i class="mr-1 fa-solid fa-calendar-check"></i> Schedule
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const baseScheduleUrl = @json(url('/hr/applications'));

function openScheduleModal(applicantId, name) {
    document.getElementById('scheduleApplicantName').textContent = 'Applicant: ' + name;
    document.getElementById('scheduleForm').action = `${baseScheduleUrl}/${applicantId}/schedule-interview`;
    document.getElementById('scheduleModal').classList.remove('hidden');
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').classList.add('hidden');
}

function filterStatus(status) {
    document.querySelectorAll('.applicant-row').forEach(row => {
        row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
    });

    document.querySelectorAll('[id^="filter-"]').forEach(btn => {
        btn.classList.remove('bg-[#8B7355]', 'text-white', 'border-[#8B7355]');
        btn.classList.add('bg-white', 'text-gray-600', 'border-gray-200');
    });

    const active = document.getElementById('filter-' + status);
    if (active) {
        active.classList.add('bg-[#8B7355]', 'text-white', 'border-[#8B7355]');
        active.classList.remove('bg-white', 'text-gray-600', 'border-gray-200');
    }
}
</script>
@endsection
