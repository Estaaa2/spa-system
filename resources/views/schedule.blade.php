@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl">

    <!-- HEADER -->
    <div class="p-6">
        <x-page-header
            title="Schedule"
            subtitle="View and manage all appointments in a weekly calendar view."
        />

    <!-- WEEK NAV -->
    <div class="flex items-center justify-center p-4 mb-6 bg-white border border-gray-200 rounded-lg dark:bg-gray-800 dark:border-gray-700">
        <div class="flex items-center gap-4">
            <a href="{{ route('schedule.index', ['week' => $prevWeek]) }}"
               class="p-2 text-gray-600 rounded-lg hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                &lt;
            </a>

            <div class="text-lg font-semibold text-gray-800 dark:text-white">
                {{ $startOfWeek->format('F Y') }}
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                    ({{ $startOfWeek->format('M d') }} - {{ $endOfWeek->format('M d') }})
                </span>
            </div>

            <a href="{{ route('schedule.index', ['week' => $nextWeek]) }}"
               class="p-2 text-gray-600 rounded-lg hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                &gt;
            </a>
        </div>
    </div>

    @php
        $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
        $dayDates = [];
        foreach (range(0,6) as $i) {
            $dayDates[$i] = $startOfWeek->copy()->addDays($i);
        }

        $timeSlots = array_map(function($t){
            return \Carbon\Carbon::createFromFormat('H:i', $t)->format('g:i A');
        }, $timeSlotKeys);

        $user     = auth()->user();
        $canEdit  = $user?->hasBranchPermission('edit appointments') ?? false;
    @endphp

    <!-- TIMETABLE GRID -->
    <div class="overflow-auto bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
        @php
            $numTimeSlots = count($timeSlotKeys);
            $numDays      = count($dayDates);
        @endphp

        <div class="grid border-b border-gray-200 dark:border-gray-700"
             style="display: grid; grid-template-columns: 80px repeat({{ $numDays }}, 1fr);
                    grid-template-rows: 55px repeat({{ $numTimeSlots }}, 55px);">

            {{-- HEADERS --}}
            <div class="p-2 text-sm text-gray-500 border-b border-r border-gray-200 dark:text-gray-400 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                Time
            </div>

            @foreach($dayDates as $i => $date)
                <div class="px-3 py-2 text-center border-b border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                    <div class="text-sm font-semibold text-gray-800 dark:text-white">
                        {{ $days[$i] }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $date->format('M d') }}
                    </div>
                </div>
            @endforeach

            {{-- TIME LABELS + CELLS --}}
            @foreach($timeSlotKeys as $slotIndex => $timeKey)
                {{-- Time column --}}
                <div class="p-2 text-sm text-gray-500 border-b border-r border-gray-200 dark:text-gray-400 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                    {{ \Carbon\Carbon::createFromFormat('H:i', $timeKey)->format('g:i A') }}
                </div>

                {{-- Day cells --}}
                @foreach($dayDates as $dayIndex => $date)
                    @php
                        $dateKey      = $date->toDateString();
                        $cellBookings = $grid[$dateKey][$timeKey] ?? [];
                        $isDayClosed  = $operatingHours[$dateKey]['closed'] ?? false;
                    @endphp

                    <div class="relative border-b border-r border-gray-200 dark:border-gray-700 last:border-r-0">

                        {{-- SPA CLOSED --}}
                        @if($isDayClosed)
                            <div class="absolute inset-0 z-50 flex items-center justify-center bg-gray-200 dark:bg-gray-700 opacity-90"
                                style="pointer-events: auto;">
                                <span class="px-3 py-1 text-xs font-semibold text-gray-600 bg-gray-300 rounded-full dark:bg-gray-800 dark:text-gray-300">
                                    Spa Closed
                                </span>
                            </div>
                        @else
                            {{-- EMPTY SLOT BUTTON --}}
                            <button type="button"
                                    class="absolute inset-0 z-10 w-full h-full text-sm text-center text-gray-400 transition-opacity opacity-0 dark:text-gray-500 hover:opacity-100"
                                    onclick="event.stopPropagation(); alert('Click to add booking: {{ $dateKey }} {{ $timeKey }}');">
                                Click to add
                            </button>

                            {{-- APPOINTMENTS --}}
                            @foreach($cellBookings as $b)
                                @php
                                    $startTime  = \Carbon\Carbon::parse($b->start_time)->format('H:i');
                                    $isStartSlot = $startTime === $timeKey;
                                    if($isStartSlot){
                                        $minutes = \Carbon\Carbon::parse($b->start_time)
                                                    ->diffInMinutes(\Carbon\Carbon::parse($b->end_time));
                                        $rowspan = ceil($minutes / 30);
                                        $badge   = match($b->status) {
                                            'reserved'  => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                            'pending'   => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                            'ongoing'   => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                            'completed' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                            'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                            default     => 'bg-gray-100 text-gray-800'
                                        };

                                        $reschedule       = $b->latestRescheduleRequest;
                                        $hasPendingResched = $reschedule?->isPending();
                                    }
                                @endphp

                                @if($isStartSlot)
                                    <div
                                        class="absolute top-0 left-0 z-20 w-full p-2 border rounded-lg cursor-pointer dark:border-gray-700 bg-white/70 dark:bg-gray-900/30 hover:ring-2 hover:ring-[#8B7355]/40 transition"
                                        style="height: calc({{ $rowspan }} * 100%);"
                                        onclick="openAppointmentModal(this)"
                                        data-booking-id="{{ $b->id }}"
                                        data-customer="{{ $b->customer_name ?? 'Walk-in' }}"
                                        data-service="{{ $b->service_type_label }}"
                                        data-treatment="{{ $b->treatment_label }}"
                                        data-date="{{ \Carbon\Carbon::parse($b->appointment_date)->format('F d, Y') }}"
                                        data-time="{{ \Carbon\Carbon::parse($b->start_time)->format('h:i A') }} - {{ \Carbon\Carbon::parse($b->end_time)->format('h:i A') }}"
                                        data-status="{{ ucfirst($b->status) }}"
                                        data-reschedule-id="{{ $reschedule?->id }}"
                                        data-reschedule-status="{{ $reschedule?->status }}"
                                        data-reschedule-date="{{ $reschedule?->requested_date?->format('F j, Y') }}"
                                        data-reschedule-time="{{ $reschedule ? \Carbon\Carbon::parse($reschedule->requested_time)->format('g:i A') : '' }}"
                                        data-reschedule-reason="{{ $reschedule?->reason }}"
                                        data-reschedule-rejection="{{ $reschedule?->rejection_reason }}"
                                    >
                                        {{-- Pending reschedule indicator dot --}}
                                        @if($hasPendingResched)
                                            <span class="absolute top-1.5 right-1.5 flex h-2.5 w-2.5">
                                                <span class="absolute inline-flex w-full h-full bg-orange-400 rounded-full opacity-75 animate-ping"></span>
                                                <span class="relative inline-flex w-2.5 h-2.5 rounded-full bg-orange-500"></span>
                                            </span>
                                        @endif

                                        <div class="flex items-center justify-between gap-2">
                                            <div class="text-sm font-semibold text-gray-800 truncate dark:text-white">
                                                {{ $b->customer_name ?? 'Walk-in' }}
                                            </div>
                                            <span class="px-2 py-0.5 text-[10px] font-medium rounded-full {{ $badge }}">
                                                {{ ucfirst($b->status) }}
                                            </span>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-600 truncate dark:text-gray-300">
                                            {{ ucfirst($b->service_type_label) }} • {{ $b->treatment_label }}
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @endif
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>

    <!-- ====================================================
         APPOINTMENT DETAILS MODAL
         ==================================================== -->
    <div id="appointmentModal" class="fixed inset-0 z-50 items-center justify-center hidden bg-black/50">
        <div class="w-full max-w-md overflow-hidden bg-white shadow-xl rounded-xl dark:bg-gray-800">

            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Appointment Details</h3>
                <button onclick="closeAppointmentModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    ✕
                </button>
            </div>

            <!-- Booking Info -->
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
                    <span class="text-gray-500 dark:text-gray-400">Date &amp; Time</span>
                    <p id="modalDateTime" class="font-medium text-gray-800 dark:text-white"></p>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Status</span>
                    <span id="modalStatus" class="inline-block px-3 py-1 text-xs font-medium rounded-full"></span>
                </div>
            </div>

            <!-- ===== RESCHEDULE PANEL (shown if a request exists) ===== -->
            <div id="reschedulePanel" class="hidden border-t dark:border-gray-700">

                <!-- Pending state -->
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

                    @if($canEdit)
                    <!-- Action buttons -->
                    <div id="reschedActions" class="space-y-2">
                        <div class="flex gap-2">
                            <button type="button"
                                onclick="approveReschedule()"
                                id="approveBtn"
                                class="flex-1 py-2 text-sm font-semibold text-white transition bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50">
                                ✓ Approve
                            </button>
                            <button type="button"
                                onclick="toggleRejectInput()"
                                id="rejectToggleBtn"
                                class="flex-1 py-2 text-sm font-semibold text-red-700 transition bg-red-100 rounded-lg hover:bg-red-200 dark:bg-red-900/30 dark:text-red-300">
                                ✕ Reject
                            </button>
                        </div>

                        <!-- Rejection reason (hidden until reject is toggled) -->
                        <div id="rejectReasonBlock" class="hidden space-y-2">
                            <textarea id="rejectReasonInput" rows="3"
                                placeholder="Reason for rejection (required, min 5 characters)..."
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg resize-none dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-red-400/40"></textarea>
                            <div class="flex gap-2">
                                <button type="button"
                                    onclick="rejectReschedule()"
                                    id="confirmRejectBtn"
                                    class="flex-1 py-2 text-sm font-semibold text-white transition bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50">
                                    Confirm Rejection
                                </button>
                                <button type="button"
                                    onclick="toggleRejectInput()"
                                    class="px-4 py-2 text-sm font-medium text-gray-600 transition bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300">
                                    Cancel
                                </button>
                            </div>
                        </div>

                        <!-- Feedback message -->
                        <div id="reschedFeedback" class="hidden p-2.5 text-sm rounded-lg text-center font-medium"></div>
                    </div>
                    @endif
                </div>

                <!-- Approved state -->
                <div id="rescheduleApproved" class="hidden px-5 py-4">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
                        ✓ Reschedule Approved
                    </span>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Rescheduled to <span id="approvedNewDate" class="font-semibold text-gray-700 dark:text-gray-200"></span>
                    </p>
                </div>

                <!-- Rejected state -->
                <div id="rescheduleRejected" class="hidden px-5 py-4">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                        ✕ Reschedule Rejected
                    </span>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Reason: <span id="rejectedReason" class="font-medium text-gray-700 dark:text-gray-200"></span>
                    </p>
                </div>

            </div>
            <!-- ===== END RESCHEDULE PANEL ===== -->

            <!-- Footer -->
            <div class="flex justify-end px-5 py-4 border-t dark:border-gray-700">
                <button onclick="closeAppointmentModal()"
                        class="px-4 py-2 text-sm text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348] transition">
                    Close
                </button>
            </div>
        </div>
    </div>

