@extends('layouts.app')

@section('title', 'Revenue')
@section('content')
@php
    // Reports & finance revamp: dashboard card recipe, .rt table, shared date filter.
    $user = auth()->user();
    $canViewAppointments = $user?->hasBranchPermission('view appointments') ?? false;

    $card     = 'overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $cardHead = 'flex flex-wrap items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700';
    $cardH2   = 'text-base font-semibold text-gray-900 dark:text-white';
    $cardSub  = 'mt-0.5 text-xs text-gray-500 dark:text-gray-400';
    $th       = 'px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase sm:px-6 dark:text-gray-400';
    $td       = 'px-4 py-4 text-sm sm:px-6';
    $kpi      = 'p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $kpiLabel = 'min-w-0 text-xs font-semibold tracking-wide text-gray-500 uppercase truncate dark:text-gray-400';
    $kpiIcon  = 'flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-[#8B7355]/10 dark:bg-[#C4A97D]/10';
    $money    = 'mt-3 text-xl font-bold text-gray-900 tabular-nums sm:text-2xl xl:text-3xl dark:text-white [overflow-wrap:anywhere]';
    $up       = 'text-emerald-700 dark:text-emerald-400';
    $down     = 'text-red-600 dark:text-red-400';

    $sourceMeta = [
        'online'  => ['Online',  'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'],
        'walk_in' => ['Walk-in', 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'],
        'staff'   => ['Staff',   'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'],
    ];
    $onlinePct = $totalRevenue > 0 ? round(($onlineRevenue / $totalRevenue) * 100) : 0;
    $walkPct   = $totalRevenue > 0 ? round(($walkInRevenue / $totalRevenue) * 100) : 0;
    $owed      = $owedOnOpen['amount'] > 0;
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">

    <x-page-header
        title="Revenue"
        subtitle="Revenue from completed appointments for the selected period."
    />

    <x-date-range-filter :from="$from" :to="$to" :compare-days="$periodDays" />

    {{-- ── KPI cards ──────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">

        <div class="{{ $kpi }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Service Revenue</p>
                <div class="{{ $kpiIcon }}"><i class="text-sm fa-solid fa-peso-sign text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i></div>
            </div>
            <p class="{{ $money }}">₱{{ number_format($totalRevenue, 2) }}</p>
            @if ($revenueGrowth !== null)
                <p class="mt-1 text-xs font-medium {{ $revenueGrowth >= 0 ? $up : $down }}">
                    <i class="fa-solid {{ $revenueGrowth >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}" aria-hidden="true"></i>
                    {{ $revenueGrowth >= 0 ? '+' : '−' }}{{ abs($revenueGrowth) }}% <span class="font-normal text-gray-500 dark:text-gray-400">vs before</span>
                </p>
            @else
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">No earlier data to compare</p>
            @endif
        </div>

        <div class="{{ $kpi }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Completed</p>
                <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800">
                    <i class="text-sm fa-solid fa-circle-check text-slate-500 dark:text-slate-400" aria-hidden="true"></i>
                </div>
            </div>
            <p class="mt-3 text-3xl font-bold text-gray-900 tabular-nums dark:text-white">{{ number_format($completedCount) }}</p>
            @if ($countGrowth !== null)
                <p class="mt-1 text-xs font-medium {{ $countGrowth >= 0 ? $up : $down }}">
                    <i class="fa-solid {{ $countGrowth >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}" aria-hidden="true"></i>
                    {{ $countGrowth >= 0 ? '+' : '−' }}{{ abs($countGrowth) }}% <span class="font-normal text-gray-500 dark:text-gray-400">vs before</span>
                </p>
            @else
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Checked-out bookings</p>
            @endif
        </div>

        {{-- Booking lifecycle fix, Phase 4 (Q2-A, Q3-A): kept on no-show / cancelled
             bookings, shown on its own and never added to service revenue. --}}
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

        {{-- Q4-A: balance still owed on ended bookings nobody closed (any date). --}}
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

    {{-- ── Source breakdown + daily chart ──────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <section class="{{ $card }}" aria-labelledby="sourceTitle">
            <div class="{{ $cardHead }}">
                <div>
                    <h2 id="sourceTitle" class="{{ $cardH2 }}">Revenue by Source</h2>
                    <p class="{{ $cardSub }}">How the paying customers booked</p>
                </div>
            </div>
            <div class="p-4 space-y-4 sm:p-6">
                @foreach ([['Online bookings', $onlineRevenue, $onlinePct, 'bg-violet-500'], ['Walk-in / Staff', $walkInRevenue, $walkPct, 'bg-[#8B7355] dark:bg-[#C4A97D]']] as [$label, $amount, $pct, $bar])
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

                <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Average per booking</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 tabular-nums dark:text-white">
                        ₱{{ $completedCount > 0 ? number_format($totalRevenue / $completedCount, 2) : '0.00' }}
                    </p>
                </div>
            </div>
        </section>

        <section class="{{ $card }} lg:col-span-2" aria-labelledby="dailyTitle">
            <div class="{{ $cardHead }}">
                <div>
                    <h2 id="dailyTitle" class="{{ $cardH2 }}">Daily Revenue</h2>
                    <p class="{{ $cardSub }}">Amount collected on checked-out bookings, per day</p>
                </div>
            </div>
            <div class="p-4 sm:p-6">
                @if (count($dailyRevenue))
                    <div class="relative h-64 sm:h-72">
                        <canvas id="revenueChart" role="img" aria-label="Bar chart of revenue collected per day">Daily revenue chart.</canvas>
                    </div>
                @else
                    <p class="flex items-center justify-center h-64 text-sm text-gray-500 sm:h-72 dark:text-gray-400">No revenue in this period.</p>
                @endif
            </div>
        </section>
    </div>

    {{-- ── Completed bookings ──────────────────────────────────────────────── --}}
    <section class="{{ $card }}" aria-labelledby="bookingsTitle">
        <div class="{{ $cardHead }}">
            <div>
                <h2 id="bookingsTitle" class="{{ $cardH2 }}">Completed Bookings</h2>
                <p class="{{ $cardSub }}">Revenue records for completed appointments only</p>
            </div>
            <span class="inline-flex items-center px-3 py-1 text-xs font-semibold text-gray-700 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-300">
                {{ $bookings->count() }} {{ \Illuminate\Support\Str::plural('record', $bookings->count()) }}
            </span>
        </div>

        <div class="md:overflow-x-auto">
            <table role="table" class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" scope="col" class="{{ $th }}">Date</th>
                        <th role="columnheader" scope="col" class="{{ $th }}">Customer</th>
                        <th role="columnheader" scope="col" class="{{ $th }}">Service / Package</th>
                        <th role="columnheader" scope="col" class="{{ $th }}">Source</th>
                        <th role="columnheader" scope="col" class="{{ $th }} text-right">Total</th>
                        <th role="columnheader" scope="col" class="{{ $th }} text-right">Collected</th>
                        <th role="columnheader" scope="col" class="{{ $th }} text-right">Outstanding</th>
                    </tr>
                </thead>
                <tbody role="rowgroup" class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                    @forelse ($bookings as $booking)
                        @php
                            $src = $booking->booking_source ?? '';
                            [$srcLabel, $srcColor] = $sourceMeta[$src] ?? $sourceMeta['staff'];
                        @endphp
                        <tr role="row" class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td role="cell" data-label="Date" class="{{ $td }} text-gray-700 whitespace-nowrap dark:text-gray-300">
                                {{ $booking->appointment_date?->format('M d, Y') }}
                            </td>
                            <td role="cell" data-label="Customer" class="{{ $td }}">
                                <p class="font-medium text-gray-900 dark:text-white">{{ $booking->customer_name ?? 'Walk-in Customer' }}</p>
                                <p class="text-xs text-gray-500 break-all md:break-normal dark:text-gray-400">{{ $booking->customer_email ?? 'No email' }}</p>
                            </td>
                            <td role="cell" data-label="Service / Package" class="{{ $td }}">
                                <p class="text-gray-900 dark:text-white">{{ $booking->treatment_label }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $booking->service_type_label }}</p>
                            </td>
                            <td role="cell" data-label="Source" class="{{ $td }}">
                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full {{ $srcColor }}">{{ $srcLabel }}</span>
                            </td>
                            <td role="cell" data-label="Total" class="{{ $td }} font-semibold text-right text-gray-900 tabular-nums whitespace-nowrap dark:text-white">
                                ₱{{ number_format($booking->total_amount, 2) }}
                            </td>
                            <td role="cell" data-label="Collected" class="{{ $td }} font-medium text-right tabular-nums whitespace-nowrap text-emerald-700 dark:text-emerald-400">
                                ₱{{ number_format($booking->amount_paid, 2) }}
                            </td>
                            <td role="cell" data-label="Outstanding" class="{{ $td }} text-right whitespace-nowrap">
                                @if ($booking->balance_amount > 0)
                                    <span class="font-medium tabular-nums text-amber-700 dark:text-amber-400">₱{{ number_format($booking->balance_amount, 2) }}</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Paid</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr role="row">
                            <td role="cell" colspan="7" class="px-6 py-12 text-sm text-center text-gray-500 rt-empty dark:text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="mb-3 text-3xl fa-regular fa-folder-open" aria-hidden="true"></i>
                                    <p>No completed bookings found for the selected period.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($bookings->count())
                    <tfoot class="hidden bg-gray-50 md:table-footer-group dark:bg-gray-900">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-xs font-semibold text-right text-gray-600 uppercase sm:px-6 dark:text-gray-300">Totals</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-gray-900 tabular-nums sm:px-6 dark:text-white">₱{{ number_format($totalCharged, 2) }}</td>
                            <td class="px-4 py-3 text-sm font-bold text-right tabular-nums sm:px-6 text-emerald-700 dark:text-emerald-400">₱{{ number_format($totalRevenue, 2) }}</td>
                            <td class="px-4 py-3 text-sm font-bold text-right tabular-nums sm:px-6 text-amber-700 dark:text-amber-400">₱{{ number_format($totalOutstanding, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if ($bookings->count())
            {{-- Phones: the totals row as a summary under the cards. --}}
            <dl class="grid grid-cols-3 gap-3 px-4 py-4 border-t border-gray-200 md:hidden bg-gray-50 dark:bg-gray-900 dark:border-gray-700">
                <div>
                    <dt class="text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Total</dt>
                    <dd class="mt-0.5 text-sm font-bold text-gray-900 tabular-nums dark:text-white">₱{{ number_format($totalCharged, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Collected</dt>
                    <dd class="mt-0.5 text-sm font-bold tabular-nums text-emerald-700 dark:text-emerald-400">₱{{ number_format($totalRevenue, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Outstanding</dt>
                    <dd class="mt-0.5 text-sm font-bold tabular-nums text-amber-700 dark:text-amber-400">₱{{ number_format($totalOutstanding, 2) }}</dd>
                </div>
            </dl>
        @endif
    </section>

</div>

@include('partials.insights-charts')
<script>
(function () {
    const LC = window.LevictasCharts;
    const el = document.getElementById('revenueChart');
    if (!LC || !el) return;

    const dailyData = @json($dailyRevenue);
    const vals = dailyData.map(x => x.revenue);

    new Chart(el, {
        type: 'bar',
        data: {
            labels: dailyData.map(x => LC.shortDate(x.label)),
            datasets: [{ label: 'Revenue', data: vals, backgroundColor: LC.colors.primary, borderRadius: 6, maxBarThickness: 32 }],
        },
        options: {
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => LC.peso(ctx.raw),
                        afterLabel: ctx => {
                            const n = dailyData[ctx.dataIndex]?.count ?? 0;
                            return n + (n === 1 ? ' booking' : ' bookings');
                        },
                    },
                },
            },
            scales: { x: LC.categoryAxis(12), y: LC.pesoAxis(vals) },
        },
    });
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
