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

        <!-- Today's Appointment -->
        <div class="p-6 bg-white border rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs tracking-widest text-gray-500 dark:text-gray-400">TODAY</p>
            <p class="text-lg font-semibold text-gray-800 dark:text-white">APPOINTMENTS</p>
            <p class="mt-2 text-3xl font-bold text-gray-800 dark:text-white">{{ $todayCount }}</p>
        </div>

        <!-- Pending Appointment -->
        <div class="p-6 rounded-lg shadow-lg bg-gradient-to-r from-[#8B7355] to-[#6F5430] text-white">
            <p class="text-xs tracking-widest opacity-80">PENDING</p>
            <p class="text-lg font-semibold">APPOINTMENTS</p>
            <p class="mt-2 text-3xl font-bold">{{ $pending }}</p>
        </div>

        <!-- Revenue Today -->
        <div class="p-6 bg-white border rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs tracking-widest text-gray-500 dark:text-gray-400">REVENUE</p>
            <p class="text-lg font-semibold text-gray-800 dark:text-white">TODAY</p>
            <p class="mt-2 text-3xl font-bold text-gray-800 dark:text-white">₱0.00</p>
        </div>

        <!-- Top Service Today -->
        <div class="p-6 rounded-lg shadow-lg bg-gradient-to-r from-[#8B7355] to-[#6F5430] text-white">
            <p class="text-xs tracking-widest opacity-80">TOP SERVICE</p>
            <p class="text-lg font-semibold">TODAY</p>
            <p class="mt-2 text-3xl font-bold">
                {{ $topServiceToday ? $topServiceToday->service_type . ' (' . $topServiceToday->count . ')' : 'N/A' }}
            </p>
        </div>
    </div>

    <!-- TABLE 1: Therapist Available -->
    <div class="mt-10">
        <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Therapist Availability</h2>
                <span class="text-sm text-gray-500 dark:text-gray-400">Today: {{ now()->format('F d, Y') }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Therapist Name</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Today's Appointments</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Availability</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @php
                            // Only show therapists that are "Available" (appointmentCount < 6)
                            $availableTherapists = $therapists->filter(function ($t) {
                                return ($t->assigned_bookings_count ?? 0) < 6;
                            });
                        @endphp

                        @forelse($availableTherapists->filter(fn($t) => !empty($t->name)) as $therapist)
                            @php
                                $appointmentCount = $therapist->assigned_bookings_count ?? 0;
                                $availability = 'Available';
                                $statusColor = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
                            @endphp

                            <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-[#8B7355] flex items-center justify-center text-white">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">{{ $therapist->name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $therapist->email }}</p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <span class="px-3 py-1.5 text-xs font-medium rounded-full {{ $statusColor }}">
                                        {{ $availability }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">
                                        {{ $appointmentCount }} appointments
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    @php
                                        $capacity = 8; // max appointments per therapist per day
                                        $bookedPct = (int) round(min((($appointmentCount / $capacity) * 100), 100));
                                        $availablePct = 100 - $bookedPct;
                                    @endphp

                                    <div class="flex items-center gap-3">
                                        <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                            <div
                                                class="h-2.5 rounded-full
                                                    {{ $appointmentCount >= 8
                                                        ? 'bg-red-600'
                                                        : ($appointmentCount >= 6
                                                            ? 'bg-yellow-500'
                                                            : 'bg-green-600') }}"
                                                style="width: {{ $availablePct }}%">
                                            </div>
                                        </div>

                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $availablePct }}%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="mb-2 text-3xl text-gray-400 fas fa-user-md"></i>
                                        <p>No therapists available today</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TABLE 2: Today's Appointments -->
    <div class="mt-10">
        <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Today's Appointments</h2>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $todayAppointments->count() }} appointment(s) for today
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Time</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Customer</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Service</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Therapist</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @forelse($todayAppointments as $appointment)
                            @php
                                $statusColors = [
                                    'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                    'reserved' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                    'confirmed' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                    'completed' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                    'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                                ];
                                $statusColor = $statusColors[$appointment->status] ?? 'bg-gray-100 text-gray-800';
                            @endphp
                            <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="px-6 py-4">
                                    <span class="text-sm font-medium text-gray-800 dark:text-white">
                                        {{ \Carbon\Carbon::parse($appointment->start_time)->format('h:i A') }}
                                         - 
                                        {{ \Carbon\Carbon::parse($appointment->end_time)->format('h:i A') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-[#8B7355] flex items-center justify-center text-white">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">{{ $appointment->customer_name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $appointment->customer_phone }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <span class="text-sm font-medium text-gray-800 dark:text-white">{{ ucfirst($appointment->service_type) }}</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $appointment->treatment }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">
                                        {{ $appointment->therapist->name ?? 'Not Assigned' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1.5 text-xs font-medium rounded-full {{ $statusColor }}">
                                        {{ ucfirst($appointment->status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="mb-2 text-3xl text-gray-400 fas fa-calendar-check"></i>
                                        <p>No appointments scheduled for today</p>
                                        <a href="{{ route('booking') }}"
                                           class="mt-2 px-4 py-2 text-sm text-white bg-[#8B7355] rounded hover:bg-[#7A6348]">
                                            Book an Appointment
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($todayAppointments->count() > 0)
                <div class="px-4 py-3 bg-white border-t border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Showing {{ $todayAppointments->count() }} of {{ $todayAppointments->count() }} appointments
                        </p>
                        <a href="{{ route('appointments.index') }}"
                           class="text-sm text-[#8B7355] hover:text-[#7A6348] dark:text-[#A08565] dark:hover:text-[#8B7355]">
                            View all appointments →
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- ALERTS / NOTIFICATIONS -->
    <div class="mt-10">
        <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">Alerts / Notifications</h2>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <!-- Late Appointments -->
            <div class="p-6 rounded-lg shadow-lg bg-gradient-to-r from-[#8B7355] to-[#6F5430] text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs tracking-widest opacity-80">LATE</p>
                        <p class="text-lg font-semibold">APPOINTMENTS</p>
                        <p class="mt-2 text-3xl font-bold">{{ $lateAppointments }}</p>
                    </div>
                    @if($lateAppointments > 0)
                        <span class="flex items-center justify-center w-8 h-8 bg-white rounded-full">
                            <i class="text-[#8B7355] fas fa-exclamation-triangle"></i>
                        </span>
                    @endif
                </div>
                @if($lateAppointments > 0)
                    <p class="mt-2 text-sm opacity-80">
                        {{ $lateAppointments }} appointment(s) running late today
                    </p>
                @endif
            </div>

            <!-- No Shows -->
            <div class="p-6 bg-white border rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs tracking-widest text-gray-500 dark:text-gray-400">NO</p>
                        <p class="text-lg font-semibold text-gray-800 dark:text-white">SHOWS</p>
                        <p class="mt-2 text-3xl font-bold text-gray-800 dark:text-white">{{ $noShows }}</p>
                    </div>
                    @if($noShows > 0)
                        <span class="flex items-center justify-center w-8 h-8 bg-red-100 rounded-full dark:bg-red-900">
                            <i class="text-red-600 fas fa-user-times dark:text-red-300"></i>
                        </span>
                    @endif
                </div>
                @if($noShows > 0)
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ $noShows }} customer(s) didn't show up
                    </p>
                @endif
            </div>

            <!-- Overbooked Slots -->
            <div class="p-6 rounded-lg shadow-lg bg-gradient-to-r from-[#8B7355] to-[#6F5430] text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs tracking-widest opacity-80">OVERBOOKED</p>
                        <p class="text-lg font-semibold">SLOTS</p>
                        <p class="mt-2 text-3xl font-bold">{{ $overbookedSlots }}</p>
                    </div>
                    @if($overbookedSlots > 0)
                        <span class="flex items-center justify-center w-8 h-8 bg-white rounded-full">
                            <i class="text-[#8B7355] fas fa-calendar-times"></i>
                        </span>
                    @endif
                </div>
                @if($overbookedSlots > 0)
                    <p class="mt-2 text-sm opacity-80">
                        {{ $overbookedSlots }} therapist(s) overbooked
                    </p>
                @endif
            </div>
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
