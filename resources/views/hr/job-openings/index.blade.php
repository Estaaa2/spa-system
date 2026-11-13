@extends('layouts.app')

@section('title', 'Job Openings')

@section('content')
@php
    use App\Models\JobPosting;
    use App\Models\StaffWorkSchedule;

    $user = auth()->user();
    $canCreate = $user?->hasBranchPermission('create hiring') ?? false;
    $canEdit   = $user?->hasBranchPermission('edit hiring') ?? false;
    $canDelete = $user?->hasBranchPermission('delete hiring') ?? false;
    $canViewApplicants = $user?->hasBranchPermission('view applications') ?? false;

    // Same controls as the Staff page (levictas-design-language §5).
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';
    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'edit'    => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'neutral' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
        'remove'  => $btnBase . ' bg-red-700 text-white hover:bg-red-800',
    ];
    $closeBtn = 'inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 '
              . 'hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200';
    $inputClass = 'block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 '
                . 'dark:placeholder-gray-400 dark:text-white';
    $labelClass = 'block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400';

    $statusChip = [
        'open'    => ['Open', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
        'filled'  => ['Filled', 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300'],
        'closed'  => ['Closed', 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'],
        'expired' => ['Deadline passed', 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'],
        'draft'   => ['Draft', 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300'],
    ];

    $errorsBag = $errors->getBag('posting');
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">

    <x-page-header title="Job Openings" :subtitle="'Openings for this branch. Open ones show a “We’re Hiring” badge on the landing page.'">
        @if ($canCreate)
            <x-slot name="right">
                <button type="button" onclick="openPostingModal(null)" class="{{ $btn['primary'] }}">
                    <i class="text-xs fa-solid fa-plus" aria-hidden="true"></i>
                    New Job Opening
                </button>
            </x-slot>
        @endif
    </x-page-header>

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">

        <div class="flex flex-col gap-4 px-4 py-4 border-b border-gray-200 lg:flex-row lg:items-start lg:justify-between sm:px-6 dark:border-gray-700">
            <div class="shrink-0">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Openings</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Close one when you stop hiring for it.</p>
            </div>

            <x-table-filters :action="route('job-openings.index')" :shown="$postings->count()" :total="$total"
                             :active="$activeFilters" noun="job opening"
                             :search="$filters['q'] ?? ''" search-placeholder="Job title">
                <x-table-filters.select name="status" label="Status" all="All statuses"
                    :options="\App\Http\Controllers\HR\JobOpeningController::DISPLAY_STATUSES" :value="$filters['status']" width="sm:w-40" />
                <x-table-filters.select name="role" label="Role" all="All roles"
                    :options="JobPosting::ROLES" :value="$filters['role']" width="sm:w-36" />
            </x-table-filters>
        </div>

        <div class="md:overflow-x-auto">
            {{-- .rt collapses this table into stacked cards below md (design language §8). --}}
            <table role="table" class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Position</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Hired</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Schedule</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Pay</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Deadline</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Applicants</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Status</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Actions</th>
                    </tr>
                </thead>
                <tbody role="rowgroup" class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($postings as $posting)
                        @php
                            $display = $posting->displayStatus();
                            [$chipLabel, $chipClass] = $statusChip[$display];
                            $restDays = collect($posting->schedule ?? [])->filter(fn ($d) => ($d['mode'] ?? null) === 'rest')->keys();
                            $postingData = [
                                'id' => $posting->id,
                                'title' => $posting->title,
                                'role' => $posting->role,
                                'openings' => $posting->openings,
                                'employment_type' => $posting->employment_type,
                                'status' => $posting->status,
                                'deadline' => $posting->deadline?->format('Y-m-d'),
                                'schedule' => $posting->schedule,
                                'pay_basis' => $posting->pay_basis,
                                'pay_min' => $posting->pay_min,
                                'pay_max' => $posting->pay_max,
                                'show_pay' => $posting->show_pay,
                                'description' => $posting->description,
                                'requirements' => $posting->requirements,
                                'update_url' => route('job-openings.update', $posting),
                            ];
                        @endphp
                        <tr role="row" class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900" data-status="{{ $display }}">
                            <td role="cell" data-label="Position" class="px-6 py-4">
                                <p class="font-medium text-gray-900 dark:text-white">{{ $posting->title }}</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $posting->roleLabel() }} · {{ $posting->employmentTypeLabel() }}
                                </p>
                            </td>
                            <td role="cell" data-label="Hired" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                {{ $posting->hired_count }} of {{ $posting->openings }}
                            </td>
                            <td role="cell" data-label="Schedule" class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                @if ($restDays->isEmpty())
                                    <span class="text-gray-400">Not set</span>
                                @else
                                    Rest: {{ $restDays->map(fn ($d) => substr($d, 0, 3))->implode(', ') }}
                                @endif
                            </td>
                            <td role="cell" data-label="Pay" class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                {{ $posting->payLabel(false) ?? '—' }}
                                @if ($posting->pay_basis)
                                    <p class="text-xs text-gray-400">{{ $posting->show_pay ? 'Shown publicly' : 'Hidden' }}</p>
                                @endif
                            </td>
                            <td role="cell" data-label="Deadline" class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                {{ $posting->deadline?->format('M j, Y') ?? 'None' }}
                            </td>
                            <td role="cell" data-label="Applicants" class="px-6 py-4 text-sm whitespace-nowrap">
                                @if ($canViewApplicants && $posting->applicants_count > 0)
                                    <a href="{{ route('applications.index', ['posting' => $posting->id]) }}"
                                       class="inline-flex items-center min-h-[44px] -my-3 font-medium text-[#8B7355] hover:underline dark:text-[#C4A97D]">
                                        {{ $posting->applicants_count }} {{ \Illuminate\Support\Str::plural('applicant', $posting->applicants_count) }}
                                    </a>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">{{ $posting->applicants_count }}</span>
                                @endif
                            </td>
                            <td role="cell" data-label="Status" class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full whitespace-nowrap {{ $chipClass }}">{{ $chipLabel }}</span>
                            </td>
                            <td role="cell" class="rt-actions px-6 py-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($canEdit)
                                        <button type="button" class="{{ $btn['edit'] }}"
                                                data-posting='@json($postingData)'
                                                onclick="openPostingModal(JSON.parse(this.dataset.posting))">
                                            <i class="text-xs fa-solid fa-pen" aria-hidden="true"></i>
                                            Edit
                                        </button>
                                        @if (in_array($display, ['open', 'expired'], true))
                                            <form method="POST" action="{{ route('job-openings.status', $posting) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="closed">
                                                <button type="submit" class="{{ $btn['neutral'] }}">
                                                    <i class="text-xs fa-solid fa-lock" aria-hidden="true"></i>
                                                    Close
                                                </button>
                                            </form>
                                        @elseif ($display === 'closed')
                                            <form method="POST" action="{{ route('job-openings.status', $posting) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="open">
                                                <button type="submit" class="{{ $btn['neutral'] }}">
                                                    <i class="text-xs fa-solid fa-lock-open" aria-hidden="true"></i>
                                                    Reopen
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                    @if ($canDelete)
                                        <button type="button" class="{{ $btn['remove'] }}"
                                                aria-label="Delete {{ $posting->title }}"
                                                data-url="{{ route('job-openings.destroy', $posting) }}"
                                                data-title="{{ $posting->title }}"
                                                onclick="openPostingDeleteModal(this)">
                                            <i class="text-xs fa-solid fa-trash" aria-hidden="true"></i>
                                            Delete
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr role="row">
                            <td role="cell" colspan="8" class="px-6 py-12 text-center text-gray-500 rt-empty dark:text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="mb-3 text-4xl text-gray-400 fa-solid fa-bullhorn" aria-hidden="true"></i>
                                    <p class="font-medium">{{ $activeFilters > 0 ? 'No job openings match these filters.' : 'No job openings yet.' }}</p>
                                    @if ($canCreate && $activeFilters === 0)
                                        <p class="mt-1 text-sm">Create one to show "We're Hiring" on the landing page.</p>
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

@if ($canCreate || $canEdit)
{{-- Create / edit modal --}}
<div id="postingModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="postingModalTitle"
             class="w-full max-w-3xl bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div>
                    <h2 id="postingModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">New Job Opening</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No age or birth-date requirements (RA 10911).</p>
                </div>
                <button type="button" onclick="closePostingModal()" aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <form id="postingForm" method="POST" action="{{ route('job-openings.store') }}" novalidate>
                @csrf
                <input type="hidden" name="_method" id="postingMethod" value="POST" disabled>
                <input type="hidden" name="_posting_id" id="postingId" value="">

                <div class="px-4 py-6 space-y-6 sm:px-6">
                    @if ($errorsBag->any())
                        <div class="p-3 text-sm text-red-700 border border-red-200 bg-red-50 rounded-xl dark:bg-red-900/20 dark:border-red-800 dark:text-red-300" role="alert">
                            <p class="font-semibold">Please fix the following:</p>
                            <ul class="mt-1 ml-4 list-disc">
                                @foreach ($errorsBag->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="p_title" class="{{ $labelClass }}">Job title <x-required-mark /></label>
                            <input id="p_title" name="title" type="text" maxlength="100" required placeholder="e.g. Massage Therapist" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label for="p_role" class="{{ $labelClass }}">Role when hired <x-required-mark /></label>
                            <select id="p_role" name="role" required class="{{ $inputClass }}">
                                @foreach (JobPosting::ROLES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="p_type" class="{{ $labelClass }}">Job type <x-required-mark /></label>
                            <select id="p_type" name="employment_type" required class="{{ $inputClass }}">
                                @foreach (JobPosting::EMPLOYMENT_TYPES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="p_openings" class="{{ $labelClass }}">Number of openings <x-required-mark /></label>
                            <input id="p_openings" name="openings" type="number" min="1" max="50" required value="1" class="{{ $inputClass }}">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Closes by itself when this many are hired.</p>
                        </div>
                        <div>
                            <label for="p_deadline" class="{{ $labelClass }}">Deadline</label>
                            <input id="p_deadline" name="deadline" type="date" min="{{ now()->toDateString() }}" class="{{ $inputClass }}">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Stops showing after this day. Leave empty for no deadline.</p>
                        </div>
                        <div>
                            <label for="p_status" class="{{ $labelClass }}">Status <x-required-mark /></label>
                            <select id="p_status" name="status" required class="{{ $inputClass }}">
                                <option value="open">Open (shows publicly)</option>
                                <option value="draft">Draft (hidden)</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                    </section>

                    <section>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Weekly schedule <x-required-mark /></h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Applicants are asked if they can work it. It becomes the new staff member's work schedule when hired, and can be changed on the Staff page.</p>
                        <div class="mt-2 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach (StaffWorkSchedule::WEEKDAYS as $day)
                                @php $bh = $branchHours[$day] ?? null; @endphp
                                <div class="grid items-center grid-cols-1 gap-2 py-3 sm:grid-cols-[7rem_11rem_1fr]" data-day="{{ $day }}">
                                    <label for="p_mode_{{ $day }}" class="text-sm font-medium text-gray-900 dark:text-white">{{ $day }}</label>
                                    <select id="p_mode_{{ $day }}" name="schedule[{{ $day }}][mode]" class="{{ $inputClass }} posting-mode">
                                        <option value="branch">Branch hours</option>
                                        <option value="custom">Custom hours</option>
                                        <option value="rest">Rest day</option>
                                    </select>
                                    <div>
                                        <div class="flex items-center gap-2 posting-times hidden">
                                            <input type="time" name="schedule[{{ $day }}][start]" aria-label="{{ $day }} start time" data-default="{{ $bh['open'] ?? '' }}" class="{{ $inputClass }}">
                                            <span class="text-sm text-gray-500 dark:text-gray-400">to</span>
                                            <input type="time" name="schedule[{{ $day }}][end]" aria-label="{{ $day }} end time" data-default="{{ $bh['close'] ?? '' }}" class="{{ $inputClass }}">
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            @if ($branchHours === [])
                                                No branch hours set yet
                                            @elseif (! $bh || $bh['closed'])
                                                <i class="mr-1 fa-solid fa-store-slash" aria-hidden="true"></i>Branch closed
                                            @else
                                                Branch: {{ \Illuminate\Support\Carbon::createFromFormat('H:i', $bh['open'])->format('g:i A') }} – {{ \Illuminate\Support\Carbon::createFromFormat('H:i', $bh['close'])->format('g:i A') }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <section>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Pay <span class="text-sm font-normal text-gray-400">(optional)</span></h3>
                        <div class="grid grid-cols-1 gap-4 mt-2 sm:grid-cols-3">
                            <div>
                                <label for="p_pay_basis" class="{{ $labelClass }}">Paid by</label>
                                <select id="p_pay_basis" name="pay_basis" class="{{ $inputClass }}">
                                    <option value="">Not stated</option>
                                    @foreach (JobPosting::PAY_BASES as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="posting-pay-range">
                                <label for="p_pay_min" class="{{ $labelClass }}">From (₱)</label>
                                <input id="p_pay_min" name="pay_min" type="number" min="0" step="0.01" class="{{ $inputClass }}">
                            </div>
                            <div class="posting-pay-range">
                                <label for="p_pay_max" class="{{ $labelClass }}">To (₱)</label>
                                <input id="p_pay_max" name="pay_max" type="number" min="0" step="0.01" class="{{ $inputClass }}">
                            </div>
                        </div>
                        <label class="inline-flex items-center gap-2 mt-3 text-sm text-gray-700 cursor-pointer dark:text-gray-300 min-h-[44px]">
                            <input type="hidden" name="show_pay" value="0">
                            <input id="p_show_pay" type="checkbox" name="show_pay" value="1" class="w-4 h-4 rounded accent-[#8B7355]">
                            Show pay on the landing page
                        </label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">For information only. Pay is set on the staff member's pay profile after hiring.</p>
                    </section>

                    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="p_description" class="{{ $labelClass }}">Description</label>
                            <textarea id="p_description" name="description" rows="4" maxlength="2000" placeholder="What the job involves." class="{{ $inputClass }}"></textarea>
                        </div>
                        <div>
                            <label for="p_requirements" class="{{ $labelClass }}">Requirements</label>
                            <textarea id="p_requirements" name="requirements" rows="4" maxlength="2000" placeholder="Skills, licences or experience. No age, sex or civil-status requirements." class="{{ $inputClass }}"></textarea>
                        </div>
                    </section>
                </div>

                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closePostingModal()" class="w-full {{ $btn['neutral'] }} sm:w-auto">Cancel</button>
                        <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto">
                            <i class="text-xs fa-solid fa-floppy-disk" aria-hidden="true"></i>
                            Save Job Opening
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if ($canDelete)
<div id="postingDeleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="alertdialog" aria-modal="true" aria-labelledby="postingDeleteTitle" aria-describedby="postingDeleteDesc"
             class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <h2 id="postingDeleteTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Delete Job Opening</h2>
            <p id="postingDeleteDesc" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                This removes <span id="postingDeleteName" class="font-medium text-gray-900 dark:text-white"></span>
                from this page and the landing page. Its applicants stay on the Applicants page.
            </p>
            <div class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">
                <button type="button" onclick="closePostingDeleteModal()" class="w-full {{ $btn['neutral'] }} sm:w-auto">Keep It</button>
                <form id="postingDeleteForm" method="POST" class="sm:w-auto">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full {{ $btn['remove'] }} sm:w-auto">Yes, Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

<script>
(function () {
    'use strict';

    const STORE_URL = @json(route('job-openings.store'));
    const WEEKDAYS = @json(StaffWorkSchedule::WEEKDAYS);
    const form = document.getElementById('postingForm');

    function defaultSchedule() {
        const out = {};
        WEEKDAYS.forEach(d => { out[d] = { mode: d === 'Sunday' ? 'rest' : 'branch' }; });
        return out;
    }

    function syncRow(row) {
        const mode = row.querySelector('.posting-mode').value;
        const times = row.querySelector('.posting-times');
        times.classList.toggle('hidden', mode !== 'custom');
        if (mode === 'custom') {
            times.querySelectorAll('input[type="time"]').forEach(i => { if (!i.value) i.value = i.dataset.default || ''; });
        }
    }

    function syncPay() {
        const basis = form.querySelector('[name="pay_basis"]').value;
        form.querySelectorAll('.posting-pay-range').forEach(el => el.classList.toggle('hidden', basis === '' || basis === 'commission'));
    }

    function fill(p) {
        const set = (name, v) => { const el = form.querySelector(`[name="${name}"]`); if (el) el.value = v ?? ''; };
        set('title', p.title); set('role', p.role || 'therapist'); set('employment_type', p.employment_type || 'full_time');
        set('openings', p.openings || 1); set('deadline', p.deadline); set('status', p.status || 'open');
        set('pay_basis', p.pay_basis); set('pay_min', p.pay_min); set('pay_max', p.pay_max);
        set('description', p.description); set('requirements', p.requirements);
        document.getElementById('p_show_pay').checked = !!(p.show_pay === true || p.show_pay === '1' || p.show_pay === 1);

        const sched = p.schedule && Object.keys(p.schedule).length ? p.schedule : defaultSchedule();
        form.querySelectorAll('[data-day]').forEach(row => {
            const d = sched[row.dataset.day] || { mode: 'branch' };
            row.querySelector('.posting-mode').value = d.mode || 'branch';
            row.querySelector('input[name$="[start]"]').value = d.start || '';
            row.querySelector('input[name$="[end]"]').value = d.end || '';
            syncRow(row);
        });
        syncPay();
    }

    // ── Shared modal behaviour (same as staff/index.blade.php) ────────────────
    const MODAL_IDS = ['postingModal', 'postingDeleteModal'];
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
    // Backdrop click closes the delete confirmation only; the posting form keeps typed input (§8).
    document.getElementById('postingDeleteModal')?.addEventListener('click', function (e) {
        if (e.target === this || e.target === this.firstElementChild) closeModal(this.id);
    });

    window.openPostingModal = function (p) {
        if (!form) return;
        const editing = !!(p && (p.update_url || p.id));
        document.getElementById('postingModalTitle').textContent = editing ? 'Edit Job Opening' : 'New Job Opening';
        form.action = editing ? (p.update_url || STORE_URL) : STORE_URL;
        const method = document.getElementById('postingMethod');
        method.disabled = !editing;
        method.value = editing ? 'PUT' : 'POST';
        document.getElementById('postingId').value = editing ? (p.id || '') : '';
        fill(p || {});
        openModal('postingModal', '#p_title');
    };
    window.closePostingModal = () => closeModal('postingModal');

    window.openPostingDeleteModal = function (btn) {
        document.getElementById('postingDeleteForm').action = btn.dataset.url;
        document.getElementById('postingDeleteName').textContent = btn.dataset.title;
        openModal('postingDeleteModal', '#postingDeleteModal button[type="button"]');
    };
    window.closePostingDeleteModal = () => closeModal('postingDeleteModal');

    form?.querySelectorAll('[data-day] .posting-mode').forEach(sel => sel.addEventListener('change', () => syncRow(sel.closest('[data-day]'))));
    form?.querySelector('[name="pay_basis"]')?.addEventListener('change', syncPay);

    // Reopen with what was typed when the server sent errors back.
    @if ($errorsBag->any())
        @php
            $oldId = old('_posting_id');
            $old = [
                'id' => $oldId ?: null,
                'update_url' => $oldId ? route('job-openings.update', $oldId) : null,
                'title' => old('title'), 'role' => old('role'), 'openings' => old('openings'),
                'employment_type' => old('employment_type'), 'status' => old('status'), 'deadline' => old('deadline'),
                'schedule' => old('schedule'), 'pay_basis' => old('pay_basis'), 'pay_min' => old('pay_min'),
                'pay_max' => old('pay_max'), 'show_pay' => old('show_pay'),
                'description' => old('description'), 'requirements' => old('requirements'),
            ];
        @endphp
        openPostingModal(@json($old));
    @else
        // DSS revamp (D6): Decision Support's "Create job opening" links here with
        // ?dss_prefill=1&role=therapist&title=<text>. Opens a new opening, prefilled; nothing is saved yet.
        (function () {
            const q = new URLSearchParams(window.location.search);
            if (!q.get('dss_prefill') || !form) return;
            const roles = @json(array_keys(\App\Models\JobPosting::ROLES));
            const role = roles.includes(q.get('role')) ? q.get('role') : 'therapist';
            openPostingModal({ title: (q.get('title') || '').slice(0, 100), role: role, openings: 1 });
        })();
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
