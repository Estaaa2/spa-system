@extends('layouts.app')
@section('content')
@php
    $user = auth()->user();

    $canViewPayroll = $user?->hasBranchPermission('view payroll') ?? false;
    $canEditPayroll = $user?->hasBranchPermission('edit payroll') ?? false;

    $summary = [
        'total' => $payrolls->count(),
        'draft' => $payrolls->where('status', 'draft')->count(),
        'finalized' => $payrolls->where('status', 'finalized')->count(),
        'total_amount' => $payrolls->sum('total_pay'),
    ];

    $statusClasses = [
        'draft' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
        'finalized' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    ];
@endphp

<div class="p-6 mx-auto space-y-6 max-w-7xl">
    <x-page-header
        title="Payroll"
        subtitle="Generate, review, and finalize payroll records for branch staff."
    />

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Payroll Records</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-gray-900 dark:text-white">{{ $summary['total'] }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">All records</span>
            </div>
        </div>

        <div class="p-5 border shadow-sm bg-yellow-50 border-yellow-200 rounded-2xl dark:bg-yellow-900/10 dark:border-yellow-800">
            <p class="text-xs font-semibold tracking-wide uppercase text-yellow-700 dark:text-yellow-300">Draft Payrolls</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-yellow-900 dark:text-yellow-200">{{ $summary['draft'] }}</h3>
                <span class="text-sm text-yellow-700 dark:text-yellow-300">Pending finalization</span>
            </div>
        </div>

        <div class="p-5 border shadow-sm bg-green-50 border-green-200 rounded-2xl dark:bg-green-900/10 dark:border-green-800">
            <p class="text-xs font-semibold tracking-wide uppercase text-green-700 dark:text-green-300">Finalized</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-green-900 dark:text-green-200">{{ $summary['finalized'] }}</h3>
                <span class="text-sm text-green-700 dark:text-green-300">Completed payrolls</span>
            </div>
        </div>

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Total Payroll Amount</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-gray-900 dark:text-white">₱{{ number_format($summary['total_amount'], 2) }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Across records</span>
            </div>
        </div>
    </div>

    {{-- Generate Payroll --}}
    @if($canEditPayroll)
    <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex items-center gap-3 pb-4 mb-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-[#F6EFE6] dark:bg-gray-700">
                <i class="fa-solid fa-file-invoice-dollar text-[#8B7355]"></i>
            </div>
            <div>
                <h2 class="text-base font-semibold text-gray-800 dark:text-white">Generate Payroll</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Create a payroll draft for a selected period.</p>
            </div>
        </div>

        <form action="{{ route('payroll.generate') }}" method="POST">
            @csrf
            <div class="flex flex-col gap-4 md:flex-row md:items-end">
                <div>
                    <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">Period Start *</label>
                    <input type="date" name="period_start" required
                        class="px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:outline-none focus:ring-1 focus:ring-[#8B7355]/30 dark:bg-gray-700 dark:border-gray-600 dark:text-white"/>
                </div>

                <div>
                    <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">Period End *</label>
                    <input type="date" name="period_end" required
                        class="px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:outline-none focus:ring-1 focus:ring-[#8B7355]/30 dark:bg-gray-700 dark:border-gray-600 dark:text-white"/>
                </div>

                <button type="submit"
                    class="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-xl hover:opacity-90 transition shadow-sm hover:shadow-md active:translate-y-0.5">
                    <i class="mr-1.5 fa-solid fa-gear"></i> Generate Payroll
                </button>
            </div>
        </form>
    </div>
    @endif

    {{-- Payroll Records --}}
    @if($canViewPayroll || $canEditPayroll)
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Payroll Records</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Review payroll calculations, deductions, commissions, and status.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Staff</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Period</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Basic</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Deductions</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Commission</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Total</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse($payrolls as $payroll)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                        <td class="px-6 py-4">
                            <div>
                                <p class="text-sm font-medium text-gray-800 dark:text-white">
                                    {{ $payroll->staff->user->name ?? 'N/A' }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    P: {{ $payroll->days_present }} · A: {{ $payroll->days_absent }} · L: {{ $payroll->days_late }}
                                </p>
                            </div>
                        </td>

                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                            {{ $payroll->period_label }}
                        </td>

                        <td class="px-6 py-4 text-sm text-gray-800 dark:text-white">
                            ₱{{ number_format($payroll->basic_salary, 2) }}
                        </td>

                        <td class="px-6 py-4 text-sm text-red-600 dark:text-red-400">
                            -₱{{ number_format($payroll->absent_deduction + $payroll->late_deduction, 2) }}
                        </td>

                        <td class="px-6 py-4 text-sm text-green-600 dark:text-green-400">
                            +₱{{ number_format($payroll->commission, 2) }}
                        </td>

                        <td class="px-6 py-4 text-sm font-bold text-gray-800 dark:text-white">
                            ₱{{ number_format($payroll->total_pay, 2) }}
                        </td>

                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $statusClasses[$payroll->status] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                                {{ ucfirst($payroll->status) }}
                            </span>
                        </td>

                        <td class="px-6 py-4">
                            @if($canEditPayroll)
                                @if($payroll->status === 'draft')
                                    <form action="{{ route('payroll.finalize', $payroll) }}" method="POST">
                                        @csrf
                                        <button type="submit"
                                            class="px-3 py-1.5 text-xs font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 transition">
                                            Finalize
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-gray-400 dark:text-gray-500">Done</span>
                                @endif
                            @else
                                <span class="text-xs text-gray-400 dark:text-gray-500">View only</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-sm text-center text-gray-400 dark:text-gray-500">
                            No payroll records yet.
                            @if($canEditPayroll)
                                Generate one above.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection