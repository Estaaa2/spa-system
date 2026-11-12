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

    // Interview status → chip (canon palette §6).
    $statusClasses = [
        'pending'  => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'approved' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    ];

    // Button / input tokens, same block as staff/index.blade.php and appointments.blade.php
    // (design language §5). Rejecting is a status change, not a deletion, so it uses the
    // outlined-amber "cancel" style with fa-ban (§8), never solid red.
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';
    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'edit'    => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'neutral' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
        'cancel'  => $btnBase . ' border border-amber-300 bg-white text-amber-800 hover:bg-amber-50 '
                   . 'dark:border-amber-700 dark:bg-gray-800 dark:text-amber-300 dark:hover:bg-amber-900/20',
        'process' => $btnBase . ' bg-amber-700 text-white hover:bg-amber-800',
    ];
    $inputClass = 'block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 '
                . 'dark:placeholder-gray-400 dark:text-white';
    $labelClass = 'block mb-2 text-sm font-medium text-gray-900 dark:text-white';
    $closeBtn = 'inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 '
              . 'hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200';

    $rejectErrors = $errors->getBag('rejectInterview');
    $createErrors = $errors->getBag('createStaff');

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

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">
    <x-page-header
        title="Interviews"
        subtitle="Review scheduled interviews, record outcomes, and create staff accounts for approved applicants."
    />

    {{-- Summary cards (same recipe as the Staff page) --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Total Interviews</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">{{ $summary['total'] }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">All records</span>
            </div>
        </div>
        <div class="p-4 border shadow-sm sm:p-5 bg-amber-50 border-amber-200 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800">
            <p class="text-xs font-semibold tracking-wide uppercase text-amber-700 dark:text-amber-300">Pending Review</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold sm:text-3xl text-amber-900 dark:text-amber-200">{{ $summary['pending'] }}</h3>
                <span class="text-sm text-amber-700 dark:text-amber-300">Awaiting decision</span>
            </div>
        </div>
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Approved</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">{{ $summary['approved'] }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Passed interview</span>
            </div>
        </div>
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Rejected</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">{{ $summary['rejected'] }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Not selected</span>
            </div>
        </div>
    </div>

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-col gap-4 px-4 py-4 border-b border-gray-200 lg:flex-row lg:items-start lg:justify-between sm:px-6 dark:border-gray-700">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Interview Records</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Approve or reject interviews, then create staff accounts for approved applicants.</p>
            </div>

            <x-table-filters :action="route('interviews.index')" :shown="$interviews->count()" :total="$summary['total']"
                             :active="$activeFilters ?? 0" noun="interview"
                             :search="$filters['q'] ?? ''" search-placeholder="Applicant name or email">
                <x-table-filters.select name="status" label="Status" all="All statuses"
                    :options="['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']" :value="$filters['status'] ?? null" width="sm:w-36" />
                <x-table-filters.select name="posting" label="Job opening" all="All openings"
                    :options="['none' => 'No posting'] + ($postingOptions ?? collect())->all()" :value="$filters['posting'] ?? null" width="sm:w-48" />
            </x-table-filters>
        </div>

        <div class="md:overflow-x-auto">
            {{-- .rt collapses this table into stacked cards below md (design language §8). --}}
            <table role="table" class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Applicant</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Position</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Schedule</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Status</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Actions</th>
                    </tr>
                </thead>

                <tbody role="rowgroup" class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($interviews as $interview)
                        @php
                            $dateLabel = $interview->interview_date
                                ? \Carbon\Carbon::parse($interview->interview_date)->format('M d, Y')
                                : 'N/A';
                            $timeLabel = $interview->interview_time
                                ? \Carbon\Carbon::parse($interview->interview_time)->format('h:i A')
                                : 'N/A';
                        @endphp

                        <tr role="row" class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900" data-status="{{ $interview->status }}">
                            <td role="cell" data-label="Applicant" class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-10 h-10 text-sm font-semibold text-white rounded-full bg-[#8B7355] shrink-0" aria-hidden="true">
                                        {{ strtoupper(substr($interview->applicant->full_name ?? '?', 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $interview->applicant->full_name ?? 'Unknown Applicant' }}</p>
                                        <p class="text-sm text-gray-500 break-all dark:text-gray-400">{{ $interview->applicant->email ?? 'No email' }}</p>
                                    </div>
                                </div>
                            </td>

                            <td role="cell" data-label="Position" class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-[#8B7355]/10 text-[#6F5430] dark:bg-[#C4A97D]/15 dark:text-[#C4A97D]">
                                    {{ $positionLabel($interview) }}
                                </span>
                                @if ($interview->applicant?->jobPosting)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $interview->applicant->jobPosting->title }}</p>
                                @endif
                            </td>

                            <td role="cell" data-label="Schedule" class="px-6 py-4 whitespace-nowrap">
                                <p class="text-sm text-gray-900 dark:text-white">{{ $dateLabel }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $timeLabel }}</p>
                            </td>

                            <td role="cell" data-label="Status" class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $statusClasses[$interview->status] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                                    {{ ucfirst($interview->status) }}
                                </span>
                                @if($interview->status === 'rejected' && filled($interview->rejection_reason))
                                    <p class="mt-2 text-xs leading-5 text-gray-500 md:max-w-xs dark:text-gray-400">{{ $interview->rejection_reason }}</p>
                                @endif
                            </td>

                            <td role="cell" class="px-6 py-4 rt-actions">
                                <div class="flex flex-wrap gap-2">
                                    @if($interview->status === 'pending')
                                        @if($canReviewInterview)
                                            <form action="{{ route('interviews.approve', $interview) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="{{ $btn['primary'] }}">
                                                    <i class="text-xs fa-solid fa-check" aria-hidden="true"></i>
                                                    Approve
                                                </button>
                                            </form>
                                            <button type="button" class="{{ $btn['cancel'] }}"
                                                    data-reject-url="{{ route('interviews.reject', $interview) }}"
                                                    data-applicant-name="{{ $interview->applicant?->full_name }}"
                                                    onclick="openInterviewRejectModal(this)">
                                                <i class="text-xs fa-solid fa-ban" aria-hidden="true"></i>
                                                Reject
                                            </button>
                                        @else
                                            <span class="text-sm text-gray-500 dark:text-gray-400">Pending review</span>
                                        @endif
                                    @elseif($interview->status === 'approved' && !$interview->staff_account_created)
                                        @if($canCreateStaffFromInterview)
                                            @if(in_array(mb_strtolower((string) $interview->applicant?->email), $existingStaffEmails ?? [], true))
                                                {{-- Account was already added on the Staff page: link it. --}}
                                                <form action="{{ route('interviews.create-staff', $interview) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="{{ $btn['edit'] }}"
                                                            title="A staff account with this email already exists. Link it to this interview.">
                                                        <i class="text-xs fa-solid fa-link" aria-hidden="true"></i>
                                                        Link Existing Account
                                                    </button>
                                                </form>
                                            @else
                                                <button type="button" class="{{ $btn['primary'] }}"
                                                        data-create-url="{{ route('interviews.create-staff', $interview) }}"
                                                        data-applicant-name="{{ $interview->applicant?->full_name }}"
                                                        data-applicant-email="{{ $interview->applicant?->email }}"
                                                        data-applicant-position="{{ $positionLabel($interview) }}"
                                                        onclick="openCreateStaffModal(this)">
                                                    <i class="text-xs fa-solid fa-user-plus" aria-hidden="true"></i>
                                                    Create Account
                                                </button>
                                            @endif
                                        @else
                                            <span class="text-sm text-gray-500 dark:text-gray-400">No staff access</span>
                                        @endif
                                    @elseif($interview->staff_account_created)
                                        <span class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                                            <i class="fa-solid fa-check" aria-hidden="true"></i>
                                            Account Created
                                        </span>
                                    @else
                                        <span class="text-sm text-gray-400" aria-label="No action">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr role="row">
                            <td role="cell" colspan="5" class="px-6 py-12 text-center text-gray-500 rt-empty dark:text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="mb-3 text-4xl text-gray-400 fa-solid fa-calendar-check" aria-hidden="true"></i>
                                    <p class="font-medium">{{ ($activeFilters ?? 0) > 0 ? 'No interviews match these filters.' : 'No interviews scheduled.' }}</p>
                                    @if (($activeFilters ?? 0) === 0)
                                        <p class="mt-1 text-sm">Schedule one from the Applicants page.</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($canReviewInterview)
{{-- Reject applicant (status change, so amber — not a deletion) --}}
<div id="rejectInterviewModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="rejectModalTitle" aria-describedby="rejectApplicantName"
             class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div>
                    <h2 id="rejectModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Reject Applicant</h2>
                    <p id="rejectApplicantName" class="mt-1 text-sm text-gray-500 dark:text-gray-400"></p>
                </div>
                <button type="button" onclick="closeInterviewRejectModal()" aria-label="Close dialog" class="{{ $closeBtn }}">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <form id="rejectInterviewForm" method="POST" action="{{ old('_reject_url') }}" novalidate>
                @csrf
                <input type="hidden" name="_reject_url" id="rejectUrl" value="{{ old('_reject_url') }}">
                <input type="hidden" name="_applicant_name" id="rejectNameInput" value="{{ old('_applicant_name') }}">

                <div class="px-4 py-6 space-y-4 sm:px-6">
                    <div class="flex gap-3 p-4 border rounded-2xl border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/10">
                        <i class="mt-0.5 text-amber-700 fa-solid fa-circle-exclamation dark:text-amber-300" aria-hidden="true"></i>
                        <p class="text-sm text-amber-800 dark:text-amber-300">This marks the applicant as rejected and emails them that they were not selected.</p>
                    </div>

                    @if ($rejectErrors->any())
                        <div class="p-3 text-sm text-red-700 border border-red-200 bg-red-50 rounded-xl dark:bg-red-900/20 dark:border-red-800 dark:text-red-300" role="alert">
                            <ul class="ml-4 list-disc">
                                @foreach ($rejectErrors->all() as $message)<li>{{ $message }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    <div>
                        <label for="rejection_reason" class="{{ $labelClass }}">Internal reason <x-required-mark /></label>
                        <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">For HR records only. This is not sent to the applicant.</p>
                        <textarea id="rejection_reason" name="rejection_reason" rows="4" maxlength="2000" required
                                  placeholder="Example: Does not meet the required experience for the position."
                                  class="{{ $inputClass }}">{{ old('rejection_reason') }}</textarea>
                    </div>

                    <div>
                        <label for="applicant_message" class="{{ $labelClass }}">Message to applicant <span class="font-normal text-gray-500 dark:text-gray-400">(optional)</span></label>
                        <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">The applicant always gets a polite "not selected" email. Anything you write here is added to it.</p>
                        <textarea id="applicant_message" name="applicant_message" rows="3" maxlength="2000"
                                  placeholder="Example: We encourage you to apply again once you have more hands-on massage experience."
                                  class="{{ $inputClass }}">{{ old('applicant_message') }}</textarea>
                    </div>
                </div>

                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeInterviewRejectModal()" class="w-full {{ $btn['neutral'] }} sm:w-auto">Keep Pending</button>
                        <button type="submit" class="w-full {{ $btn['process'] }} sm:w-auto">
                            <i class="text-xs fa-solid fa-ban" aria-hidden="true"></i>
                            Reject Applicant
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if($canCreateStaffFromInterview)
{{-- Create staff account --}}
<div id="createStaffModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="createStaffTitle"
             class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div>
                    <h2 id="createStaffTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Create Staff Account</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Applied as <span id="csFullName" class="font-medium text-gray-900 dark:text-white"></span>.
                        Confirm the legal name; it is used for identity verification and payroll.
                    </p>
                </div>
                <button type="button" onclick="closeCreateStaffModal()" aria-label="Close dialog" class="{{ $closeBtn }}">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <form id="createStaffForm" method="POST" action="{{ old('_create_url') }}" novalidate>
                @csrf
                <input type="hidden" name="_create_url" id="createUrl" value="{{ old('_create_url') }}">
                <input type="hidden" name="_applicant_name" id="csNameInput" value="{{ old('_applicant_name') }}">
                <input type="hidden" name="_applicant_email" id="csEmailInput" value="{{ old('_applicant_email') }}">
                <input type="hidden" name="_applicant_position" id="csPositionInput" value="{{ old('_applicant_position') }}">

                <div class="px-4 py-6 space-y-4 sm:px-6">
                    @if ($createErrors->any())
                        <div class="p-3 text-sm text-red-700 border border-red-200 bg-red-50 rounded-xl dark:bg-red-900/20 dark:border-red-800 dark:text-red-300" role="alert">
                            <ul class="ml-4 list-disc">
                                @foreach ($createErrors->all() as $message)<li>{{ $message }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="csFirstName" class="{{ $labelClass }}">First name <x-required-mark /></label>
                            <input type="text" name="first_name" id="csFirstName" required maxlength="255" value="{{ old('first_name') }}" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label for="csLastName" class="{{ $labelClass }}">Last name <x-required-mark /></label>
                            <input type="text" name="last_name" id="csLastName" required maxlength="255" value="{{ old('last_name') }}" class="{{ $inputClass }}">
                        </div>
                    </div>

                    <div>
                        <label for="csMiddleName" class="{{ $labelClass }}">Middle name</label>
                        <input type="text" name="middle_name" id="csMiddleName" maxlength="255" value="{{ old('middle_name') }}" class="{{ $inputClass }}">
                    </div>

                    <dl class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 rounded-2xl bg-gray-50 dark:bg-gray-900/40">
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Email</dt>
                            <dd id="csEmail" class="text-sm text-gray-900 break-all dark:text-white"></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Role</dt>
                            <dd id="csRole" class="text-sm text-gray-900 dark:text-white"></dd>
                        </div>
                    </dl>
                </div>

                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeCreateStaffModal()" class="w-full {{ $btn['neutral'] }} sm:w-auto">Cancel</button>
                        <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto">
                            <i class="text-xs fa-solid fa-user-plus" aria-hidden="true"></i>
                            Create Account
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<script>
(function () {
    'use strict';

    // ── Shared modal behaviour (same as staff/index.blade.php) ────────────────
    const MODAL_IDS = ['rejectInterviewModal', 'createStaffModal'];
    const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    let lastFocused = null;

    function topmostOpenModal() {
        for (let i = MODAL_IDS.length - 1; i >= 0; i--) {
            const el = document.getElementById(MODAL_IDS[i]);
            if (el && !el.classList.contains('hidden')) return el;
        }
        return null;
    }
    function openModal(id, focusSelector) {
        const el = document.getElementById(id);
        if (!el) return;
        lastFocused = document.activeElement;
        el.classList.remove('hidden');
        const target = (focusSelector && el.querySelector(focusSelector)) || el.querySelector(FOCUSABLE);
        if (target) target.focus();
    }
    function closeModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('hidden');
        if (lastFocused && document.contains(lastFocused)) lastFocused.focus();
        lastFocused = null;
    }
    document.addEventListener('keydown', function (e) {
        const modal = topmostOpenModal();
        if (!modal) return;
        if (e.key === 'Escape') { e.preventDefault(); closeModal(modal.id); return; }
        if (e.key !== 'Tab') return;
        const items = Array.from(modal.querySelectorAll(FOCUSABLE)).filter(el => el.offsetParent !== null);
        if (!items.length) return;
        const first = items[0], last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
    // Both modals are forms, so a backdrop click does not close them (design language §8).

    document.querySelectorAll('#rejectInterviewForm, #createStaffForm').forEach(form => {
        form.addEventListener('submit', function (e) { if (!this.getAttribute('action')) e.preventDefault(); });
    });

@if($canReviewInterview)
    window.openInterviewRejectModal = function (button) {
        const url = button?.dataset.rejectUrl;
        if (!url) return;
        const form = document.getElementById('rejectInterviewForm');
        form.action = url;
        document.getElementById('rejectUrl').value = url;
        document.getElementById('rejectNameInput').value = button.dataset.applicantName || '';
        document.getElementById('rejectApplicantName').textContent = 'Applicant: ' + (button.dataset.applicantName || '');
        openModal('rejectInterviewModal', '#rejection_reason');
    };
    window.closeInterviewRejectModal = () => closeModal('rejectInterviewModal');

    @if ($rejectErrors->any() && old('_reject_url'))
        document.getElementById('rejectApplicantName').textContent = 'Applicant: ' + @json(old('_applicant_name', ''));
        openModal('rejectInterviewModal', '#rejection_reason');
    @endif
@endif

@if($canCreateStaffFromInterview)
    window.openCreateStaffModal = function (button) {
        const url = button?.dataset.createUrl;
        if (!url) return;

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
        document.getElementById('csNameInput').value = fullName;
        document.getElementById('csEmailInput').value = button.dataset.applicantEmail || '';
        document.getElementById('csPositionInput').value = button.dataset.applicantPosition || '';
        document.getElementById('createUrl').value = url;
        document.getElementById('createStaffForm').action = url;
        openModal('createStaffModal', '#csFirstName');
    };
    window.closeCreateStaffModal = () => closeModal('createStaffModal');

    @if ($createErrors->any() && old('_create_url'))
        document.getElementById('csFullName').textContent = @json(old('_applicant_name', ''));
        document.getElementById('csEmail').textContent = @json(old('_applicant_email', ''));
        document.getElementById('csRole').textContent = @json(old('_applicant_position', ''));
        openModal('createStaffModal', '#csFirstName');
    @endif
@endif
})();
</script>

<style>
@media (max-width: 767px) {
    .rt,
    .rt tbody,
    .rt tr,
    .rt td { display: block; width: 100%; }

    .rt thead { display: none; }

    .rt tr { padding: 0.75rem 1rem; }

    .rt td {
        padding: 0.375rem 0 !important;
        text-align: left !important;
    }

    .rt td[data-label]::before {
        content: attr(data-label);
        display: block;
        margin-bottom: 0.125rem;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .rt td.rt-actions { padding-top: 0.75rem !important; }

    .rt td.rt-empty {
        padding: 2rem 0 !important;
        text-align: center !important;
    }
}

@media (max-width: 767px) and (prefers-color-scheme: dark) {
    .rt td[data-label]::before { color: #9ca3af; }
}
</style>
@endsection
