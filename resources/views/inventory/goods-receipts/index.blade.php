@extends('layouts.app')

@section('title', 'Goods Receipts')

@section('content')
@php
    $user = auth()->user();

    $canReceiveGoods = $user->hasBranchPermission('receive goods');

    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'rowPrimary' => $btnBase . ' bg-amber-700 text-white hover:bg-amber-800',
        'secondary' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700',
    ];

    $inputClass = 'w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white';

    $receivablePayloads = $receivablePurchaseOrders->mapWithKeys(function ($purchaseOrder) {
        return [
            $purchaseOrder->id => [
                'id' => $purchaseOrder->id,
                'supplier' => $purchaseOrder->supplier?->name ?? 'Unknown Supplier',
                'status' => $purchaseOrder->status,
                'expected_delivery_date' => $purchaseOrder->expected_delivery_date?->format('Y-m-d'),
                'store_url' => route('inventory.goods-receipts.store', $purchaseOrder),
                'items' => $purchaseOrder->items->map(function ($item) {
                    $accepted = (float) $item->goodsReceiptItems->sum('accepted_quantity');
                    $ordered = (float) $item->quantity;
                    $outstanding = max(round($ordered - $accepted, 3), 0);

                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product?->name ?? 'Unknown Product',
                        'ordered' => $ordered,
                        'accepted' => $accepted,
                        'outstanding' => $outstanding,
                        'unit' => $item->unit ?: 'unit',
                    ];
                })->values(),
            ],
        ];
    });

    $oldPurchaseOrderId = old('purchase_order_id');
    $receiveErrorBag = $errors->getBag('receiveGoods');
@endphp

<div
    class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl"
    x-data="goodsReceiptPage()"
