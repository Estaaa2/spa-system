@extends('layouts.app')

@section('title', 'Decision Support')
@section('content')
@php
    // DSS revamp: forecast → recommendations → what-if → history. Reports keeps
    // "what happened"; nothing here repeats its charts (D7).
    $card     = 'overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $cardHead = 'flex flex-wrap items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700';
    $cardH2   = 'text-base font-semibold text-gray-900 dark:text-white';
    $cardSub  = 'mt-0.5 text-xs text-gray-500 dark:text-gray-400';
    $kpi      = 'p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $kpiLabel = 'min-w-0 text-xs font-semibold tracking-wide text-gray-500 uppercase truncate dark:text-gray-400';
    $kpiIcon  = 'flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-[#8B7355]/10 dark:bg-[#C4A97D]/10';
    $btnPrimary = 'inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-xl bg-gradient-to-r from-[#8B7355] to-[#6F5430] shadow-sm hover:opacity-90 transition-opacity focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B7355] dark:focus-visible:ring-offset-gray-800';
    $btnSecondary = 'inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]';
    $input = 'w-full px-3 py-2 text-sm text-gray-900 bg-white border border-gray-300 rounded-xl dark:bg-gray-900 dark:text-white dark:border-gray-600 focus:border-[#8B7355] focus:ring-[#8B7355]';
    $label = 'block mb-1 text-xs font-medium text-gray-600 dark:text-gray-300';

    $prioMeta = [
        'high'   => ['High',   'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',         'border-l-red-500'],
        'medium' => ['Medium', 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300', 'border-l-amber-500'],
        'low'    => ['Low',    'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',     'border-l-blue-500'],
    ];
    $ruleIcon = [
        'r1_staffing' => 'fa-calendar-day', 'r2_hire' => 'fa-user-plus', 'r3_overstaffed' => 'fa-couch',
        'r4_promo' => 'fa-tags', 'r6_expansion' => 'fa-store',
    ];

    $days = $forecast['days'];
    $openDays = collect($days)->where('open', true);
    $busiest = $openDays->filter(fn ($d) => $d['use'] !== null)->sortByDesc('use')->first();
    $highCount = $recommendations->where('priority', 'high')->count();
    $useTone = function ($use) use ($thresholds) {
        if ($use === null) return ['bg-gray-300 dark:bg-gray-600', 'text-gray-500 dark:text-gray-400'];
        if ($use >= 100) return ['bg-red-500', 'text-red-700 dark:text-red-400'];
        if ($use >= $thresholds['busy']) return ['bg-amber-500', 'text-amber-700 dark:text-amber-400'];
        if ($use <= $thresholds['light']) return ['bg-blue-400', 'text-blue-700 dark:text-blue-400'];
        return ['bg-emerald-500', 'text-emerald-700 dark:text-emerald-400'];
    };
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl"
     x-data="dssPage(@js(['days' => $days, 'avgDuration' => $forecast['avg_duration'], 'items' => $promoItems]))"
     @dss-staffing.window="openStaffing($event.detail.index)"
     @dss-breakeven.window="openBreakEven($event.detail.code)">

    <x-page-header
        title="Decision Support"
        subtitle="Looks ahead and recommends what to do next. For what already happened, see Reports."
    >
        <x-slot name="right">
            <form method="POST" action="{{ route('decision-support.refresh') }}" class="flex flex-wrap items-center gap-3">
                @csrf
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    @if ($lastSync)
                        Last checked {{ \Illuminate\Support\Carbon::parse($lastSync)->diffForHumans() }}
                    @else
                        Not checked yet
                    @endif
                </span>
                <button type="submit" class="{{ $btnSecondary }}">
                    <i class="text-xs fa-solid fa-rotate" aria-hidden="true"></i> Refresh now
                </button>
            </form>
        </x-slot>
    </x-page-header>

    @if (! $branchId)
        <div class="{{ $card }} p-6 text-sm text-gray-600 dark:text-gray-300">Pick a branch to see its forecast and recommendations.</div>
    @else

    {{-- ── Summary ────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-3 sm:gap-4 sm:grid-cols-3">
        <div class="{{ $kpi }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Needs your decision</p>
                <div class="{{ $kpiIcon }}"><i class="text-sm fa-solid fa-lightbulb text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i></div>
            </div>
            <p class="mt-3 text-3xl font-bold text-gray-900 tabular-nums dark:text-white">{{ $recommendations->count() }}</p>
            <p class="mt-1 text-xs {{ $highCount ? 'font-medium text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">
                {{ $highCount ? $highCount.' high priority' : 'Nothing urgent' }}
            </p>
        </div>
        <div class="{{ $kpi }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Busiest day ahead</p>
                <div class="{{ $kpiIcon }}"><i class="text-sm fa-solid fa-gauge-high text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i></div>
            </div>
            @if ($busiest)
                @php [$bar, $txt] = $useTone($busiest['use']); @endphp
                <p class="mt-3 text-3xl font-bold tabular-nums {{ $txt }}">{{ rtrim(rtrim(number_format($busiest['use'], 1), '0'), '.') }}%</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $busiest['label'] }} · expected capacity use</p>
            @else
                <p class="mt-3 text-3xl font-bold text-gray-400 dark:text-gray-500">—</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">No therapist time scheduled in the next 7 days</p>
            @endif
        </div>
        <div class="{{ $kpi }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Forecast basis</p>
                <div class="{{ $kpiIcon }}"><i class="text-sm fa-solid fa-clock-rotate-left text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i></div>
            </div>
            @if ($forecast['enough_data'])
                <p class="mt-3 text-3xl font-bold text-gray-900 tabular-nums dark:text-white">{{ $forecast['weeks'] }} weeks</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Same-weekday average, plus bookings already made</p>
            @else
                <p class="mt-3 text-xl font-bold text-amber-700 dark:text-amber-400">Not enough data yet</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Needs {{ $thresholds['min_weeks'] }} weeks of bookings ({{ $forecast['weeks'] }} so far). Showing bookings already made only.</p>
            @endif
        </div>
    </div>

    {{-- ── Recommendations ────────────────────────────────────────────────── --}}
    <section class="{{ $card }}" aria-labelledby="dssRecsTitle">
        <div class="{{ $cardHead }}">
            <div>
                <h2 id="dssRecsTitle" class="{{ $cardH2 }}">Recommendations</h2>
                <p class="{{ $cardSub }}">Found from your schedules and bookings. Accept to act on one, or dismiss it.</p>
            </div>
        </div>

        @if ($recommendations->isEmpty())
            <div class="px-4 py-10 text-center sm:px-6">
                <i class="text-2xl fa-solid fa-circle-check text-emerald-500" aria-hidden="true"></i>
                <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No decisions needed right now</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ $forecast['enough_data'] ? 'Staffing, demand and revenue are within the thresholds below.' : 'Recommendations start once this branch has '.$thresholds['min_weeks'].' weeks of bookings.' }}
                </p>
            </div>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($recommendations as $rec)
                    @php
                        [$pLabel, $pClass, $pBorder] = $prioMeta[$rec->priority] ?? $prioMeta['medium'];
                        $url = $actionUrls[$rec->id] ?? null;
                        $nums = $rec->details['numbers'] ?? [];
                    @endphp
                    <li class="px-4 py-4 border-l-4 sm:px-6 {{ $pBorder }}">
                        <div class="flex items-start gap-3">
                            <div class="flex items-center justify-center flex-shrink-0 w-9 h-9 rounded-xl bg-[#8B7355]/10 dark:bg-[#C4A97D]/10">
                                <i class="text-sm fa-solid {{ $ruleIcon[$rec->rule_key] ?? 'fa-lightbulb' }} text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full {{ $pClass }}">{{ $pLabel }}</span>
                                    <span class="text-[11px] font-medium text-gray-500 uppercase tracking-wide dark:text-gray-400">{{ $rec->ruleLabel() }}</span>
                                    @if ($rec->scope === 'spa')
                                        <span class="px-2 py-0.5 text-[11px] font-medium rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">Whole spa · owner only</span>
                                    @endif
                                </div>
                                <h3 class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $rec->title }}</h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $rec->message }}</p>

                                <details class="mt-2 group">
                                    <summary class="text-xs font-medium cursor-pointer text-[#8B7355] dark:text-[#C4A97D] hover:underline">Why am I seeing this?</summary>
                                    <ul class="pl-4 mt-2 space-y-1 text-xs text-gray-600 list-disc dark:text-gray-300">
                                        @foreach ($rec->details['why'] ?? [] as $line)
                                            <li>{{ $line }}</li>
                                        @endforeach
                                        <li>Found {{ $rec->first_seen_at?->diffForHumans() ?? 'just now' }}; numbers updated {{ $rec->last_seen_at?->diffForHumans() ?? 'just now' }}.</li>
                                    </ul>
                                </details>

                                <div class="flex flex-wrap items-center gap-2 mt-3">
                                    <form method="POST" action="{{ route('decision-support.accept', $rec) }}">
                                        @csrf
                                        <button type="submit" class="{{ $btnPrimary }}">
                                            <i class="text-xs fa-solid fa-check" aria-hidden="true"></i>
                                            {{ $url ? 'Accept & '.\Illuminate\Support\Str::lower($rec->action['label'] ?? 'open') : 'Accept' }}
                                        </button>
                                    </form>
                                    @if ($rec->rule_key === 'r4_promo' && ! empty($nums['code']))
                                        <button type="button" class="{{ $btnSecondary }}" @click="$dispatch('dss-breakeven', { code: @js($nums['code']) })">
                                            <i class="text-xs fa-solid fa-calculator" aria-hidden="true"></i> Try break-even
                                        </button>
                                    @elseif (in_array($rec->rule_key, ['r1_staffing', 'r3_overstaffed'], true) && ! empty($nums['date']))
                                        @php $idx = collect($days)->search(fn ($d) => $d['date'] === $nums['date']); @endphp
                                        @if ($idx !== false)
                                            <button type="button" class="{{ $btnSecondary }}" @click="$dispatch('dss-staffing', { index: {{ $idx }} })">
                                                <i class="text-xs fa-solid fa-calculator" aria-hidden="true"></i> Try what-if
                                            </button>
                                        @endif
                                    @endif
                                    <button type="button" class="{{ $btnSecondary }}"
                                            @click="openDismiss(@js(route('decision-support.dismiss', $rec)), @js($rec->title))">
                                        Dismiss
                                    </button>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- ── 7-day staffing forecast ─────────────────────────────────────────── --}}
    <section class="{{ $card }}" aria-labelledby="dssForecastTitle">
        <div class="{{ $cardHead }}">
            <div>
                <h2 id="dssForecastTitle" class="{{ $cardH2 }}">Next 7 days: demand vs therapists</h2>
                <p class="{{ $cardSub }}">Expected booked time ÷ scheduled therapist time. Amber at {{ (int) $thresholds['busy'] }}% or more, blue at {{ (int) $thresholds['light'] }}% or less.</p>
            </div>
        </div>
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach ($days as $i => $d)
                @php [$bar, $txt] = $useTone($d['use']); $w = $d['use'] === null ? 0 : min($d['use'], 100); @endphp
                <li class="grid items-center grid-cols-2 gap-3 px-4 py-3 sm:grid-cols-12 sm:px-6">
                    <div class="sm:col-span-2">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $d['label'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $d['open'] ? $d['therapists'].' '.\Illuminate\Support\Str::plural('therapist', $d['therapists']).' scheduled' : 'Branch closed' }}</p>
                    </div>
                    <div class="text-right sm:text-left sm:col-span-3">
                        <p class="text-sm text-gray-900 tabular-nums dark:text-white">
                            {{ $d['booked_count'] }} booked
                            @if ($forecast['enough_data'] && $d['open'])
                                <span class="text-gray-500 dark:text-gray-400">· ~{{ rtrim(rtrim(number_format($d['expected_count'], 1), '0'), '.') }} expected</span>
                            @endif
                        </p>
                        @if ($d['short_hours'])
                            <p class="text-xs font-medium text-red-600 dark:text-red-400">Short {{ \App\Services\DecisionSupport\DemandForecaster::hourRanges($d['short_hours']) }}</p>
                        @endif
                    </div>
                    <div class="col-span-2 sm:col-span-5">
                        <div class="flex items-center gap-3">
                            <div class="flex-1 h-2.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700" role="img"
                                 aria-label="Expected capacity use {{ $d['use'] === null ? 'not available' : $d['use'].'%' }}">
                                <div class="h-full {{ $bar }}" style="width: {{ $w }}%"></div>
                            </div>
                            <span class="w-14 text-sm font-semibold text-right tabular-nums {{ $txt }}">{{ $d['use'] === null ? '—' : rtrim(rtrim(number_format($d['use'], 1), '0'), '.').'%' }}</span>
                        </div>
                    </div>
                    <div class="col-span-2 sm:col-span-2 sm:text-right">
                        @if ($d['open'])
                            <button type="button" class="text-xs font-medium text-[#8B7355] dark:text-[#C4A97D] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] rounded"
                                    @click="openStaffing({{ $i }})">What if…? <span aria-hidden="true">→</span></button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- ── What-if ────────────────────────────────────────────────────────── --}}
    <section id="dss-whatif" class="{{ $card }}" aria-labelledby="dssWhatIfTitle">
        <div class="{{ $cardHead }}">
            <div>
                <h2 id="dssWhatIfTitle" class="{{ $cardH2 }}">What-if</h2>
                <p class="{{ $cardSub }}">Change a value and see the result. Pure arithmetic on your own numbers: no guessed customer response.</p>
            </div>
            <div class="inline-flex p-1 bg-gray-100 rounded-xl dark:bg-gray-700" role="tablist" aria-label="What-if tools">
                <button type="button" role="tab" :aria-selected="tab === 'staffing'" @click="tab = 'staffing'"
                        :class="tab === 'staffing' ? 'bg-white shadow-sm text-gray-900 dark:bg-gray-800 dark:text-white' : 'text-gray-600 dark:text-gray-300'"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg">Staffing</button>
                <button type="button" role="tab" :aria-selected="tab === 'promo'" @click="tab = 'promo'"
                        :class="tab === 'promo' ? 'bg-white shadow-sm text-gray-900 dark:bg-gray-800 dark:text-white' : 'text-gray-600 dark:text-gray-300'"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg">Promo break-even</button>
            </div>
        </div>

        {{-- Staffing --}}
        <div class="p-4 sm:p-6" x-show="tab === 'staffing'">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label for="wiDay" class="{{ $label }}">Day</label>
                    <select id="wiDay" x-model.number="dayIndex" class="{{ $input }}">
                        <template x-for="(d, i) in days" :key="d.date">
                            <option :value="i" x-text="d.label + (d.open ? '' : ' (closed)')"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label for="wiDelta" class="{{ $label }}">Therapists to add (negative = remove)</label>
                    <div class="flex items-center gap-2">
                        <button type="button" class="{{ $btnSecondary }} !px-3" @click="delta = Math.max(delta - 1, -day().therapists)" aria-label="One fewer">−</button>
                        <input id="wiDelta" type="number" x-model.number="delta" :min="-day().therapists" max="10" class="{{ $input }} text-center tabular-nums">
                        <button type="button" class="{{ $btnSecondary }} !px-3" @click="delta = Math.min(delta + 1, 10)" aria-label="One more">+</button>
                    </div>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 sm:pt-6">
                    Each added therapist works the branch's full hours that day (<span x-text="day().open_start || '—'"></span>–<span x-text="day().open_end || '—'"></span>). Removing one takes away a full shift.
                </div>
            </div>

            <template x-if="!day().open">
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">The branch is closed on this day.</p>
            </template>
            <template x-if="day().open">
                <dl class="grid grid-cols-2 gap-3 mt-5 sm:grid-cols-4">
                    <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-900/40">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Therapists</dt>
                        <dd class="mt-1 text-lg font-semibold text-gray-900 tabular-nums dark:text-white"><span x-text="day().therapists"></span> → <span x-text="Math.max(day().therapists + (delta || 0), 0)"></span></dd>
                    </div>
                    <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-900/40">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Expected capacity use</dt>
                        <dd class="mt-1 text-lg font-semibold tabular-nums" :class="toneText(staffing().after.use)"><span x-text="fmtPct(staffing().before.use)"></span> → <span x-text="fmtPct(staffing().after.use)"></span></dd>
                    </div>
                    <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-900/40">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Bookings that may not get a therapist</dt>
                        <dd class="mt-1 text-lg font-semibold text-gray-900 tabular-nums dark:text-white"><span x-text="fmt(staffing().before.unserved)"></span> → <span x-text="fmt(staffing().after.unserved)"></span></dd>
                    </div>
                    <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-900/40">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Short hours</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white"><span x-text="staffing().before.short || 'none'"></span> → <span x-text="staffing().after.short || 'none'"></span></dd>
                    </div>
                </dl>
            </template>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Unserved bookings = short therapist-minutes ÷ average booking length (<span x-text="fmt(avgDuration)"></span> min). Uses the same expected demand as the forecast above.
            </p>
        </div>

        {{-- Promo break-even --}}
        <div class="p-4 sm:p-6" x-show="tab === 'promo'" x-cloak>
            @if (count($promoItems) === 0)
                <p class="text-sm text-gray-600 dark:text-gray-300">This branch has no treatments or packages yet.</p>
            @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div class="sm:col-span-2">
                    <label for="beItem" class="{{ $label }}">Treatment or package</label>
                    <select id="beItem" x-model="code" @change="pickItem()" class="{{ $input }}">
                        <template x-for="it in items" :key="it.code">
                            <option :value="it.code" x-text="it.name + ' — ₱' + money(it.price)"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label for="beDiscount" class="{{ $label }}">Discount (%)</label>
                    <input id="beDiscount" type="number" min="1" max="90" step="1" x-model.number="discount" class="{{ $input }} tabular-nums">
                </div>
                <div>
                    <label for="beBase" class="{{ $label }}">Bookings now (last 28 days)</label>
                    <input id="beBase" type="number" min="0" max="10000" step="1" x-model.number="base" class="{{ $input }} tabular-nums">
                </div>
            </div>

            <template x-if="breakEven().ok">
                <dl class="grid grid-cols-2 gap-3 mt-5 sm:grid-cols-4">
                    <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-900/40">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Price after discount</dt>
                        <dd class="mt-1 text-lg font-semibold text-gray-900 tabular-nums dark:text-white">₱<span x-text="money(breakEven().newPrice)"></span></dd>
                    </div>
                    <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-900/40">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Revenue to match</dt>
                        <dd class="mt-1 text-lg font-semibold text-gray-900 tabular-nums dark:text-white">₱<span x-text="money(breakEven().revenue)"></span></dd>
                    </div>
                    <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-900/15">
                        <dt class="text-xs text-amber-800 dark:text-amber-300">Bookings needed</dt>
                        <dd class="mt-1 text-lg font-semibold text-amber-900 tabular-nums dark:text-amber-200" x-text="breakEven().needed"></dd>
                    </div>
                    <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-900/15">
                        <dt class="text-xs text-amber-800 dark:text-amber-300">Extra bookings to break even</dt>
                        <dd class="mt-1 text-lg font-semibold text-amber-900 tabular-nums dark:text-amber-200"><span x-text="'+' + breakEven().extra"></span> <span class="text-xs font-medium" x-text="'(+' + breakEven().pct + '%)'"></span></dd>
                    </div>
                </dl>
            </template>
            <template x-if="!breakEven().ok">
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-300" x-text="breakEven().why"></p>
            </template>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Bookings needed = ⌈bookings now × price ÷ discounted price⌉ = ⌈bookings now ÷ (1 − discount)⌉. Assumes every booking in the promo period gets the discount (the worst case).
            </p>
            @endif
        </div>
    </section>

    {{-- ── Decision history ───────────────────────────────────────────────── --}}
    <section class="{{ $card }}" aria-labelledby="dssHistoryTitle">
        <div class="{{ $cardHead }}">
            <div>
                <h2 id="dssHistoryTitle" class="{{ $cardH2 }}">Decision history</h2>
                <p class="{{ $cardSub }}">What was accepted or dismissed, by whom, with the numbers at that time.</p>
            </div>
        </div>
        @if ($history->isEmpty())
            <p class="px-4 py-8 text-sm text-center text-gray-500 sm:px-6 dark:text-gray-400">No decisions yet.</p>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($history as $h)
                    <li class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-start sm:gap-4 sm:px-6">
                        <div class="sm:w-40 shrink-0">
                            @if ($h->status === 'accepted')
                                <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Accepted</span>
                            @else
                                <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">Dismissed</span>
                            @endif
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $h->decided_at?->format('M j, Y g:i A') }}</p>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $h->title }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $h->ruleLabel() }}
                                · by {{ trim(($h->decider->first_name ?? '').' '.($h->decider->last_name ?? '')) ?: 'a former user' }}
                                @if ($h->dismissReasonLabel()) · {{ $h->dismissReasonLabel() }} @endif
                            </p>
                            @if ($h->note)
                                <p class="mt-1 text-xs italic text-gray-600 dark:text-gray-300">“{{ $h->note }}”</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- ── How it works (for the defense) ──────────────────────────────────── --}}
    <details class="{{ $card }} px-4 py-4 sm:px-6">
        <summary class="text-sm font-semibold text-gray-900 cursor-pointer dark:text-white">How Decision Support works</summary>
        <div class="mt-3 space-y-2 text-xs text-gray-600 dark:text-gray-300">
            <p><strong>Forecast.</strong> For each of the next 7 days and each hour: the higher of the minutes already booked and the average for the same weekday and hour over the last {{ $thresholds['weeks'] }} weeks (at least {{ $thresholds['min_weeks'] }} weeks needed).</p>
            <p><strong>Capacity.</strong> Therapist time from each therapist's work schedule, after rest days, one-off days off and shifts, approved leave and branch hours.</p>
            <p><strong>Rules.</strong> Staffing: expected use ≥ {{ (int) $thresholds['busy'] }}% or 1+ hour with nobody scheduled. Hire: use ≥ {{ (int) $thresholds['busy'] }}% in 3 of the last 4 weeks. Light day: 2+ therapists and use ≤ {{ (int) $thresholds['light'] }}%. Promo: a treatment down ≥ 40% vs the 28 days before (3+ bookings before). New branch (owner): each of the last 3 months ≥ 10% above the 3 months before, while also meeting the hire rule.</p>
            <p><strong>When.</strong> Checked every night at 12:30 AM, and when you press Refresh now. A dismissed item stays hidden until the situation changes into a new day, week or month.</p>
            <p><strong>Limit.</strong> Past weeks use today's staff list, so a therapist who has left is not counted on the days they used to work.</p>
        </div>
    </details>

    @endif

    {{-- ── Dismiss modal ──────────────────────────────────────────────────── --}}
    <x-app-modal show="dismissOpen" max-width="md" labelledby="dssDismissTitle">
        <form method="POST" :action="dismissUrl">
            @csrf
            <div class="px-5 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <h2 id="dssDismissTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Dismiss recommendation</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400" x-text="dismissTitle"></p>
            </div>
            <div class="px-5 py-4 space-y-4 sm:px-6">
                <fieldset>
                    <legend class="{{ $label }}">Reason (optional)</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Models\DssRecommendation::DISMISS_REASONS as $key => $text)
                            <label class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium border border-gray-300 cursor-pointer rounded-xl dark:border-gray-600 dark:text-gray-200">
                                <input type="radio" name="reason" value="{{ $key }}" class="text-[#8B7355] focus:ring-[#8B7355]"> {{ $text }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div>
                    <label for="dssDismissNote" class="{{ $label }}">Note (optional)</label>
                    <textarea id="dssDismissNote" name="note" rows="2" maxlength="500" class="{{ $input }}" placeholder="e.g. Already moved Ana's rest day"></textarea>
                </div>
            </div>
            <div class="flex flex-col-reverse gap-2 px-5 py-4 border-t border-gray-200 sm:flex-row sm:justify-end sm:px-6 dark:border-gray-700">
                <button type="button" class="{{ $btnSecondary }}" @click="dismissOpen = false">Cancel</button>
                <button type="submit" class="{{ $btnPrimary }}">Dismiss</button>
            </div>
        </form>
    </x-app-modal>
</div>

<script>
function dssPage(init) {
    const hourLabel = h => { const s = h % 12 === 0 ? 12 : h % 12; return s + ' ' + (h < 12 || h === 24 ? 'AM' : 'PM'); };
    const ranges = hours => {
        const out = []; let a = null, p = null;
        hours.forEach(h => { if (a === null) { a = p = h; } else if (h === p + 1) { p = h; } else { out.push([a, p]); a = p = h; } });
        if (a !== null) out.push([a, p]);
        return out.map(([x, y]) => hourLabel(x) + '–' + hourLabel(y + 1)).join(', ');
    };
    const toMin = t => { if (!t) return null; const [h, m] = t.split(':').map(Number); return h * 60 + (m || 0); };

    return {
        days: init.days || [],
        items: init.items || [],
        avgDuration: init.avgDuration || 60,
        tab: 'staffing',
        dayIndex: Math.max((init.days || []).findIndex(d => d.open), 0),
        delta: 1,
        code: (init.items[0] || {}).code || '',
        discount: 20,
        base: (init.items[0] || {}).bookings || 0,
        dismissOpen: false,
        dismissUrl: '',
        dismissTitle: '',

        day() { return this.days[this.dayIndex] || { therapists: 0, open: false, hours: [] }; },
        openStaffing(i) { this.tab = 'staffing'; this.dayIndex = i; this.delta = 1; this.scrollToWhatIf(); },
        openBreakEven(code) { this.tab = 'promo'; this.code = code; this.pickItem(); this.scrollToWhatIf(); },
        scrollToWhatIf() { this.$nextTick(() => document.getElementById('dss-whatif')?.scrollIntoView({ behavior: 'smooth', block: 'start' })); },
        openDismiss(url, title) { this.dismissUrl = url; this.dismissTitle = title; this.dismissOpen = true; },
        pickItem() { const it = this.items.find(i => i.code === this.code); if (it) this.base = it.bookings; },

        // Same arithmetic as DemandForecaster, with N full shifts added or removed.
        staffing() {
            const d = this.day();
            const n = Number(this.delta) || 0;
            const os = toMin(d.open_start), oe = toMin(d.open_end);
            const byHour = {};
            (d.hours || []).forEach(h => { byHour[h.h] = { s: h.scheduled, e: h.expected }; });
            if (os !== null && oe !== null) {
                for (let h = Math.floor(os / 60); h * 60 < oe; h++) byHour[h] = byHour[h] || { s: 0, e: 0 };
            }
            const calc = add => {
                let s = 0, e = 0, short = 0; const shortHours = [];
                Object.keys(byHour).map(Number).sort((a, b) => a - b).forEach(h => {
                    const open = (os === null) ? 0 : Math.max(0, Math.min(oe, (h + 1) * 60) - Math.max(os, h * 60));
                    const sched = Math.max(0, byHour[h].s + add * open);
                    s += sched; e += byHour[h].e;
                    if (byHour[h].e - sched >= 1) { short += byHour[h].e - sched; shortHours.push(h); }
                });
                return { use: s > 0 ? e / s * 100 : null, unserved: short / Math.max(this.avgDuration, 1), short: ranges(shortHours) };
            };
            return { before: calc(0), after: calc(n) };
        },

        breakEven() {
            const it = this.items.find(i => i.code === this.code);
            const d = Number(this.discount), n = Math.floor(Number(this.base));
            if (!it || !(it.price > 0)) return { ok: false, why: 'This item has no price set.' };
            if (!(d > 0 && d < 100)) return { ok: false, why: 'Enter a discount between 1 and 90%.' };
            if (!(n > 0)) return { ok: false, why: 'No bookings in the last 28 days, so there is no current revenue to protect: every promo booking is extra revenue.' };
            const newPrice = it.price * (1 - d / 100);
            const revenue = n * it.price;
            const needed = Math.ceil(revenue / newPrice - 1e-9);
            return { ok: true, newPrice, revenue, needed, extra: needed - n, pct: Math.round((needed - n) / n * 100) };
        },

        fmt(v) { return v === null || v === undefined ? '—' : (Math.round(v * 10) / 10).toString(); },
        fmtPct(v) { return v === null ? '—' : (Math.round(v * 10) / 10) + '%'; },
        money(v) { return Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        toneText(v) {
            if (v === null) return 'text-gray-500 dark:text-gray-400';
            if (v >= 100) return 'text-red-700 dark:text-red-400';
            if (v >= {{ (int) $thresholds['busy'] }}) return 'text-amber-700 dark:text-amber-400';
            if (v <= {{ (int) $thresholds['light'] }}) return 'text-blue-700 dark:text-blue-400';
            return 'text-emerald-700 dark:text-emerald-400';
        },
    };
}
</script>
@endsection
