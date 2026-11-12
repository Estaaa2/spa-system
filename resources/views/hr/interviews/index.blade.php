@extends('layouts.app')

@section('title', 'Interviews')
@section('content')
@php
    $user = auth()->user();

    $canViewInterviews   = $user?->hasBranchPermission('view interviews') ?? false;
    $canCreateInterviews = $user?->hasBranchPermission('create interviews') ?? false;
    $canEditInterviews   = $user?->hasBranchPermission('edit interviews') ?? false;
    $canDeleteInterviews = $user?->hasBranchPermission('delete interviews') ?? false;

    $canCreateStaff = $user?->hasBranchPermission('create staff') ?? false;

    $canReviewInterview = $canEditInterviews;
    $canCreateStaffFromInterview = $canEditInterviews && $canCreateStaff;

    $statusClasses = [
        'pending'  => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
        'approved' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    ];

    $positionLabel = function ($interview) {
        $applicant = $interview->applicant;

        if (!$applicant) {
            return 'N/A';
        }

        if (!empty($applicant->position_applied)) {
            return ucwords(str_replace('_', ' ', $applicant->position_applied));
        }

        if (!empty($applicant->role)) {
            return ucwords(str_replace('_', ' ', $applicant->role));
        }

        if (!empty($applicant->jobPosting?->title)) {
            return $applicant->jobPosting->title;
        }

        return 'N/A';
    };

    $summary = $summary ?? [
        'total'    => $interviews->count(),
        'pending'  => $interviews->where('status', 'pending')->count(),
        'approved' => $interviews->where('status', 'approved')->count(),
        'rejected' => $interviews->where('status', 'rejected')->count(),
    ];
@endphp

