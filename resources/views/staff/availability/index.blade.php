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

    <!-- MAIN CARD -->
    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">

        <div class="flex gap-6">
            <!-- LEFT STAFF LIST -->
            <div class="w-1/3">
                <input type="text" placeholder="Search Staff" class="w-full p-2 mb-3 border rounded">

                @foreach($staff as $member)
                    @if($member->user) {{-- Check if user exists --}}
                        <div class="p-3 mb-2 border rounded">
                            <div class="font-semibold">{{ $member->user->name }}</div>
                            <div class="text-xs text-gray-500">
                                {{-- Get first role name --}}
                                @if($member->user->roles->isNotEmpty())
                                    {{ $member->user->roles->first()->name }}
                                @else
                                    No role
                                @endif

                                {{-- Display branch name --}}
                                @if($member->branch)
                                    - {{ $member->branch->name }}
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            <!-- RIGHT SCHEDULE GRID -->
            <div class="w-2/3">
                <div class="flex justify-between mb-4">
                    <div class="font-semibold">
                        {{ $startOfWeek->format('F d, Y') }} - {{ $startOfWeek->copy()->endOfWeek()->format('F d, Y') }}
                    </div>
                    <button class="px-4 py-2 text-white bg-blue-500 rounded">+ Set Availability</button>
                </div>

                <div class="grid grid-cols-7 gap-2">
                    @foreach(range(0,6) as $day)
                        <div class="font-semibold text-center">
                            {{ $startOfWeek->copy()->addDays($day)->format('D') }}
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    <div class="grid grid-cols-7 gap-2">
                        @foreach(range(0,6) as $day)
                            <div class="h-64 border rounded"></div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

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
    });
</script>

@endsection
