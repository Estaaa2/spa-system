@extends('layouts.app')

@section('title', 'Vendor Bills')

@section('content')
@php
    $user = Auth::user();

    $canCreateVendorBills = $user->hasBranchPermission('create vendor bills');
    $canEditVendorBills = $user->hasBranchPermission('edit vendor bills');
    $canMatchVendorBills = $user->hasBranchPermission('match vendor bills');

    $showVendorBillActions =
        $canEditVendorBills ||
        $canMatchVendorBills;

    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348] disabled:cursor-not-allowed disabled:opacity-50',
        'secondary' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700',
        'rowPrimary' => $btnBase . ' bg-amber-700 text-white hover:bg-amber-800 disabled:cursor-not-allowed disabled:opacity-50',
    ];

    $inputBase = 'w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white';

    $modalClose = 'inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]';

    $modalFooter = 'flex flex-col-reverse gap-2 px-4 py-4 border-t border-gray-200 sm:flex-row sm:justify-end sm:px-6 dark:border-gray-700';

    $statusMeta = [
        'draft' => [
            'label' => 'Draft',
            'class' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        ],
        'pending_match' => [
            'label' => 'Pending Match',
            'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        ],
        'matched' => [
            'label' => 'Matched',
            'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        ],
        'discrepancy' => [
            'label' => 'Discrepancy',
            'class' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        ],
        'approved' => [
            'label' => 'Approved',
            'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        ],
        'paid' => [
            'label' => 'Paid',
            'class' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
        ],
        'cancelled' => [
            'label' => 'Cancelled',
            'class' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        ],
    ];

    $poPayload = $billablePurchaseOrders->mapWithKeys(function ($purchaseOrder) {
        return [
            (string) $purchaseOrder->id => [
                'id' => $purchaseOrder->id,
                'supplier' => $purchaseOrder->supplier?->name ?? 'Unknown Supplier',
                'status' => $purchaseOrder->status,
                'items' => $purchaseOrder->items->map(fn($item) => [
                    'id' => $item->id,
                    'product' => $item->product?->name ?? 'Unknown Product',
                    'quantity' => (float) $item->quantity,
                    'unit' => $item->unit,
                    'unit_cost' => $item->unit_cost !== null
                        ? (float) $item->unit_cost
                        : null,
                ])->values(),
            ],
        ];
    });

    $matchPayloads = $vendorBills->getCollection()->mapWithKeys(function ($vendorBill) use ($statusMeta) {
        $meta = $statusMeta[$vendorBill->status] ?? [
            'label' => ucfirst(str_replace('_', ' ', $vendorBill->status)),
            'class' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
        ];

        return [
            (string) $vendorBill->id => [
                'id' => $vendorBill->id,
                'invoice' => $vendorBill->invoice_number,
                'status' => $vendorBill->status,
                'status_label' => $meta['label'],
                'status_class' => $meta['class'],
                'matched_at' => $vendorBill->matched_at?->format('M d, Y h:i A'),
                'details' => $vendorBill->match_details,
            ],
        ];
    });

    $editBillPayloads = $vendorBills->getCollection()->mapWithKeys(function ($vendorBill) {
        return [
            (string) $vendorBill->id => [
                'id' => $vendorBill->id,
                'update_url' => route('vendor-bills.update', $vendorBill),
                'invoice_number' => $vendorBill->invoice_number,
                'invoice_date' => $vendorBill->invoice_date?->format('Y-m-d'),
                'due_date' => $vendorBill->due_date?->format('Y-m-d'),
                'notes' => $vendorBill->notes,
                'supplier' => $vendorBill->supplier?->name ?? 'Unknown Supplier',
                'purchase_order_id' => $vendorBill->purchase_order_id,
                'items' => $vendorBill->items->map(fn($item) => [
                    'id' => $item->id,
                    'product' => $item->product?->name ?? 'Unknown Product',
                    'unit' => $item->unit,
                    'billed_quantity' => (float) $item->billed_quantity,
                    'unit_cost' => (float) $item->unit_cost,
                ])->values(),
            ],
        ];
    });
@endphp

<div
    class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl"
    x-data="vendorBillPage(
    @js($poPayload),
    @js($matchPayloads),
    @js($editBillPayloads),
    {{ $errors->createVendorBill->any() ? 'true' : 'false' }}
)"
>
    <x-page-header
        title="Vendor Bills"
        subtitle="Record supplier invoices and prepare them for 3-way matching."
    >
        @if($canCreateVendorBills)
            <x-slot name="right">
                <button
                    type="button"
                    @click="openCreate()"
                    class="{{ $btn['primary'] }}"
                    @disabled($billablePurchaseOrders->isEmpty())
                >
                    <i class="fa-solid fa-file-invoice" aria-hidden="true"></i>
                    Create Vendor Bill
                </button>
            </x-slot>
        @endif
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Vendor Bills
            </p>

            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <p class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                    {{ $vendorBills->total() }}
                </p>

                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                    Recorded
                </span>
            </div>
        </div>

        <div class="p-4 border shadow-sm sm:p-5 bg-amber-50 border-amber-200 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800">
            <p class="text-xs font-semibold tracking-wide uppercase text-amber-700 dark:text-amber-300">
                Pending Match
            </p>

            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <p class="text-2xl font-semibold sm:text-3xl text-amber-900 dark:text-amber-200">
                    {{ $vendorBills->getCollection()->where('status', 'pending_match')->count() }}
                </p>

                <span class="text-xs sm:text-sm text-amber-700 dark:text-amber-300">
                    Needs matching
                </span>
            </div>
        </div>

        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Discrepancies
            </p>

            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <p class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                    {{ $vendorBills->getCollection()->where('status', 'discrepancy')->count() }}
                </p>

                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                    Needs review
                </span>
            </div>
        </div>
    </div>

    @if($billablePurchaseOrders->isEmpty())
        <div class="p-4 border shadow-sm sm:p-5 border-amber-200 bg-amber-50 rounded-2xl dark:border-amber-800 dark:bg-amber-900/10">
            <div class="flex items-start gap-3">
                <div class="flex items-center justify-center w-10 h-10 shrink-0 rounded-xl bg-amber-100 dark:bg-amber-900/30">
                    <i class="text-amber-700 fa-solid fa-circle-info dark:text-amber-300" aria-hidden="true"></i>
                </div>

                <div>
                    <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                        No purchase orders available for billing
                    </p>

                    <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                        An issued or received purchase order is required before recording a supplier invoice.
                    </p>
                </div>
            </div>
        </div>
    @endif

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-col gap-2 px-4 py-4 border-b border-gray-200 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-gray-700">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    Supplier Invoices
                </h2>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Vendor bills recorded for the currently selected branch.
                </p>
            </div>

            <span class="inline-flex items-center self-start px-3 py-1 text-xs font-semibold text-gray-700 bg-gray-100 rounded-full sm:self-auto dark:bg-gray-700 dark:text-gray-300">
                {{ $vendorBills->total() }} {{ Str::plural('record', $vendorBills->total()) }}
            </span>
        </div>

        <div class="md:overflow-x-auto">
            <table role="table" class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th
                            role="columnheader"
                            class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400"
                        >
                            Invoice
                        </th>

                        <th
                            role="columnheader"
                            class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400"
                        >
                            Supplier
                        </th>

                        <th
                            role="columnheader"
                            class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400"
                        >
                            Purchase Order
                        </th>

                        <th
                            role="columnheader"
                            class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400"
                        >
                            Invoice Date
                        </th>

                        <th
                            role="columnheader"
                            class="px-6 py-3 text-xs font-medium text-right text-gray-500 uppercase dark:text-gray-400"
                        >
                            Amount
                        </th>

                        <th
                            role="columnheader"
                            class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400"
                        >
                            Status
                        </th>
                        @if($showVendorBillActions)
                            <th
                                role="columnheader"
                                class="px-6 py-3 text-xs font-medium text-center text-gray-500 uppercase dark:text-gray-400"
                            >
                                Actions
                            </th>
                        @endif
                    </tr>
                </thead>

                <tbody role="rowgroup" class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse($vendorBills as $vendorBill)
                        @php
                            $meta = $statusMeta[$vendorBill->status] ?? [
                                'label' => ucfirst(str_replace('_', ' ', $vendorBill->status)),
                                'class' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
                            ];
                        @endphp

                        <tr role="row" class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td role="cell" data-label="Invoice" class="px-6 py-4">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $vendorBill->invoice_number }}
                                    </p>

                                    @if($vendorBill->due_date)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Due {{ $vendorBill->due_date->format('M d, Y') }}
                                        </p>
                                    @endif
                                </div>
                            </td>

                            <td
                                role="cell"
                                data-label="Supplier"
                                class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                <span>
                                    {{ $vendorBill->supplier?->name ?? '—' }}
                                </span>
                            </td>

                            <td
                                role="cell"
                                data-label="Purchase Order"
                                class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                <span>
                                    PO #{{ str_pad($vendorBill->purchase_order_id, 5, '0', STR_PAD_LEFT) }}
                                </span>
                            </td>

                            <td
                                role="cell"
                                data-label="Invoice Date"
                                class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap"
                            >
                                <span>
                                    {{ $vendorBill->invoice_date?->format('M d, Y') }}
                                </span>
                            </td>

                            <td
                                role="cell"
                                data-label="Amount"
                                class="px-6 py-4 text-sm font-semibold text-right text-gray-900 dark:text-white whitespace-nowrap"
                            >
                                <span>
                                    ₱{{ number_format($vendorBill->total_amount, 2) }}
                                </span>
                            </td>

                            <td role="cell" data-label="Status" class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full {{ $meta['class'] }}">
                                    {{ $meta['label'] }}
                                </span>
                            </td>
                            @if($showVendorBillActions)
                            <td
                                role="cell"
                                data-label="Actions"
                                class="px-6 py-4 rt-actions"
                            >
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    @if(in_array($vendorBill->status, [
                                        \App\Models\VendorBill::STATUS_PENDING_MATCH,
                                        \App\Models\VendorBill::STATUS_MATCHED,
                                        \App\Models\VendorBill::STATUS_DISCREPANCY,
                                    ], true))
                                        <form
                                            method="POST"
                                            action="{{ route('vendor-bills.match', $vendorBill) }}"
                                        >
                                            @csrf

                                            @if(
                                                $canEditVendorBills &&
                                                in_array($vendorBill->status, [
                                                    \App\Models\VendorBill::STATUS_PENDING_MATCH,
                                                    \App\Models\VendorBill::STATUS_DISCREPANCY,
                                                ], true)
                                            )
                                                <button
                                                    type="button"
                                                    @click="openEditBill({{ $vendorBill->id }})"
                                                    class="{{ $btn['secondary'] }}"
                                                >
                                                    <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                                    {{ $vendorBill->status === \App\Models\VendorBill::STATUS_DISCREPANCY
                                                        ? 'Correct Bill'
                                                        : 'Edit Bill' }}
                                                </button>
                                            @endif

                                            <button
                                                type="submit"
                                                class="{{ $btn['primary'] }}"
                                            >
                                                <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>

                                                {{ $vendorBill->status === \App\Models\VendorBill::STATUS_PENDING_MATCH
                                                    ? 'Run Match'
                                                    : 'Re-run Match' }}
                                            </button>
                                        </form>
                                    @endif

                                    @if($vendorBill->match_details)
                                        <button
                                            type="button"
                                            @click="openMatch({{ $vendorBill->id }})"
                                            class="{{ $btn['secondary'] }}"
                                        >
                                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                            View Match
                                        </button>
                                    @endif
                                </div>
                            </td>
                        @endif
                        </tr>
                    @empty
                        <tr role="row">
                            <td
                                role="cell"
                                colspan="{{ $showVendorBillActions ? 7 : 6 }}"
                                class="px-6 py-12 text-sm text-center text-gray-500 rt-empty dark:text-gray-400"
                            >
                                <div class="flex flex-col items-center justify-center">
                                    <div class="flex items-center justify-center w-12 h-12 mb-3 text-gray-400 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-500">
                                        <i class="text-lg fa-solid fa-file-invoice" aria-hidden="true"></i>
                                    </div>

                                    <p>No vendor bills have been recorded yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($vendorBills->hasPages())
            <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
                {{ $vendorBills->links() }}
            </div>
        @endif
    </div>

    @if($canCreateVendorBills)
        <x-app-modal
            show="createOpen"
            max-width="2xl"
            role="dialog"
            labelledby="createVendorBillTitle"
        >
            <form
                method="POST"
                action="{{ route('vendor-bills.store') }}"
            >
                @csrf

                <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div class="min-w-0">
                        <h2
                            id="createVendorBillTitle"
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Create Vendor Bill
                        </h2>

                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Record the invoice received from a supplier.
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="closeCreate()"
                        aria-label="Close dialog"
                        class="{{ $modalClose }}"
                    >
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="p-5 space-y-5 sm:p-6">
                    <div>
                        <label
                            for="purchase_order_id"
                            class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Purchase Order
                        </label>

                        <select
                            id="purchase_order_id"
                            name="purchase_order_id"
                            x-model="selectedPoId"
                            @change="selectPo()"
                            required
                            class="{{ $inputBase }}"
                        >
                            <option value="">Select a purchase order</option>

                            @foreach($billablePurchaseOrders as $purchaseOrder)
                                <option
                                    value="{{ $purchaseOrder->id }}"
                                    @selected(old('purchase_order_id') == $purchaseOrder->id)
                                >
                                    PO #{{ str_pad($purchaseOrder->id, 5, '0', STR_PAD_LEFT) }}
                                    — {{ $purchaseOrder->supplier?->name }}
                                    — {{ ucwords(str_replace('_', ' ', $purchaseOrder->status)) }}
                                </option>
                            @endforeach
                        </select>

                        @error('purchase_order_id', 'createVendorBill')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div
                        x-show="selectedPo"
                        x-cloak
                        class="p-4 border border-gray-200 bg-gray-50 rounded-xl dark:border-gray-700 dark:bg-gray-900/40"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                    Supplier
                                </p>

                                <p
                                    class="mt-1 text-sm font-semibold text-gray-900 dark:text-white"
                                    x-text="selectedPo?.supplier || '—'"
                                ></p>
                            </div>

                            <span class="inline-flex items-center self-start px-2.5 py-1 text-xs font-medium text-slate-700 bg-slate-100 rounded-full sm:self-auto dark:bg-slate-900/40 dark:text-slate-300">
                                PO #
                                <span
                                    class="ml-1"
                                    x-text="String(selectedPo?.id || '').padStart(5, '0')"
                                ></span>
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label
                                for="invoice_number"
                                class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Invoice Number
                            </label>

                            <input
                                id="invoice_number"
                                name="invoice_number"
                                value="{{ old('invoice_number') }}"
                                required
                                maxlength="100"
                                placeholder="INV-2026-001"
                                class="{{ $inputBase }}"
                            >

                            @error('invoice_number', 'createVendorBill')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label
                                for="invoice_date"
                                class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Invoice Date
                            </label>

                            <input
                                id="invoice_date"
                                type="date"
                                name="invoice_date"
                                value="{{ old('invoice_date', now()->format('Y-m-d')) }}"
                                required
                                class="{{ $inputBase }}"
                            >

                            @error('invoice_date', 'createVendorBill')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label
                                for="due_date"
                                class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Due Date
                            </label>

                            <input
                                id="due_date"
                                type="date"
                                name="due_date"
                                value="{{ old('due_date') }}"
                                class="{{ $inputBase }}"
                            >

                            @error('due_date', 'createVendorBill')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>

                    <div
                        x-show="selectedPo?.items?.length"
                        x-cloak
                        class="space-y-3"
                    >
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                Invoice Items
                            </h3>

                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Enter the quantity and unit cost shown on the supplier invoice.
                            </p>
                        </div>

                        <div class="space-y-3">
                            <template
                                x-for="(item, index) in selectedPo?.items || []"
                                :key="item.id"
                            >
                                <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
                                    <input
                                        type="hidden"
                                        :name="`items[${index}][purchase_order_item_id]`"
                                        :value="item.id"
                                    >

                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-4 md:items-end">
                                        <div class="md:col-span-2">
                                            <p
                                                class="text-sm font-semibold text-gray-900 dark:text-white"
                                                x-text="item.product"
                                            ></p>

                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Ordered:
                                                <span x-text="formatQuantity(item.quantity)"></span>
                                                <span x-text="item.unit"></span>
                                            </p>

                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                PO Unit Cost:
                                                ₱<span x-text="formatMoney(item.unit_cost)"></span>
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >
                                                Billed Quantity
                                            </label>

                                            <input
                                                type="number"
                                                step="0.001"
                                                min="0.001"
                                                required
                                                :name="`items[${index}][billed_quantity]`"
                                                x-model.number="item.billed_quantity"
                                                class="{{ $inputBase }}"
                                            >
                                        </div>

                                        <div>
                                            <label
                                                class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >
                                                Unit Cost
                                            </label>

                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                required
                                                :name="`items[${index}][unit_cost]`"
                                                x-model.number="item.billed_unit_cost"
                                                class="{{ $inputBase }}"
                                            >
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between gap-3 pt-3 mt-3 border-t border-gray-100 dark:border-gray-700">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            Invoice line total
                                        </span>

                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                            ₱<span x-text="lineTotal(item)"></span>
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="text-right">
                                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                    Invoice Total
                                </p>

                                <p class="mt-1 text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                                    ₱<span x-text="invoiceTotal()"></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label
                            for="notes"
                            class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Notes
                        </label>

                        <textarea
                            id="notes"
                            name="notes"
                            rows="3"
                            maxlength="2000"
                            placeholder="Optional notes about this supplier invoice"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        >{{ old('notes') }}</textarea>

                        @error('notes', 'createVendorBill')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    @error('items', 'createVendorBill')
                        <div class="p-3 text-sm text-red-700 border border-red-200 bg-red-50 rounded-xl dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="{{ $modalFooter }}">
                    <button
                        type="button"
                        @click="closeCreate()"
                        class="w-full {{ $btn['secondary'] }} sm:w-auto"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        :disabled="!selectedPo"
                        class="w-full {{ $btn['primary'] }} sm:w-auto"
                    >
                        <i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i>
                        Save Vendor Bill
                    </button>
                </div>
            </form>
        </x-app-modal>
    @endif
    @if($canMatchVendorBills)
    <x-app-modal
        show="matchOpen"
        max-width="3xl"
        role="dialog"
        labelledby="matchDetailsTitle"
    >
        <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <div class="min-w-0">
                <h2
                    id="matchDetailsTitle"
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    3-Way Match Details
                </h2>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Purchase Order vs Goods Receipt vs Vendor Bill.
                </p>
            </div>

            <button
                type="button"
                @click="closeMatch()"
                aria-label="Close dialog"
                class="{{ $modalClose }}"
            >
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <div class="p-5 space-y-5 sm:p-6">
            <template x-if="selectedMatch">
                <div class="space-y-5">
                    <div class="flex flex-col gap-3 p-4 border border-gray-200 bg-gray-50 rounded-xl sm:flex-row sm:items-center sm:justify-between dark:border-gray-700 dark:bg-gray-900/40">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                Vendor Invoice
                            </p>

                            <p
                                class="mt-1 text-sm font-semibold text-gray-900 dark:text-white"
                                x-text="selectedMatch.invoice"
                            ></p>

                            <p
                                x-show="selectedMatch.matched_at"
                                class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                            >
                                Checked
                                <span x-text="selectedMatch.matched_at"></span>
                            </p>
                        </div>

                        <span
                            class="inline-flex items-center self-start px-3 py-1 text-xs font-medium rounded-full sm:self-auto"
                            :class="selectedMatch.status_class"
                            x-text="selectedMatch.status_label"
                        ></span>
                    </div>

                    <template x-if="selectedMatch.details?.summary">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="p-4 bg-white border border-gray-200 rounded-xl dark:bg-gray-800 dark:border-gray-700">
                                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                    Total Lines
                                </p>

                                <p
                                    class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white"
                                    x-text="selectedMatch.details.summary.total_lines"
                                ></p>
                            </div>

                            <div class="p-4 border border-emerald-200 bg-emerald-50 rounded-xl dark:border-emerald-800 dark:bg-emerald-900/10">
                                <p class="text-xs font-semibold tracking-wide uppercase text-emerald-700 dark:text-emerald-300">
                                    Matched
                                </p>

                                <p
                                    class="mt-2 text-2xl font-semibold text-emerald-900 dark:text-emerald-200"
                                    x-text="selectedMatch.details.summary.matched_lines"
                                ></p>
                            </div>

                            <div class="p-4 border border-red-200 bg-red-50 rounded-xl dark:border-red-800 dark:bg-red-900/10">
                                <p class="text-xs font-semibold tracking-wide text-red-700 uppercase dark:text-red-300">
                                    Discrepancies
                                </p>

                                <p
                                    class="mt-2 text-2xl font-semibold text-red-900 dark:text-red-200"
                                    x-text="selectedMatch.details.summary.discrepancy_lines"
                                ></p>
                            </div>
                        </div>
                    </template>

                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                            Line Comparison
                        </h3>

                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Each invoice line is compared with its purchase order and accepted goods receipt quantity.
                        </p>
                    </div>

                    <div class="space-y-3">
                        <template
                            x-for="line in selectedMatch.details?.lines || []"
                            :key="line.vendor_bill_item_id"
                        >
                            <div class="overflow-hidden border border-gray-200 rounded-2xl dark:border-gray-700">
                                <div class="flex flex-col gap-2 px-4 py-3 border-b border-gray-200 bg-gray-50 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700 dark:bg-gray-900/40">
                                    <div>
                                        <p
                                            class="text-sm font-semibold text-gray-900 dark:text-white"
                                            x-text="line.product"
                                        ></p>

                                        <p
                                            class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"
                                            x-text="line.unit"
                                        ></p>
                                    </div>

                                    <span
                                        class="inline-flex items-center self-start px-2.5 py-1 text-xs font-medium rounded-full sm:self-auto"
                                        :class="line.matched
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                            : 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300'"
                                        x-text="line.matched
                                            ? 'No Issues'
                                            : `${line.issues?.length || 0} ${line.issues?.length === 1 ? 'Issue' : 'Issues'}`"
                                    ></span>
                                </div>

                                <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-3">
                                    <div>
                                        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                            Purchase Order
                                        </p>

                                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                                            Quantity:
                                            <span
                                                class="font-semibold text-gray-900 dark:text-white"
                                                x-text="formatQuantity(line.purchase_order?.quantity)"
                                            ></span>
                                        </p>

                                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                            Unit Cost:
                                            <span
                                                class="font-semibold text-gray-900 dark:text-white"
                                                x-text="'₱' + formatMoney(line.purchase_order?.unit_cost)"
                                            ></span>
                                        </p>
                                    </div>

                                    <div>
                                        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                            Goods Receipt
                                        </p>

                                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                                            Accepted:
                                            <span
                                                class="font-semibold text-gray-900 dark:text-white"
                                                x-text="formatQuantity(line.goods_receipt?.accepted_quantity)"
                                            ></span>
                                        </p>

                                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                            Rejected:
                                            <span
                                                class="font-semibold text-gray-900 dark:text-white"
                                                x-text="formatQuantity(line.goods_receipt?.rejected_quantity)"
                                            ></span>
                                        </p>
                                    </div>

                                    <div>
                                        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                            Vendor Bill
                                        </p>

                                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                                            Quantity:
                                            <span
                                                class="font-semibold text-gray-900 dark:text-white"
                                                x-text="formatQuantity(line.vendor_bill?.quantity)"
                                            ></span>
                                        </p>

                                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                            Unit Cost:
                                            <span
                                                class="font-semibold text-gray-900 dark:text-white"
                                                x-text="'₱' + formatMoney(line.vendor_bill?.unit_cost)"
                                            ></span>
                                        </p>
                                    </div>
                                </div>

                                <template x-if="line.issues?.length">
                                    <div class="px-4 pb-4">
                                        <div class="flex items-start gap-3 p-4 text-sm text-red-700 border border-red-200 bg-red-50 rounded-xl dark:border-red-800 dark:bg-red-900/10 dark:text-red-300">
                                            <i
                                                class="mt-0.5 shrink-0 fa-solid fa-triangle-exclamation"
                                                aria-hidden="true"
                                            ></i>

                                            <div class="min-w-0">
                                                <p class="font-semibold">
                                                    Match requires review
                                                </p>

                                                <ul class="mt-1.5 space-y-1">
                                                    <template
                                                        x-for="issue in line.issues"
                                                        :key="issue.type"
                                                    >
                                                        <li x-text="issue.message"></li>
                                                    </template>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <div class="{{ $modalFooter }}">
            <button
                type="button"
                @click="closeMatch()"
                class="w-full {{ $btn['secondary'] }} sm:w-auto"
            >
                Close
            </button>
        </div>
    </x-app-modal>
    @if($canEditVendorBills)
        <x-app-modal
            show="editBillOpen"
            max-width="2xl"
            role="dialog"
            labelledby="editVendorBillTitle"
        >
            <form
                method="POST"
                :action="selectedEditBill?.update_url || '#'"
            >
                @csrf
                @method('PUT')

                <input
                    type="hidden"
                    name="vendor_bill_id"
                    :value="selectedEditBill?.id || ''"
                >

                <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div class="min-w-0">
                        <h2
                            id="editVendorBillTitle"
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Correct Vendor Bill
                        </h2>

                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Correct the supplier invoice values, then run the 3-way match again.
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="closeEditBill()"
                        aria-label="Close dialog"
                        class="{{ $modalClose }}"
                    >
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="p-5 space-y-5 sm:p-6">
                    <div class="p-4 border border-gray-200 bg-gray-50 rounded-xl dark:border-gray-700 dark:bg-gray-900/40">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                    Supplier
                                </p>

                                <p
                                    class="mt-1 text-sm font-semibold text-gray-900 dark:text-white"
                                    x-text="selectedEditBill?.supplier || '—'"
                                ></p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                    Purchase Order
                                </p>

                                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                                    PO #
                                    <span
                                        x-text="String(selectedEditBill?.purchase_order_id || '').padStart(5, '0')"
                                    ></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label
                                for="edit_invoice_number"
                                class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Invoice Number
                            </label>

                            <input
                                id="edit_invoice_number"
                                name="invoice_number"
                                x-model="selectedEditBill.invoice_number"
                                required
                                maxlength="100"
                                class="{{ $inputBase }}"
                            >
                        </div>

                        <div>
                            <label
                                for="edit_invoice_date"
                                class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Invoice Date
                            </label>

                            <input
                                id="edit_invoice_date"
                                type="date"
                                name="invoice_date"
                                x-model="selectedEditBill.invoice_date"
                                required
                                class="{{ $inputBase }}"
                            >
                        </div>

                        <div>
                            <label
                                for="edit_due_date"
                                class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Due Date
                            </label>

                            <input
                                id="edit_due_date"
                                type="date"
                                name="due_date"
                                x-model="selectedEditBill.due_date"
                                class="{{ $inputBase }}"
                            >
                        </div>
                    </div>

                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                            Invoice Items
                        </h3>

                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Enter the corrected values shown on the supplier invoice.
                        </p>
                    </div>

                    <div class="space-y-3">
                        <template
                            x-for="(item, index) in selectedEditBill?.items || []"
                            :key="item.id"
                        >
                            <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
                                <input
                                    type="hidden"
                                    :name="`items[${index}][id]`"
                                    :value="item.id"
                                >

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-4 md:items-end">
                                    <div class="md:col-span-2">
                                        <p
                                            class="text-sm font-semibold text-gray-900 dark:text-white"
                                            x-text="item.product"
                                        ></p>

                                        <p
                                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                            x-text="item.unit"
                                        ></p>
                                    </div>

                                    <div>
                                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Billed Quantity
                                        </label>

                                        <input
                                            type="number"
                                            step="0.001"
                                            min="0.001"
                                            required
                                            :name="`items[${index}][billed_quantity]`"
                                            x-model.number="item.billed_quantity"
                                            class="{{ $inputBase }}"
                                        >
                                    </div>

                                    <div>
                                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Unit Cost
                                        </label>

                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            required
                                            :name="`items[${index}][unit_cost]`"
                                            x-model.number="item.unit_cost"
                                            class="{{ $inputBase }}"
                                        >
                                    </div>
                                </div>

                                <div class="flex items-center justify-between gap-3 pt-3 mt-3 border-t border-gray-100 dark:border-gray-700">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        Corrected line total
                                    </span>

                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                        ₱<span x-text="editLineTotal(item)"></span>
                                    </span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="text-right">
                            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                Corrected Invoice Total
                            </p>

                            <p class="mt-1 text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                                ₱<span x-text="editInvoiceTotal()"></span>
                            </p>
                        </div>
                    </div>

                    <div>
                        <label
                            for="edit_notes"
                            class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Notes
                        </label>

                        <textarea
                            id="edit_notes"
                            name="notes"
                            rows="3"
                            maxlength="2000"
                            x-model="selectedEditBill.notes"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        ></textarea>
                    </div>

                    <div class="flex items-start gap-3 p-4 text-sm border border-amber-200 bg-amber-50 rounded-xl dark:border-amber-800 dark:bg-amber-900/10">
                        <i
                            class="mt-0.5 shrink-0 text-amber-700 fa-solid fa-circle-info dark:text-amber-300"
                            aria-hidden="true"
                        ></i>

                        <p class="text-amber-800 dark:text-amber-300">
                            Saving a correction clears the previous match result. The bill must pass the 3-way match again before it can move forward.
                        </p>
                    </div>
                </div>

                <div class="{{ $modalFooter }}">
                    <button
                        type="button"
                        @click="closeEditBill()"
                        class="w-full {{ $btn['secondary'] }} sm:w-auto"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="w-full {{ $btn['primary'] }} sm:w-auto"
                    >
                        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                        Save Correction
                    </button>
                </div>
            </form>
        </x-app-modal>
    @endif
