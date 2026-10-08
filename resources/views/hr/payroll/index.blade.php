@extends('layouts.app')

@section('title', 'Payroll')

@section('content')
@php
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'primary' => $btnBase
            . ' bg-[#8B7355] text-white hover:bg-[#7A6348] disabled:opacity-60 disabled:cursor-not-allowed',

        'edit' => $btnBase
            . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
            . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',

        'neutral' => $btnBase
            . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
            . 'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
    ];

    $inputClass = 'w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl bg-white text-gray-900 '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:border-gray-600 dark:bg-gray-700 dark:text-white';

    $labelClass = 'block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300';

    $card = 'overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700';

    $cardHead = 'px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700';

    $th = 'px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400';

    $badgeBase = 'inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-medium rounded-full';

    $statusMeta = \App\Services\Payroll\Display\PayrollDisplay::STATUS;
    $typeLabels = \App\Services\Payroll\Display\PayrollDisplay::TYPE;

    $months = collect(range(1, 12))
        ->mapWithKeys(fn ($month) => [
            $month => \Illuminate\Support\Carbon::create(2000, $month, 1)->format('F'),
        ])
        ->all();

    $eb = $errors->getBag('newRun');
    $reopen = old('_modal') === 'newRunModal';
    $oldType = $reopen ? old('run_type', 'regular') : 'regular';

    $spa = auth()->user()?->spa;

    $hasPayrollAccess = (bool) (
        $spa
        && $spa->hasAccess()
        && $spa->hasFeature('payroll')
    );

    $hasGaps = $hasPayrollAccess && (
        ($gaps['suite_branches'] ?? 0) === 0
        || count($gaps['branches_missing_wage'] ?? []) > 0
        || ($gaps['staff_missing_profile'] ?? 0) > 0
    );
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">

    <x-page-header
        title="Payroll"
        subtitle="Semi-monthly and 13th-month pay runs for active branches covered by your payroll plan."
    >
        <x-slot name="right">
            <a
                href="{{ route('payroll.setup.index') }}"
                class="{{ $btn['edit'] }}"
            >
                <i class="text-xs fa-solid fa-sliders" aria-hidden="true"></i>
                <span class="hidden sm:inline">Setup</span>
                <span class="sr-only sm:hidden">Payroll setup</span>
            </a>

            @if ($canEdit && $hasPayrollAccess)
                <button
                    type="button"
                    class="{{ $btn['primary'] }}"
                    onclick="openNewRunModal()"
                >
                    <i class="text-xs fa-solid fa-plus" aria-hidden="true"></i>
                    New Run
                </button>
            @elseif ($canEdit && Route::has('owner.subscription.index'))
                <a
                    href="{{ route('owner.subscription.index') }}"
                    class="{{ $btn['primary'] }}"
                >
                    <i class="text-xs fa-solid fa-lock" aria-hidden="true"></i>
                    Unlock Payroll
                </a>
            @endif
        </x-slot>
    </x-page-header>

    @if (! $hasPayrollAccess)
        <div
            role="status"
            class="p-4 border border-purple-200 rounded-2xl bg-purple-50 dark:border-purple-800 dark:bg-purple-900/10"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-purple-900 dark:text-purple-200">
                        <i class="mr-1 fa-solid fa-lock" aria-hidden="true"></i>
                        Payroll requires an active Business plan
                    </p>

                    <p class="mt-1 text-sm text-purple-800 dark:text-purple-300">
                        Payroll generation is unavailable until your spa has an active subscription with payroll access.
                    </p>
                </div>

                @if (Route::has('owner.subscription.index'))
                    <a
                        href="{{ route('owner.subscription.index') }}"
                        class="{{ $btn['primary'] }} shrink-0"
                    >
                        <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                        View Subscription Plans
                    </a>
                @endif
            </div>
        </div>
    @endif

    {{-- Setup gaps --}}
    @if ($hasGaps)
        <div
            role="status"
            class="p-4 border border-amber-200 rounded-2xl bg-amber-50 dark:bg-amber-900/10 dark:border-amber-800"
        >
            <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                <i class="mr-1 fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                Some staff cannot be included until payroll setup is finished
            </p>

            <ul class="mt-2 space-y-1 text-sm list-disc list-inside text-amber-800 dark:text-amber-300">
                @if (($gaps['suite_branches'] ?? 0) === 0)
                    <li>
                        No active branch is currently available for payroll.
                    </li>
                @endif

                @if (count($gaps['branches_missing_wage'] ?? []) > 0)
                    <li>
                        No minimum daily wage for
                        {{ implode(', ', $gaps['branches_missing_wage']) }} —

                        <a
                            href="{{ route('payroll.setup.index', ['tab' => 'wages']) }}"
                            class="font-semibold underline hover:no-underline"
                        >
                            set branch wages
                        </a>.
                    </li>
                @endif

                @if (($gaps['staff_missing_profile'] ?? 0) > 0)
                    <li>
                        {{ $gaps['staff_missing_profile'] }}
                        active staff member(s) have no pay profile in effect today —

                        <a
                            href="{{ route('payroll.staff.index', ['filter' => 'no_profile']) }}"
                            class="font-semibold underline hover:no-underline"
                        >
                            add pay profiles
                        </a>.
                    </li>
                @endif
            </ul>
        </div>
    @endif

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Runs in {{ $filters['year'] }}
            </p>

            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                    {{ $summary['runs'] }}
                </h3>

                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                    All statuses
                </span>
            </div>
        </div>

        <div class="p-4 border shadow-sm sm:p-5 rounded-2xl {{ $summary['needs_action'] > 0 ? 'bg-amber-50 border-amber-200 dark:bg-amber-900/10 dark:border-amber-800' : 'bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700' }}">
            <p class="text-xs font-semibold tracking-wide uppercase {{ $summary['needs_action'] > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500 dark:text-gray-400' }}">
                Needs Action
            </p>

            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold sm:text-3xl {{ $summary['needs_action'] > 0 ? 'text-amber-900 dark:text-amber-200' : 'text-gray-900 dark:text-white' }}">
                    {{ $summary['needs_action'] }}
                </h3>

                <span class="text-xs sm:text-sm {{ $summary['needs_action'] > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500 dark:text-gray-400' }}">
                    Not yet released
                </span>
            </div>
        </div>

        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Gross Pay
            </p>

            <p class="mt-3 text-xl font-semibold text-gray-900 break-words sm:text-2xl dark:text-white">
                {{ $summary['gross'] }}
            </p>

            <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                Finalized &amp; released
            </span>
        </div>

        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Net Pay
            </p>

            <p class="mt-3 text-xl font-semibold break-words sm:text-2xl text-emerald-700 dark:text-emerald-400">
                {{ $summary['net'] }}
            </p>

            <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                Finalized &amp; released
            </span>
        </div>
    </div>

    {{-- Runs table --}}
    <div class="{{ $card }}">
        <div class="flex flex-col gap-4 {{ $cardHead }} lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    Pay Runs
                </h2>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    One consolidated run per cutoff for the whole spa.
                    Open a run to review, adjust and approve it.
                </p>
            </div>

            <form
                method="GET"
                action="{{ route('payroll.index') }}"
                class="grid grid-cols-2 gap-2 sm:flex sm:items-end"
                aria-label="Filter runs"
            >
                <div>
                    <label for="f_year" class="{{ $labelClass }}">Year</label>

                    <select
                        id="f_year"
                        name="year"
                        class="{{ $inputClass }} sm:w-28"
                    >
                        @foreach ($years as $year)
                            <option
                                value="{{ $year }}"
                                @selected($filters['year'] === $year)
                            >
                                {{ $year }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="f_type" class="{{ $labelClass }}">Type</label>

                    <select
                        id="f_type"
                        name="type"
                        class="{{ $inputClass }} sm:w-36"
                    >
                        <option value="">All types</option>

                        @foreach ($typeLabels as $key => $label)
                            <option
                                value="{{ $key }}"
                                @selected($filters['type'] === $key)
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="f_status" class="{{ $labelClass }}">Status</label>

                    <select
                        id="f_status"
                        name="status"
                        class="{{ $inputClass }} sm:w-36"
                    >
                        <option value="">All statuses</option>

                        @foreach ($statusMeta as $key => $meta)
                            <option
                                value="{{ $key }}"
                                @selected($filters['status'] === $key)
                            >
                                {{ $meta['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end">
                    <button
                        type="submit"
                        class="w-full {{ $btn['neutral'] }}"
                    >
                        <i class="text-xs fa-solid fa-filter" aria-hidden="true"></i>
                        Apply
                    </button>
                </div>
            </form>
        </div>

        <div class="md:overflow-x-auto">
            <table
                role="table"
                class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700"
            >
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" scope="col" class="{{ $th }}">Type</th>
                        <th role="columnheader" scope="col" class="{{ $th }}">Period</th>
                        <th role="columnheader" scope="col" class="{{ $th }}">Cutoff</th>
                        <th role="columnheader" scope="col" class="{{ $th }}">Pay Date</th>
                        <th role="columnheader" scope="col" class="{{ $th }}">Status</th>
                        <th role="columnheader" scope="col" class="{{ $th }} text-right">Staff</th>
                        <th role="columnheader" scope="col" class="{{ $th }} text-right">Gross</th>
                        <th role="columnheader" scope="col" class="{{ $th }} text-right">Net</th>
                        <th role="columnheader" scope="col" class="{{ $th }}">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>

                <tbody
                    role="rowgroup"
                    class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700"
                >
                    @forelse ($runs as $run)
                        <tr
                            role="row"
                            class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900"
                        >
                            <td
                                role="cell"
                                data-label="Type"
                                class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white"
                            >
                                {{ $run['type'] }}
                            </td>

                            <td
                                role="cell"
                                data-label="Period"
                                class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap dark:text-gray-300"
                            >
                                {{ $run['period'] }}
                            </td>

                            <td
                                role="cell"
                                data-label="Cutoff"
                                class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap dark:text-gray-300"
                            >
                                {{ $run['cutoff'] }}
                            </td>

                            <td
                                role="cell"
                                data-label="Pay Date"
                                class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap dark:text-gray-300"
                            >
                                {{ $run['pay_date'] }}
                            </td>

                            <td role="cell" data-label="Status" class="px-6 py-4">
                                <span class="{{ $badgeBase }} {{ $statusMeta[$run['status']]['class'] }}">
                                    <i
                                        class="fa-solid {{ $statusMeta[$run['status']]['icon'] }} text-[10px]"
                                        aria-hidden="true"
                                    ></i>

                                    {{ $statusMeta[$run['status']]['label'] }}
                                </span>
                            </td>

                            <td
                                role="cell"
                                data-label="Staff"
                                class="px-6 py-4 text-sm text-right text-gray-700 dark:text-gray-300"
                            >
                                {{ $run['staff'] }}
                            </td>

                            <td
                                role="cell"
                                data-label="Gross"
                                class="px-6 py-4 text-sm font-semibold text-right text-gray-900 whitespace-nowrap dark:text-white"
                            >
                                {{ $run['gross'] }}
                            </td>

                            <td
                                role="cell"
                                data-label="Net"
                                class="px-6 py-4 text-sm font-medium text-right whitespace-nowrap text-emerald-700 dark:text-emerald-400"
                            >
                                {{ $run['net'] }}
                            </td>

                            <td
                                role="cell"
                                data-label="Actions"
                                class="px-6 py-4 rt-actions"
                            >
                                <a
                                    href="{{ route('payroll.runs.show', $run['id']) }}"
                                    class="{{ $btn['edit'] }}"
                                >
                                    <i class="text-xs fa-solid fa-arrow-right" aria-hidden="true"></i>

                                    <span>
                                        Open
                                        <span class="sr-only">
                                            {{ $run['type'] }} run {{ $run['period'] }}
                                        </span>
                                    </span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr role="row">
                            <td
                                role="cell"
                                colspan="9"
                                class="px-6 py-12 text-center text-gray-500 rt-empty dark:text-gray-400"
                            >
                                <div class="flex flex-col items-center justify-center">
                                    <div class="flex items-center justify-center w-12 h-12 mb-3 text-gray-400 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-500">
                                        <i
                                            class="text-lg fa-solid fa-money-check-dollar"
                                            aria-hidden="true"
                                        ></i>
                                    </div>

                                    <p class="text-gray-600 dark:text-gray-400">
                                        No runs match these filters.
                                    </p>

                                    @if ($canEdit && $hasPayrollAccess)
                                        <p class="mt-1 text-sm">
                                            Use <strong>New Run</strong> to generate a cutoff.
                                        </p>
                                    @elseif ($canEdit && Route::has('owner.subscription.index'))
                                        <p class="mt-1 text-sm">
                                            Choose a payroll plan to generate a cutoff.
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

@if ($canEdit && $hasPayrollAccess)
    {{-- New run modal --}}
    <div
        id="newRunModal"
        class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50"
    >
        <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="newRunTitle"
                class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800"
            >
                <form
                    id="newRunForm"
                    method="POST"
                    action="{{ route('payroll.runs.store') }}"
                >
                    @csrf

                    <div class="flex items-start justify-between gap-3 {{ $cardHead }}">
                        <div>
                            <h2
                                id="newRunTitle"
                                class="text-lg font-semibold text-gray-900 dark:text-white"
                            >
                                New Pay Run
                            </h2>

                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Creates a draft. Nothing is paid until it is approved,
                                finalized and released.
                            </p>
                        </div>

                        <button
                            type="button"
                            onclick="closeModalById('newRunModal')"
                            aria-label="Close dialog"
                            class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                        >
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="px-4 py-6 space-y-4 sm:px-6">
                        <fieldset>
                            <legend class="{{ $labelClass }}">Run type</legend>

                            <div class="grid grid-cols-2 gap-2">
                                @foreach ([
                                    'regular' => ['Regular cutoff', 'fa-calendar-days'],
                                    'thirteenth_month' => ['13th month', 'fa-gift'],
                                ] as $value => [$label, $icon])
                                    <label class="run-type-option flex items-center justify-center gap-2 min-h-[44px] px-3 text-sm font-medium border border-gray-300 cursor-pointer rounded-xl text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                        <input
                                            type="radio"
                                            name="run_type"
                                            value="{{ $value }}"
                                            class="sr-only"
                                            @checked($oldType === $value)
                                        >

                                        <i
                                            class="text-xs fa-solid {{ $icon }}"
                                            aria-hidden="true"
                                        ></i>

                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        {{-- Regular payroll --}}
                        <div
                            data-run-panel="regular"
                            class="grid grid-cols-2 gap-4"
                        >
                            <div>
                                <label for="nr_month" class="{{ $labelClass }}">
                                    Month
                                </label>

                                <select
                                    id="nr_month"
                                    name="month"
                                    class="{{ $inputClass }}"
                                >
                                    @foreach ($months as $month => $name)
                                        <option
                                            value="{{ $month }}"
                                            @selected((int) ($reopen ? old('month', $suggested['month']) : $suggested['month']) === $month)
                                        >
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="nr_year" class="{{ $labelClass }}">
                                    Year
                                </label>

                                <input
                                    type="number"
                                    id="nr_year"
                                    name="year"
                                    min="2000"
                                    max="2100"
                                    required
                                    class="{{ $inputClass }}"
                                    value="{{ $reopen ? old('year', $suggested['year']) : $suggested['year'] }}"
                                >
                            </div>

                            <fieldset class="col-span-2">
                                <legend class="{{ $labelClass }}">Cutoff</legend>

                                <div class="grid grid-cols-2 gap-2">
                                    @foreach ([1 => '1st half', 2 => '2nd half'] as $cutoff => $label)
                                        <label class="run-type-option flex items-center justify-center gap-2 min-h-[44px] px-3 text-sm font-medium border border-gray-300 cursor-pointer rounded-xl text-gray-700 dark:border-gray-600 dark:text-gray-200">
                                            <input
                                                type="radio"
                                                name="cutoff_no"
                                                value="{{ $cutoff }}"
                                                class="sr-only"
                                                @checked((int) ($reopen ? old('cutoff_no', $suggested['cutoff_no']) : $suggested['cutoff_no']) === $cutoff)
                                            >

                                            Cutoff {{ $cutoff }}

                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                ({{ $label }})
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        </div>

                        {{-- 13th month --}}
                        <div
                            data-run-panel="thirteenth_month"
                            class="hidden grid-cols-2 gap-4"
                        >
                            <div>
                                <label for="nr_13_year" class="{{ $labelClass }}">
                                    Year
                                </label>

                                <input
                                    type="number"
                                    id="nr_13_year"
                                    name="year"
                                    data-year-13
                                    min="2000"
                                    max="2100"
                                    class="{{ $inputClass }}"
                                    value="{{ $reopen && $oldType === 'thirteenth_month' ? old('year', $suggested['thirteenth_year']) : $suggested['thirteenth_year'] }}"
                                >
                            </div>

                            <div>
                                <label for="nr_pay_date" class="{{ $labelClass }}">
                                    Pay date
                                </label>

                                <input
                                    type="date"
                                    id="nr_pay_date"
                                    name="pay_date"
                                    class="{{ $inputClass }}"
                                    value="{{ $reopen ? old('pay_date', $suggested['thirteenth_pay_date']) : $suggested['thirteenth_pay_date'] }}"
                                >
                            </div>

                            <p class="col-span-2 text-xs text-gray-500 dark:text-gray-400">
                                Due on or before December 24
                                (PD 851; DOLE Labor Advisory 16-25).
                            </p>
                        </div>

                        {{-- Preview --}}
                        <div
                            id="nr_preview"
                            class="p-4 border border-gray-200 rounded-xl bg-gray-50 dark:bg-gray-900/40 dark:border-gray-700"
                            aria-live="polite"
                        >
                            <p
                                id="nr_loading"
                                class="text-sm text-gray-500 dark:text-gray-400"
                            >
                                Checking dates…
                            </p>

                            <dl
                                id="nr_dates"
                                class="grid hidden grid-cols-3 gap-2 text-sm"
                            >
                                <div>
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">
                                        From
                                    </dt>

                                    <dd
                                        id="nr_from"
                                        class="font-medium text-gray-900 dark:text-white"
                                    ></dd>
                                </div>

                                <div>
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">
                                        To
                                    </dt>

                                    <dd
                                        id="nr_to"
                                        class="font-medium text-gray-900 dark:text-white"
                                    ></dd>
                                </div>

                                <div>
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">
                                        Pay date
                                    </dt>

                                    <dd
                                        id="nr_pay"
                                        class="font-medium text-gray-900 dark:text-white"
                                    ></dd>
                                </div>
                            </dl>

                            <p
                                id="nr_block"
                                class="hidden mt-2 text-sm text-red-700 dark:text-red-300"
                            ></p>

                            <ul
                                id="nr_notes"
                                class="hidden mt-2 space-y-1 text-xs text-amber-800 dark:text-amber-300"
                            ></ul>

                            <a
                                id="nr_existing"
                                href="#"
                                class="hidden mt-2 inline-flex items-center min-h-[44px] text-sm font-semibold text-[#6F5430] underline hover:no-underline dark:text-[#C4A97D]"
                            >
                                Open the existing run
                            </a>

                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Dates come from the pay schedule in Payroll Setup.
                            </p>
                        </div>

                        @if ($eb->any())
                            <ul
                                class="space-y-1 text-sm text-red-600 dark:text-red-400"
                                role="alert"
                            >
                                @foreach ($eb->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <button
                                type="button"
                                onclick="closeModalById('newRunModal')"
                                class="w-full {{ $btn['neutral'] }} sm:w-auto"
                            >
                                Cancel
                            </button>

                            <button
                                type="submit"
                                id="nr_submit"
                                class="w-full {{ $btn['primary'] }} sm:w-auto"
                            >
                                <i class="fa-solid fa-gears" aria-hidden="true"></i>
                                Generate Draft
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

@if ($canEdit && $hasPayrollAccess)
    const MODAL_IDS = ['newRunModal'];

    const FOCUSABLE =
        'a[href]:not(.hidden), button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    let lastFocused = null;

    function topmostOpenModal() {
        for (let index = MODAL_IDS.length - 1; index >= 0; index--) {
            const modal = document.getElementById(MODAL_IDS[index]);

            if (modal && !modal.classList.contains('hidden')) {
                return modal;
            }
        }

        return null;
    }

    function openModal(id, focusSelector) {
        const modal = document.getElementById(id);

        if (! modal) {
            return;
        }

        lastFocused = document.activeElement;
        modal.classList.remove('hidden');

        const target =
            (focusSelector && modal.querySelector(focusSelector))
            || modal.querySelector(FOCUSABLE);

        if (target) {
            target.focus();
        }
    }

    function closeModal(id) {
        const modal = document.getElementById(id);

        if (! modal) {
            return;
        }

        modal.classList.add('hidden');

        if (lastFocused && document.contains(lastFocused)) {
            lastFocused.focus();
        }

        lastFocused = null;
    }

    document.addEventListener('keydown', function (event) {
        const modal = topmostOpenModal();

        if (! modal) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal(modal.id);
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const items = Array.from(
            modal.querySelectorAll(FOCUSABLE)
        ).filter(element => element.offsetParent !== null);

        if (! items.length) {
            return;
        }

        const first = items[0];
        const last = items[items.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (! event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    const form = document.getElementById('newRunForm');
    const routePreview = @json(route('payroll.runs.preview'));
    const element = id => document.getElementById(id);

    let sequence = 0;
    let timer = null;

    function runType() {
        return (
            form.querySelector('input[name="run_type"]:checked') || {}
        ).value || 'regular';
    }

    function syncPanels() {
        const type = runType();

        form.querySelectorAll('[data-run-panel]').forEach(panel => {
            const isActive = panel.dataset.runPanel === type;

            panel.classList.toggle('hidden', ! isActive);
            panel.classList.toggle('grid', isActive);

            panel.querySelectorAll('input, select').forEach(input => {
                input.disabled = ! isActive;
            });
        });

        element('nr_pay_date').required = type === 'thirteenth_month';
        form.querySelector('[data-year-13]').required = type === 'thirteenth_month';
    }

    function params() {
        const type = runType();
        const params = new URLSearchParams({
            run_type: type,
        });

        if (type === 'regular') {
            params.set('year', element('nr_year').value);
            params.set('month', element('nr_month').value);

            params.set(
                'cutoff_no',
                (
                    form.querySelector('input[name="cutoff_no"]:checked') || {}
                ).value || ''
            );
        } else {
            params.set(
                'year',
                form.querySelector('[data-year-13]').value
            );

            params.set(
                'pay_date',
                element('nr_pay_date').value
            );
        }

        return params;
    }

    function show(id, visible) {
        element(id).classList.toggle('hidden', ! visible);
    }

    async function refreshPreview() {
        const currentSequence = ++sequence;

        show('nr_loading', true);
        show('nr_dates', false);
        show('nr_block', false);
        show('nr_notes', false);
        show('nr_existing', false);

        element('nr_submit').disabled = true;

        try {
            const response = await fetch(
                routePreview + '?' + params().toString(),
                {
                    headers: {
                        Accept: 'application/json',
                    },
                }
            );

            const data = await response.json();

            if (currentSequence !== sequence) {
                return;
            }

            show('nr_loading', false);

            if (data.period) {
                element('nr_from').textContent = data.period.start;

                element('nr_to').textContent =
                    data.period.end
                    + (
                        data.period.days
                            ? ' (' + data.period.days + ' days)'
                            : ''
                    );

                element('nr_pay').textContent = data.period.pay;

                show('nr_dates', true);
            }

            if (data.blocking) {
                element('nr_block').textContent = data.blocking;
                show('nr_block', true);
            }

            const notes = element('nr_notes');
            notes.replaceChildren();

            (data.notes || []).forEach(note => {
                const listItem = document.createElement('li');
                listItem.textContent = note;
                notes.appendChild(listItem);
            });

            show('nr_notes', (data.notes || []).length > 0);

            if (data.existing && data.existing.url) {
                element('nr_existing').href = data.existing.url;

                element('nr_existing').textContent =
                    'Open the existing '
                    + data.existing.label.toLowerCase()
                    + ' run';

                show('nr_existing', true);
            }

            element('nr_submit').disabled = ! data.ok;
        } catch (error) {
            if (currentSequence !== sequence) {
                return;
            }

            show('nr_loading', false);

            element('nr_block').textContent =
                'Could not check the dates. You can still generate; '
                + 'the server checks them again.';

            show('nr_block', true);
            element('nr_submit').disabled = false;
        }
    }

    function queuePreview() {
        clearTimeout(timer);
        timer = setTimeout(refreshPreview, 250);
    }

    form.addEventListener('change', function (event) {
        if (event.target.name === 'run_type') {
            syncPanels();
        }

        queuePreview();
    });

    form.addEventListener('input', function (event) {
        if (event.target.type === 'number') {
            queuePreview();
        }
    });

    let submitting = false;

    form.addEventListener('submit', function (event) {
        if (submitting) {
            event.preventDefault();
            return;
        }

        if (! form.checkValidity()) {
            return;
        }

        submitting = true;
        element('nr_submit').disabled = true;
        element('nr_submit').classList.add(
            'opacity-70',
            'cursor-not-allowed'
        );
    });

    window.openNewRunModal = function () {
        syncPanels();
        refreshPreview();

        openModal(
            'newRunModal',
            'input[name="run_type"]:checked'
        );
    };

    window.closeModalById = closeModal;

    syncPanels();

    if (@json($reopen)) {
        window.openNewRunModal();
    }
@endif
}());
</script>

<style>
[x-cloak] {
    display: none !important;
}

.run-type-option:has(input:checked) {
    background-image: linear-gradient(to right, #7A6348, #6F5430);
    border-color: #6F5430;
    color: #ffffff;
}

.run-type-option:has(input:checked) span {
    color: #F6EFE6;
}

.run-type-option:has(input:focus-visible) {
    outline: 2px solid #8B7355;
    outline-offset: 2px;
}

@media (max-width: 767px) {
    .rt,
    .rt tbody,
    .rt tr,
    .rt td {
        display: block;
        width: 100%;
    }

    .rt thead {
        display: none;
    }

    .rt tr {
        padding: 0.75rem 1rem;
    }

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

    .rt td.rt-actions {
        padding-top: 0.75rem !important;
    }

    .rt td.rt-actions::before {
        display: none;
    }

    .rt td.rt-empty {
        padding: 2rem 0 !important;
        text-align: center !important;
    }
}

@media (max-width: 767px) and (prefers-color-scheme: dark) {
    .rt td[data-label]::before {
        color: #9ca3af;
    }
}
</style>
@endsection
