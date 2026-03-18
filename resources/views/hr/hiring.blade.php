@extends('layouts.app')
@section('content')
<div class="p-6 mx-auto max-w-7xl">

    <x-page-header title="Hiring" subtitle="Record and manage applicant applications."/>

    {{-- APPLICATION FORM --}}
    @can('manage hiring')
    <div class="p-6 mt-6 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">

        <div class="flex items-center gap-3 pb-4 mb-6 border-b border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-[#F6EFE6]">
                <i class="fa-solid fa-file-pen text-[#8B7355]"></i>
            </div>
            <div>
                <h2 class="text-base font-semibold text-gray-800 dark:text-white">Applicant Application Form</h2>
                <p class="text-xs text-gray-500">Fill out the form for walk-in applicants</p>
            </div>
        </div>

        <form action="{{ route('hr.hiring.store') }}" method="POST">
            @csrf

            {{-- ROW 1: Personal Information --}}
            <div class="mb-6">
                <h3 class="flex items-center gap-2 mb-4 text-xs font-bold tracking-widest text-[#8B7355] uppercase">
                    <i class="fa-solid fa-user"></i> Personal Information
                </h3>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">

                    <div class="lg:col-span-2">
                        <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="full_name" required
                            value="{{ old('full_name') }}"
                            placeholder="full name"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"/>
                        @error('full_name')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                            Gender <span class="text-red-500">*</span>
                        </label>
                        <select name="gender" required
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="">Select</option>
                            <option value="male"   {{ old('gender') == 'male'   ? 'selected' : '' }}>Male</option>
                            <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>Female</option>
                            <option value="other"  {{ old('gender') == 'other'  ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('gender')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                            Date of Birth <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="date_of_birth" required
                            value="{{ old('date_of_birth') }}"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"/>
                        @error('date_of_birth')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                            Civil Status
                        </label>
                        <select name="civil_status"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="">Select</option>
                            <option value="single"    {{ old('civil_status') == 'single'    ? 'selected' : '' }}>Single</option>
                            <option value="married"   {{ old('civil_status') == 'married'   ? 'selected' : '' }}>Married</option>
                            <option value="widowed"   {{ old('civil_status') == 'widowed'   ? 'selected' : '' }}>Widowed</option>
                            <option value="separated" {{ old('civil_status') == 'separated' ? 'selected' : '' }}>Separated</option>
                        </select>
                    </div>

                    <div>
                        <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                            Phone <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="phone" required
                            value="{{ old('phone') }}"
                            placeholder="09xxxxxxxxx"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"/>
                        @error('phone')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="col-span-2 md:col-span-3 lg:col-span-4">
                        <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <input type="email" name="email" required
                            value="{{ old('email') }}"
                            placeholder="user@email.com"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"/>
                        @error('email')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="col-span-2 md:col-span-3 lg:col-span-2">
                        <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                            Address <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="address" required
                            value="{{ old('address') }}"
                            placeholder="Street, Barangay, City"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"/>
                        @error('address')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>

            <hr class="mb-6 border-gray-100 dark:border-gray-700">

            {{-- ROW 2: Position + Background --}}
            <div class="grid grid-cols-1 gap-6 mb-6 md:grid-cols-2">

                {{-- Position Details --}}
                <div>
                    <h3 class="flex items-center gap-2 mb-4 text-xs font-bold tracking-widest text-[#8B7355] uppercase">
                        <i class="fa-solid fa-briefcase"></i> Position Details
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                Position <span class="text-red-500">*</span>
                            </label>
                            <select name="role" required
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="">Select</option>
                                <option value="therapist"    {{ old('role') == 'therapist'    ? 'selected' : '' }}>Therapist</option>
                                <option value="receptionist" {{ old('role') == 'receptionist' ? 'selected' : '' }}>Receptionist</option>
                                <option value="manager"      {{ old('role') == 'manager'      ? 'selected' : '' }}>Manager</option>
                                <option value="hr"           {{ old('role') == 'hr'           ? 'selected' : '' }}>HR</option>
                                <option value="finance"      {{ old('role') == 'finance'      ? 'selected' : '' }}>Finance</option>
                            </select>
                            @error('role')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                Expected Start Date
                            </label>
                            <input type="date" name="expected_start_date"
                                value="{{ old('expected_start_date') }}"
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"/>
                        </div>

                        <div>
                            <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                Educational Attainment
                            </label>
                            <select name="education"
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="">Select</option>
                                <option value="high_school"   {{ old('education') == 'high_school'   ? 'selected' : '' }}>High School</option>
                                <option value="vocational"    {{ old('education') == 'vocational'    ? 'selected' : '' }}>Vocational</option>
                                <option value="undergraduate" {{ old('education') == 'undergraduate' ? 'selected' : '' }}>Undergraduate</option>
                                <option value="college"       {{ old('education') == 'college'       ? 'selected' : '' }}>College Graduate</option>
                                <option value="postgrad"      {{ old('education') == 'postgrad'      ? 'selected' : '' }}>Post Graduate</option>
                            </select>
                        </div>

                        <div class="col-span-2">
                            <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                Skills / Certifications
                            </label>
                            <input type="text" name="skills"
                                value="{{ old('skills') }}"
                                placeholder="e.g. Swedish Massage, NC II..."
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"/>
                        </div>

                        <div class="col-span-2">
                            <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                Work Experience
                            </label>
                            <textarea name="work_experience" rows="3"
                                placeholder="Previous work experience..."
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">{{ old('work_experience') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Emergency Contact + Notes --}}
                <div>
                    <h3 class="flex items-center gap-2 mb-4 text-xs font-bold tracking-widest text-[#8B7355] uppercase">
                        <i class="fa-solid fa-phone-volume"></i> Emergency Contact
                    </h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">Contact Person</label>
                            <input type="text" name="emergency_contact_name"
                                value="{{ old('emergency_contact_name') }}"
                                placeholder="Full name"
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"/>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">Relationship</label>
                                <input type="text" name="emergency_contact_relation"
                                    value="{{ old('emergency_contact_relation') }}"
                                    placeholder="e.g. Mother"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"/>
                            </div>
                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">Contact Number</label>
                                <input type="text" name="emergency_contact_phone"
                                    value="{{ old('emergency_contact_phone') }}"
                                    placeholder="09xxxxxxxxx"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"/>
                            </div>
                        </div>

                        <hr class="border-gray-100 dark:border-gray-700">

                        <h3 class="flex items-center gap-2 pt-1 text-xs font-bold tracking-widest text-[#8B7355] uppercase">
                            <i class="fa-solid fa-note-sticky"></i> Notes / Remarks
                        </h3>
                        <textarea name="notes" rows="5"
                            placeholder="Additional notes about the applicant..."
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">{{ old('notes') }}</textarea>
                    </div>
                </div>

            </div>

            <div class="flex justify-end pt-2 border-t border-gray-100 dark:border-gray-700">
                <button type="reset"
                    class="px-5 py-2.5 mr-3 text-sm font-semibold text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition">
                    <i class="mr-2 fa-solid fa-rotate-left"></i> Clear
                </button>
                <button type="submit"
                    class="px-6 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-xl hover:opacity-90 transition shadow-sm hover:shadow-md active:translate-y-0.5">
                    <i class="mr-2 fa-solid fa-paper-plane"></i> Submit Application
                </button>
            </div>
        </form>
    </div>
    @endcan

    {{-- APPLICANTS LIST --}}
    <div class="mt-6 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">

        <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-800 dark:text-white">
                Applicants
                <span class="ml-2 px-2 py-0.5 text-xs font-semibold bg-[#F6EFE6] text-[#8B7355] rounded-full">
                    {{ $applicants->count() }}
                </span>
            </h2>

            {{-- Status Filter --}}
            <div class="flex flex-wrap gap-2">
                @foreach(['all' => 'All', 'pending' => 'Pending', 'interview' => 'Interview', 'approved' => 'Approved', 'hired' => 'Hired', 'rejected' => 'Rejected'] as $val => $label)
                <button onclick="filterStatus('{{ $val }}')" id="filter-{{ $val }}"
                    class="px-3 py-1 text-xs font-semibold rounded-full border transition
                    {{ $val === 'all' ? 'bg-[#8B7355] text-white border-[#8B7355]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#8B7355] hover:text-[#8B7355] dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600' }}">
                    {{ $label }}
                </button>
                @endforeach
            </div>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" id="applicantsList">
                @forelse($applicants as $applicant)
                <div class="flex flex-col p-4 border border-gray-100 applicant-card rounded-xl bg-gray-50 dark:bg-gray-900 dark:border-gray-700"
                    data-status="{{ $applicant->status }}">

                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-full bg-[#8B7355] text-white font-semibold text-sm">
                                {{ strtoupper(substr($applicant->full_name, 0, 1)) }}
                            </div>
                            <div>
                                <p class="text-sm font-semibold leading-tight text-gray-800 dark:text-white">
                                    {{ $applicant->full_name }}
                                </p>
                                <p class="text-xs text-gray-500">{{ $applicant->email }}</p>
                            </div>
                        </div>
                        <span class="flex-shrink-0 px-2 py-1 text-xs font-semibold rounded-full
                            {{ $applicant->status === 'pending'   ? 'bg-yellow-100 text-yellow-700'  :
                              ($applicant->status === 'interview' ? 'bg-blue-100 text-blue-700'      :
                              ($applicant->status === 'approved'  ? 'bg-green-100 text-green-700'    :
                              ($applicant->status === 'hired'     ? 'bg-teal-100 text-teal-700'      :
                               'bg-red-100 text-red-700'))) }}">
                            {{ ucfirst($applicant->status) }}
                        </span>
                    </div>

                    {{-- Details --}}
                    <div class="grid grid-cols-2 gap-1 mt-3 text-xs text-gray-500">
                        <div>
                            <i class="mr-1 fa-solid fa-briefcase text-[#8B7355]"></i>
                            {{ ucfirst($applicant->role ?? 'N/A') }}
                        </div>
                        <div>
                            <i class="mr-1 fa-solid fa-phone text-[#8B7355]"></i>
                            {{ $applicant->phone ?? 'N/A' }}
                        </div>
                        @if($applicant->education)
                        <div>
                            <i class="mr-1 fa-solid fa-graduation-cap text-[#8B7355]"></i>
                            {{ ucwords(str_replace('_', ' ', $applicant->education)) }}
                        </div>
                        @endif
                        @if($applicant->address)
                        <div class="col-span-2 truncate">
                            <i class="mr-1 fa-solid fa-location-dot text-[#8B7355]"></i>
                            {{ $applicant->address }}
                        </div>
                        @endif
                        @if($applicant->skills)
                        <div class="col-span-2 truncate">
                            <i class="mr-1 fa-solid fa-star text-[#8B7355]"></i>
                            {{ $applicant->skills }}
                        </div>
                        @endif
                    </div>

                    <p class="mt-2 text-[10px] text-gray-400">
                        Applied: {{ $applicant->created_at->format('M d, Y') }}
                    </p>

                    {{-- Action --}}
                    @if($applicant->status === 'pending')
                    @can('manage hiring')
                    <div class="pt-3 mt-auto border-t border-gray-100 dark:border-gray-700">
                        <button onclick="openScheduleModal({{ $applicant->id }}, '{{ addslashes($applicant->full_name) }}')"
                            class="w-full px-3 py-2 text-xs font-semibold text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348] transition">
                            <i class="mr-1 fa-solid fa-calendar-plus"></i> Schedule Interview
                        </button>
                    </div>
                    @endcan
                    @endif
                </div>
                @empty
                <div class="py-12 text-center col-span-full">
                    <i class="mb-3 text-4xl text-gray-200 fa-solid fa-users"></i>
                    <p class="text-sm text-gray-400">No applicants yet.</p>
                    <p class="text-xs text-gray-300">Fill out the form above to add an applicant.</p>
                </div>
                @endforelse
            </div>
        </div>

    </div>
</div>

{{-- Schedule Interview Modal --}}
<div id="scheduleModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50">
    <div class="w-full max-w-md p-6 mx-auto mt-24 bg-white shadow-xl rounded-xl dark:bg-gray-800">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Schedule Interview</h2>
            <button onclick="closeScheduleModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <p id="scheduleApplicantName" class="mb-4 text-sm font-medium text-[#8B7355]"></p>
        <form id="scheduleForm" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block mb-1 text-xs font-semibold text-gray-600">Interview Date *</label>
                <input type="date" name="interview_date" required
                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:outline-none"/>
            </div>
            <div>
                <label class="block mb-1 text-xs font-semibold text-gray-600">Interview Time *</label>
                <input type="time" name="interview_time" required
                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:outline-none"/>
            </div>
            <div>
                <label class="block mb-1 text-xs font-semibold text-gray-600">Remarks</label>
                <textarea name="remarks" rows="2" placeholder="Optional notes..."
                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:outline-none"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeScheduleModal()"
                    class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
                <button type="submit"
                    class="px-4 py-2 text-sm font-semibold text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348]">
                    <i class="mr-1 fa-solid fa-calendar-check"></i> Schedule
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const baseHiringUrl = @json(url('/hr/applications'));

function openScheduleModal(applicantId, name) {
    document.getElementById('scheduleApplicantName').textContent = 'Applicant: ' + name;
    document.getElementById('scheduleForm').action = `${baseHiringUrl}/${applicantId}/schedule-interview`;
    document.getElementById('scheduleModal').classList.remove('hidden');
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').classList.add('hidden');
}

function filterStatus(status) {
    document.querySelectorAll('.applicant-card').forEach(card => {
        card.style.display = (status === 'all' || card.dataset.status === status) ? 'flex' : 'none';
    });

    document.querySelectorAll('[id^="filter-"]').forEach(btn => {
        btn.classList.remove('bg-[#8B7355]', 'text-white', 'border-[#8B7355]');
        btn.classList.add('bg-white', 'text-gray-600', 'border-gray-200');
    });

    const active = document.getElementById('filter-' + status);
    if (active) {
        active.classList.add('bg-[#8B7355]', 'text-white', 'border-[#8B7355]');
        active.classList.remove('bg-white', 'text-gray-600', 'border-gray-200');
    }
}
</script>
@endsection
