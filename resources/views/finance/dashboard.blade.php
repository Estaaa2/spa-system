@extends('layouts.app')
@section('content')
<div class="p-6 mx-auto max-w-7xl">

    <x-page-header
        title="Finance Dashboard"
        subtitle="Financial overview of your business"/>

    {{-- ── STAT CARDS ── --}}
    <div class="grid grid-cols-1 gap-4 mt-6 sm:grid-cols-2 xl:grid-cols-4">

        {{-- Total Revenue --}}
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-widest text-gray-400 uppercase">Total Revenue</p>
                    <p class="mt-1 text-2xl font-bold text-gray-800 dark:text-white">
                        ₱{{ number_format($totalRevenue, 2) }}
                    </p>
                    <p class="mt-1 text-xs text-gray-400">All completed bookings</p>
                </div>
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-green-50 dark:bg-green-900/20">
                    <i class="text-lg text-green-600 fa-solid fa-peso-sign"></i>
                </div>
            </div>
        </div>

        {{-- Monthly Revenue --}}
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-widest text-gray-400 uppercase">This Month</p>
                    <p class="mt-1 text-2xl font-bold text-gray-800 dark:text-white">
                        ₱{{ number_format($monthlyRevenue, 2) }}
                    </p>
                    <p class="mt-1 text-xs flex items-center gap-1
                        {{ $revenueGrowth >= 0 ? 'text-green-600' : 'text-red-500' }}">
                        <i class="fa-solid {{ $revenueGrowth >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}"></i>
                        {{ $revenueGrowth >= 0 ? '+' : '' }}{{ $revenueGrowth }}% vs last month
                    </p>
                </div>
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-900/20">
                    <i class="text-lg text-blue-600 fa-solid fa-chart-line"></i>
                </div>
            </div>
        </div>

        {{-- Subscription --}}
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-widest text-gray-400 uppercase">Subscription</p>
                    @if($subscription)
                        <p class="mt-1 text-lg font-bold text-gray-800 capitalize dark:text-white">
                            {{ $subscription->business_tier }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">
                            Expires {{ $subscription->expires_at?->format('M d, Y') ?? 'N/A' }}
                        </p>
                        <span class="inline-flex items-center gap-1 mt-1 text-xs font-semibold
                            {{ $subscription->expires_at?->isFuture() ? 'text-green-600' : 'text-red-500' }}">
                            <i class="fa-solid fa-circle text-[8px]"></i>
                            {{ $subscription->expires_at?->isFuture() ? 'Active' : 'Expired' }}
                        </span>
                    @else
                        <p class="mt-1 text-lg font-bold text-gray-500">Basic</p>
                        <p class="mt-1 text-xs text-gray-400">No active subscription</p>
                    @endif
                </div>
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-orange-50 dark:bg-orange-900/20">
                    <i class="text-lg text-orange-500 fa-solid fa-file-invoice-dollar"></i>
                </div>
            </div>
        </div>

        {{-- Inventory Value --}}
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-widest text-gray-400 uppercase">Total Stock</p>
                    <p class="mt-1 text-2xl font-bold text-gray-800 dark:text-white">
                        {{ number_format($inventoryValue) }} units
                    </p>
                    <p class="mt-1 text-xs text-gray-400">{{ $inventoryCount }} product(s)</p>
                </div>
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-purple-50 dark:bg-purple-900/20">
                    <i class="text-lg text-purple-600 fa-solid fa-boxes-stacked"></i>
                </div>
            </div>
        </div>

    </div>

    {{-- ── CHART + TOP EARNERS ── --}}
    <div class="grid grid-cols-1 gap-6 mt-6 lg:grid-cols-3">

        {{-- Revenue Chart (last 6 months) --}}
        <div class="p-6 bg-white border border-gray-200 shadow-sm lg:col-span-2 rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white">Monthly Revenue</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Last 6 months</p>
                </div>
                <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-[#F6EFE6]">
                    <i class="fa-solid fa-chart-bar text-[#8B7355]"></i>
                </div>
            </div>
            <canvas id="revenueChart" height="120"></canvas>
        </div>

        {{-- Top Earning Services --}}
        <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white">Top Earning Services</h3>
                    <p class="text-xs text-gray-400 mt-0.5">By completed bookings</p>
                </div>
                <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-[#F6EFE6]">
                    <i class="fa-solid fa-ranking-star text-[#8B7355]"></i>
                </div>
            </div>

            @forelse($topEarners as $index => $earner)
            <div class="flex items-center gap-3 py-3 {{ !$loop->last ? 'border-b border-gray-100 dark:border-gray-700' : '' }}">
                <div class="flex items-center justify-center flex-shrink-0 w-7 h-7 rounded-full text-xs font-bold
                    {{ $index === 0 ? 'bg-yellow-100 text-yellow-700' :
                      ($index === 1 ? 'bg-gray-100 text-gray-600' :
                      ($index === 2 ? 'bg-orange-100 text-orange-600' : 'bg-gray-50 text-gray-400')) }}">
                    {{ $index + 1 }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate dark:text-white">
                        {{ $earner['name'] }}
                    </p>
                    <p class="text-xs text-gray-400">{{ $earner['count'] }} booking(s)</p>
                </div>
                <p class="flex-shrink-0 text-sm font-semibold text-green-600">
                    ₱{{ number_format($earner['revenue'], 2) }}
                </p>
            </div>
            @empty
            <div class="py-8 text-center">
                <i class="block mb-2 text-3xl text-gray-200 fa-solid fa-chart-pie"></i>
                <p class="text-sm text-gray-400">No completed bookings yet.</p>
            </div>
            @endforelse
        </div>

    </div>

    {{-- ── SUBSCRIPTION DETAIL ── --}}
    @if($subscription)
    <div class="p-6 mt-6 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white">Subscription & Billing</h3>
            <a href="{{ route('owner.subscription.index') }}"
                class="text-xs font-semibold text-[#8B7355] hover:underline">
                Manage →
            </a>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900">
                <p class="text-xs tracking-wide text-gray-400 uppercase">Plan</p>
                <p class="mt-1 text-sm font-bold text-gray-800 capitalize dark:text-white">
                    {{ $subscription->business_tier }}
                </p>
            </div>
            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900">
                <p class="text-xs tracking-wide text-gray-400 uppercase">Amount</p>
                <p class="mt-1 text-sm font-bold text-gray-800 dark:text-white">
                    ₱{{ number_format($subscription->amount, 2) }}
                </p>
            </div>
            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900">
                <p class="text-xs tracking-wide text-gray-400 uppercase">Started</p>
                <p class="mt-1 text-sm font-bold text-gray-800 dark:text-white">
                    {{ $subscription->starts_at?->format('M d, Y') ?? 'N/A' }}
                </p>
            </div>
            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900">
                <p class="text-xs tracking-wide text-gray-400 uppercase">Expires</p>
                <p class="mt-1 text-sm font-bold
                    {{ $subscription->expires_at?->isFuture() ? 'text-green-600' : 'text-red-500' }}">
                    {{ $subscription->expires_at?->format('M d, Y') ?? 'N/A' }}
                </p>
            </div>
        </div>
    </div>
    @endif

</div>

{{-- Chart.js --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const isDark = document.documentElement.classList.contains('dark');
const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
const textColor = isDark ? '#9ca3af' : '#6b7280';

const chartData = @json($monthlyChart);

new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: chartData.map(d => d.label),
        datasets: [{
            label: 'Revenue (₱)',
            data: chartData.map(d => d.revenue),
            backgroundColor: 'rgba(139, 115, 85, 0.15)',
            borderColor: '#8B7355',
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ' ₱' + Number(ctx.raw).toLocaleString('en-PH', { minimumFractionDigits: 2 })
                }
            }
        },
        scales: {
            x: {
                grid: { color: gridColor },
                ticks: { color: textColor, font: { size: 11 } }
            },
            y: {
                grid: { color: gridColor },
                ticks: {
                    color: textColor,
                    font: { size: 11 },
                    callback: val => '₱' + Number(val).toLocaleString()
                },
                beginAtZero: true
            }
        }
    }
});
</script>
@endsection
