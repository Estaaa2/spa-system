@extends('layouts.app')

@section('title', 'Inventory Logs')

@section('content')
<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl"
    x-data="{
        monthFilter: '{{ request('month', now()->format('Y-m')) }}',

        applyFilter() {
            if (this.monthFilter) {
                window.location.href = `{{ route('inventory.logs') }}?month=${this.monthFilter}`;
            }
        },

        clearFilter() {
            window.location.href = `{{ route('inventory.logs') }}`;
        },

        exportPdf() {
            let url = `{{ route('inventory.logs.export-pdf') }}`;

            if (this.monthFilter) {
                url += `?month=${this.monthFilter}`;
            }

            window.location.href = url;
        }
    }">

    <x-page-header
        title="Inventory Logs"
        subtitle="Review all inventory changes and activities."
    />

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-col gap-4 px-4 py-4 border-b border-gray-200 sm:px-6 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    Activity Logs
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Review inventory activity for the selected month.
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                <div>
                    <label for="monthFilter" class="sr-only">Filter by month</label>
                    <input type="month"
                        id="monthFilter"
                        x-model="monthFilter"
                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none sm:w-auto dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>

                <button type="button"
                    @click="applyFilter()"
                    class="inline-flex items-center justify-center gap-1.5 min-h-[44px] px-4 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-xl transition-colors hover:bg-[#7A6348] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i>
                    Apply
                </button>

                <button type="button"
                    @click="clearFilter()"
                    class="inline-flex items-center justify-center gap-1.5 min-h-[44px] px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 dark:focus-visible:ring-offset-gray-800">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    Clear
                </button>

                <button type="button"
                    @click="exportPdf()"
                    class="inline-flex items-center justify-center gap-1.5 min-h-[44px] px-4 py-2 text-sm font-medium border border-[#8B7355] bg-white text-[#8B7355] rounded-xl transition-colors hover:bg-[#F8F5F1] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:bg-gray-800 dark:text-[#C4A97D] dark:border-[#8B7355] dark:hover:bg-gray-700 dark:focus-visible:ring-offset-gray-800">
                    <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                    Export PDF
                </button>
            </div>
        </div>

        <div class="md:overflow-x-auto">
            <table role="table" class="rt min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader"
                            class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Description
                        </th>
                        <th role="columnheader"
                            class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Date & Time
                        </th>
                    </tr>
                </thead>

                <tbody role="rowgroup"
                    class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">

                    @forelse($logs as $log)
                        <tr role="row"
                            class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/40">

                            <td role="cell"
                                data-label="Description"
                                class="px-6 py-4">

                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $log->description }}
                                </p>
                            </td>

                            <td role="cell"
                                data-label="Date & Time"
                                class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">

                                {{ $log->logged_at?->format('M d, Y h:i A') ?? $log->created_at->format('M d, Y h:i A') }}
                            </td>
                        </tr>

                    @empty
                        <tr role="row">
                            <td role="cell"
                                colspan="2"
                                class="px-6 py-12 text-sm text-center text-gray-500 rt-empty dark:text-gray-400">

                                <div class="flex flex-col items-center justify-center">
                                    <div class="flex items-center justify-center w-12 h-12 mb-3 text-gray-400 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-500">
                                        <i class="text-lg fa-solid fa-clipboard-list" aria-hidden="true"></i>
                                    </div>

                                    <p>No logs found for the selected period.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse

                </tbody>
            </table>
        </div>

        <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
            {{ $logs->appends(request()->query())->links() }}
        </div>
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

    .rt tr {
        padding: 0.75rem 1rem;
    }

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

    .rt td.rt-empty {
        padding: 2rem 0 !important;
        text-align: center !important;
    }
}

@media (max-width: 767px) and (prefers-color-scheme: dark) {
    .rt td[data-label]::before {
        color: #9ca3af;
    }
}
</style>
@endsection
