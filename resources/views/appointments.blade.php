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

    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Service Type</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Treatment</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Therapist</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Time Range</th>
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
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $booking->customer_name ?? 'Walk-in Customer' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $booking->customer_email ?? 'No email' }}</p>
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4">
                            <span class="text-sm font-medium text-gray-800 dark:text-white">
                                {{ $booking->service_type_label }}
                            </span>
                        </td>

                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $booking->treatment_label }}
                            </span>
                        </td>

                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $booking->therapist->name ?? 'Not Assigned' }}
                            </span>
                        </td>

                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $booking->appointment_date ? \Carbon\Carbon::parse($booking->appointment_date)->format('M d, Y') : 'No Date' }}
                            </span>
                        </td>

                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ \Carbon\Carbon::parse($booking->start_time)->format('h:i A') }}
                                -
                                {{ \Carbon\Carbon::parse($booking->end_time)->format('h:i A') }}
                            </span>
                        </td>

                        <td class="px-6 py-4">
                            <span class="px-3 py-1.5 text-xs font-medium rounded-full
                                {{ $booking->status == 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' :
                                ($booking->status == 'reserved' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300' :
                                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300') }}">
                                {{ ucfirst($booking->status) }}
                            </span>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <div class="flex justify-center gap-2">
                                <a href="{{ route('appointments.edit', $booking) }}"
                                class="px-3 py-1 text-sm text-white bg-yellow-500 rounded hover:bg-yellow-600">
                                    Edit
                                </a>

                                <!-- DELETE BUTTON -->
                                <button onclick="openDeleteModal({{ $booking->id }})"
                                    class="px-3 text-sm text-white bg-red-600 rounded hover:bg-red-700">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        {{-- <div style="display: none;">
            @foreach($bookings as $booking)
                Booking ID: {{ $booking->id }}<br>
                Customer: {{ $booking->customer_name }}<br>
                Therapist ID from DB: {{ $booking->therapist_id }}<br>
                Therapist relationship loaded: {{ $booking->relationLoaded('therapist') ? 'Yes' : 'No' }}<br>
                Therapist object: {{ $booking->therapist ? 'Exists' : 'NULL' }}<br>
                Therapist name via relationship: {{ $booking->therapist->name ?? 'NULL' }}<br>
                <hr>
            @endforeach
        </div> --}}

        <!-- DELETE CONFIRMATION MODAL -->
        <div id="deleteModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50">
            <div class="w-full max-w-md p-6 mx-auto mt-24 bg-white rounded-lg dark:bg-gray-800">
                <h2 class="mb-4 text-xl font-semibold text-gray-800 dark:text-white">
                    Confirm Delete
                </h2>

                <p class="text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete this appointment?
                </p>

                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeDeleteModal()"
                        class="px-4 py-2 text-gray-700 bg-gray-200 rounded">
                        Cancel
                    </button>

                    <form id="deleteForm" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="px-4 py-2 text-white bg-red-600 rounded">
                            Yes, Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>

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

    document.addEventListener('DOMContentLoaded', function () {
        updateClock();
        setInterval(updateClock, 1000);
    });

    function openEditModal(booking) {
    // Update to use correct field names
    document.getElementById('edit_customer_name').value = booking.customer_name || '';
    document.getElementById('edit_customer_email').value = booking.customer_email || '';
    document.getElementById('edit_service_type').value = booking.service_type || '';
    document.getElementById('edit_treatment').value = booking.treatment || '';

    // For therapist - use therapist_id from the booking
    document.getElementById('edit_therapist_id').value = booking.therapist_id || '';

    // For date and time - use the correct field names
    document.getElementById('edit_appointment_date').value = booking.appointment_date || '';
    document.getElementById('edit_start_time').value = booking.start_time || '';
    document.getElementById('edit_status').value = booking.status || 'reserved';

    // Update the form action
    document.getElementById('editForm').action = '/appointments/' + booking.id;
    document.getElementById('editModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    // Delete Modal Functions
    function openDeleteModal(id) {
        document.getElementById('deleteForm').action = '/appointments/' + id;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

</script>

@if (session('success'))
    <script>
        // Check if toast has already been shown
        if (!window.successToastShown) {
            window.successToastShown = true;

            document.addEventListener('DOMContentLoaded', function() {
                Toastify({
                    text: "{{ session('success') }}",
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#22c55e",
                    close: true
                }).showToast();
            });
        }
    </script>
@endif

@if ($errors->any())
    <script>
        // Check if error toast has already been shown
        if (!window.errorToastShown) {
            window.errorToastShown = true;

            document.addEventListener('DOMContentLoaded', function() {
                Toastify({
                    text: "{{ $errors->first() }}",
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#ef4444",
                    close: true
                }).showToast();
            });
        }
    </script>
@endif

@endsection
