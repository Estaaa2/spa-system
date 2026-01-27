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
                    <label for="therapist_id" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Therapist</label>
                    <select class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent"name="therapist_id">
                        @foreach($therapists as $therapist)
                            <option value="{{ $therapist->id }}">
                                {{ $therapist->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Customer Phone -->
                <div>
                    <label for="customer_phone" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Phone Number</label>
                    <input type="tel" id="customer_phone" name="customer_phone" placeholder="Enter phone number" class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent" required>
                </div>
            </div>

            <!-- Customer Name -->
            <div class="mt-4">
                <label for="customer_name" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Full Name</label>
                <input type="text" id="customer_name" name="customer_name" placeholder="Enter full name" class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent" required>
            </div>

            <!-- Customer Address -->
            <div class="mt-4">
                <label for="customer_address" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Address</label>
                <input type="text" id="customer_address" name="customer_address" placeholder="Enter address" class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent" required>
            </div>

            <!-- Customer Email -->
            <div class="mt-4">
                <label for="customer_email" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Email</label>
                <input type="email" id="customer_email" name="customer_email" placeholder="Enter email" class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent" required>
            </div>

            <!-- Appointment Date & Time -->
            <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2">
                <div>
                    <label for="appointment_date" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Calendar</label>
                    <input type="date" id="appointment_date" name="appointment_date" class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent" required>
                </div>

                <div>
                    <label for="appointment_time" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Time</label>
                    <select id="appointment_time" name="appointment_time" class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent" required>
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
                <p id="summary-service" class="text-lg font-semibold text-gray-800 dark:text-white"></p>
            </div>

            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">Treatment</p>
                <p id="summary-treatment" class="text-lg font-semibold text-gray-800 dark:text-white"></p>
            </div>

            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">Therapist</p>
                <p id="summary-therapist" class="text-lg font-semibold text-gray-800 dark:text-white"></p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Date</p>
                    <p id="summary-date" class="text-lg font-semibold text-gray-800 dark:text-white"></p>
                </div>

                <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Time</p>
                    <p id="summary-time" class="text-lg font-semibold text-gray-800 dark:text-white"></p>
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

        // CORRECTED SUMMARY UPDATE FUNCTION
        function updateSummary() {
            // Get form elements with YOUR ACTUAL IDs
            const serviceType = document.getElementById('service_type');
            const treatment = document.getElementById('treatment');
            const therapist = document.querySelector('select[name="therapist_id"]');
            const dateInput = document.getElementById('appointment_date');
            const timeSelect = document.getElementById('appointment_time');

            // Update service type summary - ONLY IF SELECTED
            if (serviceType && serviceType.value && serviceType.value !== "") {
                document.getElementById('summary-service').textContent =
                    serviceType.options[serviceType.selectedIndex].text;
            } else {
                document.getElementById('summary-service').textContent = "";
            }

            // Update treatment summary - ONLY IF SELECTED
            if (treatment && treatment.value && treatment.value !== "") {
                document.getElementById('summary-treatment').textContent =
                    treatment.options[treatment.selectedIndex].text;
            } else {
                document.getElementById('summary-treatment').textContent = "";
            }

            // Update therapist summary - ONLY IF SELECTED
            if (therapist && therapist.value && therapist.value !== "") {
                document.getElementById('summary-therapist').textContent =
                    therapist.options[therapist.selectedIndex].text;
            } else {
                document.getElementById('summary-therapist').textContent = "";
            }

            // Update date summary - ONLY IF SELECTED
            if (dateInput && dateInput.value) {
                const date = new Date(dateInput.value);
                document.getElementById('summary-date').textContent =
                    date.toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric'
                    });
            } else {
                document.getElementById('summary-date').textContent = "";
            }

            // Update time summary - ONLY IF SELECTED (not the default disabled option)
            if (timeSelect && timeSelect.value && timeSelect.value !== "") {
                document.getElementById('summary-time').textContent =
                    timeSelect.options[timeSelect.selectedIndex].text;
            } else {
                document.getElementById('summary-time').textContent = "";
            }
        }

        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        const dateInput = document.getElementById('appointment_date');
        if (dateInput) {
            dateInput.min = today;
        }

        // Add event listeners to YOUR ACTUAL ELEMENTS
        const serviceType = document.getElementById('service_type');
        const treatment = document.getElementById('treatment');
        const therapist = document.querySelector('select[name="therapist_id"]');
        const dateInputElem = document.getElementById('appointment_date');
        const timeSelect = document.getElementById('appointment_time');

        if (serviceType) serviceType.addEventListener('change', updateSummary);
        if (treatment) treatment.addEventListener('change', updateSummary);
        if (therapist) therapist.addEventListener('change', updateSummary);
        if (dateInputElem) dateInputElem.addEventListener('change', updateSummary);
        if (timeSelect) timeSelect.addEventListener('change', updateSummary);

        // Initial update - CLEAR ALL DEFAULTS
        updateSummary();
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
                }).showToast();
            });
        }
    </script>
@endif

@endsection
