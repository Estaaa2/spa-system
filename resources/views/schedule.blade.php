@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-7xl">
        <div class="p-6">

            <x-page-header title="Schedule" subtitle="View and manage all appointments in a weekly calendar view." />

            {{-- ── Week Navigation ─────────────────────────────────────────────── --}}
            <div class="flex items-center justify-between p-4 mb-6 bg-white border border-gray-200 rounded-lg dark:bg-gray-800 dark:border-gray-700">

                {{-- "Today" button — jumps back to the current week from anywhere --}}
                <a href="{{ route('schedule.index') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-[#8B7355] border border-[#8B7355]/40 rounded-lg hover:bg-[#8B7355]/5 transition dark:text-[#C4A97D] dark:border-[#C4A97D]/30">
                    <i class="fa-regular fa-calendar-check text-xs"></i>
                    Today
                </a>

                {{-- Week range + prev/next arrows --}}
                <div class="flex items-center gap-3">
                    <a href="{{ route('schedule.index', ['week' => $prevWeek]) }}"
                        class="flex items-center justify-center w-8 h-8 text-gray-600 rounded-lg hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 transition">
                        <i class="fa-solid fa-chevron-left text-xs"></i>
                    </a>
                    <div class="text-base font-semibold text-gray-800 dark:text-white text-center min-w-[200px]">
                        {{ $startOfWeek->format('F Y') }}
                        <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">
                            {{ $startOfWeek->format('M d') }} — {{ $endOfWeek->format('M d') }}
                        </span>
                    </div>
                    <a href="{{ route('schedule.index', ['week' => $nextWeek]) }}"
                        class="flex items-center justify-center w-8 h-8 text-gray-600 rounded-lg hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 transition">
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </a>
                </div>

                {{-- Spacer mirrors Today button width so the center stays centered --}}
                <div class="w-[88px]"></div>
            </div>

            @php
                $user    = auth()->user();
                $canEdit = $user?->hasBranchPermission('edit appointments') ?? false;
                $days    = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            @endphp

            {{-- ── Timetable ──────────────────────────────────────────────────── --}}
            {{--
                No overflow-x-auto wrapper — the CSS spec coerces overflow-y:visible
                → overflow-y:auto whenever overflow-x is set to anything other than
                visible. That creates a second scroll context inside the <main>
                (h-screen overflow-y-auto in navigation.blade), giving the double-
                scroll the design had. Removing the class lets <main> own all vertical
                scrolling. Narrow-viewport horizontal overflow is handled by <main>
                via the same spec rule.
            --}}
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                <div class="flex" style="min-width: 640px;">

                    {{-- ── Time-label column ──────────────────────────────────── --}}
                    <div class="w-16 shrink-0 border-r border-gray-200 dark:border-gray-700">

                        {{-- Sticky day-header spacer --}}
                        <div class="sticky top-0 z-20 h-14 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50"></div>

                        {{--
                            FIX 1 — first-label overlap:
                            Every label uses translateY(-50%) to sit centred on its grid
                            line. At topPx=0 that pulls the label above the container
                            boundary and into the sticky header. For the first label only
                            we skip the transform so it sits flush at the top edge.
                        --}}
                        <div class="relative" style="height: {{ $totalHeight }}px;">
                            @foreach ($timeLabels as $label)
                                <div class="absolute right-0 left-0 flex justify-end pr-2"
                                     style="top: {{ $label['topPx'] }}px;
                                            {{ $loop->first ? '' : 'transform: translateY(-50%);' }}">
                                    @if ($label['isHour'])
                                        <span class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 whitespace-nowrap leading-none">
                                            {{ $label['labelFull'] }}
                                        </span>
                                    @else
                                        <span class="text-[9px] text-gray-300 dark:text-gray-600 leading-none">
                                            :30
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- ── Day columns ────────────────────────────────────────── --}}
                    @foreach ($dayDates as $i => $date)
                        @php
                            $dateKey     = $date->toDateString();
                            $isDayClosed = $operatingHours[$dateKey]['closed'] ?? false;
                            $isToday     = $date->isToday();
                            $dayBookings = $bookingsByDate[$dateKey] ?? [];

                            // FIX 2 — pre/post operating-hours gray overlay.
                            // For days that open later than the global range start, or
                            // close earlier than the global range end, we compute pixel
                            // heights for "not yet open" (top) and "already closed" (bottom)
                            // blocks and render them as semi-transparent overlays. They sit
                            // at z-[5], below appointment cards (z-10) but above grid lines.
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

                        <div class="flex-1 border-r border-gray-200 dark:border-gray-700 last:border-r-0"
                             style="min-width: 80px;">

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
                                <div class="current-time-line hidden absolute left-0 right-0 z-20 pointer-events-none">
                                    <div class="flex items-center">
                                        <div class="w-2 h-2 rounded-full bg-red-500 shrink-0 -ml-1"></div>
                                        <div class="flex-1 border-t-[2px] border-red-500"></div>
                                    </div>
                                </div>

                                @if ($isDayClosed)
                                    {{-- Fully-closed overlay (whole day) --}}
                                    <div class="absolute inset-0 z-10 flex items-center justify-center bg-gray-100/70 dark:bg-gray-700/50">
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

                                    {{-- Appointment cards — pixel-precise positioning --}}
                                    @foreach ($dayBookings as $b)
                                        @php
                                            $widthPct = round(100 / max($b->overlap_total, 1), 3);
                                            $leftPct  = round($b->overlap_column * $widthPct, 3);

                                            $accentColor = match ($b->status) {
                                                'reserved'  => '#d97706',
                                                'pending'   => '#3b82f6',
                                                'ongoing'   => '#16a34a',
                                                'completed' => '#6b7280',
                                                'cancelled' => '#ef4444',
                                                default     => '#8B7355',
                                            };

                                            $badgeCls = match ($b->status) {
                                                'reserved'  => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/60 dark:text-yellow-200',
                                                'pending'   => 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200',
                                                'ongoing'   => 'bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-200',
                                                'completed' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                                'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-200',
                                                default     => 'bg-gray-100 text-gray-700',
                                            };

                                            $reschedule        = $b->latestRescheduleRequest;
                                            $hasPendingResched = $reschedule?->isPending();

                                            // Show progressive detail based on card height
                                            $showTreatment = $b->sched_height_px >= 40;
                                            $showTimeRange = $b->sched_height_px >= 60;
                                        @endphp

                                        <div class="absolute z-10 cursor-pointer overflow-hidden rounded-md
                                                     bg-white dark:bg-gray-900
                                                     border border-gray-200 dark:border-gray-600
                                                     hover:ring-2 hover:ring-[#8B7355]/50 transition-shadow shadow-sm"
                                             style="
                                                 top: {{ $b->sched_top_px + 1 }}px;
                                                 height: {{ $b->sched_height_px - 2 }}px;
                                                 left: calc({{ $leftPct }}% + 1px);
                                                 width: calc({{ $widthPct }}% - 4px);
                                                 border-left: 3px solid {{ $accentColor }};
                                             "
                                             onclick="openAppointmentModal(this)"
                                             data-booking-id="{{ $b->id }}"
                                             data-customer="{{ $b->customer_name ?? 'Walk-in' }}"
                                             data-service="{{ $b->service_type_label }}"
                                             data-treatment="{{ $b->treatment_label }}"
                                             data-date="{{ \Carbon\Carbon::parse($b->appointment_date)->format('F d, Y') }}"
                                             data-start-time="{{ \Carbon\Carbon::parse($b->start_time)->format('g:i A') }}"
                                             data-end-time="{{ \Carbon\Carbon::parse($b->end_time)->format('g:i A') }}"
                                             data-status="{{ ucfirst($b->status) }}"
                                             data-reschedule-id="{{ $reschedule?->id }}"
                                             data-reschedule-status="{{ $reschedule?->status }}"
                                             data-reschedule-date="{{ $reschedule?->requested_date?->format('F j, Y') }}"
                                             data-reschedule-time="{{ $reschedule ? \Carbon\Carbon::parse($reschedule->requested_time)->format('g:i A') : '' }}"
                                             data-reschedule-reason="{{ $reschedule?->reason }}"
                                             data-reschedule-rejection="{{ $reschedule?->rejection_reason }}">

                                            {{-- Pending reschedule pulse dot --}}
                                            @if ($hasPendingResched)
                                                <span class="absolute top-1 right-1 flex h-2 w-2">
                                                    <span class="absolute inline-flex w-full h-full bg-orange-400 rounded-full opacity-75 animate-ping"></span>
                                                    <span class="relative inline-flex w-2 h-2 rounded-full bg-orange-500"></span>
                                                </span>
                                            @endif

                                            <div class="px-1.5 py-1 h-full flex flex-col overflow-hidden">
                                                {{-- Customer name + status badge (always visible) --}}
                                                <div class="flex items-start justify-between gap-0.5">
                                                    <p class="text-[10px] font-semibold leading-tight text-gray-800 dark:text-white truncate flex-1">
                                                        {{ $b->customer_name ?? 'Walk-in' }}
                                                    </p>
                                                    <span class="shrink-0 px-1 py-0.5 text-[9px] font-medium rounded-full leading-none {{ $badgeCls }}">
                                                        {{ ucfirst($b->status) }}
                                                    </span>
                                                </div>

                                                {{-- Treatment name (visible from ~20 min / 40px) --}}
                                                @if ($showTreatment)
                                                    <p class="mt-0.5 text-[10px] text-gray-500 dark:text-gray-400 truncate leading-tight">
                                                        {{ $b->treatment_label }}
                                                    </p>
                                                @endif

                                                {{-- Time range (visible from ~30 min / 60px) --}}
                                                @if ($showTimeRange)
                                                    <p class="mt-auto text-[9px] text-gray-400 dark:text-gray-500 leading-none tabular-nums">
                                                        {{ \Carbon\Carbon::parse($b->start_time)->format('g:i') }}–{{ \Carbon\Carbon::parse($b->end_time)->format('g:i A') }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach

                                @endif

                            </div>{{-- end column body --}}
                        </div>{{-- end day column --}}
                    @endforeach

                </div>{{-- end flex --}}
            </div>{{-- end timetable --}}

            {{-- ══════════════════════════════════════════════════════════════════
                 APPOINTMENT DETAILS MODAL
            ═══════════════════════════════════════════════════════════════════ --}}
            <div id="appointmentModal" class="fixed inset-0 z-50 items-center justify-center hidden bg-black/50">
                <div class="w-full max-w-md overflow-hidden bg-white shadow-xl rounded-xl dark:bg-gray-800">

                    <div class="flex items-center justify-between px-5 py-4 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Appointment Details</h3>
                        <button onclick="closeAppointmentModal()"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">✕</button>
                    </div>

                    <div class="px-5 py-4 space-y-3 text-sm">
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Customer</span>
                            <p id="modalCustomer" class="font-medium text-gray-800 dark:text-white"></p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Service</span>
                            <p id="modalService" class="font-medium text-gray-800 dark:text-white"></p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Treatment</span>
                            <p id="modalTreatment" class="font-medium text-gray-800 dark:text-white"></p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Date</span>
                            <p id="modalDate" class="font-medium text-gray-800 dark:text-white"></p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Time</span>
                            <p id="modalTime" class="font-medium text-gray-800 dark:text-white"></p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Status</span>
                            <span id="modalStatus" class="inline-block px-3 py-1 text-xs font-medium rounded-full"></span>
                        </div>
                    </div>

                    <!-- RESCHEDULE PANEL -->
                    <div id="reschedulePanel" class="hidden border-t dark:border-gray-700">

                        <!-- Pending -->
                        <div id="reschedulePending" class="hidden px-5 py-4 space-y-4">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300">
                                    <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse inline-block"></span>
                                    Reschedule Requested
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Requested Date</p>
                                    <p id="reschedRequestedDate" class="text-xs font-medium text-gray-800 dark:text-white"></p>
                                </div>
                                <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Requested Time</p>
                                    <p id="reschedRequestedTime" class="text-xs font-medium text-gray-800 dark:text-white"></p>
                                </div>
                            </div>
                            <div class="p-3 text-sm rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Customer's Reason</p>
                                <p id="reschedReason" class="text-xs leading-relaxed text-gray-700 dark:text-gray-300"></p>
                            </div>

                            @if ($canEdit)
                                <div id="reschedActions" class="space-y-2">
                                    <div class="flex gap-2">
                                        <button type="button" onclick="toggleRejectInput()" id="rejectToggleBtn"
                                            class="flex-1 py-2 text-sm font-semibold text-white transition bg-red-600 rounded-lg hover:bg-red-700">
                                            Reject
                                        </button>
                                        <button type="button" onclick="approveReschedule()" id="approveBtn"
                                            class="flex-1 py-2 text-sm font-semibold text-white transition bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50">
                                            Approve
                                        </button>
                                    </div>
                                    <div id="rejectReasonBlock" class="hidden space-y-2">
                                        <textarea id="rejectReasonInput" rows="3"
                                            placeholder="Reason for rejection (required, min 5 characters)..."
                                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg resize-none dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-red-400/40"></textarea>
                                        <div class="flex gap-2">
                                            <button type="button" onclick="rejectReschedule()" id="confirmRejectBtn"
                                                class="flex-1 py-2 text-sm font-semibold text-white transition bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50">
                                                Confirm Rejection
                                            </button>
                                            <button type="button" onclick="toggleRejectInput()"
                                                class="px-4 py-2 text-sm font-medium text-gray-600 transition bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                    <div id="reschedFeedback"
                                        class="hidden p-2.5 text-sm rounded-lg text-center font-medium"></div>
                                </div>
                            @endif
                        </div>

                        <!-- Approved -->
                        <div id="rescheduleApproved" class="hidden px-5 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
                                ✓ Reschedule Approved
                            </span>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Rescheduled to <span id="approvedNewDate" class="font-semibold text-gray-700 dark:text-gray-200"></span>
                            </p>
                        </div>

                        <!-- Rejected -->
                        <div id="rescheduleRejected" class="hidden px-5 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                ✕ Reschedule Rejected
                            </span>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Reason: <span id="rejectedReason" class="font-medium text-gray-700 dark:text-gray-200"></span>
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex justify-end px-5 py-4 border-t dark:border-gray-700">
                        <button onclick="closeAppointmentModal()"
                            class="px-4 py-2 text-sm text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348] transition">
                            Close
                        </button>
                    </div>
                </div>
            </div>

        </div>{{-- end p-6 --}}

        {{-- ══════════════════════════════════════════════════════════════════
             SCRIPTS
        ═══════════════════════════════════════════════════════════════════ --}}
        <script>
        // ── Current-time indicator ────────────────────────────────────────────
        (function () {
            const RANGE_START = {{ $rangeStartMinutes }};
            const RANGE_END   = {{ $rangeEndMinutes }};
            const PX_PER_MIN  = {{ $pxPerMinute }};

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

                if (currentMin < RANGE_START || currentMin > RANGE_END) return;

                const topPx = (currentMin - RANGE_START) * PX_PER_MIN;
                const today = todayStr();

                const col = document.querySelector(`[data-date="${today}"] .current-time-line`);
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

        function openAppointmentModal(el) {
            document.getElementById('modalCustomer').innerText  = el.dataset.customer  || '';
            document.getElementById('modalService').innerText   = el.dataset.service   || '';
            document.getElementById('modalTreatment').innerText = el.dataset.treatment || '';
            document.getElementById('modalDate').innerText      = el.dataset.date      || '';
            document.getElementById('modalTime').innerText      = `${el.dataset.startTime || ''} – ${el.dataset.endTime || ''}`;

            const statusEl = document.getElementById('modalStatus');
            statusEl.innerText = el.dataset.status || '';
            const s = (el.dataset.status || '').toLowerCase();
            statusEl.className = 'inline-block px-3 py-1 text-xs font-medium rounded-full ' + ({
                reserved:  'bg-yellow-100 text-yellow-800',
                pending:   'bg-blue-100 text-blue-800',
                ongoing:   'bg-green-100 text-green-800',
                completed: 'bg-gray-200 text-gray-800',
                cancelled: 'bg-red-100 text-red-800',
            }[s] || 'bg-gray-100 text-gray-800');

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
            modal.classList.add('flex');
        }

        function closeAppointmentModal() {
            document.getElementById('appointmentModal').classList.add('hidden');
            document.getElementById('appointmentModal').classList.remove('flex');
            currentRescheduleId = null;
            resetRejectState();
        }

        function resetRejectState() {
            document.getElementById('rejectReasonBlock')?.classList.add('hidden');
            const fb = document.getElementById('reschedFeedback');
            if (fb) { fb.classList.add('hidden'); fb.textContent = ''; }
            const inp = document.getElementById('rejectReasonInput');
            if (inp) inp.value = '';
        }

        function toggleRejectInput() {
            document.getElementById('rejectReasonBlock').classList.toggle('hidden');
        }

        async function approveReschedule() {
            if (!currentRescheduleId) return;
            const approveBtn = document.getElementById('approveBtn');
            const feedback   = document.getElementById('reschedFeedback');
            approveBtn.disabled    = true;
            approveBtn.textContent = 'Approving…';
            try {
                const res  = await fetch(`/reschedule-requests/${currentRescheduleId}/approve`, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
                });
                const data = await res.json();
                if (!res.ok) {
                    showFeedback(feedback, data.message || 'Something went wrong.', 'error');
                    approveBtn.disabled    = false;
                    approveBtn.textContent = '✓ Approve';
                    return;
                }
                showFeedback(feedback, '✓ Approved! Customer has been notified.', 'success');
                document.getElementById('reschedActions').classList.add('hidden');
                setTimeout(() => location.reload(), 1500);
            } catch (e) {
                showFeedback(feedback, 'Network error. Please try again.', 'error');
                approveBtn.disabled    = false;
                approveBtn.textContent = '✓ Approve';
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
                const res  = await fetch(`/reschedule-requests/${currentRescheduleId}/reject`, {
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
                showFeedback(feedback, '✕ Rejected. Customer has been notified.', 'success');
                document.getElementById('reschedActions').classList.add('hidden');
                setTimeout(() => location.reload(), 1500);
            } catch (e) {
                showFeedback(feedback, 'Network error. Please try again.', 'error');
                btn.disabled    = false;
                btn.textContent = 'Confirm Rejection';
            }
        }

        function getCsrf() {
            return document.querySelector('meta[name="csrf-token"]')?.content ??
                document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '';
        }

        function showFeedback(el, message, type) {
            el.textContent = message;
            el.className   = type === 'success'
                ? 'p-2.5 text-sm rounded-lg text-center font-medium bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-300'
                : 'p-2.5 text-sm rounded-lg text-center font-medium bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300';
            el.classList.remove('hidden');
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('appointmentModal').addEventListener('click', function (e) {
                if (e.target === this) closeAppointmentModal();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeAppointmentModal();
            });
        });
        </script>

    </div>{{-- end max-w-7xl --}}
@endsection