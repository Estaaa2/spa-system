@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl">

    <!-- Dashboard Header -->
    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
    <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">
        Dashboard
    </h1>

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


    <!-- Cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">

        <!-- Total Appointment -->
        <div class="p-6 bg-white border rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs tracking-widest text-gray-500 dark:text-gray-400">TOTAL</p>
            <p class="text-lg font-semibold text-gray-800 dark:text-white">APPOINTMENTS</p>
            <p class="mt-2 text-3xl font-bold text-gray-800 dark:text-white">{{ $total }}</p>
        </div>

        <!-- Completed Appointment -->
        <div class="p-6 rounded-lg shadow-lg bg-gradient-to-r from-[#8B7355] to-[#6F5430] text-white">
            <p class="text-xs tracking-widest opacity-80">COMPLETED</p>
            <p class="text-lg font-semibold">APPOINTMENTS</p>
            <p class="mt-2 text-3xl font-bold">{{ $completed }}</p>
        </div>

        <!-- Pending Appointment -->
        <div class="p-6 bg-white border rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs tracking-widest text-gray-500 dark:text-gray-400">PENDING</p>
            <p class="text-lg font-semibold text-gray-800 dark:text-white">APPOINTMENTS</p>
            <p class="mt-2 text-3xl font-bold text-gray-800 dark:text-white">{{ $pending }}</p>
        </div>

        <!-- Revenue Today -->
        <div class="p-6 bg-white border rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs tracking-widest text-gray-500 dark:text-gray-400">REVENUE</p>
            <p class="text-lg font-semibold text-gray-800 dark:text-white">TODAY</p>
            <p class="mt-2 text-3xl font-bold text-gray-800 dark:text-white">$0.00</p>
        </div>

        <!-- Top Service Today -->
        <div class="p-6 bg-white border rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs tracking-widest text-gray-500 dark:text-gray-400">TOP SERVICE</p>
            <p class="text-lg font-semibold text-gray-800 dark:text-white">TODAY</p>
            <p class="mt-2 text-3xl font-bold text-gray-800 dark:text-white">N/A</p>
        </div>

    </div>

    <!-- TABLE 1 -->
    <div class="mt-10">
        <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">Therapist Available</h2>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                <thead>
                    <tr class="text-left">
                        <th class="px-4 py-3 border-b dark:border-gray-700">Therapist Name</th>
                        <th class="px-4 py-3 border-b dark:border-gray-700">Status</th>
                        <th class="px-4 py-3 border-b dark:border-gray-700">Today's App.</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-3 border-b dark:border-gray-700">Sarah Rebate</td>
                        <td class="px-4 py-3 border-b dark:border-gray-700">Active</td>
                        <td class="px-4 py-3 border-b dark:border-gray-700">2</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TABLE 2 -->
    <div class="mt-10">
        <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">Today's Appointment</h2>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                <thead>
                    <tr class="text-left">
                        <th class="px-4 py-3 border-b dark:border-gray-700">Time</th>
                        <th class="px-4 py-3 border-b dark:border-gray-700">Customer</th>
                        <th class="px-4 py-3 border-b dark:border-gray-700">Service</th>
                        <th class="px-4 py-3 border-b dark:border-gray-700">Therapist</th>
                        <th class="px-4 py-3 border-b dark:border-gray-700">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-3 border-b dark:border-gray-700">08:00 AM</td>
                        <td class="px-4 py-3 border-b dark:border-gray-700">Cedie Heyrosa</td>
                        <td class="px-4 py-3 border-b dark:border-gray-700">Massage</td>
                        <td class="px-4 py-3 border-b dark:border-gray-700">Sarah Rebate</td>
                        <td class="px-4 py-3 border-b dark:border-gray-700">Pending</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ALERTS / NOTIFICATIONS -->
    <div class="mt-10">
        <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">Alerts / Notifications</h2>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

            <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
                <p class="text-sm font-semibold text-center text-gray-700 dark:text-gray-200">Late Appointment</p>
            </div>

            <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
                <p class="text-sm font-semibold text-center text-gray-700 dark:text-gray-200">No-Shows</p>
            </div>

            <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
                <p class="text-sm font-semibold text-center text-gray-700 dark:text-gray-200">Overbooked Slots</p>
            </div>
        </div>
    </div>

</div>
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

    // Initialize and start the clock
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize clock immediately
        updateClock();

        // Update clock every second
        setInterval(updateClock, 1000);
    });
</script>
@endsection
