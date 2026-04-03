@extends('layouts.app')

@section('content')
<div class="p-6 mx-auto space-y-6 max-w-7xl">

    <x-page-header
        title="Reports"
        subtitle="A factual summary of branch activity for the selected period."
    />

    {{-- ── Date Range Filter (same preset pattern as DSS) ───────────────────── --}}
    <form method="GET" id="reportFilterForm">
        <input type="hidden" name="from" id="filterFrom" value="{{ $filters['from'] }}">
        <input type="hidden" name="to"   id="filterTo"   value="{{ $filters['to'] }}">

        <div class="p-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-wrap items-center gap-2">
                @php
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
                    @php $active = $filters['from'] === $pFrom && $filters['to'] === $pTo; @endphp
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
                {{ \Carbon\Carbon::parse($filters['from'])->format('M d, Y') }}
                – {{ \Carbon\Carbon::parse($filters['to'])->format('M d, Y') }}
            </p>
        </div>
    </form>

    {{-- ── Row 1: Booking + Revenue summary cards ─────────────────────────── --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- Booking Summary --}}
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Booking Summary</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Status breakdown for the period</p>
            </div>
            <div class="p-6">
                <div class="flex items-end justify-between mb-4">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Total Bookings</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $totalBookings }}</p>
                    </div>
                    {{-- Source breakdown pills --}}
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($sourceCounts as $src => $cnt)
                            @php
                                $srcLabel = match($src) {
                                    'online' => 'Online',
                                    'walk_in'=> 'Walk-in',
                                    default  => 'Staff',
                                };
                                $srcColor = $src === 'online'
                                    ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'
                                    : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full {{ $srcColor }}">
                                {{ $srcLabel }}: {{ $cnt }}
                            </span>
                        @endforeach
                    </div>
                </div>

                {{-- Status bars --}}
                @php
                    $statusMeta = [
                        'completed' => ['label' => 'Completed', 'color' => 'bg-slate-500'],
                        'ongoing'   => ['label' => 'Ongoing',   'color' => 'bg-emerald-500'],
                        'pending'   => ['label' => 'Pending',   'color' => 'bg-amber-500'],
                        'reserved'  => ['label' => 'Reserved',  'color' => 'bg-blue-500'],
                        'cancelled' => ['label' => 'Cancelled', 'color' => 'bg-red-500'],
                    ];
                @endphp
                <div class="space-y-2.5">
                    @foreach($statusMeta as $key => $meta)
                        @php
                            $count = $statusSummary[$key] ?? 0;
                            $pct   = $totalBookings > 0 ? round(($count / $totalBookings) * 100) : 0;
                        @endphp
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-gray-600 dark:text-gray-400">{{ $meta['label'] }}</span>
                                <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ $count }} <span class="font-normal text-gray-400">({{ $pct }}%)</span></span>
                            </div>
                            <div class="h-1.5 rounded-full bg-gray-100 dark:bg-gray-700">
                                <div class="h-1.5 rounded-full {{ $meta['color'] }}" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Revenue Summary --}}
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Revenue Summary</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Based on actual booking amounts</p>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Gross Revenue (Total Billed)</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">₱{{ number_format($grossRevenue, 2) }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/15">
                        <p class="text-xs text-emerald-700 dark:text-emerald-400">Collected</p>
                        <p class="mt-0.5 text-lg font-semibold text-emerald-800 dark:text-emerald-300">₱{{ number_format($collected, 2) }}</p>
                    </div>
                    <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-900/15">
                        <p class="text-xs text-amber-700 dark:text-amber-400">Outstanding</p>
                        <p class="mt-0.5 text-lg font-semibold text-amber-800 dark:text-amber-300">₱{{ number_format($outstanding, 2) }}</p>
                    </div>
                </div>

                <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                    <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Revenue by Type</p>
                    <div class="flex gap-4">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Treatments</p>
                            <p class="text-sm font-semibold text-gray-800 dark:text-white">₱{{ number_format($treatRevenue, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Packages</p>
                            <p class="text-sm font-semibold text-gray-800 dark:text-white">₱{{ number_format($pkgRevenue, 2) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- ── Charts Row ───────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- Bookings per day --}}
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Bookings per Day</h2>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Daily booking volume</p>
            <canvas id="bookingsChart" height="130"></canvas>
        </div>

        {{-- Revenue per day --}}
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Revenue per Day</h2>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Daily billed amount</p>
            <canvas id="revenueChart" height="130"></canvas>
        </div>

    </div>

    {{-- ── Service Usage Table ──────────────────────────────────────────────── --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Service Usage</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">Bookings per treatment and package</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Service</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Bookings</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Unit Price</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">% of Total</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                    @forelse($serviceRows as $row)
                        @php $pct = $totalBookings > 0 ? round(($row['count'] / $totalBookings) * 100, 1) : 0; @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td class="px-6 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ $row['name'] }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
                                    {{ $row['type'] === 'Package'
                                        ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'
                                        : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                    {{ $row['type'] }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-sm font-semibold text-gray-900 dark:text-white">{{ $row['count'] }}</td>
                            <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">₱{{ number_format($row['unit_price'], 2) }}</td>
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-16 h-1.5 rounded-full bg-gray-100 dark:bg-gray-700">
                                        <div class="h-1.5 rounded-full bg-[#8B7355]" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500">{{ $pct }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-sm text-center text-gray-500">No service data for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Staff / Therapist Summary ─────────────────────────────────────────── --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div>
                <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Therapist Activity</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Bookings handled and revenue per therapist</p>
            </div>
            <div class="flex gap-3 text-xs text-gray-500 dark:text-gray-400">
                <span>Assigned: <strong class="text-gray-800 dark:text-white">{{ $assignedCount }}</strong></span>
                <span>Unassigned: <strong class="text-amber-600">{{ $unassignedCount }}</strong></span>
            </div>
        </div>

        @if($therapistRows->count())
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Therapist</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Bookings Handled</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Revenue Generated</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Share of Bookings</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                    @php $maxTherapistBookings = $therapistRows->max('bookings'); @endphp
                    @foreach($therapistRows as $row)
                        @php $pct = $assignedCount > 0 ? round(($row['bookings'] / $assignedCount) * 100, 1) : 0; @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td class="px-6 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ $row['name'] }}</td>
                            <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row['bookings'] }}</td>
                            <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">₱{{ number_format($row['revenue'], 2) }}</td>
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-16 h-1.5 rounded-full bg-gray-100 dark:bg-gray-700">
                                        <div class="h-1.5 rounded-full bg-[#8B7355]" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500">{{ $pct }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="px-6 py-8 text-sm text-center text-gray-500 dark:text-gray-400">
            No therapist assignment data for this period.
        </div>
        @endif
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    function setPreset(from, to) {
        document.getElementById('filterFrom').value = from;
        document.getElementById('filterTo').value   = to;
        document.getElementById('customFrom').value = from;
        document.getElementById('customTo').value   = to;
        document.getElementById('reportFilterForm').submit();
    }
    function customDateChanged() {
        document.getElementById('filterFrom').value = document.getElementById('customFrom').value;
        document.getElementById('filterTo').value   = document.getElementById('customTo').value;
    }

    const bookingsPerDay = @json($bookingsPerDay);
    const revenuePerDay  = @json($revenuePerDay);
    const C_PRIMARY      = '#8B7355';
    const C_MUTED        = '#C8B89A';

    function dynMax(vals) {
        const m = Math.max(...vals, 0);
        if (m <= 5)  return 5;
        if (m <= 10) return 10;
        if (m <= 20) return 20;
        return Math.ceil(m * 1.2);
    }
    function dynStep(m) {
        if (m <= 5)  return 1;
        if (m <= 10) return 2;
        if (m <= 20) return 5;
        if (m <= 50) return 10;
        return Math.ceil(m / 5);
    }

    // Bookings per day
    const bVals = bookingsPerDay.map(x => x.value);
    const bMax  = dynMax(bVals);
    new Chart(document.getElementById('bookingsChart'), {
        type: 'bar',
        data: {
            labels: bookingsPerDay.map(x => x.label),
            datasets: [{ data: bVals, backgroundColor: C_PRIMARY, borderRadius: 3 }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { maxTicksLimit: 12, font: { size: 10 } } },
                y: { beginAtZero: true, max: bMax, ticks: { stepSize: dynStep(bMax), precision: 0 } }
            }
        }
    });

    // Revenue per day
    const rVals = revenuePerDay.map(x => x.value);
    const rMax  = dynMax(rVals);
    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: {
            labels: revenuePerDay.map(x => x.label),
            datasets: [{ data: rVals, backgroundColor: C_MUTED, borderRadius: 3 }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { maxTicksLimit: 12, font: { size: 10 } } },
                y: {
                    beginAtZero: true,
                    max: rMax,
                    ticks: {
                        stepSize: dynStep(rMax),
                        callback: v => '₱' + v.toLocaleString()
                    }
                }
            }
        }
    });
</script>
@endsection