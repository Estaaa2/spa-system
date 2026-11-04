@extends('layouts.app')

@section('title', 'My Payslip')
@section('content')
@php
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';
    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'edit'    => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
    ];
@endphp

<div class="max-w-4xl p-4 mx-auto space-y-6 sm:p-6">
    <div class="no-print">
        <x-page-header title="My Payslip" :subtitle="$slip['period'] . ' · paid ' . $slip['pay_date']" :show-clock="false">
            <x-slot name="right">
                <a href="{{ route('my-payslips.index') }}" class="{{ $btn['edit'] }}">
                    <i class="text-xs fa-solid fa-arrow-left" aria-hidden="true"></i> My Payslips
                </a>
                <button type="button" onclick="window.print()" class="{{ $btn['primary'] }}">
                    <i class="text-xs fa-solid fa-print" aria-hidden="true"></i> Print
                </button>
            </x-slot>
        </x-page-header>
    </div>

    <div class="payslip-print-area">
        @include('hr.payroll.partials.payslip', ['slip' => $slip])
    </div>

    <p class="text-xs text-gray-500 no-print dark:text-gray-400">
        Something looks wrong? Tell HR or the owner — corrections are made as an adjustment in a later pay run.
    </p>
</div>
@endsection
