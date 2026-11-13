@extends('layouts.app')

@section('title', 'Reports')
@section('content')
@php
    // Reports & finance revamp: dashboard card recipe, .rt tables, shared date filter.
    $user = auth()->user();
    $canViewAppointments = $user?->hasBranchPermission('view appointments') ?? false;

    $card     = 'overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $cardHead = 'flex flex-wrap items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700';
    $cardH2   = 'text-base font-semibold text-gray-900 dark:text-white';
    $cardSub  = 'mt-0.5 text-xs text-gray-500 dark:text-gray-400';
    $th       = 'px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase sm:px-6 dark:text-gray-400';
    $td       = 'px-4 py-3 text-sm sm:px-6';
    $kpi      = 'p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $kpiLabel = 'min-w-0 text-xs font-semibold tracking-wide text-gray-500 uppercase truncate dark:text-gray-400';
    $kpiIcon  = 'flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-[#8B7355]/10 dark:bg-[#C4A97D]/10';
    $money    = 'mt-3 text-xl font-bold text-gray-900 tabular-nums sm:text-2xl xl:text-3xl dark:text-white [overflow-wrap:anywhere]';

    $statusMeta = [
        'completed' => ['Completed', 'bg-slate-500'],
        'ongoing'   => ['Ongoing',   'bg-emerald-500'],
        'pending'   => ['Pending',   'bg-amber-500'],
        'reserved'  => ['Reserved',  'bg-blue-500'],
        'cancelled' => ['Cancelled', 'bg-red-500'],
        'no_show'   => ['No-show',   'bg-orange-500'],
    ];
    $sourceMeta = [
        'online'  => ['Online',  'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'],
        'walk_in' => ['Walk-in', 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'],
        'staff'   => ['Staff',   'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'],
    ];
    // Anything that isn't online or walk-in counts as Staff (same rule as before).
    $sources = [];
    foreach ($sourceCounts as $src => $cnt) {
        $key = array_key_exists($src, $sourceMeta) ? $src : 'staff';
        $sources[$key] = ($sources[$key] ?? 0) + $cnt;
    }
    $sources = collect($sources);

    $revenueByType = $treatRevenue + $pkgRevenue;
    $completedCount = $statusSummary['completed'] ?? 0;
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">

    <x-page-header
        title="Reports"
        subtitle="A factual summary of branch activity for the selected period."
    />

    <x-date-range-filter :from="$filters['from']" :to="$filters['to']" />

    {{-- ── KPI cards ──────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">

        <div class="{{ $kpi }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Bookings</p>
                <div class="{{ $kpiIcon }}"><i class="text-sm fa-solid fa-calendar-check text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i></div>
            </div>
            <p class="mt-3 text-3xl font-bold text-gray-900 tabular-nums dark:text-white">{{ number_format($totalBookings) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ number_format($completedCount) }} completed</p>
        </div>

        <div class="{{ $kpi }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Service Revenue</p>
                <div class="{{ $kpiIcon }}"><i class="text-sm fa-solid fa-peso-sign text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i></div>
            </div>
            <p class="{{ $money }}">₱{{ number_format($grossRevenue, 2) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Collected on checked-out appointments</p>
        </div>

        {{-- Booking lifecycle fix, Phase 4: shown next to revenue, never added into it. --}}
        <div class="{{ $kpi }}" title="Money already paid on bookings that ended as no-show or cancelled. Refunds made outside the system aren't tracked.">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Kept Deposits</p>
                <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800">
                    <i class="text-sm fa-solid fa-piggy-bank text-slate-500 dark:text-slate-400" aria-hidden="true"></i>
                </div>
            </div>
            <p class="{{ $money }}">₱{{ number_format($keptDeposits['amount'], 2) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $keptDeposits['count'] }} no-show / cancelled · as recorded</p>
        </div>

        @php $owed = $owedOnOpen['amount'] > 0; @endphp
        <div class="p-4 border shadow-sm sm:p-5 rounded-2xl {{ $owed ? 'bg-amber-50 border-amber-200 dark:bg-amber-900/10 dark:border-amber-800' : 'bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700' }}">
            <div class="flex items-center justify-between gap-2">
                <p class="min-w-0 text-xs font-semibold tracking-wide uppercase line-clamp-2 {{ $owed ? 'text-amber-700 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}">Owed on Open Appointments</p>
                <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl {{ $owed ? 'bg-amber-100 dark:bg-amber-900/30' : 'bg-gray-100 dark:bg-gray-700' }}">
                    <i class="text-sm fa-solid fa-hourglass-half {{ $owed ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}" aria-hidden="true"></i>
                </div>
            </div>
            <p class="mt-3 text-xl font-bold tabular-nums sm:text-2xl xl:text-3xl [overflow-wrap:anywhere] {{ $owed ? 'text-amber-900 dark:text-amber-200' : 'text-gray-900 dark:text-white' }}">₱{{ number_format($owedOnOpen['amount'], 2) }}</p>
            @if ($owedOnOpen['count'] > 0 && $canViewAppointments)
                <a href="{{ route('appointments.index') }}#needs-checkout"
                   class="inline-flex items-center gap-1 mt-1 text-xs font-medium rounded text-amber-700 hover:underline dark:text-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]">
                    {{ $owedOnOpen['count'] }} waiting for checkout <span aria-hidden="true">→</span>
                </a>
            @else
                <p class="mt-1 text-xs {{ $owed ? 'text-amber-700 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $owedOnOpen['count'] }} waiting for checkout</p>
            @endif
        </div>
    </div>

    {{-- ── Booking summary + revenue breakdown ─────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">

        <section class="{{ $card }} lg:col-span-3" aria-labelledby="bookingSummaryTitle">
            <div class="{{ $cardHead }}">
                <div>
                    <h2 id="bookingSummaryTitle" class="{{ $cardH2 }}">Booking Summary</h2>
                    <p class="{{ $cardSub }}">Every booking in the period, by status</p>
                </div>
            </div>
            <div class="p-4 sm:p-6">
                @if ($totalBookings > 0)
                    {{-- Stacked share bar --}}
                    <div class="flex w-full h-2.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700" aria-hidden="true">
                        @foreach ($statusMeta as $key => [$label, $color])
                            @php $pct = ($statusSummary[$key] ?? 0) / $totalBookings * 100; @endphp
                            @if ($pct > 0)<div class="{{ $color }} h-full" style="width: {{ $pct }}%"></div>@endif
                        @endforeach
                    </div>

                    <dl class="grid grid-cols-2 mt-5 gap-x-6 gap-y-4 sm:grid-cols-3">
                        @foreach ($statusMeta as $key => [$label, $color])
                            @php
                                $count = $statusSummary[$key] ?? 0;
                                $pct   = round($count / $totalBookings * 100);
                            @endphp
                            <div class="min-w-0">
                                <dt class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    <span class="inline-block w-2 h-2 rounded-full {{ $color }}" aria-hidden="true"></span>
                                    {{ $label }}
                                </dt>
                                <dd class="mt-1 text-lg font-semibold text-gray-900 tabular-nums dark:text-white">
                                    {{ number_format($count) }}
                                    <span class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ $pct }}%</span>
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <div class="flex flex-col items-center justify-center py-10 text-gray-500 dark:text-gray-400">
                        <i class="mb-3 text-3xl fa-regular fa-calendar-xmark" aria-hidden="true"></i>
                        <p class="text-sm">No bookings in this period.</p>
                    </div>
                @endif
            </div>
        </section>

        <section class="{{ $card }} lg:col-span-2" aria-labelledby="revenueTypeTitle">
            <div class="{{ $cardHead }}">
                <div>
                    <h2 id="revenueTypeTitle" class="{{ $cardH2 }}">Revenue & Sources</h2>
                    <p class="{{ $cardSub }}">Where the service revenue and bookings came from</p>
                </div>
            </div>
            <div class="p-4 space-y-5 sm:p-6">
                <div class="space-y-3">
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Revenue by type</p>
                    @foreach ([['Treatments', $treatRevenue, 'bg-[#8B7355] dark:bg-[#C4A97D]'], ['Packages', $pkgRevenue, 'bg-violet-500']] as [$label, $amount, $bar])
                        @php $pct = $revenueByType > 0 ? round($amount / $revenueByType * 100) : 0; @endphp
                        <div>
                            <div class="flex items-baseline justify-between gap-3 mb-1">
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                                <span class="text-sm font-semibold text-gray-900 tabular-nums dark:text-white">₱{{ number_format($amount, 2) }}</span>
                            </div>
                            <div class="h-2 bg-gray-100 rounded-full dark:bg-gray-700" aria-hidden="true">
                                <div class="h-2 rounded-full {{ $bar }}" style="width: {{ $pct }}%"></div>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $pct }}% of service revenue</p>
                        </div>
                    @endforeach
                </div>

                <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Bookings by source</p>
                    @if ($sources->sum() > 0)
                        <ul class="flex flex-wrap gap-2 mt-3">
                            @foreach ($sources as $src => $cnt)
                                <li class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full {{ $sourceMeta[$src][1] }}">
                                    {{ $sourceMeta[$src][0] }}
                                    <span class="font-semibold tabular-nums">{{ number_format($cnt) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No bookings in this period.</p>
                    @endif
                </div>
            </div>
        </section>
    </div>

    {{-- ── Charts ──────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section class="{{ $card }}" aria-labelledby="bookingsChartTitle">
            <div class="{{ $cardHead }}">
                <div>
                    <h2 id="bookingsChartTitle" class="{{ $cardH2 }}">Bookings per Day</h2>
                    <p class="{{ $cardSub }}">Daily booking volume, all statuses</p>
                </div>
            </div>
            <div class="p-4 sm:p-6">
                @if (count($bookingsPerDay))
                    <div class="relative h-56 sm:h-64">
                        <canvas id="bookingsChart" role="img" aria-label="Bar chart of bookings per day">Bookings per day chart.</canvas>
                    </div>
                @else
                    <p class="flex items-center justify-center h-56 text-sm text-gray-500 sm:h-64 dark:text-gray-400">No bookings in this period.</p>
                @endif
            </div>
        </section>

        <section class="{{ $card }}" aria-labelledby="revenueChartTitle">
            <div class="{{ $cardHead }}">
                <div>
                    <h2 id="revenueChartTitle" class="{{ $cardH2 }}">Billed per Day</h2>
                    <p class="{{ $cardSub }}">Billed amount on completed appointments, per day</p>
                </div>
            </div>
            <div class="p-4 sm:p-6">
                @if (count($revenuePerDay))
                    <div class="relative h-56 sm:h-64">
                        <canvas id="revenueChart" role="img" aria-label="Bar chart of billed amount per day">Billed amount per day chart.</canvas>
                    </div>
                @else
                    <p class="flex items-center justify-center h-56 text-sm text-gray-500 sm:h-64 dark:text-gray-400">No completed appointments in this period.</p>
                @endif
            </div>
        </section>
    </div>

    {{-- ── Service usage ───────────────────────────────────────────────────── --}}
    <section class="{{ $card }}" aria-labelledby="serviceUsageTitle">
        <div class="{{ $cardHead }}">
            <div>
                <h2 id="serviceUsageTitle" class="{{ $cardH2 }}">Service Usage</h2>
                <p class="{{ $cardSub }}">Bookings per treatment and package</p>
            </div>
            <span class="inline-flex items-center px-3 py-1 text-xs font-semibold text-gray-700 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-300">
                {{ count($serviceRows) }} {{ \Illuminate\Support\Str::plural('service', count($serviceRows)) }}
            </span>
        </div>
        <div class="md:overflow-x-auto">
            <table role="table" class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" scope="col" class="{{ $th }}">Service</th>
                        <th role="columnheader" scope="col" class="{{ $th }}">Type</th>
                        <th role="columnheader" scope="col" class="{{ $th }} text-right">Bookings</th>
                        <th role="columnheader" scope="col" class="{{ $th }} text-right">Unit Price</th>
                        <th role="columnheader" scope="col" class="{{ $th }}">Share of Bookings</th>
                    </tr>
                </thead>
                <tbody role="rowgroup" class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                    @forelse ($serviceRows as $row)
                        @php $pct = $totalBookings > 0 ? round(($row['count'] / $totalBookings) * 100, 1) : 0; @endphp
                        <tr role="row" class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td role="cell" data-label="Service" class="{{ $td }} font-medium text-gray-900 dark:text-white">{{ $row['name'] }}</td>
                            <td role="cell" data-label="Type" class="{{ $td }}">
                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full
                                    {{ $row['type'] === 'Package'
                                        ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'
                                        : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                                    {{ $row['type'] }}
                                </span>
                            </td>
                            <td role="cell" data-label="Bookings" class="{{ $td }} font-semibold text-right text-gray-900 tabular-nums dark:text-white">{{ number_format($row['count']) }}</td>
                            <td role="cell" data-label="Unit Price" class="{{ $td }} text-right text-gray-700 tabular-nums whitespace-nowrap dark:text-gray-300">₱{{ number_format($row['unit_price'], 2) }}</td>
                            <td role="cell" data-label="Share of Bookings" class="{{ $td }}">
                                <div class="flex items-center gap-2">
                                    <div class="w-20 h-1.5 rounded-full bg-gray-100 dark:bg-gray-700" aria-hidden="true">
                                        <div class="h-1.5 rounded-full bg-[#8B7355] dark:bg-[#C4A97D]" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500 tabular-nums dark:text-gray-400">{{ $pct }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr role="row">
                            <td role="cell" colspan="5" class="px-6 py-10 text-sm text-center text-gray-500 rt-empty dark:text-gray-400">No service data for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- ── Therapist activity ──────────────────────────────────────────────── --}}
    <section class="{{ $card }}" aria-labelledby="therapistTitle">
        <div class="{{ $cardHead }}">
            <div>
                <h2 id="therapistTitle" class="{{ $cardH2 }}">Therapist Activity</h2>
                <p class="{{ $cardSub }}">Bookings handled and revenue per therapist</p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
                <span class="inline-flex items-center px-3 py-1 font-semibold text-gray-700 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-300">
                    Assigned {{ number_format($assignedCount) }}
                </span>
                <span class="inline-flex items-center px-3 py-1 font-semibold rounded-full {{ $unassignedCount > 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                    Unassigned {{ number_format($unassignedCount) }}
                </span>
            </div>
        </div>

        <div class="md:overflow-x-auto">
            <table role="table" class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" scope="col" class="{{ $th }}">Therapist</th>
                        <th role="columnheader" scope="col" class="{{ $th }} text-right">Bookings Handled</th>
                        <th role="columnheader" scope="col" class="{{ $th }} text-right">Revenue Generated</th>
                        <th role="columnheader" scope="col" class="{{ $th }}">Share of Bookings</th>
                    </tr>
                </thead>
                <tbody role="rowgroup" class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                    @forelse ($therapistRows as $row)
                        @php $pct = $assignedCount > 0 ? round(($row['bookings'] / $assignedCount) * 100, 1) : 0; @endphp
                        <tr role="row" class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td role="cell" data-label="Therapist" class="{{ $td }}">
                                <div class="flex items-center gap-3">
                                    <span class="flex items-center justify-center flex-shrink-0 w-8 h-8 text-xs font-semibold rounded-full bg-[#8B7355]/10 text-[#6F5430] dark:bg-[#C4A97D]/10 dark:text-[#C4A97D]" aria-hidden="true">
                                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($row['name'], 0, 1)) }}
                                    </span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $row['name'] }}</span>
                                </div>
                            </td>
                            <td role="cell" data-label="Bookings Handled" class="{{ $td }} text-right text-gray-700 tabular-nums dark:text-gray-300">{{ number_format($row['bookings']) }}</td>
                            <td role="cell" data-label="Revenue Generated" class="{{ $td }} font-semibold text-right text-gray-900 tabular-nums whitespace-nowrap dark:text-white">₱{{ number_format($row['revenue'], 2) }}</td>
                            <td role="cell" data-label="Share of Bookings" class="{{ $td }}">
                                <div class="flex items-center gap-2">
                                    <div class="w-20 h-1.5 rounded-full bg-gray-100 dark:bg-gray-700" aria-hidden="true">
                                        <div class="h-1.5 rounded-full bg-[#8B7355] dark:bg-[#C4A97D]" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500 tabular-nums dark:text-gray-400">{{ $pct }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr role="row">
                            <td role="cell" colspan="4" class="px-6 py-10 text-sm text-center text-gray-500 rt-empty dark:text-gray-400">No therapist assignment data for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

</div>

@include('partials.insights-charts')
<script>
(function () {
    const LC = window.LevictasCharts;
    if (!LC) return;

    const bookingsPerDay = @json($bookingsPerDay);
    const revenuePerDay  = @json($revenuePerDay);

    const bookingsEl = document.getElementById('bookingsChart');
    if (bookingsEl) {
        const vals = bookingsPerDay.map(x => x.value);
        new Chart(bookingsEl, {
            type: 'bar',
            data: {
                labels: bookingsPerDay.map(x => LC.shortDate(x.label)),
                datasets: [{ label: 'Bookings', data: vals, backgroundColor: LC.colors.primary, borderRadius: 6, maxBarThickness: 28 }],
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { x: LC.categoryAxis(10), y: LC.countAxis(vals) },
            },
        });
    }

    const revenueEl = document.getElementById('revenueChart');
    if (revenueEl) {
        const vals = revenuePerDay.map(x => x.value);
        new Chart(revenueEl, {
            type: 'bar',
            data: {
                labels: revenuePerDay.map(x => LC.shortDate(x.label)),
                datasets: [{ label: 'Billed', data: vals, backgroundColor: LC.colors.primary, borderRadius: 6, maxBarThickness: 28 }],
            },
            options: {
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => LC.peso(ctx.raw) } },
                },
                scales: { x: LC.categoryAxis(10), y: LC.pesoAxis(vals) },
            },
        });
    }
})();
</script>

<style>
@media (max-width: 767px) {
    .rt,
    .rt tbody,
    .rt tr,
    .rt td { display: block; width: 100%; }

    .rt thead { display: none; }

    .rt tr { padding: 0.75rem 1rem; }

    .rt td {
        padding: 0.375rem 0 !important;
        text-align: left !important;
    }

    .rt td[data-label]::before {
        content: attr(data-label);
        display: block;
        margin-bottom: 0.125rem;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .rt td.rt-actions { padding-top: 0.75rem !important; }

    .rt td.rt-empty {
        padding: 2rem 0 !important;
        text-align: center !important;
    }
}

@media (max-width: 767px) and (prefers-color-scheme: dark) {
    .rt td[data-label]::before { color: #9ca3af; }
}
</style>
@endsection
