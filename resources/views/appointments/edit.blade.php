@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto">

    <!-- Page Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">Edit Appointment</h1>
        </div>

        <a href="{{ route('appointments.index') }}"
        class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] border border-gray-300 rounded-lg hover:bg-[#7A6348] focus:ring-4 focus:ring-[#8B7355]/50 dark:bg-[#8B7355] dark:border-gray-600 dark:hover:bg-[#7A6348]">
            Back to Appointments
        </a>
    </div>

    <!-- Edit Form -->
    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <form action="{{ route('appointments.update', $booking) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <!-- Customer Information -->
                <div class="md:col-span-2">
                    <h3 class="text-lg font-medium text-gray-800 dark:text-white">Customer Information</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update appointment details for {{ $booking->customer_name }}</p>
                    <div class="grid grid-cols-1 gap-4 mt-4 md:grid-cols-2">
                        <div>
                            <label for="customer_name" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Full Name *
                            </label>
                            <input type="text" id="customer_name" name="customer_name"
                                   value="{{ old('customer_name', $booking->customer_name) }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent"
                                   required>
                            @error('customer_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="customer_email" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Email Address *
                            </label>
                            <input type="email" id="customer_email" name="customer_email"
                                   value="{{ old('customer_email', $booking->customer_email) }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent"
                                   required>
                            @error('customer_email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="customer_phone" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Phone Number
                            </label>
                            <input type="tel" id="customer_phone" name="customer_phone"
                                   value="{{ old('customer_phone', $booking->customer_phone) }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent">
                            @error('customer_phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="customer_address" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Address
                            </label>
                            <input type="text" id="customer_address" name="customer_address"
                                   value="{{ old('customer_address', $booking->customer_address) }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent">
                            @error('customer_address')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Service Details -->
                <div class="md:col-span-2">
                    <h3 class="mb-4 text-lg font-medium text-gray-800 dark:text-white">Service Details</h3>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="service_type" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Service Type *
                            </label>
                            <select id="service_type" name="service_type"
                                    class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:text-white"
                                    required>
                                <option value="in_branch" {{ old('service_type', $booking->service_type) === 'in_branch' ? 'selected' : '' }}>
                                    In Branch
                                </option>
                                <option value="in_home" {{ old('service_type', $booking->service_type) === 'in_home' ? 'selected' : '' }}>
                                    In Home
                                </option>
                            </select>
                            @error('service_type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="treatment" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Treatment *
                            </label>
                            <select id="treatment" name="treatment"
                                    class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:text-white"
                                    required>
                                <option disabled>Select Treatment or Package</option>

                                @foreach($treatments as $t)
                                    <option value="treatment_{{ $t->id }}"
                                        {{ old('treatment', $booking->treatment) === "treatment_{$t->id}" ? 'selected' : '' }}>
                                        Treatment: {{ $t->name }} ({{ $t->duration }} mins)
                                    </option>
                                @endforeach

                                @foreach($packages as $p)
                                    <option value="package_{{ $p->id }}"
                                        {{ old('treatment', $booking->treatment) === "package_{$p->id}" ? 'selected' : '' }}>
                                        Package: {{ $p->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('treatment')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="therapist_id" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Therapist *
                            </label>
                            <select id="therapist_id" name="therapist_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent"
                                    required>
                                <option value="">Select Therapist</option>
                                @foreach($therapists as $therapist)
                                    <option value="{{ $therapist->id }}"
                                            {{ old('therapist_id', $booking->therapist_id) == $therapist->id ? 'selected' : '' }}>
                                        {{ $therapist->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('therapist_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Appointment Details -->
                <div class="md:col-span-2">
                    <h3 class="mb-4 text-lg font-medium text-gray-800 dark:text-white">Schedule Details</h3>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="appointment_date" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Date *
                            </label>
                            <input type="date" id="appointment_date" name="appointment_date"
                                value="{{ old('appointment_date', $booking->appointment_date->format('Y-m-d')) }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent"
                                required>
                            @error('appointment_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="start_time" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Start Time *
                            </label>
                            <input
                                type="time"
                                id="start_time"
                                name="start_time"
                                value="{{ old('start_time', optional($booking->start_time)->format('H:i')) }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                    dark:bg-gray-700 dark:border-gray-600 dark:text-white
                                    focus:ring-2 focus:ring-[#8B7355] focus:border-transparent"
                                required
                            >
                            @error('start_time')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="end_time" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                End Time
                            </label>
                            <input
                                type="time"
                                id="end_time"
                                value="{{ optional($booking->end_time)->format('H:i') }}"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg
                                    bg-gray-100 dark:bg-gray-600 dark:border-gray-500
                                    dark:text-gray-200 cursor-not-allowed"
                                disabled
                            >
                            <p class="mt-1 text-xs text-gray-500">
                                Automatically calculated based on treatment duration
                            </p>
                        </div>

                        <div>
                            <label for="status" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Status *
                            </label>
                            <select id="status" name="status"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-2 focus:ring-[#8B7355] focus:border-transparent"
                                    required>
                                <option value="reserved" {{ old('status', $booking->status) == 'reserved' ? 'selected' : '' }}>Reserved</option>
                                <option value="confirmed" {{ old('status', $booking->status) == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                                <option value="ongoing" {{ old('status', $booking->status) == 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                                <option value="completed" {{ old('status', $booking->status) == 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="cancelled" {{ old('status', $booking->status) == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                            @error('status')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-end gap-3 pt-6 mt-8 border-t border-gray-200 dark:border-gray-700">
                <a href="{{ route('appointments.index') }}"
                   class="px-6 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:hover:bg-gray-600">
                    Cancel
                </a>
                <button type="submit"
                        class="px-6 py-3 text-sm font-medium text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348] focus:ring-4 focus:ring-[#8B7355]/50">
                    Update Appointment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Set minimum date to today
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        const dateInput = document.getElementById('appointment_date');
        if (dateInput) {
            dateInput.min = today;
        }
    });
</script>

@endsection
