@extends('layouts.app')

@section('content')
<div class="p-6">
    <x-page-header
        title="Decision Support"
        subtitle="Track top services, top packages, and peak booking hours."
    />

    {{-- Filtering --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 p-4 mb-4 bg-white rounded-xl dark:bg-gray-800">
        <div>
            <label class="block mb-1 text-xs font-medium text-gray-600 dark:text-gray-300">From</label>
            <input type="date" name="from" value="{{ $filters['from'] }}"
                class="px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white">
        </div>

        <div>
            <label class="block mb-1 text-xs font-medium text-gray-600 dark:text-gray-300">To</label>
            <input type="date" name="to" value="{{ $filters['to'] }}"
                class="px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white">
        </div>

        <button class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-lg hover:opacity-90">
            Apply
        </button>
    </form>

    {{-- Charts Grid --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Popular Services (Horizontal Bar) --}}
        <div class="p-5 bg-white border rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <div class="mb-3">
                <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                    Popular Services
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Top booked treatments</p>
            </div>
            <canvas id="popularServicesChart" height="140"></canvas>
        </div>

        {{-- Popular Packages (Donut) --}}
        <div class="p-5 bg-white border rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <div class="mb-3">
                <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                    Popular Packages
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Share of package bookings</p>
            </div>
            <canvas id="popularPackagesChart" height="140"></canvas>
        </div>
    </div>

    {{-- Peak Hours (Line) --}}
    <div class="p-5 mt-4 bg-white border rounded-xl dark:bg-gray-800 dark:border-gray-700">
        <div class="mb-3">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                Peak Hours
            </h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">Bookings by hour</p>
        </div>
        <canvas id="peakHoursChart" height="90"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const popularServices = @json($popularServices);
    const popularPackages = @json($popularPackages);
    const peakHours = @json($peakHours);

    function getDynamicMax(values) {
        const max = Math.max(...values, 0);

        if (max <= 5) return 5;
        if (max <= 10) return 10;
        if (max <= 20) return 20;

        return Math.ceil(max * 1.2);
    }

    function getDynamicStepSize(max) {
        if (max <= 5) return 1;
        if (max <= 10) return 2;
        if (max <= 20) return 5;
        if (max <= 50) return 10;
        return Math.ceil(max / 5);
    }

    const serviceValues = popularServices.map(x => Number(x.value) || 0);
    const packageValues = popularPackages.map(x => Number(x.value) || 0);
    const peakHourValues = peakHours.map(x => Number(x.value) || 0);

    const servicesMax = getDynamicMax(serviceValues);
    const servicesStep = getDynamicStepSize(servicesMax);

    const peakMax = getDynamicMax(peakHourValues);
    const peakStep = getDynamicStepSize(peakMax);

    // 1) Popular Services - Horizontal Bar
    new Chart(document.getElementById('popularServicesChart'), {
        type: 'bar',
        data: {
            labels: popularServices.map(x => x.label),
            datasets: [{
                label: 'Bookings',
                data: serviceValues,
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    max: servicesMax,
                    ticks: {
                        stepSize: servicesStep,
                        precision: 0
                    }
                }
            }
        }
    });

    // 2) Popular Packages - Donut
    new Chart(document.getElementById('popularPackagesChart'), {
        type: 'doughnut',
        data: {
            labels: popularPackages.map(x => x.label),
            datasets: [{
                data: packageValues,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = packageValues.reduce((a, b) => a + b, 0);
                            const value = context.raw || 0;
                            const percent = total ? ((value / total) * 100).toFixed(1) : 0;
                            return `${context.label}: ${value} (${percent}%)`;
                        }
                    }
                }
            }
        }
    });

    // 3) Peak Hours - Line
    new Chart(document.getElementById('peakHoursChart'), {
        type: 'line',
        data: {
            labels: peakHours.map(x => x.label),
            datasets: [{
                label: 'Bookings',
                data: peakHourValues,
                tension: 0.35,
                fill: false,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: peakMax,
                    ticks: {
                        stepSize: peakStep,
                        precision: 0
                    }
                }
            }
        }
    });
</script>
@endsection
