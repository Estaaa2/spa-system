@extends('layouts.app')

@section('title', 'Product Logs')

@section('content')
@php
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'secondary' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'outline' => $btnBase . ' border border-[#8B7355] bg-white text-[#8B7355] hover:bg-[#F8F5F1] dark:bg-gray-800 dark:text-[#C4A97D] dark:border-[#8B7355] dark:hover:bg-gray-700',
    ];
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">

    <x-page-header
        title="Product Logs"
        subtitle="Review structured stock movements and inventory activity for the current branch."
    />

    <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">

        <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <div class="flex flex-col gap-1">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    Stock Movement History
                </h2>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Every controlled change to branch inventory is recorded here.
                </p>
            </div>
        </div>

        <form method="GET"
            action="{{ route('inventory.logs') }}"
            class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
                        <label for="movement_type"
                            class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Movement
                        </label>
                        <select id="movement_type"
                            name="movement_type"
                            class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="">All Movements</option>
                            @foreach($movementTypes as $type)
                                <option value="{{ $type }}"
                                    @selected(request('movement_type') === $type)>
                                    {{ ucwords(str_replace('_', ' ', $type)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="date_from"
                            class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                            From
                        </label>

                        <input type="date"
                            id="date_from"
                            name="date_from"
                            value="{{ request('date_from') }}"
                            class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                    <div>
                        <label for="date_to"
                            class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                            To
                        </label>

                        <input type="date"
                            id="date_to"
                            name="date_to"
                            value="{{ request('date_to') }}"
                            class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 xl:flex-nowrap xl:shrink-0">
                    <button type="submit"
                        class="{{ $btn['primary'] }} whitespace-nowrap">
                        <i class="fa-solid fa-filter" aria-hidden="true"></i>
                        Apply
                    </button>

                    <a href="{{ route('inventory.logs') }}"
                        class="{{ $btn['secondary'] }} whitespace-nowrap">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        Clear
                    </a>

                    <a href="{{ route('inventory.logs.export-pdf', request()->query()) }}"
                        class="{{ $btn['outline'] }} whitespace-nowrap">
                        <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                        Export PDF
                    </a>
                </div>
            </div>
        </form>

        <div class="lg:overflow-x-auto">
            <table role="table"
                class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Date & Time
                        </th>
                        <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Product
                        </th>
                        <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Movement
                        </th>
                        <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Quantity
                        </th>
                        <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Balance
                        </th>
                        <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Reason
                        </th>
                        <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Performed By
                        </th>
                    </tr>
                </thead>
                <tbody role="rowgroup"
                    class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse($logs as $log)
                        @php
                            $isIn = $log->direction === 'in';

                            $movementClass = $isIn
                                ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
                            $quantity = rtrim(
                                rtrim(number_format((float) $log->quantity, 3, '.', ''), '0'),
                                '.'
                            );
                            $before = rtrim(
                                rtrim(number_format((float) $log->balance_before, 3, '.', ''), '0'),
                                '.'
                            );
                            $after = rtrim(
                                rtrim(number_format((float) $log->balance_after, 3, '.', ''), '0'),
                                '.'
                            );
                        @endphp
                        <tr role="row"
                            class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td role="cell"
                                data-label="Date & Time"
                                class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">

                                {{ $log->occurred_at?->format('M d, Y h:i A') ?? $log->created_at?->format('M d, Y h:i A') }}
                            </td>
                            <td role="cell"
                                data-label="Product"
                                class="px-4 py-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $log->product?->name ?? 'Deleted Product' }}
                                </p>
                                @if($log->product?->sku)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $log->product->sku }}
                                    </p>
                                @endif
                            </td>
                            <td role="cell"
                                data-label="Movement"
                                class="px-4 py-4">
                                <div class="flex flex-col items-start gap-1">

                                    <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full {{ $movementClass }}">
                                        {{ ucwords(str_replace('_', ' ', $log->movement_type)) }}
                                    </span>

                                    <span class="text-xs font-medium uppercase {{ $isIn ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $log->direction }}
                                    </span>
                                </div>
                            </td>
                            <td role="cell"
                                data-label="Quantity"
                                class="px-4 py-4 text-sm font-medium text-gray-900 dark:text-white">

                                {{ $isIn ? '+' : '-' }}{{ $quantity }}
                                {{ $log->unit }}
                            </td>
                            <td role="cell"
                                data-label="Balance"
                                class="px-4 py-4">
                                <p class="text-sm text-gray-900 dark:text-white">
                                    {{ $before }}
                                    <span class="mx-1 text-gray-400">→</span>
                                    {{ $after }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $log->unit }}
                                </p>
                            </td>
                            <td role="cell"
                                data-label="Reason"
                                class="px-4 py-4">
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ $log->notes ?: '—' }}
                                </p>
                                @if($log->reference_type)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Ref:
                                        {{ ucwords(str_replace('_', ' ', $log->reference_type)) }}

                                        @if($log->reference_id)
                                            #{{ $log->reference_id }}
                                        @endif
                                    </p>
                                @endif
                            </td>
                            <td role="cell"
                                data-label="Performed By"
                                class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">

                                {{ $log->user?->name ?? $log->user?->full_name ?? 'System' }}
                            </td>
                        </tr>
                    @empty
                        <tr role="row">
                            <td role="cell"
                                colspan="7"
                                class="px-6 py-12 text-sm text-center text-gray-500 rt-empty dark:text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="flex items-center justify-center w-12 h-12 mb-3 text-gray-400 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-500">
                                        <i class="text-lg fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                                    </div>
                                    <p>No stock movements found.</p>
                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                        Try changing your filters or create a stock transaction.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($logs->hasPages() || $logs->total() > 0)
            <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Showing
                        <span class="font-medium text-gray-700 dark:text-gray-200">
                            {{ $logs->firstItem() ?? 0 }}
                        </span>
                        to
                        <span class="font-medium text-gray-700 dark:text-gray-200">
                            {{ $logs->lastItem() ?? 0 }}
                        </span>
                        of
                        <span class="font-medium text-gray-700 dark:text-gray-200">
                            {{ $logs->total() }}
                        </span>
                        results
                    </p>
                    @if($logs->hasPages())
                        <div class="pagination-wrapper">
                            {{ $logs->links() }}
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

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

    .rt td[data-label="Movement"] > div {
        align-items: flex-end;
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
}
</style>
@endsection