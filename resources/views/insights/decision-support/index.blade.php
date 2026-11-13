@extends('layouts.app')

@section('title', 'Decision Support')
@section('content')
@php
    // Reports & finance revamp: dashboard card recipe, shared date filter, responsive charts.
    $card     = 'overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $cardHead = 'flex flex-wrap items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700';
    $cardH2   = 'text-base font-semibold text-gray-900 dark:text-white';
    $cardSub  = 'mt-0.5 text-xs text-gray-500 dark:text-gray-400';
    $kpi      = 'p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $kpiLabel = 'min-w-0 text-xs font-semibold tracking-wide text-gray-500 uppercase truncate dark:text-gray-400';
    $kpiIcon  = 'flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-[#8B7355]/10 dark:bg-[#C4A97D]/10';
    $up       = 'text-emerald-700 dark:text-emerald-400';
    $down     = 'text-red-600 dark:text-red-400';

    // Insight look per type; most urgent first so the list reads as a to-do.
    $insightMeta = [
        'danger'  => ['fa-circle-xmark',          'bg-red-50 border-red-200 dark:bg-red-900/15 dark:border-red-800',                 'text-red-600 dark:text-red-400',         'text-red-900 dark:text-red-200',         'text-red-800 dark:text-red-300'],
        'warning' => ['fa-triangle-exclamation',  'bg-amber-50 border-amber-200 dark:bg-amber-900/15 dark:border-amber-800',         'text-amber-600 dark:text-amber-400',     'text-amber-900 dark:text-amber-200',     'text-amber-800 dark:text-amber-300'],
        'info'    => ['fa-circle-info',           'bg-blue-50 border-blue-200 dark:bg-blue-900/15 dark:border-blue-800',             'text-blue-600 dark:text-blue-400',       'text-blue-900 dark:text-blue-200',       'text-blue-800 dark:text-blue-300'],
        'success' => ['fa-circle-check',          'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/15 dark:border-emerald-800', 'text-emerald-600 dark:text-emerald-400', 'text-emerald-900 dark:text-emerald-200', 'text-emerald-800 dark:text-emerald-300'],
    ];
    $order = array_flip(array_keys($insightMeta));
    $sortedInsights = collect($insights)->sortBy(fn ($i) => $order[$i['type']] ?? 2)->values();
    $attention = $sortedInsights->whereIn('type', ['danger', 'warning'])->count();

    $cancelRate = $kpis['cancel_rate'];
    $cancelTone = $cancelRate >= 20 ? 'red' : ($cancelRate >= 10 ? 'amber' : null);
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">

    <x-page-header
        title="Decision Support"
        subtitle="Actionable insights to guide staffing, promotions, and scheduling decisions."
    />

    <x-date-range-filter :from="$filters['from']" :to="$filters['to']" :compare-days="$kpis['period_days']" />

    {{-- ── KPI cards ──────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">

        <div class="{{ $kpi }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Bookings</p>
                <div class="{{ $kpiIcon }}"><i class="text-sm fa-solid fa-calendar-check text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i></div>
            </div>
            <p class="mt-3 text-3xl font-bold text-gray-900 tabular-nums dark:text-white">{{ number_format($kpis['total']) }}</p>
            @if ($kpis['growth'] !== null)
                <p class="mt-1 text-xs font-medium {{ $kpis['growth'] >= 0 ? $up : $down }}">
                    <i class="fa-solid {{ $kpis['growth'] >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}" aria-hidden="true"></i>
                    {{ $kpis['growth'] >= 0 ? '+' : '−' }}{{ abs($kpis['growth']) }}% <span class="font-normal text-gray-500 dark:text-gray-400">vs before</span>
                </p>
            @endif
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $kpis['avg_per_day'] }} a day on average</p>
        </div>

        <div class="{{ $kpi }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Revenue</p>
                <div class="{{ $kpiIcon }}"><i class="text-sm fa-solid fa-peso-sign text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i></div>
            </div>
            <p class="mt-3 text-xl font-bold text-gray-900 tabular-nums sm:text-2xl xl:text-3xl dark:text-white [overflow-wrap:anywhere]">₱{{ number_format($kpis['revenue'], 0) }}</p>
            @if ($kpis['revenue_growth'] !== null)
                <p class="mt-1 text-xs font-medium {{ $kpis['revenue_growth'] >= 0 ? $up : $down }}">
                    <i class="fa-solid {{ $kpis['revenue_growth'] >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}" aria-hidden="true"></i>
                    {{ $kpis['revenue_growth'] >= 0 ? '+' : '−' }}{{ abs($kpis['revenue_growth']) }}% <span class="font-normal text-gray-500 dark:text-gray-400">vs before</span>
                </p>
            @else
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">No earlier data to compare</p>
            @endif
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">From completed bookings</p>
        </div>

        <div class="{{ $kpi }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Spa Rating</p>
                <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-900/30">
                    <i class="text-sm fa-solid fa-star text-amber-500 dark:text-amber-400" aria-hidden="true"></i>
                </div>
            </div>
            <p class="mt-3 text-3xl font-bold text-gray-900 tabular-nums dark:text-white">
                @if ($kpis['avg_spa_rating'] !== null)
                    {{ $kpis['avg_spa_rating'] }}<span class="text-base font-normal text-gray-500 dark:text-gray-400">/5</span>
                @else
                    —
                @endif
            </p>
            @if ($kpis['spa_rating_growth'] !== null)
                <p class="mt-1 text-xs font-medium {{ $kpis['spa_rating_growth'] >= 0 ? $up : $down }}">
                    <i class="fa-solid {{ $kpis['spa_rating_growth'] >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}" aria-hidden="true"></i>
                    {{ $kpis['spa_rating_growth'] >= 0 ? '+' : '−' }}{{ abs($kpis['spa_rating_growth']) }} <span class="font-normal text-gray-500 dark:text-gray-400">vs before</span>
                </p>
            @endif
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $kpis['spa_rating_count'] }} {{ \Illuminate\Support\Str::plural('rating', $kpis['spa_rating_count']) }} this period</p>
        </div>

        <div class="p-4 border shadow-sm sm:p-5 rounded-2xl {{ match ($cancelTone) {
                'red'   => 'bg-red-50 border-red-200 dark:bg-red-900/10 dark:border-red-800',
                'amber' => 'bg-amber-50 border-amber-200 dark:bg-amber-900/10 dark:border-amber-800',
                default => 'bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700',
            } }}">
            <div class="flex items-center justify-between gap-2">
                <p class="min-w-0 text-xs font-semibold tracking-wide uppercase truncate {{ match ($cancelTone) {
                        'red' => 'text-red-700 dark:text-red-400', 'amber' => 'text-amber-700 dark:text-amber-400', default => 'text-gray-500 dark:text-gray-400' } }}">Cancellations</p>
                <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl {{ match ($cancelTone) {
                        'red' => 'bg-red-100 dark:bg-red-900/30', 'amber' => 'bg-amber-100 dark:bg-amber-900/30', default => 'bg-gray-100 dark:bg-gray-700' } }}">
                    <i class="text-sm fa-solid fa-calendar-xmark {{ match ($cancelTone) {
                        'red' => 'text-red-600 dark:text-red-400', 'amber' => 'text-amber-600 dark:text-amber-400', default => 'text-gray-500 dark:text-gray-400' } }}" aria-hidden="true"></i>
                </div>
            </div>
            <p class="mt-3 text-3xl font-bold tabular-nums {{ match ($cancelTone) {
                    'red' => 'text-red-900 dark:text-red-200', 'amber' => 'text-amber-900 dark:text-amber-200', default => 'text-gray-900 dark:text-white' } }}">{{ $cancelRate }}%</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">of bookings this period</p>
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                <span class="inline-block w-2 h-2 mr-1 bg-orange-400 rounded-full" aria-hidden="true"></span>No-show rate {{ $kpis['no_show_rate'] ?? 0 }}%
            </p>
        </div>
    </div>

    {{-- ── Insights (the DSS output) ───────────────────────────────────────── --}}
    <section class="{{ $card }}" aria-labelledby="insightsTitle">
        <div class="{{ $cardHead }}">
            <div class="flex items-start gap-3">
                <div class="flex items-center justify-center flex-shrink-0 w-9 h-9 rounded-xl bg-[#8B7355]/10 dark:bg-[#C4A97D]/10"><i class="text-sm fa-solid fa-lightbulb text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i></div>
                <div>
                    <h2 id="insightsTitle" class="{{ $cardH2 }}">Actionable Insights</h2>
                    <p class="{{ $cardSub }}">System recommendations from patterns in the selected period, most urgent first</p>
                </div>
            </div>
            @if ($attention > 0)
                <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                    {{ $attention }} {{ $attention === 1 ? 'needs' : 'need' }} attention
                </span>
            @endif
        </div>
        <ul class="grid grid-cols-1 gap-3 p-4 sm:p-6 lg:grid-cols-2">
            @foreach ($sortedInsights as $insight)
                @php [$icon, $box, $iconColor, $titleColor, $textColor] = $insightMeta[$insight['type']] ?? $insightMeta['info']; @endphp
                <li class="flex gap-3 p-4 border rounded-xl {{ $box }}">
                    <i class="fa-solid {{ $icon }} mt-0.5 text-base shrink-0 {{ $iconColor }}" aria-hidden="true"></i>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold {{ $titleColor }}">{{ $insight['title'] }}</p>
                        <p class="mt-1 text-sm leading-relaxed {{ $textColor }}">{{ $insight['message'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- ── Trend + day of week ─────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section class="{{ $card }}" aria-labelledby="trendTitle">
            <div class="{{ $cardHead }}">
                <div>
                    <h2 id="trendTitle" class="{{ $cardH2 }}">Daily Booking Trend</h2>
                    <p class="{{ $cardSub }}">Volume over the selected period</p>
                </div>
            </div>
            <div class="p-4 sm:p-6">
                @if (count($bookingTrend))
                    <div class="relative h-56 sm:h-64">
                        <canvas id="trendChart" role="img" aria-label="Line chart of bookings per day">Bookings per day chart.</canvas>
                    </div>
                @else
                    <p class="flex items-center justify-center h-56 text-sm text-gray-500 sm:h-64 dark:text-gray-400">No bookings in this period.</p>
                @endif
            </div>
        </section>

        <section class="{{ $card }}" aria-labelledby="dowTitle">
            <div class="{{ $cardHead }}">
                <div>
                    <h2 id="dowTitle" class="{{ $cardH2 }}">Bookings by Day of Week</h2>
                    <p class="{{ $cardSub }}">Your busiest day is highlighted</p>
                </div>
            </div>
            <div class="p-4 sm:p-6">
                <div class="relative h-56 sm:h-64">
                    <canvas id="dowChart" role="img" aria-label="Bar chart of bookings by day of the week">Bookings by day of week chart.</canvas>
                </div>
            </div>
        </section>
    </div>

    {{-- ── Services + packages ─────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section class="{{ $card }}" aria-labelledby="servicesTitle">
            <div class="{{ $cardHead }}">
                <div>
                    <h2 id="servicesTitle" class="{{ $cardH2 }}">Service Performance</h2>
                    <p class="{{ $cardSub }}">This period vs the one before — spot growth and decline</p>
                </div>
            </div>
            <div class="p-4 sm:p-6">
                @if ($popularServices->count())
                    {{-- Grows with the number of services so bars never get squashed. --}}
                    <div class="relative" style="height: {{ max(240, $popularServices->count() * 44 + 70) }}px">
                        <canvas id="servicesChart" role="img" aria-label="Bar chart comparing bookings per service with the previous period">Service performance chart.</canvas>
                    </div>
                @else
                    <p class="flex items-center justify-center h-40 text-sm text-gray-500 dark:text-gray-400">No service bookings in this period.</p>
                @endif
            </div>
        </section>

        <section class="{{ $card }}" aria-labelledby="packagesTitle">
            <div class="{{ $cardHead }}">
                <div>
                    <h2 id="packagesTitle" class="{{ $cardH2 }}">Package Booking Share</h2>
                    <p class="{{ $cardSub }}">Which packages customers are choosing</p>
                </div>
            </div>
            <div class="p-4 sm:p-6">
                @if ($popularPackages->count())
                    <div class="relative h-64 sm:h-72">
                        <canvas id="packagesChart" role="img" aria-label="Doughnut chart of package bookings">Package share chart.</canvas>
                    </div>
                @else
                    <p class="flex items-center justify-center h-40 text-sm text-gray-500 dark:text-gray-400">No package bookings in this period.</p>
                @endif
            </div>
        </section>
    </div>

    {{-- ── Peak hours ──────────────────────────────────────────────────────── --}}
    <section class="{{ $card }}" aria-labelledby="peakTitle">
        <div class="{{ $cardHead }}">
            <div>
                <h2 id="peakTitle" class="{{ $cardH2 }}">Booking Activity by Hour</h2>
                <p class="{{ $cardSub }}">Peak hours need the most staff; quiet hours suit promos, training and admin work</p>
            </div>
            <ul class="flex flex-wrap gap-3 text-xs text-gray-600 dark:text-gray-300" aria-label="Legend">
                <li class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-[#8B7355] dark:bg-[#C4A97D]" aria-hidden="true"></span>Peak hour</li>
                <li class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-[#D8CBB5] dark:bg-[#5C4D39]" aria-hidden="true"></span>Regular</li>
                <li class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-[#8B7355]/20 dark:bg-[#C4A97D]/20" aria-hidden="true"></span>Low activity</li>
            </ul>
        </div>
        <div class="p-4 sm:p-6">
            @if (count($peakHours))
                <div class="relative h-56 sm:h-64">
                    <canvas id="peakHoursChart" role="img" aria-label="Bar chart of bookings by starting hour">Bookings by hour chart.</canvas>
                </div>
            @else
                <p class="flex items-center justify-center h-40 text-sm text-gray-500 dark:text-gray-400">No bookings in this period.</p>
            @endif
        </div>
    </section>

    {{-- ── Therapist load ──────────────────────────────────────────────────── --}}
    @if ($staffUtilization->count())
        <section class="{{ $card }}" aria-labelledby="staffTitle">
            <div class="{{ $cardHead }}">
                <div>
                    <h2 id="staffTitle" class="{{ $cardH2 }}">Therapist Booking Load</h2>
                    <p class="{{ $cardSub }}">Spot workload imbalances and reassign accordingly</p>
                </div>
            </div>
            <div class="p-4 sm:p-6">
                <div class="relative h-56 sm:h-64">
                    <canvas id="staffChart" role="img" aria-label="Bar chart of bookings handled per therapist">Therapist load chart.</canvas>
                </div>
            </div>
        </section>
    @endif

</div>

@include('partials.insights-charts')
<script>
(function () {
    const LC = window.LevictasCharts;
    if (!LC) return;
    const C = LC.colors;

    const trendData    = @json($bookingTrend);
    const dowData      = @json($dayData);
    const servicesData = @json($popularServices);
    const pkgData      = @json($popularPackages);
    const peakData     = @json($peakHours);
    const staffData    = @json($staffUtilization);

    const el = id => document.getElementById(id);

    // 1. Daily trend
    if (el('trendChart')) {
        const vals = trendData.map(x => x.value);
        new Chart(el('trendChart'), {
            type: 'line',
            data: {
                labels: trendData.map(x => LC.shortDate(x.label)),
                datasets: [{
                    label: 'Bookings', data: vals,
                    borderColor: C.primary, backgroundColor: C.fill,
                    tension: 0.35, cubicInterpolationMode: 'monotone', fill: true, borderWidth: 2,
                    pointRadius: trendData.length > 31 ? 0 : 3, pointHoverRadius: 5,
                    pointBackgroundColor: C.primary,
                }],
            },
            options: {
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false } },
                scales: { x: LC.categoryAxis(8), y: LC.countAxis(vals) },
            },
        });
    }

    // 2. Day of week — busiest day highlighted
    if (el('dowChart')) {
        const vals = dowData.map(x => x.value);
        const top  = Math.max(...vals);
        new Chart(el('dowChart'), {
            type: 'bar',
            data: {
                labels: dowData.map(x => x.label),
                datasets: [{ label: 'Bookings', data: vals, backgroundColor: vals.map(v => v === top && v > 0 ? C.primary : C.muted), borderRadius: 6, maxBarThickness: 40 }],
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { x: LC.categoryAxis(7), y: LC.countAxis(vals) },
            },
        });
    }

    // 3. Services — this period vs before
    if (el('servicesChart')) {
        const all = [...servicesData.map(x => x.value), ...servicesData.map(x => x.prev)];
        new Chart(el('servicesChart'), {
            type: 'bar',
            data: {
                labels: servicesData.map(x => x.label),
                datasets: [
                    { label: 'This period',   data: servicesData.map(x => x.value), backgroundColor: C.primary, borderRadius: 4, maxBarThickness: 16 },
                    { label: 'Period before', data: servicesData.map(x => x.prev),  backgroundColor: C.muted,   borderRadius: 4, maxBarThickness: 16 },
                ],
            },
            options: {
                indexAxis: 'y',
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom' }, tooltip: { displayColors: true } },
                scales: {
                    x: LC.countAxis(all),
                    y: { grid: { display: false }, border: { display: false }, ticks: { callback(v) { const l = this.getLabelForValue(v); return l.length > 22 ? l.slice(0, 21) + '…' : l; } } },
                },
            },
        });
    }

    // 4. Packages — share of bookings
    if (el('packagesChart')) {
        const vals  = pkgData.map(x => x.value);
        const total = vals.reduce((a, b) => a + b, 0);
        new Chart(el('packagesChart'), {
            type: 'doughnut',
            data: {
                labels: pkgData.map(x => x.label),
                datasets: [{ data: vals, backgroundColor: C.palette, borderWidth: 2, borderColor: LC.dark ? '#1f2937' : '#ffffff' }],
            },
            options: {
                cutout: '62%',
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { label: ctx => `${ctx.label}: ${ctx.raw} (${total ? (ctx.raw / total * 100).toFixed(1) : 0}%)` } },
                },
            },
        });
    }

    // 5. Peak hours — peak, regular, low activity (under 25% of the peak)
    if (el('peakHoursChart')) {
        const vals = peakData.map(x => x.value);
        const peak = Math.max(...vals);
        const isLow = v => v > 0 && v < peak * 0.25;
        new Chart(el('peakHoursChart'), {
            type: 'bar',
            data: {
                labels: peakData.map(x => x.label),
                datasets: [{ label: 'Bookings', data: vals, backgroundColor: vals.map(v => v === peak && v > 0 ? C.primary : (isLow(v) ? C.faint : C.muted)), borderRadius: 6, maxBarThickness: 36 }],
            },
            options: {
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { afterLabel: ctx => ctx.raw === peak && peak > 0 ? 'Peak hour' : (isLow(ctx.raw) ? 'Low activity' : '') } },
                },
                scales: { x: LC.categoryAxis(12), y: LC.countAxis(vals) },
            },
        });
    }

    // 6. Therapist load
    if (el('staffChart')) {
        const vals = staffData.map(x => x.value);
        new Chart(el('staffChart'), {
            type: 'bar',
            data: {
                labels: staffData.map(x => x.label),
                datasets: [{ label: 'Bookings handled', data: vals, backgroundColor: C.primary, borderRadius: 6, maxBarThickness: 40 }],
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { x: LC.categoryAxis(12), y: LC.countAxis(vals) },
            },
        });
    }
})();
</script>
@endsection