<div class="p-6 mx-auto space-y-6 max-w-7xl">
    <x-page-header
        title="Interviews"
        subtitle="Review scheduled interviews, record outcomes, and create staff accounts for approved applicants."
    />

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Total Interviews</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-gray-900 dark:text-white">{{ $summary['total'] }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">All records</span>
            </div>
        </div>

        <div class="p-5 border shadow-sm bg-amber-50 border-amber-200 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800">
            <p class="text-xs font-semibold tracking-wide uppercase text-amber-700 dark:text-amber-300">Pending Review</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-amber-900 dark:text-amber-200">{{ $summary['pending'] }}</h3>
                <span class="text-sm text-amber-700 dark:text-amber-300">Awaiting decision</span>
            </div>
        </div>

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Approved</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-gray-900 dark:text-white">{{ $summary['approved'] }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Passed interview</span>
            </div>
        </div>

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Rejected</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-gray-900 dark:text-white">{{ $summary['rejected'] }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Not selected</span>
            </div>
        </div>
    </div>

        {{-- Interviews Table --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-col gap-4 px-6 py-4 border-b border-gray-200 lg:flex-row lg:items-start lg:justify-between dark:border-gray-700">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Interview Records</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Review applicant interviews, approve or reject them, and create staff accounts for approved candidates.
                </p>
            </div>

            {{-- Filters (table filters workstream; same bar as Pay Runs) --}}
            <x-table-filters :action="route('interviews.index')" :shown="$interviews->count()" :total="$summary['total']"
                             :active="$activeFilters ?? 0" noun="interview"
                             :search="$filters['q'] ?? ''" search-placeholder="Applicant name or email">
                <x-table-filters.select name="status" label="Status" all="All statuses"
                    :options="['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']" :value="$filters['status'] ?? null" width="sm:w-36" />
                <x-table-filters.select name="posting" label="Job opening" all="All openings"
                    :options="['none' => 'No posting'] + ($postingOptions ?? collect())->all()" :value="$filters['posting'] ?? null" width="sm:w-48" />
            </x-table-filters>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Applicant</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Applied Position</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Schedule</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700" id="interviewsList">
                    @forelse($interviews as $interview)
                        @php
                            $dateLabel = $interview->interview_date
                                ? \Carbon\Carbon::parse($interview->interview_date)->format('M d, Y')
                                : 'N/A';

                            $timeLabel = $interview->interview_time
                                ? \Carbon\Carbon::parse($interview->interview_time)->format('h:i A')
                                : 'N/A';
                        @endphp

                        <tr class="transition interview-row hover:bg-gray-50 dark:hover:bg-gray-900/40"
                            data-status="{{ $interview->status }}">
                            <td class="px-6 py-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-800 dark:text-white">
                                        {{ $interview->applicant->full_name ?? 'Unknown Applicant' }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ $interview->applicant->email ?? 'No email' }}
                                    </p>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-[#F6EFE6] text-[#6F5430]">
                                    {{ $positionLabel($interview) }}
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                <p class="text-sm text-gray-800 dark:text-white">{{ $dateLabel }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $timeLabel }}</p>
                            </td>

                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $statusClasses[$interview->status] ?? '' }}">
                                    {{ ucfirst($interview->status) }}
                                </span>

                                @if($interview->status === 'rejected' && filled($interview->rejection_reason))
                                    <p class="max-w-xs mt-2 text-xs leading-5 text-gray-500 dark:text-gray-400">
                                        {{ $interview->rejection_reason }}
                                    </p>
                                @endif
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($interview->status === 'pending')
                                    @if($canReviewInterview)
                                        <div class="flex flex-wrap gap-2">
                                            <form action="{{ route('interviews.approve', $interview) }}" method="POST">
                                                @csrf

                                                <button
                                                    type="submit"
                                                    class="px-3 py-1.5 text-xs font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 transition">
                                                    Approve
                                                </button>
                                            </form>

                                            <button
                                                type="button"
                                                data-reject-url="{{ route('interviews.reject', $interview) }}"
                                                data-applicant-name="{{ $interview->applicant?->full_name }}"
                                                onclick="openInterviewRejectModal(this)"
                                                class="px-3 py-1.5 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 transition">
                                                Reject
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">
                                            Pending review
                                        </span>
                                    @endif

                                @elseif($interview->status === 'approved' && !$interview->staff_account_created)
                                    @if($canCreateStaffFromInterview)
                                        @if(in_array(mb_strtolower((string) $interview->applicant?->email), $existingStaffEmails ?? [], true))
                                            {{-- Account was already added on the Staff page: link it. --}}
                                            <form action="{{ route('interviews.create-staff', $interview) }}" method="POST">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    title="A staff account with this email already exists. Link it to this interview."
                                                    class="px-3 py-1.5 text-xs font-semibold text-[#8B7355] bg-white border border-[#8B7355] rounded-lg hover:bg-[#F6EFE6] transition whitespace-nowrap">
                                                    <i class="mr-1 fa-solid fa-link" aria-hidden="true"></i>
                                                    Link Existing Account
                                                </button>
                                            </form>
                                        @else
                                            <button
                                                type="button"
                                                data-create-url="{{ route('interviews.create-staff', $interview) }}"
                                                data-applicant-name="{{ $interview->applicant?->full_name }}"
                                                data-applicant-email="{{ $interview->applicant?->email }}"
                                                data-applicant-position="{{ $positionLabel($interview) }}"
                                                onclick="openCreateStaffModal(this)"
                                                class="px-3 py-1.5 text-xs font-semibold text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348] transition whitespace-nowrap">
                                                <i class="mr-1 fa-solid fa-user-plus" aria-hidden="true"></i>
                                                Create Account
                                            </button>
                                        @endif
                                    @else
                                        <span class="text-xs text-gray-400">
                                            No staff access
                                        </span>
                                    @endif

                                @elseif($interview->staff_account_created)
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
                                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                                        Account Created
                                    </span>

                                @elseif($interview->status === 'rejected')
                                    <span class="text-xs text-gray-400">
                                        —
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-sm text-center text-gray-400 dark:text-gray-500">
                                {{ ($activeFilters ?? 0) > 0 ? 'No interviews match these filters.' : 'No interviews scheduled.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($canReviewInterview)
<div id="rejectInterviewModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">

            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Reject Applicant
                    </h2>

                    <p id="rejectApplicantName" class="mt-1 text-sm text-gray-500 dark:text-gray-400"></p>
                </div>

                <button
                    type="button"
                    onclick="closeInterviewRejectModal()"
                    class="inline-flex items-center justify-center text-gray-500 transition w-11 h-11 rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                    aria-label="Close reject applicant modal"
                >
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <form
                id="rejectInterviewForm"
                method="POST"
                class="p-6 space-y-4"
            >
                @csrf

                <div class="p-4 border border-red-200 bg-red-50 rounded-xl dark:border-red-900 dark:bg-red-900/10">
                    <div class="flex gap-3">
                        <i class="mt-0.5 text-red-600 fa-solid fa-circle-exclamation" aria-hidden="true"></i>

                        <p class="text-sm text-red-700 dark:text-red-300">
                            This will mark the applicant as rejected and email them that they were not selected.
                        </p>
                    </div>
                </div>

                <div>
                    <label
                        for="rejection_reason"
                        class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300"
                    >
                        Internal Reason
                        <x-required-mark />
                    </label>
                    <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">For HR records only. This is not sent to the applicant.</p>

                    <textarea
                        id="rejection_reason"
                        name="rejection_reason"
                        rows="4"
                        maxlength="2000"
                        required
                        placeholder="Example: Does not meet the required experience for the position."
                        class="w-full px-3 py-2 text-sm text-gray-900 bg-white border border-gray-300 rounded-xl
                               focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none
                               dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    >{{ old('rejection_reason') }}</textarea>

                    @error('rejection_reason', 'rejectInterview')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="applicant_message"
                        class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300"
                    >
                        Message to Applicant
                        <span class="font-normal text-gray-400">(optional)</span>
                    </label>
                    <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">The applicant always gets a polite "not selected" email. Anything you write here is added to it.</p>

                    <textarea
                        id="applicant_message"
                        name="applicant_message"
                        rows="3"
                        maxlength="2000"
                        placeholder="Example: We encourage you to apply again once you have more hands-on massage experience."
                        class="w-full px-3 py-2 text-sm text-gray-900 bg-white border border-gray-300 rounded-xl
                               focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none
                               dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    >{{ old('applicant_message') }}</textarea>

                    @error('applicant_message', 'rejectInterview')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button
                        type="button"
                        onclick="closeInterviewRejectModal()"
                        class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 text-sm font-semibold text-white bg-red-700 rounded-xl hover:bg-red-800"
                    >
                        <i class="mr-2 fa-solid fa-ban" aria-hidden="true"></i>
                        Reject Applicant
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>
@endif

{{-- Create Staff Account Modal --}}
@if($canCreateStaffFromInterview)
<div id="createStaffModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center sm:p-6">
        <div class="w-full max-w-md p-6 bg-white border border-gray-200 shadow-xl rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Create Staff Account</h2>
                <button type="button" onclick="closeCreateStaffModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="createStaffForm" method="POST" class="space-y-4">
                @csrf

                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Applied as <span id="csFullName" class="font-semibold text-gray-700 dark:text-gray-200"></span>.
                    Confirm the legal name split below; it is used for identity verification and payroll.
                </p>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label for="csFirstName" class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">First Name <x-required-mark /></label>
                        <input type="text" name="first_name" id="csFirstName" required maxlength="255" value="{{ old('first_name') }}"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"/>
                    </div>
                    <div>
                        <label for="csLastName" class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">Last Name <x-required-mark /></label>
                        <input type="text" name="last_name" id="csLastName" required maxlength="255" value="{{ old('last_name') }}"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"/>
                    </div>
                </div>

                <div>
                    <label for="csMiddleName" class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">Middle Name</label>
                    <input type="text" name="middle_name" id="csMiddleName" maxlength="255" value="{{ old('middle_name') }}"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"/>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <p class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">Email</p>
                        <p id="csEmail" class="px-3 py-2 text-sm text-gray-700 break-all bg-gray-50 border border-gray-200 rounded-xl dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200"></p>
                    </div>
                    <div>
                        <p class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">Role</p>
                        <p id="csRole" class="px-3 py-2 text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-xl dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200"></p>
                    </div>
                </div>

                @foreach(['first_name', 'middle_name', 'last_name'] as $field)
                    @error($field, 'createStaff')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                @endforeach

                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeCreateStaffModal()"
                        class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 text-sm font-semibold text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348]">
                        Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<script>
@if($canReviewInterview)

function openInterviewRejectModal(button) {
    const modal = document.getElementById('rejectInterviewModal');
    const form = document.getElementById('rejectInterviewForm');
    const applicantName = document.getElementById('rejectApplicantName');

    if (!modal || !form || !button) {
        return;
    }

    const actionUrl = button.dataset.rejectUrl;
    const name = button.dataset.applicantName;

    if (!actionUrl) {
        return;
    }

    form.action = actionUrl;

    if (applicantName) {
        applicantName.textContent = 'Applicant: ' + name;
    }

    modal.classList.remove('hidden');
}

function closeInterviewRejectModal() {
    const modal = document.getElementById('rejectInterviewModal');
    const form = document.getElementById('rejectInterviewForm');

    modal?.classList.add('hidden');

    if (form) {
        form.removeAttribute('action');
    }
}

document.getElementById('rejectInterviewForm')?.addEventListener('submit', function (e) {
    if (!this.getAttribute('action')) {
        e.preventDefault();
    }
});

@endif

@if($canCreateStaffFromInterview)
function openCreateStaffModal(button) {
    const url = button?.dataset.createUrl;

    if (!url) {
        return;
    }

    // Best-guess split of the single "full name" from the application form:
    // last word = last name, the rest = first name. HR confirms it in the modal.
    const fullName = (button.dataset.applicantName || '').trim();
    const parts = fullName.split(/\s+/).filter(Boolean);
    const lastName = parts.length > 1 ? parts.pop() : '';

    document.getElementById('csFullName').textContent = fullName || 'Unknown applicant';
    document.getElementById('csFirstName').value = parts.join(' ');
    document.getElementById('csMiddleName').value = '';
    document.getElementById('csLastName').value = lastName;
    document.getElementById('csEmail').textContent = button.dataset.applicantEmail || '';
    document.getElementById('csRole').textContent = button.dataset.applicantPosition || '';
    document.getElementById('createStaffForm').action = url;
    document.getElementById('createStaffModal').classList.remove('hidden');
}

function closeCreateStaffModal() {
    document.getElementById('createStaffModal').classList.add('hidden');
    document.getElementById('createStaffForm').removeAttribute('action');
}

document.getElementById('createStaffForm')?.addEventListener('submit', function (e) {
    if (!this.getAttribute('action')) {
        e.preventDefault();
    }
});
@endif
</script>
@endsection
