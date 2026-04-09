@extends('layouts.app')

@section('content')
<div class="p-6 mx-auto space-y-6 max-w-7xl">

         <x-page-header
            title="Client Bookings"
            subtitle="Schedule and manage customer appointments."
        />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- Booking Form -->
        <div class="lg:col-span-2">
            <div class="h-full p-6 bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
                <h2 class="mb-2 text-lg font-semibold text-gray-800 dark:text-white">Appointment Details</h2>
                <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Fill in all required information to schedule an appointment</p>

                <form action="{{ route('bookings.store') }}" method="POST" id="booking-form">
                    @csrf

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <!-- Service Type -->
                        <div>
                            <label for="service_type" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Service Type</label>
                            <select id="service_type" name="service_type"
                                class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent">
                                <option value="in_branch" {{ old('service_type') == 'in_branch' ? 'selected' : '' }}>In Branch</option>
                                <option value="in_home" {{ old('service_type') == 'in_home' ? 'selected' : '' }}>In Home</option>
                            </select>
                        </div>

                        <!-- Treatment / Package -->
                        <div>
                            <label for="treatment" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Select Treatment / Package</label>
                            <select id="treatment" name="treatment"
                                class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent">
                                <option value="" disabled selected>Select Treatment or Package</option>
                                @foreach($treatments as $t)
                                    <option value="treatment_{{ $t->id }}" data-duration="{{ $t->duration }}" {{ old('treatment') == 'treatment_'.$t->id ? 'selected' : '' }}>
                                        Treatment: {{ $t->name }}
                                    </option>
                                @endforeach
                                @foreach($packages as $p)
                                    <option value="package_{{ $p->id }}" data-duration="{{ $p->duration }}" {{ old('treatment') == 'package_'.$p->id ? 'selected' : '' }}>
                                        Package: {{ $p->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Therapist -->
                        <div>
                            <label for="therapist_id" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Therapist</label>
                            <select id="therapist_id" name="therapist_id"
                                class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent">
                                <option value="">Select Therapist</option>
                                @foreach($therapists as $therapist)
                                    <option value="{{ $therapist->id }}" {{ old('therapist_id') == $therapist->id ? 'selected' : '' }}>{{ $therapist->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Customer Phone -->
                        <div>
                            <label for="customer_phone" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Phone Number</label>
                            <input type="tel" id="customer_phone" name="customer_phone" placeholder="Enter phone number" maxlength="11" pattern="^09\d{9}$"
                                value="{{ old('customer_phone') }}"
                                class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent" required>
                        </div>
                    </div>

                    <!-- Customer Name -->
                    <div class="mt-4">
                        <label for="customer_name" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Full Name</label>
                        <input type="text" id="customer_name" name="customer_name" placeholder="Enter full name"
                            value="{{ old('customer_name') }}"
                            class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent" required>
                    </div>

                    <!-- Customer Address -->
                    <div class="mt-4" id="customer_address_container" style="{{ old('service_type') == 'in_home' ? 'display: block;' : 'display: none;' }}">
                        <label for="customer_address" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Address</label>
                        <input type="text" id="customer_address" name="customer_address" placeholder="Enter address"
                            value="{{ old('customer_address') }}"
                            class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent">
                    </div>

                    <!-- Customer Email -->
                    <div class="mt-4">
                        <label for="customer_email" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Email</label>
                        <input type="email" id="customer_email" name="customer_email" placeholder="Enter email"
                            value="{{ old('customer_email') }}"
                            class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent" required>
                    </div>

                    <!-- Appointment Date & Time -->
                    <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2">
                        <div>
                            <label for="appointment_date" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Appointment Date</label>
                            <input type="date" id="appointment_date" name="appointment_date"
                                value="{{ old('appointment_date') }}"
                                class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent" required>
                        </div>
                        <div>
                            <label for="start_time" class="block mb-2 text-sm font-medium text-gray-800 dark:text-white">Start Time</label>
                            <input type="time" id="start_time" name="start_time"
                                value="{{ old('start_time') }}"
                                class="w-full px-3 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent"
                                required>
                            <p class="mt-1 text-xs text-gray-500" id="time-note"></p>
                        </div>
                    </div>

                    {{-- Status is always 'reserved' on creation; automated from there --}}
                    <input type="hidden" name="status" value="reserved">

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
    document.addEventListener('DOMContentLoaded', function() {
        const branchId = "{{ Auth::user()->branch_id ?? '' }}";
        const startTimeInput = document.getElementById('start_time');
        const dateInput = document.getElementById('appointment_date');
        const timeNote = document.getElementById('time-note');

        // Only show warning if no branch ID, but don't block functionality
        if (!branchId) {
            console.warn('No branch ID found for user - operating hours feature disabled');
            if (timeNote) {
                timeNote.textContent = 'Note: Operating hours validation is currently unavailable.';
                timeNote.classList.add('text-yellow-500');
            }
            if (startTimeInput) {
                startTimeInput.disabled = false;
                startTimeInput.removeAttribute('min');
                startTimeInput.removeAttribute('max');
            }
            // Still allow booking without operating hours validation
            return;
        }

        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        if (dateInput) {
            dateInput.min = today;
        }

        // Function to set min/max attributes on time input based on operating hours
        async function setTimeConstraints() {
            if (!dateInput.value) {
                if (startTimeInput) {
                    startTimeInput.disabled = true;
                    startTimeInput.placeholder = "Select date first";
                }
                if (timeNote) timeNote.textContent = 'Please select a date first';
                return;
            }

            const day = new Date(dateInput.value).toLocaleDateString('en-US', { weekday: 'long' });

            try {
                const response = await fetch(`/operating-hours/${branchId}/${day}`);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();

                if (data.is_closed) {
                    if (startTimeInput) {
                        startTimeInput.disabled = true;
                        startTimeInput.value = '';
                    }
                    if (timeNote) {
                        timeNote.textContent = 'The spa is closed on ' + day + 's. Please select another date.';
                        timeNote.classList.add('text-red-500');
                        timeNote.classList.remove('text-gray-500');
                    }
                    return;
                }

                // Set min and max attributes
                if (startTimeInput) {
                    startTimeInput.min = data.opening_time;
                    startTimeInput.max = data.closing_time;
                    startTimeInput.disabled = false;
                }

                // Get treatment duration for additional validation
                const treatmentSelect = document.getElementById('treatment');
                let duration = 60;
                if (treatmentSelect && treatmentSelect.selectedOptions[0] && treatmentSelect.selectedOptions[0].dataset.duration) {
                    duration = parseInt(treatmentSelect.selectedOptions[0].dataset.duration);
                }

                if (timeNote) {
                    timeNote.textContent = `Operating hours: ${formatTimeDisplay(data.opening_time)} - ${formatTimeDisplay(data.closing_time)} | Treatment duration: ${duration} minutes`;
                    timeNote.classList.remove('text-red-500');
                    timeNote.classList.add('text-gray-500');
                }

                if (startTimeInput && startTimeInput.value) {
                    validateSelectedTime(data.opening_time, data.closing_time);
                }

            } catch (error) {
                console.error('Error fetching operating hours:', error);
                // Don't disable the time input, just show warning
                if (startTimeInput) {
                    startTimeInput.disabled = false;
                    startTimeInput.removeAttribute('min');
                    startTimeInput.removeAttribute('max');
                }
                if (timeNote) {
                    timeNote.textContent = 'Unable to load operating hours. You can still proceed with booking.';
                    timeNote.classList.add('text-yellow-500');
                }
            }
        }

        function validateSelectedTime(openingTime, closingTime) {
            if (!startTimeInput) return true;

            const selectedTime = startTimeInput.value;
            if (!selectedTime) return true;

            if (selectedTime < openingTime) {
                startTimeInput.setCustomValidity(`Start time must be at or after ${formatTimeDisplay(openingTime)}`);
                startTimeInput.reportValidity();
                return false;
            }

            if (selectedTime > closingTime) {
                startTimeInput.setCustomValidity(`Start time must be at or before ${formatTimeDisplay(closingTime)}`);
                startTimeInput.reportValidity();
                return false;
            }

            const treatmentSelect = document.getElementById('treatment');
            let duration = 60;
            if (treatmentSelect && treatmentSelect.selectedOptions[0] && treatmentSelect.selectedOptions[0].dataset.duration) {
                duration = parseInt(treatmentSelect.selectedOptions[0].dataset.duration);
            }

            const [selectedHour, selectedMinute] = selectedTime.split(':').map(Number);
            const [closeHour, closeMinute] = closingTime.split(':').map(Number);

            const selectedDate = new Date();
            selectedDate.setHours(selectedHour, selectedMinute, 0);

            const closeDate = new Date();
            closeDate.setHours(closeHour, closeMinute, 0);

            const endDate = new Date(selectedDate.getTime() + duration * 60000);

            if (endDate > closeDate) {
                startTimeInput.setCustomValidity(`This appointment would end after closing time (${formatTimeDisplay(closingTime)}). Please select an earlier time.`);
                startTimeInput.reportValidity();
                return false;
            }

            startTimeInput.setCustomValidity('');
            return true;
        }

        function formatTimeDisplay(time) {
            if (!time) return '';
            const [hours, minutes] = time.split(':').map(Number);
            const period = hours >= 12 ? 'PM' : 'AM';
            const displayHours = hours % 12 || 12;
            return `${displayHours}:${minutes.toString().padStart(2, '0')} ${period}`;
        }

        if (startTimeInput) {
            startTimeInput.addEventListener('change', async function() {
                if (!dateInput || !dateInput.value) {
                    startTimeInput.setCustomValidity('Please select a date first');
                    startTimeInput.reportValidity();
                    return;
                }

                const day = new Date(dateInput.value).toLocaleDateString('en-US', { weekday: 'long' });
                try {
                    const response = await fetch(`/operating-hours/${branchId}/${day}`);
                    if (!response.ok) throw new Error('Failed to fetch');
                    const data = await response.json();

                    if (!data.is_closed) {
                        validateSelectedTime(data.opening_time, data.closing_time);
                    }
                } catch (error) {
                    console.error('Error validating time:', error);
                }
                updateSummary();
            });
        }

        const treatmentSelect = document.getElementById('treatment');
        if (treatmentSelect) {
            treatmentSelect.addEventListener('change', async function() {
                if (dateInput && dateInput.value && startTimeInput && startTimeInput.value && !startTimeInput.disabled) {
                    const day = new Date(dateInput.value).toLocaleDateString('en-US', { weekday: 'long' });
                    try {
                        const response = await fetch(`/operating-hours/${branchId}/${day}`);
                        if (!response.ok) throw new Error('Failed to fetch');
                        const data = await response.json();

                        if (!data.is_closed) {
                            validateSelectedTime(data.opening_time, data.closing_time);
                        }
                    } catch (error) {
                        console.error('Error validating time:', error);
                    }
                }
                if (dateInput && dateInput.value && timeNote) {
                    const day = new Date(dateInput.value).toLocaleDateString('en-US', { weekday: 'long' });
                    try {
                        const response = await fetch(`/operating-hours/${branchId}/${day}`);
                        if (!response.ok) throw new Error('Failed to fetch');
                        const data = await response.json();

                        if (!data.is_closed) {
                            let duration = 60;
                            if (treatmentSelect.selectedOptions[0] && treatmentSelect.selectedOptions[0].dataset.duration) {
                                duration = parseInt(treatmentSelect.selectedOptions[0].dataset.duration);
                            }
                            timeNote.textContent = `Operating hours: ${formatTimeDisplay(data.opening_time)} - ${formatTimeDisplay(data.closing_time)} | Treatment duration: ${duration} minutes`;
                        }
                    } catch (error) {
                        console.error('Error updating time note:', error);
                    }
                }
                updateSummary();
            });
        }

        if (dateInput) {
            dateInput.addEventListener('change', function() {
                setTimeConstraints();
                updateSummary();
            });
        }

        if (startTimeInput) {
            startTimeInput.addEventListener('input', function() {
                startTimeInput.setCustomValidity('');
            });
        }

        if (dateInput && dateInput.value) {
            setTimeConstraints();
        }

        const serviceType = document.getElementById('service_type');
        const addressContainer = document.getElementById('customer_address_container');

        if (serviceType && addressContainer) {
            serviceType.addEventListener('change', function() {
                if (this.value === 'in_home') {
                    addressContainer.style.display = 'block';
                    const customerAddress = document.getElementById('customer_address');
                    if (customerAddress) customerAddress.required = true;
                } else {
                    addressContainer.style.display = 'none';
                    const customerAddress = document.getElementById('customer_address');
                    if (customerAddress) customerAddress.required = false;
                }
                updateSummary();
            });
        }

        function updateSummary() {
            const serviceTypeElem = document.getElementById('service_type');
            const treatmentElem = document.getElementById('treatment');
            const therapistElem = document.querySelector('select[name="therapist_id"]');
            const dateInputElem = document.getElementById('appointment_date');
            const timeInputElem = document.getElementById('start_time');

            const summaryService = document.getElementById('summary-service');
            if (summaryService) {
                summaryService.textContent = (serviceTypeElem && serviceTypeElem.value && serviceTypeElem.value !== "")
                    ? serviceTypeElem.options[serviceTypeElem.selectedIndex].text
                    : "";
            }

            const summaryTreatment = document.getElementById('summary-treatment');
            if (summaryTreatment) {
                summaryTreatment.textContent = (treatmentElem && treatmentElem.value && treatmentElem.value !== "")
                    ? treatmentElem.options[treatmentElem.selectedIndex].text
                    : "";
            }

            const summaryTherapist = document.getElementById('summary-therapist');
            if (summaryTherapist) {
                summaryTherapist.textContent = (therapistElem && therapistElem.value && therapistElem.value !== "")
                    ? therapistElem.options[therapistElem.selectedIndex].text
                    : "";
            }

            const summaryDate = document.getElementById('summary-date');
            if (summaryDate) {
                if (dateInputElem && dateInputElem.value) {
                    const date = new Date(dateInputElem.value);
                    summaryDate.textContent = date.toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric'
                    });
                } else {
                    summaryDate.textContent = "";
                }
            }

            const summaryTime = document.getElementById('summary-time');
            if (summaryTime) {
                if (timeInputElem && timeInputElem.value) {
                    let startTime = timeInputElem.value;
                    let duration = 0;

                    if (treatmentElem && treatmentElem.selectedOptions[0] && treatmentElem.selectedOptions[0].dataset.duration) {
                        duration = parseInt(treatmentElem.selectedOptions[0].dataset.duration) || 0;
                    }

                    const [hours, minutes] = startTime.split(':').map(Number);
                    const startDate = new Date();
                    startDate.setHours(hours, minutes);

                    const endDate = new Date(startDate.getTime() + duration * 60000);

                    const formatTime = date => {
                        let h = date.getHours();
                        const m = date.getMinutes().toString().padStart(2, '0');
                        const ampm = h >= 12 ? 'PM' : 'AM';
                        h = h % 12 || 12;
                        return `${h}:${m} ${ampm}`;
                    }

                    summaryTime.textContent = `${formatTime(startDate)} - ${formatTime(endDate)}`;
                } else {
                    summaryTime.textContent = "";
                }
            }
        }

        async function refreshAvailableTherapists() {
            const treatmentInput = document.getElementById('treatment');
            const dateInputElem = document.getElementById('appointment_date');
            const timeInputElem = document.getElementById('start_time');
            const therapistSelectElem = document.getElementById('therapist_id');

            if (!treatmentInput?.value || !dateInputElem?.value || !timeInputElem?.value || !therapistSelectElem) {
                return;
            }

            try {
                const params = new URLSearchParams({
                    treatment: treatmentInput.value,
                    appointment_date: dateInputElem.value,
                    start_time: timeInputElem.value,
                });

                const response = await fetch(`{{ route('booking.available-therapists') }}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                });

                const data = await response.json();

                therapistSelectElem.innerHTML = '<option value="">Select Therapist</option>';

                if (!data.therapists || data.therapists.length === 0) {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'No therapist available';
                    therapistSelectElem.appendChild(option);
                    therapistSelectElem.disabled = true;
                    return;
                }

                therapistSelectElem.disabled = false;

                data.therapists.forEach(therapist => {
                    const option = document.createElement('option');
                    option.value = therapist.id;
                    option.textContent = therapist.name;

                    const oldTherapistId = "{{ old('therapist_id') }}";
                    if (oldTherapistId && Number(oldTherapistId) === Number(therapist.id)) {
                        option.selected = true;
                    } else if (Number(data.recommended_id) === Number(therapist.id) && !oldTherapistId) {
                        option.selected = true;
                    }

                    therapistSelectElem.appendChild(option);
                });

                therapistSelectElem.dispatchEvent(new Event('change'));
            } catch (error) {
                console.error('Failed to load available therapists:', error);
            }
        }

        if (treatmentSelect) treatmentSelect.addEventListener('change', refreshAvailableTherapists);
        if (dateInput) dateInput.addEventListener('change', refreshAvailableTherapists);
        if (startTimeInput) startTimeInput.addEventListener('change', refreshAvailableTherapists);

        updateSummary();

        if (treatmentSelect?.value && dateInput?.value && startTimeInput?.value) {
            refreshAvailableTherapists();
        }
    });
</script>
@endsection
