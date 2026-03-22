@extends('layouts.app')

@section('content')
@php
    $canEdit = auth()->user()->can('edit appointments');
    $canDelete = auth()->user()->can('delete appointments');
    $showActions = $canEdit || $canDelete;
@endphp

<div class="mx-auto max-w-7xl">

    <div class="p-6">
        <x-page-header
            title="Appointments"
            subtitle="Manage all customer appointments and bookings."
        />
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

                    @if($showActions)
                        <th class="px-6 py-3 text-xs font-medium text-center text-gray-500 uppercase">Actions</th>
                    @endif
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

                        @if($showActions)
                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center gap-2">
                                    @can('edit appointments')
                                        <button
                                            type="button"
                                            onclick="openEditModal(this)"
                                            data-id="{{ $booking->id }}"
                                            data-customer-name="{{ $booking->customer_name }}"
                                            data-customer-email="{{ $booking->customer_email }}"
                                            data-service-type="{{ $booking->service_type }}"
                                            data-treatment="{{ $booking->treatment }}"
                                            data-therapist-id="{{ $booking->therapist_id }}"
                                            data-branch-id="{{ $booking->branch_id }}"
                                            data-appointment-date="{{ $booking->appointment_date?->format('Y-m-d') }}"
                                            data-start-time="{{ $booking->start_time }}"
                                            data-status="{{ $booking->status }}"
                                            class="px-3 py-1 text-sm text-white bg-yellow-500 rounded hover:bg-yellow-600">
                                            Edit
                                        </button>
                                    @endcan

                                    @can('delete appointments')
                                        <button onclick="openDeleteModal({{ $booking->id }})"
                                                class="px-3 py-1 text-sm text-white bg-red-600 rounded hover:bg-red-700">
                                            Delete
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        @can('edit appointments')
            @php
                $allTreatments = \App\Models\Treatment::orderBy('name')->get();
                $allPackages   = \App\Models\Package::orderBy('name')->get();
            @endphp

            <!-- EDIT MODAL -->
            <div id="editModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50">
                <div class="w-full max-w-lg p-6 mx-auto mt-16 bg-white rounded-lg dark:bg-gray-800">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Edit Appointment</h2>
                        <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form id="editForm" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Customer Name</label>
                                <input type="text" id="edit_customer_name" name="customer_name"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-yellow-500">
                            </div>

                            <div class="col-span-2">
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Customer Email</label>
                                <input type="email" id="edit_customer_email" name="customer_email"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-yellow-500">
                            </div>

                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Service Type</label>
                                <select id="edit_service_type" name="service_type"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                    <option value="in_branch">In Branch</option>
                                    <option value="in_home">In Home</option>
                                </select>
                            </div>

                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Treatment</label>
                                <select id="edit_treatment" name="treatment"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                    @if($allTreatments->isNotEmpty())
                                        <optgroup label="Treatments">
                                            @foreach($allTreatments as $treatment)
                                                <option value="treatment_{{ $treatment->id }}">{{ $treatment->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                    @if($allPackages->isNotEmpty())
                                        <optgroup label="Packages">
                                            @foreach($allPackages as $package)
                                                <option value="package_{{ $package->id }}">{{ $package->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                </select>
                            </div>

                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Therapist</label>
                                <select id="edit_therapist_id" name="therapist_id"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                    <option value="">— No Therapist Assigned —</option>
                                    @foreach(\App\Models\Staff::with('user')->get() as $staff)
                                        @if($staff->user && $staff->user->hasRole('therapist'))
                                            <option value="{{ $staff->user_id }}" data-branch="{{ $staff->branch_id }}">
                                                {{ $staff->user->name }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                                <select id="edit_status" name="status"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                    <option value="reserved">Reserved</option>
                                    <option value="pending">Pending</option>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>

                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Appointment Date</label>
                                <input type="date" id="edit_appointment_date" name="appointment_date"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-yellow-500">
                            </div>

                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Start Time</label>
                                <input type="time" id="edit_start_time" name="start_time"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-yellow-500">
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 mt-6">
                            <button type="button" onclick="closeEditModal()"
                                class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-md hover:bg-[#7A6348]">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan

        @can('delete appointments')
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
        @endcan

        <!-- PAGINATION -->
        <div class="px-4 py-3 bg-white border-t border-gray-200 dark:bg-gray-800 dark:border-gray-700">
            {{ $bookings->links() }}
        </div>
    </div>
</div>

<script>
    @can('edit appointments')
    function openEditModal(btn) {
        const d = btn.dataset;

        document.getElementById('edit_customer_name').value  = d.customerName  || '';
        document.getElementById('edit_customer_email').value = d.customerEmail || '';
        document.getElementById('edit_service_type').value   = d.serviceType   || '';
        document.getElementById('edit_treatment').value      = d.treatment     || '';
        document.getElementById('edit_start_time').value     = d.startTime     || '';
        document.getElementById('edit_status').value         = d.status        || 'pending';

        // Show assigned date, block past dates from being selected
        const dateInput = document.getElementById('edit_appointment_date');
        dateInput.min   = new Date().toISOString().split('T')[0];
        dateInput.value = d.appointmentDate || '';

        // Filter therapists to booking's branch only
        const therapistSelect = document.getElementById('edit_therapist_id');
        Array.from(therapistSelect.options).forEach(option => {
            if (option.value === '') return;
            option.hidden = option.dataset.branch != d.branchId;
        });
        therapistSelect.value = d.therapistId || '';

        document.getElementById('editForm').action = '/appointments/' + d.id;
        document.getElementById('editModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
    @endcan

    @can('delete appointments')
    function openDeleteModal(id) {
        document.getElementById('deleteForm').action = '/appointments/' + id;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }
    @endcan
</script>
@endsection
