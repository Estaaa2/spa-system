@extends('layouts.app')

@section('content')
<div class="min-h-screen p-6 bg-gray-50 dark:bg-gray-900">
    <!-- HEADER -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">Schedule</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage Weekly Appointments</p>
        </div>

        <div class="flex items-center gap-4">
            <div class="flex items-center gap-3 px-4 py-2 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500 dark:text-gray-400">Today</span>
                    <span id="todayDate" class="text-sm font-medium text-gray-800 dark:text-white"></span>
                </div>

                <div class="h-6 border-l border-gray-200 dark:border-gray-700"></div>

                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500 dark:text-gray-400">Time</span>
                    <span id="realTimeClock" class="text-sm font-medium text-gray-800 dark:text-white"></span>
                </div>
            </div>
        </div>
    </div>

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

        // Convert 24h slot keys to display labels
        $timeSlots = array_map(function($t){
            return \Carbon\Carbon::createFromFormat('H:i', $t)->format('g:i A');
        }, $timeSlotKeys);
    @endphp

    <!-- CALENDAR -->
    <div class="overflow-hidden bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <!-- HEADERS -->
        <div class="grid grid-cols-8 border-b border-gray-200 dark:border-gray-700">
            <div class="p-4 text-sm font-semibold text-gray-500 border-r border-gray-200 dark:text-gray-400 dark:border-gray-700">
                Time
            </div>

            @foreach($dayDates as $i => $date)
                <div class="p-4 text-center border-r border-gray-200 dark:border-gray-700 last:border-r-0">
                    <div class="font-semibold text-gray-800 dark:text-white">{{ $days[$i] }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $date->format('d') }}</div>
                </div>
            @endforeach
        </div>

        <!-- TIME ROWS -->
        @foreach($timeSlotKeys as $slotIndex => $timeKey)
            <div class="grid grid-cols-8 border-b border-gray-200 dark:border-gray-700 last:border-b-0">
                <!-- TIME LABEL -->
                <div class="p-4 text-sm text-gray-500 border-r border-gray-200 dark:text-gray-400 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                    {{ $timeSlots[$slotIndex] }}
                </div>

                <!-- DAY CELLS -->
                @foreach($dayDates as $date)
                    @php
                        $dateKey = $date->toDateString();
                        $cellBookings = $grid[$dateKey][$timeKey] ?? [];
                        $cellId = 'cell-' . $dateKey . '-' . str_replace(':', '', $timeKey);
                    @endphp

                    <div class="p-3 border-r border-gray-200 dark:border-gray-700 last:border-r-0 hover:bg-gray-50 dark:hover:bg-gray-700/30 min-h-[84px]">
                        <div class="space-y-2" id="{{ $cellId }}">
                            @if(count($cellBookings))
                                @foreach($cellBookings as $b)
                                    @php
                                        $badge = match($b->status) {
                                            'reserved' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                            'confirmed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                            'completed' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                            'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                    @endphp

                                    <div
                                        class="p-2 border border-gray-200 rounded-lg cursor-pointer dark:border-gray-700 bg-white/70 dark:bg-gray-900/30"
                                        onclick="openAppointmentModal(this)"
                                        data-customer="{{ $b->customer_name ?? 'Walk-in' }}"
                                        data-service="{{ ucfirst($b->service_type) }}"
                                        data-treatment="{{ $b->treatment }}"
                                        data-date="{{ \Carbon\Carbon::parse($b->appointment_date)->format('F d, Y') }}"
                                        data-time="{{ \Carbon\Carbon::parse($b->appointment_time)->format('h:i A') }}"
                                        data-status="{{ ucfirst($b->status) }}"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="text-sm font-semibold text-gray-800 truncate dark:text-white">
                                                {{ $b->customer_name ?? 'Walk-in' }}
                                            </div>
                                            <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $badge }}">
                                                {{ ucfirst($b->status) }}
                                            </span>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-600 truncate dark:text-gray-300">
                                            {{ ucfirst($b->service_type) }} • {{ $b->treatment }}
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <!-- Empty slot -->
                                <button
                                    type="button"
                                    class="w-full h-full text-sm text-center text-gray-400 transition-opacity opacity-0 dark:text-gray-500 hover:opacity-100"
                                    onclick="event.stopPropagation(); alert('Click a slot to create booking: {{ $dateKey }} {{ $timeKey }}');"
                                >
                                    Click to add
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    <!-- VIEW APPOINTMENT MODAL -->
    <div id="appointmentModal" class="fixed inset-0 z-50 items-center justify-center hidden bg-black/50">
        <div class="w-full max-w-md bg-white rounded-lg shadow-xl dark:bg-gray-800">
            <div class="flex items-center justify-between px-5 py-4 border-b dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Appointment Details</h3>
                <button onclick="closeAppointmentModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    ✕
                </button>
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
                    <span class="text-gray-500 dark:text-gray-400">Date & Time</span>
                    <p id="modalDateTime" class="font-medium text-gray-800 dark:text-white"></p>
                </div>

                <div>
                    <span class="text-gray-500 dark:text-gray-400">Status</span>
                    <span id="modalStatus" class="inline-block px-3 py-1 text-xs font-medium rounded-full"></span>
                </div>
            </div>

            <div class="flex justify-end px-5 py-4 border-t dark:border-gray-700">
                <button onclick="closeAppointmentModal()"
                        class="px-4 py-2 text-sm text-white bg-[#8B7355] rounded hover:bg-[#7A6348]">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CLOCK + MODAL SCRIPT -->
<script>
    function updateClock() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };

        const todayDateElement = document.getElementById('todayDate');
        const realTimeClockElement = document.getElementById('realTimeClock');

        if (todayDateElement) {
            todayDateElement.innerText = now.toLocaleDateString('en-US', options);
        }

        if (realTimeClockElement) {
            realTimeClockElement.innerText = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        }
    }

    function openAppointmentModal(el) {
        document.getElementById('modalCustomer').innerText = el.dataset.customer || '';
        document.getElementById('modalService').innerText = el.dataset.service || '';
        document.getElementById('modalTreatment').innerText = el.dataset.treatment || '';
        document.getElementById('modalDateTime').innerText = `${el.dataset.date} at ${el.dataset.time}`;

        const statusEl = document.getElementById('modalStatus');
        statusEl.innerText = el.dataset.status || '';

        // status color
        const s = (el.dataset.status || '').toLowerCase();
        let cls = 'bg-gray-100 text-gray-800';
        if (s === 'reserved') cls = 'bg-yellow-100 text-yellow-800';
        if (s === 'confirmed') cls = 'bg-blue-100 text-blue-800';
        if (s === 'completed') cls = 'bg-gray-200 text-gray-800';
        if (s === 'cancelled') cls = 'bg-red-100 text-red-800';

        statusEl.className = `inline-block px-3 py-1 text-xs font-medium rounded-full ${cls}`;

        const modal = document.getElementById('appointmentModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeAppointmentModal() {
        const modal = document.getElementById('appointmentModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.addEventListener('DOMContentLoaded', function () {
        updateClock();
        setInterval(updateClock, 1000);

        // Close modal on backdrop click
        document.getElementById('appointmentModal').addEventListener('click', function(e) {
            if (e.target === this) closeAppointmentModal();
        });

        // Close modal on ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeAppointmentModal();
        });
    });
</script>
@endsection