@endif
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('vendorBillPage', (purchaseOrders, matchPayloads, editBillPayloads, reopen) => ({
        createOpen: reopen,
        purchaseOrders,
        matchPayloads,
        matchOpen: false,
        editBillPayloads,
        editBillOpen: false,
        selectedEditBill: null,
        selectedMatch: null,
        selectedPoId: @js((string) old('purchase_order_id', '')),
        selectedPo: null,

        init() {
            if (this.selectedPoId) {
                this.selectPo();
            }
        },

        openCreate() {
            this.createOpen = true;

            this.$nextTick(() => {
                document.getElementById('purchase_order_id')?.focus();
            });
        },

        closeCreate() {
            this.createOpen = false;
        },

        openEditBill(id) {
            const source = this.editBillPayloads[String(id)] ?? null;

            if (!source) {
                return;
            }

            this.selectedEditBill = JSON.parse(
                JSON.stringify(source)
            );

            this.editBillOpen = true;
        },

        closeEditBill() {
            this.editBillOpen = false;
            this.selectedEditBill = null;
        },

        editLineTotal(item) {
            const total =
                Number(item.billed_quantity || 0) *
                Number(item.unit_cost || 0);

            return total.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },

        editInvoiceTotal() {
            if (!this.selectedEditBill?.items) {
                return '0.00';
            }

            const total = this.selectedEditBill.items.reduce((sum, item) => {
                return sum +
                    (
                        Number(item.billed_quantity || 0) *
                        Number(item.unit_cost || 0)
                    );
            }, 0);

            return total.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },

        openMatch(id) {
            this.selectedMatch = this.matchPayloads[String(id)] ?? null;

            if (!this.selectedMatch) {
                return;
            }

            this.matchOpen = true;
        },

        closeMatch() {
            this.matchOpen = false;
            this.selectedMatch = null;
        },

        selectPo() {
            const source = this.purchaseOrders[String(this.selectedPoId)] ?? null;

            if (!source) {
                this.selectedPo = null;
                return;
            }

            this.selectedPo = {
                ...source,
                items: source.items.map(item => ({
                    ...item,
                    billed_quantity: Number(item.quantity || 0),
                    billed_unit_cost: Number(item.unit_cost || 0),
                })),
            };
        },

        formatQuantity(value) {
            return Number(value || 0).toLocaleString(undefined, {
                maximumFractionDigits: 3,
            });
        },

        formatMoney(value) {
            return Number(value || 0).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },

        lineTotal(item) {
            const total =
                Number(item.billed_quantity || 0) *
                Number(item.billed_unit_cost || 0);

            return total.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },

        invoiceTotal() {
            if (!this.selectedPo?.items) {
                return '0.00';
            }

            const total = this.selectedPo.items.reduce((sum, item) => {
                return sum +
                    (
                        Number(item.billed_quantity || 0) *
                        Number(item.billed_unit_cost || 0)
                    );
            }, 0);

            return total.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
    }));
});
</script>

