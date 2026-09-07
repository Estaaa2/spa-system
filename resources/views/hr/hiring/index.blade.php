@extends('layouts.app')

@section('title', 'Hiring')

@section('content')
@php
    $user = auth()->user();

    $canCreateHiring = $user?->hasBranchPermission('create hiring') ?? false;
@endphp

<div class="p-6 mx-auto max-w-7xl">

    <x-page-header
        title="Hiring"
        subtitle="Record walk-in or manually submitted applicants for this branch."
    />

    @if ($canCreateHiring)

        <div class="p-6 mt-6 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">

            {{-- HEADER --}}
            <div class="flex items-center gap-3 pb-4 mb-6 border-b border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-[#F6EFE6] dark:bg-gray-700">
                    <i class="fa-solid fa-file-pen text-[#8B7355] dark:text-[#C4A97D]"></i>
                </div>

                <div>
                    <h2 class="text-base font-semibold text-gray-800 dark:text-white">
                        Applicant Application Form
                    </h2>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Fill out the form for walk-in or manually encoded applicants.
                    </p>
                </div>
            </div>

            <form
                action="{{ route('hiring.store') }}"
                method="POST"
                enctype="multipart/form-data"
            >
                @csrf

                {{-- ================= PERSONAL INFORMATION ================= --}}
                <div class="mb-6">

                    <h3 class="flex items-center gap-2 mb-4 text-xs font-bold tracking-widest text-[#8B7355] dark:text-[#C4A97D] uppercase">
                        <i class="fa-solid fa-user"></i>
                        Personal Information
                    </h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-6">

                        {{-- Full Name --}}
                        <div class="lg:col-span-2">
                            <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                Full Name
                                <span class="text-red-500">*</span>
                            </label>

                            <input
                                type="text"
                                name="full_name"
                                required
                                value="{{ old('full_name') }}"
                                placeholder="Full name"
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                                       focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none
                                       dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            >

                            @error('full_name', 'application')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Email --}}
                        <div class="lg:col-span-2">
                            <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                Email
                                <span class="text-red-500">*</span>
                            </label>

                            <input
                                type="email"
                                name="email"
                                required
                                value="{{ old('email') }}"
                                placeholder="user@email.com"
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                                       focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none
                                       dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            >

                            @error('email', 'application')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Phone --}}
                        <div>
                            <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                Phone
                                <span class="text-red-500">*</span>
                            </label>

                            <input
                                type="text"
                                name="phone"
                                required
                                maxlength="11"
                                inputmode="numeric"
                                pattern="^09\d{9}$"
                                value="{{ old('phone') }}"
                                placeholder="09xxxxxxxxx"
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                                       focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none
                                       dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            >

                            @error('phone', 'application')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Gender --}}
                        <div>
                            <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                Gender
                            </label>

                            <select
                                name="gender"
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                                       focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none
                                       dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            >
                                <option value="">Select</option>

                                <option value="male" {{ old('gender') === 'male' ? 'selected' : '' }}>
                                    Male
                                </option>

                                <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>
                                    Female
                                </option>

                                <option value="other" {{ old('gender') === 'other' ? 'selected' : '' }}>
                                    Other
                                </option>
                            </select>

                            @error('gender', 'application')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Date of Birth --}}
                        <div>
                            <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                Date of Birth
                            </label>

                            <input
                                type="date"
                                name="date_of_birth"
                                value="{{ old('date_of_birth') }}"
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                                       focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none
                                       dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            >

                            @error('date_of_birth', 'application')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Civil Status --}}
                        <div>
                            <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                Civil Status
                            </label>

                            <select
                                name="civil_status"
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                                       focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none
                                       dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            >
                                <option value="">Select</option>

                                <option value="single" {{ old('civil_status') === 'single' ? 'selected' : '' }}>
                                    Single
                                </option>

                                <option value="married" {{ old('civil_status') === 'married' ? 'selected' : '' }}>
                                    Married
                                </option>

                                <option value="widowed" {{ old('civil_status') === 'widowed' ? 'selected' : '' }}>
                                    Widowed
                                </option>

                                <option value="separated" {{ old('civil_status') === 'separated' ? 'selected' : '' }}>
                                    Separated
                                </option>
                            </select>

                            @error('civil_status', 'application')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Address --}}
                        <div class="lg:col-span-3">
                            <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                Address
                                <span class="text-red-500">*</span>
                            </label>

                            <input
                                type="text"
                                name="address"
                                required
                                value="{{ old('address') }}"
                                placeholder="Street, Barangay, City"
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                                       focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none
                                       dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            >

                            @error('address', 'application')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                </div>

                <hr class="mb-6 border-gray-100 dark:border-gray-700">

                {{-- ================= POSITION + BACKGROUND ================= --}}
                <div class="grid grid-cols-1 gap-6 mb-6 md:grid-cols-2">

                    {{-- POSITION DETAILS --}}
                    <div>

                        <h3 class="flex items-center gap-2 mb-4 text-xs font-bold tracking-widest text-[#8B7355] dark:text-[#C4A97D] uppercase">
                            <i class="fa-solid fa-briefcase"></i>
                            Position Details
                        </h3>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                            {{-- Applying For --}}
                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                    Applying For
                                    <span class="text-red-500">*</span>
                                </label>

                                <select
                                    name="position_applied"
                                    required
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                                           focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none
                                           dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                >
                                    <option value="">Select</option>

                                    <option value="therapist" {{ old('position_applied') === 'therapist' ? 'selected' : '' }}>
                                        Therapist
                                    </option>

                                    <option value="receptionist" {{ old('position_applied') === 'receptionist' ? 'selected' : '' }}>
                                        Receptionist
                                    </option>

                                    <option value="manager" {{ old('position_applied') === 'manager' ? 'selected' : '' }}>
                                        Manager
                                    </option>

                                    <option value="hr" {{ old('position_applied') === 'hr' ? 'selected' : '' }}>
                                        HR
                                    </option>

                                    <option value="finance" {{ old('position_applied') === 'finance' ? 'selected' : '' }}>
                                        Finance
                                    </option>
                                </select>

                                @error('position_applied', 'application')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Availability --}}
                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                    Shift Availability
                                </label>

                                <select
                                    name="availability"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                                           focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none
                                           dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                >
                                    <option value="">Select</option>

                                    <option value="full_time" {{ old('availability') === 'full_time' ? 'selected' : '' }}>
                                        Full Time
                                    </option>

                                    <option value="part_time" {{ old('availability') === 'part_time' ? 'selected' : '' }}>
                                        Part Time
                                    </option>

                                    <option value="weekdays" {{ old('availability') === 'weekdays' ? 'selected' : '' }}>
                                        Weekdays Only
                                    </option>

                                    <option value="weekends" {{ old('availability') === 'weekends' ? 'selected' : '' }}>
                                        Weekends Only
                                    </option>

                                    <option value="shifting" {{ old('availability') === 'shifting' ? 'selected' : '' }}>
                                        Shifting
                                    </option>

                                    <option value="flexible" {{ old('availability') === 'flexible' ? 'selected' : '' }}>
                                        Flexible
                                    </option>
                                </select>

                                @error('availability', 'application')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Start Date --}}
                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                    Expected Start Date
                                </label>

                                <input
                                    type="date"
                                    name="expected_start_date"
                                    value="{{ old('expected_start_date') }}"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                                           focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none
                                           dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                >

                                @error('expected_start_date', 'application')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Education --}}
                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                    Educational Attainment
                                </label>

                                <select
                                    name="education"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                                           focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none
                                           dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                >
                                    <option value="">Select</option>

                                    <option value="high_school" {{ old('education') === 'high_school' ? 'selected' : '' }}>
                                        High School
                                    </option>

                                    <option value="vocational" {{ old('education') === 'vocational' ? 'selected' : '' }}>
                                        Vocational
                                    </option>

                                    <option value="undergraduate" {{ old('education') === 'undergraduate' ? 'selected' : '' }}>
                                        Undergraduate
                                    </option>

                                    <option value="college" {{ old('education') === 'college' ? 'selected' : '' }}>
                                        College Graduate
                                    </option>

                                    <option value="postgrad" {{ old('education') === 'postgrad' ? 'selected' : '' }}>
                                        Post Graduate
                                    </option>
                                </select>

                                @error('education', 'application')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Resume --}}
                            <div class="md:col-span-2">

                                <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                    Resume / CV
                                    <span class="text-xs font-normal text-gray-400">
                                        (PDF only, max 5MB)
                                    </span>
                                </label>

                                <input
                                    type="file"
                                    name="resume"
                                    accept="application/pdf,.pdf"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                                           focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none
                                           dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                >

                                <p class="mt-1 text-[11px] text-gray-400">
                                    PDF is recommended so HR can preview the résumé directly in the browser.
                                </p>

                                @error('resume', 'application')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                        </div>
                    </div>

                    {{-- ================= EMERGENCY CONTACT ================= --}}
                    <div>

                        <h3 class="flex items-center gap-2 mb-4 text-xs font-bold tracking-widest text-[#8B7355] dark:text-[#C4A97D] uppercase">
                            <i class="fa-solid fa-phone-volume"></i>
                            Emergency Contact & Notes
                        </h3>

                        <div class="space-y-3">

                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                    Contact Person
                                </label>

                                <input
                                    type="text"
                                    name="emergency_contact_name"
                                    value="{{ old('emergency_contact_name') }}"
                                    placeholder="Full name"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                                           focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none
                                           dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                >

                                @error('emergency_contact_name', 'application')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-2 gap-3">

                                <div>
                                    <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                        Relationship
                                    </label>

                                    <input
                                        type="text"
                                        name="emergency_contact_relation"
                                        value="{{ old('emergency_contact_relation') }}"
                                        placeholder="e.g. Mother"
                                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                                               focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none
                                               dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    >
                                </div>

                                <div>
                                    <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                        Contact Number
                                    </label>

                                    <input
                                        type="text"
                                        name="emergency_contact_phone"
                                        maxlength="11"
                                        inputmode="numeric"
                                        value="{{ old('emergency_contact_phone') }}"
                                        placeholder="09xxxxxxxxx"
                                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                                               focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none
                                               dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    >
                                </div>

                            </div>

                            <hr class="border-gray-100 dark:border-gray-700">

                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                    Notes / Remarks
                                </label>

                                <textarea
                                    name="notes"
                                    rows="10"
                                    placeholder="Additional notes about the applicant..."
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                                           focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none
                                           dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                >{{ old('notes') }}</textarea>

                                @error('notes', 'application')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                        </div>
                    </div>

                </div>

                {{-- BUTTONS --}}
                <div class="flex justify-end pt-4 border-t border-gray-100 dark:border-gray-700">

                    <button
                        type="reset"
                        class="px-5 py-2.5 mr-3 text-sm font-semibold text-gray-600 transition
                               bg-gray-100 rounded-xl hover:bg-gray-200
                               dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                        <i class="mr-2 fa-solid fa-rotate-left"></i>
                        Clear
                    </button>

                    <button
                        type="submit"
                        class="px-6 py-2.5 text-sm font-semibold text-white
                               bg-gradient-to-r from-[#8B7355] to-[#6F5430]
                               rounded-xl hover:opacity-90 transition shadow-sm
                               hover:shadow-md active:translate-y-0.5"
                    >
                        <i class="mr-2 fa-solid fa-paper-plane"></i>
                        Submit Application
                    </button>

                </div>

            </form>

        </div>

    @else

        <div class="p-8 mt-6 text-center bg-white border border-gray-200 rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <i class="text-3xl text-gray-300 fa-solid fa-lock"></i>

            <p class="mt-3 text-sm font-semibold text-gray-600 dark:text-gray-300">
                You don't have permission to create applicants.
            </p>
        </div>

    @endif

</div>
@endsection
