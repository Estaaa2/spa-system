@extends('layouts.app')

@section('title', 'Applications')
@section('content')
@php
    $user = auth()->user();

    $canViewApplications = $user?->hasBranchPermission('view applications') ?? false;
    $canEditApplications = $user?->hasBranchPermission('edit applications') ?? false;
    $canDeleteApplications = $user?->hasBranchPermission('delete applications') ?? false;

    $canCreateInterviews = $user?->hasBranchPermission('create interviews') ?? false;
    $canScheduleInterview = $canEditApplications && $canCreateInterviews;

    $formatLabel = function ($value) {
        return filled($value) ? ucwords(str_replace('_', ' ', $value)) : 'N/A';
    };

    // Button / input tokens, same block as staff/index.blade.php and appointments.blade.php
    // (levictas-design-language §5), so controls are identical across pages.
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';
    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'edit'    => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'neutral' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
    ];
    $inputClass = 'block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 '
                . 'dark:placeholder-gray-400 dark:text-white';
    $labelClass = 'block mb-2 text-sm font-medium text-gray-900 dark:text-white';

    // Applicant status → chip (canon palette §6: amber / blue / emerald / teal / red).
    $statusChip = [
        'pending'   => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'interview' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'approved'  => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'hired'     => 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300',
        'rejected'  => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    ];
    $chipFallback = 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';

    $scheduleErrors = $errors->getBag('schedule');

    // The Branch column only helps spa-wide users who see more than one branch.
    $showBranch = $applicants->pluck('branch_id')->unique()->count() > 1;
    $colCount = $showBranch ? 7 : 6;
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">

    <x-page-header title="Applications" subtitle="List of all applicants from hiring." />

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">

        {{-- Card header + filters --}}
        <div class="flex flex-col gap-4 px-4 py-4 border-b border-gray-200 lg:flex-row lg:items-start lg:justify-between sm:px-6 dark:border-gray-700">
            <div class="shrink-0">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Applicant List</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Online applications and walk-ins.</p>
            </div>

            <x-table-filters :action="route('applications.index')" :shown="$applicants->count()" :total="$total"
                             :active="$activeFilters" noun="applicant"
                             :search="$filters['q'] ?? ''" search-placeholder="Name, email or phone">
                <x-table-filters.select name="status" label="Status" all="All statuses" :options="$statusOptions" :value="$filters['status']" width="sm:w-36" />
                <x-table-filters.select name="source" label="Applied" all="Online & walk-in" :options="$sourceOptions" :value="$filters['source']" width="sm:w-40" />
                <x-table-filters.select name="posting" label="Job opening" all="All openings"
                    :options="['none' => 'No posting'] + $postingOptions->all()" :value="$filters['posting']" width="sm:w-48" />
            </x-table-filters>
        </div>

        <div class="md:overflow-x-auto">
            {{-- .rt collapses this table into stacked cards below md (design language §8). --}}
            <table role="table" class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Applicant</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Position</th>
                        @if ($showBranch)
                            <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Branch</th>
                        @endif
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Contact</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Applied</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Status</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Actions</th>
                    </tr>
                </thead>

                <tbody role="rowgroup" class="divide-y divide-gray-200 dark:divide-gray-700">
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
                                'job_posting' => $applicant->jobPosting?->title,
                                'schedule_fit' => $applicant->schedule_fit === null ? null : ($applicant->schedule_fit ? 'Yes' : 'No'),
                                'schedule_fit_note' => $applicant->schedule_fit_note,
                                'availability' => $applicant->availability,
                                'source' => match ($applicant->source) { 'website' => 'Online', 'manual' => 'Walk-in', default => $applicant->source },
                                'education' => $applicant->education,
                                'expected_start_date' => optional($applicant->expected_start_date)->format('M d, Y'),
                                'resume_url' => filled($applicant->resume_path)
                                    ? route('applications.resume.view', $applicant->id)
                                    : null,
                                'emergency_contact_name' => $applicant->emergency_contact_name,
                                'emergency_contact_relation' => $applicant->emergency_contact_relation,
                                'emergency_contact_phone' => $applicant->emergency_contact_phone,
                                'notes' => $applicant->notes,
                                'status' => $applicant->status,
                                'applied_on' => optional($applicant->created_at)->format('M d, Y'),
                                'interview_date' => $applicant->interview
                                    ? optional($applicant->interview->interview_date)->format('M d, Y')
                                    : null,
                                'interview_time' => $applicant->interview->interview_time ?? null,
                                'interview_status' => $applicant->interview->status ?? null,
                                'rejection_reason' => $applicant->interview->rejection_reason ?? null,
                            ];
                        @endphp

                        <tr role="row" class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900" data-status="{{ $applicant->status }}">
                            <td role="cell" data-label="Applicant" class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-10 h-10 text-sm font-semibold text-white rounded-full bg-[#8B7355] shrink-0" aria-hidden="true">
                                        {{ strtoupper(substr($applicant->full_name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $applicant->full_name }}</p>
                                        <p class="text-sm text-gray-500 break-all md:break-normal dark:text-gray-400">{{ $applicant->email }}</p>
                                    </div>
                                </div>
                            </td>

                            <td role="cell" data-label="Position" class="px-6 py-4">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-[#8B7355]/10 text-[#6F5430] dark:bg-[#C4A97D]/15 dark:text-[#C4A97D]">
                                        {{ $formatLabel($applicant->position_applied ?? $applicant->role) }}
                                    </span>
                                    @if ($applicant->source === 'website')
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300">Online</span>
                                    @elseif ($applicant->source === 'manual')
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $chipFallback }}">Walk-in</span>
                                    @endif
                                </div>
                                @if ($applicant->jobPosting)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $applicant->jobPosting->title }}</p>
                                @endif
                            </td>

                            @if ($showBranch)
                                <td role="cell" data-label="Branch" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $applicant->branch?->name ?? 'N/A' }}
                                </td>
                            @endif

                            <td role="cell" data-label="Contact" class="px-6 py-4">
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $applicant->phone ?? 'N/A' }}</p>
                                @if($applicant->address)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 md:truncate md:max-w-[160px]">{{ $applicant->address }}</p>
                                @endif
                            </td>

                            <td role="cell" data-label="Applied" class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap dark:text-gray-300">
                                {{ optional($applicant->created_at)->format('M d, Y') }}
                            </td>

                            <td role="cell" data-label="Status" class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $statusChip[$applicant->status] ?? $chipFallback }}">
                                    {{ ucfirst($applicant->status) }}
                                </span>
                            </td>

                            <td role="cell" class="px-6 py-4 rt-actions">
                                <div class="flex flex-wrap gap-2 md:flex-nowrap">
                                    <button type="button" class="{{ $btn['edit'] }}"
                                            data-applicant='@json($applicantData)'
                                            onclick="openApplicantDetailsModal(this)"
                                            aria-label="View {{ $applicant->full_name }}">
                                        <i class="text-xs fa-solid fa-eye" aria-hidden="true"></i>
                                        View
                                    </button>
                                    @if($applicant->status === 'pending' && $canScheduleInterview)
                                        <button type="button" class="{{ $btn['primary'] }}"
                                                data-schedule-url="{{ route('applications.schedule-interview', $applicant) }}"
                                                data-applicant-name="{{ $applicant->full_name }}"
                                                onclick="openInterviewScheduleModal(this)">
                                            <i class="text-xs fa-solid fa-calendar-plus" aria-hidden="true"></i>
                                            Schedule
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr role="row">
                            <td role="cell" colspan="{{ $colCount }}" class="px-6 py-12 text-center text-gray-500 rt-empty dark:text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="mb-3 text-4xl text-gray-400 fa-solid fa-users" aria-hidden="true"></i>
                                    <p class="font-medium">{{ $activeFilters > 0 ? 'No applicants match these filters.' : 'No applicants yet.' }}</p>
                                    @if ($activeFilters === 0)
                                        <p class="mt-1 text-sm">
                                            Online applications appear here. Record walk-ins on the
                                            <a href="{{ route('hiring.index') }}" class="inline-flex items-center min-h-[44px] -my-3 px-1 font-medium text-[#8B7355] hover:underline dark:text-[#C4A97D]">Application Form</a>.
                                        </p>
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

