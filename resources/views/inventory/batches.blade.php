@extends('layouts.app')

@section('title', 'Inventory Batches')

@section('content')
@php
    $user = auth()->user();
    $canEditInventory = $user?->hasBranchPermission('edit inventory items') ?? false;

    $lossFailedBatch = null;

    if ($errors->batchLoss->any() && old('batch_id')) {
        $lossFailedBatch = $batches->firstWhere('id', (int) old('batch_id'));
    }

    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'secondary' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
    ];
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl"
    x-data="{
        lossOpen: false,

        lossBatch: {
            id: null,
            product: '',
            batch_number: '',
            remaining_quantity: 0,
            unit: 'pcs',
            expired: false
        },

        openLoss(batch) {
            this.lossBatch = batch;
            this.lossOpen = true;
        },

        closeLoss() {
            this.lossOpen = false;
        }
    }"
    x-init="@if($lossFailedBatch)
        openLoss(@js([
            'id' => $lossFailedBatch->id,
            'product' => $lossFailedBatch->product?->name ?? 'Deleted Product',
            'batch_number' => $lossFailedBatch->batch_number,
            'remaining_quantity' => (float) $lossFailedBatch->remaining_quantity,
            'unit' => $lossFailedBatch->product?->usage_unit ?? $lossFailedBatch->product?->unit ?? 'pcs',
            'expired' => $lossFailedBatch->expiration_date
                ? $lossFailedBatch->expiration_date->lt(today())
                : false,
        ]))
    @endif">

    <x-page-header
        title="Product Batches"
        subtitle="Monitor received inventory, remaining quantities, and product expiration dates."
    />

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">

        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Active
            </p>

            <h3 class="mt-3 text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                {{ $summary['active'] }}
            </h3>

            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Healthy active batches
            </p>
        </div>

        <div class="p-4 border shadow-sm sm:p-5 bg-amber-50 border-amber-200 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800">
            <p class="text-xs font-semibold tracking-wide uppercase text-amber-700 dark:text-amber-300">
                Expiring Soon
            </p>

            <h3 class="mt-3 text-2xl font-semibold sm:text-3xl text-amber-900 dark:text-amber-200">
                {{ $summary['expiring'] }}
            </h3>

            <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                Within the next 30 days
            </p>
        </div>

        <div class="p-4 border border-red-200 shadow-sm sm:p-5 bg-red-50 rounded-2xl dark:bg-red-900/10 dark:border-red-800">
            <p class="text-xs font-semibold tracking-wide text-red-700 uppercase dark:text-red-300">
                Expired
            </p>

            <h3 class="mt-3 text-2xl font-semibold text-red-900 sm:text-3xl dark:text-red-200">
                {{ $summary['expired'] }}
            </h3>

            <p class="mt-1 text-xs text-red-700 dark:text-red-300">
                Remaining expired stock
            </p>
        </div>

        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Depleted
            </p>

            <h3 class="mt-3 text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                {{ $summary['depleted'] }}
            </h3>

            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                No remaining quantity
            </p>
        </div>

    </div>

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">

        <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    Batch Inventory
                </h2>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Batches are ordered by expiration date to support FEFO inventory handling.
                </p>
            </div>
        </div>

        <form method="GET"
            action="{{ route('inventory.batches') }}"
            class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">

            <div class="flex flex-col gap-4 xl:flex-row xl:items-end">

                <div class="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">

                    <div>
                        <label for="batch_search"
                            class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Search
                        </label>

                        <input type="search"
                            id="batch_search"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Product or batch number"
                            class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>

                    <div>
                        <label for="product_id"
                            class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Product
                        </label>

                        <select id="product_id"
                            name="product_id"
                            class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                            <option value="">All Products</option>

                            @foreach($products as $product)
                                <option value="{{ $product->id }}"
                                    @selected((string) request('product_id') === (string) $product->id)>
                                    {{ $product->name }}
                                </option>
                            @endforeach

                        </select>
                    </div>

                    <div>
                        <label for="status"
                            class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Status
                        </label>

                        <select id="status"
                            name="status"
                            class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                            <option value="">All Statuses</option>

                            <option value="active"
                                @selected(request('status') === 'active')>
                                Active
                            </option>

                            <option value="expiring"
                                @selected(request('status') === 'expiring')>
                                Expiring Soon
                            </option>

                            <option value="expired"
                                @selected(request('status') === 'expired')>
                                Expired
                            </option>

                            <option value="depleted"
                                @selected(request('status') === 'depleted')>
                                Depleted
                            </option>

                        </select>
                    </div>

                </div>

                <div class="flex items-center gap-2 xl:shrink-0">

                    <button type="submit"
                        class="{{ $btn['primary'] }} whitespace-nowrap">
                        <i class="fa-solid fa-filter" aria-hidden="true"></i>
                        Apply
                    </button>

                    <a href="{{ route('inventory.batches') }}"
                        class="{{ $btn['secondary'] }} whitespace-nowrap">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        Clear
                    </a>

                </div>

            </div>

        </form>

        <div class="lg:overflow-x-auto">

            <table role="table"
                class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">

                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">

                    <tr role="row">

                        <th role="columnheader"
                            class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Product
                        </th>

                        <th role="columnheader"
                            class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Batch
                        </th>

                        <th role="columnheader"
                            class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Received
                        </th>

                        <th role="columnheader"
                            class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Remaining
                        </th>

                        <th role="columnheader"
                            class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Received Date
                        </th>

                        <th role="columnheader"
                            class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Expiration
                        </th>

                        <th role="columnheader"
                            class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Status
                        </th>

                        @if($canEditInventory)
                            <th role="columnheader"
                                class="px-4 py-3 text-xs font-medium text-center text-gray-500 uppercase dark:text-gray-400">
                                Actions
                            </th>
                        @endif

                    </tr>

                </thead>

                <tbody role="rowgroup"
                    class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">

                    @forelse($batches as $batch)
                        @php
                            $received = (float) $batch->received_quantity;
                            $remaining = (float) $batch->remaining_quantity;

                            $isExpired = $batch->expiration_date
                                ? $batch->expiration_date->lt(today())
                                : false;

                            $isExpiringSoon = $batch->expiration_date
                                && !$isExpired
                                && $batch->expiration_date->lte(today()->addDays(30));

                            $statusLabel = 'Active';
                            $statusClass = 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300';

                            if ($remaining <= 0) {
                                $statusLabel = 'Depleted';
                                $statusClass = 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
                            } elseif ($isExpired) {
                                $statusLabel = 'Expired';
                                $statusClass = 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
                            } elseif ($isExpiringSoon) {
                                $statusLabel = 'Expiring Soon';
                                $statusClass = 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
                            }
                        @endphp

                        <tr role="row"
                            class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/40">

                            <td role="cell"
                                data-label="Product"
                                class="px-4 py-4">

                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $batch->product?->name ?? 'Deleted Product' }}
                                </p>

                                @if($batch->product?->sku)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        SKU: {{ $batch->product->sku }}
                                    </p>
                                @endif

                            </td>

                            <td role="cell"
                                data-label="Batch"
                                class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">

                                {{ $batch->batch_number }}

                            </td>

                            <td role="cell"
                                data-label="Received"
                                class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">

                                {{ rtrim(rtrim(number_format($received, 3, '.', ''), '0'), '.') }}
                                {{ $batch->product?->usage_unit ?? $batch->product?->unit ?? 'pcs' }}

                            </td>

                            <td role="cell"
                                data-label="Remaining"
                                class="px-4 py-4">

                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ rtrim(rtrim(number_format($remaining, 3, '.', ''), '0'), '.') }}
                                    {{ $batch->product?->usage_unit ?? $batch->product?->unit ?? 'pcs' }}
                                </p>

                                @if($received > 0)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ number_format(($remaining / $received) * 100, 0) }}% remaining
                                    </p>
                                @endif

                            </td>

                            <td role="cell"
                                data-label="Received Date"
                                class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">

                                {{ $batch->received_at?->format('M d, Y') ?? '—' }}

                            </td>

                            <td role="cell"
                                data-label="Expiration"
                                class="px-4 py-4">

                                @if($batch->expiration_date)

                                    <p class="text-sm text-gray-900 dark:text-white">
                                        {{ $batch->expiration_date->format('M d, Y') }}
                                    </p>

                                    @if($remaining > 0 && !$isExpired)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            @php
                                                $daysRemaining = today()->diffInDays(
                                                    $batch->expiration_date->copy()->startOfDay()
                                                );
                                            @endphp

                                            @if($daysRemaining == 0)
                                                Expires today
                                            @elseif($daysRemaining == 1)
                                                1 day remaining
                                            @else
                                                {{ (int) $daysRemaining }} days remaining
                                            @endif
                                        </p>
                                    @endif

                                @else

                                    <span class="text-sm text-gray-400 dark:text-gray-500">
                                        No expiration
                                    </span>

                                @endif

                            </td>

                            <td role="cell"
                                data-label="Status"
                                class="px-4 py-4">

                                <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full {{ $statusClass }}">
                                    {{ $statusLabel }}
                                </span>

                            </td>

                            @if($canEditInventory)
                                <td role="cell"
                                    data-label="Actions"
                                    class="px-4 py-4 text-center rt-actions">

                                    @if($remaining > 0)

                                        <button type="button"
                                            @click="openLoss({
                                                id: {{ $batch->id }},
                                                product: @js($batch->product?->name ?? 'Deleted Product'),
                                                batch_number: @js($batch->batch_number),
                                                remaining_quantity: {{ (float) $batch->remaining_quantity }},
                                                unit: @js($batch->product?->usage_unit ?? $batch->product?->unit ?? 'pcs'),
                                                expired: {{ $isExpired ? 'true' : 'false' }}
                                            })"
                                            class="{{ $btn['secondary'] }}">

                                            <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>
                                            Record Loss
                                        </button>

                                    @else

                                        <span class="text-sm text-gray-400 dark:text-gray-500">
                                            —
                                        </span>

                                    @endif

                                </td>
                            @endif

                        </tr>

                    @empty

                        <tr role="row">

                            <td role="cell"
                                colspan="{{ $canEditInventory ? 8 : 7 }}"
                                class="px-6 py-12 text-sm text-center text-gray-500 rt-empty dark:text-gray-400">

                                <div class="flex flex-col items-center justify-center">

                                    <div class="flex items-center justify-center w-12 h-12 mb-3 text-gray-400 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-500">
                                        <i class="text-lg fa-solid fa-boxes-stacked" aria-hidden="true"></i>
                                    </div>

                                    <p>No product batches found.</p>

                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                        Receive stock from the Products page to create a batch.
                                    </p>

                                </div>

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        @if($batches->total() > 0)
            <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Showing

                        <span class="font-medium text-gray-700 dark:text-gray-200">
                            {{ $batches->firstItem() ?? 0 }}
                        </span>

                        to

                        <span class="font-medium text-gray-700 dark:text-gray-200">
                            {{ $batches->lastItem() ?? 0 }}
                        </span>

                        of

                        <span class="font-medium text-gray-700 dark:text-gray-200">
                            {{ $batches->total() }}
                        </span>

                        results
                    </p>

                    @if($batches->hasPages())
                        <div class="pagination-wrapper">
                            {{ $batches->links() }}
                        </div>
                    @endif

                </div>

            </div>
        @endif

    </div>

    @if($canEditInventory)
        <template x-teleport="body">

            <div x-show="lossOpen"
                x-transition.opacity
                class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50"
                :class="{ 'hidden': !lossOpen }"
                @keydown.escape.window="closeLoss()">

                <div class="flex items-start justify-center min-h-full p-4 sm:items-center">

                    <div role="dialog"
                        aria-modal="true"
                        aria-labelledby="batchLossTitle"
                        x-transition
                        class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">

                        <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">

                            <div>
                                <h2 id="batchLossTitle"
                                    class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Record Stock Loss
                                </h2>

                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Record damaged, wasted, or expired inventory from this batch.
                                </p>
                            </div>

                            <button type="button"
                                @click="closeLoss()"
                                aria-label="Close dialog"
                                class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">

                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>

                        </div>

                        <form method="POST"
                            :action="`{{ url('/inventory/batches') }}/${lossBatch.id}/record-loss`">

                            @csrf

                            <input type="hidden"
                                name="batch_id"
                                :value="lossBatch.id">

                            <div class="px-4 py-5 space-y-4 sm:px-6">

                                <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/40">

                                    <p class="text-sm font-semibold text-gray-900 dark:text-white"
                                        x-text="lossBatch.product">
                                    </p>

                                    <div class="mt-2 space-y-1 text-sm text-gray-500 dark:text-gray-400">

                                        <p>
                                            Batch:
                                            <span class="font-medium text-gray-700 dark:text-gray-200"
                                                x-text="lossBatch.batch_number">
                                            </span>
                                        </p>

                                        <p>
                                            Remaining:

                                            <span class="font-medium text-gray-700 dark:text-gray-200"
                                                x-text="lossBatch.remaining_quantity">
                                            </span>

                                            <span x-text="lossBatch.unit"></span>
                                        </p>

                                    </div>

                                </div>

                                <div>
                                    <label for="loss_type"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Loss Type
                                    </label>

                                    <select id="loss_type"
                                        name="loss_type"
                                        required
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                        <option value="">
                                            Select loss type
                                        </option>

                                        <option value="wastage"
                                            @selected(old('loss_type') === 'wastage')>
                                            Wastage
                                        </option>

                                        <option value="damage"
                                            @selected(old('loss_type') === 'damage')>
                                            Damage
                                        </option>

                                        <option value="expiry"
                                            @selected(old('loss_type') === 'expiry')
                                            :disabled="!lossBatch.expired">
                                            Expired Stock
                                        </option>

                                    </select>

                                    <p x-show="!lossBatch.expired"
                                        class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Expired Stock becomes available only after the batch expiration date.
                                    </p>

                                    @error('loss_type', 'batchLoss')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="loss_quantity"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Quantity
                                    </label>

                                    <div class="flex">

                                        <input type="number"
                                            id="loss_quantity"
                                            name="quantity"
                                            step="0.001"
                                            min="0.001"
                                            :max="lossBatch.remaining_quantity"
                                            value="{{ old('quantity') }}"
                                            required
                                            class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-l-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                        <div class="inline-flex items-center px-3 text-sm text-gray-500 border border-l-0 border-gray-300 rounded-r-xl bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400"
                                            x-text="lossBatch.unit">
                                        </div>

                                    </div>

                                    @error('quantity', 'batchLoss')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="loss_reason"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Reason
                                    </label>

                                    <textarea id="loss_reason"
                                        name="reason"
                                        rows="3"
                                        required
                                        placeholder="Explain what happened to this stock."
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">{{ old('reason') }}</textarea>

                                    @error('reason', 'batchLoss')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <div class="p-4 border border-amber-200 rounded-xl bg-amber-50 dark:border-amber-900/50 dark:bg-amber-900/10">

                                    <div class="flex items-start gap-3">

                                        <i class="mt-0.5 text-amber-600 fa-solid fa-triangle-exclamation dark:text-amber-400"
                                            aria-hidden="true">
                                        </i>

                                        <div>
                                            <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
                                                This reduces real inventory.
                                            </p>

                                            <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">
                                                The transaction is recorded permanently in Inventory Logs.
                                            </p>
                                        </div>

                                    </div>

                                </div>

                            </div>

                            <div class="flex flex-col-reverse gap-2 px-4 py-4 border-t border-gray-200 sm:flex-row sm:justify-end sm:px-6 dark:border-gray-700">

                                <button type="button"
                                    @click="closeLoss()"
                                    class="w-full {{ $btn['secondary'] }} sm:w-auto">
                                    Cancel
                                </button>

                                <button type="submit"
                                    class="w-full {{ $btn['primary'] }} sm:w-auto">

                                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                                    Record Stock Loss
                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </template>
    @endif

</div>

<style>
.pagination-wrapper nav > div:first-child {
    display: none;
}

.pagination-wrapper nav > div:last-child {
    display: flex;
    align-items: center;
    justify-content: flex-end;
}

.pagination-wrapper nav > div:last-child > div:first-child {
    display: none;
}

@media (max-width: 1023px) {
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
        width: 38%;
        text-align: left;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .rt td > * {
        min-width: 0;
        max-width: 62%;
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

    .rt td.rt-actions > * {
        max-width: none;
    }

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

@media (max-width: 1023px) and (prefers-color-scheme: dark) {
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

@media (max-width: 639px) {
    .pagination-wrapper {
        width: 100%;
    }
    .pagination-wrapper nav {
        width: 100%;
    }
    .pagination-wrapper nav > div:last-child {
        justify-content: space-between;
    }
}
</style>
@endsection