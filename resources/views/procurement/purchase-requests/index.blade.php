@extends('layouts.app')

@section('title', 'Purchase Requests')

@section('content')
@php
    $user = auth()->user();

    $canCreate = $user->hasBranchPermission('create purchase requests');
    $canReview = $user->hasBranchPermission('review purchase requests');

    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'secondary' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'approve' => $btnBase . ' bg-emerald-700 text-white hover:bg-emerald-800',
        'reject' => $btnBase . ' border border-red-300 bg-white text-red-700 hover:bg-red-50 dark:border-red-800 dark:bg-gray-800 dark:text-red-300 dark:hover:bg-red-900/20',
    ];

    $modalFooter = 'flex flex-col-reverse gap-3 px-5 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:flex-row sm:justify-end sm:px-6 dark:bg-gray-900/30 dark:border-gray-700';

    $modalClose = 'inline-flex items-center justify-center shrink-0 w-11 h-11 text-gray-500 rounded-xl hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $inputBase = 'w-full min-h-[44px] px-3 py-2 mt-1 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-[#8B7355] dark:border-gray-600 dark:bg-gray-700 dark:text-white';

    $approveUrl = route(
        'procurement.purchase-requests.approve',
        ['purchaseRequest' => '__ID__']
    );

    $rejectUrl = route(
        'procurement.purchase-requests.reject',
        ['purchaseRequest' => '__ID__']
    );

    $statusMeta = [
        'pending' => [
            'label' => 'Pending',
            'badge' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'icon' => 'fa-clock',
        ],
        'approved' => [
            'label' => 'Approved',
            'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
            'icon' => 'fa-check',
        ],
        'rejected' => [
            'label' => 'Rejected',
            'badge' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
            'icon' => 'fa-ban',
        ],
        'converted' => [
            'label' => 'Converted',
            'badge' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
            'icon' => 'fa-file-invoice',
        ],
    ];

    $statusFallback = [
        'label' => 'Unknown',
        'badge' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
        'icon' => 'fa-circle-question',
    ];

    $oldItems = collect(old('items', [
        [
            'product_id' => '',
            'quantity' => 1,
        ],
    ]))->values()->map(function ($item, $index) {
        return [
            'key' => 'old-' . $index,
            'product_id' => $item['product_id'] ?? '',
            'quantity' => $item['quantity'] ?? 1,
        ];
    })->values()->all();

    $oldReviewRequestId = old('purchase_request_id');

    $oldReviewRequest = $oldReviewRequestId
        ? $purchaseRequests->getCollection()->firstWhere('id', (int) $oldReviewRequestId)
        : null;

    $oldReviewPayload = $oldReviewRequest
        ? [
            'id' => $oldReviewRequest->id,
            'reference' => 'PR #' . str_pad($oldReviewRequest->id, 5, '0', STR_PAD_LEFT),
            'requester' => $oldReviewRequest->requester?->name ?? 'Unknown',
            'reason' => $oldReviewRequest->reason,
            'status' => $oldReviewRequest->status,
        ]
        : [
            'id' => null,
            'reference' => '',
            'requester' => '',
            'reason' => '',
            'status' => 'pending',
        ];

    $createOpen = $errors->createPurchaseRequest->any();
    $approveOpen = $errors->approvePurchaseRequest->any() && (bool) $oldReviewRequest;
    $rejectOpen = $errors->rejectPurchaseRequest->any() && (bool) $oldReviewRequest;
@endphp

<div
    class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl"
    x-data="purchaseRequestPage"
>
    <x-page-header
        title="Purchase Requests"
        subtitle="Request branch supplies and review purchasing needs before they become official purchase orders."
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

                    New Request
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Requests
            </p>

            <div class="flex items-end justify-between mt-3">
                <p class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                    {{ $purchaseRequests->total() }}
                </p>

                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                    Total
                </span>
            </div>
        </div>

        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Pending
            </p>

            <div class="flex items-end justify-between mt-3">
                <p class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                    {{ $purchaseRequests->getCollection()->where('status', 'pending')->count() }}
                </p>

                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                    This page
                </span>
            </div>
        </div>

        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Approved
            </p>

            <div class="flex items-end justify-between mt-3">
                <p class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                    {{ $purchaseRequests->getCollection()->where('status', 'approved')->count() }}
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
                Purchase Request Directory
            </h2>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Requests are scoped to the currently selected branch and retain their review status for audit history.
            </p>
        </div>

        @if($purchaseRequests->isEmpty())
            <div class="flex flex-col items-center justify-center px-4 py-16 text-center">
                <i
                    class="mb-3 text-3xl text-gray-400 fa-solid fa-file-circle-plus dark:text-gray-500"
                    aria-hidden="true"
                ></i>

                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    No purchase requests yet
                </p>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Create a request when this branch needs products for replenishment.
                </p>

                @if($canCreate)
                    <button
                        type="button"
                        @click="createOpen = true"
                        class="inline-flex items-center justify-center min-h-[44px] mt-2 px-2 text-sm font-semibold rounded-xl text-[#8B7355] hover:text-[#7A6348] dark:text-[#C4A97D] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800"
                    >
                        Create request

                        <i
                            class="ml-1.5 text-xs fa-solid fa-arrow-right"
                            aria-hidden="true"
                        ></i>
                    </button>
                @endif
            </div>
        @else
            <div class="w-full overflow-x-auto overscroll-x-contain">
                <table class="w-full min-w-[950px] text-sm text-left">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-gray-900/40 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 sm:px-6">
                                Request
                            </th>

                            <th class="px-4 py-3">
                                Requester
                            </th>

                            <th class="px-4 py-3">
                                Products
                            </th>

                            <th class="px-4 py-3">
                                Status
                            </th>

                            <th class="px-4 py-3">
                                Requested
                            </th>

                            <th class="px-4 py-3 text-right sm:px-6">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($purchaseRequests as $purchaseRequest)
                            @php
                                $status = $statusMeta[$purchaseRequest->status] ?? $statusFallback;

                                $requestPayload = [
                                    'id' => $purchaseRequest->id,
                                    'reference' => 'PR #' . str_pad($purchaseRequest->id, 5, '0', STR_PAD_LEFT),
                                    'requester' => $purchaseRequest->requester?->name ?? 'Unknown',
                                    'reason' => $purchaseRequest->reason,
                                    'status' => $purchaseRequest->status,
                                    'status_label' => $status['label'],
                                    'status_badge' => $status['badge'],
                                    'created_at' => $purchaseRequest->created_at?->format('M d, Y h:i A'),
                                    'reviewer' => $purchaseRequest->reviewer?->name,
                                    'review_reason' => $purchaseRequest->review_reason,
                                    'reviewed_at' => $purchaseRequest->reviewed_at?->format('M d, Y h:i A'),
                                    'converted_at' => $purchaseRequest->converted_at?->format('M d, Y h:i A'),
                                    'items' => $purchaseRequest->items->map(function ($item) {
                                        return [
                                            'product' => $item->product?->name ?? 'Unavailable product',
                                            'quantity' => $item->quantity,
                                            'unit' => $item->unit,
                                        ];
                                    })->values()->all(),
                                ];
                            @endphp

                            <tr class="align-top">
                                <td class="px-4 py-4 sm:px-6">
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        PR #{{ str_pad($purchaseRequest->id, 5, '0', STR_PAD_LEFT) }}
                                    </p>

                                    @if($purchaseRequest->reason)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ \Illuminate\Support\Str::limit($purchaseRequest->reason, 60) }}
                                        </p>
                                    @endif
                                </td>

                                <td class="px-4 py-4">
                                    <p class="text-gray-700 dark:text-gray-200">
                                        {{ $purchaseRequest->requester?->name ?? 'Unknown' }}
                                    </p>
                                </td>

                                <td class="px-4 py-4">
                                    @if($purchaseRequest->items->isEmpty())
                                        <span class="text-sm text-gray-500 dark:text-gray-400">
                                            No products
                                        </span>
                                    @else
                                        <div class="space-y-2">
                                            @foreach($purchaseRequest->items as $item)
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="text-sm text-gray-700 dark:text-gray-200">
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
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-full {{ $status['badge'] }}">
                                        <i
                                            class="text-[11px] fa-solid {{ $status['icon'] }}"
                                            aria-hidden="true"
                                        ></i>

                                        {{ $status['label'] }}
                                    </span>
                                </td>

                                <td class="px-4 py-4">
                                    <p class="text-gray-700 dark:text-gray-200">
                                        {{ $purchaseRequest->created_at?->format('M d, Y') }}
                                    </p>

                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $purchaseRequest->created_at?->format('h:i A') }}
                                    </p>
                                </td>

                                <td class="px-4 py-4 sm:px-6">
                                    <div class="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap sm:justify-end">
                                        <button
                                            type="button"
                                            @click='openDetails(@json($requestPayload))'
                                            class="{{ $btn['secondary'] }}"
                                        >
                                            <i
                                                class="text-xs fa-solid fa-eye"
                                                aria-hidden="true"
                                            ></i>

                                            View
                                        </button>

                                        @if($canReview && $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING)
                                            <button
                                                type="button"
                                                @click='openApprove(@json($requestPayload))'
                                                class="{{ $btn['approve'] }}"
                                            >
                                                <i
                                                    class="text-xs fa-solid fa-check"
                                                    aria-hidden="true"
                                                ></i>

                                                Approve
                                            </button>

                                            <button
                                                type="button"
                                                @click='openReject(@json($requestPayload))'
                                                class="{{ $btn['reject'] }}"
                                            >
                                                <i
                                                    class="text-xs fa-solid fa-ban"
                                                    aria-hidden="true"
                                                ></i>

                                                Reject
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($purchaseRequests->hasPages())
                <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
                    {{ $purchaseRequests->links() }}
                </div>
            @endif
        @endif
    </div>

    @if($canCreate)
        <x-app-modal
            show="createOpen"
            max-width="xl"
            labelledby="createPurchaseRequestTitle"
        >
            <form
                method="POST"
                action="{{ route('procurement.purchase-requests.store') }}"
            >
                @csrf

                <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div class="min-w-0">
                        <h2
                            id="createPurchaseRequestTitle"
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            New Purchase Request
                        </h2>

                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Request products for {{ $branch->name }}.
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="createOpen = false"
                        aria-label="Close purchase request dialog"
                        class="{{ $modalClose }}"
                    >
                        <i
                            class="fa-solid fa-xmark"
                            aria-hidden="true"
                        ></i>
                    </button>
                </div>

                <div class="p-5 space-y-5 sm:p-6">
                    <div>
                        <div class="p-4 mb-4 border border-blue-200 bg-blue-50 rounded-xl dark:border-blue-800 dark:bg-blue-900/10">
                            <div class="flex items-start gap-3">
                                <i
                                    class="mt-0.5 text-blue-600 fa-solid fa-circle-info dark:text-blue-400"
                                    aria-hidden="true"
                                ></i>

                                <div>
                                    <p class="text-sm font-medium text-blue-800 dark:text-blue-300">
                                        Purchase request only
                                    </p>

                                    <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                                        Submitting this request does not add inventory or place an order with a supplier.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <label
                            for="purchase-request-reason"
                            class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            Reason
                        </label>

                        <textarea
                            id="purchase-request-reason"
                            name="reason"
                            rows="3"
                            maxlength="1000"
                            class="{{ $inputBase }}"
                            placeholder="Why does this branch need these products?"
                        >{{ old('reason') }}</textarea>

                        @error('reason', 'createPurchaseRequest')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Optional, but recommended so reviewers understand the request.
                        </p>
                    </div>

                    <div>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                                    Requested Products
                                </h3>

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Add the products and quantities required by this branch.
                                </p>
                            </div>

                            <button
                                type="button"
                                @click="addItem()"
                                class="{{ $btn['secondary'] }}"
                            >
                                <i
                                    class="text-xs fa-solid fa-plus"
                                    aria-hidden="true"
                                ></i>

                                Add Product
                            </button>
                        </div>

                        <div class="mt-4 space-y-3">
                            <template
                                x-for="(item, index) in items"
                                :key="item.key"
                            >
                                <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,1fr)_160px_44px] sm:items-end">
                                        <div>
                                            <label
                                                :for="'purchase-product-' + item.key"
                                                class="text-sm font-medium text-gray-700 dark:text-gray-200"
                                            >
                                                Product
                                            </label>

                                            <select
                                                :id="'purchase-product-' + item.key"
                                                :name="'items[' + index + '][product_id]'"
                                                x-model="item.product_id"
                                                required
                                                class="{{ $inputBase }}"
                                            >
                                                <option value="">
                                                    Select a product
                                                </option>

                                                @foreach($products as $product)
                                                    <option value="{{ $product->id }}">
                                                        {{ $product->name }}
                                                        @if($product->purchase_unit)
                                                            — {{ $product->purchase_unit }}
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label
                                                :for="'purchase-quantity-' + item.key"
                                                class="text-sm font-medium text-gray-700 dark:text-gray-200"
                                            >
                                                Quantity
                                            </label>

                                            <input
                                                :id="'purchase-quantity-' + item.key"
                                                type="number"
                                                :name="'items[' + index + '][quantity]'"
                                                x-model="item.quantity"
                                                min="0.001"
                                                step="0.001"
                                                required
                                                class="{{ $inputBase }}"
                                            >
                                        </div>

                                        <button
                                            type="button"
                                            @click="removeItem(index)"
                                            :disabled="items.length === 1"
                                            aria-label="Remove product from request"
                                            class="inline-flex items-center justify-center shrink-0 w-11 h-11 text-red-600 border border-red-200 rounded-xl hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-900/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800"
                                        >
                                            <i
                                                class="fa-solid fa-trash"
                                                aria-hidden="true"
                                            ></i>
                                        </button>
                                        @error('items', 'createPurchaseRequest')
                                            <p class="mt-1 text-sm text-red-600 sm:col-span-3 dark:text-red-400">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>
                                </div>
                            </template>
                        </div>
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
                        class="{{ $btn['primary'] }}"
                    >
                        <i
                            class="text-xs fa-solid fa-paper-plane"
                            aria-hidden="true"
                        ></i>

                        Submit Request
                    </button>
                </div>
            </form>
        </x-app-modal>
    @endif

    <x-app-modal
        show="detailsOpen"
        max-width="lg"
        labelledby="purchaseRequestDetailsTitle"
    >
        <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <div class="min-w-0">
                <h2
                    id="purchaseRequestDetailsTitle"
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Purchase Request Details
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    x-text="request.reference"
                ></p>
            </div>

            <button
                type="button"
                @click="detailsOpen = false"
                aria-label="Close purchase request details dialog"
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
                        Requester
                    </p>

                    <p
                        class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        x-text="request.requester || 'Unknown'"
                    ></p>
                </div>

                <div>
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                        Requested
                    </p>

                    <p
                        class="mt-1 text-sm text-gray-700 dark:text-gray-200"
                        x-text="request.created_at || '—'"
                    ></p>
                </div>
            </div>

            <div>
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                    Status
                </p>

                <span
                    class="inline-flex items-center px-3 py-1.5 mt-2 text-xs font-medium rounded-full"
                    :class="request.status_badge"
                    x-text="request.status_label"
                ></span>
            </div>

            <div>
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                    Reason
                </p>

                <p
                    class="mt-1 text-sm text-gray-700 whitespace-pre-line dark:text-gray-200"
                    x-text="request.reason || 'No reason provided.'"
                ></p>
            </div>

            <div>
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                    Requested Products
                </p>

                <div class="mt-2 overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700">
                    <template
                        x-for="(item, index) in request.items"
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

            <template x-if="request.reviewer">
                <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                        Review Information
                    </p>

                    <div class="mt-3 space-y-2">
                        <div class="flex flex-col gap-1 sm:flex-row sm:justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                Reviewed by
                            </span>

                            <span
                                class="text-sm font-medium text-gray-900 dark:text-white"
                                x-text="request.reviewer"
                            ></span>
                        </div>

                        <div
                            x-show="request.reviewed_at"
                            class="flex flex-col gap-1 sm:flex-row sm:justify-between"
                        >
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                Reviewed
                            </span>

                            <span
                                class="text-sm text-gray-700 dark:text-gray-200"
                                x-text="request.reviewed_at"
                            ></span>
                        </div>

                        <div
                            x-show="request.converted_at"
                            class="flex flex-col gap-1 sm:flex-row sm:justify-between"
                        >
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                Converted
                            </span>

                            <span
                                class="text-sm text-gray-700 dark:text-gray-200"
                                x-text="request.converted_at"
                            ></span>
                        </div>

                        <div
                            x-show="request.review_reason"
                            class="pt-3 mt-3 border-t border-gray-200 dark:border-gray-700"
                        >
                            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                Review Note
                            </p>

                            <p
                                class="mt-1 text-sm text-gray-700 whitespace-pre-line dark:text-gray-200"
                                x-text="request.review_reason"
                            ></p>
                        </div>
                    </div>
                </div>
            </template>
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

    @if($canReview)
        <x-app-modal
            show="approveOpen"
            max-width="lg"
            labelledby="approvePurchaseRequestTitle"
        >
            <form
                method="POST"
                :action="'{{ $approveUrl }}'.replace('__ID__', reviewRequest.id)"
            >
                @csrf
                @method('PATCH')

                <input
                    type="hidden"
                    name="purchase_request_id"
                    :value="reviewRequest.id"
                >

                <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div class="min-w-0">
                        <h2
                            id="approvePurchaseRequestTitle"
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Approve Purchase Request
                        </h2>

                        <p
                            class="mt-1 text-sm text-gray-500 truncate dark:text-gray-400"
                            x-text="reviewRequest.reference"
                        ></p>
                    </div>

                    <button
                        type="button"
                        @click="approveOpen = false"
                        aria-label="Close approval dialog"
                        class="{{ $modalClose }}"
                    >
                        <i
                            class="fa-solid fa-xmark"
                            aria-hidden="true"
                        ></i>
                    </button>
                </div>

                <div class="p-5 space-y-4 sm:p-6">
                    @if($errors->approvePurchaseRequest->any())
                        <div class="p-4 border border-red-200 bg-red-50 rounded-xl dark:border-red-800 dark:bg-red-900/10">
                            @foreach($errors->approvePurchaseRequest->all() as $error)
                                <p class="text-sm text-red-700 dark:text-red-300">
                                    {{ $error }}
                                </p>
                            @endforeach
                        </div>
                    @endif

                    <div class="p-4 border border-emerald-200 bg-emerald-50 rounded-xl dark:border-emerald-800 dark:bg-emerald-900/10">
                        <div class="flex items-start gap-3">
                            <div class="flex items-center justify-center shrink-0 w-11 h-11 rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                <i
                                    class="fa-solid fa-check"
                                    aria-hidden="true"
                                ></i>
                            </div>

                            <div class="min-w-0">
                                <p class="text-sm font-medium text-emerald-800 dark:text-emerald-300">
                                    Approve this request?
                                </p>

                                <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-300">
                                    Approval allows Procurement to use this request when preparing a purchase order.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label
                            for="approve-review-reason"
                            class="text-sm font-medium text-gray-700 dark:text-gray-200"
                        >
                            Review Note
                        </label>

                        <textarea
                            id="approve-review-reason"
                            name="review_reason"
                            rows="3"
                            maxlength="1000"
                            class="{{ $inputBase }}"
                            placeholder="Optional approval note"
                        >{{ $approveOpen ? old('review_reason') : '' }}</textarea>

                        @error('review_reason', 'approvePurchaseRequest')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                <div class="{{ $modalFooter }}">
                    <button
                        type="button"
                        @click="approveOpen = false"
                        class="{{ $btn['secondary'] }}"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="{{ $btn['approve'] }}"
                    >
                        <i
                            class="text-xs fa-solid fa-check"
                            aria-hidden="true"
                        ></i>

                        Approve Request
                    </button>
                </div>
            </form>
        </x-app-modal>

        <x-app-modal
            show="rejectOpen"
            max-width="lg"
            labelledby="rejectPurchaseRequestTitle"
        >
            <form
                method="POST"
                :action="'{{ $rejectUrl }}'.replace('__ID__', reviewRequest.id)"
            >
                @csrf
                @method('PATCH')

                <input
                    type="hidden"
                    name="purchase_request_id"
                    :value="reviewRequest.id"
                >

                <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div class="min-w-0">
                        <h2
                            id="rejectPurchaseRequestTitle"
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Reject Purchase Request
                        </h2>

                        <p
                            class="mt-1 text-sm text-gray-500 truncate dark:text-gray-400"
                            x-text="reviewRequest.reference"
                        ></p>
                    </div>

                    <button
                        type="button"
                        @click="rejectOpen = false"
                        aria-label="Close rejection dialog"
                        class="{{ $modalClose }}"
                    >
                        <i
                            class="fa-solid fa-xmark"
                            aria-hidden="true"
                        ></i>
                    </button>
                </div>

                <div class="p-5 space-y-4 sm:p-6">
                    @if($errors->rejectPurchaseRequest->any())
                        <div class="p-4 border border-red-200 bg-red-50 rounded-xl dark:border-red-800 dark:bg-red-900/10">
                            @foreach($errors->rejectPurchaseRequest->all() as $error)
                                <p class="text-sm text-red-700 dark:text-red-300">
                                    {{ $error }}
                                </p>
                            @endforeach
                        </div>
                    @endif

                    <div class="p-4 border border-red-200 bg-red-50 rounded-xl dark:border-red-800 dark:bg-red-900/10">
                        <div class="flex items-start gap-3">
                            <div class="flex items-center justify-center text-red-700 bg-red-100 shrink-0 w-11 h-11 rounded-xl dark:bg-red-900/30 dark:text-red-300">
                                <i
                                    class="fa-solid fa-ban"
                                    aria-hidden="true"
                                ></i>
                            </div>

                            <div class="min-w-0">
                                <p class="text-sm font-medium text-red-800 dark:text-red-300">
                                    Reject this request?
                                </p>

                                <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                                    The request will remain in history as rejected and cannot be reviewed again.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label
                            for="reject-review-reason"
                            class="text-sm font-medium text-gray-700 dark:text-gray-200"
                        >
                            Rejection Reason
                        </label>

                        <textarea
                            id="reject-review-reason"
                            name="review_reason"
                            rows="4"
                            maxlength="1000"
                            required
                            class="{{ $inputBase }}"
                            placeholder="Explain why this request is being rejected"
                        >{{ $rejectOpen ? old('review_reason') : '' }}</textarea>

                        @error('review_reason', 'rejectPurchaseRequest')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                <div class="{{ $modalFooter }}">
                    <button
                        type="button"
                        @click="rejectOpen = false"
                        class="{{ $btn['secondary'] }}"
                    >
                        Keep Pending
                    </button>

                    <button
                        type="submit"
                        class="{{ $btn['reject'] }}"
                    >
                        <i
                            class="text-xs fa-solid fa-ban"
                            aria-hidden="true"
                        ></i>

                        Reject Request
                    </button>
                </div>
            </form>
        </x-app-modal>
    @endif
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('purchaseRequestPage', () => ({
            createOpen: @json((bool) $createOpen),
            detailsOpen: false,
            approveOpen: @json((bool) $approveOpen),
            rejectOpen: @json((bool) $rejectOpen),

            request: {
                id: null,
                reference: '',
                requester: '',
                reason: '',
                status: '',
                status_label: '',
                status_badge: '',
                created_at: '',
                reviewer: '',
                review_reason: '',
                reviewed_at: '',
                converted_at: '',
                items: []
            },

            reviewRequest: @json($oldReviewPayload),

            items: @json($oldItems),

            addItem() {
                this.items.push({
                    key: 'new-' + Date.now() + '-' + Math.random(),
                    product_id: '',
                    quantity: 1
                });
            },

            removeItem(index) {
                if (this.items.length <= 1) {
                    return;
                }

                this.items.splice(index, 1);
            },

            openDetails(data) {
                this.request = {
                    id: data.id,
                    reference: data.reference ?? '',
                    requester: data.requester ?? '',
                    reason: data.reason ?? '',
                    status: data.status ?? '',
                    status_label: data.status_label ?? '',
                    status_badge: data.status_badge ?? '',
                    created_at: data.created_at ?? '',
                    reviewer: data.reviewer ?? '',
                    review_reason: data.review_reason ?? '',
                    reviewed_at: data.reviewed_at ?? '',
                    converted_at: data.converted_at ?? '',
                    items: data.items ?? []
                };

                this.detailsOpen = true;
            },

            openApprove(data) {
                this.reviewRequest = {
                    id: data.id,
                    reference: data.reference ?? '',
                    requester: data.requester ?? '',
                    reason: data.reason ?? '',
                    status: data.status ?? 'pending'
                };

                this.approveOpen = true;
            },

            openReject(data) {
                this.reviewRequest = {
                    id: data.id,
                    reference: data.reference ?? '',
                    requester: data.requester ?? '',
                    reason: data.reason ?? '',
                    status: data.status ?? 'pending'
                };

                this.rejectOpen = true;
            }
        }));
    });
</script>
@endsection