{{-- Applicant details --}}
<div id="applicantDetailsModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="detailName"
             class="w-full max-w-2xl bg-white shadow-xl rounded-2xl dark:bg-gray-800">

            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div class="flex items-center min-w-0 gap-3">
                    <div id="detailAvatar" aria-hidden="true"
                         class="flex items-center justify-center w-12 h-12 text-lg font-semibold text-white rounded-full shrink-0 bg-[#8B7355]"></div>
                    <div class="min-w-0">
                        <h2 id="detailName" class="text-lg font-semibold text-gray-900 dark:text-white"></h2>
                        <p id="detailEmail" class="text-sm text-gray-500 break-all dark:text-gray-400"></p>
                    </div>
                </div>
                <button type="button" onclick="closeApplicantDetailsModal()" aria-label="Close applicant details"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="px-4 py-6 space-y-6 text-sm sm:px-6">
                <section>
                    <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Personal information</h3>
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Phone</dt><dd id="detailPhone" class="text-gray-900 dark:text-white"></dd></div>
                        {{-- No longer collected (Phase 5, RA 10911 / RA 10173); shown only on older applications. --}}
                        <div id="detailGenderWrap"><dt class="text-xs text-gray-500 dark:text-gray-400">Gender</dt><dd id="detailGender" class="text-gray-900 dark:text-white"></dd></div>
                        <div id="detailDobWrap"><dt class="text-xs text-gray-500 dark:text-gray-400">Date of Birth</dt><dd id="detailDob" class="text-gray-900 dark:text-white"></dd></div>
                        <div id="detailCivilStatusWrap"><dt class="text-xs text-gray-500 dark:text-gray-400">Civil Status</dt><dd id="detailCivilStatus" class="text-gray-900 dark:text-white"></dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs text-gray-500 dark:text-gray-400">Address</dt><dd id="detailAddress" class="text-gray-900 dark:text-white"></dd></div>
                    </dl>
                </section>

                <section class="pt-6 border-t border-gray-200 dark:border-gray-700">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Position</h3>
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Applying For</dt><dd id="detailPosition" class="text-gray-900 dark:text-white"></dd></div>
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Job Opening</dt><dd id="detailJobPosting" class="text-gray-900 dark:text-white"></dd></div>
                        <div id="detailScheduleFitWrap"><dt class="text-xs text-gray-500 dark:text-gray-400">Can work the schedule</dt><dd id="detailScheduleFit" class="text-gray-900 dark:text-white"></dd></div>
                        <div id="detailAvailabilityWrap"><dt class="text-xs text-gray-500 dark:text-gray-400">Availability</dt><dd id="detailAvailability" class="text-gray-900 dark:text-white"></dd></div>
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Source</dt><dd id="detailSource" class="text-gray-900 dark:text-white"></dd></div>
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Education</dt><dd id="detailEducation" class="text-gray-900 dark:text-white"></dd></div>
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Expected Start</dt><dd id="detailStartDate" class="text-gray-900 dark:text-white"></dd></div>
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Applied On</dt><dd id="detailAppliedOn" class="text-gray-900 dark:text-white"></dd></div>
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Résumé / CV</dt><dd id="detailResume" class="text-gray-900 dark:text-white"></dd></div>
                    </dl>
                </section>

                <section id="detailInterviewWrapper" class="hidden pt-6 border-t border-gray-200 dark:border-gray-700">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Interview</h3>
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Date</dt><dd id="detailInterviewDate" class="text-gray-900 dark:text-white"></dd></div>
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Time</dt><dd id="detailInterviewTime" class="text-gray-900 dark:text-white"></dd></div>
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Status</dt><dd id="detailInterviewStatus" class="font-medium text-gray-900 dark:text-white"></dd></div>
                    </dl>
                    <a href="{{ route('interviews.index') }}"
                       class="inline-flex items-center gap-1 min-h-[44px] -my-2 mt-1 text-sm font-medium text-[#8B7355] hover:underline dark:text-[#C4A97D]">
                        View in Interviews <i class="text-xs fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </section>

                <section id="detailRejectionWrapper" class="hidden">
                    <div class="p-4 border border-red-200 bg-red-50 rounded-2xl dark:border-red-800 dark:bg-red-900/10">
                        <h3 class="flex items-center gap-2 mb-2 text-sm font-semibold text-red-700 dark:text-red-300">
                            <i class="fa-solid fa-ban" aria-hidden="true"></i> Rejection reason
                        </h3>
                        <p id="detailRejectionReason" class="text-sm leading-6 text-gray-700 whitespace-pre-line dark:text-gray-300"></p>
                    </div>
                </section>

                <section class="pt-6 border-t border-gray-200 dark:border-gray-700">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Emergency contact</h3>
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Name</dt><dd id="detailEmergencyName" class="text-gray-900 dark:text-white"></dd></div>
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Relationship</dt><dd id="detailEmergencyRelation" class="text-gray-900 dark:text-white"></dd></div>
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Number</dt><dd id="detailEmergencyPhone" class="text-gray-900 dark:text-white"></dd></div>
                    </dl>
                </section>

                <section id="detailNotesWrapper" class="hidden pt-6 border-t border-gray-200 dark:border-gray-700">
                    <h3 class="mb-2 text-sm font-semibold text-gray-900 dark:text-white">Notes</h3>
                    <p id="detailNotes" class="text-gray-700 whitespace-pre-line dark:text-gray-300"></p>
                </section>
            </div>

            <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" onclick="closeApplicantDetailsModal()" class="w-full {{ $btn['neutral'] }} sm:w-auto">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

