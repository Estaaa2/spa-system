@extends('layouts.app')

@section('title', 'Book an Appointment')
@section('content')

<div class="p-6 mx-auto space-y-6 max-w-7xl">

    <x-page-header
        title="Client Bookings"
        subtitle="Schedule and manage customer appointments."
    />

    <!-- Context Notice -->
    <div class="border border-amber-200 rounded-2xl bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700">
        <button type="button" id="noticeToggle"
                aria-expanded="false" aria-controls="noticeDetail"
                class="flex items-center w-full gap-3 px-4 py-3 text-left rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50">
            <i class="fa-solid fa-circle-info text-amber-600 dark:text-amber-400"></i>
            <span class="flex-1 text-sm font-medium text-amber-800 dark:text-amber-300">
                For in-branch and phone bookings only
            </span>
            <i id="noticeChevron" class="text-xs transition-transform duration-200 fa-solid fa-chevron-down text-amber-600 dark:text-amber-400"></i>
        </button>
        <div id="noticeDetail" class="hidden px-4 pb-4 pl-12">
            <p class="text-sm text-amber-700 dark:text-amber-400">
                Use this form for <strong>walk-in customers</strong> who arrived at the branch, or for customers who
                <strong>called ahead</strong> using the branch contact number. Online customer bookings are handled
                separately through the customer portal and appear automatically in Appointments.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- Appointment Summary -->
        <div class="lg:order-2">
            <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">

                <button type="button" id="summaryToggle"
                        aria-expanded="false" aria-controls="summaryBody"
                        class="flex items-start w-full gap-3 p-4 text-left sm:p-6 rounded-2xl lg:cursor-default focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#8B7355]/50 dark:focus-visible:ring-[#C4A97D]/50">
                    <div class="flex-1 min-w-0">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Appointment Summary</h2>
                        <p class="hidden mt-1 text-sm text-gray-500 lg:block dark:text-gray-400">
                            Review these details before reserving.
                        </p>
                        <p id="summaryDigest" class="mt-1 text-sm text-gray-500 lg:hidden dark:text-gray-400">
                            Nothing selected yet
                        </p>
                    </div>
                    <i id="summaryChevron" class="mt-1 text-xs text-gray-400 transition-transform duration-200 fa-solid fa-chevron-down lg:hidden"></i>
                </button>

                <div id="summaryBody" class="hidden px-4 pb-4 sm:px-6 sm:pb-6">
                    <div class="space-y-4">
                        <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-700">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Customer</p>
                            <p id="summary-customer" class="text-base font-semibold text-gray-900 dark:text-white">—</p>
                        </div>
                        <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-700">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Service Type</p>
                            <p id="summary-service" class="text-base font-semibold text-gray-900 dark:text-white">—</p>
                        </div>
                        <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-700">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Treatment</p>
                            <p id="summary-treatment" class="text-base font-semibold text-gray-900 dark:text-white">—</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-700">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Date</p>
                                <p id="summary-date" class="text-base font-semibold text-gray-900 dark:text-white">—</p>
                            </div>
                            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-700">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Time</p>
                                <p id="summary-time" class="text-base font-semibold text-gray-900 dark:text-white">—</p>
                            </div>
                        </div>
                        <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-700">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Therapist</p>
                            <p id="summary-therapist" class="text-base font-semibold text-gray-900 dark:text-white">—</p>
                        </div>
                    </div>

                    {{-- Booking source badge — same border-based amber notice
                         pattern as the context notice, one radius step down. --}}
                    <div class="pt-5 mt-6 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 p-3 border border-amber-200 rounded-xl bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700">
                            <i class="flex-shrink-0 text-xs fa-solid fa-user text-amber-600 dark:text-amber-400"></i>
                            <p class="text-xs font-medium text-amber-700 dark:text-amber-400">
                                Staff-created booking (walk-in / phone)
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Booking Form -->
        <div class="lg:order-1 lg:col-span-2">
            <div class="h-full p-4 bg-white border border-gray-200 shadow-sm sm:p-6 rounded-2xl dark:bg-gray-800 dark:border-gray-700">

                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Appointment Details</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Fields marked <span class="text-red-600 dark:text-red-400">*</span> are required.
                </p>

                <button type="button" id="walkinFillBtn"
                    class="inline-flex items-center justify-center w-full gap-2 px-4 py-3 mt-4 text-sm font-semibold text-white transition sm:w-auto rounded-xl
                           bg-gradient-to-r from-[#7A6348] to-[#6F5430] hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]/50 dark:focus-visible:ring-[#C4A97D]/50">
                    <i class="fa-solid fa-clock"></i>
                    Walk-in Now — fill today &amp; current time
                </button>

                <form action="{{ route('bookings.store') }}" method="POST" id="bookingForm" class="mt-6">
                    @csrf

                    {{-- ---------- CUSTOMER ---------- --}}
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Customer</p>

                    <div class="mt-3">
                        <label for="customer_name" class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Full Name <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                        </label>
                        <input type="text" id="customer_name" name="customer_name"
                            value="{{ old('customer_name') }}"
                            placeholder="Enter customer's full name"
                            class="w-full px-3 py-2 text-gray-900 bg-white border rounded-xl dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355] dark:focus:ring-[#C4A97D] focus:border-transparent
                                   {{ $errors->has('customer_name') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600' }}"
                            required>
                        @error('customer_name')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2">
                        <div>
                            <label for="customer_phone" class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Phone Number <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                            </label>
                            <input type="tel" id="customer_phone" name="customer_phone"
                                value="{{ old('customer_phone') }}"
                                placeholder="09xxxxxxxxx" maxlength="11" pattern="^09\d{9}$"
                                class="w-full px-3 py-2 text-gray-900 bg-white border rounded-xl dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355] dark:focus:ring-[#C4A97D] focus:border-transparent
                                       {{ $errors->has('customer_phone') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600' }}"
                                required>
                            @error('customer_phone')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="customer_email" class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Email <span class="font-normal text-gray-400">(optional)</span>
                            </label>
                            <input type="email" id="customer_email" name="customer_email"
                                value="{{ old('customer_email') }}"
                                placeholder="Enter customer's email address"
                                class="w-full px-3 py-2 text-gray-900 bg-white border rounded-xl dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355] dark:focus:ring-[#C4A97D] focus:border-transparent
                                    {{ $errors->has('customer_email') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600' }}">
                            @error('customer_email')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- ---------- SERVICE ---------- --}}
                    <p class="mt-8 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Service</p>

                    <div class="grid grid-cols-1 gap-4 mt-3 sm:grid-cols-2">
                        <div>
                            <label for="service_type" class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Service Type <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                            </label>
                            <select id="service_type" name="service_type"
                                class="w-full px-3 py-2 text-gray-900 bg-white border rounded-xl dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355] dark:focus:ring-[#C4A97D] focus:border-transparent
                                       {{ $errors->has('service_type') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600' }}">
                                <option value="in_branch" {{ old('service_type', 'in_branch') === 'in_branch' ? 'selected' : '' }}>In Branch</option>
                                <option value="in_home"   {{ old('service_type') === 'in_home' ? 'selected' : '' }}>In Home</option>
                            </select>
                            @error('service_type')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="treatment" class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Treatment / Package <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                            </label>
                            <select id="treatment" name="treatment"
                                class="w-full px-3 py-2 text-gray-900 bg-white border rounded-xl dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355] dark:focus:ring-[#C4A97D] focus:border-transparent
                                       {{ $errors->has('treatment') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600' }}">
                                <option value="" disabled {{ old('treatment') ? '' : 'selected' }}>Select Treatment or Package</option>
                                @foreach($treatments as $t)
                                    <option value="treatment_{{ $t->id }}"
                                            data-duration="{{ $t->duration }}"
                                            data-service-type="{{ $t->service_type }}"
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
                    </div>

                    {{-- Address is driven by Service Type, so it sits directly
                         beneath it. Visibility uses the `hidden` class in both
                         Blade and JS — no inline style.display anywhere. --}}
                    @php $isHomeInitial = old('service_type') === 'in_home'; @endphp
                    <div class="mt-4 {{ $isHomeInitial ? '' : 'hidden' }}" id="customer_address_container">
                        <label for="customer_address" class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Address <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                        </label>
                        <input type="text" id="customer_address" name="customer_address"
                            value="{{ old('customer_address') }}"
                            placeholder="Enter customer's full address"
                            class="w-full px-3 py-2 text-gray-900 bg-white border rounded-xl dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355] dark:focus:ring-[#C4A97D] focus:border-transparent
                                   {{ $errors->has('customer_address') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600' }}"
                            {{ $isHomeInitial ? 'required' : '' }}>
                        @error('customer_address')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- ---------- SCHEDULE ---------- --}}
                    <p class="mt-8 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Schedule</p>

                    <div aria-live="polite" aria-atomic="true">
                        <div id="closedDayError"
                             class="items-start hidden gap-2 p-3 mt-3 text-sm text-red-700 border border-red-200 rounded-xl bg-red-50 dark:bg-red-900/20 dark:border-red-700 dark:text-red-400">
                            <i class="flex-shrink-0 mt-0.5 fa-solid fa-circle-exclamation"></i>
                            <span id="closedDayErrorText">The branch is closed on the selected day.</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 mt-3 sm:grid-cols-2">
                        <div>
                            <label for="appointment_date" class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Appointment Date <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                            </label>
                            <input type="date" id="appointment_date" name="appointment_date"
                                value="{{ old('appointment_date') }}"
                                min="{{ date('Y-m-d') }}"
                                class="w-full px-3 py-2 text-gray-900 bg-white border rounded-xl dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355] dark:focus:ring-[#C4A97D] focus:border-transparent
                                       {{ $errors->has('appointment_date') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600' }}"
                                required>
                            @error('appointment_date')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                <label for="start_time" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Start Time <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                                </label>
                                <span id="operatingHoursHint" class="text-xs text-gray-500 dark:text-gray-400"></span>
                            </div>
                            <input type="time" id="start_time" name="start_time"
                                value="{{ old('start_time') }}"
                                aria-describedby="timeRangeError"
                                class="w-full px-3 py-2 text-gray-900 bg-white border rounded-xl dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355] dark:focus:ring-[#C4A97D] focus:border-transparent
                                       {{ $errors->has('start_time') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600' }}"
                                required>
                            @error('start_time')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                            <div aria-live="polite" aria-atomic="true">
                                <p id="timeRangeError" class="hidden mt-1 text-xs text-red-600 dark:text-red-400"></p>
                            </div>
                        </div>
                    </div>

                    {{-- ---------- THERAPIST ---------- --}}
                    <p class="mt-8 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Therapist</p>

                    <div class="mt-3">
                        <label for="therapist_id" class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Assigned Therapist <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                        </label>
                        <select id="therapist_id" name="therapist_id"
                            aria-describedby="therapistHint"
                            class="w-full px-3 py-2 text-gray-900 bg-white border rounded-xl dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355] dark:focus:ring-[#C4A97D] focus:border-transparent
                                   {{ $errors->has('therapist_id') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600' }}"
                            required>
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
                        <div aria-live="polite" aria-atomic="true">
                            <p id="therapistHint" class="hidden mt-1 text-xs text-amber-600 dark:text-amber-400"></p>
                        </div>
                    </div>

                    <input type="hidden" name="status" value="reserved">

                    <div class="mt-8">
                        <button type="submit" id="submitBtn"
                            class="w-full px-6 py-3 font-semibold text-white transition-all duration-200 bg-gradient-to-r from-[#7A6348] to-[#6F5430] rounded-xl hover:opacity-90 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#8B7355]/50 dark:focus-visible:ring-[#C4A97D]/50 disabled:opacity-50 disabled:cursor-not-allowed">
                            Reserve Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>{{-- end grid --}}
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // =====================================================
    // ELEMENTS
    // =====================================================
    const els = {
        form:             document.getElementById('bookingForm'),
        dateInput:        document.getElementById('appointment_date'),
        timeInput:        document.getElementById('start_time'),
        serviceType:      document.getElementById('service_type'),
        treatmentSelect:  document.getElementById('treatment'),
        therapistSelect:  document.getElementById('therapist_id'),
        submitBtn:        document.getElementById('submitBtn'),
        timeRangeError:   document.getElementById('timeRangeError'),
        closedDayError:   document.getElementById('closedDayError'),
        closedDayText:    document.getElementById('closedDayErrorText'),
        hoursHint:        document.getElementById('operatingHoursHint'),
        therapistHint:    document.getElementById('therapistHint'),
        addressContainer: document.getElementById('customer_address_container'),
        addressInput:     document.getElementById('customer_address'),
        walkinBtn:        document.getElementById('walkinFillBtn'),
        phoneInput:       document.getElementById('customer_phone'),
        nameInput:        document.getElementById('customer_name'),
        noticeToggle:     document.getElementById('noticeToggle'),
        noticeDetail:     document.getElementById('noticeDetail'),
        noticeChevron:    document.getElementById('noticeChevron'),
        summaryToggle:    document.getElementById('summaryToggle'),
        summaryBody:      document.getElementById('summaryBody'),
        summaryChevron:   document.getElementById('summaryChevron'),
        summaryDigest:    document.getElementById('summaryDigest'),
        summaryCustomer:  document.getElementById('summary-customer'),
        summaryService:   document.getElementById('summary-service'),
        summaryTreatment: document.getElementById('summary-treatment'),
        summaryTherapist: document.getElementById('summary-therapist'),
        summaryDate:      document.getElementById('summary-date'),
        summaryTime:      document.getElementById('summary-time'),
    };

    const missing = Object.entries(els).filter(([, node]) => !node).map(([key]) => key);
    if (missing.length) {
        console.error('booking.blade.php: missing expected elements —', missing.join(', '));
        return;
    }

    const {
        form, dateInput, timeInput, serviceType, treatmentSelect, therapistSelect,
        submitBtn, timeRangeError, closedDayError, closedDayText, hoursHint,
        therapistHint, addressContainer, addressInput, walkinBtn, phoneInput, nameInput,
    } = els;

    // =====================================================
    // CONSTANTS
    // =====================================================
    const branchId = "{{ Auth::user()->currentBranchId() ?? Auth::user()->branch_id }}";

    // NOTE: the operating-hours endpoint is an unnamed closure in
    // routes/api.php, so route() cannot be used for it. Confirm the resolved
    // path with `php artisan route:list --path=operating-hours` and adjust
    // this one constant if it is served under the /api prefix.
    const OPERATING_HOURS_ENDPOINT      = @json(url('/operating-hours'));
    const AVAILABLE_THERAPISTS_ENDPOINT = @json(route('booking.available-therapists'));

    // A walk-in is recorded as it happens, so a start time slightly behind
    // the clock is legitimate. Older than this is treated as a mistake.
    const PAST_TIME_GRACE_MINUTES = 15;
    const THERAPIST_DEBOUNCE_MS   = 350;

    const restoredTherapistId = @json(old('therapist_id'));
    const desktopQuery = window.matchMedia('(min-width: 1024px)');

    let openingTime = null; // "HH:MM" — populated after date selection
    let closingTime = null;

    // =====================================================
    // SUBMIT-STATE SINGLE SOURCE OF TRUTH
    // Nothing else writes submitBtn.disabled. Each rule sets its
    // own flag and calls syncSubmitState(), so a later passing
    // check cannot silently undo an earlier failing one.
    // =====================================================
    const blockers = {
        closedDay:      false, // branch closed on the chosen date
        timeOutOfRange: false, // outside operating hours, too far past, or ends after closing
        noTherapist:    false, // availability API returned an empty list for this slot
    };

    function syncSubmitState() {
        submitBtn.disabled = blockers.closedDay || blockers.timeOutOfRange || blockers.noTherapist;
    }

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

    function toMinutes(hhmm) {
        const [h, m] = hhmm.split(':').map(Number);
        return h * 60 + m;
    }

    function fromMinutes(total) {
        const h = Math.floor(total / 60) % 24, m = total % 60;
        return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
    }

    function formatTime12(hhmm) {
        if (!hhmm) return '';
        const [h, m] = hhmm.split(':').map(Number);
        return `${h % 12 || 12}:${String(m).padStart(2,'0')} ${h >= 12 ? 'PM' : 'AM'}`;
    }

    function selectedDurationMinutes() {
        return parseInt(treatmentSelect.selectedOptions[0]?.dataset.duration, 10) || 0;
    }

    function setFieldError(input, hasError) {
        if (!input) return;
        input.classList.toggle('border-red-400', hasError);
        input.classList.toggle('bg-red-50', hasError);
        input.classList.toggle('dark:bg-red-900/20', hasError);
        input.classList.toggle('border-gray-300', !hasError);
        input.classList.toggle('dark:border-gray-600', !hasError);
        input.setAttribute('aria-invalid', hasError ? 'true' : 'false');
    }

    function showInlineMessage(node, text) {
        node.textContent = text;
        node.classList.remove('hidden');
    }

    function clearInlineMessage(node) {
        node.textContent = '';
        node.classList.add('hidden');
    }

    function showClosedDayBanner(text) {
        closedDayText.textContent = text;
        closedDayError.classList.remove('hidden');
        closedDayError.classList.add('flex');
    }

    function hideClosedDayBanner() {
        closedDayError.classList.add('hidden');
        closedDayError.classList.remove('flex');
    }

    function debounce(fn, wait) {
        let timer = null;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    function minutesBehindNow(hhmm) {
        const now = new Date();
        now.setSeconds(0, 0);
        const selected = new Date(now);
        const [hh, mm] = hhmm.split(':').map(Number);
        selected.setHours(hh, mm, 0, 0);
        return Math.round((now - selected) / 60000);
    }

    // =====================================================
    // COLLAPSIBLE SECTIONS
    // =====================================================
    function setDisclosure(toggle, panel, chevron, open) {
        panel.classList.toggle('hidden', !open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (chevron) chevron.classList.toggle('rotate-180', open);
    }

    els.noticeToggle.addEventListener('click', () => {
        const open = els.noticeToggle.getAttribute('aria-expanded') === 'true';
        setDisclosure(els.noticeToggle, els.noticeDetail, els.noticeChevron, !open);
    });

    function applySummaryMode() {
        if (desktopQuery.matches) {
            setDisclosure(els.summaryToggle, els.summaryBody, els.summaryChevron, true);
            els.summaryToggle.disabled = true;
        } else {
            els.summaryToggle.disabled = false;
            setDisclosure(els.summaryToggle, els.summaryBody, els.summaryChevron, false);
        }
    }

    els.summaryToggle.addEventListener('click', () => {
        if (desktopQuery.matches) return;
        const open = els.summaryToggle.getAttribute('aria-expanded') === 'true';
        setDisclosure(els.summaryToggle, els.summaryBody, els.summaryChevron, !open);
    });

    desktopQuery.addEventListener('change', applySummaryMode);
    applySummaryMode();

    // =====================================================
    // OPERATING HOURS FETCH
    // =====================================================
    async function updateOperatingHours() {
        if (!dateInput.value || !branchId) return;

        const day = new Date(dateInput.value + 'T00:00:00')
            .toLocaleDateString('en-US', { weekday: 'long' });

        try {
            const res = await fetch(`${OPERATING_HOURS_ENDPOINT}/${encodeURIComponent(branchId)}/${encodeURIComponent(day)}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();

            if (data.is_closed) {
                openingTime = null;
                closingTime = null;
                timeInput.value    = '';
                timeInput.disabled = true;
                timeInput.removeAttribute('min');
                timeInput.removeAttribute('max');
                showClosedDayBanner(`The branch is closed on ${day}s. Please select a different date.`);
                setFieldError(dateInput, true);
                setFieldError(timeInput, true);
                hoursHint.textContent = 'Closed';
                clearInlineMessage(timeRangeError);

                blockers.closedDay      = true;
                blockers.timeOutOfRange = false; // superseded by the closed-day rule
                syncSubmitState();
                return;
            }

            openingTime = data.opening_time?.slice(0,5) ?? null;
            closingTime = data.closing_time?.slice(0,5) ?? null;

            timeInput.disabled = false;
            if (openingTime) timeInput.min = openingTime; else timeInput.removeAttribute('min');
            if (closingTime) timeInput.max = closingTime; else timeInput.removeAttribute('max');

            hideClosedDayBanner();
            setFieldError(dateInput, false);
            hoursHint.textContent = openingTime && closingTime
                ? `${formatTime12(openingTime)} – ${formatTime12(closingTime)}`
                : '';

            blockers.closedDay = false;
            syncSubmitState();

        } catch (err) {
            console.error('Failed to fetch operating hours:', err);
            openingTime = null;
            closingTime = null;
            timeInput.disabled = false;
            timeInput.removeAttribute('min');
            timeInput.removeAttribute('max');
            hideClosedDayBanner();
            hoursHint.textContent = 'Hours unavailable';
            blockers.closedDay      = false;
            blockers.timeOutOfRange = false;
            syncSubmitState();
        }
    }

    // =====================================================
    // TIME VALIDATION (client-side)
    // =====================================================
    function validateTimeRange() {
        const time = timeInput.value;

        if (blockers.closedDay) {
            syncSubmitState();
            return false;
        }

        if (!time || !openingTime || !closingTime) {
            clearInlineMessage(timeRangeError);
            setFieldError(timeInput, false);
            blockers.timeOutOfRange = false;
            syncSubmitState();
            return true;
        }

        const fail = (message) => {
            showInlineMessage(timeRangeError, message);
            setFieldError(timeInput, true);
            blockers.timeOutOfRange = true;
            syncSubmitState();
            return false;
        };

        if (time < openingTime || time >= closingTime) {
            return fail(`Time must be within operating hours: ${formatTime12(openingTime)} – ${formatTime12(closingTime)}.`);
        }

        if (dateInput.value === todayString() && minutesBehindNow(time) > PAST_TIME_GRACE_MINUTES) {
            return fail(`This time is more than ${PAST_TIME_GRACE_MINUTES} minutes in the past. Use the current time or later.`);
        }

        const duration = selectedDurationMinutes();
        if (duration > 0) {
            const endMinutes = toMinutes(time) + duration;
            if (endMinutes > toMinutes(closingTime)) {
                return fail(
                    `This ${duration}-minute service would end at ${formatTime12(fromMinutes(endMinutes))}, ` +
                    `after closing (${formatTime12(closingTime)}). Choose an earlier start time.`
                );
            }
        }

        clearInlineMessage(timeRangeError);
        setFieldError(timeInput, false);
        blockers.timeOutOfRange = false;
        syncSubmitState();
        return true;
    }

    // =====================================================
    // WALK-IN QUICK FILL
    // =====================================================
    walkinBtn.addEventListener('click', async function () {
        dateInput.value = todayString();
        await updateOperatingHours();

        if (blockers.closedDay) {
            dateInput.focus();
            return;
        }

        let time = nowTimeString();

        // Before opening, the earliest a walk-in can start is opening time.
        if (openingTime && time < openingTime) time = openingTime;

        // After closing, there is no valid walk-in slot left today.
        if (closingTime && time >= closingTime) {
            timeInput.value = '';
            showInlineMessage(timeRangeError,
                `The branch has already closed for today (${formatTime12(closingTime)}). Pick another date.`);
            setFieldError(timeInput, true);
            blockers.timeOutOfRange = true;
            syncSubmitState();
            updateSummary();
            dateInput.focus();
            return;
        }

        timeInput.value = time;
        validateTimeRange();
        updateSummary();
        refreshAvailableTherapists();
    });

    // =====================================================
    // ADDRESS FIELD TOGGLE
    // =====================================================
    function toggleAddress() {
        const isHome = serviceType.value === 'in_home';
        addressContainer.classList.toggle('hidden', !isHome);
        addressInput.required = isHome;
    }

    function filterTreatmentOptions() {
        const isHome = serviceType.value === 'in_home';
        let clearedTreatmentName = null;

        Array.from(treatmentSelect.options).forEach(opt => {
            if (!opt.value) return; // skip the placeholder option

            const restricted = opt.dataset.serviceType === 'in_branch_only';
            const shouldDisable = isHome && restricted;

            opt.disabled = shouldDisable;
            opt.hidden = shouldDisable;

            if (shouldDisable && opt.selected) {
                clearedTreatmentName = opt.textContent.trim();
            }
        });

        if (clearedTreatmentName) {
            treatmentSelect.value = '';

            showSpaToast(
                `"${clearedTreatmentName}" is In-Branch only and was removed — not available for In Home bookings.`,
                'error'
            );

            validateTimeRange();
            refreshAvailableTherapists();
        }
    }

    // =====================================================
    // THERAPIST LIST
    // =====================================================
    let therapistController = null;
    let therapistRequestSeq = 0;

    function therapistInputsReady() {
        return Boolean(treatmentSelect.value && dateInput.value && timeInput.value);
    }

    function setTherapistPlaceholder(text, hint) {
        therapistSelect.innerHTML = '';
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = text;
        therapistSelect.appendChild(opt);
        therapistSelect.value = '';
        if (hint) showInlineMessage(therapistHint, hint); else clearInlineMessage(therapistHint);
    }

    function gateTherapistSelect() {
        therapistSelect.disabled = false; // stays enabled so `required` still fires natively
        setTherapistPlaceholder(
            'Select treatment, date and time first',
            'The therapist list is filtered by who is actually free for the chosen slot.'
        );
        blockers.noTherapist = false;
        syncSubmitState();
        updateSummary();
    }

    async function refreshAvailableTherapists(preferredId = null) {
        if (!therapistInputsReady()) {
            gateTherapistSelect();
            return;
        }

        const keepId = preferredId ?? therapistSelect.value;

        therapistController?.abort();
        therapistController = new AbortController();
        const seq = ++therapistRequestSeq;

        try {
            const params = new URLSearchParams({
                treatment:        treatmentSelect.value,
                appointment_date: dateInput.value,
                start_time:       timeInput.value,
            });

            const res = await fetch(`${AVAILABLE_THERAPISTS_ENDPOINT}?${params}`, {
                signal: therapistController.signal,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await res.json().catch(() => null);

            if (seq !== therapistRequestSeq) return; // a newer request has been issued

            if (!res.ok) {
                setTherapistPlaceholder(
                    'Unable to load therapists',
                    data?.message ?? 'Could not check therapist availability. Try changing the time.'
                );
                blockers.noTherapist = true;
                syncSubmitState();
                updateSummary();
                return;
            }

            therapistSelect.innerHTML = '';

            if (!data?.therapists?.length) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'No therapist available for this slot';
                therapistSelect.appendChild(opt);
                therapistSelect.value = '';
                showInlineMessage(therapistHint, 'No therapist is free for this slot. Try a different time.');
                blockers.noTherapist = true;
                syncSubmitState();
                updateSummary();
                return;
            }

            clearInlineMessage(therapistHint);

            data.therapists.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = t.name;
                therapistSelect.appendChild(opt);
            });

            const ids = data.therapists.map(t => String(t.id));
            if (keepId && ids.includes(String(keepId))) {
                therapistSelect.value = String(keepId);
            } else if (data.recommended_id && ids.includes(String(data.recommended_id))) {
                therapistSelect.value = String(data.recommended_id);
            }

            blockers.noTherapist = false;
            syncSubmitState();
            updateSummary();

        } catch (err) {
            if (err.name === 'AbortError') return;
            console.error('Therapist refresh failed:', err);
            setTherapistPlaceholder('Unable to load therapists', 'Could not reach the server. Check the connection and try again.');
            blockers.noTherapist = true;
            syncSubmitState();
            updateSummary();
        }
    }

    const refreshTherapistsSoon = debounce(() => refreshAvailableTherapists(), THERAPIST_DEBOUNCE_MS);

    // =====================================================
    // SUMMARY PANEL
    // =====================================================
    function summaryTimeText() {
        if (!timeInput.value) return '—';

        const fmtStart = formatTime12(timeInput.value);
        const duration = selectedDurationMinutes();

        if (duration <= 0) return `${fmtStart} (duration not set)`;

        return `${fmtStart} – ${formatTime12(fromMinutes(toMinutes(timeInput.value) + duration))}`;
    }

    function updateSummary() {
        const customer = nameInput.value.trim();
        els.summaryCustomer.textContent = customer || '—';

        els.summaryService.textContent = serviceType.value
            ? serviceType.options[serviceType.selectedIndex].text : '—';

        els.summaryTreatment.textContent = treatmentSelect.value
            ? treatmentSelect.options[treatmentSelect.selectedIndex].text : '—';

        els.summaryTherapist.textContent = therapistSelect.value
            ? therapistSelect.options[therapistSelect.selectedIndex]?.text ?? '—' : '—';

        const dateText = dateInput.value
            ? new Date(dateInput.value + 'T00:00:00')
                .toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
            : '—';
        els.summaryDate.textContent = dateText;

        const timeText = summaryTimeText();
        els.summaryTime.textContent = timeText;

        const parts = [];
        if (customer) parts.push(customer);
        if (treatmentSelect.value) parts.push(treatmentSelect.options[treatmentSelect.selectedIndex].text);
        if (dateInput.value) parts.push(dateText);
        if (timeInput.value) parts.push(timeText);
        els.summaryDigest.textContent = parts.length ? parts.join(' · ') : 'Nothing selected yet';
    }

    // =====================================================
    // EVENT WIRING
    // =====================================================
    dateInput.addEventListener('change', () => {
        updateOperatingHours().then(() => {
            validateTimeRange();
            updateSummary();
            refreshAvailableTherapists();
        });
    });

    timeInput.addEventListener('change', () => { validateTimeRange(); updateSummary(); refreshTherapistsSoon(); });
    timeInput.addEventListener('input',  () => { validateTimeRange(); updateSummary(); refreshTherapistsSoon(); });

    // Treatment affects the end-time check as well as availability.
    treatmentSelect.addEventListener('change', () => {
        validateTimeRange();
        updateSummary();
        refreshAvailableTherapists();
    });

    therapistSelect.addEventListener('change', updateSummary);
    serviceType.addEventListener('change', () => { toggleAddress(); updateSummary(); filterTreatmentOptions(); });
    nameInput.addEventListener('input', updateSummary);

    phoneInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 11);
    });

    // =====================================================
    // INIT
    // =====================================================
    toggleAddress();
    filterTreatmentOptions();

    if (dateInput.value) {
        updateOperatingHours().then(() => {
            validateTimeRange();
            refreshAvailableTherapists(restoredTherapistId);
            updateSummary();
        });
    } else {
        gateTherapistSelect();
    }

    // =====================================================
    // FORM SUBMIT GUARD
    // =====================================================
    form.addEventListener('submit', function (e) {
        const timeOk = validateTimeRange();

        if (blockers.closedDay) {
            e.preventDefault();
            dateInput.focus();
            return;
        }
        if (!timeOk) {
            e.preventDefault();
            timeInput.focus();
            return;
        }
        if (blockers.noTherapist || !therapistSelect.value) {
            e.preventDefault();
            therapistSelect.focus();
        }
    });
});
</script>
@endsection
