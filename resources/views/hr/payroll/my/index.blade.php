@extends('layouts.app')

@section('title', 'My Payslips')
@section('content')
@php
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';
    $btn = [
        'edit'    => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'neutral' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
    ];
    $inputClass = 'w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl bg-white text-gray-900 '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:border-gray-600 dark:bg-gray-700 dark:text-white';
    $labelClass = 'block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300';
    $card       = 'overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $cardHead   = 'px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700';
    $th         = 'px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400';
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">

    <x-page-header title="My Payslips" subtitle="Your payslips from released pay runs. Print any payslip for your records." />

    @if(! $hasStaff)
        <div class="p-4 border border-blue-200 rounded-2xl bg-blue-50 dark:bg-blue-900/10 dark:border-blue-800">
            <p class="text-sm text-blue-800 dark:text-blue-300">
                <i class="mr-1 fa-solid fa-circle-info" aria-hidden="true"></i>
                Your account is not linked to a staff record in this spa, so there are no payslips to show.
            </p>
        </div>
    @else
        {{-- Year-to-date (billing/setup card treatment) --}}
        <section aria-labelledby="ytdTitle">
            <h2 id="ytdTitle" class="sr-only">Year to date, {{ $year }}</h2>
            <div class="grid grid-cols-1 gap-3 sm:gap-4 sm:grid-cols-3">
                @foreach([['Gross Pay', $ytd['gross'], 'text-gray-900 dark:text-white'], ['Withholding Tax', $ytd['wtax'], 'text-gray-900 dark:text-white'], ['SSS, PhilHealth & Pag-IBIG', $ytd['ee'], 'text-gray-900 dark:text-white']] as [$label, $value, $tone])
                    <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">{{ $label }}</p>
                        <p class="mt-3 text-xl font-semibold break-words sm:text-2xl {{ $tone }}">{{ $value }}</p>
                        <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">{{ $year }} to date · {{ $ytd['count'] }} payslip(s)</span>
                    </div>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Totals count payslips by pay date. Your employer's official year-end certificate may differ after the year-end tax adjustment.</p>
        </section>

        <div class="{{ $card }}">
            <div class="flex flex-col gap-4 {{ $cardHead }} sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Payslips — {{ $year }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Newest first.</p>
                </div>
                <form method="GET" action="{{ route('my-payslips.index') }}" class="flex items-end gap-2" aria-label="Choose year">
                    <div>
                        <label for="f_year" class="{{ $labelClass }}">Year</label>
                        <select id="f_year" name="year" class="{{ $inputClass }} w-28">
                            @foreach($years as $y)<option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>@endforeach
                        </select>
                    </div>
                    <button type="submit" class="{{ $btn['neutral'] }}">Show</button>
                </form>
            </div>

            <div class="md:overflow-x-auto">
                <table role="table" class="rt min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                        <tr role="row">
                            <th role="columnheader" scope="col" class="{{ $th }}">Pay Date</th>
                            <th role="columnheader" scope="col" class="{{ $th }}">Period</th>
                            <th role="columnheader" scope="col" class="{{ $th }}">Type</th>
                            <th role="columnheader" scope="col" class="{{ $th }} text-right">Gross</th>
                            <th role="columnheader" scope="col" class="{{ $th }} text-right">Deductions</th>
                            <th role="columnheader" scope="col" class="{{ $th }} text-right">Net</th>
                            <th role="columnheader" scope="col" class="{{ $th }}"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup" class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @forelse($payslips as $p)
                            <tr role="row" class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td role="cell" data-label="Pay Date" class="px-6 py-4 text-sm font-medium text-gray-900 whitespace-nowrap dark:text-white">{{ $p['pay_date'] }}</td>
                                <td role="cell" data-label="Period" class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap dark:text-gray-300">{{ $p['period'] }}</td>
                                <td role="cell" data-label="Type" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $p['type'] }}@if($p['cutoff'] !== '—') · {{ $p['cutoff'] }}@endif</td>
                                <td role="cell" data-label="Gross" class="px-6 py-4 text-sm text-right text-gray-900 whitespace-nowrap dark:text-white">{{ $p['gross'] }}</td>
                                <td role="cell" data-label="Deductions" class="px-6 py-4 text-sm text-right text-gray-700 whitespace-nowrap dark:text-gray-300">{{ $p['deductions'] }}</td>
                                <td role="cell" data-label="Net" class="px-6 py-4 text-sm font-semibold text-right whitespace-nowrap text-emerald-700 dark:text-emerald-400">{{ $p['net'] }}</td>
                                <td role="cell" data-label="Actions" class="px-6 py-4 rt-actions">
                                    <a href="{{ route('my-payslips.show', $p['id']) }}" class="{{ $btn['edit'] }}">
                                        <i class="text-xs fa-solid fa-file-invoice" aria-hidden="true"></i>
                                        <span>View<span class="sr-only"> payslip paid {{ $p['pay_date'] }}</span></span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr role="row">
                                <td role="cell" colspan="7" class="px-6 py-12 text-center text-gray-500 rt-empty dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="flex items-center justify-center w-12 h-12 mb-3 text-gray-400 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-500">
                                            <i class="text-lg fa-solid fa-file-invoice" aria-hidden="true"></i>
                                        </div>
                                        <p>No released payslips for {{ $year }} yet.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<style>
@media (max-width: 767px) {
    .rt, .rt tbody, .rt tr, .rt td { display: block; width: 100%; }
    .rt thead { display: none; }
    .rt tr { padding: 0.75rem 1rem; }
    .rt td { padding: 0.375rem 0 !important; text-align: left !important; }
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
    .rt td.rt-actions::before { display: none; }
    .rt td.rt-empty { padding: 2rem 0 !important; text-align: center !important; }
}
@media (max-width: 767px) and (prefers-color-scheme: dark) {
    .rt td[data-label]::before { color: #9ca3af; }
}
</style>
@endsection
