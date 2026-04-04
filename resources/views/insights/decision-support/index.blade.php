@extends('layouts.app')

@section('content')
<div class="p-6 mx-auto space-y-6 max-w-7xl">

    <x-page-header
        title="Decision Support"
        subtitle="Actionable insights to guide staffing, promotions, and scheduling decisions."
    />

    {{-- ── Date Range Filter ──────────────────────────────────────────────── --}}
    <form method="GET" id="dssFilterForm">
        <input type="hidden" name="from" id="filterFrom" value="{{ $filters['from'] }}">
        <input type="hidden" name="to"   id="filterTo"   value="{{ $filters['to'] }}">

        <div class="p-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-wrap items-center gap-2">
                {{-- Preset buttons --}}
                @php
                    $presets = [
                        'today'    => ['Today',      now()->format('Y-m-d'), now()->format('Y-m-d')],
                        '7d'       => ['Past 7 Days', now()->subDays(6)->format('Y-m-d'), now()->format('Y-m-d')],
                        '30d'      => ['Past 30 Days',now()->subDays(29)->format('Y-m-d'),now()->format('Y-m-d')],
                        'month'    => ['This Month',  now()->startOfMonth()->format('Y-m-d'), now()->format('Y-m-d')],
                        'lastmonth'=> ['Last Month',  now()->subMonth()->startOfMonth()->format('Y-m-d'), now()->subMonth()->endOfMonth()->format('Y-m-d')],
                        '3mo'      => ['Past 3 Months',now()->subMonths(3)->format('Y-m-d'),now()->format('Y-m-d')],
                        'year'     => ['This Year',   now()->startOfYear()->format('Y-m-d'), now()->format('Y-m-d')],
                    ];
                @endphp

                @foreach($presets as [$label, $pFrom, $pTo])
                    @php $active = $filters['from'] === $pFrom && $filters['to'] === $pTo; @endphp
                    <button
                        type="button"
                        onclick="setPreset('{{ $pFrom }}', '{{ $pTo }}')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors
                            {{ $active
                                ? 'bg-[#8B7355] text-white'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}">
                        {{ $label }}
                    </button>
                @endforeach

                <span class="mx-1 text-gray-300 dark:text-gray-600">|</span>

                {{-- Custom range --}}
                <div class="flex items-center gap-1.5">
                    <input type="date" id="customFrom" value="{{ $filters['from'] }}"
                        class="px-2 py-1.5 text-xs border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        onchange="customDateChanged()">
                    <span class="text-xs text-gray-400">to</span>
                    <input type="date" id="customTo" value="{{ $filters['to'] }}"
                        class="px-2 py-1.5 text-xs border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        onchange="customDateChanged()">
                    <button type="submit"
                        class="px-3 py-1.5 text-xs font-medium text-white bg-[#8B7355] rounded-lg hover:opacity-90">
                        Apply
                    </button>
                </div>
            </div>

            <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                Showing {{ \Carbon\Carbon::parse($filters['from'])->format('M d, Y') }}
                – {{ \Carbon\Carbon::parse($filters['to'])->format('M d, Y') }}
                &nbsp;·&nbsp;
                Compared to the {{ $kpis['period_days'] }}-day period before this range.
            </p>
        </div>
    </form>

    {{-- ── KPI Cards ──────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Total Bookings</p>
            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $kpis['total'] }}</p>
            @if($kpis['growth'] !== null)
                <p class="mt-1 text-xs {{ $kpis['growth'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' }}">
                    {{ $kpis['growth'] >= 0 ? '▲' : '▼' }} {{ abs($kpis['growth']) }}% vs prior period
                </p>
            @else
                <p class="mt-1 text-xs text-gray-400">No prior data</p>
            @endif
        </div>

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Avg / Day</p>
            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $kpis['avg_per_day'] }}</p>
            <p class="mt-1 text-xs text-gray-400">over {{ $kpis['period_days'] }} days</p>
        </div>

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Revenue</p>
            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">₱{{ number_format($kpis['revenue'], 0) }}</p>
            @if($kpis['revenue_growth'] !== null)
                <p class="mt-1 text-xs {{ $kpis['revenue_growth'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' }}">
                    {{ $kpis['revenue_growth'] >= 0 ? '▲' : '▼' }} {{ abs($kpis['revenue_growth']) }}% vs prior period
                </p>
            @else
                <p class="mt-1 text-xs text-gray-400">No prior data</p>
            @endif
        </div>

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Cancellation Rate</p>
            <p class="mt-2 text-3xl font-bold {{ $kpis['cancel_rate'] >= 20 ? 'text-red-500' : ($kpis['cancel_rate'] >= 10 ? 'text-amber-500' : 'text-gray-900 dark:text-white') }}">
                {{ $kpis['cancel_rate'] }}%
            </p>
            <p class="mt-1 text-xs text-gray-400">of bookings this period</p>
        </div>

    </div>

    {{-- ── Insights (the actual DSS output) ──────────────────────────────── --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Actionable Insights</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                System-generated recommendations based on patterns in the selected period.
            </p>
        </div>
        <div class="p-6 space-y-3">
            @php
                $insightColors = [
                    'success' => 'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/15 dark:border-emerald-800',
                    'info'    => 'bg-blue-50 border-blue-200 dark:bg-blue-900/15 dark:border-blue-800',
                    'warning' => 'bg-amber-50 border-amber-200 dark:bg-amber-900/15 dark:border-amber-800',
                    'danger'  => 'bg-red-50 border-red-200 dark:bg-red-900/15 dark:border-red-800',
                ];
                $insightText = [
                    'success' => 'text-emerald-800 dark:text-emerald-300',
                    'info'    => 'text-blue-800 dark:text-blue-300',
                    'warning' => 'text-amber-800 dark:text-amber-300',
                    'danger'  => 'text-red-800 dark:text-red-300',
                ];
                $insightIcons = [
                    'success' => '✓',
                    'info'    => 'ℹ',
                    'warning' => '!',
                    'danger'  => '✕',
                ];
            @endphp

            @foreach($insights as $insight)
                @php
                    $bg   = $insightColors[$insight['type']] ?? $insightColors['info'];
                    $txt  = $insightText[$insight['type']] ?? $insightText['info'];
                    $icon = $insightIcons[$insight['type']] ?? 'ℹ';
                @endphp
                <div class="flex gap-3 p-4 border rounded-xl {{ $bg }}">
                    <span class="flex items-center justify-center w-5 h-5 mt-0.5 text-xs font-bold rounded-full shrink-0
                        {{ $insight['type'] === 'success' ? 'bg-emerald-200 text-emerald-800 dark:bg-emerald-800 dark:text-emerald-200' : '' }}
                        {{ $insight['type'] === 'info'    ? 'bg-blue-200 text-blue-800 dark:bg-blue-800 dark:text-blue-200' : '' }}
                        {{ $insight['type'] === 'warning' ? 'bg-amber-200 text-amber-800 dark:bg-amber-800 dark:text-amber-200' : '' }}
                        {{ $insight['type'] === 'danger'  ? 'bg-red-200 text-red-800 dark:bg-red-800 dark:text-red-200' : '' }}">
                        {{ $icon }}
                    </span>
                    <div>
                        <p class="text-sm font-semibold {{ $txt }}">{{ $insight['title'] }}</p>
                        <p class="mt-0.5 text-xs {{ $txt }} opacity-90">{{ $insight['message'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ── Row: Trend + Day-of-Week ────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Daily Booking Trend</h2>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Volume over selected period</p>
            <canvas id="trendChart" height="140"></canvas>
        </div>

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Bookings by Day of Week</h2>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Identify your busiest and slowest days</p>
            <canvas id="dowChart" height="140"></canvas>
        </div>

    </div>

    {{-- ── Row: Service performance + Packages ─────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Service Performance</h2>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Current vs prior period — spot growth and decline</p>
            @if($popularServices->count())
                <canvas id="servicesChart" height="160"></canvas>
            @else
                <div class="flex items-center justify-center h-24 text-sm text-gray-400">No service bookings in this period.</div>
            @endif
        </div>

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Package Booking Share</h2>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Which packages are customers choosing</p>
            @if($popularPackages->count())
                <canvas id="packagesChart" height="160"></canvas>
            @else
                <div class="flex items-center justify-center h-24 text-sm text-gray-400">No package bookings in this period.</div>
            @endif
        </div>

    </div>

    {{-- ── Peak Hours ───────────────────────────────────────────────────────── --}}
    <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Booking Activity by Hour</h2>
        <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
            Peak hours show where staffing demand is highest; low-activity hours are promotion and scheduling opportunities
        </p>
        <canvas id="peakHoursChart" height="90"></canvas>
    </div>

    {{-- ── Staff Workload ───────────────────────────────────────────────────── --}}
    @if($staffUtilization->count())
    <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Therapist Booking Load</h2>
        <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Use to identify workload imbalances and reassign accordingly</p>
        <canvas id="staffChart" height="120"></canvas>
    </div>
    @endif

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // ── Preset filter logic ──────────────────────────────────────────────────
    function setPreset(from, to) {
        document.getElementById('filterFrom').value = from;
        document.getElementById('filterTo').value   = to;
        document.getElementById('customFrom').value = from;
        document.getElementById('customTo').value   = to;
        document.getElementById('dssFilterForm').submit();
    }

    function customDateChanged() {
        const from = document.getElementById('customFrom').value;
        const to   = document.getElementById('customTo').value;
        document.getElementById('filterFrom').value = from;
        document.getElementById('filterTo').value   = to;
    }

    // ── Chart data ───────────────────────────────────────────────────────────
    const trendData    = @json($bookingTrend);
    const dowData      = @json($dayData);
    const servicesData = @json($popularServices);
    const pkgData      = @json($popularPackages);
    const peakData     = @json($peakHours);
    const staffData    = @json($staffUtilization);

    const C_PRIMARY   = '#8B7355';
    const C_MUTED     = '#C8B89A';
    const C_PALETTE   = ['#8B7355','#A0937D','#C8B89A','#6B5B45','#BFA98E','#E8D9C4','#7A6448','#9C836A'];

    function dynMax(vals) {
        const m = Math.max(...vals, 0);
        if (m <= 5)  return 5;
        if (m <= 10) return 10;
        if (m <= 20) return 20;
        return Math.ceil(m * 1.2);
    }
    function dynStep(max) {
        if (max <= 5)  return 1;
        if (max <= 10) return 2;
        if (max <= 20) return 5;
        if (max <= 50) return 10;
        return Math.ceil(max / 5);
    }
    function yAxis(max) {
        return { beginAtZero: true, max, ticks: { stepSize: dynStep(max), precision: 0 } };
    }

    // 1. Daily trend
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: trendData.map(x => x.label),
            datasets: [{
                data: trendData.map(x => x.value),
                borderColor: C_PRIMARY,
                backgroundColor: 'rgba(139,115,85,0.08)',
                tension: 0.35, fill: true, borderWidth: 2,
                pointRadius: trendData.length > 30 ? 0 : 3,
                pointBackgroundColor: C_PRIMARY,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { maxTicksLimit: 12, font: { size: 11 } } },
                y: yAxis(dynMax(trendData.map(x => x.value)))
            }
        }
    });

    // 2. Day of week
    const dowMax  = dynMax(dowData.map(x => x.value));
    const maxDow  = Math.max(...dowData.map(x => x.value));
    new Chart(document.getElementById('dowChart'), {
        type: 'bar',
        data: {
            labels: dowData.map(x => x.label),
            datasets: [{
                data: dowData.map(x => x.value),
                backgroundColor: dowData.map(x => x.value === maxDow ? C_PRIMARY : C_MUTED),
                borderRadius: 5,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: yAxis(dowMax) }
        }
    });

    // 3. Services — grouped horizontal bar (current vs prev)
    @if($popularServices->count())
    const svcMax = dynMax([...servicesData.map(x=>x.value), ...servicesData.map(x=>x.prev)]);
    new Chart(document.getElementById('servicesChart'), {
        type: 'bar',
        data: {
            labels: servicesData.map(x => x.label),
            datasets: [
                { label: 'This Period', data: servicesData.map(x=>x.value), backgroundColor: C_PRIMARY, borderRadius: 3 },
                { label: 'Prior Period', data: servicesData.map(x=>x.prev), backgroundColor: C_MUTED, borderRadius: 3 },
            ]
        },
        options: {
            indexAxis: 'y', responsive: true,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
            scales: { x: yAxis(svcMax) }
        }
    });
    @endif

    // 4. Packages donut
    @if($popularPackages->count())
    const pkgVals = pkgData.map(x => x.value);
    new Chart(document.getElementById('packagesChart'), {
        type: 'doughnut',
        data: {
            labels: pkgData.map(x => x.label),
            datasets: [{ data: pkgVals, backgroundColor: C_PALETTE, borderWidth: 1 }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label(ctx) {
                            const total = pkgVals.reduce((a,b)=>a+b,0);
                            const pct   = total ? ((ctx.raw/total)*100).toFixed(1) : 0;
                            return `${ctx.label}: ${ctx.raw} (${pct}%)`;
                        }
                    }
                }
            }
        }
    });
    @endif

    // 5. Peak hours
    const peakVals = peakData.map(x => x.value);
    const peakMax  = dynMax(peakVals);
    const maxPeak  = Math.max(...peakVals);
    new Chart(document.getElementById('peakHoursChart'), {
        type: 'bar',
        data: {
            labels: peakData.map(x => x.label),
            datasets: [{
                data: peakVals,
                backgroundColor: peakVals.map(v =>
                    v === maxPeak ? C_PRIMARY : (v < maxPeak * 0.25 && v > 0 ? 'rgba(139,115,85,0.22)' : C_MUTED)
                ),
                borderRadius: 3,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        afterLabel(ctx) {
                            if (ctx.raw === maxPeak) return '★ Peak hour';
                            if (ctx.raw < maxPeak * 0.25 && ctx.raw > 0) return '⚠ Low utilization';
                            return '';
                        }
                    }
                }
            },
            scales: { y: yAxis(peakMax) }
        }
    });

    // 6. Staff workload
    @if($staffUtilization->count())
    new Chart(document.getElementById('staffChart'), {
        type: 'bar',
        data: {
            labels: staffData.map(x => x.label),
            datasets: [{
                label: 'Bookings Handled',
                data: staffData.map(x => x.value),
                backgroundColor: C_PRIMARY,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: yAxis(dynMax(staffData.map(x => x.value))) }
        }
    });
    @endif
</script>
@endsection