@if($canScheduleInterview)
{{-- Schedule interview --}}
<div id="scheduleModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="scheduleModalTitle" aria-describedby="scheduleApplicantName"
             class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div>
                    <h2 id="scheduleModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Schedule Interview</h2>
                    <p id="scheduleApplicantName" class="mt-1 text-sm text-gray-500 dark:text-gray-400"></p>
                </div>
                <button type="button" onclick="closeInterviewScheduleModal()" aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <form id="scheduleForm" method="POST" action="{{ old('_schedule_url') }}" novalidate>
                @csrf
                <input type="hidden" name="_schedule_url" id="scheduleUrl" value="{{ old('_schedule_url') }}">
                <input type="hidden" name="_applicant_name" id="scheduleNameInput" value="{{ old('_applicant_name') }}">

                <div class="px-4 py-6 space-y-4 sm:px-6">
                    @if ($scheduleErrors->any())
                        <div class="p-3 text-sm text-red-700 border border-red-200 bg-red-50 rounded-xl dark:bg-red-900/20 dark:border-red-800 dark:text-red-300" role="alert">
                            <ul class="ml-4 list-disc">
                                @foreach ($scheduleErrors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="interview_date" class="{{ $labelClass }}">Interview date <x-required-mark /></label>
                            <input type="date" id="interview_date" name="interview_date" value="{{ old('interview_date') }}" required class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label for="interview_time" class="{{ $labelClass }}">Interview time <x-required-mark /></label>
                            <input type="time" id="interview_time" name="interview_time" value="{{ old('interview_time') }}" required class="{{ $inputClass }}">
                        </div>
                    </div>

                    <div>
                        <label for="interview_remarks" class="{{ $labelClass }}">Remarks</label>
                        <textarea id="interview_remarks" name="remarks" rows="3" maxlength="5000" placeholder="Optional notes" class="{{ $inputClass }}">{{ old('remarks') }}</textarea>
                    </div>
                </div>

                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeInterviewScheduleModal()" class="w-full {{ $btn['neutral'] }} sm:w-auto">Cancel</button>
                        <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto">
                            <i class="text-xs fa-solid fa-calendar-check" aria-hidden="true"></i>
                            Schedule Interview
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
    const MODAL_IDS = ['applicantDetailsModal', 'scheduleModal'];
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

    // Backdrop click closes the read-only details view only; the schedule form keeps typed input.
    document.getElementById('applicantDetailsModal')?.addEventListener('click', function (e) {
        if (e.target === this || e.target === this.firstElementChild) closeModal(this.id);
    });

    // ── Applicant details ────────────────────────────────────────────────────
    const fmt = (val) => (val && val !== 'null')
        ? String(val).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
        : 'N/A';
    const set = (id, text) => { document.getElementById(id).textContent = text; };
    const toggle = (id, show) => document.getElementById(id).classList.toggle('hidden', !show);

    window.openApplicantDetailsModal = function (button) {
        if (!button) return;
        const data = JSON.parse(button.dataset.applicant);

        set('detailAvatar', (data.full_name || '?').charAt(0).toUpperCase());
        set('detailName', data.full_name || 'Applicant');
        set('detailEmail', data.email || 'N/A');
        set('detailPhone', data.phone || 'N/A');
        set('detailGender', fmt(data.gender));
        set('detailDob', data.date_of_birth || 'N/A');
        set('detailCivilStatus', fmt(data.civil_status));
        toggle('detailGenderWrap', !!data.gender);
        toggle('detailDobWrap', !!data.date_of_birth);
        toggle('detailCivilStatusWrap', !!data.civil_status);
        set('detailAddress', data.address || 'N/A');
        set('detailPosition', fmt(data.position_applied));
        set('detailJobPosting', data.job_posting || 'No posting');
        set('detailScheduleFit', (data.schedule_fit || 'N/A') + (data.schedule_fit_note ? ' — ' + data.schedule_fit_note : ''));
        toggle('detailScheduleFitWrap', !!data.schedule_fit);
        set('detailAvailability', fmt(data.availability));
        toggle('detailAvailabilityWrap', !!data.availability);
        set('detailSource', data.source || 'N/A');
        set('detailEducation', fmt(data.education));
        set('detailStartDate', data.expected_start_date || 'N/A');
        set('detailAppliedOn', data.applied_on || 'N/A');

        const resume = document.getElementById('detailResume');
        resume.textContent = '';
        if (data.resume_url) {
            const a = document.createElement('a');
            a.href = data.resume_url;
            a.target = '_blank';
            a.rel = 'noopener';
            a.className = 'inline-flex items-center gap-1 min-h-[44px] -my-3 font-medium text-[#8B7355] hover:underline dark:text-[#C4A97D]';
            a.textContent = 'View résumé';
            resume.appendChild(a);
        } else {
            resume.textContent = 'No résumé uploaded';
        }

        const rejected = data.status === 'rejected' && data.rejection_reason;
        toggle('detailRejectionWrapper', rejected);
        set('detailRejectionReason', rejected ? data.rejection_reason : '');

        set('detailEmergencyName', data.emergency_contact_name || 'N/A');
        set('detailEmergencyRelation', data.emergency_contact_relation || 'N/A');
        set('detailEmergencyPhone', data.emergency_contact_phone || 'N/A');

        toggle('detailNotesWrapper', !!data.notes);
        set('detailNotes', data.notes || '');

        toggle('detailInterviewWrapper', !!data.interview_date);
        if (data.interview_date) {
            set('detailInterviewDate', data.interview_date);
            set('detailInterviewTime', data.interview_time || 'N/A');
            set('detailInterviewStatus', data.interview_status ? fmt(data.interview_status) : 'N/A');
        }

        openModal('applicantDetailsModal', 'button[aria-label="Close applicant details"]');
    };

    window.closeApplicantDetailsModal = () => closeModal('applicantDetailsModal');

    @if($canScheduleInterview)
    // ── Schedule interview ───────────────────────────────────────────────────
    const scheduleForm = document.getElementById('scheduleForm');

    window.openInterviewScheduleModal = function (button) {
        const url = button?.dataset.scheduleUrl;
        if (!url) return;
        scheduleForm.action = url;
        document.getElementById('scheduleUrl').value = url;
        document.getElementById('scheduleNameInput').value = button.dataset.applicantName || '';
        set('scheduleApplicantName', 'Applicant: ' + (button.dataset.applicantName || ''));
        openModal('scheduleModal', '#interview_date');
    };

    window.closeInterviewScheduleModal = () => closeModal('scheduleModal');

    scheduleForm?.addEventListener('submit', function (e) {
        if (!this.getAttribute('action')) e.preventDefault();
    });

    // Reopen with the typed values when the server sent errors back.
    @if ($scheduleErrors->any() && old('_schedule_url'))
        set('scheduleApplicantName', 'Applicant: ' + @json(old('_applicant_name', '')));
        openModal('scheduleModal', '#interview_date');
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
