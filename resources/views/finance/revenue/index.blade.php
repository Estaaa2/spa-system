@extends('layouts.app')

@section('content')
<div class="p-6 mx-auto space-y-6 max-w-7xl">

    <x-page-header
        title="Revenue"
        subtitle="Revenue from completed appointments for the selected period."
    />

    {{-- ── Date Range Filter ──────────────────────────────────────────────── --}}
    <form method="GET" id="revenueFilterForm">
        <input type="hidden" name="from" id="filterFrom" value="{{ $from->format('Y-m-d') }}">
        <input type="hidden" name="to"   id="filterTo"   value="{{ $to->format('Y-m-d') }}">

        <div class="p-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-wrap items-center gap-2">
                @php
                    $currentFrom = $from->format('Y-m-d');
                    $currentTo   = $to->format('Y-m-d');

                    $presets = [
                        ['Today',         now()->format('Y-m-d'),                              now()->format('Y-m-d')],
                        ['Past 7 Days',   now()->subDays(6)->format('Y-m-d'),                  now()->format('Y-m-d')],
                        ['Past 30 Days',  now()->subDays(29)->format('Y-m-d'),                 now()->format('Y-m-d')],
                        ['This Month',    now()->startOfMonth()->format('Y-m-d'),               now()->format('Y-m-d')],
                        ['Last Month',    now()->subMonth()->startOfMonth()->format('Y-m-d'),   now()->subMonth()->endOfMonth()->format('Y-m-d')],
                        ['Past 3 Months', now()->subMonths(3)->format('Y-m-d'),                now()->format('Y-m-d')],
                        ['This Year',     now()->startOfYear()->format('Y-m-d'),               now()->format('Y-m-d')],
                    ];
                @endphp

                @foreach($presets as [$label, $pFrom, $pTo])
                    @php $active = $currentFrom === $pFrom && $currentTo === $pTo; @endphp
                    <button type="button"
                        onclick="setPreset('{{ $pFrom }}','{{ $pTo }}')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors
                            {{ $active
                                ? 'bg-[#8B7355] text-white'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}">
                        {{ $label }}
                    </button>
                @endforeach

                <span class="mx-1 text-gray-300 dark:text-gray-600">|</span>

                <div class="flex items-center gap-1.5">
                    <input type="date" id="customFrom" value="{{ $currentFrom }}"
                        class="px-2 py-1.5 text-xs border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        onchange="syncCustom()">
                    <span class="text-xs text-gray-400">to</span>
                    <input type="date" id="customTo" value="{{ $currentTo }}"
                        class="px-2 py-1.5 text-xs border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        onchange="syncCustom()">
                    <button type="submit"
                        class="px-3 py-1.5 text-xs font-medium text-white bg-[#8B7355] rounded-lg hover:opacity-90">
                        Apply
                    </button>
                </div>
            </div>

            <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                {{ $from->format('M d, Y') }} – {{ $to->format('M d, Y') }}
                &nbsp;·&nbsp; Compared to the prior {{ $periodDays }}-day period.
            </p>
        </div>
    </form>

    {{-- ── KPI Cards ──────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Gross Revenue</p>
            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">₱{{ number_format($totalRevenue, 2) }}</p>
            @if($revenueGrowth !== null)
                <p class="mt-1 text-xs {{ $revenueGrowth >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' }}">
                    {{ $revenueGrowth >= 0 ? '▲' : '▼' }} {{ abs($revenueGrowth) }}% vs prior period
                </p>
            @else
                <p class="mt-1 text-xs text-gray-400">No prior data</p>
            @endif
        </div>

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Collected</p>
            <p class="mt-2 text-2xl font-bold text-emerald-700 dark:text-emerald-400">₱{{ number_format($totalCollected, 2) }}</p>
            @php $collectedPct = $totalRevenue > 0 ? round(($totalCollected / $totalRevenue) * 100) : 0; @endphp
            <p class="mt-1 text-xs text-gray-400">{{ $collectedPct }}% of gross</p>
        </div>

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Outstanding</p>
            <p class="mt-2 text-2xl font-bold {{ $totalOutstanding > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">
                ₱{{ number_format($totalOutstanding, 2) }}
            </p>
            <p class="mt-1 text-xs text-gray-400">Unpaid balance</p>
        </div>

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Completed Bookings</p>
            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $completedCount }}</p>
            @if($countGrowth !== null)
                <p class="mt-1 text-xs {{ $countGrowth >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' }}">
                    {{ $countGrowth >= 0 ? '▲' : '▼' }} {{ abs($countGrowth) }}% vs prior period
                </p>
            @else
                <p class="mt-1 text-xs text-gray-400">No prior data</p>
            @endif
        </div>

    </div>

    {{-- ── Revenue by Source + Chart ───────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Source breakdown --}}
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <h2 class="mb-4 text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Revenue by Source</h2>
            <div class="space-y-4">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs text-gray-500 dark:text-gray-400">Online Bookings</span>
                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">₱{{ number_format($onlineRevenue, 2) }}</span>
                    </div>
                    @php $onlinePct = $totalRevenue > 0 ? round(($onlineRevenue / $totalRevenue) * 100) : 0; @endphp
                    <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-700">
                        <div class="h-2 rounded-full bg-violet-500" style="width: {{ $onlinePct }}%"></div>
                    </div>
                    <p class="mt-0.5 text-xs text-gray-400">{{ $onlinePct }}% of total</p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs text-gray-500 dark:text-gray-400">Walk-in / Staff</span>
                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">₱{{ number_format($walkInRevenue, 2) }}</span>
                    </div>
                    @php $walkPct = $totalRevenue > 0 ? round(($walkInRevenue / $totalRevenue) * 100) : 0; @endphp
                    <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-700">
                        <div class="h-2 rounded-full bg-[#8B7355]" style="width: {{ $walkPct }}%"></div>
                    </div>
                    <p class="mt-0.5 text-xs text-gray-400">{{ $walkPct }}% of total</p>
                </div>
            </div>

            {{-- Avg revenue per booking --}}
            <div class="pt-4 mt-4 border-t border-gray-100 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400">Avg Revenue / Booking</p>
                <p class="mt-1 text-lg font-semibold text-gray-800 dark:text-white">
                    ₱{{ $completedCount > 0 ? number_format($totalRevenue / $completedCount, 2) : '0.00' }}
                </p>
            </div>
        </div>

        {{-- Daily revenue chart --}}
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 lg:col-span-2">
            <h2 class="mb-1 text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Daily Revenue</h2>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Gross revenue per day</p>
            <canvas id="revenueChart" height="110"></canvas>
        </div>

    </div>

    {{-- ── Revenue Table ────────────────────────────────────────────────────── --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Completed Bookings</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Revenue records for completed appointments only.</p>
            </div>
            <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                {{ $bookings->count() }} records
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Customer</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Service / Package</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Source</th>
                        <th class="px-6 py-3 text-xs font-medium text-right text-gray-500 uppercase">Total</th>
                        <th class="px-6 py-3 text-xs font-medium text-right text-gray-500 uppercase">Collected</th>
                        <th class="px-6 py-3 text-xs font-medium text-right text-gray-500 uppercase">Outstanding</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                    @forelse($bookings as $booking)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ $booking->appointment_date?->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $booking->customer_name ?? 'Walk-in Customer' }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $booking->customer_email ?? 'No email' }}
                                </p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm text-gray-900 dark:text-white">{{ $booking->treatment_label }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $booking->service_type_label }}</p>
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $src = $booking->booking_source ?? '';
                                    $srcLabel = match($src) {
                                        'online'  => 'Online',
                                        'walk_in' => 'Walk-in',
                                        default   => 'Staff',
                                    };
                                    $srcColor = $src === 'online'
                                        ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'
                                        : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full {{ $srcColor }}">
                                    {{ $srcLabel }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold text-right text-gray-900 dark:text-white">
                                ₱{{ number_format($booking->total_amount, 2) }}
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-right text-emerald-700 dark:text-emerald-400">
                                ₱{{ number_format($booking->amount_paid, 2) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                @if($booking->balance_amount > 0)
                                    <span class="text-sm font-medium text-amber-600 dark:text-amber-400">
                                        ₱{{ number_format($booking->balance_amount, 2) }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                        Paid
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-sm text-center text-gray-500 dark:text-gray-400">
                                No completed bookings found for the selected period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($bookings->count())
                <tfoot class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <td colspan="4" class="px-6 py-3 text-xs font-semibold text-right text-gray-600 uppercase dark:text-gray-300">
                            Totals
                        </td>
                        <td class="px-6 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">
                            ₱{{ number_format($totalRevenue, 2) }}
                        </td>
                        <td class="px-6 py-3 text-sm font-bold text-right text-emerald-700 dark:text-emerald-400">
                            ₱{{ number_format($totalCollected, 2) }}
                        </td>
                        <td class="px-6 py-3 text-sm font-bold text-right text-amber-600 dark:text-amber-400">
                            ₱{{ number_format($totalOutstanding, 2) }}
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    function setPreset(from, to) {
        document.getElementById('filterFrom').value = from;
        document.getElementById('filterTo').value   = to;
        document.getElementById('customFrom').value = from;
        document.getElementById('customTo').value   = to;
        document.getElementById('revenueFilterForm').submit();
    }
    function syncCustom() {
        document.getElementById('filterFrom').value = document.getElementById('customFrom').value;
        document.getElementById('filterTo').value   = document.getElementById('customTo').value;
    }

    const dailyData = @json($dailyRevenue);
    const rVals     = dailyData.map(x => x.revenue);
    const rMax      = Math.max(...rVals, 0);
    const yMax      = rMax <= 0 ? 100 : Math.ceil(rMax * 1.2);

    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: {
            labels: dailyData.map(x => x.label),
            datasets: [{
                data: rVals,
                backgroundColor: '#8B7355',
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { maxTicksLimit: 14, font: { size: 10 } } },
                y: {
                    beginAtZero: true,
                    max: yMax,
                    ticks: {
                        font: { size: 10 },
                        callback: v => '₱' + v.toLocaleString()
                    }
                }
            },
            interaction: { mode: 'index' },
        }
    });
</script>
@endsection