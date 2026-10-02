@extends('layouts.app')

@section('title', 'Purchase Orders')

@section('content')
@php
    $user = auth()->user();

    $canCreate = $user->hasBranchPermission('create purchase orders');
    $canManage = $user->hasBranchPermission('manage purchase orders');

    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'secondary' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'issue' => $btnBase . ' bg-amber-700 text-white hover:bg-amber-800',
        'cancel' => $btnBase . ' border border-amber-300 bg-white text-amber-800 hover:bg-amber-50 dark:border-amber-700 dark:bg-gray-800 dark:text-amber-300 dark:hover:bg-amber-900/20',
    ];

    $modalFooter = 'flex flex-col-reverse gap-3 px-5 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:flex-row sm:justify-end sm:px-6 dark:bg-gray-900/30 dark:border-gray-700';

    $modalClose = 'inline-flex items-center justify-center shrink-0 w-11 h-11 text-gray-500 rounded-xl hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $inputBase = 'w-full min-h-[44px] px-3 py-2 mt-1 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-[#8B7355] dark:border-gray-600 dark:bg-gray-700 dark:text-white';

    $issueUrl = route(
        'procurement.purchase-orders.issue',
        ['purchaseOrder' => '__ID__']
    );

    $cancelUrl = route(
        'procurement.purchase-orders.cancel',
        ['purchaseOrder' => '__ID__']
    );

    $statusMeta = [
        'draft' => [
            'label' => 'Draft',
            'badge' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
            'icon' => 'fa-pen-to-square',
        ],
        'issued' => [
            'label' => 'Issued',
            'badge' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
            'icon' => 'fa-paper-plane',
        ],
        'partially_received' => [
            'label' => 'Partially Received',
            'badge' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'icon' => 'fa-box-open',
        ],
        'received' => [
            'label' => 'Received',
            'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
            'icon' => 'fa-circle-check',
        ],
        'cancelled' => [
            'label' => 'Cancelled',
            'badge' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
            'icon' => 'fa-ban',
        ],
    ];

    $statusFallback = [
        'label' => 'Unknown',
        'badge' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
        'icon' => 'fa-circle-question',
    ];

    $approvedRequestPayloads = $approvedRequests->map(function ($request) {
        return [
            'id' => $request->id,
            'reference' => 'PR #' . str_pad($request->id, 5, '0', STR_PAD_LEFT),
            'requester' => $request->requester?->name ?? 'Unknown',
            'reason' => $request->reason,
            'items' => $request->items->map(function ($item) {
                return [
                    'product' => $item->product?->name ?? 'Unavailable product',
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                ];
            })->values()->all(),
        ];
    })->values()->all();

    $oldActionOrderId = old('purchase_order_id');

    $oldActionOrder = $oldActionOrderId
        ? $purchaseOrders->getCollection()->firstWhere('id', (int) $oldActionOrderId)
        : null;

    $oldActionPayload = $oldActionOrder
        ? [
            'id' => $oldActionOrder->id,
            'reference' => 'PO #' . str_pad($oldActionOrder->id, 5, '0', STR_PAD_LEFT),
            'supplier' => $oldActionOrder->supplier?->name ?? 'Unknown',
            'status' => $oldActionOrder->status,
        ]
        : [
            'id' => null,
            'reference' => '',
            'supplier' => '',
            'status' => '',
        ];

    $createOpen = $errors->createPurchaseOrder->any();
    $issueOpen = $errors->issuePurchaseOrder->any() && (bool) $oldActionOrder;
    $cancelOpen = $errors->cancelPurchaseOrder->any() && (bool) $oldActionOrder;
@endphp

<div
    class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl"
    x-data="purchaseOrderPage"
>
    <x-page-header
        title="Purchase Orders"
        subtitle="Manage official supplier orders created from approved branch purchase requests."
    >
        <x-slot name="right">
            @if($canCreate)
                <button
                    type="button"
                    @click="createOpen = true"
                    class="{{ $btn['primary'] }}"
                >
                    <i
                        class="text-xs fa-solid fa-plus"
                        aria-hidden="true"
                    ></i>

                    New Purchase Order
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Purchase Orders
            </p>

            <div class="flex items-end justify-between mt-3">
                <p class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                    {{ $purchaseOrders->total() }}
                </p>

                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                    Total
                </span>
            </div>
        </div>

        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Draft
            </p>

            <div class="flex items-end justify-between mt-3">
                <p class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                    {{ $purchaseOrders->getCollection()->where('status', 'draft')->count() }}
                </p>

                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                    This page
                </span>
            </div>
        </div>

        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Issued
            </p>

            <div class="flex items-end justify-between mt-3">
                <p class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                    {{ $purchaseOrders->getCollection()->where('status', 'issued')->count() }}
                </p>

                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                    This page
                </span>
            </div>
        </div>
    </div>

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                Purchase Order Directory
            </h2>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Orders are scoped to the currently selected branch and retain their status history for procurement auditing.
            </p>
        </div>

        @if($purchaseOrders->isEmpty())
            <div class="flex flex-col items-center justify-center px-4 py-16 text-center">
                <i
                    class="mb-3 text-3xl text-gray-400 fa-solid fa-file-invoice dark:text-gray-500"
                    aria-hidden="true"
                ></i>

                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    No purchase orders yet
                </p>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Convert an approved purchase request into an official supplier order.
                </p>

                @if($canCreate)
                    <button
                        type="button"
                        @click="createOpen = true"
                        class="inline-flex items-center justify-center min-h-[44px] mt-2 px-2 text-sm font-semibold rounded-xl text-[#8B7355] hover:text-[#7A6348] dark:text-[#C4A97D] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800"
                    >
                        Create purchase order

                        <i
                            class="ml-1.5 text-xs fa-solid fa-arrow-right"
                            aria-hidden="true"
                        ></i>
                    </button>
                @endif
            </div>
        @else
            <div class="w-full overflow-x-auto overscroll-x-contain">
                <table class="w-full min-w-[1100px] text-sm text-left">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-gray-900/40 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 sm:px-6">
                                Order
                            </th>

                            <th class="px-4 py-3">
                                Supplier
                            </th>

                            <th class="px-4 py-3">
                                Purchase Request
                            </th>

                            <th class="px-4 py-3">
                                Products
                            </th>

                            <th class="px-4 py-3">
                                Expected Delivery
                            </th>

                            <th class="px-4 py-3">
                                Status
                            </th>

                            <th class="px-4 py-3 text-right sm:px-6">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($purchaseOrders as $purchaseOrder)
                            @php
                                $status = $statusMeta[$purchaseOrder->status] ?? $statusFallback;

                                $orderPayload = [
                                    'id' => $purchaseOrder->id,
                                    'reference' => 'PO #' . str_pad($purchaseOrder->id, 5, '0', STR_PAD_LEFT),
                                    'status' => $purchaseOrder->status,
                                    'status_label' => $status['label'],
                                    'status_badge' => $status['badge'],
                                    'supplier' => $purchaseOrder->supplier?->name ?? 'Unknown',
                                    'purchase_request' => $purchaseOrder->purchaseRequest
                                        ? 'PR #' . str_pad($purchaseOrder->purchaseRequest->id, 5, '0', STR_PAD_LEFT)
                                        : 'Unavailable',
                                    'request_reason' => $purchaseOrder->purchaseRequest?->reason,
                                    'creator' => $purchaseOrder->creator?->name ?? 'Unknown',
                                    'notes' => $purchaseOrder->notes,
                                    'created_at' => $purchaseOrder->created_at?->format('M d, Y h:i A'),
                                    'expected_delivery_date' => $purchaseOrder->expected_delivery_date?->format('M d, Y'),
                                    'issued_at' => $purchaseOrder->issued_at?->format('M d, Y h:i A'),
                                    'cancelled_at' => $purchaseOrder->cancelled_at?->format('M d, Y h:i A'),
                                    'items' => $purchaseOrder->items->map(function ($item) {
                                        return [
                                            'product' => $item->product?->name ?? 'Unavailable product',
                                            'quantity' => $item->quantity,
                                            'unit' => $item->unit,
                                        ];
                                    })->values()->all(),
                                    'history' => $purchaseOrder->statusHistory
                                        ->sortBy('id')
                                        ->map(function ($history) use ($statusMeta, $statusFallback) {
                                            $fromMeta = $history->from_status
                                                ? ($statusMeta[$history->from_status] ?? $statusFallback)
                                                : null;

                                            $toMeta = $statusMeta[$history->to_status] ?? $statusFallback;

                                            return [
                                                'from_status' => $history->from_status,
                                                'from_label' => $fromMeta['label'] ?? null,
                                                'to_status' => $history->to_status,
                                                'to_label' => $toMeta['label'],
                                                'changed_by' => $history->changedBy?->name ?? 'System',
                                                'reason' => $history->reason,
                                                'created_at' => $history->created_at?->format('M d, Y h:i A'),
                                            ];
                                        })
                                        ->values()
                                        ->all(),
                                ];
                            @endphp

                            <tr class="align-top">
                                <td class="px-4 py-4 sm:px-6">
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        PO #{{ str_pad($purchaseOrder->id, 5, '0', STR_PAD_LEFT) }}
                                    </p>

                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $purchaseOrder->created_at?->format('M d, Y') }}
                                    </p>
                                </td>

                                <td class="px-4 py-4">
                                    <p class="font-medium text-gray-700 dark:text-gray-200">
                                        {{ $purchaseOrder->supplier?->name ?? 'Unknown' }}
                                    </p>
                                </td>

                                <td class="px-4 py-4">
                                    @if($purchaseOrder->purchaseRequest)
                                        <p class="text-gray-700 dark:text-gray-200">
                                            PR #{{ str_pad($purchaseOrder->purchaseRequest->id, 5, '0', STR_PAD_LEFT) }}
                                        </p>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">
                                            Unavailable
                                        </span>
                                    @endif
                                </td>

                                <td class="px-4 py-4">
                                    @if($purchaseOrder->items->isEmpty())
                                        <span class="text-gray-500 dark:text-gray-400">
                                            No products
                                        </span>
                                    @else
                                        <div class="space-y-2">
                                            @foreach($purchaseOrder->items as $item)
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="text-gray-700 dark:text-gray-200">
                                                        {{ $item->product?->name ?? 'Unavailable product' }}
                                                    </span>

                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300">
                                                        {{ $item->quantity }}
                                                        {{ $item->unit }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>

                                <td class="px-4 py-4">
                                    @if($purchaseOrder->expected_delivery_date)
                                        <p class="text-gray-700 dark:text-gray-200">
                                            {{ $purchaseOrder->expected_delivery_date->format('M d, Y') }}
                                        </p>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">
                                            Not specified
                                        </span>
                                    @endif
                                </td>

                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-full {{ $status['badge'] }}">
                                        <i
                                            class="text-[11px] fa-solid {{ $status['icon'] }}"
                                            aria-hidden="true"
                                        ></i>

                                        {{ $status['label'] }}
                                    </span>
                                </td>

                                <td class="px-4 py-4 sm:px-6">
                                    <div class="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap sm:justify-end">
                                        <button
                                            type="button"
                                            @click='openDetails(@json($orderPayload))'
                                            class="{{ $btn['secondary'] }}"
                                        >
                                            <i
                                                class="text-xs fa-solid fa-eye"
                                                aria-hidden="true"
                                            ></i>

                                            View
                                        </button>

                                        @if($canManage && $purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_DRAFT)
                                            <button
                                                type="button"
                                                @click='openIssue(@json($orderPayload))'
                                                class="{{ $btn['issue'] }}"
                                            >
                                                <i
                                                    class="text-xs fa-solid fa-paper-plane"
                                                    aria-hidden="true"
                                                ></i>

                                                Issue
                                            </button>
                                        @endif

                                        @if(
                                            $canManage &&
                                            in_array(
                                                $purchaseOrder->status,
                                                [
                                                    \App\Models\PurchaseOrder::STATUS_DRAFT,
                                                    \App\Models\PurchaseOrder::STATUS_ISSUED,
                                                ],
                                                true
                                            )
                                        )
                                            <button
                                                type="button"
                                                @click='openCancel(@json($orderPayload))'
                                                class="{{ $btn['cancel'] }}"
                                            >
                                                <i
                                                    class="text-xs fa-solid fa-ban"
                                                    aria-hidden="true"
                                                ></i>

                                                Cancel Order
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($purchaseOrders->hasPages())
                <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
                    {{ $purchaseOrders->links() }}
                </div>
            @endif
        @endif
    </div>

    @if($canCreate)
        <x-app-modal
            show="createOpen"
            max-width="xl"
            labelledby="createPurchaseOrderTitle"
        >
            <form
                method="POST"
                action="{{ route('procurement.purchase-orders.store') }}"
            >
                @csrf

                <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div class="min-w-0">
                        <h2
                            id="createPurchaseOrderTitle"
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            New Purchase Order
                        </h2>

                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Convert an approved branch request into an official supplier order.
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="createOpen = false"
                        aria-label="Close purchase order dialog"
                        class="{{ $modalClose }}"
                    >
                        <i
                            class="fa-solid fa-xmark"
                            aria-hidden="true"
                        ></i>
                    </button>
                </div>

                <div class="p-5 space-y-5 sm:p-6">
                    <div class="p-4 border border-blue-200 bg-blue-50 rounded-xl dark:border-blue-800 dark:bg-blue-900/10">
                        <div class="flex items-start gap-3">
                            <i
                                class="mt-0.5 text-blue-600 fa-solid fa-circle-info dark:text-blue-400"
                                aria-hidden="true"
                            ></i>

                            <div>
                                <p class="text-sm font-medium text-blue-800 dark:text-blue-300">
                                    Purchase order only
                                </p>

                                <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                                    Creating or issuing a purchase order does not increase inventory. Stock will only increase later when goods are physically received through GRN.
                                </p>
                            </div>
                        </div>
                    </div>

                    @if($approvedRequests->isEmpty())
                        <div class="p-4 border border-amber-200 bg-amber-50 rounded-xl dark:border-amber-800 dark:bg-amber-900/10">
                            <div class="flex items-start gap-3">
                                <i
                                    class="mt-0.5 text-amber-700 fa-solid fa-circle-exclamation dark:text-amber-300"
                                    aria-hidden="true"
                                ></i>

                                <div>
                                    <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
                                        No approved purchase requests available
                                    </p>

                                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                                        A purchase request must be approved and must not already have a purchase order.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div>
                        <label
                            for="purchase-order-request"
                            class="text-sm font-medium text-gray-700 dark:text-gray-200"
                        >
                            Approved Purchase Request
                        </label>

                        <select
                            id="purchase-order-request"
                            name="purchase_request_id"
                            x-model="selectedRequestId"
                            required
                            class="{{ $inputBase }}"
                        >
                            <option value="">
                                Select an approved request
                            </option>

                            @foreach($approvedRequests as $approvedRequest)
                                <option
                                    value="{{ $approvedRequest->id }}"
                                    @selected((string) old('purchase_request_id') === (string) $approvedRequest->id)
                                >
                                    PR #{{ str_pad($approvedRequest->id, 5, '0', STR_PAD_LEFT) }}
                                    — {{ $approvedRequest->requester?->name ?? 'Unknown' }}
                                </option>
                            @endforeach
                        </select>

                        @error('purchase_request_id', 'createPurchaseOrder')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <template x-if="selectedRequest">
                        <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                        Request
                                    </p>

                                    <p
                                        class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                                        x-text="selectedRequest.reference"
                                    ></p>
                                </div>

                                <div>
                                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                        Requester
                                    </p>

                                    <p
                                        class="mt-1 text-sm text-gray-700 dark:text-gray-200"
                                        x-text="selectedRequest.requester"
                                    ></p>
                                </div>
                            </div>

                            <div class="mt-4">
                                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                    Products
                                </p>

                                <div class="mt-2 overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700">
                                    <template
                                        x-for="(item, index) in selectedRequest.items"
                                        :key="index"
                                    >
                                        <div class="flex flex-col gap-1 px-4 py-3 border-b border-gray-200 last:border-b-0 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700">
                                            <p
                                                class="text-sm font-medium text-gray-900 dark:text-white"
                                                x-text="item.product"
                                            ></p>

                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                <span x-text="item.quantity"></span>
                                                <span x-text="item.unit || ''"></span>
                                            </p>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <template x-if="selectedRequest.reason">
                                <div class="pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
                                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                        Request Reason
                                    </p>

                                    <p
                                        class="mt-1 text-sm text-gray-700 whitespace-pre-line dark:text-gray-200"
                                        x-text="selectedRequest.reason"
                                    ></p>
                                </div>
                            </template>
                        </div>
                    </template>

                    <div>
                        <label
                            for="purchase-order-supplier"
                            class="text-sm font-medium text-gray-700 dark:text-gray-200"
                        >
                            Supplier
                        </label>

                        <select
                            id="purchase-order-supplier"
                            name="supplier_id"
                            required
                            class="{{ $inputBase }}"
                        >
                            <option value="">
                                Select supplier
                            </option>

                            @foreach($suppliers as $supplier)
                                <option
                                    value="{{ $supplier->id }}"
                                    @selected((string) old('supplier_id') === (string) $supplier->id)
                                >
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>

                        @error('supplier_id', 'createPurchaseOrder')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label
                            for="purchase-order-delivery"
                            class="text-sm font-medium text-gray-700 dark:text-gray-200"
                        >
                            Expected Delivery Date
                        </label>

                        <input
                            id="purchase-order-delivery"
                            type="date"
                            name="expected_delivery_date"
                            min="{{ now()->toDateString() }}"
                            value="{{ old('expected_delivery_date') }}"
                            class="{{ $inputBase }}"
                        >

                        @error('expected_delivery_date', 'createPurchaseOrder')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label
                            for="purchase-order-notes"
                            class="text-sm font-medium text-gray-700 dark:text-gray-200"
                        >
                            Notes
                        </label>

                        <textarea
                            id="purchase-order-notes"
                            name="notes"
                            rows="4"
                            maxlength="2000"
                            class="{{ $inputBase }}"
                            placeholder="Optional notes for this supplier order"
                        >{{ old('notes') }}</textarea>

                        @error('notes', 'createPurchaseOrder')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                <div class="{{ $modalFooter }}">
                    <button
                        type="button"
                        @click="createOpen = false"
                        class="{{ $btn['secondary'] }}"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        @disabled($approvedRequests->isEmpty() || $suppliers->isEmpty())
                        class="{{ $btn['primary'] }} disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <i
                            class="text-xs fa-solid fa-file-circle-plus"
                            aria-hidden="true"
                        ></i>

                        Create Purchase Order
                    </button>
                </div>
            </form>
        </x-app-modal>
    @endif

    <x-app-modal
        show="detailsOpen"
        max-width="xl"
        labelledby="purchaseOrderDetailsTitle"
    >
        <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <div class="min-w-0">
                <h2
                    id="purchaseOrderDetailsTitle"
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Purchase Order Details
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    x-text="order.reference"
                ></p>
            </div>

            <button
                type="button"
                @click="detailsOpen = false"
                aria-label="Close purchase order details dialog"
                class="{{ $modalClose }}"
            >
                <i
                    class="fa-solid fa-xmark"
                    aria-hidden="true"
                ></i>
            </button>
        </div>

        <div class="p-5 space-y-5 sm:p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                        Supplier
                    </p>

                    <p
                        class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        x-text="order.supplier || 'Unknown'"
                    ></p>
                </div>

                <div>
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                        Purchase Request
                    </p>

                    <p
                        class="mt-1 text-sm text-gray-700 dark:text-gray-200"
                        x-text="order.purchase_request || 'Unavailable'"
                    ></p>
                </div>

                <div>
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                        Created By
                    </p>

                    <p
                        class="mt-1 text-sm text-gray-700 dark:text-gray-200"
                        x-text="order.creator || 'Unknown'"
                    ></p>
                </div>

                <div>
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                        Created
                    </p>

                    <p
                        class="mt-1 text-sm text-gray-700 dark:text-gray-200"
                        x-text="order.created_at || '—'"
                    ></p>
                </div>

                <div>
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                        Expected Delivery
                    </p>

                    <p
                        class="mt-1 text-sm text-gray-700 dark:text-gray-200"
                        x-text="order.expected_delivery_date || 'Not specified'"
                    ></p>
                </div>

                <div>
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                        Status
                    </p>

                    <span
                        class="inline-flex items-center px-3 py-1.5 mt-2 text-xs font-medium rounded-full"
                        :class="order.status_badge"
                        x-text="order.status_label"
                    ></span>
                </div>
            </div>

            <template x-if="order.issued_at">
                <div class="p-4 border border-blue-200 bg-blue-50 rounded-xl dark:border-blue-800 dark:bg-blue-900/10">
                    <p class="text-xs font-semibold tracking-wide text-blue-700 uppercase dark:text-blue-300">
                        Issued
                    </p>

                    <p
                        class="mt-1 text-sm text-blue-800 dark:text-blue-300"
                        x-text="order.issued_at"
                    ></p>
                </div>
            </template>

            <template x-if="order.cancelled_at">
                <div class="p-4 border border-red-200 bg-red-50 rounded-xl dark:border-red-800 dark:bg-red-900/10">
                    <p class="text-xs font-semibold tracking-wide text-red-700 uppercase dark:text-red-300">
                        Cancelled
                    </p>

                    <p
                        class="mt-1 text-sm text-red-800 dark:text-red-300"
                        x-text="order.cancelled_at"
                    ></p>
                </div>
            </template>

            <div>
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                    Products
                </p>

                <div class="mt-2 overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700">
                    <template
                        x-for="(item, index) in order.items"
                        :key="index"
                    >
                        <div class="flex flex-col gap-1 px-4 py-3 border-b border-gray-200 last:border-b-0 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700">
                            <p
                                class="text-sm font-medium text-gray-900 dark:text-white"
                                x-text="item.product"
                            ></p>

                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                <span x-text="item.quantity"></span>
                                <span x-text="item.unit || ''"></span>
                            </p>
                        </div>
                    </template>
                </div>
            </div>

            <div>
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                    Notes
                </p>

                <p
                    class="mt-1 text-sm text-gray-700 whitespace-pre-line dark:text-gray-200"
                    x-text="order.notes || 'No notes provided.'"
                ></p>
            </div>

            <template x-if="order.request_reason">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                        Original Request Reason
                    </p>

                    <p
                        class="mt-1 text-sm text-gray-700 whitespace-pre-line dark:text-gray-200"
                        x-text="order.request_reason"
                    ></p>
                </div>
            </template>

            <div>
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                    Status History
                </p>

                <div class="mt-2 overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700">
                    <template
                        x-for="(entry, index) in order.history"
                        :key="index"
                    >
                        <div class="px-4 py-3 border-b border-gray-200 last:border-b-0 dark:border-gray-700">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        <template x-if="entry.from_label">
                                            <span>
                                                <span x-text="entry.from_label"></span>
                                                <i
                                                    class="mx-1.5 text-xs text-gray-400 fa-solid fa-arrow-right"
                                                    aria-hidden="true"
                                                ></i>
                                            </span>
                                        </template>

                                        <span x-text="entry.to_label"></span>
                                    </p>

                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        By
                                        <span x-text="entry.changed_by"></span>
                                    </p>
                                </div>

                                <p
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                    x-text="entry.created_at || '—'"
                                ></p>
                            </div>

                            <template x-if="entry.reason">
                                <p
                                    class="mt-2 text-sm text-gray-600 whitespace-pre-line dark:text-gray-300"
                                    x-text="entry.reason"
                                ></p>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="{{ $modalFooter }}">
            <button
                type="button"
                @click="detailsOpen = false"
                class="{{ $btn['secondary'] }}"
            >
                Close
            </button>
        </div>
    </x-app-modal>

    @if($canManage)
        <x-app-modal
            show="issueOpen"
            max-width="lg"
            labelledby="issuePurchaseOrderTitle"
        >
            <form
                method="POST"
                :action="'{{ $issueUrl }}'.replace('__ID__', actionOrder.id)"
            >
                @csrf
                @method('PATCH')

                <input
                    type="hidden"
                    name="purchase_order_id"
                    :value="actionOrder.id"
                >

                <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div class="min-w-0">
                        <h2
                            id="issuePurchaseOrderTitle"
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Issue Purchase Order
                        </h2>

                        <p
                            class="mt-1 text-sm text-gray-500 truncate dark:text-gray-400"
                            x-text="actionOrder.reference"
                        ></p>
                    </div>

                    <button
                        type="button"
                        @click="issueOpen = false"
                        aria-label="Close issue purchase order dialog"
                        class="{{ $modalClose }}"
                    >
                        <i
                            class="fa-solid fa-xmark"
                            aria-hidden="true"
                        ></i>
                    </button>
                </div>

                <div class="p-5 space-y-4 sm:p-6">
                    <div class="p-4 border border-blue-200 bg-blue-50 rounded-xl dark:border-blue-800 dark:bg-blue-900/10">
                        <div class="flex items-start gap-3">
                            <div class="flex items-center justify-center text-blue-700 bg-blue-100 shrink-0 w-11 h-11 rounded-xl dark:bg-blue-900/30 dark:text-blue-300">
                                <i
                                    class="fa-solid fa-paper-plane"
                                    aria-hidden="true"
                                ></i>
                            </div>

                            <div class="min-w-0">
                                <p class="text-sm font-medium text-blue-800 dark:text-blue-300">
                                    Issue this purchase order?
                                </p>

                                <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                                    Issuing records that the order has been formally sent to the supplier. Inventory will not change.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label
                            for="issue-purchase-order-reason"
                            class="text-sm font-medium text-gray-700 dark:text-gray-200"
                        >
                            Issue Note
                        </label>

                        <textarea
                            id="issue-purchase-order-reason"
                            name="reason"
                            rows="3"
                            maxlength="1000"
                            class="{{ $inputBase }}"
                            placeholder="Optional note about issuing this order"
                        >{{ $issueOpen ? old('reason') : '' }}</textarea>

                        @error('reason', 'issuePurchaseOrder')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                <div class="{{ $modalFooter }}">
                    <button
                        type="button"
                        @click="issueOpen = false"
                        class="{{ $btn['secondary'] }}"
                    >
                        Keep Draft
                    </button>

                    <button
                        type="submit"
                        class="{{ $btn['issue'] }}"
                    >
                        <i
                            class="text-xs fa-solid fa-paper-plane"
                            aria-hidden="true"
                        ></i>

                        Issue Order
                    </button>
                </div>
            </form>
        </x-app-modal>

        <x-app-modal
            show="cancelOpen"
            max-width="lg"
            labelledby="cancelPurchaseOrderTitle"
        >
            <form
                method="POST"
                :action="'{{ $cancelUrl }}'.replace('__ID__', actionOrder.id)"
            >
                @csrf
                @method('PATCH')

                <input
                    type="hidden"
                    name="purchase_order_id"
                    :value="actionOrder.id"
                >

                <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div class="min-w-0">
                        <h2
                            id="cancelPurchaseOrderTitle"
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Cancel Purchase Order
                        </h2>

                        <p
                            class="mt-1 text-sm text-gray-500 truncate dark:text-gray-400"
                            x-text="actionOrder.reference"
                        ></p>
                    </div>

                    <button
                        type="button"
                        @click="cancelOpen = false"
                        aria-label="Close cancellation dialog"
                        class="{{ $modalClose }}"
                    >
                        <i
                            class="fa-solid fa-xmark"
                            aria-hidden="true"
                        ></i>
                    </button>
                </div>

                <div class="p-5 space-y-4 sm:p-6">
                    <div class="p-4 border border-amber-200 bg-amber-50 rounded-xl dark:border-amber-800 dark:bg-amber-900/10">
                        <div class="flex items-start gap-3">
                            <div class="flex items-center justify-center text-amber-700 bg-amber-100 shrink-0 w-11 h-11 rounded-xl dark:bg-amber-900/30 dark:text-amber-300">
                                <i
                                    class="fa-solid fa-ban"
                                    aria-hidden="true"
                                ></i>
                            </div>

                            <div class="min-w-0">
                                <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
                                    Cancel this purchase order?
                                </p>

                                <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                                    The order will remain in procurement history as cancelled. This does not delete the purchase order or change inventory.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label
                            for="cancel-purchase-order-reason"
                            class="text-sm font-medium text-gray-700 dark:text-gray-200"
                        >
                            Cancellation Reason
                        </label>

                        <textarea
                            id="cancel-purchase-order-reason"
                            name="reason"
                            rows="4"
                            maxlength="1000"
                            required
                            class="{{ $inputBase }}"
                            placeholder="Explain why this purchase order is being cancelled"
                        >{{ $cancelOpen ? old('reason') : '' }}</textarea>

                        @error('reason', 'cancelPurchaseOrder')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                <div class="{{ $modalFooter }}">
                    <button
                        type="button"
                        @click="cancelOpen = false"
                        class="{{ $btn['secondary'] }}"
                    >
                        Keep Order
                    </button>

                    <button
                        type="submit"
                        class="{{ $btn['cancel'] }}"
                    >
                        <i
                            class="text-xs fa-solid fa-ban"
                            aria-hidden="true"
                        ></i>

                        Cancel Order
                    </button>
                </div>
            </form>
        </x-app-modal>
    @endif
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('purchaseOrderPage', () => ({
            createOpen: @json((bool) $createOpen),
            detailsOpen: false,
            issueOpen: @json((bool) $issueOpen),
            cancelOpen: @json((bool) $cancelOpen),

            selectedRequestId: @json((string) old('purchase_request_id', '')),

            approvedRequests: @json($approvedRequestPayloads),

            order: {
                id: null,
                reference: '',
                status: '',
                status_label: '',
                status_badge: '',
                supplier: '',
                purchase_request: '',
                request_reason: '',
                creator: '',
                notes: '',
                created_at: '',
                expected_delivery_date: '',
                issued_at: '',
                cancelled_at: '',
                items: [],
                history: []
            },

            actionOrder: @json($oldActionPayload),

            get selectedRequest() {
                return this.approvedRequests.find(
                    request => String(request.id) === String(this.selectedRequestId)
                ) ?? null;
            },

            openDetails(data) {
                this.order = {
                    id: data.id,
                    reference: data.reference ?? '',
                    status: data.status ?? '',
                    status_label: data.status_label ?? '',
                    status_badge: data.status_badge ?? '',
                    supplier: data.supplier ?? '',
                    purchase_request: data.purchase_request ?? '',
                    request_reason: data.request_reason ?? '',
                    creator: data.creator ?? '',
                    notes: data.notes ?? '',
                    created_at: data.created_at ?? '',
                    expected_delivery_date: data.expected_delivery_date ?? '',
                    issued_at: data.issued_at ?? '',
                    cancelled_at: data.cancelled_at ?? '',
                    items: data.items ?? [],
                    history: data.history ?? []
                };

                this.detailsOpen = true;
            },

            openIssue(data) {
                this.actionOrder = {
                    id: data.id,
                    reference: data.reference ?? '',
                    supplier: data.supplier ?? '',
                    status: data.status ?? 'draft'
                };

                this.issueOpen = true;
            },

            openCancel(data) {
                this.actionOrder = {
                    id: data.id,
                    reference: data.reference ?? '',
                    supplier: data.supplier ?? '',
                    status: data.status ?? ''
                };

                this.cancelOpen = true;
            }
        }));
    });
</script>
@endsection
