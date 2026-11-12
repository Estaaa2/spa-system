@extends('layouts.app')

@section('title', 'Application Form')

@section('content')
@php
    $user = auth()->user();

    $canCreateHiring = $user?->hasBranchPermission('create hiring') ?? false;

    // Button / input tokens, same block as staff/index.blade.php (design language §5).
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';
    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'neutral' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
    ];
    $inputClass = 'block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 '
                . 'dark:placeholder-gray-400 dark:text-white';
    $labelClass = 'block mb-2 text-sm font-medium text-gray-900 dark:text-white';
    $hintClass  = 'mt-1 text-xs text-gray-500 dark:text-gray-400';
    $errorClass = 'mt-1 text-xs text-red-600 dark:text-red-400';

    $positions = ['therapist' => 'Therapist', 'receptionist' => 'Receptionist', 'manager' => 'Manager', 'hr' => 'HR', 'finance' => 'Finance'];
    $availabilities = ['full_time' => 'Full time', 'part_time' => 'Part time', 'weekdays' => 'Weekdays only', 'weekends' => 'Weekends only', 'shifting' => 'Shifting', 'flexible' => 'Flexible'];
    $educations = ['high_school' => 'High school', 'vocational' => 'Vocational', 'undergraduate' => 'Undergraduate', 'college' => 'College graduate', 'postgrad' => 'Post graduate'];

    $appErrors = $errors->getBag('application');
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">

    <x-page-header
        title="Application Form"
        subtitle="Record walk-in applicants for this branch. Online applications arrive on the Applicants page."
    />

    @if ($canCreateHiring)
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Walk-in Applicant</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    No age, date of birth, gender or civil status is asked (RA 10911, RA 10173).
                </p>
            </div>

            <form action="{{ route('hiring.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                @csrf

                <div class="p-4 space-y-8 sm:p-5">
                    @if ($appErrors->any())
                        <div class="p-3 text-sm text-red-700 border border-red-200 bg-red-50 rounded-xl dark:bg-red-900/20 dark:border-red-800 dark:text-red-300" role="alert">
                            <p class="font-semibold">Please fix the following:</p>
                            <ul class="mt-1 ml-4 list-disc">
                                @foreach ($appErrors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Position (job opening first, so the rest of the form follows it) --}}
                    <section>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Position</h3>
                        <div class="grid grid-cols-1 gap-4 mt-3 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="job_posting_id" class="{{ $labelClass }}">Job opening</label>
                                <select id="job_posting_id" name="job_posting_id" class="{{ $inputClass }}">
                                    <option value="">No posting (general walk-in)</option>
                                    @foreach ($openings as $opening)
                                        <option value="{{ $opening->id }}"
                                            data-role="{{ $opening->role }}"
                                            data-schedule='@json($opening->scheduleLines($branchHours))'
                                            @selected((string) old('job_posting_id') === (string) $opening->id)>
                                            {{ $opening->title }} · {{ $opening->roleLabel() }} · {{ $opening->employmentTypeLabel() }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="{{ $hintClass }}">
                                    @if ($openings->isEmpty())
                                        This branch has no open job openings, so this walk-in is recorded without one.
                                    @else
                                        Picking an opening sets the position and asks about its schedule.
                                    @endif
                                </p>
                                @error('job_posting_id', 'application')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                            </div>

                            {{-- Schedule fit, shown when an opening is picked --}}
                            <div id="walkinScheduleFit" class="hidden p-4 border border-blue-200 md:col-span-2 bg-blue-50 rounded-2xl dark:border-blue-800 dark:bg-blue-900/10">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">Opening's weekly schedule</p>
                                <ul id="walkinScheduleLines" class="grid grid-cols-1 mt-2 text-sm text-gray-700 sm:grid-cols-2 gap-x-6 gap-y-1 dark:text-gray-300"></ul>

                                <fieldset class="mt-4">
                                    <legend class="mb-1 text-sm font-medium text-gray-900 dark:text-white">
                                        Can the applicant work this schedule? <x-required-mark />
                                    </legend>
                                    <div class="flex gap-6">
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer min-h-[44px] dark:text-gray-300">
                                            <input type="radio" name="schedule_fit" value="1" class="w-4 h-4 accent-[#8B7355]" @checked(old('schedule_fit') === '1')> Yes
                                        </label>
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer min-h-[44px] dark:text-gray-300">
                                            <input type="radio" name="schedule_fit" value="0" class="w-4 h-4 accent-[#8B7355]" @checked(old('schedule_fit') === '0')> No
                                        </label>
                                    </div>
                                    @error('schedule_fit', 'application')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                                </fieldset>

                                <label for="schedule_fit_note" class="{{ $labelClass }} mt-2">Note <span class="font-normal text-gray-500 dark:text-gray-400">(optional)</span></label>
                                <input type="text" id="schedule_fit_note" name="schedule_fit_note" maxlength="255"
                                       value="{{ old('schedule_fit_note') }}" placeholder="e.g. Can't work Sundays" class="{{ $inputClass }}">
                            </div>

                            <div id="walkinPositionField">
                                <label for="position_applied" class="{{ $labelClass }}">Applying for <x-required-mark /></label>
                                <select id="position_applied" name="position_applied" required class="{{ $inputClass }}">
                                    <option value="">Select</option>
                                    @foreach ($positions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('position_applied') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('position_applied', 'application')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                            </div>

                            {{-- Walk-ins without an opening only --}}
                            <div id="walkinAvailabilityField">
                                <label for="availability" class="{{ $labelClass }}">Shift availability</label>
                                <select id="availability" name="availability" class="{{ $inputClass }}">
                                    <option value="">Select</option>
                                    @foreach ($availabilities as $value => $label)
                                        <option value="{{ $value }}" @selected(old('availability') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('availability', 'application')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="expected_start_date" class="{{ $labelClass }}">Expected start date</label>
                                <input type="date" id="expected_start_date" name="expected_start_date" value="{{ old('expected_start_date') }}" class="{{ $inputClass }}">
                                @error('expected_start_date', 'application')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="education" class="{{ $labelClass }}">Educational attainment</label>
                                <select id="education" name="education" class="{{ $inputClass }}">
                                    <option value="">Select</option>
                                    @foreach ($educations as $value => $label)
                                        <option value="{{ $value }}" @selected(old('education') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('education', 'application')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>

                    {{-- Applicant --}}
                    <section class="pt-6 border-t border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Applicant</h3>
                        <div class="grid grid-cols-1 gap-4 mt-3 md:grid-cols-3">
                            <div>
                                <label for="full_name" class="{{ $labelClass }}">Full name <x-required-mark /></label>
                                <input type="text" id="full_name" name="full_name" required maxlength="255" value="{{ old('full_name') }}" placeholder="Full name" class="{{ $inputClass }}">
                                @error('full_name', 'application')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="email" class="{{ $labelClass }}">Email <x-required-mark /></label>
                                <input type="email" id="email" name="email" required maxlength="255" value="{{ old('email') }}" placeholder="name@example.com" class="{{ $inputClass }}">
                                @error('email', 'application')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="phone" class="{{ $labelClass }}">Phone <x-required-mark /></label>
                                <input type="tel" id="phone" name="phone" required maxlength="11" inputmode="numeric" pattern="^09\d{9}$"
                                       value="{{ old('phone') }}" placeholder="09xxxxxxxxx" class="{{ $inputClass }}">
                                @error('phone', 'application')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                            </div>
                            <div class="md:col-span-3">
                                <label for="address" class="{{ $labelClass }}">Address <x-required-mark /></label>
                                <input type="text" id="address" name="address" required maxlength="255" value="{{ old('address') }}" placeholder="Street, Barangay, City" class="{{ $inputClass }}">
                                @error('address', 'application')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                            </div>
                            <div class="md:col-span-3">
                                <label for="resume" class="{{ $labelClass }}">Résumé / CV <span class="font-normal text-gray-500 dark:text-gray-400">(optional)</span></label>
                                <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx"
                                       class="block w-full text-sm text-gray-900 border border-gray-300 cursor-pointer rounded-xl bg-gray-50 file:mr-3 file:min-h-[44px] file:px-4 file:border-0 file:bg-gray-100 file:text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:file:bg-gray-600 dark:file:text-gray-200">
                                <p class="{{ $hintClass }}">Upload it if they brought one. PDF, DOC or DOCX, up to 5 MB.</p>
                                @error('resume', 'application')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>

                    {{-- Emergency contact & notes --}}
                    <section class="pt-6 border-t border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Emergency contact &amp; notes</h3>
                        <div class="grid grid-cols-1 gap-4 mt-3 md:grid-cols-3">
                            <div>
                                <label for="emergency_contact_name" class="{{ $labelClass }}">Contact person</label>
                                <input type="text" id="emergency_contact_name" name="emergency_contact_name" maxlength="255" value="{{ old('emergency_contact_name') }}" placeholder="Full name" class="{{ $inputClass }}">
                                @error('emergency_contact_name', 'application')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="emergency_contact_relation" class="{{ $labelClass }}">Relationship</label>
                                <input type="text" id="emergency_contact_relation" name="emergency_contact_relation" maxlength="255" value="{{ old('emergency_contact_relation') }}" placeholder="e.g. Mother" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label for="emergency_contact_phone" class="{{ $labelClass }}">Contact number</label>
                                <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" maxlength="11" inputmode="numeric"
                                       value="{{ old('emergency_contact_phone') }}" placeholder="09xxxxxxxxx" class="{{ $inputClass }}">
                                @error('emergency_contact_phone', 'application')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                            </div>
                            <div class="md:col-span-3">
                                <label for="notes" class="{{ $labelClass }}">Notes / remarks</label>
                                <textarea id="notes" name="notes" rows="4" maxlength="5000" placeholder="Additional notes about the applicant" class="{{ $inputClass }}">{{ old('notes') }}</textarea>
                                @error('notes', 'application')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>
                </div>

                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="reset" class="w-full {{ $btn['neutral'] }} sm:w-auto">
                            <i class="text-xs fa-solid fa-rotate-left" aria-hidden="true"></i>
                            Clear Form
                        </button>
                        <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto">
                            <i class="text-xs fa-solid fa-paper-plane" aria-hidden="true"></i>
                            Submit Application
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @else
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-col items-center py-8 text-center text-gray-500 dark:text-gray-400">
                <i class="mb-3 text-4xl text-gray-400 fa-solid fa-lock" aria-hidden="true"></i>
                <p class="font-medium">You don't have permission to record applicants.</p>
            </div>
        </div>
    @endif
</div>

@if ($canCreateHiring)
<script>
(function () {
    'use strict';
    const posting = document.getElementById('job_posting_id');
    const position = document.getElementById('position_applied');
    const fit = document.getElementById('walkinScheduleFit');
    const lines = document.getElementById('walkinScheduleLines');
    const availability = document.getElementById('walkinAvailabilityField');
    if (!posting || !position) return;

    function sync() {
        const opt = posting.selectedOptions[0];
        const picked = !!posting.value;
        // The opening decides the role; the server sets it too.
        if (picked && opt?.dataset.role) position.value = opt.dataset.role;
        position.disabled = picked;
        position.required = !picked;
        availability?.classList.toggle('hidden', picked);
        fit.classList.toggle('hidden', !picked);
        fit.querySelectorAll('input[name="schedule_fit"]').forEach(r => { r.required = picked; });

        lines.textContent = '';
        if (!picked) return;
        JSON.parse(opt.dataset.schedule || '[]').forEach(l => {
            const li = document.createElement('li');
            li.className = 'flex justify-between gap-3';
            const day = document.createElement('span');
            day.className = 'font-medium';
            day.textContent = l.day;
            const label = document.createElement('span');
            label.className = l.rest ? 'text-gray-500 dark:text-gray-400' : '';
            label.textContent = l.label;
            li.append(day, label);
            lines.appendChild(li);
        });
    }

    posting.addEventListener('change', sync);
    posting.closest('form')?.addEventListener('reset', () => setTimeout(sync, 0));
    sync();
})();
</script>
@endif
@endsection
