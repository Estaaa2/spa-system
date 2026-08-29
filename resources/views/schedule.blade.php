@extends('layouts.app')

@section('title', 'Schedule')
@section('content')
    @php
        $user    = auth()->user();
        $canEdit = $user?->hasBranchPermission('edit appointments') ?? false;
        $days    = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        $statusMeta = [
            'reserved'  => ['label' => 'Reserved',  'accent' => '#2563eb', 'badge' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'],
            'pending'   => ['label' => 'Pending',   'accent' => '#d97706', 'badge' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'],
            'ongoing'   => ['label' => 'Ongoing',   'accent' => '#059669', 'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
            'completed' => ['label' => 'Completed', 'accent' => '#64748b', 'badge' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300'],
            'cancelled' => ['label' => 'Cancelled', 'accent' => '#dc2626', 'badge' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
        ];
        $statusFallback = ['label' => 'Unknown', 'accent' => '#8B7355', 'badge' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300'];

        $selectedDay = 0;
        foreach ($dayDates as $i => $d) {
            if ($d->isToday()) { $selectedDay = $i; break; }
        }

        $dayOpenMinutes = [];
        foreach ($dayDates as $i => $d) {
            $k   = $d->toDateString();
            $str = ($operatingHours[$k]['closed'] ?? true) ? null : ($operatingHours[$k]['opening_time'] ?? null);

            if ($str) {
                [$oh, $om] = explode(':', $str);
                $dayOpenMinutes[$i] = (int) $oh * 60 + (int) $om;
            } else {
                $dayOpenMinutes[$i] = $rangeStartMinutes;
            }
        }
    @endphp

    <style>
        .sched-page { height: calc(100vh - 80px); }

        @media (min-width: 768px) {
            .sched-page { height: calc(100vh - 32px); }
        }

        @supports (height: 100dvh) {
            .sched-page { height: calc(100dvh - 80px); }

            @media (min-width: 768px) {
                .sched-page { height: calc(100dvh - 32px); }
            }
        }

        /* Desktop: seven columns side by side, with the original width floor. */
        @media (min-width: 1024px) {
            .sched-row { min-width: 640px; }
            .sched-col { min-width: 80px; }
        }

        /* Below lg: one day at a time. data-day is server-rendered and then
           driven by Alpine, so the right column shows from first paint. */
        @media (max-width: 1023.98px) {
            @foreach (range(0, 6) as $d)
                .sched-grid[data-day="{{ $d }}"] .sched-col:not([data-col="{{ $d }}"]) { display: none; }
            @endforeach
        }

        /* Day tabs are styled off aria-pressed so Alpine only has to manage one
           attribute, and the state stays correct for assistive tech. */
        .day-tab[aria-pressed="true"] {
            background-color: #8B7355;
            color: #ffffff;
        }
        .day-tab[aria-pressed="true"] .day-tab-sub { color: rgba(255, 255, 255, 0.85); }
        .day-tab[aria-pressed="true"] .day-tab-dot { background-color: #ffffff; }

        @media (prefers-color-scheme: dark) {
            .day-tab[aria-pressed="true"] {
                background-color: #C4A97D;
                color: #1f2937;
            }
            .day-tab[aria-pressed="true"] .day-tab-sub { color: rgba(31, 41, 55, 0.75); }
            .day-tab[aria-pressed="true"] .day-tab-dot { background-color: #1f2937; }
        }
    </style>

    <div class="sched-page flex flex-col gap-4 p-4 mx-auto sm:p-6 max-w-7xl">

        <div class="shrink-0">
            <x-page-header title="Schedule" subtitle="View and manage all appointments in a weekly calendar view." />
        </div>

        <div x-data="scheduleView({{ $selectedDay }})" class="flex flex-col flex-1 min-h-0 gap-4">

            {{-- ── Week Navigation ─────────────────────────────────────────── --}}
            <div class="flex items-center gap-2 p-2 bg-white border border-gray-200 shadow-sm shrink-0 rounded-2xl dark:bg-gray-800 dark:border-gray-700 sm:p-3">

                <div class="flex flex-1">
                    <a href="{{ route('schedule.index') }}"
                       aria-label="Jump to this week"
                       class="inline-flex items-center justify-center gap-1.5 w-11 h-11 sm:w-auto sm:px-4 text-sm font-medium text-[#8B7355] border border-[#8B7355]/40 rounded-xl hover:bg-[#8B7355]/5 transition dark:text-[#C4A97D] dark:border-[#C4A97D]/30">
                        <i class="text-sm fa-regular fa-calendar-check" aria-hidden="true"></i>
                        <span class="hidden sm:inline">Today</span>
                    </a>
                </div>

                <div class="flex items-center gap-1 sm:gap-3">
                    <a href="{{ route('schedule.index', ['week' => $prevWeek]) }}"
                       aria-label="Previous week"
                       class="flex items-center justify-center text-gray-600 transition w-11 h-11 shrink-0 rounded-xl hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                        <i class="text-sm fa-solid fa-chevron-left" aria-hidden="true"></i>
                    </a>

                    {{-- min-width is for label stability across month names, not centring --}}
                    <div class="text-center sm:min-w-[170px]">
                        <div class="text-sm font-semibold text-gray-900 dark:text-white sm:text-base">
                            {{ $startOfWeek->format('M j') }} – {{ $endOfWeek->format('M j') }}
                        </div>
                        <div class="text-[11px] font-normal leading-none text-gray-500 dark:text-gray-400">
                            {{ $startOfWeek->format('Y') }}
                        </div>
                    </div>

                    <a href="{{ route('schedule.index', ['week' => $nextWeek]) }}"
                       aria-label="Next week"
                       class="flex items-center justify-center text-gray-600 transition w-11 h-11 shrink-0 rounded-xl hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                        <i class="text-sm fa-solid fa-chevron-right" aria-hidden="true"></i>
                    </a>
                </div>

                <div class="flex flex-1"></div>
            </div>

            {{-- ── Day selector (for mobile / tablet only) ──────────────────────── --}}
            <div class="shrink-0 lg:hidden" role="group" aria-label="Select a day">
                <div class="flex gap-1 p-1.5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                    @foreach ($dayDates as $i => $date)
                        @php
                            $dk       = $date->toDateString();
                            $dayCount = count($bookingsByDate[$dk] ?? []);
                            $dkClosed = $operatingHours[$dk]['closed'] ?? false;
                        @endphp
                        <button type="button"
                                class="day-tab flex-1 min-w-0 min-h-[52px] px-0.5 py-1.5 rounded-xl transition text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                aria-pressed="{{ $i === $selectedDay ? 'true' : 'false' }}"
                                :aria-pressed="day === {{ $i }} ? 'true' : 'false'"
                                @click="select({{ $i }})"
                                aria-label="{{ $date->format('l, F j') }}{{ $dkClosed ? ', closed' : ', ' . $dayCount . ' appointments' }}">
                            <span class="block text-[11px] font-semibold leading-none">{{ $days[$i] }}</span>
                            <span class="day-tab-sub block mt-1 text-sm font-bold leading-none {{ $dkClosed ? 'text-gray-400 dark:text-gray-500' : '' }}">
                                {{ $date->format('j') }}
                            </span>
                            <span class="flex justify-center h-1.5 mt-1" aria-hidden="true">
                                @if ($dayCount > 0)
                                    <span class="day-tab-dot w-1.5 h-1.5 rounded-full bg-[#8B7355] dark:bg-[#C4A97D]"></span>
                                @endif
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- ── Timetable ────────────────────────────────────────────────── --}}

            <div class="flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">

                <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain rounded-2xl" x-ref="gridScroll">
                    <div class="flex sched-row sched-grid"
                         data-day="{{ $selectedDay }}"
                         :data-day="day">

                    {{-- ── Time-label column ────────────────────────────────── --}}
                    <div class="w-16 border-r border-gray-200 shrink-0 dark:border-gray-700">

                        {{-- Sticky against the grid scroller, not <main> --}}
                        <div class="sticky top-0 z-20 border-b border-gray-200 h-14 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50"></div>

                        <div class="relative" style="height: {{ $totalHeight }}px;">
                            @foreach ($timeLabels as $label)
                                <div class="absolute left-0 right-0 flex justify-end pr-2"
                                     style="top: {{ $label['topPx'] }}px;
                                            {{ $loop->first ? '' : 'transform: translateY(-50%);' }}">
                                    @if ($label['isHour'])
                                        <span class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 whitespace-nowrap leading-none">
                                            {{ $label['labelFull'] }}
                                        </span>
                                    @else
                                        {{-- gray-500/gray-400: the old gray-300 measured 1.47:1 --}}
                                        <span class="text-[9px] text-gray-500 dark:text-gray-400 leading-none">
                                            :30
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- ── Day columns ──────────────────────────────────────── --}}
                    @foreach ($dayDates as $i => $date)
                        @php
                            $dateKey     = $date->toDateString();
                            $isDayClosed = $operatingHours[$dateKey]['closed'] ?? false;
                            $isToday     = $date->isToday();
                            $dayBookings = $bookingsByDate[$dateKey] ?? [];

                            $preOpenPx    = 0;
                            $postClosePx  = 0;
                            $postCloseTop = $totalHeight;

                            if (!$isDayClosed) {
                                $openingStr = $operatingHours[$dateKey]['opening_time'] ?? null;
                                $closingStr = $operatingHours[$dateKey]['closing_time'] ?? null;

                                if ($openingStr) {
                                    [$oh, $om] = explode(':', $openingStr);
                                    $dayOpenMin  = (int)$oh * 60 + (int)$om;
                                    $preOpenPx   = max(($dayOpenMin - $rangeStartMinutes) * $pxPerMinute, 0);
                                }

                                if ($closingStr) {
                                    [$ch, $cm] = explode(':', $closingStr);
                                    $dayCloseMin  = (int)$ch * 60 + (int)$cm;
                                    $postCloseTop = ($dayCloseMin - $rangeStartMinutes) * $pxPerMinute;
                                    $postClosePx  = max($totalHeight - $postCloseTop, 0);
                                }
                            }
                        @endphp

                        <div class="sched-col flex-1 min-w-0 border-r border-gray-200 dark:border-gray-700 last:border-r-0"
                             data-col="{{ $i }}">

                            {{-- Day header --}}
                            <div class="sticky top-0 z-20 h-14 px-1 flex flex-col items-center justify-center border-b border-gray-200 dark:border-gray-700
                                        {{ $isToday
                                            ? 'bg-[#8B7355]/5 dark:bg-[#8B7355]/10'
                                            : 'bg-gray-50 dark:bg-gray-900' }}">

                                <span class="text-xs font-semibold
                                    {{ $isToday ? 'text-[#8B7355] dark:text-[#C4A97D]' : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ $days[$i] }}
                                </span>

                                @if ($isToday)
                                    <span class="text-[10px] font-semibold text-[#8B7355] dark:text-[#C4A97D] leading-none mt-0.5">
                                        {{ $date->format('M') }}
                                    </span>
                                    <div class="w-6 h-6 mt-0.5 rounded-full bg-[#8B7355] flex items-center justify-center">
                                        <span class="text-[11px] font-bold text-white leading-none">
                                            {{ $date->format('j') }}
                                        </span>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ $date->format('M j') }}
                                    </span>
                                @endif
                            </div>

                            {{-- Column body — relative container for cards + grid lines --}}
                            <div class="relative {{ $isToday ? 'bg-[#8B7355]/[0.02] dark:bg-[#8B7355]/[0.04]' : '' }}"
                                 style="height: {{ $totalHeight }}px;"
                                 data-date="{{ $dateKey }}">

                                {{-- Grid lines: solid for hours, dashed for half-hours --}}
                                @foreach ($timeLabels as $label)
                                    <div class="absolute left-0 right-0 pointer-events-none
                                                {{ $label['isHour']
                                                    ? 'border-t border-gray-200 dark:border-gray-700'
                                                    : 'border-t border-dashed border-gray-100 dark:border-gray-700/40' }}"
                                         style="top: {{ $label['topPx'] }}px;">
                                    </div>
                                @endforeach

                                {{-- Current-time indicator (JS positions + shows this) --}}
                                <div class="absolute left-0 right-0 z-20 hidden pointer-events-none current-time-line">
                                    <div class="flex items-center">
                                        <div class="w-2 h-2 -ml-1 bg-red-500 rounded-full shrink-0"></div>
                                        <div class="flex-1 border-t-[2px] border-red-500"></div>
                                    </div>
                                </div>

                                @if ($isDayClosed)
                                    {{-- Fully-closed overlay (whole day) --}}
                                    <div class="absolute inset-0 z-10 flex items-start justify-center pt-8 bg-gray-100/70 dark:bg-gray-700/50">
                                        <span class="px-3 py-1 text-xs font-semibold text-gray-500 bg-gray-200 rounded-full dark:bg-gray-800 dark:text-gray-400">
                                            Spa Closed
                                        </span>
                                    </div>

                                @else

                                    {{-- ── Pre-opening overlay ─────────────────────── --}}
                                    {{-- Covers the rows before this day's opening time --}}
                                    @if ($preOpenPx > 0)
                                        <div class="absolute left-0 right-0 top-0 z-[5] pointer-events-none
                                                    bg-gray-100/70 dark:bg-gray-700/40"
                                             style="height: {{ $preOpenPx }}px;">
                                        </div>
                                    @endif

                                    {{-- ── Post-closing overlay ────────────────────── --}}
                                    {{-- Covers the rows after this day's closing time --}}
                                    @if ($postClosePx > 0)
                                        <div class="absolute left-0 right-0 z-[5] pointer-events-none
                                                    bg-gray-100/70 dark:bg-gray-700/40"
                                             style="top: {{ $postCloseTop }}px; height: {{ $postClosePx }}px;">
                                        </div>
                                    @endif

                                    @if (empty($dayBookings))
                                        <div class="absolute inset-x-0 z-[6] flex justify-center pointer-events-none lg:hidden"
                                             style="top: {{ $preOpenPx + 16 }}px;">
                                            <span class="px-3 py-1 text-xs text-gray-500 rounded-full bg-gray-100/90 dark:bg-gray-700/70 dark:text-gray-400">
                                                No appointments this day
                                            </span>
                                        </div>
                                    @endif

                                    {{-- Appointment cards — pixel-precise positioning --}}
                                    @foreach ($dayBookings as $b)
                                        @php
                                            $widthPct = round(100 / max($b->overlap_total, 1), 3);
                                            $leftPct  = round($b->overlap_column * $widthPct, 3);

                                            $meta = $statusMeta[$b->status] ?? $statusFallback;

                                            $customerName = $b->customer_name ?? 'Walk-in';
                                            // Resolved once for the whole week in ScheduleController.
                                            // Falls back to the accessor if the controller is older.
                                            $treatmentLabel = $b->resolved_treatment ?? $b->treatment_label;
                                            $serviceLabel   = $b->service_type_label;

                                            $startAt   = \Carbon\Carbon::parse($b->start_time);
                                            $endAt     = \Carbon\Carbon::parse($b->end_time);
                                            $statusTxt = ucfirst($b->status);

                                            // Human duration for the modal, e.g. "1 hr 30 min"
                                            $durationMins = max($startAt->diffInMinutes($endAt), 0);
                                            $durHours     = intdiv($durationMins, 60);
                                            $durRemainder = $durationMins % 60;
                                            $durationTxt  = trim(
                                                ($durHours ? $durHours . ' hr ' : '')
                                                . ($durRemainder ? $durRemainder . ' min' : '')
                                            ) ?: '0 min';

                                            $reschedule        = $b->latestRescheduleRequest;
                                            $hasPendingResched = $reschedule?->isPending();

                                            // Progressive detail based on card height
                                            $showTreatment = $b->sched_height_px >= 40;
                                            $showTimeRange = $b->sched_height_px >= 60;

                                            $showStatusChip = (int) $b->overlap_total === 1;

                                            $cardLabel = $statusTxt . ' appointment. '
                                                . $customerName . '. '
                                                . $treatmentLabel . '. '
                                                . $startAt->format('g:i A') . ' to ' . $endAt->format('g:i A') . '.'
                                                . ($hasPendingResched ? ' Reschedule requested.' : '');
                                        @endphp

                                        <div class="absolute z-10 overflow-hidden transition-shadow bg-white border border-gray-200 rounded-lg shadow-sm cursor-pointer dark:bg-gray-900 dark:border-gray-600
                                                     hover:ring-2 hover:ring-[#8B7355]/50
                                                     focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#8B7355] dark:focus-visible:ring-[#C4A97D]"
                                             style="
                                                 top: {{ $b->sched_top_px + 1 }}px;
                                                 height: {{ $b->sched_height_px - 2 }}px;
                                                 left: calc({{ $leftPct }}% + 1px);
                                                 width: calc({{ $widthPct }}% - 4px);
                                                 border-left: 3px solid {{ $meta['accent'] }};
                                             "
                                             role="button"
                                             tabindex="0"
                                             aria-haspopup="dialog"
                                             aria-label="{{ $cardLabel }}"
                                             onclick="openAppointmentModal(this)"
                                             onkeydown="handleCardKeydown(event, this)"
                                             data-booking-id="{{ $b->id }}"
                                             data-customer="{{ $customerName }}"
                                             data-service="{{ $serviceLabel }}"
                                             data-treatment="{{ $treatmentLabel }}"
                                             data-appointment-date="{{ \Carbon\Carbon::parse($b->appointment_date)->format('F d, Y') }}"
                                             data-start-time="{{ $startAt->format('g:i A') }}"
                                             data-end-time="{{ $endAt->format('g:i A') }}"
                                             data-duration="{{ $durationTxt }}"
                                             data-status="{{ $statusTxt }}"
                                             data-reschedule-id="{{ $reschedule?->id }}"
                                             data-reschedule-status="{{ $reschedule?->status }}"
                                             data-reschedule-date="{{ $reschedule?->requested_date?->format('F j, Y') }}"
                                             data-reschedule-time="{{ $reschedule ? \Carbon\Carbon::parse($reschedule->requested_time)->format('g:i A') : '' }}"
                                             data-reschedule-reason="{{ $reschedule?->reason }}"
                                             data-reschedule-rejection="{{ $reschedule?->rejection_reason }}">

                                            {{-- Pending reschedule pulse dot --}}
                                            @if ($hasPendingResched)
                                                <span class="absolute flex w-2 h-2 top-1 right-1" aria-hidden="true">
                                                    <span class="absolute inline-flex w-full h-full bg-orange-400 rounded-full opacity-75 animate-ping"></span>
                                                    <span class="relative inline-flex w-2 h-2 bg-orange-500 rounded-full"></span>
                                                </span>
                                            @endif

                                            <div class="px-1.5 py-1 h-full flex flex-col overflow-hidden" aria-hidden="true">
                                                <div class="flex items-start justify-between gap-0.5">
                                                    <p class="text-[10px] font-semibold leading-tight text-gray-800 dark:text-white truncate flex-1">
                                                        {{ $customerName }}
                                                    </p>
                                                    @if ($showStatusChip)
                                                        <span class="shrink-0 px-1 py-0.5 text-[9px] font-medium rounded-full leading-none {{ $meta['badge'] }}">
                                                            {{ $statusTxt }}
                                                        </span>
                                                    @else
                                                        <span class="w-2 h-2 mt-0.5 rounded-full shrink-0"
                                                              style="background: {{ $meta['accent'] }};"></span>
                                                    @endif
                                                </div>

                                                @if ($showTreatment)
                                                    <p class="mt-0.5 text-[10px] text-gray-500 dark:text-gray-400 truncate leading-tight">
                                                        {{ $treatmentLabel }}
                                                    </p>
                                                @endif

                                                @if ($showTimeRange)
                                                    <p class="mt-auto text-[9px] text-gray-500 dark:text-gray-400 leading-none tabular-nums">
                                                        {{ $startAt->format('g:i') }}–{{ $endAt->format('g:i A') }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach

                                @endif

                            </div>{{-- end column body --}}
                        </div>{{-- end day column --}}
                    @endforeach

                    </div>
                </div>

                <div class="flex-wrap items-center hidden px-4 py-3 border-t border-gray-200 shrink-0 gap-x-4 gap-y-2 dark:border-gray-700 lg:flex">
                    @foreach ($statusMeta as $meta)
                        <span class="inline-flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                            <span class="w-2.5 h-2.5 rounded-sm" style="background: {{ $meta['accent'] }};" aria-hidden="true"></span>
                            {{ $meta['label'] }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════
             APPOINTMENT DETAILS MODAL
        ═══════════════════════════════════════════════════════════════════ --}}
        <div id="appointmentModal"
             role="dialog"
             aria-modal="true"
             aria-labelledby="appointmentModalTitle"
             class="fixed inset-0 z-[100] hidden overflow-y-auto overscroll-contain bg-black/60">

            <div id="appointmentModalScroll" class="flex items-center justify-center min-h-full p-4">
                <div class="w-full max-w-md overflow-hidden bg-white shadow-xl rounded-2xl dark:bg-gray-800">

                    <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="min-w-0">
                            <h3 id="appointmentModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">
                                Appointment Details
                            </h3>
                            <span id="modalStatus" class="inline-block mt-1.5 px-2.5 py-0.5 text-xs font-medium rounded-full"></span>
                        </div>
                        <button type="button" id="modalCloseX" onclick="closeAppointmentModal()"
                            aria-label="Close appointment details"
                            class="flex items-center justify-center w-10 h-10 -mr-2 text-gray-400 transition shrink-0 rounded-xl hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="px-5 py-4 space-y-4">

                        {{-- Who and what, as the primary line --}}
                        <div>
                            <p id="modalCustomer" class="text-base font-semibold text-gray-900 dark:text-white"></p>
                            <p id="modalTreatment" class="mt-0.5 text-sm text-gray-500 dark:text-gray-400"></p>
                        </div>

                        {{-- When, as two scannable cells rather than label/value stacks --}}
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="flex items-start gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-700/50">
                                <i class="fa-regular fa-calendar mt-0.5 text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">Date</p>
                                    <p id="modalDate" class="mt-0.5 text-sm font-medium text-gray-900 dark:text-white"></p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-700/50">
                                <i class="fa-regular fa-clock mt-0.5 text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">Time</p>
                                    <p id="modalTime" class="mt-0.5 text-sm font-medium text-gray-900 dark:text-white tabular-nums"></p>
                                    <p id="modalDuration" class="text-xs text-gray-500 dark:text-gray-400"></p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-1 text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Service</span>
                            <span id="modalService" class="font-medium text-gray-900 dark:text-white"></span>
                        </div>
                    </div>

                    <!-- RESCHEDULE PANEL -->
                    <div id="reschedulePanel" class="hidden border-t border-gray-200 dark:border-gray-700">

                        <!-- Pending -->
                        <div id="reschedulePending" class="hidden px-5 py-4 space-y-4">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300">
                                    <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse inline-block" aria-hidden="true"></span>
                                    Reschedule Requested
                                </span>
                            </div>
                            <div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                                <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-700/50">
                                    <p class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Requested Date</p>
                                    <p id="reschedRequestedDate" class="text-xs font-medium text-gray-900 dark:text-white"></p>
                                </div>
                                <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-700/50">
                                    <p class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Requested Time</p>
                                    <p id="reschedRequestedTime" class="text-xs font-medium text-gray-900 dark:text-white"></p>
                                </div>
                            </div>
                            <div class="p-3 text-sm rounded-xl bg-gray-50 dark:bg-gray-700/50">
                                <p class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Customer's Reason</p>
                                <p id="reschedReason" class="text-xs leading-relaxed text-gray-700 dark:text-gray-300"></p>
                            </div>

                            @if ($canEdit)
                                <div id="reschedActions" class="space-y-2">
                                    <div class="flex gap-2">
                                        <button type="button" onclick="showRejectInput()" id="rejectToggleBtn"
                                            class="flex-1 min-h-[44px] px-4 text-sm font-semibold text-white transition bg-red-600 rounded-xl hover:bg-red-700 disabled:opacity-50">
                                            Reject
                                        </button>
                                        <button type="button" onclick="approveReschedule()" id="approveBtn"
                                            class="flex-1 min-h-[44px] px-4 text-sm font-semibold text-white transition shadow-sm bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-xl hover:opacity-90 disabled:opacity-50">
                                            Approve
                                        </button>
                                    </div>
                                    <div id="rejectReasonBlock" class="hidden space-y-2">
                                        <label for="rejectReasonInput" class="sr-only">Reason for rejection</label>
                                        <textarea id="rejectReasonInput" rows="3"
                                            placeholder="Reason for rejection (required, min 5 characters)..."
                                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl resize-none dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-red-400/40"></textarea>
                                        <div class="flex gap-2">
                                            <button type="button" onclick="rejectReschedule()" id="confirmRejectBtn"
                                                class="flex-1 min-h-[44px] px-4 text-sm font-semibold text-white transition bg-red-600 rounded-xl hover:bg-red-700 disabled:opacity-50">
                                                Confirm Rejection
                                            </button>
                                            <button type="button" onclick="cancelReject()"
                                                class="min-h-[44px] px-4 text-sm font-medium text-gray-700 transition bg-gray-100 rounded-xl hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                    <div id="reschedFeedback" role="status"
                                        class="hidden p-2.5 text-sm rounded-xl text-center font-medium"></div>
                                </div>
                            @endif
                        </div>

                        <!-- Approved -->
                        <div id="rescheduleApproved" class="hidden px-5 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                <i class="fa-solid fa-check" aria-hidden="true"></i>
                                Reschedule Approved
                            </span>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Rescheduled to <span id="approvedNewDate" class="font-semibold text-gray-700 dark:text-gray-200"></span>
                            </p>
                        </div>

                        <!-- Rejected -->
                        <div id="rescheduleRejected" class="hidden px-5 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                Reschedule Rejected
                            </span>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Reason: <span id="rejectedReason" class="font-medium text-gray-700 dark:text-gray-200"></span>
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex justify-end px-5 py-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="button" onclick="closeAppointmentModal()"
                            class="min-h-[44px] px-5 text-sm font-medium text-white transition shadow-sm bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-xl hover:opacity-90">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════
             SCRIPTS
        ═══════════════════════════════════════════════════════════════════ --}}
        <script>
        const SCHED_RANGE_START = {{ $rangeStartMinutes }};
        const SCHED_RANGE_END   = {{ $rangeEndMinutes }};
        const SCHED_PX_PER_MIN  = {{ $pxPerMinute }};
        const SCHED_DAY_OPEN    = @json($dayOpenMinutes);
        const SCHED_TODAY_INDEX = {{ $dayDates[$selectedDay]->isToday() ? $selectedDay : -1 }};

        // ── Day switching + auto-scroll ───────────────────────────────────────
        document.addEventListener('alpine:init', () => {
            Alpine.data('scheduleView', (initial = 0) => ({
                day: initial,

                init() {
                    this.$nextTick(() => this.jumpToUsefulPoint('auto'));
                },

                select(index) {
                    this.day = index;
                    this.$nextTick(() => this.jumpToUsefulPoint('smooth'));
                },

                jumpToUsefulPoint(behavior) {
                    const now        = new Date();
                    const nowMinutes = now.getHours() * 60 + now.getMinutes();
                    const viewingToday = this.day === SCHED_TODAY_INDEX;

                    let targetMinutes;

                    if (viewingToday && nowMinutes >= SCHED_RANGE_START && nowMinutes <= SCHED_RANGE_END) {
                        targetMinutes = nowMinutes;
                    } else {
                        targetMinutes = SCHED_DAY_OPEN[this.day] ?? SCHED_RANGE_START;
                    }

                    const offsetPx = Math.max((targetMinutes - SCHED_RANGE_START) * SCHED_PX_PER_MIN, 0);
                    this.scrollGridTo(offsetPx, behavior);
                },

                scrollGridTo(offsetPx, behavior) {
                    // The calendar's own scroller, not <main>. Scrolling the page
                    // here is what used to drag the heading and week nav out of
                    // view on load.
                    const scroller = this.$refs.gridScroll;
                    if (!scroller) return;

                    const target = Math.max(offsetPx - 56, 0);
                    if (Math.abs(target - scroller.scrollTop) < 8) return;

                    scroller.scrollTo({ top: target, behavior: behavior });
                },
            }));
        });

        // ── Current-time indicator ────────────────────────────────────────────
        (function () {
            function todayStr() {
                const d = new Date();
                const y = d.getFullYear();
                const m = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return `${y}-${m}-${day}`;
            }

            function updateTimeLine() {
                document.querySelectorAll('.current-time-line').forEach(el => {
                    el.classList.add('hidden');
                });

                const now = new Date();
                const currentMin = now.getHours() * 60 + now.getMinutes();

                if (currentMin < SCHED_RANGE_START || currentMin > SCHED_RANGE_END) return;

                const topPx = (currentMin - SCHED_RANGE_START) * SCHED_PX_PER_MIN;
                const col = document.querySelector(`[data-date="${todayStr()}"] .current-time-line`);

                if (col) {
                    col.style.top = topPx + 'px';
                    col.classList.remove('hidden');
                }
            }

            updateTimeLine();
            setInterval(updateTimeLine, 60_000);
        }());

        // ── Modal helpers ─────────────────────────────────────────────────────
        let currentRescheduleId = null;
        let lastFocusedCard     = null;

        const RESCHEDULE_APPROVE_URL = @json(route('reschedule.approve', ['rescheduleRequest' => '__ID__']));
        const RESCHEDULE_REJECT_URL  = @json(route('reschedule.reject',  ['rescheduleRequest' => '__ID__']));

        function rescheduleUrl(template, id) {
            return template.replace('__ID__', encodeURIComponent(id));
        }

        const STATUS_CHIP_CLASSES  = @json(collect($statusMeta)->map(fn ($m) => $m['badge']));
        const STATUS_CHIP_FALLBACK = @json($statusFallback['badge']);

        function modalIsOpen() {
            return !document.getElementById('appointmentModal').classList.contains('hidden');
        }

        function handleCardKeydown(e, el) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                openAppointmentModal(el);
            }
        }

        function openAppointmentModal(el) {
            lastFocusedCard = el;

            document.getElementById('modalCustomer').innerText  = el.dataset.customer  || '';
            document.getElementById('modalService').innerText   = el.dataset.service   || '';
            document.getElementById('modalTreatment').innerText = el.dataset.treatment || '';
            document.getElementById('modalDate').innerText      = el.dataset.appointmentDate || '';
            document.getElementById('modalTime').innerText      = `${el.dataset.startTime || ''} – ${el.dataset.endTime || ''}`;
            document.getElementById('modalDuration').innerText  = el.dataset.duration || '';

            const statusEl = document.getElementById('modalStatus');
            statusEl.innerText = el.dataset.status || '';
            const s = (el.dataset.status || '').toLowerCase();
            statusEl.className = 'inline-block mt-1.5 px-2.5 py-0.5 text-xs font-medium rounded-full '
                + (STATUS_CHIP_CLASSES[s] || STATUS_CHIP_FALLBACK);

            const reschedStatus    = el.dataset.rescheduleStatus    || '';
            const reschedId        = el.dataset.rescheduleId        || '';
            const reschedDate      = el.dataset.rescheduleDate      || '';
            const reschedTime      = el.dataset.rescheduleTime      || '';
            const reschedReason    = el.dataset.rescheduleReason    || '';
            const reschedRejection = el.dataset.rescheduleRejection || '';

            currentRescheduleId = reschedId || null;

            ['reschedulePanel','reschedulePending','rescheduleApproved','rescheduleRejected']
                .forEach(id => document.getElementById(id)?.classList.add('hidden'));

            if (reschedStatus === 'pending' && reschedId) {
                document.getElementById('reschedulePanel').classList.remove('hidden');
                document.getElementById('reschedulePending').classList.remove('hidden');
                document.getElementById('reschedRequestedDate').innerText = reschedDate;
                document.getElementById('reschedRequestedTime').innerText = reschedTime;
                document.getElementById('reschedReason').innerText        = reschedReason;
                resetRejectState();
            } else if (reschedStatus === 'approved') {
                document.getElementById('reschedulePanel').classList.remove('hidden');
                document.getElementById('rescheduleApproved').classList.remove('hidden');
                document.getElementById('approvedNewDate').innerText = `${reschedDate} at ${reschedTime}`;
            } else if (reschedStatus === 'rejected') {
                document.getElementById('reschedulePanel').classList.remove('hidden');
                document.getElementById('rescheduleRejected').classList.remove('hidden');
                document.getElementById('rejectedReason').innerText = reschedRejection || 'No reason provided.';
            }

            const modal = document.getElementById('appointmentModal');
            modal.classList.remove('hidden');
            modal.scrollTop = 0;
            document.getElementById('modalCloseX')?.focus();
        }

        function closeAppointmentModal() {
            // Guard so an Escape press anywhere on the page does not wipe state
            // while the modal is already closed.
            if (!modalIsOpen()) return;

            document.getElementById('appointmentModal').classList.add('hidden');
            currentRescheduleId = null;
            resetRejectState();

            lastFocusedCard?.focus();
            lastFocusedCard = null;
        }

        function resetRejectState() {
            document.getElementById('rejectReasonBlock')?.classList.add('hidden');
            const fb = document.getElementById('reschedFeedback');
            if (fb) { fb.classList.add('hidden'); fb.textContent = ''; }
            const inp = document.getElementById('rejectReasonInput');
            if (inp) inp.value = '';
        }

        function showRejectInput() {
            const block = document.getElementById('rejectReasonBlock');
            if (!block) return;
            resetRejectState();
            block.classList.remove('hidden');
            document.getElementById('rejectReasonInput')?.focus();
        }

        function cancelReject() {
            resetRejectState();
            document.getElementById('rejectToggleBtn')?.focus();
        }

        async function approveReschedule() {
            if (!currentRescheduleId) return;
            const approveBtn = document.getElementById('approveBtn');
            const feedback   = document.getElementById('reschedFeedback');
            approveBtn.disabled    = true;
            approveBtn.textContent = 'Approving…';
            try {
                const res  = await fetch(rescheduleUrl(RESCHEDULE_APPROVE_URL, currentRescheduleId), {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
                });
                const data = await res.json();
                if (!res.ok) {
                    showFeedback(feedback, data.message || 'Something went wrong.', 'error');
                    approveBtn.disabled    = false;
                    approveBtn.textContent = 'Approve';
                    return;
                }
                showFeedback(feedback, 'Approved. The customer has been notified.', 'success');
                document.getElementById('reschedActions').classList.add('hidden');
                setTimeout(() => location.reload(), 1500);
            } catch (e) {
                showFeedback(feedback, 'Network error. Please try again.', 'error');
                approveBtn.disabled    = false;
                approveBtn.textContent = 'Approve';
            }
        }

        async function rejectReschedule() {
            if (!currentRescheduleId) return;
            const reason   = document.getElementById('rejectReasonInput').value.trim();
            const feedback = document.getElementById('reschedFeedback');
            const btn      = document.getElementById('confirmRejectBtn');
            if (!reason || reason.length < 5) {
                showFeedback(feedback, 'Please enter a reason (min 5 characters).', 'error');
                return;
            }
            btn.disabled    = true;
            btn.textContent = 'Rejecting…';
            try {
                const res  = await fetch(rescheduleUrl(RESCHEDULE_REJECT_URL, currentRescheduleId), {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
                    body:    JSON.stringify({ rejection_reason: reason }),
                });
                const data = await res.json();
                if (!res.ok) {
                    showFeedback(feedback, data.message || 'Something went wrong.', 'error');
                    btn.disabled    = false;
                    btn.textContent = 'Confirm Rejection';
                    return;
                }
                showFeedback(feedback, 'Rejected. The customer has been notified.', 'success');
                document.getElementById('reschedActions').classList.add('hidden');
                setTimeout(() => location.reload(), 1500);
            } catch (e) {
                showFeedback(feedback, 'Network error. Please try again.', 'error');
                btn.disabled    = false;
                btn.textContent = 'Confirm Rejection';
            }
        }

        function getCsrf() {
            const meta = document.querySelector('meta[name="csrf-token"]')?.content;
            if (meta) return meta;
            // Laravel URL-encodes XSRF-TOKEN, so the fallback has to decode it.
            const cookie = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1];
            return cookie ? decodeURIComponent(cookie) : '';
        }

        function showFeedback(el, message, type) {
            el.textContent = message;
            el.className   = type === 'success'
                ? 'p-2.5 text-sm rounded-xl text-center font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300'
                : 'p-2.5 text-sm rounded-xl text-center font-medium bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300';
            el.classList.remove('hidden');
        }

        document.addEventListener('DOMContentLoaded', function () {
            const modal  = document.getElementById('appointmentModal');
            const scroll = document.getElementById('appointmentModalScroll');

            if (modal && modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }

            scroll.addEventListener('click', function (e) {
                if (e.target === this) closeAppointmentModal();
            });

            modal.addEventListener('keydown', function (e) {
                if (e.key !== 'Tab') return;
                const nodes = Array.from(modal.querySelectorAll(
                    'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])'
                )).filter(n => n.offsetParent !== null);
                if (!nodes.length) return;

                const first = nodes[0];
                const last  = nodes[nodes.length - 1];

                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modalIsOpen()) closeAppointmentModal();
            });
        });
        </script>

    </div>
@endsection