<style>
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

    .rt tbody {
        display: grid;
        gap: 0.75rem;
        padding: 0.75rem;
        background: #f9fafb;
    }

    .rt tr {
        padding: 0.875rem 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.875rem;
        background: #ffffff;
    }

    .rt td {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.5rem 0 !important;
        text-align: right !important;
        border: 0 !important;
    }

    .rt td[data-label]::before {
        content: attr(data-label);
        flex-shrink: 0;
        max-width: 42%;
        text-align: left;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .rt td > * {
        min-width: 0;
        max-width: 58%;
    }

    .rt td.rt-actions {
        display: block;
        padding-top: 0.875rem !important;
        margin-top: 0.375rem;
        border-top: 1px solid #e5e7eb !important;
    }

    .rt td.rt-actions::before {
        display: none;
    }

    .rt td.rt-actions > div {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.5rem;
        max-width: none;
    }

    .rt td.rt-actions form,
    .rt td.rt-actions button {
        width: 100%;
    }

    .rt td.rt-empty {
        display: block;
        padding: 2rem 1rem !important;
        text-align: center !important;
    }

    .rt td.rt-empty::before {
        display: none;
    }

    .rt td.rt-empty > * {
        max-width: none;
    }
}

@media (min-width: 768px) and (max-width: 1023px) {
    .rt th,
    .rt td {
        padding-left: 1rem;
        padding-right: 1rem;
    }
}

@media (max-width: 767px) and (prefers-color-scheme: dark) {
    .rt tbody {
        background: #111827;
    }

    .rt tr {
        background: #1f2937;
        border-color: #374151;
    }

    .rt td[data-label]::before {
        color: #9ca3af;
    }
    .rt td.rt-actions {
        border-top-color: #374151 !important;
    }
}
</style>
@endsection
