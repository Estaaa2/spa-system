@extends('layouts.app')

@section('content')
<div class="p-6 mx-auto max-w-7xl">
    <x-page-header
        title="My Performance"
        subtitle="Track your ratings, feedback, and service performance"
    />

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-3">
        <div class="p-5 bg-white rounded-lg shadow-sm dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">Average Rating</p>
            <div class="flex items-center mt-2">
                <h3 class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($averageRating, 1) }}</h3>
                <div class="flex ml-2">
                    @for($i = 1; $i <= 5; $i++)
                        <i class="fa-solid fa-star {{ $i <= round($averageRating) ? 'text-yellow-400' : 'text-gray-300' }} text-sm"></i>
                    @endfor
                </div>
            </div>
            <p class="mt-2 text-sm text-gray-500">Based on {{ $totalRatings }} reviews</p>
        </div>

        <div class="p-5 bg-white rounded-lg shadow-sm dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">Completed Services</p>
            <h3 class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $completedBookings->total() }}</h3>
            <p class="mt-2 text-sm text-gray-500">Lifetime completed appointments</p>
        </div>

        <div class="p-5 bg-white rounded-lg shadow-sm dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">Customer Satisfaction</p>
            <h3 class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                {{ $averageRating >= 4.5 ? 'Excellent' : ($averageRating >= 3.5 ? 'Good' : 'Needs Improvement') }}
            </h3>
            <p class="mt-2 text-sm text-gray-500">Based on customer feedback</p>
        </div>
    </div>

    <!-- Rating Distribution -->
    <div class="mb-6 overflow-hidden bg-white rounded-lg shadow-sm dark:bg-gray-800">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Rating Distribution</h2>
        </div>
        <div class="p-6">
            @foreach([5,4,3,2,1] as $star)
                @php
                    $count = $ratingDistribution[$star];
                    $percentage = $totalRatings > 0 ? ($count / $totalRatings) * 100 : 0;
                @endphp
                <div class="flex items-center mb-3">
                    <div class="w-16 text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ $star }} {{ Str::plural('star', $star) }}
                    </div>
                    <div class="flex-1 mx-4">
                        <div class="h-2 overflow-hidden bg-gray-200 rounded-full dark:bg-gray-700">
                            <div class="h-full bg-yellow-400 rounded-full" style="width: {{ $percentage }}%"></div>
                        </div>
                    </div>
                    <div class="w-16 text-sm text-right text-gray-600 dark:text-gray-400">
                        {{ $count }} ({{ round($percentage) }}%)
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Monthly Performance Chart -->
    <div class="mb-6 overflow-hidden bg-white rounded-lg shadow-sm dark:bg-gray-800">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Monthly Performance</h2>
        </div>
        <div class="p-6">
            <canvas id="performanceChart" class="w-full h-64"></canvas>
        </div>
    </div>

    <!-- Recent Feedback -->
    <div class="overflow-hidden bg-white rounded-lg shadow-sm dark:bg-gray-800">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Customer Feedback</h2>
        </div>
        <div class="divide-y dark:divide-gray-700">
            @forelse($recentFeedback as $feedback)
                <div class="p-6">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            @for($i = 1; $i <= 5; $i++)
                                <i class="fa-solid fa-star {{ $i <= $feedback->rating ? 'text-yellow-400' : 'text-gray-300' }} text-sm"></i>
                            @endfor
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $feedback->customer->name ?? 'Anonymous' }}
                            </span>
                        </div>
                        <span class="text-xs text-gray-500">{{ $feedback->created_at->diffForHumans() }}</span>
                    </div>
                    @if($feedback->comment)
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $feedback->comment }}</p>
                    @endif
                    @if($feedback->feedback)
                        <p class="mt-1 text-xs italic text-gray-500">"{{ $feedback->feedback }}"</p>
                    @endif
                </div>
            @empty
                <div class="p-6 text-center text-gray-500">
                    No feedback received yet. Keep up the great work!
                </div>
            @endforelse
        </div>
        <div class="px-6 py-4 border-t dark:border-gray-700">
            {{ $completedBookings->links() }}
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('performanceChart').getContext('2d');
        const monthlyData = @json($monthlyData);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.map(d => d.month),
                datasets: [
                    {
                        label: 'Average Rating',
                        data: monthlyData.map(d => d.rating),
                        borderColor: '#8B7355',
                        backgroundColor: 'rgba(139, 115, 85, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Completed Bookings',
                        data: monthlyData.map(d => d.bookings),
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                let value = context.raw;
                                if (context.dataset.label === 'Average Rating') {
                                    return `${label}: ${value.toFixed(1)} ★`;
                                }
                                return `${label}: ${value}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Average Rating (★)'
                        },
                        min: 0,
                        max: 5,
                        ticks: {
                            stepSize: 1
                        }
                    },
                    y1: {
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Completed Bookings'
                        },
                        min: 0,
                        grid: {
                            drawOnChartArea: false,
                        }
                    }
                }
            }
        });
    });
</script>
@endsection
