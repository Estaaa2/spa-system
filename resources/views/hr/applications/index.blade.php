@extends('layouts.app')

@section('title', 'Applications')
@section('content')
@php
    $user = auth()->user();

    $canViewApplications = $user?->hasBranchPermission('view applications') ?? false;
    $canEditApplications = $user?->hasBranchPermission('edit applications') ?? false;
    $canDeleteApplications = $user?->hasBranchPermission('delete applications') ?? false;

    $canScheduleInterview = $canEditApplications;

    $formatLabel = function ($value) {
        return filled($value) ? ucwords(str_replace('_', ' ', $value)) : 'N/A';
    };
@endphp

<div class="p-6 mx-auto max-w-7xl">

        <x-page-header title="Applications" subtitle="List of all applicants from hiring."/>

    {{-- One white card holds the header row, filter tabs, and the table --}}
    <div class="mt-6 overflow-hidden bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">

        {{-- Section header + Filter Tabs — same row alignment as the branch-card filters --}}
        <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-5 border-b border-gray-100 dark:border-gray-700">
            <h2 class="flex items-center gap-2 text-lg font-semibold text-gray-800 dark:text-white">
                Applicant List
                <span class="px-2 py-0.5 text-xs font-semibold text-gray-500 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-300">
                    {{ $applicants->count() }}
                </span>
            </h2>

            <div class="flex flex-wrap gap-2">
                @foreach (['all' => 'All', 'pending' => 'Pending', 'interview' => 'Interview', 'approved' => 'Approved', 'hired' => 'Hired', 'rejected' => 'Rejected'] as $val => $label)
                    <button onclick="filterStatus('{{ $val }}')" id="filter-{{ $val }}"
                        class="px-3 py-1 text-xs font-semibold rounded-full border transition
                {{ $val === 'all' ? 'bg-[#8B7355] text-white border-[#8B7355]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#8B7355] hover:text-[#8B7355] dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Applicants Table --}}
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Applicant</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Applied Position</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Contact</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Education</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Applied</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700" id="applicantsList">
                @forelse($applicants as $applicant)
                @php
                    $applicantData = [
                        'id' => $applicant->id,
                        'full_name' => $applicant->full_name,
                        'email' => $applicant->email,
                        'phone' => $applicant->phone,
                        'gender' => $applicant->gender,
                        'date_of_birth' => optional($applicant->date_of_birth)->format('M d, Y'),
                        'civil_status' => $applicant->civil_status,
                        'address' => $applicant->address,
                        'position_applied' => $applicant->position_applied,
                        'availability' => $applicant->availability,
                        'source' => $applicant->source,
                        'education' => $applicant->education,
                        'skills' => $applicant->skills,
                        'work_experience' => $applicant->work_experience,
                        'emergency_contact_name' => $applicant->emergency_contact_name,
                        'emergency_contact_relation' => $applicant->emergency_contact_relation,
                        'emergency_contact_phone' => $applicant->emergency_contact_phone,
                        'expected_start_date' => optional($applicant->expected_start_date)->format('M d, Y'),
                        'notes' => $applicant->notes,
                        'status' => $applicant->status,
                        'applied_on' => $applicant->created_at->format('M d, Y'),
                        'interview_date' => $applicant->interview ? optional($applicant->interview->interview_date)->format('M d, Y') : null,
                        'interview_time' => $applicant->interview->interview_time ?? null,
                        'interview_status' => $applicant->interview->status ?? null,
                    ];
                @endphp
                <tr class="transition cursor-pointer applicant-row hover:bg-gray-50 dark:hover:bg-gray-900"
                    data-status="{{ $applicant->status }}"
                    onclick="openApplicantDetailsModal({{ $applicant->id }})"
                    data-applicant='@json($applicantData)'>

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

                    {{-- Applied Position --}}
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-[#F6EFE6] text-[#6F5430]">
                            {{ $formatLabel($applicant->position_applied ?? $applicant->role) }}
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
                        {{ $formatLabel($applicant->education) }}
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
                            @if($canScheduleInterview)
                                <button onclick="event.stopPropagation(); openScheduleModal({{ $applicant->id }}, '{{ addslashes($applicant->full_name) }}')"
                                    class="px-3 py-1.5 text-xs font-semibold text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348] transition">
                                    <i class="mr-1 fa-solid fa-calendar-plus"></i> Schedule
                                </button>
                            @else
                                <span class="text-xs text-gray-400">
                                    {{ ucfirst($applicant->status) }}
                                </span>
                            @endif
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
                            <a href="{{ route('hiring.index') }}" class="text-[#8B7355] underline">Hiring</a>
                            to add applicants.
                        </p>
                    </td>
                </tr>
                @endforelse
            </tbody>
                </table>
    </div>
    </div>

    {{-- Applicant Details Modal --}}
    <div id="applicantDetailsModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50">
        <div class="w-full max-w-2xl p-6 mx-auto mt-16 overflow-y-auto bg-white shadow-xl rounded-xl dark:bg-gray-800 max-h-[85vh]">
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <div id="detailAvatar" class="flex items-center justify-center flex-shrink-0 w-12 h-12 text-lg font-semibold text-white rounded-full bg-[#8B7355]"></div>
                    <div>
                        <h2 id="detailName" class="text-lg font-semibold text-gray-800 dark:text-white"></h2>
                        <p id="detailEmail" class="text-xs text-gray-500"></p>
                    </div>
                </div>
                <button onclick="closeApplicantDetailsModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="text-xl fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="space-y-5 text-sm">
                <div>
                    <h3 class="flex items-center gap-2 mb-2 text-xs font-bold tracking-widest text-[#8B7355] uppercase">
                        <i class="fa-solid fa-user"></i> Personal Information
                    </h3>
                    <div class="grid grid-cols-2 gap-3 text-gray-700 dark:text-gray-300">
                        <div><span class="text-xs text-gray-400">Phone</span><p id="detailPhone"></p></div>
                        <div><span class="text-xs text-gray-400">Gender</span><p id="detailGender"></p></div>
                        <div><span class="text-xs text-gray-400">Date of Birth</span><p id="detailDob"></p></div>
                        <div><span class="text-xs text-gray-400">Civil Status</span><p id="detailCivilStatus"></p></div>
                        <div class="col-span-2"><span class="text-xs text-gray-400">Address</span><p id="detailAddress"></p></div>
                    </div>
                </div>

                <hr class="border-gray-100 dark:border-gray-700">

                <div>
                    <h3 class="flex items-center gap-2 mb-2 text-xs font-bold tracking-widest text-[#8B7355] uppercase">
                        <i class="fa-solid fa-briefcase"></i> Position Details
                    </h3>
                    <div class="grid grid-cols-2 gap-3 text-gray-700 dark:text-gray-300">
                        <div><span class="text-xs text-gray-400">Applying For</span><p id="detailPosition"></p></div>
                        <div><span class="text-xs text-gray-400">Availability</span><p id="detailAvailability"></p></div>
                        <div><span class="text-xs text-gray-400">Source</span><p id="detailSource"></p></div>
                        <div><span class="text-xs text-gray-400">Education</span><p id="detailEducation"></p></div>
                        <div><span class="text-xs text-gray-400">Expected Start</span><p id="detailStartDate"></p></div>
                        <div><span class="text-xs text-gray-400">Applied On</span><p id="detailAppliedOn"></p></div>
                        <div class="col-span-2"><span class="text-xs text-gray-400">Skills / Certifications</span><p id="detailSkills"></p></div>
                        <div class="col-span-2"><span class="text-xs text-gray-400">Work Experience</span><p id="detailWorkExperience" class="whitespace-pre-line"></p></div>
                    </div>
                </div>

                <div id="detailInterviewWrapper" class="hidden">
                    <hr class="mb-4 border-gray-100 dark:border-gray-700">
                    <h3 class="flex items-center gap-2 mb-2 text-xs font-bold tracking-widest text-[#8B7355] uppercase">
                        <i class="fa-solid fa-calendar-check"></i> Interview
                    </h3>
                    <div class="grid grid-cols-3 gap-3 text-gray-700 dark:text-gray-300">
                        <div><span class="text-xs text-gray-400">Date</span><p id="detailInterviewDate"></p></div>
                        <div><span class="text-xs text-gray-400">Time</span><p id="detailInterviewTime"></p></div>
                        <div><span class="text-xs text-gray-400">Status</span><p id="detailInterviewStatus" class="font-semibold"></p></div>
                    </div>
                    <a href="{{ route('interviews.index') }}" class="inline-block mt-2 text-xs text-[#8B7355] underline">
                        View in Interviews →
                    </a>
                </div>

                <hr class="border-gray-100 dark:border-gray-700">

                <div>
                    <h3 class="flex items-center gap-2 mb-2 text-xs font-bold tracking-widest text-[#8B7355] uppercase">
                        <i class="fa-solid fa-phone-volume"></i> Emergency Contact
                    </h3>
                    <div class="grid grid-cols-3 gap-3 text-gray-700 dark:text-gray-300">
                        <div><span class="text-xs text-gray-400">Name</span><p id="detailEmergencyName"></p></div>
                        <div><span class="text-xs text-gray-400">Relationship</span><p id="detailEmergencyRelation"></p></div>
                        <div><span class="text-xs text-gray-400">Number</span><p id="detailEmergencyPhone"></p></div>
                    </div>
                </div>

                <div id="detailNotesWrapper" class="hidden">
                    <hr class="mb-4 border-gray-100 dark:border-gray-700">
                    <h3 class="flex items-center gap-2 mb-2 text-xs font-bold tracking-widest text-[#8B7355] uppercase">
                        <i class="fa-solid fa-note-sticky"></i> Notes / Remarks
                    </h3>
                    <p id="detailNotes" class="text-gray-700 whitespace-pre-line dark:text-gray-300"></p>
                </div>
            </div>

            <div class="flex justify-end pt-4 mt-4 border-t border-gray-100 dark:border-gray-700">
                <button onclick="closeApplicantDetailsModal()"
                    class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                    Close
                </button>
            </div>
        </div>
    </div>

</div>

{{-- Schedule Interview Modal --}}
@if($canScheduleInterview)
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
@endif

<script>
@if($canScheduleInterview)
const baseScheduleUrl = @json(url('/applications'));

function openScheduleModal(applicantId, name) {
    document.getElementById('scheduleApplicantName').textContent = 'Applicant: ' + name;
    document.getElementById('scheduleForm').action = `${baseScheduleUrl}/${applicantId}/schedule-interview`;
    document.getElementById('scheduleModal').classList.remove('hidden');
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').classList.add('hidden');
}

function openApplicantDetailsModal(applicantId) {
    const row = document.querySelector(`.applicant-row[onclick*="${applicantId}"]`);
    if (!row) return;

    const data = JSON.parse(row.getAttribute('data-applicant'));
    const fmt  = (val) => val && val !== 'null' ? val.replace ? val.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : val : 'N/A';

    document.getElementById('detailAvatar').textContent    = data.full_name.charAt(0).toUpperCase();
    document.getElementById('detailName').textContent      = data.full_name;
    document.getElementById('detailEmail').textContent      = data.email;
    document.getElementById('detailPhone').textContent      = data.phone || 'N/A';
    document.getElementById('detailGender').textContent     = fmt(data.gender);
    document.getElementById('detailDob').textContent        = data.date_of_birth || 'N/A';
    document.getElementById('detailCivilStatus').textContent = fmt(data.civil_status);
    document.getElementById('detailAddress').textContent    = data.address || 'N/A';
    document.getElementById('detailPosition').textContent   = fmt(data.position_applied);
    document.getElementById('detailAvailability').textContent = fmt(data.availability);
    document.getElementById('detailSource').textContent     = fmt(data.source);
    document.getElementById('detailEducation').textContent  = fmt(data.education);
    document.getElementById('detailStartDate').textContent  = data.expected_start_date || 'N/A';
    document.getElementById('detailAppliedOn').textContent  = data.applied_on;
    document.getElementById('detailSkills').textContent     = data.skills || 'N/A';
    document.getElementById('detailWorkExperience').textContent = data.work_experience || 'N/A';
    document.getElementById('detailEmergencyName').textContent     = data.emergency_contact_name || 'N/A';
    document.getElementById('detailEmergencyRelation').textContent = data.emergency_contact_relation || 'N/A';
    document.getElementById('detailEmergencyPhone').textContent    = data.emergency_contact_phone || 'N/A';

    const notesWrapper = document.getElementById('detailNotesWrapper');
    if (data.notes) {
        notesWrapper.classList.remove('hidden');
        document.getElementById('detailNotes').textContent = data.notes;
    } else {
        notesWrapper.classList.add('hidden');
    }

    const interviewWrapper = document.getElementById('detailInterviewWrapper');
    if (data.interview_date) {
        interviewWrapper.classList.remove('hidden');
        document.getElementById('detailInterviewDate').textContent   = data.interview_date;
        document.getElementById('detailInterviewTime').textContent   = data.interview_time || 'N/A';
        document.getElementById('detailInterviewStatus').textContent = data.interview_status
            ? data.interview_status.charAt(0).toUpperCase() + data.interview_status.slice(1)
            : 'N/A';
    } else {
        interviewWrapper.classList.add('hidden');
    }

    document.getElementById('applicantDetailsModal').classList.remove('hidden');
}

function closeApplicantDetailsModal() {
    document.getElementById('applicantDetailsModal').classList.add('hidden');
}

@endif

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
