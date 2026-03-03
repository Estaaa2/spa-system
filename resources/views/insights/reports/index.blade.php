@extends('layouts.app')

@section('content')
<div class="p-6">

    <x-page-header
        title="Reports"
        subtitle="Summary cards and trend graph."
    />

    {{-- Filtering (matches your figma "FILTERING") --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 p-4 mb-6 bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
        <div>
            <label class="block mb-1 text-xs font-medium text-gray-600 dark:text-gray-300">From</label>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                   class="px-3 py-2 text-sm bg-white border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white">
        </div>

        <div>
            <label class="block mb-1 text-xs font-medium text-gray-600 dark:text-gray-300">To</label>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                   class="px-3 py-2 text-sm bg-white border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white">
        </div>

        <button class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-lg hover:opacity-90">
            Apply
        </button>
    </form>

    {{-- 4 Summary Cards (matches your figma layout) --}}
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

        {{-- Booking Summary --}}
        <div class="p-6 bg-white border rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Booking Summary</h2>
            <div class="grid grid-cols-2 gap-4 mt-4">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Total</p>
                    <p class="text-xl font-semibold text-gray-800 dark:text-white">{{ $summary['totalBookings'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Reserved</p>
                    <p class="text-xl font-semibold text-gray-800 dark:text-white">{{ $summary['reserved'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Confirmed</p>
                    <p class="text-xl font-semibold text-gray-800 dark:text-white">{{ $summary['confirmed'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Completed</p>
                    <p class="text-xl font-semibold text-gray-800 dark:text-white">{{ $summary['completed'] }}</p>
                </div>
            </div>
        </div>

        {{-- Revenue Summary --}}
        <div class="p-6 bg-white border rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Revenue Summary</h2>

            <div class="grid grid-cols-2 gap-4 mt-4">
                <div class="col-span-2">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Revenue</p>
                    <p class="text-2xl font-semibold text-gray-800 dark:text-white">
                        ₱ {{ number_format($revenue['total'], 2) }}
                    </p>
                </div>

                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Treatment Revenue</p>
                    <p class="text-lg font-semibold text-gray-800 dark:text-white">
                        ₱ {{ number_format($revenue['treatments'], 2) }}
                    </p>
                </div>

                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Package Revenue</p>
                    <p class="text-lg font-semibold text-gray-800 dark:text-white">
                        ₱ {{ number_format($revenue['packages'], 2) }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Service Summary --}}
        <div class="p-6 bg-white border rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Service Summary</h2>
            <div class="grid grid-cols-2 gap-4 mt-4">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Treatments</p>
                    <p class="text-xl font-semibold text-gray-800 dark:text-white">{{ $summary['treatmentCount'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Packages</p>
                    <p class="text-xl font-semibold text-gray-800 dark:text-white">{{ $summary['packageCount'] }}</p>
                </div>
            </div>
        </div>

        {{-- Staff Summary --}}
        <div class="p-6 bg-white border rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Staff Summary</h2>
            <div class="grid grid-cols-2 gap-4 mt-4">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Assigned Therapist</p>
                    <p class="text-xl font-semibold text-gray-800 dark:text-white">{{ $summary['assignedTherapist'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Unassigned</p>
                    <p class="text-xl font-semibold text-gray-800 dark:text-white">{{ $summary['unassignedTherapist'] }}</p>
                </div>
            </div>
        </div>

    </div>

    {{-- GRAPH area --}}
    <div class="p-6 mt-5 bg-white border rounded-xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Graph</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">Bookings per day</p>
        </div>

        <div class="mt-4">
            <canvas id="reportsGraph" height="90"></canvas>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const bookingsPerDay = @json($bookingsPerDay);

    new Chart(document.getElementById('reportsGraph'), {
        type: 'bar',
        data: {
            labels: bookingsPerDay.map(x => x.label),
            datasets: [{
                label: 'Bookings',
                data: bookingsPerDay.map(x => x.value),
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } }
        }
    });
</script>
@endsection
