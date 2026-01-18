@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl">

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">Appointments</h1>

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

    <div class="overflow-hidden bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Service</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Treatment</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Therapist</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Time</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-xs font-medium text-center text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>

            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                @foreach ($bookings as $booking)
                    <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-[#8B7355] flex items-center justify-center text-white">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $booking->fullname }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $booking->email }}</p>
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4">
                            <span class="text-sm font-medium text-gray-800 dark:text-white">{{ ucfirst($booking->service_type) }}</span>
                        </td>

                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ ucfirst($booking->treatment) }}</span>
                        </td>

                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $booking->therapist }}</span>
                        </td>

                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $booking->date }}</span>
                        </td>

                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $booking->time }}</span>
                        </td>

                        <td class="px-6 py-4">
                            <span class="px-3 py-1.5 text-xs font-medium rounded-full
                                {{ $booking->status == 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' :
                                   'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' }}">
                                {{ ucfirst($booking->status) }}
                            </span>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <div class="flex justify-center gap-2">
                                <form action="{{ route('appointments.reserve', $booking->id) }}" method="POST">
                                    @csrf
                                    <button
                                        onclick="openEditModal(@json($booking))"
                                        class="px-4 py-2 text-white bg-yellow-500 rounded hover:bg-yellow-600">
                                        Edit
                                    </button>
                                </form>

                                <button onclick="showDetailsModal({{ $booking->id }})" class="px-4 py-2 text-white bg-blue-600 rounded hover:bg-blue-700">
                                    Details
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- PAGINATION -->
        <div class="px-4 py-3 bg-white border-t border-gray-200 dark:bg-gray-800 dark:border-gray-700">
            {{ $bookings->links() }}
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

    document.addEventListener('DOMContentLoaded', function() {
        updateClock();
        setInterval(updateClock, 1000);
    });
</script>

@endsection
