@extends('layouts.app')

@section('title', 'Inventory Logs')
@section('content')
<div class="p-6"
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

    <div class="bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
        <!-- Header with Filters -->
        <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                Activity Logs
            </h2>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <label for="monthFilter" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    </label>
                    <input type="month"
                           id="monthFilter"
                           x-model="monthFilter"
                           class="px-3 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-[#8B7355] focus:border-[#8B7355]">
                </div>

                <button type="button"
                        @click="applyFilter()"
                        class="px-4 py-2 text-sm font-semibold text-white transition bg-[#8B7355] rounded-lg hover:bg-[#6F5430] focus:outline-none focus:ring-2 focus:ring-[#8B7355]/50">
                    <i class="mr-1 fa-solid fa-filter"></i> Apply
                </button>

                <button type="button"
                        @click="clearFilter()"
                        class="px-4 py-2 text-sm font-semibold text-gray-600 transition bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                    <i class="mr-1 fa-solid fa-times"></i> Clear
                </button>

                <button type="button"
                        @click="exportPdf()"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm border rounded-lg bg-white text-[#8B7355] border-[#8B7355] hover:bg-[#F8F5F1] whitespace-nowrap dark:bg-gray-800 dark:text-[#D2B48C] dark:border-[#8B7355] dark:hover:bg-gray-700">
                    <i class="fa-solid fa-file-pdf"></i> Export PDF
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr class="text-left">
                        <th class="px-6 py-3 font-medium text-gray-600 dark:text-gray-300">Description</th>
                        <th class="px-6 py-3 font-medium text-gray-600 dark:text-gray-300">Date & Time</th>
                    </tr>
                </thead>

                <tbody class="divide-y dark:divide-gray-700">
                    @forelse($logs as $log)
                        <tr>
                            <td class="px-6 py-3 text-gray-800 dark:text-gray-100">{{ $log->description }}</td>
                            <td class="px-6 py-3 text-gray-700 dark:text-gray-200">
                                {{ $log->logged_at?->format('M d, Y h:i A') ?? $log->created_at->format('M d, Y h:i A') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-6 py-10 text-center text-gray-500">
                                <i class="block mb-3 text-3xl text-gray-300 fa-solid fa-clipboard-list dark:text-gray-600"></i>
                                No logs found for the selected period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t dark:border-gray-700">
            {{ $logs->appends(request()->query())->links() }}
        </div>
    </div>
</div>
@endsection
