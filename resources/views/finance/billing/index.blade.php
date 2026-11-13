@extends('layouts.app')

@section('title', 'Billing & Expenses')
@section('content')
@php
    // Reports & finance revamp: dashboard card recipe, .rt tables, shared date filter,
    // the Staff / Job Openings modal contract and $btn tokens.
    $user = auth()->user();
    // Same permissions as the routes (billing.expense.store / updateStatus), so nobody sees a button that 403s.
    $canFileExpense   = $user?->hasBranchPermission('create billing') ?? false;
    $canReviewExpense = $user?->hasBranchPermission('edit billing') ?? false;

    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';
    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'neutral' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
    ];
    $closeBtn   = 'inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 '
                . 'hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200 '
                . 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]';
    $inputClass = 'block w-full min-h-[44px] px-3 py-2 text-sm text-gray-900 bg-white border border-gray-300 rounded-xl '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white';
    $labelClass = 'block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300';

    $card     = 'overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $cardHead = 'flex flex-wrap items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700';
    $cardH2   = 'text-base font-semibold text-gray-900 dark:text-white';
    $cardSub  = 'mt-0.5 text-xs text-gray-500 dark:text-gray-400';
    $th       = 'px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase sm:px-6 dark:text-gray-400';
    $td       = 'px-4 py-4 text-sm sm:px-6';
    $kpi      = 'p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $kpiLabel = 'min-w-0 text-xs font-semibold tracking-wide text-gray-500 uppercase truncate dark:text-gray-400';
    $kpiIcon  = 'flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-[#8B7355]/10 dark:bg-[#C4A97D]/10';
    $money    = 'mt-3 text-xl font-bold tabular-nums sm:text-2xl xl:text-3xl [overflow-wrap:anywhere]';

    // Booking status chips: same map as the Dashboard.
    $statusClasses = [
        'reserved'  => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'pending'   => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'ongoing'   => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'completed' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
    ];
    $expenseStatus = [
        'pending'   => ['Pending',   'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'],
        'on_review' => ['On Review', 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'],
        'accepted'  => ['Accepted',  'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
        'rejected'  => ['Rejected',  'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
    ];

    $colPct = $billingTotal > 0 ? round(($billingCollected / $billingTotal) * 100) : 0;
    $reopenExpense = $canFileExpense && $errors->hasAny(['title', 'description', 'amount']);
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">

    <x-page-header
        title="Billing & Expenses"
        subtitle="Customer billing records and operational expense requests for the selected period."
    >
        @if ($canFileExpense)
            <x-slot name="right">
                <button type="button" onclick="openExpenseModal()" class="{{ $btn['primary'] }}">
                    <i class="text-xs fa-solid fa-plus" aria-hidden="true"></i>
                    File Expense
                </button>
            </x-slot>
        @endif
    </x-page-header>

    <x-date-range-filter :from="$from" :to="$to">
        @if ($expenseStatusFilter)
            <input type="hidden" name="expense_status" value="{{ $expenseStatusFilter }}">
        @endif
    </x-date-range-filter>

    {{-- ═══ CUSTOMER BILLING ═════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 sm:grid-cols-3">
        <div class="{{ $kpi }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Total Billed</p>
                <div class="{{ $kpiIcon }}"><i class="text-sm fa-solid fa-file-invoice text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i></div>
            </div>
            <p class="{{ $money }} text-gray-900 dark:text-white">₱{{ number_format($billingTotal, 2) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $billingRecords->count() }} {{ \Illuminate\Support\Str::plural('booking', $billingRecords->count()) }}</p>
        </div>

        <div class="p-4 border shadow-sm sm:p-5 bg-emerald-50 border-emerald-200 rounded-2xl dark:bg-emerald-900/10 dark:border-emerald-800">
            <div class="flex items-center justify-between gap-2">
                <p class="min-w-0 text-xs font-semibold tracking-wide uppercase truncate text-emerald-700 dark:text-emerald-400">Collected</p>
                <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                    <i class="text-sm fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i>
                </div>
            </div>
            <p class="{{ $money }} text-emerald-900 dark:text-emerald-200">₱{{ number_format($billingCollected, 2) }}</p>
            <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">{{ $colPct }}% of total billed</p>
        </div>

        @php $hasBalance = $billingBalance > 0; @endphp
        <div class="col-span-2 sm:col-span-1 p-4 border shadow-sm sm:p-5 rounded-2xl {{ $hasBalance ? 'bg-amber-50 border-amber-200 dark:bg-amber-900/10 dark:border-amber-800' : 'bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700' }}">
            <div class="flex items-center justify-between gap-2">
                <p class="min-w-0 text-xs font-semibold tracking-wide uppercase truncate {{ $hasBalance ? 'text-amber-700 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}">Outstanding</p>
                <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl {{ $hasBalance ? 'bg-amber-100 dark:bg-amber-900/30' : 'bg-gray-100 dark:bg-gray-700' }}">
                    <i class="text-sm fa-solid fa-hourglass-half {{ $hasBalance ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}" aria-hidden="true"></i>
                </div>
            </div>
            <p class="{{ $money }} {{ $hasBalance ? 'text-amber-900 dark:text-amber-200' : 'text-gray-900 dark:text-white' }}">₱{{ number_format($billingBalance, 2) }}</p>
            <p class="mt-1 text-xs {{ $hasBalance ? 'text-amber-700 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}">Unpaid balance</p>
        </div>
    </div>

    <section class="{{ $card }}" aria-labelledby="billingTitle">
        <div class="{{ $cardHead }}">
            <div>
                <h2 id="billingTitle" class="{{ $cardH2 }}">Billing Records</h2>
                <p class="{{ $cardSub }}">Active bookings in the period — what each customer owes or has paid</p>
            </div>
            <span class="inline-flex items-center px-3 py-1 text-xs font-semibold text-gray-700 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-300">
                {{ $billingRecords->count() }} {{ \Illuminate\Support\Str::plural('record', $billingRecords->count()) }}
            </span>
        </div>

        <div class="md:overflow-x-auto">
            <table role="table" class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" scope="col" class="{{ $th }}">Date</th>
                        <th role="columnheader" scope="col" class="{{ $th }}">Customer</th>
                        <th role="columnheader" scope="col" class="{{ $th }}">Service / Package</th>
                        <th role="columnheader" scope="col" class="{{ $th }}">Therapist</th>
                        <th role="columnheader" scope="col" class="{{ $th }} text-right">Total</th>
                        <th role="columnheader" scope="col" class="{{ $th }} text-right">Amount Paid</th>
                        <th role="columnheader" scope="col" class="{{ $th }}">Status</th>
                    </tr>
                </thead>
                <tbody role="rowgroup" class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                    @forelse ($billingRecords as $booking)
                        <tr role="row" class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td role="cell" data-label="Date" class="{{ $td }} text-gray-700 whitespace-nowrap dark:text-gray-300">
                                {{ $booking->appointment_date?->format('M d, Y') }}
                            </td>
                            <td role="cell" data-label="Customer" class="{{ $td }}">
                                <p class="font-medium text-gray-900 dark:text-white">{{ $booking->customer_name ?? 'Walk-in' }}</p>
                                <p class="text-xs text-gray-500 break-all md:break-normal dark:text-gray-400">{{ $booking->customer_email ?? '—' }}</p>
                            </td>
                            <td role="cell" data-label="Service / Package" class="{{ $td }}">
                                <p class="text-gray-900 dark:text-white">{{ $booking->treatment_label }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $booking->service_type_label }}</p>
                            </td>
                            <td role="cell" data-label="Therapist" class="{{ $td }} text-gray-700 dark:text-gray-300">
                                {{ $booking->therapist?->name ?? '—' }}
                            </td>
                            <td role="cell" data-label="Total" class="{{ $td }} font-semibold text-right text-gray-900 tabular-nums whitespace-nowrap dark:text-white">
                                ₱{{ number_format($booking->total_amount, 2) }}
                            </td>
                            <td role="cell" data-label="Amount Paid" class="{{ $td }} text-right whitespace-nowrap">
                                <span class="font-medium tabular-nums text-emerald-700 dark:text-emerald-400">₱{{ number_format($booking->amount_paid, 2) }}</span>
                                @if ($booking->balance_amount > 0)
                                    <p class="text-xs tabular-nums text-amber-700 dark:text-amber-400">₱{{ number_format($booking->balance_amount, 2) }} due</p>
                                @endif
                            </td>
                            <td role="cell" data-label="Status" class="{{ $td }}">
                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full {{ $statusClasses[$booking->status] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                                    {{ \App\Models\Booking::statusLabel($booking->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr role="row">
                            <td role="cell" colspan="7" class="px-6 py-12 text-sm text-center text-gray-500 rt-empty dark:text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="mb-3 text-3xl fa-regular fa-folder-open" aria-hidden="true"></i>
                                    <p>No billing records found for the selected period.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($billingRecords->count())
                    <tfoot class="hidden bg-gray-50 md:table-footer-group dark:bg-gray-900">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-xs font-semibold text-right text-gray-600 uppercase sm:px-6 dark:text-gray-300">Totals</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-gray-900 tabular-nums sm:px-6 dark:text-white">₱{{ number_format($billingTotal, 2) }}</td>
                            <td class="px-4 py-3 text-sm font-bold text-right tabular-nums sm:px-6 text-emerald-700 dark:text-emerald-400">₱{{ number_format($billingCollected, 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>

    {{-- ═══ EXPENSES ═════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 sm:grid-cols-3">
        <div class="{{ $kpi }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Total Requested</p>
                <div class="{{ $kpiIcon }}"><i class="text-sm fa-solid fa-receipt text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i></div>
            </div>
            <p class="{{ $money }} text-gray-900 dark:text-white">₱{{ number_format($expenseTotalAmount, 2) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $expenses->count() }} expense {{ \Illuminate\Support\Str::plural('request', $expenses->count()) }}</p>
        </div>

        <div class="{{ $kpi }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Funds Released</p>
                <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                    <i class="text-sm fa-solid fa-hand-holding-dollar text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i>
                </div>
            </div>
            <p class="{{ $money }} text-emerald-700 dark:text-emerald-400">₱{{ number_format($expensesAccepted, 2) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Accepted expenses</p>
        </div>

        @php $needsAction = $expensesPending > 0; @endphp
        <div class="col-span-2 sm:col-span-1 p-4 border shadow-sm sm:p-5 rounded-2xl {{ $needsAction ? 'bg-amber-50 border-amber-200 dark:bg-amber-900/10 dark:border-amber-800' : 'bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700' }}">
            <div class="flex items-center justify-between gap-2">
                <p class="min-w-0 text-xs font-semibold tracking-wide uppercase truncate {{ $needsAction ? 'text-amber-700 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}">Needs Action</p>
                <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl {{ $needsAction ? 'bg-amber-100 dark:bg-amber-900/30' : 'bg-gray-100 dark:bg-gray-700' }}">
                    <i class="text-sm fa-solid fa-circle-exclamation {{ $needsAction ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}" aria-hidden="true"></i>
                </div>
            </div>
            <p class="mt-3 text-3xl font-bold tabular-nums {{ $needsAction ? 'text-amber-900 dark:text-amber-200' : 'text-gray-900 dark:text-white' }}">{{ $expensesPending }}</p>
            <p class="mt-1 text-xs {{ $needsAction ? 'text-amber-700 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}">Pending or on review</p>
        </div>
    </div>

    <section class="{{ $card }}" aria-labelledby="expensesTitle">
        <div class="{{ $cardHead }}">
            <div>
                <h2 id="expensesTitle" class="{{ $cardH2 }}">Expense Requests</h2>
                <p class="{{ $cardSub }}">Operational expense requests and their approval status</p>
            </div>
            {{-- Status filter: changes apply at once and keep the date range. --}}
            <form method="GET" action="{{ url()->current() }}" class="w-full sm:w-52" role="search" aria-label="Filter expenses">
                <input type="hidden" name="from" value="{{ $from->format('Y-m-d') }}">
                <input type="hidden" name="to" value="{{ $to->format('Y-m-d') }}">
                <label for="expense_status_filter" class="sr-only">Expense status</label>
                <select id="expense_status_filter" name="expense_status" onchange="this.form.submit()" class="{{ $inputClass }}">
                    <option value="">All statuses</option>
                    @foreach ($expenseStatus as $value => [$label])
                        <option value="{{ $value }}" @selected($expenseStatusFilter === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="mt-2 {{ $btn['neutral'] }}">Apply</button></noscript>
            </form>
        </div>

        <div class="md:overflow-x-auto">
            <table role="table" class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" scope="col" class="{{ $th }}">Date Filed</th>
                        <th role="columnheader" scope="col" class="{{ $th }}">Expense</th>
                        <th role="columnheader" scope="col" class="{{ $th }}">Filed By</th>
                        <th role="columnheader" scope="col" class="{{ $th }} text-right">Amount</th>
                        <th role="columnheader" scope="col" class="{{ $th }}">Status</th>
                        @if ($canReviewExpense)
                            <th role="columnheader" scope="col" class="{{ $th }} text-right"><span class="sr-only">Actions</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody role="rowgroup" class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                    @forelse ($expenses as $expense)
                        @php
                            [$expLabel, $expClass] = $expenseStatus[$expense->status] ?? [ucfirst(str_replace('_', ' ', $expense->status)), 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'];
                            $open = in_array($expense->status, ['pending', 'on_review'], true);
                        @endphp
                        <tr role="row" class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td role="cell" data-label="Date Filed" class="{{ $td }} text-gray-700 whitespace-nowrap dark:text-gray-300">
                                {{ $expense->created_at->format('M d, Y') }}
                            </td>
                            <td role="cell" data-label="Expense" class="{{ $td }} md:max-w-sm">
                                <p class="font-medium text-gray-900 dark:text-white">{{ $expense->title }}</p>
                                @if ($expense->description)
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($expense->description, 120) }}</p>
                                @endif
                            </td>
                            <td role="cell" data-label="Filed By" class="{{ $td }} text-gray-700 dark:text-gray-300">
                                {{ $expense->requester?->name ?? '—' }}
                            </td>
                            <td role="cell" data-label="Amount" class="{{ $td }} font-semibold text-right text-gray-900 tabular-nums whitespace-nowrap dark:text-white">
                                ₱{{ number_format($expense->amount, 2) }}
                            </td>
                            <td role="cell" data-label="Status" class="{{ $td }}">
                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full {{ $expClass }}">{{ $expLabel }}</span>
                                @if ($expense->review_notes)
                                    <p class="max-w-xs mt-1 text-xs text-gray-500 dark:text-gray-400">“{{ \Illuminate\Support\Str::limit($expense->review_notes, 80) }}”</p>
                                @endif
                            </td>
                            @if ($canReviewExpense)
                                <td role="cell" class="{{ $td }} text-right rt-actions">
                                    @if ($open)
                                        <button type="button"
                                                onclick="openReviewModal(this)"
                                                data-url="{{ route('billing.expense.updateStatus', $expense) }}"
                                                data-title="{{ $expense->title }}"
                                                data-amount="₱{{ number_format($expense->amount, 2) }}"
                                                data-status="{{ $expense->status }}"
                                                class="w-full md:w-auto {{ $btn['primary'] }}">
                                            <i class="text-xs fa-solid fa-clipboard-check" aria-hidden="true"></i>
                                            Review<span class="sr-only"> {{ $expense->title }}</span>
                                        </button>
                                    @elseif ($expense->status === 'accepted')
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                                            <i class="fa-solid fa-check" aria-hidden="true"></i> Funds released
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-500 dark:text-gray-400">No action</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr role="row">
                            <td role="cell" colspan="{{ $canReviewExpense ? 6 : 5 }}" class="px-6 py-12 text-sm text-center text-gray-500 rt-empty dark:text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="mb-3 text-3xl fa-solid fa-receipt" aria-hidden="true"></i>
                                    <p>No expense requests found for the selected period.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

</div>

@if ($canFileExpense)
{{-- ── File expense modal ──────────────────────────────────────────────── --}}
<div id="expenseModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="expenseModalTitle"
             class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div>
                    <h2 id="expenseModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">File Expense Request</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Submit an operational expense for review and approval.</p>
                </div>
                <button type="button" onclick="closeExpenseModal()" aria-label="Close dialog" class="{{ $closeBtn }}">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('billing.expense.store') }}" data-once>
                @csrf
                <div class="px-4 py-6 space-y-4 sm:px-6">
                    <x-required-note />

                    @if ($reopenExpense)
                        <div class="p-3 text-sm text-red-700 border border-red-200 bg-red-50 rounded-xl dark:border-red-800 dark:bg-red-900/20 dark:text-red-300" role="alert">
                            Please fix the highlighted fields.
                        </div>
                    @endif

                    <div>
                        <label for="expense_title" class="{{ $labelClass }}">Expense request / title <x-required-mark /></label>
                        <input type="text" name="title" id="expense_title" required maxlength="255" value="{{ old('title') }}"
                               placeholder="e.g. Office supplies, equipment maintenance"
                               class="{{ $inputClass }}" @error('title') aria-invalid="true" aria-describedby="expense_title_error" @enderror>
                        @error('title')<p id="expense_title_error" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="expense_description" class="{{ $labelClass }}">Description</label>
                        <textarea name="description" id="expense_description" rows="3" maxlength="{{ config('limits.long_text') }}"
                                  placeholder="Describe what the expense is for"
                                  class="{{ $inputClass }} resize-y" @error('description') aria-invalid="true" aria-describedby="expense_description_error" @enderror>{{ old('description') }}</textarea>
                        @error('description')<p id="expense_description_error" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="expense_amount" class="{{ $labelClass }}">Amount (₱) <x-required-mark /></label>
                        <input type="number" name="amount" id="expense_amount" step="0.01" min="0.01" max="{{ config('limits.money') }}" required
                               inputmode="decimal" placeholder="e.g. 1500.00" value="{{ old('amount') }}"
                               class="{{ $inputClass }}" @error('amount') aria-invalid="true" aria-describedby="expense_amount_error" @enderror>
                        @error('amount')<p id="expense_amount_error" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeExpenseModal()" class="w-full {{ $btn['neutral'] }} sm:w-auto">Cancel</button>
                        <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto">
                            <i class="text-xs fa-solid fa-paper-plane" aria-hidden="true"></i>
                            Submit Request
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if ($canReviewExpense)
{{-- ── Review expense modal ────────────────────────────────────────────── --}}
<div id="reviewModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="reviewModalTitle"
             class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div>
                    <h2 id="reviewModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Review Expense</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Accept to release funds, put it on review, or reject it.</p>
                </div>
                <button type="button" onclick="closeReviewModal()" aria-label="Close dialog" class="{{ $closeBtn }}">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <form id="reviewForm" method="POST" data-once>
                @csrf
                @method('PATCH')
                <div class="px-4 py-6 space-y-4 sm:px-6">
                    <div class="flex items-start justify-between gap-3 p-4 border border-gray-200 bg-gray-50 rounded-xl dark:border-gray-700 dark:bg-gray-900/40">
                        <p class="min-w-0 text-sm font-semibold text-gray-900 dark:text-white" id="review_title"></p>
                        <p class="text-sm font-bold tabular-nums shrink-0 text-[#6F5430] dark:text-[#C4A97D]" id="review_amount"></p>
                    </div>

                    <div>
                        <label for="review_status" class="{{ $labelClass }}">Decision <x-required-mark /></label>
                        <select name="status" id="review_status" class="{{ $inputClass }}" onchange="syncReviewNotes()">
                            <option value="on_review">Mark as under review</option>
                            <option value="accepted">Accept — release funds</option>
                            <option value="rejected">Reject</option>
                        </select>
                    </div>

                    <div>
                        <label for="review_notes" class="{{ $labelClass }}">
                            Notes
                            <span id="notesRequired" class="hidden"><x-required-mark /></span>
                            <span id="notesOptional" class="font-normal text-gray-500 dark:text-gray-400">(optional)</span>
                        </label>
                        <textarea name="review_notes" id="review_notes" rows="3" maxlength="1000"
                                  placeholder="Add a note for the requester"
                                  aria-describedby="review_notes_help"
                                  class="{{ $inputClass }} resize-y"></textarea>
                        <p id="review_notes_help" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            The person who filed the request will see this note.
                        </p>
                    </div>
                </div>

                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeReviewModal()" class="w-full {{ $btn['neutral'] }} sm:w-auto">Cancel</button>
                        <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto">
                            <i class="text-xs fa-solid fa-floppy-disk" aria-hidden="true"></i>
                            Save Decision
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
    // ── Shared modal behaviour (same as staff/index and job-openings) ─────────
    const MODAL_IDS = ['expenseModal', 'reviewModal'];
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

    // One submit per form, so a double tap can't file the same expense twice.
    document.querySelectorAll('form[data-once]').forEach(form => form.addEventListener('submit', () => {
        form.querySelectorAll('button[type="submit"]').forEach(b => { b.disabled = true; b.setAttribute('aria-busy', 'true'); });
    }));

    // ── File expense ─────────────────────────────────────────────────────────
    window.openExpenseModal  = () => openModal('expenseModal', '#expense_title');
    window.closeExpenseModal = () => closeModal('expenseModal');

    // ── Review expense ───────────────────────────────────────────────────────
    window.syncReviewNotes = function () {
        const reject = document.getElementById('review_status')?.value === 'rejected';
        document.getElementById('notesRequired')?.classList.toggle('hidden', !reject);
        document.getElementById('notesOptional')?.classList.toggle('hidden', reject);
        const notes = document.getElementById('review_notes');
        if (notes) notes.required = reject;
    };
    window.openReviewModal = function (btn) {
        const form = document.getElementById('reviewForm');
        if (!form) return;
        form.action = btn.dataset.url;
        document.getElementById('review_title').textContent  = btn.dataset.title;
        document.getElementById('review_amount').textContent = btn.dataset.amount;
        // "Under review" is already set on on_review expenses, so suggest Accept for those.
        document.getElementById('review_status').value = btn.dataset.status === 'on_review' ? 'accepted' : 'on_review';
        document.getElementById('review_notes').value  = '';
        syncReviewNotes();
        openModal('reviewModal', '#review_status');
    };
    window.closeReviewModal = () => closeModal('reviewModal');

    @if ($reopenExpense)
        openExpenseModal();
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
