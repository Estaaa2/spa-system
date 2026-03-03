@extends('layouts.app')

@section('content')
<div class="p-6">
    <x-page-header
        title="Inventory Logs"
        subtitle="Review all inventory changes and activities."
    />

    <div class="bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr class="text-left">
                        <th class="px-6 py-3 font-medium text-gray-600 dark:text-gray-300">Description</th>
                        <th class="px-6 py-3 font-medium text-gray-600 dark:text-gray-300">Date</th>
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
                                No logs yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
