@extends('layouts.app')
@section('content')
<div class="p-6 mx-auto max-w-7xl">
    <x-page-header title="Payroll" subtitle="Generate and manage staff payroll."/>

    {{-- Generate Payroll --}}
    @can('manage payroll')
    <div class="p-6 mt-6 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800">
        <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-white">Generate Payroll</h2>
        <form action="{{ route('hr.payroll.generate') }}" method="POST">
            @csrf
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block mb-1 text-xs font-semibold text-gray-600">Period Start *</label>
                    <input type="date" name="period_start" required
                        class="px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:outline-none"/>
                </div>
                <div>
                    <label class="block mb-1 text-xs font-semibold text-gray-600">Period End *</label>
                    <input type="date" name="period_end" required
                        class="px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:outline-none"/>
                </div>
                <button type="submit" class="px-5 py-2.5 text-sm font-semibold text-white bg-[#8B7355] rounded-xl hover:bg-[#7A6348]">
                    Generate
                </button>
            </div>
        </form>
    </div>
    @endcan

    {{-- Payroll Records --}}
    <div class="mt-6 overflow-hidden bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800">
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
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($payrolls as $payroll)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-900">
                    <td class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-800 dark:text-white">
                            {{ $payroll->staff->user->name ?? 'N/A' }}
                        </p>
                        <p class="text-xs text-gray-500">
                            P: {{ $payroll->days_present }} · A: {{ $payroll->days_absent }} · L: {{ $payroll->days_late }}
                        </p>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $payroll->period_label }}</td>
                    <td class="px-6 py-4 text-sm text-gray-800 dark:text-white">₱{{ number_format($payroll->basic_salary, 2) }}</td>
                    <td class="px-6 py-4 text-sm text-red-600">
                        -₱{{ number_format($payroll->absent_deduction + $payroll->late_deduction, 2) }}
                    </td>
                    <td class="px-6 py-4 text-sm text-green-600">+₱{{ number_format($payroll->commission, 2) }}</td>
                    <td class="px-6 py-4 text-sm font-bold text-gray-800 dark:text-white">
                        ₱{{ number_format($payroll->total_pay, 2) }}
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full
                            {{ $payroll->status === 'finalized' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                            {{ ucfirst($payroll->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        @can('manage payroll')
                        @if($payroll->status === 'draft')
                        <form action="{{ route('hr.payroll.finalize', $payroll) }}" method="POST">
                            @csrf
                            <button type="submit" class="px-3 py-1 text-xs text-white bg-green-600 rounded hover:bg-green-700">
                                Finalize
                            </button>
                        </form>
                        @else
                            <span class="text-xs text-gray-400">Done</span>
                        @endif
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-10 text-sm text-center text-gray-400">
                        No payroll records yet. Generate one above.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
