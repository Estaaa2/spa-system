@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl">

    <!-- HEADER -->
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">Staff Availability</h1>

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

    <!-- WEEK NAV -->
    <div class="flex items-center justify-center p-4 mb-6 bg-white border border-gray-200 rounded-lg dark:bg-gray-800 dark:border-gray-700">
        <div class="flex items-center gap-4">
            <a href="{{ route('staff.availability', ['week' => $startOfWeek->copy()->subWeek()->format('Y-m-d'), 'staff_id' => $selectedStaffId ?? '']) }}"
               class="p-2 text-gray-600 rounded-lg hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                &lt;
            </a>

            <div class="text-lg font-semibold text-gray-800 dark:text-white">
                {{ $startOfWeek->format('F Y') }}
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                    ({{ $startOfWeek->format('M d') }} - {{ $startOfWeek->copy()->endOfWeek()->format('M d') }})
                </span>
            </div>

            <a href="{{ route('staff.availability', ['week' => $startOfWeek->copy()->addWeek()->format('Y-m-d'), 'staff_id' => $selectedStaffId ?? '']) }}"
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
    @endphp

    <!-- STAFF AVAILABILITY TABLE -->
    <div class="overflow-auto bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Staff</th>
                    @foreach($weekDays as $day)
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                            {{ $day->format('D, M d') }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                @foreach($staff as $s)
                <tr>
                    <td class="px-6 py-4 text-gray-800 dark:text-white font-medium">{{ $s->name }}</td>

                    @foreach($weekDays as $day)
                        @php
                            $availability = $availabilities[$s->id][$day->format('Y-m-d')] ?? null;
                            $status = $availability->status ?? 'available';
                            $startTime = optional($availability)->start_time;
                            $endTime = optional($availability)->end_time;
                            $opening = $branchOperatingHours[$s->id][$day->format('N')]['opening'];
                            $closing = $branchOperatingHours[$s->id][$day->format('N')]['closing'];
                            $closed = $branchOperatingHours[$s->id][$day->format('N')]['closed'];
                        @endphp
                        <td class="px-2 py-1 text-center">
                            @if($closed)
                                <span class="px-3 py-1 text-xs font-semibold text-gray-500 bg-gray-200 rounded-full dark:bg-gray-700 dark:text-gray-300">
                                    Spa Closed
                                </span>
                            @else
                                <button 
                                    onclick="openAvailabilityModal({{ $s->id }}, '{{ $s->name }}', '{{ $day->format('Y-m-d') }}', '{{ $status }}', '{{ $startTime }}', '{{ $endTime }}', '{{ $opening }}', '{{ $closing }}')"
                                    class="px-2 py-1 rounded-full text-sm font-medium
                                        @if($status == 'available') dark:bg-green-900 dark:text-green-200 bg-green-100 text-green-800
                                        @elseif($status == 'partial') dark:bg-yellow-900 dark:text-yellow-200 bg-yellow-100 text-yellow-800
                                        @elseif($status == 'unavailable') dark:bg-red-900 dark:text-red-200 bg-red-100 text-red-800
                                        @endif
                                        hover:opacity-80">
                                    {{ ucfirst($status) }}
                                </button>
                            @endif
                        </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>


    <!-- MODAL -->
    <div id="availabilityModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75"></div>

            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl dark:bg-gray-800 sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form id="availabilityForm" method="POST" action="{{ route('staff.availability.store') }}">
                    @csrf
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 id="modalTitle" class="text-lg font-medium text-gray-900 dark:text-white">
                                Set Availability
                            </h3>
                            <button type="button" onclick="closeAvailabilityModal()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                                <i class="text-xl fa-solid fa-times"></i>
                            </button>
                        </div>

                        <p id="modalStaffDate" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Staff: <span id="modalStaffName"></span> | Date: <span id="modalDate"></span>
                        </p>
                        
                        <div class="mt-5 space-y-3">
                            <div>
                                <input type="hidden" name="user_id" id="modalUserId">
                                <input type="hidden" name="date" id="modalDateValue">
                                <input type="radio" name="status" value="available" id="statusAvailable" checked>
                                <label for="statusAvailable" class="ml-2 text-gray-700 dark:text-gray-300">Fully Available</label>
                            </div>
                            <div>
                                <input type="radio" name="status" value="partial" id="statusPartial">
                                <label for="statusPartial" class="ml-2 text-gray-700 dark:text-gray-300">Partially Available</label>
                            </div>
                            <div>
                                <input type="radio" name="status" value="unavailable" id="statusUnavailable">
                                <label for="statusUnavailable" class="ml-2 text-gray-700 dark:text-gray-300">On Leave</label>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-700 dark:text-gray-300">Start Time</label>
                                <input type="time" name="start_time" id="startTime"
                                    class="block w-full mt-1 border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-700 dark:text-gray-300">End Time</label>
                                <input type="time" name="end_time" id="endTime"
                                    class="block w-full mt-1 border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 flex justify-end gap-3">
                        <button type="button" onclick="closeAvailabilityModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border rounded-lg hover:bg-gray-50 dark:bg-gray-600 dark:border-gray-500 dark:text-white dark:hover:bg-gray-500">Cancel</button>
                        <button type="submit" id="availabilitySubmitBtn" class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg focus:ring-4 focus:ring-blue-300">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<script>
    function openAvailabilityModal(userId, staffName, date, status, startTime, endTime, openingTime, closingTime) {

        if (!openingTime || !closingTime) {
            alert("Spa is closed on this day.");
            return;
        }

        const form = document.getElementById('availabilityForm');
        
        document.getElementById('modalStaffName').textContent = staffName;
        document.getElementById('modalDate').textContent = date;

        document.getElementById('modalUserId').value = userId;
        document.getElementById('modalDateValue').value = date;

        // Fill radio buttons
        document.getElementById('statusAvailable').checked = status === 'available';
        document.getElementById('statusPartial').checked = status === 'partial';
        document.getElementById('statusUnavailable').checked = status === 'unavailable';

        // Store operating hours for convenience
        form.dataset.openingTime = openingTime;
        form.dataset.closingTime = closingTime;

        // Fill start/end inputs based on status
        function applyStatus(currentStatus){
            const startInput = document.getElementById('startTime');
            const endInput = document.getElementById('endTime');
            const opening = form.dataset.openingTime;
            const closing = form.dataset.closingTime;

            if(currentStatus === 'available'){
                startInput.value = opening;
                endInput.value = closing;
                startInput.disabled = true;
                endInput.disabled = true;
            } else if(currentStatus === 'partial'){
                startInput.value = startTime || opening;
                endInput.value = endTime || closing;
                startInput.disabled = false;
                endInput.disabled = false;
            } else if(currentStatus === 'unavailable'){
                startInput.value = '';
                endInput.value = '';
                startInput.disabled = true;
                endInput.disabled = true;
            }
        }

        // Initial fill
        applyStatus(status);

        // Remove old listeners and add fresh ones
        form.querySelectorAll('input[name="status"]').forEach(radio => {
            radio.onchange = e => applyStatus(e.target.value);
        });

        document.getElementById('availabilityModal').classList.remove('hidden');
    }

    function closeAvailabilityModal(){
        document.getElementById('availabilityModal').classList.add('hidden');
    }

    function toggleTimeInputs(status){
        const start = document.getElementById('startTime');
        const end = document.getElementById('endTime');
        if(status === 'unavailable'){
            start.disabled = true;
            end.disabled = true;
        } else {
            start.disabled = false;
            end.disabled = false;
        }
    }

    // Function to update both the date and time displays.
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

    document.addEventListener('DOMContentLoaded', function () {
        updateClock();
        setInterval(updateClock, 1000);
    });
</script>

@endsection
