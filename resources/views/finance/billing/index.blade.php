@extends('layouts.app')

@section('content')
<div class="p-6 mx-auto space-y-6 max-w-7xl">

    <x-page-header
        title="Billing & Expenses"
        subtitle="Customer billing records and operational expense requests for the selected period."
    />

    {{-- ── Success Flash ───────────────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="flex items-center gap-3 px-4 py-3 text-sm text-emerald-800 border border-emerald-200 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-300">
            <span class="font-semibold">✓</span> {{ session('success') }}
        </div>
    @endif

    {{-- ── Date Range Filter ──────────────────────────────────────────────── --}}
    <form method="GET" id="billingFilterForm">
        <input type="hidden" name="from" id="filterFrom" value="{{ $from->format('Y-m-d') }}">
        <input type="hidden" name="to"   id="filterTo"   value="{{ $to->format('Y-m-d') }}">
        @if($expenseStatusFilter)
            <input type="hidden" name="expense_status" value="{{ $expenseStatusFilter }}">
        @endif

        <div class="p-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-wrap items-center gap-2">
                @php
                    $currentFrom = $from->format('Y-m-d');
                    $currentTo   = $to->format('Y-m-d');
                    $presets = [
                        ['Today',         now()->format('Y-m-d'),                              now()->format('Y-m-d')],
                        ['Past 7 Days',   now()->subDays(6)->format('Y-m-d'),                  now()->format('Y-m-d')],
                        ['Past 30 Days',  now()->subDays(29)->format('Y-m-d'),                 now()->format('Y-m-d')],
                        ['This Month',    now()->startOfMonth()->format('Y-m-d'),               now()->format('Y-m-d')],
                        ['Last Month',    now()->subMonth()->startOfMonth()->format('Y-m-d'),   now()->subMonth()->endOfMonth()->format('Y-m-d')],
                        ['Past 3 Months', now()->subMonths(3)->format('Y-m-d'),                now()->format('Y-m-d')],
                        ['This Year',     now()->startOfYear()->format('Y-m-d'),               now()->format('Y-m-d')],
                    ];
                @endphp

                @foreach($presets as [$label, $pFrom, $pTo])
                    @php $active = $currentFrom === $pFrom && $currentTo === $pTo; @endphp
                    <button type="button"
                        onclick="setPreset('{{ $pFrom }}','{{ $pTo }}')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors
                            {{ $active
                                ? 'bg-[#8B7355] text-white'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}">
                        {{ $label }}
                    </button>
                @endforeach

                <span class="mx-1 text-gray-300 dark:text-gray-600">|</span>

                <div class="flex items-center gap-1.5">
                    <input type="date" id="customFrom" value="{{ $currentFrom }}"
                        class="px-2 py-1.5 text-xs border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        onchange="syncCustom()">
                    <span class="text-xs text-gray-400">to</span>
                    <input type="date" id="customTo" value="{{ $currentTo }}"
                        class="px-2 py-1.5 text-xs border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        onchange="syncCustom()">
                    <button type="submit"
                        class="px-3 py-1.5 text-xs font-medium text-white bg-[#8B7355] rounded-lg hover:opacity-90">
                        Apply
                    </button>
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                {{ $from->format('M d, Y') }} – {{ $to->format('M d, Y') }}
            </p>
        </div>
    </form>

    {{-- ════════════════════════════════════════════════════════════════════ --}}
    {{-- BILLING SECTION                                                      --}}
    {{-- ════════════════════════════════════════════════════════════════════ --}}

    {{-- Billing Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Total Billed</p>
            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">₱{{ number_format($billingTotal, 2) }}</p>
            <p class="mt-1 text-xs text-gray-400">{{ $billingRecords->count() }} bookings</p>
        </div>
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Collected</p>
            <p class="mt-2 text-2xl font-bold text-emerald-700 dark:text-emerald-400">₱{{ number_format($billingCollected, 2) }}</p>
            @php $colPct = $billingTotal > 0 ? round(($billingCollected / $billingTotal) * 100) : 0; @endphp
            <p class="mt-1 text-xs text-gray-400">{{ $colPct }}% of total billed</p>
        </div>
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Outstanding</p>
            <p class="mt-2 text-2xl font-bold {{ $billingBalance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">
                ₱{{ number_format($billingBalance, 2) }}
            </p>
            <p class="mt-1 text-xs text-gray-400">Unpaid balance</p>
        </div>
    </div>

    {{-- Billing Table --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Billing Records</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                All active booking transactions — customer email, service, and payment status.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Customer Email</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Service / Package Availed</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Therapist</th>
                        <th class="px-6 py-3 text-xs font-medium text-right text-gray-500 uppercase">Total</th>
                        <th class="px-6 py-3 text-xs font-medium text-right text-gray-500 uppercase">Amount Paid</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                    @php
                        $statusClasses = [
                            'reserved'  => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
                            'pending'   => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                            'ongoing'   => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                            'completed' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                        ];
                    @endphp
                    @forelse($billingRecords as $booking)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                {{ $booking->appointment_date?->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $booking->customer_name ?? 'Walk-in' }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $booking->customer_email ?? '—' }}
                                </p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm text-gray-900 dark:text-white">{{ $booking->treatment_label }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $booking->service_type_label }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ $booking->therapist?->name ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold text-right text-gray-900 dark:text-white whitespace-nowrap">
                                ₱{{ number_format($booking->total_amount, 2) }}
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <span class="text-sm font-medium text-emerald-700 dark:text-emerald-400">
                                    ₱{{ number_format($booking->amount_paid, 2) }}
                                </span>
                                @if($booking->balance_amount > 0)
                                    <p class="text-xs text-amber-600 dark:text-amber-400">
                                        ₱{{ number_format($booking->balance_amount, 2) }} due
                                    </p>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full {{ $statusClasses[$booking->status] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ ucfirst($booking->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-10 text-sm text-center text-gray-500 dark:text-gray-400">
                                No billing records found for the selected period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($billingRecords->count())
                <tfoot class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <td colspan="4" class="px-6 py-3 text-xs font-semibold text-right text-gray-600 uppercase dark:text-gray-300">Totals</td>
                        <td class="px-6 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">₱{{ number_format($billingTotal, 2) }}</td>
                        <td class="px-6 py-3 text-sm font-bold text-right text-emerald-700 dark:text-emerald-400">₱{{ number_format($billingCollected, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════ --}}
    {{-- EXPENSES SECTION                                                     --}}
    {{-- ════════════════════════════════════════════════════════════════════ --}}

    {{-- Expense Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Total Requested</p>
            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">₱{{ number_format($expenseTotalAmount, 2) }}</p>
            <p class="mt-1 text-xs text-gray-400">{{ $expenses->count() }} expense requests</p>
        </div>
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Funds Released</p>
            <p class="mt-2 text-2xl font-bold text-emerald-700 dark:text-emerald-400">₱{{ number_format($expensesAccepted, 2) }}</p>
            <p class="mt-1 text-xs text-gray-400">Accepted expenses</p>
        </div>
        <div class="p-5 {{ $expensesPending > 0 ? 'bg-amber-50 border-amber-200 dark:bg-amber-900/10 dark:border-amber-800' : 'bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700' }} border shadow-sm rounded-2xl">
            <p class="text-xs font-semibold tracking-wide uppercase {{ $expensesPending > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500 dark:text-gray-400' }}">
                Needs Action
            </p>
            <p class="mt-2 text-2xl font-bold {{ $expensesPending > 0 ? 'text-amber-900 dark:text-amber-200' : 'text-gray-900 dark:text-white' }}">
                {{ $expensesPending }}
            </p>
            <p class="mt-1 text-xs {{ $expensesPending > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400' }}">
                Pending / under review
            </p>
        </div>
    </div>

    {{-- Expense Table Header + File Button --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Expense Requests</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Operational expense requests and their approval status.</p>
            </div>
            <div class="flex items-center gap-2">
                {{-- Status filter --}}
                <form method="GET" class="flex items-center gap-1">
                    <input type="hidden" name="from" value="{{ $from->format('Y-m-d') }}">
                    <input type="hidden" name="to"   value="{{ $to->format('Y-m-d') }}">
                    <select name="expense_status" onchange="this.form.submit()"
                        class="px-3 py-1.5 text-xs border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="">All Statuses</option>
                        <option value="pending"   {{ $expenseStatusFilter === 'pending'   ? 'selected' : '' }}>Pending</option>
                        <option value="on_review" {{ $expenseStatusFilter === 'on_review' ? 'selected' : '' }}>On Review</option>
                        <option value="accepted"  {{ $expenseStatusFilter === 'accepted'  ? 'selected' : '' }}>Accepted</option>
                        <option value="rejected"  {{ $expenseStatusFilter === 'rejected'  ? 'selected' : '' }}>Rejected</option>
                    </select>
                </form>

                <button type="button" onclick="openExpenseModal()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-xl hover:bg-[#7A6348]">
                    + File Expense
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Date Filed</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Expense Request</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Description</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Filed By</th>
                        <th class="px-6 py-3 text-xs font-medium text-right text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-xs font-medium text-center text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                    @php
                        $expStatusClasses = [
                            'pending'   => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                            'on_review' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
                            'accepted'  => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                            'rejected'  => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                        ];
                        $expStatusLabels = [
                            'pending'   => 'Pending',
                            'on_review' => 'On Review',
                            'accepted'  => 'Accepted',
                            'rejected'  => 'Rejected',
                        ];
                    @endphp

                    @forelse($expenses as $expense)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40
                            {{ $expense->status === 'pending' ? 'bg-amber-50/40 dark:bg-amber-900/5' : '' }}">
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                {{ $expense->created_at->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $expense->title }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 max-w-xs">
                                {{ $expense->description ? \Str::limit($expense->description, 80) : '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ $expense->requester?->name ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold text-right text-gray-900 dark:text-white whitespace-nowrap">
                                ₱{{ number_format($expense->amount, 2) }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full {{ $expStatusClasses[$expense->status] ?? '' }}">
                                    {{ $expStatusLabels[$expense->status] ?? ucfirst($expense->status) }}
                                </span>
                                @if($expense->review_notes)
                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500 max-w-xs">
                                        "{{ \Str::limit($expense->review_notes, 60) }}"
                                    </p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if(in_array($expense->status, ['pending', 'on_review']))
                                    <button type="button"
                                        onclick="openReviewModal({{ $expense->id }}, '{{ addslashes($expense->title) }}', {{ $expense->amount }})"
                                        class="px-3 py-1.5 text-xs font-medium text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348]">
                                        Review
                                    </button>
                                @elseif($expense->status === 'accepted')
                                    <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Funds Released</span>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-10 text-sm text-center text-gray-500 dark:text-gray-400">
                                No expense requests found for the selected period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- ── FILE EXPENSE MODAL ───────────────────────────────────────────────── --}}
<div id="expenseModal" class="fixed inset-0 z-50 hidden p-4 bg-black/50">
    <div class="w-full max-w-lg mx-auto mt-20 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">File Expense Request</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Submit an operational expense for review and approval.</p>
            </div>
            <button type="button" onclick="closeExpenseModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">✕</button>
        </div>

        <form method="POST" action="{{ route('billing.expense.store') }}" class="px-6 py-6 space-y-4">
            @csrf
            <div>
                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Expense Request / Title</label>
                <input type="text" name="title" required
                    placeholder="e.g. Office supplies, Equipment maintenance..."
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            </div>

            <div>
                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                <textarea name="description" rows="3"
                    placeholder="Describe what the expense is for..."
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white"></textarea>
            </div>

            <div>
                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Amount (₱)</label>
                <input type="number" name="amount" step="0.01" min="0.01" required
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeExpenseModal()"
                    class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 text-sm font-medium text-white rounded-xl bg-[#8B7355] hover:bg-[#7A6348]">
                    Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── REVIEW EXPENSE MODAL ─────────────────────────────────────────────── --}}
<div id="reviewModal" class="fixed inset-0 z-50 hidden p-4 bg-black/50">
    <div class="w-full max-w-lg mx-auto mt-20 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Review Expense</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Accept to release funds, or reject / put on review.</p>
            </div>
            <button type="button" onclick="closeReviewModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">✕</button>
        </div>

        <form id="reviewForm" method="POST" class="px-6 py-6 space-y-4">
            @csrf
            @method('PATCH')

            <div class="p-4 bg-gray-50 rounded-xl dark:bg-gray-900/40">
                <p class="text-sm font-semibold text-gray-900 dark:text-white" id="review_title"></p>
                <p class="mt-1 text-sm font-bold text-[#8B7355]" id="review_amount"></p>
            </div>

            <div>
                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Decision</label>
                <select name="status" id="review_status"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    onchange="toggleNotesField()">
                    <option value="on_review">Mark as Under Review</option>
                    <option value="accepted">Accept — Release Funds</option>
                    <option value="rejected">Reject</option>
                </select>
            </div>

            <div id="notesWrapper">
                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                    Notes <span id="notesRequired" class="text-red-500 hidden">*</span>
                    <span id="notesOptional" class="font-normal text-gray-400">(optional)</span>
                </label>
                <textarea name="review_notes" id="review_notes" rows="3"
                    placeholder="Add a note for the requester..."
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white"></textarea>
                <p class="mt-1 text-xs text-gray-400">
                    This note will be visible to the person who filed the request.
                </p>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeReviewModal()"
                    class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 text-sm font-medium text-white rounded-xl bg-[#8B7355] hover:bg-[#7A6348]">
                    Save Decision
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // ── Date filter ──────────────────────────────────────────────────────────
    function setPreset(from, to) {
        document.getElementById('filterFrom').value = from;
        document.getElementById('filterTo').value   = to;
        document.getElementById('customFrom').value = from;
        document.getElementById('customTo').value   = to;
        document.getElementById('billingFilterForm').submit();
    }
    function syncCustom() {
        document.getElementById('filterFrom').value = document.getElementById('customFrom').value;
        document.getElementById('filterTo').value   = document.getElementById('customTo').value;
    }

    // ── File expense modal ───────────────────────────────────────────────────
    function openExpenseModal() {
        document.getElementById('expenseModal').classList.remove('hidden');
    }
    function closeExpenseModal() {
        document.getElementById('expenseModal').classList.add('hidden');
    }

    // ── Review expense modal ─────────────────────────────────────────────────
    function openReviewModal(id, title, amount) {
        document.getElementById('review_title').textContent  = title;
        document.getElementById('review_amount').textContent = '₱' + Number(amount).toFixed(2);
        document.getElementById('reviewForm').action = '/billing/expenses/' + id + '/status';
        document.getElementById('review_status').value = 'on_review';
        document.getElementById('review_notes').value  = '';
        toggleNotesField();
        document.getElementById('reviewModal').classList.remove('hidden');
    }
    function closeReviewModal() {
        document.getElementById('reviewModal').classList.add('hidden');
    }

    // Show/hide "required" label on notes depending on decision
    function toggleNotesField() {
        const status   = document.getElementById('review_status').value;
        const required = document.getElementById('notesRequired');
        const optional = document.getElementById('notesOptional');
        const isReject = status === 'rejected';
        required.classList.toggle('hidden', !isReject);
        optional.classList.toggle('hidden', isReject);
    }
</script>
@endsection