>
    <x-page-header
        title="Goods Receipts"
        subtitle="Receive supplier deliveries, record rejected quantities, and update physical branch inventory."
    >
        <x-slot name="right">
            @if($canReceiveGoods)
                <button
                    type="button"
                    @click="openReceive()"
                    class="{{ $btn['primary'] }}"
                    @disabled($receivablePurchaseOrders->isEmpty())
                >
                    <i class="fa-solid fa-box-open" aria-hidden="true"></i>
                    Receive Goods
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="p-4 border border-blue-200 shadow-sm sm:p-5 bg-blue-50 rounded-2xl dark:bg-blue-900/10 dark:border-blue-800">
        <div class="flex items-start gap-3">
            <div class="flex items-center justify-center w-10 h-10 text-blue-700 bg-blue-100 rounded-xl shrink-0 dark:bg-blue-900/40 dark:text-blue-300">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            </div>

            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    Inventory changes only when goods are received
                </h2>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Purchase Orders represent supplier orders. Posting a Goods Receipt creates accepted stock,
                    records its batch, and updates the branch inventory. Rejected quantities never enter usable stock.
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Goods Receipts
            </p>

            <p class="mt-3 text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                {{ $goodsReceipts->total() }}
            </p>
        </div>

        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Awaiting Receipt
            </p>

            <p class="mt-3 text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                {{ $receivablePurchaseOrders->count() }}
            </p>
        </div>

        <div class="p-4 border shadow-sm sm:p-5 bg-amber-50 border-amber-200 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800">
            <p class="text-xs font-semibold tracking-wide uppercase text-amber-700 dark:text-amber-300">
                Partial Deliveries
            </p>

            <p class="mt-3 text-2xl font-semibold sm:text-3xl text-amber-900 dark:text-amber-200">
                {{ $receivablePurchaseOrders->where('status', 'partially_received')->count() }}
            </p>
        </div>
    </div>

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                Receipt History
            </h2>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Posted supplier deliveries for the currently selected branch.
            </p>
        </div>

        @if($goodsReceipts->isEmpty())
            <div class="flex flex-col items-center justify-center px-4 py-16 text-center">
                <div class="flex items-center justify-center w-12 h-12 mb-3 text-gray-400 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-500">
                    <i class="text-lg fa-solid fa-truck-ramp-box" aria-hidden="true"></i>
                </div>

                <p class="text-sm font-medium text-gray-900 dark:text-white">
                    No goods receipts yet
                </p>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Posted supplier deliveries will appear here.
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-gray-900/40 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3">GRN</th>
                            <th class="px-6 py-3">Purchase Order</th>
                            <th class="px-6 py-3">Supplier</th>
                            <th class="px-6 py-3">Received By</th>
                            <th class="px-6 py-3">Items</th>
                            <th class="px-6 py-3">Received At</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($goodsReceipts as $receipt)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                                <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                    GRN-{{ str_pad($receipt->id, 5, '0', STR_PAD_LEFT) }}
                                </td>

                                <td class="px-6 py-4">
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        PO-{{ str_pad($receipt->purchase_order_id, 5, '0', STR_PAD_LEFT) }}
                                    </span>

                                    @if($receipt->delivery_reference)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $receipt->delivery_reference }}
                                        </p>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
                                    {{ $receipt->purchaseOrder?->supplier?->name ?? 'Unknown Supplier' }}
                                </td>

                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
                                    {{ $receipt->receiver?->name ?? 'Unknown User' }}
                                </td>

                                <td class="px-6 py-4">
                                    <div class="space-y-1">
                                        @foreach($receipt->items as $item)
                                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                                {{ $item->product?->name ?? 'Unknown Product' }}
                                                —
                                                {{ rtrim(rtrim(number_format((float) $item->accepted_quantity, 3, '.', ''), '0'), '.') }}
                                                {{ $item->unit }}
                                                accepted

                                                @if((float) $item->rejected_quantity > 0)
                                                    <span class="text-red-600 dark:text-red-400">
                                                        · {{ rtrim(rtrim(number_format((float) $item->rejected_quantity, 3, '.', ''), '0'), '.') }}
                                                        rejected
                                                    </span>
                                                @endif
                                            </p>
                                        @endforeach
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
                                    {{ $receipt->received_at?->format('M d, Y h:i A') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($goodsReceipts->hasPages())
                <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
                    {{ $goodsReceipts->links() }}
                </div>
            @endif
        @endif
    </div>

    @if($canReceiveGoods)
        <x-app-modal
            show="receiveOpen"
            max-width="4xl"
            labelledby="receiveGoodsTitle"
        >
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 id="receiveGoodsTitle" class="text-lg font-semibold text-gray-900 dark:text-white">
                        Receive Goods
                    </h2>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Record quantities physically delivered by the supplier.
                    </p>
                </div>

                <button
                    type="button"
                    @click="closeReceive()"
                    aria-label="Close receive goods modal"
                    class="inline-flex items-center justify-center w-11 h-11 text-gray-500 rounded-xl hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]"
                >
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <form
                method="POST"
                :action="selectedOrder?.store_url || '#'"
                class="p-5 space-y-5 sm:p-6"
            >
                @csrf

                <input
                    type="hidden"
                    name="purchase_order_id"
                    :value="selectedOrder?.id || ''"
                >

                <div>
                    <label for="purchase_order_select" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Purchase Order
                    </label>

                    <select
                        id="purchase_order_select"
                        x-model="selectedOrderId"
                        @change="selectOrder(selectedOrderId)"
                        class="{{ $inputClass }}"
                        required
                    >
                        <option value="">Select purchase order</option>

                        @foreach($receivablePurchaseOrders as $purchaseOrder)
                            <option value="{{ $purchaseOrder->id }}">
                                PO-{{ str_pad($purchaseOrder->id, 5, '0', STR_PAD_LEFT) }}
                                · {{ $purchaseOrder->supplier?->name ?? 'Unknown Supplier' }}
                                · {{ ucwords(str_replace('_', ' ', $purchaseOrder->status)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <template x-if="selectedOrder">
                    <div class="p-4 border border-gray-200 rounded-2xl bg-gray-50 dark:bg-gray-900/40 dark:border-gray-700">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                    Purchase Order
                                </p>

                                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                    PO-<span x-text="String(selectedOrder.id).padStart(5, '0')"></span>
                                </p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                    Supplier
                                </p>

                                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white" x-text="selectedOrder.supplier"></p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                    Status
                                </p>

                                <p class="mt-1 text-sm font-medium text-gray-900 capitalize dark:text-white"
                                   x-text="selectedOrder.status.replaceAll('_', ' ')"></p>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="delivery_reference" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Delivery Reference
                        </label>

                        <input
                            id="delivery_reference"
                            type="text"
                            name="delivery_reference"
                            value="{{ old('delivery_reference') }}"
                            placeholder="Example: DR-2026-001"
                            class="{{ $inputClass }}"
                        >
                    </div>

                    <div>
                        <label for="received_at" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Received At
                        </label>

                        <input
                            id="received_at"
                            type="datetime-local"
                            name="received_at"
                            value="{{ old('received_at') }}"
                            class="{{ $inputClass }}"
                        >
                    </div>
                </div>

                <template x-if="selectedOrder">
                    <div class="space-y-4">
                        <template x-for="(item, index) in selectedOrder.items" :key="item.id">
                            <div class="p-4 bg-white border border-gray-200 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                                <input
                                    type="hidden"
                                    :name="`items[${index}][purchase_order_item_id]`"
                                    :value="item.id"
                                >

                                <div class="flex flex-col gap-2 mb-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white" x-text="item.product_name"></p>

                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Ordered:
                                            <span x-text="quantity(item.ordered)"></span>
                                            <span x-text="item.unit"></span>
                                            · Accepted:
                                            <span x-text="quantity(item.accepted)"></span>
                                            · Outstanding:
                                            <span class="font-medium text-amber-700 dark:text-amber-300"
                                                  x-text="`${quantity(item.outstanding)} ${item.unit}`"></span>
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Delivered
                                        </label>

                                        <input
                                            type="number"
                                            min="0.001"
                                            step="0.001"
                                            :max="item.outstanding"
                                            :name="`items[${index}][received_quantity]`"
                                            x-model.number="item.received_quantity"
                                            class="{{ $inputClass }}"
                                            required
                                        >
                                    </div>

                                    <div>
                                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Accepted
                                        </label>

                                        <input
                                            type="number"
                                            min="0"
                                            step="0.001"
                                            :max="item.outstanding"
                                            :name="`items[${index}][accepted_quantity]`"
                                            x-model.number="item.accepted_quantity"
                                            class="{{ $inputClass }}"
                                            required
                                        >
                                    </div>

                                    <div>
                                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Rejected
                                        </label>

                                        <input
                                            type="number"
                                            min="0"
                                            step="0.001"
                                            :name="`items[${index}][rejected_quantity]`"
                                            x-model.number="item.rejected_quantity"
                                            class="{{ $inputClass }}"
                                            required
                                        >
                                    </div>
                                </div>

                                <div class="grid gap-4 mt-4 sm:grid-cols-2">
                                    <div x-show="Number(item.accepted_quantity || 0) > 0">
                                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Batch Number
                                        </label>

                                        <input
                                            type="text"
                                            :name="`items[${index}][batch_number]`"
                                            x-model="item.batch_number"
                                            class="{{ $inputClass }}"
                                            :required="Number(item.accepted_quantity || 0) > 0"
                                        >
                                    </div>

                                    <div x-show="Number(item.rejected_quantity || 0) > 0">
                                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Rejection Reason
                                        </label>

                                        <input
                                            type="text"
                                            :name="`items[${index}][rejection_reason]`"
                                            x-model="item.rejection_reason"
                                            class="{{ $inputClass }}"
                                            :required="Number(item.rejected_quantity || 0) > 0"
                                        >
                                    </div>
                                </div>

                                <div
                                    class="p-3 mt-4 text-sm border rounded-xl"
                                    :class="itemQuantitiesValid(item)
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/10 dark:text-emerald-300'
                                        : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/10 dark:text-amber-300'"
                                >
                                    <template x-if="itemQuantitiesValid(item)">
                                        <span>
                                            Delivered quantity matches accepted + rejected quantity.
                                        </span>
                                    </template>

                                    <template x-if="!itemQuantitiesValid(item)">
                                        <span>
                                            Delivered must equal accepted + rejected.
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <div>
                    <label for="receipt_notes" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Notes
                    </label>

                    <textarea
                        id="receipt_notes"
                        name="notes"
                        rows="3"
                        class="{{ $inputClass }}"
                    >{{ old('notes') }}</textarea>
                </div>

                @if($receiveErrorBag->any())
                    <div class="p-4 border border-red-200 bg-red-50 rounded-2xl dark:border-red-800 dark:bg-red-900/10">
                        <p class="text-sm font-medium text-red-700 dark:text-red-300">
                            {{ $receiveErrorBag->first() }}
                        </p>
                    </div>
                @endif

                <div class="flex flex-col-reverse gap-3 pt-4 border-t border-gray-200 sm:flex-row sm:justify-end dark:border-gray-700">
                    <button
                        type="button"
                        @click="closeReceive()"
                        class="{{ $btn['secondary'] }}"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="{{ $btn['primary'] }}"
                        :disabled="!canSubmit"
                    >
                        <i class="fa-solid fa-box-open" aria-hidden="true"></i>
                        Post Goods Receipt
                    </button>
                </div>
            </form>
        </x-app-modal>
    @endif
</div>

<script>
function goodsReceiptPage() {
    const orders = @json($receivablePayloads);
    const oldPurchaseOrderId = @json($oldPurchaseOrderId);
    const hasReceiveErrors = @json($receiveErrorBag->any());

    return {
        receiveOpen: hasReceiveErrors,
        selectedOrderId: oldPurchaseOrderId ? String(oldPurchaseOrderId) : '',
        selectedOrder: null,

        init() {
            if (this.selectedOrderId) {
                this.selectOrder(this.selectedOrderId);
            }
        },

        openReceive() {
            this.receiveOpen = true;
        },

        closeReceive() {
            this.receiveOpen = false;
        },

        selectOrder(id) {
            if (!id || !orders[id]) {
                this.selectedOrder = null;
                return;
            }

            this.selectedOrderId = String(id);

            this.selectedOrder = JSON.parse(JSON.stringify(orders[id]));

            this.selectedOrder.items = this.selectedOrder.items.map(item => ({
                ...item,
                received_quantity: item.outstanding,
                accepted_quantity: item.outstanding,
                rejected_quantity: 0,
                batch_number: '',
                rejection_reason: '',
            }));
        },

        quantity(value) {
            const number = Number(value || 0);

            return number.toFixed(3)
                .replace(/\.?0+$/, '');
        },

        itemQuantitiesValid(item) {
            const received = Number(item.received_quantity || 0);
            const accepted = Number(item.accepted_quantity || 0);
            const rejected = Number(item.rejected_quantity || 0);

            return received > 0 &&
                accepted >= 0 &&
                rejected >= 0 &&
                Math.abs(received - (accepted + rejected)) < 0.0005 &&
                accepted <= Number(item.outstanding || 0);
        },

        get canSubmit() {
            if (!this.selectedOrder || !this.selectedOrder.items.length) {
                return false;
            }

            return this.selectedOrder.items.every(item =>
                this.itemQuantitiesValid(item) &&
                (
                    Number(item.accepted_quantity || 0) === 0 ||
                    String(item.batch_number || '').trim() !== ''
                ) &&
                (
                    Number(item.rejected_quantity || 0) === 0 ||
                    String(item.rejection_reason || '').trim() !== ''
                )
            );
        },
    };
}
</script>
@endsection