</div>

<!-- ====================================================
     SCRIPTS
     ==================================================== -->
<script>
    // Tracks the current reschedule request id for API calls
    let currentRescheduleId = null;

    function openAppointmentModal(el) {
        // --- Booking info ---
        document.getElementById('modalCustomer').innerText    = el.dataset.customer    || '';
        document.getElementById('modalService').innerText     = el.dataset.service     || '';
        document.getElementById('modalTreatment').innerText   = el.dataset.treatment   || '';
        document.getElementById('modalDateTime').innerText    = `${el.dataset.date} at ${el.dataset.time}`;

        const statusEl = document.getElementById('modalStatus');
        statusEl.innerText = el.dataset.status || '';
        const s = (el.dataset.status || '').toLowerCase();
        let cls = 'bg-gray-100 text-gray-800';
        if (s === 'reserved')  cls = 'bg-blue-100 text-blue-800';
        if (s === 'pending')   cls = 'bg-yellow-100 text-yellow-800';
        if (s === 'ongoing')   cls = 'bg-green-100 text-green-800';
        if (s === 'completed') cls = 'bg-gray-200 text-gray-800';
        if (s === 'cancelled') cls = 'bg-red-100 text-red-800';
        statusEl.className = `inline-block px-3 py-1 text-xs font-medium rounded-full ${cls}`;

        // --- Reschedule panel ---
        const reschedStatus    = el.dataset.rescheduleStatus   || '';
        const reschedId        = el.dataset.rescheduleId       || '';
        const reschedDate      = el.dataset.rescheduleDate     || '';
        const reschedTime      = el.dataset.rescheduleTime     || '';
        const reschedReason    = el.dataset.rescheduleReason   || '';
        const reschedRejection = el.dataset.rescheduleRejection|| '';

        currentRescheduleId = reschedId || null;

        const panel    = document.getElementById('reschedulePanel');
        const pending  = document.getElementById('reschedulePending');
        const approved = document.getElementById('rescheduleApproved');
        const rejected = document.getElementById('rescheduleRejected');

        // Hide all sub-panels first
        panel.classList.add('hidden');
        pending.classList.add('hidden');
        approved.classList.add('hidden');
        rejected.classList.add('hidden');

        if (reschedStatus === 'pending' && reschedId) {
            panel.classList.remove('hidden');
            pending.classList.remove('hidden');
            document.getElementById('reschedRequestedDate').innerText = reschedDate;
            document.getElementById('reschedRequestedTime').innerText = reschedTime;
            document.getElementById('reschedReason').innerText        = reschedReason;
            // Reset reject block & feedback
            resetRejectState();

        } else if (reschedStatus === 'approved') {
            panel.classList.remove('hidden');
            approved.classList.remove('hidden');
            document.getElementById('approvedNewDate').innerText = `${reschedDate} at ${reschedTime}`;

        } else if (reschedStatus === 'rejected') {
            panel.classList.remove('hidden');
            rejected.classList.remove('hidden');
            document.getElementById('rejectedReason').innerText = reschedRejection || 'No reason provided.';
        }

        const modal = document.getElementById('appointmentModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeAppointmentModal() {
        const modal = document.getElementById('appointmentModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        currentRescheduleId = null;
        resetRejectState();
    }

    function resetRejectState() {
        const block = document.getElementById('rejectReasonBlock');
        const feedback = document.getElementById('reschedFeedback');
        if (block) block.classList.add('hidden');
        if (feedback) {
            feedback.classList.add('hidden');
            feedback.textContent = '';
        }
        const input = document.getElementById('rejectReasonInput');
        if (input) input.value = '';
    }

    function toggleRejectInput() {
        document.getElementById('rejectReasonBlock').classList.toggle('hidden');
    }

    // =====================================================
    // APPROVE
    // =====================================================
    async function approveReschedule() {
        if (!currentRescheduleId) return;

        const approveBtn = document.getElementById('approveBtn');
        const feedback   = document.getElementById('reschedFeedback');
        approveBtn.disabled = true;
        approveBtn.textContent = 'Approving…';

        try {
            const res = await fetch(`/reschedule-requests/${currentRescheduleId}/approve`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                },
            });
            const data = await res.json();

            if (!res.ok) {
                showFeedback(feedback, data.message || 'Something went wrong.', 'error');
                approveBtn.disabled = false;
                approveBtn.textContent = '✓ Approve';
                return;
            }

            showFeedback(feedback, '✓ Approved! Customer has been notified.', 'success');
            document.getElementById('reschedActions').classList.add('hidden');

            // Refresh the page after a short delay so the schedule reflects the update
            setTimeout(() => location.reload(), 1500);

        } catch (e) {
            showFeedback(feedback, 'Network error. Please try again.', 'error');
            approveBtn.disabled = false;
            approveBtn.textContent = '✓ Approve';
        }
    }

    // =====================================================
    // REJECT
    // =====================================================
    async function rejectReschedule() {
        if (!currentRescheduleId) return;

        const reason   = document.getElementById('rejectReasonInput').value.trim();
        const feedback = document.getElementById('reschedFeedback');
        const btn      = document.getElementById('confirmRejectBtn');

        if (!reason || reason.length < 5) {
            showFeedback(feedback, 'Please enter a reason (min 5 characters).', 'error');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Rejecting…';

        try {
            const res = await fetch(`/reschedule-requests/${currentRescheduleId}/reject`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                },
                body: JSON.stringify({ rejection_reason: reason }),
            });
            const data = await res.json();

            if (!res.ok) {
                showFeedback(feedback, data.message || 'Something went wrong.', 'error');
                btn.disabled = false;
                btn.textContent = 'Confirm Rejection';
                return;
            }

            showFeedback(feedback, '✕ Rejected. Customer has been notified.', 'success');
            document.getElementById('reschedActions').classList.add('hidden');

            setTimeout(() => location.reload(), 1500);

        } catch (e) {
            showFeedback(feedback, 'Network error. Please try again.', 'error');
            btn.disabled = false;
            btn.textContent = 'Confirm Rejection';
        }
    }

    // =====================================================
    // HELPERS
    // =====================================================
    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content
            ?? document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]
            ?? '';
    }

    function showFeedback(el, message, type) {
        el.textContent = message;
        el.className = type === 'success'
            ? 'p-2.5 text-sm rounded-lg text-center font-medium bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-300'
            : 'p-2.5 text-sm rounded-lg text-center font-medium bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300';
        el.classList.remove('hidden');
    }

    function updateClock() {
        const now = new Date();
        const el  = document.getElementById('liveClock');
        if (el) el.textContent = now.toLocaleTimeString();
    }

    // =====================================================
    // INIT
    // =====================================================
    document.addEventListener('DOMContentLoaded', function () {
        updateClock();
        setInterval(updateClock, 1000);

        document.getElementById('appointmentModal').addEventListener('click', function(e) {
            if (e.target === this) closeAppointmentModal();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeAppointmentModal();
        });
    });
</script>
@endsection
