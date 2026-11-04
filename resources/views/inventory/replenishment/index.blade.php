@extends('layouts.app')

@section('title', 'Replenishment')

@section('content')
@php
    $statusMeta = [
        'healthy' => [
            'label' => 'Healthy',
            'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        ],
        'reorder' => [
            'label' => 'Reorder',
            'badge' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        ],
        'critical' => [
            'label' => 'Critical',
            'badge' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        ],
        'out_of_stock' => [
            'label' => 'Out of Stock',
            'badge' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        ],
    ];

    $statusFallback = [
        'label' => 'Unknown',
        'badge' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
    ];

    $formatQuantity = fn($value) =>
        rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">

    <x-page-header
        title="Replenishment"
        subtitle="Monitor branch stock, incoming orders, projected availability, and reorder recommendations."
    />

    <div class="p-4 border border-blue-200 shadow-sm sm:p-5 bg-blue-50 rounded-2xl dark:bg-blue-900/10 dark:border-blue-800">
        <div class="flex items-start gap-3">
            <div class="flex items-center justify-center w-10 h-10 text-blue-700 bg-blue-100 rounded-xl shrink-0 dark:bg-blue-900/40 dark:text-blue-300">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            </div>

            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    Replenishment is advisory only
                </h2>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Incoming stock comes from issued or partially received purchase orders.
                    Projected stock does not change physical inventory until a Goods Receipt is posted.
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Products
            </p>

            <p class="mt-3 text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                {{ $summary['total_products'] }}
            </p>
        </div>

        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Needs Reorder
            </p>

            <p class="mt-3 text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                {{ $summary['needs_reorder'] }}
            </p>
        </div>

        <div class="p-4 border shadow-sm sm:p-5 bg-red-50 border-red-200 rounded-2xl dark:bg-red-900/10 dark:border-red-800">
            <p class="text-xs font-semibold tracking-wide text-red-700 uppercase dark:text-red-300">
                Critical
            </p>

            <p class="mt-3 text-2xl font-semibold text-red-900 sm:text-3xl dark:text-red-200">
                {{ $summary['critical'] }}
            </p>
        </div>

        <div class="p-4 border shadow-sm sm:p-5 bg-blue-50 border-blue-200 rounded-2xl dark:bg-blue-900/10 dark:border-blue-800">
            <p class="text-xs font-semibold tracking-wide text-blue-700 uppercase dark:text-blue-300">
                Incoming Products
            </p>

            <p class="mt-3 text-2xl font-semibold text-blue-900 sm:text-3xl dark:text-blue-200">
                {{ $summary['incoming_products'] }}
            </p>
        </div>
    </div>

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                Branch Replenishment Overview
            </h2>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Current stock and incoming supplier quantities are shown in each product's usage unit.
            </p>
        </div>

        @if($replenishment->isEmpty())
            <div class="flex flex-col items-center justify-center px-4 py-16 text-center">
                <div class="flex items-center justify-center w-12 h-12 mb-3 text-gray-400 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-500">
                    <i class="text-lg fa-solid fa-boxes-stacked" aria-hidden="true"></i>
                </div>

                <p class="text-sm font-medium text-gray-900 dark:text-white">
                    No inventory products found
                </p>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Configure products and branch stock thresholds first.
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left rt" role="table">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-gray-900/40 dark:text-gray-400" role="rowgroup">
                        <tr role="row">
                            <th role="columnheader" class="px-6 py-3">Product</th>
                            <th role="columnheader" class="px-6 py-3">On Hand</th>
                            <th role="columnheader" class="px-6 py-3">Incoming</th>
                            <th role="columnheader" class="px-6 py-3">Projected</th>
                            <th role="columnheader" class="px-6 py-3">Thresholds</th>
                            <th role="columnheader" class="px-6 py-3">Recommendation</th>
                            <th role="columnheader" class="px-6 py-3">Status</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700" role="rowgroup">
                        @foreach($replenishment as $item)
                            @php
                                $meta = $statusMeta[$item['status']] ?? $statusFallback;

                                $recommendationUnit = $item['purchase_unit']
                                    ?: $item['usage_unit'];
                            @endphp

                            <tr role="row" class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                                <td role="cell" data-label="Product" class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">
                                            {{ $item['product_name'] }}
                                        </p>

                                        @if($item['sku'])
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                {{ $item['sku'] }}
                                            </p>
                                        @endif
                                    </div>
                                </td>

                                <td role="cell" data-label="On Hand" class="px-6 py-4 text-gray-700 dark:text-gray-300">
                                    {{ $formatQuantity($item['on_hand_quantity']) }}
                                    {{ $item['usage_unit'] }}
                                </td>

                                <td role="cell" data-label="Incoming" class="px-6 py-4">
                                    @if((float) $item['incoming_quantity'] > 0)
                                        <span class="font-medium text-blue-700 dark:text-blue-300">
                                            +{{ $formatQuantity($item['incoming_quantity']) }}
                                            {{ $item['usage_unit'] }}
                                        </span>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">
                                            0 {{ $item['usage_unit'] }}
                                        </span>
                                    @endif
                                </td>

                                <td role="cell" data-label="Projected" class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                    {{ $formatQuantity($item['projected_stock']) }}
                                    {{ $item['usage_unit'] }}
                                </td>

                                <td role="cell" data-label="Thresholds" class="px-6 py-4">
                                    <div class="space-y-1 text-xs text-gray-600 dark:text-gray-300">
                                        <p>
                                            Reorder:
                                            <span class="font-medium text-gray-900 dark:text-white">
                                                {{ $formatQuantity($item['reorder_level']) }}
                                            </span>
                                        </p>

                                        <p>
                                            Min:
                                            <span class="font-medium text-gray-900 dark:text-white">
                                                {{ $item['minimum_stock'] !== null
                                                    ? $formatQuantity($item['minimum_stock'])
                                                    : '—' }}
                                            </span>
                                        </p>

                                        <p>
                                            Max:
                                            <span class="font-medium text-gray-900 dark:text-white">
                                                {{ $item['maximum_stock'] !== null
                                                    ? $formatQuantity($item['maximum_stock'])
                                                    : '—' }}
                                            </span>
                                        </p>
                                    </div>
                                </td>

                                <td role="cell" data-label="Recommendation" class="px-6 py-4">
                                    @if($item['needs_reorder'])
                                        @if($item['recommended_purchase_quantity'] !== null)
                                            <div>
                                                <p class="font-medium text-amber-800 dark:text-amber-300">
                                                    Replenish
                                                    {{ $formatQuantity($item['recommended_purchase_quantity']) }}
                                                    {{ $recommendationUnit }}
                                                </p>

                                                @if($item['purchase_unit'] && $item['purchase_unit'] !== $item['usage_unit'])
                                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                        {{ $formatQuantity($item['recommended_inventory_quantity']) }}
                                                        {{ $item['usage_unit'] }}
                                                        inventory equivalent
                                                    </p>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-sm text-amber-700 dark:text-amber-300">
                                                Reorder needed, but no replenishment target is configured.
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">
                                            No replenishment needed
                                        </span>
                                    @endif
                                </td>

                                <td role="cell" data-label="Status" class="px-6 py-4">
                                    <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full {{ $meta['badge'] }}">
                                        {{ $meta['label'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>

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
}
</style>
@endsection
