@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl">

    <div class="p-6">
        <x-page-header
            title="Client Bookings"
            subtitle="Schedule and manage customer appointments."
        />

        {{-- ============================================================
             CONTEXT BANNER — Walk-in / Phone booking clarification
             ============================================================ --}}
        <div class="flex items-start gap-3 p-4 mb-6 border rounded-lg bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-700">
            <div class="flex-shrink-0 mt-0.5">
                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 110 20A10 10 0 0112 2z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">This form is for in-branch use only</p>
                <p class="mt-0.5 text-sm text-amber-700 dark:text-amber-400">
                    Use this to book appointments for <strong>walk-in customers</strong> who arrived at the branch, or for customers who
                    <strong>called ahead</strong> using the branch contact number. Online customer bookings are handled separately through the customer portal.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Booking Form -->
            <div class="lg:col-span-2">
                <div class="h-full p-6 bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">

                    <div class="flex items-center justify-between mb-1">
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Appointment Details</h2>

                        {{-- Walk-in Quick Fill Button --}}
                        <button type="button" id="walkinFillBtn"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white rounded-lg
                                   bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:opacity-90 transition focus:ring-2 focus:ring-[#8B7355]/50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Walk-in Now
                        </button>
                    </div>
                    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">
                        Fill in all required information. For walk-in customers, click <strong>Walk-in Now</strong> to auto-fill today's date and current time.
                    </p>

                    <form action="{{ route('bookings.store') }}" method="POST" id="bookingForm">
                        @csrf

                        {{-- Closed-day error banner (shown by JS when branch is closed on selected date) --}}
                        <div id="closedDayError"
                             class="hidden items-start gap-2 p-3 mb-4 text-sm text-red-700 border rounded-lg bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-700 dark:text-red-400">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01M12 3a9 9 0 100 18A9 9 0 0012 3z"/>
                            </svg>
                            <span id="closedDayErrorText">The branch is closed on the selected day.</span>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                            {{-- Service Type --}}
                            <div>
                                <label for="service_type" class="block mb-1.5 text-sm font-medium text-gray-800 dark:text-white">
                                    Service Type <span class="text-red-500">*</span>
                                </label>
                                <select id="service_type" name="service_type"
                                    class="w-full px-3 py-2 text-gray-800 bg-white border rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent
                                           {{ $errors->has('service_type') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600' }}">
                                    <option value="in_branch" {{ old('service_type', 'in_branch') === 'in_branch' ? 'selected' : '' }}>In Branch</option>
                                    <option value="in_home"   {{ old('service_type') === 'in_home' ? 'selected' : '' }}>In Home</option>
                                </select>
                                @error('service_type')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Treatment / Package --}}
                            <div>
                                <label for="treatment" class="block mb-1.5 text-sm font-medium text-gray-800 dark:text-white">
                                    Treatment / Package <span class="text-red-500">*</span>
                                </label>
                                <select id="treatment" name="treatment"
                                    class="w-full px-3 py-2 text-gray-800 bg-white border rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent
                                           {{ $errors->has('treatment') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600' }}">
                                    <option value="" disabled {{ old('treatment') ? '' : 'selected' }}>Select Treatment or Package</option>
                                    @foreach($treatments as $t)
                                        <option value="treatment_{{ $t->id }}"
                                                data-duration="{{ $t->duration }}"
                                                {{ old('treatment') === 'treatment_'.$t->id ? 'selected' : '' }}>
                                            Treatment: {{ $t->name }}
                                        </option>
                                    @endforeach
                                    @foreach($packages as $p)
                                        <option value="package_{{ $p->id }}"
                                                data-duration="{{ $p->duration ?? $p->total_duration }}"
                                                {{ old('treatment') === 'package_'.$p->id ? 'selected' : '' }}>
                                            Package: {{ $p->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('treatment')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Therapist --}}
                            <div>
                                <label for="therapist_id" class="block mb-1.5 text-sm font-medium text-gray-800 dark:text-white">
                                    Therapist <span class="text-red-500">*</span>
                                </label>
                                <select id="therapist_id" name="therapist_id"
                                    class="w-full px-3 py-2 text-gray-800 bg-white border rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent
                                           {{ $errors->has('therapist_id') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600' }}">
                                    @foreach($therapists as $therapist)
                                        <option value="{{ $therapist->id }}"
                                                {{ old('therapist_id') == $therapist->id ? 'selected' : '' }}>
                                            {{ $therapist->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('therapist_id')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                                <p id="therapistHint" class="hidden mt-1 text-xs text-amber-600 dark:text-amber-400">
                                    No therapist available for the selected time slot.
                                </p>
                            </div>

                            {{-- Customer Phone --}}
                            <div>
                                <label for="customer_phone" class="block mb-1.5 text-sm font-medium text-gray-800 dark:text-white">
                                    Phone Number <span class="text-red-500">*</span>
                                </label>
                                <input type="tel" id="customer_phone" name="customer_phone"
                                    value="{{ old('customer_phone') }}"
                                    placeholder="09xxxxxxxxx" maxlength="11" pattern="^09\d{9}$"
                                    class="w-full px-3 py-2 text-gray-800 bg-white border rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent
                                           {{ $errors->has('customer_phone') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600' }}"
                                    required>
                                @error('customer_phone')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Customer Name --}}
                        <div class="mt-4">
                            <label for="customer_name" class="block mb-1.5 text-sm font-medium text-gray-800 dark:text-white">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="customer_name" name="customer_name"
                                value="{{ old('customer_name') }}"
                                placeholder="Enter customer's full name"
                                class="w-full px-3 py-2 text-gray-800 bg-white border rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent
                                       {{ $errors->has('customer_name') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600' }}"
                                required>
                            @error('customer_name')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Customer Address (in_home only) --}}
                        <div class="mt-4" id="customer_address_container"
                             style="{{ old('service_type') === 'in_home' ? '' : 'display: none;' }}">
                            <label for="customer_address" class="block mb-1.5 text-sm font-medium text-gray-800 dark:text-white">
                                Address <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="customer_address" name="customer_address"
                                value="{{ old('customer_address') }}"
                                placeholder="Enter customer's full address"
                                class="w-full px-3 py-2 text-gray-800 bg-white border rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent
                                       {{ $errors->has('customer_address') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600' }}"
                                {{ old('service_type') === 'in_home' ? 'required' : '' }}>
                            @error('customer_address')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Customer Email --}}
                        <div class="mt-4">
                            <label for="customer_email" class="block mb-1.5 text-sm font-medium text-gray-800 dark:text-white">
                                Email <span class="text-red-500">*</span>
                            </label>
                            <input type="email" id="customer_email" name="customer_email"
                                value="{{ old('customer_email') }}"
                                placeholder="Enter customer's email address"
                                class="w-full px-3 py-2 text-gray-800 bg-white border rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent
                                       {{ $errors->has('customer_email') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600' }}"
                                required>
                            @error('customer_email')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Date & Time --}}
                        <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2">

                            {{-- Appointment Date --}}
                            <div>
                                <label for="appointment_date" class="block mb-1.5 text-sm font-medium text-gray-800 dark:text-white">
                                    Appointment Date <span class="text-red-500">*</span>
                                </label>
                                <input type="date" id="appointment_date" name="appointment_date"
                                    value="{{ old('appointment_date') }}"
                                    min="{{ date('Y-m-d') }}"
                                    class="w-full px-3 py-2 text-gray-800 bg-white border rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent
                                           {{ $errors->has('appointment_date') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600' }}"
                                    required>
                                @error('appointment_date')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Start Time --}}
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label for="start_time" class="text-sm font-medium text-gray-800 dark:text-white">
                                        Start Time <span class="text-red-500">*</span>
                                    </label>
                                    <span id="operatingHoursHint" class="text-xs text-gray-400 dark:text-gray-500"></span>
                                </div>
                                <input type="time" id="start_time" name="start_time"
                                    value="{{ old('start_time') }}"
                                    class="w-full px-3 py-2 text-gray-800 bg-white border rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent
                                           {{ $errors->has('start_time') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600' }}"
                                    required>
                                {{-- Server-side error --}}
                                @error('start_time')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                                {{-- Client-side time range error (shown by JS) --}}
                                <p id="timeRangeError" class="hidden mt-1 text-xs text-red-600 dark:text-red-400"></p>
                            </div>
                        </div>

                        <input type="hidden" name="status" value="reserved">

                        <div class="mt-8">
                            <button type="submit" id="submitBtn"
                                class="w-full px-6 py-3 font-semibold text-white transition-all duration-200 bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg hover:opacity-90 focus:ring-4 focus:ring-[#8B7355]/50 disabled:opacity-50 disabled:cursor-not-allowed">
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
                            <p id="summary-service" class="text-base font-semibold text-gray-800 dark:text-white">—</p>
                        </div>
                        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Treatment</p>
                            <p id="summary-treatment" class="text-base font-semibold text-gray-800 dark:text-white">—</p>
                        </div>
                        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Therapist</p>
                            <p id="summary-therapist" class="text-base font-semibold text-gray-800 dark:text-white">—</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Date</p>
                                <p id="summary-date" class="text-base font-semibold text-gray-800 dark:text-white">—</p>
                            </div>
                            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Time</p>
                                <p id="summary-time" class="text-base font-semibold text-gray-800 dark:text-white">—</p>
                            </div>
                        </div>
                    </div>

                    {{-- Booking source badge --}}
                    <div class="pt-5 mt-6 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 ring-1 ring-amber-200 dark:ring-amber-700">
                            <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <p class="text-xs font-medium text-amber-700 dark:text-amber-400">
                                Staff-created booking (walk-in / phone)
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>{{-- end grid --}}
    </div>{{-- end p-6 --}}
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // =====================================================
    // CONSTANTS
    // =====================================================
    const branchId        = "{{ Auth::user()->currentBranchId() ?? Auth::user()->branch_id }}";
    const dateInput       = document.getElementById('appointment_date');
    const timeInput       = document.getElementById('start_time');
    const serviceType     = document.getElementById('service_type');
    const treatmentSelect = document.getElementById('treatment');
    const therapistSelect = document.getElementById('therapist_id');
    const submitBtn       = document.getElementById('submitBtn');
    const timeRangeError  = document.getElementById('timeRangeError');
    const closedDayError  = document.getElementById('closedDayError');
    const closedDayText   = document.getElementById('closedDayErrorText');
    const hoursHint       = document.getElementById('operatingHoursHint');
    const therapistHint   = document.getElementById('therapistHint');
    const addressContainer = document.getElementById('customer_address_container');
    const addressInput    = document.getElementById('customer_address');

    let openingTime = null; // "HH:MM" — populated after date selection
    let closingTime = null;

    // =====================================================
    // HELPERS
    // =====================================================
    function todayString() {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
    }

    function nowTimeString() {
        const now = new Date();
        return `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`;
    }

    function formatTime12(hhmm) {
        if (!hhmm) return '';
        const [h, m] = hhmm.split(':').map(Number);
        const ampm = h >= 12 ? 'PM' : 'AM';
        return `${h % 12 || 12}:${String(m).padStart(2,'0')} ${ampm}`;
    }

    function setFieldError(input, hasError) {
        if (!input) return;
        if (hasError) {
            input.classList.add('border-red-400', 'bg-red-50', 'dark:bg-red-900/20');
            input.classList.remove('border-gray-300', 'dark:border-gray-600');
        } else {
            input.classList.remove('border-red-400', 'bg-red-50', 'dark:bg-red-900/20');
            input.classList.add('border-gray-300', 'dark:border-gray-600');
        }
    }

    // =====================================================
    // WALK-IN QUICK FILL
    // =====================================================
    document.getElementById('walkinFillBtn')?.addEventListener('click', function () {
        dateInput.value = todayString();
        // Trigger operating hours fetch first, then set current time
        updateOperatingHours().then(() => {
            timeInput.value = nowTimeString();
            validateTimeRange();
            updateSummary();
            refreshAvailableTherapists();
        });
    });

    // =====================================================
    // OPERATING HOURS FETCH
    // =====================================================
    async function updateOperatingHours() {
        if (!dateInput.value || !branchId) return;

        const day = new Date(dateInput.value + 'T00:00:00')
            .toLocaleDateString('en-US', { weekday: 'long' });

        try {
            const res  = await fetch(`/operating-hours/${branchId}/${day}`);
            const data = await res.json();

            if (data.is_closed) {
                openingTime = null;
                closingTime = null;
                timeInput.value    = '';
                timeInput.disabled = true;
                timeInput.removeAttribute('min');
                timeInput.removeAttribute('max');
                closedDayError.classList.remove('hidden');
                closedDayError.classList.add('flex');
                closedDayText.textContent = `The branch is closed on ${day}s. Please select a different date.`;
                setFieldError(dateInput, true);
                setFieldError(timeInput, true);
                hoursHint.textContent = '';
                submitBtn.disabled = true;
                return;
            }

            // Branch is open
            openingTime = data.opening_time?.slice(0,5) ?? null;
            closingTime = data.closing_time?.slice(0,5) ?? null;

            timeInput.disabled = false;
            if (openingTime) timeInput.min = openingTime;
            if (closingTime) timeInput.max = closingTime;

            closedDayError.classList.add('hidden');
            closedDayError.classList.remove('flex');
            setFieldError(dateInput, false);
            hoursHint.textContent = openingTime && closingTime
                ? `${formatTime12(openingTime)} – ${formatTime12(closingTime)}`
                : '';

            submitBtn.disabled = false;

            if (timeInput.value) validateTimeRange();

        } catch (err) {
            console.error('Failed to fetch operating hours:', err);
        }
    }

    // =====================================================
    // TIME RANGE VALIDATION (client-side)
    // =====================================================
    function validateTimeRange() {
        const time = timeInput.value;
        if (!time || !openingTime || !closingTime) {
            timeRangeError.classList.add('hidden');
            setFieldError(timeInput, false);
            return true;
        }

        if (time < openingTime || time >= closingTime) {
            const msg = `Time must be within operating hours: ${formatTime12(openingTime)} – ${formatTime12(closingTime)}.`;
            timeRangeError.textContent = msg;
            timeRangeError.classList.remove('hidden');
            setFieldError(timeInput, true);
            submitBtn.disabled = true;
            return false;
        }

        // If booking for today, time must be in the future
        if (dateInput.value === todayString()) {
            const now  = new Date();
            const [hh, mm] = time.split(':').map(Number);
            const sel  = new Date(); sel.setHours(hh, mm, 0, 0);
            if (sel <= now) {
                timeRangeError.textContent = 'Please select a future time — this time has already passed today.';
                timeRangeError.classList.remove('hidden');
                setFieldError(timeInput, true);
                submitBtn.disabled = true;
                return false;
            }
        }

        timeRangeError.classList.add('hidden');
        setFieldError(timeInput, false);
        submitBtn.disabled = false;
        return true;
    }

    // =====================================================
    // ADDRESS FIELD TOGGLE
    // =====================================================
    function toggleAddress() {
        const isHome = serviceType.value === 'in_home';
        addressContainer.style.display = isHome ? '' : 'none';
        if (addressInput) addressInput.required = isHome;
    }

    serviceType.addEventListener('change', toggleAddress);
    toggleAddress(); // run on load in case old() set it to in_home

    // =====================================================
    // AVAILABLE THERAPISTS (dynamic refresh)
    // =====================================================
    async function refreshAvailableTherapists() {
        if (!treatmentSelect.value || !dateInput.value || !timeInput.value) return;

        try {
            const params = new URLSearchParams({
                treatment:        treatmentSelect.value,
                appointment_date: dateInput.value,
                start_time:       timeInput.value,
            });

            const res  = await fetch(`{{ route('booking.available-therapists') }}?${params}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            therapistSelect.innerHTML = '';
            therapistHint.classList.add('hidden');

            if (!data.therapists?.length) {
                const opt   = document.createElement('option');
                opt.value   = '';
                opt.textContent = 'No therapist available for this slot';
                therapistSelect.appendChild(opt);
                therapistSelect.disabled = true;
                therapistHint.classList.remove('hidden');
                return;
            }

            therapistSelect.disabled = false;
            data.therapists.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = t.name;
                if (Number(data.recommended_id) === Number(t.id)) opt.selected = true;
                therapistSelect.appendChild(opt);
            });
            therapistSelect.dispatchEvent(new Event('change'));

        } catch (err) {
            console.error('Therapist refresh failed:', err);
        }
    }

    // =====================================================
    // SUMMARY PANEL UPDATE
    // =====================================================
    function updateSummary() {
        const svcEl  = document.getElementById('summary-service');
        const trtEl  = document.getElementById('summary-treatment');
        const thrEl  = document.getElementById('summary-therapist');
        const datEl  = document.getElementById('summary-date');
        const timEl  = document.getElementById('summary-time');

        svcEl.textContent  = serviceType.value
            ? serviceType.options[serviceType.selectedIndex].text : '—';

        trtEl.textContent  = treatmentSelect.value
            ? treatmentSelect.options[treatmentSelect.selectedIndex].text : '—';

        thrEl.textContent  = therapistSelect.value
            ? therapistSelect.options[therapistSelect.selectedIndex]?.text ?? '—' : '—';

        if (dateInput.value) {
            datEl.textContent = new Date(dateInput.value + 'T00:00:00')
                .toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        } else {
            datEl.textContent = '—';
        }

        if (timeInput.value) {
            const duration = parseInt(treatmentSelect.selectedOptions[0]?.dataset.duration) || 0;
            const [hh, mm] = timeInput.value.split(':').map(Number);
            const start    = new Date(); start.setHours(hh, mm, 0, 0);
            const end      = new Date(start.getTime() + duration * 60000);
            const fmt = d => {
                let h = d.getHours(), m = String(d.getMinutes()).padStart(2,'0');
                return `${h%12||12}:${m} ${h>=12?'PM':'AM'}`;
            };
            timEl.textContent = `${fmt(start)} – ${fmt(end)}`;
        } else {
            timEl.textContent = '—';
        }
    }

    // =====================================================
    // EVENT WIRING
    // =====================================================
    dateInput.addEventListener('change', () => {
        updateOperatingHours().then(() => {
            refreshAvailableTherapists();
            updateSummary();
        });
    });

    timeInput.addEventListener('change', () => { validateTimeRange(); refreshAvailableTherapists(); updateSummary(); });
    timeInput.addEventListener('input',  () => { validateTimeRange(); updateSummary(); });
    treatmentSelect.addEventListener('change', () => { refreshAvailableTherapists(); updateSummary(); });
    serviceType.addEventListener('change', updateSummary);
    therapistSelect.addEventListener('change', updateSummary);

    // Phone: digits only
    document.getElementById('customer_phone')?.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 11);
    });

    // =====================================================
    // INIT — if old() values were restored after a
    // validation error, rebuild the operating hours state
    // =====================================================
    if (dateInput.value) {
        updateOperatingHours().then(() => {
            if (timeInput.value) validateTimeRange();
            updateSummary();
        });
    } else {
        updateSummary();
    }

    // =====================================================
    // FORM SUBMIT GUARD
    // =====================================================
    document.getElementById('bookingForm').addEventListener('submit', function (e) {
        if (!validateTimeRange()) {
            e.preventDefault();
        }
    });
});
</script>
@endsection