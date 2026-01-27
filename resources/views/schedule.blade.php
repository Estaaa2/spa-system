@extends('layouts.app')

@section('content')
<!-- MAIN CONTENT - CALENDAR ONLY -->
<div class="min-h-screen p-6 bg-gray-50 dark:bg-gray-900">
    <!-- HEADER-->
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

    <!-- WEEK NAVIGATION CONTROLS - SIMPLIFIED -->
    <div class="flex items-center justify-center p-4 mb-6 bg-white border border-gray-200 rounded-lg dark:bg-gray-800 dark:border-gray-700">
        <div class="flex items-center gap-4">
            <button class="p-2 text-gray-600 rounded-lg hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                &lt; <!-- Previous Week -->
            </button>

            <div class="text-lg font-semibold text-gray-800 dark:text-white">
                @if(isset($startOfWeek))
                    {{ $startOfWeek->format('F Y') }}
                @else
                    {{ now()->format('F Y') }}
                @endif
            </div>

            <button class="p-2 text-gray-600 rounded-lg hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                &gt; <!-- Next Week -->
            </button>
        </div>
    </div>

    <!-- SCHEDULE CALENDAR -->
    <div class="overflow-hidden bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <!-- DAY HEADERS -->
        <div class="grid grid-cols-8 border-b border-gray-200 dark:border-gray-700">
            <div class="p-4 text-sm font-semibold text-gray-500 border-r border-gray-200 dark:text-gray-400 dark:border-gray-700">
                Time
            </div>
            @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)
                <div class="p-4 text-center border-r border-gray-200 dark:border-gray-700 last:border-r-0">
                    <div class="font-semibold text-gray-800 dark:text-white">{{ $day }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        @php
                            if(isset($startOfWeek)) {
                                $date = $startOfWeek->copy()->addDays(array_search($day, ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']));
                                echo $date->format('d');
                            } else {
                                $date = now()->startOfWeek()->addDays(array_search($day, ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']));
                                echo $date->format('d');
                            }
                        @endphp
                    </div>
                </div>
            @endforeach
        </div>

        <!-- TIME SLOTS -->
        @php
            $timeSlots = [
                '9:00 AM', '10:00 AM', '11:00 AM', '12:00 PM',
                '1:00 PM', '2:00 PM', '3:00 PM', '4:00 PM'
            ];
        @endphp

        @foreach($timeSlots as $timeSlot)
            <div class="grid grid-cols-8 border-b border-gray-200 dark:border-gray-700 last:border-b-0">
                <!-- TIME LABEL -->
                <div class="p-4 text-sm text-gray-500 border-r border-gray-200 dark:text-gray-400 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                    {{ $timeSlot }}
                </div>

                <!-- DAY COLUMNS -->
                @foreach(range(0, 6) as $day)
                    <div class="p-4 border-r border-gray-200 dark:border-gray-700 last:border-r-0 hover:bg-gray-50 dark:hover:bg-gray-700/30 cursor-pointer min-h-[80px]">
                        <!-- Click to add appointment -->
                        <div class="text-sm text-center text-gray-400 transition-opacity opacity-0 dark:text-gray-500 hover:opacity-100">
                            Click to add
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>

<!-- CLOCK SCRIPT -->
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

    document.addEventListener('DOMContentLoaded', function () {
        updateClock();
        setInterval(updateClock, 1000);

        // Add click handlers to schedule cells
        document.querySelectorAll('div[class*="cursor-pointer"]').forEach(cell => {
            cell.addEventListener('click', function() {
                alert('Add appointment for this time slot');
            });
        });
    });
</script>
@endsection
