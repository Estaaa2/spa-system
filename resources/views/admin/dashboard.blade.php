@extends('layouts.app')
@section('content')
<div class="p-6 mx-auto max-w-7xl">

    <x-page-header title="Admin Dashboard" subtitle="System overview and management."/>

    {{-- ── STAT CARDS ── --}}
    <div class="grid grid-cols-1 gap-4 mt-6 sm:grid-cols-2 xl:grid-cols-4">

        {{-- Total Spas --}}
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-widest text-gray-400 uppercase">Total Spas</p>
                    <p class="mt-1 text-3xl font-bold text-gray-800 dark:text-white">{{ $totalSpas }}</p>
                    <p class="mt-1 text-xs text-gray-400">Registered businesses</p>
                </div>
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-[#F6EFE6]">
                    <i class="fa-solid fa-spa text-[#8B7355] text-lg"></i>
                </div>
            </div>
        </div>

        {{-- Total Users --}}
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-widest text-gray-400 uppercase">Total Users</p>
                    <p class="mt-1 text-3xl font-bold text-gray-800 dark:text-white">{{ $totalUsers }}</p>
                    <p class="mt-1 text-xs text-gray-400">Owners, staff & customers</p>
                </div>
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-900/20">
                    <i class="text-lg text-blue-600 fa-solid fa-users"></i>
                </div>
            </div>
        </div>

        {{-- Active Subscriptions --}}
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-widest text-gray-400 uppercase">Active Subs</p>
                    <p class="mt-1 text-3xl font-bold text-gray-800 dark:text-white">{{ $activeSubscriptions }}</p>
                    <p class="mt-1 text-xs text-gray-400">Professional plan active</p>
                </div>
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-green-50 dark:bg-green-900/20">
                    <i class="text-lg text-green-600 fa-solid fa-circle-check"></i>
                </div>
            </div>
        </div>

        {{-- Subscription Revenue --}}
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-widest text-gray-400 uppercase">Sub Revenue</p>
                    <p class="mt-1 text-3xl font-bold text-gray-800 dark:text-white">
                        ₱{{ number_format($subscriptionRevenue, 0) }}
                    </p>
                    <p class="mt-1 text-xs text-gray-400">Total collected</p>
                </div>
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-orange-50 dark:bg-orange-900/20">
                    <i class="text-lg text-orange-500 fa-solid fa-peso-sign"></i>
                </div>
            </div>
        </div>

    </div>

    {{-- ── CHART + TIER BREAKDOWN ── --}}
    <div class="grid grid-cols-1 gap-6 mt-6 lg:grid-cols-3">

        {{-- Tier Breakdown Chart --}}
        <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white">Spa Tier Breakdown</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Basic vs Professional</p>
                </div>
                <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-[#F6EFE6]">
                    <i class="fa-solid fa-chart-pie text-[#8B7355]"></i>
                </div>
            </div>

            <canvas id="tierChart" height="200"></canvas>

            {{-- Legend --}}
            <div class="flex justify-center gap-6 mt-4">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-[#8B7355]"></span>
                    <span class="text-xs text-gray-500">Professional ({{ $professionalCount }})</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 bg-gray-200 rounded-full"></span>
                    <span class="text-xs text-gray-500">Basic ({{ $basicCount }})</span>
                </div>
            </div>
        </div>

        {{-- Quick Stats --}}
        <div class="p-6 bg-white border border-gray-200 shadow-sm lg:col-span-2 rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white">Subscription Overview</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Revenue and plan distribution</p>
                </div>
                <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-[#F6EFE6]">
                    <i class="fa-solid fa-receipt text-[#8B7355]"></i>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="p-4 text-center rounded-xl bg-gray-50 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-[#8B7355]">{{ $totalSpas }}</p>
                    <p class="mt-1 text-xs text-gray-500">Total Spas</p>
                </div>
                <div class="p-4 text-center rounded-xl bg-gray-50 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-green-600">{{ $professionalCount }}</p>
                    <p class="mt-1 text-xs text-gray-500">Professional</p>
                </div>
                <div class="p-4 text-center rounded-xl bg-gray-50 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-gray-500">{{ $basicCount }}</p>
                    <p class="mt-1 text-xs text-gray-500">Basic</p>
                </div>
            </div>

            <div class="mt-4 p-4 rounded-xl bg-[#F6EFE6]/50 dark:bg-gray-900">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">Professional Adoption Rate</span>
                    <span class="text-xs font-bold text-[#8B7355]">
                        {{ $totalSpas > 0 ? round(($professionalCount / $totalSpas) * 100) : 0 }}%
                    </span>
                </div>
                <div class="w-full h-2 bg-gray-200 rounded-full dark:bg-gray-700">
                    <div class="h-2 rounded-full bg-[#8B7355] transition-all duration-500"
                        style="width: {{ $totalSpas > 0 ? round(($professionalCount / $totalSpas) * 100) : 0 }}%">
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between p-4 mt-4 rounded-xl bg-green-50 dark:bg-green-900/20">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Subscription Revenue</p>
                    <p class="text-xl font-bold text-green-700 dark:text-green-400">
                        ₱{{ number_format($subscriptionRevenue, 2) }}
                    </p>
                </div>
                <i class="text-2xl text-green-500 fa-solid fa-sack-dollar"></i>
            </div>
        </div>

    </div>

    {{-- ── RECENTLY REGISTERED SPAS ── --}}
    <div class="mt-6 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-white">Recently Registered Spas</h3>
                <p class="text-xs text-gray-400 mt-0.5">Latest 8 spa registrations</p>
            </div>
            @can('manage spas')
            <a href="{{ route('admin.registered-spas.index') }}"
                class="text-xs font-semibold text-[#8B7355] hover:underline">
                View All →
            </a>
            @endcan
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-500 uppercase">Spa</th>
                        <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-500 uppercase">Owner</th>
                        <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-500 uppercase">Tier</th>
                        <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-500 uppercase">Subscription</th>
                        <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-500 uppercase">Registered</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse($recentSpas as $spa)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center flex-shrink-0 w-9 h-9 rounded-full bg-[#8B7355] text-white font-semibold text-sm">
                                    {{ strtoupper(substr($spa->name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $spa->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $spa->branches->count() }} branch(es)</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $spa->owner->name ?? 'N/A' }}</p>
                            <p class="text-xs text-gray-400">{{ $spa->owner->email ?? '' }}</p>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full
                                {{ $spa->business_tier === 'professional'
                                    ? 'bg-[#F6EFE6] text-[#6F5430]'
                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                {{ ucfirst($spa->business_tier) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @php $sub = $spa->subscriptions->first(); @endphp
                            @if($sub)
                                <span class="flex items-center gap-1 text-xs font-semibold text-green-600">
                                    <i class="fa-solid fa-circle text-[8px]"></i> Active
                                </span>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    Expires {{ $sub->expires_at?->format('M d, Y') }}
                                </p>
                            @else
                                <span class="text-xs text-gray-400">No subscription</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            {{ $spa->created_at->format('M d, Y') }}
                            <p class="text-xs text-gray-400">{{ $spa->created_at->diffForHumans() }}</p>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <i class="block mb-3 text-4xl text-gray-200 fa-solid fa-spa"></i>
                            <p class="text-sm text-gray-400">No spas registered yet.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- Chart.js --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('tierChart'), {
    type: 'doughnut',
    data: {
        labels: ['Professional', 'Basic'],
        datasets: [{
            data: [{{ $professionalCount }}, {{ $basicCount }}],
            backgroundColor: ['#8B7355', '#E5E7EB'],
            borderColor: ['#6F5430', '#D1D5DB'],
            borderWidth: 2,
            hoverOffset: 6,
        }]
    },
    options: {
        responsive: true,
        cutout: '70%',
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ` ${ctx.label}: ${ctx.raw} spa(s)`
                }
            }
        }
    }
});
</script>
@endsection
