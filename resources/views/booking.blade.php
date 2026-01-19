@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl">

    <!-- Booking Header -->
    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">
            Client's Appointment
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

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- Booking Form -->
        <div class="lg:col-span-2">
            <div class="h-full p-6 bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
                <div class="mb-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Appointment Details</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Fill in all required information to schedule an appointment</p>
                        </div>
                    </div>
                </div>

                <form action="{{ route('bookings.store') }}" method="POST">
                    @csrf

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <!-- Service Type -->
                        <div>
                            <label for="service_type" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Service Type</label>
                            <select class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent" id="service_type" name="service_type">
                                <option selected disabled>Select Service Type</option>
                                <option value="Hair">Hair Service</option>
                                <option value="Spa">Spa Treatment</option>
                                <option value="Massage">Massage</option>
                                <option value="Nails">Nails</option>
                            </select>
                        </div>

                        <!-- Treatment -->
                        <div>
                            <label for="treatment" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Select Treatment</label>
                            <select class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent" id="treatment" name="treatment">
                                <option selected disabled>Select Treatment</option>
                                <option value="Haircut">Haircut</option>
                                <option value="Facial">Facial</option>
                                <option value="Body Massage">Body Massage</option>
                                <option value="Nail Care">Nail Care</option>
                            </select>
                        </div>

                        <!-- Therapist -->
                        <div>
                            <label for="therapist" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Therapist</label>
                            <select class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent" id="therapist" name="therapist">
                                <option selected disabled>Select Therapist</option>
                                <option value="Cedie Heyrosa">Cedie Heyrosa</option>
                                <option value="Marjo Catibod">Marjo Catibod</option>
                                <option value="Piolo Lingo">Piolo Lingo</option>
                            </select>
                        </div>

                        <!-- Phone Number -->
                        <div>
                            <label for="phone" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Phone Number</label>
                            <input type="tel" class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent" id="phone" name="phone" placeholder="Enter phone number">
                        </div>
                    </div>

                    <!-- Full Name -->
                    <div class="mt-4">
                        <label for="fullname" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Full Name</label>
                        <input type="text" class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent" id="fullname" name="fullname" placeholder="Enter full name">
                    </div>

                    <!-- Address -->
                    <div class="mt-4">
                        <label for="address" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Address</label>
                        <input type="text" class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent" id="address" name="address" placeholder="Enter address">
                    </div>

                    <!-- Email -->
                    <div class="mt-4">
                        <label for="email" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Email</label>
                        <input type="email" class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent" id="email" name="email" placeholder="Enter email">
                    </div>

                    <!-- Date & Time -->
                    <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2">
                        <div>
                            <label for="date" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Calendar</label>
                            <input type="date" class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent" name="date" id="date">
                        </div>

                        <div>
                            <label for="time" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Time</label>
                            <select class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent" name="time" id="time">
                                <option selected disabled>Select Time</option>
                                <option value="09:00">09:00 AM</option>
                                <option value="10:00">10:00 AM</option>
                                <option value="11:00">11:00 AM</option>
                                <option value="14:00">02:00 PM</option>
                                <option value="15:00">03:00 PM</option>
                                <option value="16:00">04:00 PM</option>
                            </select>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="mt-8">
                        <button type="submit" class="w-full px-6 py-3 font-semibold text-white transition-all duration-200 bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg hover:opacity-90 focus:ring-4 focus:ring-[#8B7355]/50">
                            RESERVE BOOKING
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Appointment Summary -->
        <div>
            <div class="h-full p-6 bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Appointment Summary</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Review your booking details</p>
                </div>

                <div class="space-y-4">
                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Service Type</p>
                        <p id="summary-service" class="text-lg font-semibold text-gray-800 dark:text-white">-</p>
                    </div>

                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Treatment</p>
                        <p id="summary-treatment" class="text-lg font-semibold text-gray-800 dark:text-white">-</p>
                    </div>

                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Therapist</p>
                        <p id="summary-therapist" class="text-lg font-semibold text-gray-800 dark:text-white">-</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Date</p>
                            <p id="summary-date" class="text-lg font-semibold text-gray-800 dark:text-white">-</p>
                        </div>

                        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Time</p>
                            <p id="summary-time" class="text-lg font-semibold text-gray-800 dark:text-white">-</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="pt-6 mt-8 border-t border-gray-200 dark:border-gray-700">
                    <h3 class="mb-4 text-sm font-semibold text-gray-500 dark:text-gray-400">Booking Status</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-300">Available Slots</span>
                            <span class="text-sm font-semibold text-gray-800 dark:text-white">12</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-300">Today's Bookings</span>
                            <span class="text-sm font-semibold text-gray-800 dark:text-white">8</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-300">Waiting Time</span>
                            <span class="text-sm font-semibold text-gray-800 dark:text-white">15 min</span>
                        </div>
                    </div>
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

    // Set minimum date to today and update summary
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        const dateInput = document.getElementById('date');
        if (dateInput) {
            dateInput.min = today;
        }

        // Update summary function
        function updateSummary() {
            const serviceType = document.getElementById('service_type');
            const treatment = document.getElementById('treatment');
            const therapist = document.getElementById('therapist');
            const dateInput = document.getElementById('date');
            const timeSelect = document.getElementById('time');

            if (serviceType && serviceType.value) {
                document.getElementById('summary-service').textContent = serviceType.options[serviceType.selectedIndex].text;
            }

            if (treatment && treatment.value) {
                document.getElementById('summary-treatment').textContent = treatment.options[treatment.selectedIndex].text;
            }

            if (therapist && therapist.value) {
                document.getElementById('summary-therapist').textContent = therapist.options[therapist.selectedIndex].text;
            }

            if (dateInput && dateInput.value) {
                const date = new Date(dateInput.value);
                document.getElementById('summary-date').textContent = date.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });
            }

            if (timeSelect && timeSelect.value) {
                const time = timeSelect.value;
                const [hours, minutes] = time.split(':');
                const ampm = hours >= 12 ? 'PM' : 'AM';
                const hour12 = hours % 12 || 12;
                document.getElementById('summary-time').textContent = `${hour12}:${minutes} ${ampm}`;
            }
        }

        // Add event listeners
        const serviceType = document.getElementById('service_type');
        const treatment = document.getElementById('treatment');
        const therapist = document.getElementById('therapist');
        const timeSelect = document.getElementById('time');

        if (serviceType) serviceType.addEventListener('change', updateSummary);
        if (treatment) treatment.addEventListener('change', updateSummary);
        if (therapist) therapist.addEventListener('change', updateSummary);
        if (dateInput) dateInput.addEventListener('change', updateSummary);
        if (timeSelect) timeSelect.addEventListener('change', updateSummary);
    });

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
