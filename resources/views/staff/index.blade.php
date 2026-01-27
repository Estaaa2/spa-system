@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl">

    <!-- Staff Header -->
    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">Staff Management</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage your spa therapists and staff members</p>
        </div>

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

    <!-- Add Staff Form -->
    <div class="mb-6">
        <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">Add New Staff Member</h2>
            <form action="{{ route('staff.store') }}" method="POST" id="addStaffForm">
                @csrf
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <!-- Email -->
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Email *</label>
                        <input type="email" name="email" required
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#8B7355] focus:border-[#8B7355] block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-[#8B7355] dark:focus:border-[#8B7355]"
                            placeholder="staff@example.com" value="{{ old('email') }}">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Name -->
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Full Name *</label>
                        <input type="text" name="name" required
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#8B7355] focus:border-[#8B7355] block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-[#8B7355] dark:focus:border-[#8B7355]"
                               placeholder="John Doe" value="{{ old('name') }}">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Role -->
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Role *</label>
                        <select name="roles" required
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#8B7355] focus:border-[#8B7355] block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-[#8B7355] dark:focus:border-[#8B7355]">
                            <option value="">Select Role</option>
                            <option value="therapist" {{ old('roles') == 'therapist' ? 'selected' : '' }}>Therapist</option>
                            <option value="receptionist" {{ old('roles') == 'receptionist' ? 'selected' : '' }}>Receptionist</option>
                            <option value="manager" {{ old('roles') == 'manager' ? 'selected' : '' }}>Manager</option>
                        </select>
                        @error('roles')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end mt-6">
                    <button type="submit"
                            class="px-4 py-2.5 text-sm font-medium text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348] focus:ring-4 focus:outline-none focus:ring-[#8B7355]/50 dark:bg-[#8B7355] dark:hover:bg-[#7A6348] dark:focus:ring-[#8B7355]/50">
                        Add Staff Member
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Staff List -->
    <div>
        <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Staff Members</h2>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $staff->count() }} staff member(s)
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Staff Member</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Role</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Branch</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @forelse($staff as $member)
                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-[#8B7355] flex items-center justify-center text-white">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $member->user->name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $member->user->email ?? 'No email' }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $role = $member->user?->getRoleNames()->first();
                                @endphp

                                @if($role)
                                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                        {{ ucfirst($role) }}
                                    </span>
                                @else
                                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-gray-200 text-gray-500">
                                        No role
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($member->branch)
                                    <div class="text-sm text-gray-800 dark:text-white">
                                        {{ $member->branch->name }}
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $member->branch->location }}
                                        </p>
                                    </div>
                                @else
                                    <span class="text-sm text-gray-400 dark:text-gray-500">No branch assigned</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <!-- Edit Button -->
                                    <button onclick="editStaff({{ $member->id }})"
                                            class="p-2 text-gray-600 transition-colors bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600">
                                        <i class="w-4 h-4 fas fa-edit"></i>
                                    </button>

                                    <!-- Delete Form -->
                                    <form action="{{ route('staff.destroy', $member->id) }}" method="POST"
                                          onsubmit="return confirm('Delete this staff member?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="p-2 text-red-600 transition-colors bg-red-100 rounded-lg hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50">
                                            <i class="w-4 h-4 fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="mb-3 text-4xl text-gray-400 fas fa-users"></i>
                                    <p class="mb-2 text-gray-600 dark:text-gray-400">No staff members found</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-500">Add your first staff member using the form above</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>

        <!-- Modal panel -->
        <div class="inline-block w-full max-w-md my-8 overflow-hidden text-left align-middle transition-all transform bg-white rounded-lg shadow-xl dark:bg-gray-800">
            <form id="editStaffForm" method="POST">
                @csrf
                @method('PUT')
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit Staff Member</h3>
                        <button type="button" onclick="closeEditModal()"
                                class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="space-y-4" id="editFormContent">
                        <!-- Form fields will be loaded here -->
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900">
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeEditModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#8B7355] dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] border border-transparent rounded-md shadow-sm hover:bg-[#7A6348] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#8B7355]">
                            Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toastify Notifications -->
@if(session('success'))
<script>
document.addEventListener('DOMContentLoaded', function() {
    Toastify({
        text: "{{ session('success') }}",
        duration: 5000,
        gravity: "top",
        position: "right",
        style: {
            background: "#22c55e",
            borderRadius: "8px",
            fontWeight: "500",
            boxShadow: "0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)"
        },
        onClick: function(){}
    }).showToast();
});
</script>
@endif

@if(session('error'))
<script>
document.addEventListener('DOMContentLoaded', function() {
    Toastify({
        text: "{{ session('error') }}",
        duration: 5000,
        gravity: "top",
        position: "right",
        style: {
            background: "#ef4444",
            borderRadius: "8px",
            fontWeight: "500",
            boxShadow: "0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)"
        },
        onClick: function(){}
    }).showToast();
});
</script>
@endif

<!-- JavaScript -->
<script>
//Edit Staff Modal.
function editStaff(staffId) {
    const formContent = `
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Full Name *</label>
            <input type="text" name="name" required
                   class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#8B7355] focus:border-[#8B7355] block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-[#8B7355] dark:focus:border-[#8B7355]">
        </div>
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Role *</label>
            <select name="roles" required
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#8B7355] focus:border-[#8B7355] block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-[#8B7355] dark:focus:border-[#8B7355]">
                <option value="therapist">Therapist</option>
                <option value="receptionist">Receptionist</option>
                <option value="manager">Manager</option>
                <option value="admin">Admin</option>
            </select>
        </div>
    `;

    document.getElementById('editFormContent').innerHTML = formContent;
    document.getElementById('editStaffForm').action = `/staff/${staffId}`;

    // Show modal
    document.getElementById('editModal').classList.remove('hidden');

    // Fetch current data and populate form
    fetch(`/staff/${staffId}`)
        .then(response => response.json())
        .then(data => {
            const form = document.getElementById('editStaffForm');
            form.querySelector('[name="name"]').value = data.name;
            form.querySelector('[name="branch_id"]').value = data.branch_id || '';
            form.querySelector('[name="roles"]').value = data.roles;
            form.querySelector('[name="status"]').value = data.status;
        })
        .catch(error => {
            console.error('Error fetching staff data:', error);
        });
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target.id === 'editModal') {
        closeEditModal();
    }
});

// Clock Function
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

// Initialize clock on page load
document.addEventListener('DOMContentLoaded', function() {
    updateClock();
    setInterval(updateClock, 1000);
});
</script>
@endsection
