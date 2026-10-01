@extends('layouts.app')

@section('title', 'Print Payslips')
@section('content')
@php
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
    $inputClass = 'w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl bg-white text-gray-900 '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:border-gray-600 dark:bg-gray-700 dark:text-white';
    $labelClass = 'block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300';
@endphp

<div class="max-w-4xl p-4 mx-auto space-y-6 sm:p-6">
    <div class="space-y-4 no-print">
        <x-page-header :title="'Print Payslips — ' . $period" :subtitle="count($slips) . ' payslip(s), one per page. Employee copies — employer contributions are not shown.'" :show-clock="false">
            <x-slot name="right">
                <a href="{{ route('payroll.runs.show', $run) }}" class="{{ $btn['edit'] }}">
                    <i class="text-xs fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Run
                </a>
                <button type="button" onclick="window.print()" class="{{ $btn['primary'] }}" @disabled(count($slips) === 0)>
                    <i class="text-xs fa-solid fa-print" aria-hidden="true"></i> Print
                </button>
            </x-slot>
        </x-page-header>

        <form method="GET" action="{{ route('payroll.runs.print', $run) }}" class="flex flex-col gap-3 p-4 bg-white border border-gray-200 shadow-sm sm:flex-row sm:items-end rounded-2xl dark:bg-gray-800 dark:border-gray-700" aria-label="Print options">
            @if(count($branches) > 1)
                <div class="sm:w-56">
                    <label for="p_branch" class="{{ $labelClass }}">Home branch</label>
                    <select id="p_branch" name="branch" class="{{ $inputClass }}">
                        <option value="">All branches</option>
                        @foreach($branches as $id => $name)<option value="{{ $id }}" @selected($branchId === $id)>{{ $name }}</option>@endforeach
                    </select>
                </div>
            @endif
            <label class="flex items-center gap-3 min-h-[44px] text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="receipt" value="1" @checked($receipt)
                       class="w-5 h-5 border-gray-300 rounded text-[#8B7355] focus:ring-[#8B7355] dark:border-gray-600 dark:bg-gray-700">
                Add a “received by” signature line (for pay handed over in cash)
            </label>
            <button type="submit" class="{{ $btn['neutral'] }} sm:ml-auto">Update</button>
        </form>

        @unless($isFinal)
            <div role="note" class="p-4 border border-amber-200 rounded-2xl bg-amber-50 dark:bg-amber-900/10 dark:border-amber-800">
                <p class="text-sm text-amber-800 dark:text-amber-300">
                    <i class="mr-1 fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    This run is not finalized, so every payslip is marked “not final”. Print the copies you hand out after finalizing.
                </p>
            </div>
        @endunless
    </div>

    @if(count($slips) === 0)
        <p class="text-sm text-gray-500 dark:text-gray-400">No payslips to print.</p>
    @else
        <div class="space-y-6 payslip-print-area">
            @foreach($slips as $slip)
                @include('hr.payroll.partials.payslip', ['slip' => $slip, 'receipt' => $receipt])
            @endforeach
        </div>
    @endif
</div>
@endsection
