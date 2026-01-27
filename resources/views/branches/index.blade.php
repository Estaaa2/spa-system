@extends('layouts.app')

@section('title', 'Branches Management')

@section('content')
<div class="mx-auto max-w-7xl">
    <!-- Header with Date & Time -->
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">Branches Management</h1>

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

    <!-- Current Branch Info Card -->
    @if(session('current_branch_id'))
        @php
            $currentBranch = Auth::user()->spa->branches->firstWhere('id', session('current_branch_id'));
        @endphp
        @if($currentBranch)
        <div class="p-4 mb-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center">
                <div class="flex items-center justify-center w-10 h-10 text-white rounded-full bg-[#8B7355]">
                    <i class="fas fa-location-dot"></i>
                </div>
                <div class="ml-4">
                    <h3 class="font-medium text-gray-800 dark:text-white">Currently Viewing Branch</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        <span class="font-semibold">{{ $currentBranch->name }}</span> -
                        {{ $currentBranch->location }}
                    </p>
                    <p class="mt-1 text-xs text-blue-600 dark:text-blue-400">
                        Switch branches using the dropdown in the sidebar
                    </p>
                </div>
            </div>
        </div>
        @endif
    @endif

    <!-- Main Card with Table -->
    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <!-- Card Header with Add Button -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">All Branches</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Manage all branches for {{ Auth::user()->spa->name }}
                </p>
            </div>
            <div>
                <button onclick="openCreateModal()"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg focus:ring-4 focus:ring-blue-300 dark:bg-blue-700 dark:hover:bg-blue-600">
                    <i class="mr-2 fa-solid fa-plus"></i>
                    Add New Branch
                </button>
            </div>
        </div>

        <!-- Table -->
        @if($branches->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Branch</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Location</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Users</th>
                            <th class="px-6 py-3 text-xs font-medium text-center text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @foreach($branches as $branch)
                            <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-[#8B7355] flex items-center justify-center text-white">
                                            @if($branch->is_main)
                                                <i class="fas fa-crown"></i>
                                            @else
                                                <i class="fas fa-store"></i>
                                            @endif
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">
                                                {{ $branch->name }}
                                                @if(session('current_branch_id') == $branch->id)
                                                    <span class="px-2 py-1 ml-2 text-xs font-medium text-blue-600 bg-blue-100 rounded-full dark:bg-blue-900/30 dark:text-blue-300">
                                                        Current
                                                    </span>
                                                @endif
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $branch->phone ?? 'No phone' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $branch->location }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1.5 text-xs font-medium rounded-full
                                        {{ $branch->users_count > 0 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                        {{ $branch->users_count }} {{ Str::plural('user', $branch->users_count) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button onclick="editBranch({{ $branch->id }})"
                                                class="px-3 py-1 text-sm text-white bg-yellow-500 rounded hover:bg-yellow-600"
                                                title="Edit">
                                            Edit
                                        </button>
                                        @if(!$branch->is_main && $branch->users_count == 0)
                                            <button onclick="deleteBranch({{ $branch->id }}, '{{ addslashes($branch->name) }}')"
                                                    class="px-3 py-1 text-sm text-white bg-red-600 rounded hover:bg-red-700"
                                                    title="Delete">
                                                Delete
                                            </button>
                                        @else
                                            <button disabled
                                                    class="px-3 py-1 text-sm text-gray-400 bg-gray-200 rounded cursor-not-allowed dark:bg-gray-700 dark:text-gray-500"
                                                    title="{{ $branch->is_main ? 'Cannot delete main branch' : 'Branch has users assigned' }}">
                                                Delete
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Stats Footer -->
            <div class="px-4 py-3 mt-4 border-t border-gray-200 bg-gray-50 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col items-center justify-between sm:flex-row">
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Showing <span class="font-medium">{{ $branches->count() }}</span> branches
                    </div>
                    <div class="flex items-center gap-4 mt-2 text-sm text-gray-500 dark:text-gray-400 sm:mt-0">
                        <span class="inline-flex items-center">
                            <i class="mr-1 text-yellow-500 fa-solid fa-crown"></i>
                            Main: {{ $branches->where('is_main', true)->count() }}
                        </span>
                        <span class="inline-flex items-center">
                            <i class="mr-1 text-gray-400 fa-solid fa-store"></i>
                            Regular: {{ $branches->where('is_main', false)->count() }}
                        </span>
                    </div>
                </div>
            </div>
        @else
            <!-- Empty State -->
            <div class="px-6 py-12 text-center">
                <i class="mx-auto text-4xl text-gray-400 fa-solid fa-store"></i>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No branches</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Get started by creating your first branch.
                </p>
                <div class="mt-6">
                    <button onclick="openCreateModal()"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 dark:bg-blue-700 dark:hover:bg-blue-600">
                        <i class="mr-2 fa-solid fa-plus"></i>
                        Add New Branch
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Create/Edit Branch Modal -->
<div id="branchModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75"></div>

        <!-- Modal panel -->
        <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl dark:bg-gray-800 sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="branchForm" method="POST">
                @csrf
                <div id="formMethod"></div>

                <div class="px-6 py-4 bg-white dark:bg-gray-800">
                    <div class="flex items-center justify-between">
                        <h3 id="modalTitle" class="text-lg font-medium text-gray-900 dark:text-white">
                            Add New Branch
                        </h3>
                        <button type="button" onclick="closeModal()"
                                class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                            <i class="text-xl fa-solid fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="px-6 py-4">
                    <div class="space-y-4">
                        <!-- Branch Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Branch Name *
                            </label>
                            <input type="text" id="name" name="name" required
                                   class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Give your branch a descriptive name
                            </p>
                        </div>

                        <!-- Location -->
                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Location *
                            </label>
                            <textarea id="location" name="location" rows="2" required
                                      class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"></textarea>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Full address of the branch
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Phone Number
                                </label>
                                <input type="tel" id="phone" name="phone"
                                    maxlength="11"
                                    minlength="11"
                                    oninput="validatePhone(this)"
                                    class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    placeholder="09XXXXXXXXX">
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Exactly 11 digits (e.g., 09171234567)
                                </p>
                                <p id="phoneError" class="hidden mt-1 text-xs text-red-600 dark:text-red-400">
                                    Must be 11 digits starting with 09
                                </p>
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Email Address
                                </label>
                                <input type="email" id="email" name="email" maxlength="100"
                                    class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    placeholder="branch@example.com">
                            </div>
                        </div>

                        <!-- Main Branch Toggle -->
                        <input type="hidden" name="is_main" value="0">

                        <div class="flex items-center">
                            <input type="checkbox" id="is_main" name="is_main"
                                   class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600">
                            <label for="is_main" class="block ml-2 text-sm text-gray-700 dark:text-gray-300">
                                Set as main branch
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            The main branch is your primary location. Only one branch can be main at a time.
                        </p>
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50">
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 transition-colors bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-600 dark:border-gray-500 dark:text-white dark:hover:bg-gray-500">
                            Cancel
                        </button>
                        <button type="submit" id="submitBtn"
                                class="px-4 py-2 text-sm font-medium text-white transition-colors bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 dark:bg-blue-700 dark:hover:bg-blue-600">
                            Save Branch
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75"></div>

        <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl dark:bg-gray-800 sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="px-6 py-4 bg-white dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        Confirm Deletion
                    </h3>
                    <button type="button" onclick="closeDeleteModal()"
                            class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <i class="text-xl fa-solid fa-times"></i>
                    </button>
                </div>
            </div>

            <div class="px-6 py-4">
                <div class="flex items-start">
                    <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-red-100 rounded-full dark:bg-red-900/30">
                        <i class="text-xl text-red-600 fa-solid fa-trash dark:text-red-400"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-lg font-medium text-gray-900 dark:text-white" id="deleteBranchName">
                            Delete Branch?
                        </h4>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            This action cannot be undone. All data associated with this branch will be permanently removed.
                        </p>
                        <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">
                            ⚠️ Warning: This action is irreversible!
                        </p>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50">
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeDeleteModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 transition-colors bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-600 dark:border-gray-500 dark:text-white dark:hover:bg-gray-500">
                        Cancel
                    </button>
                    <button type="button" onclick="confirmDelete()" id="deleteConfirmBtn"
                            class="px-4 py-2 text-sm font-medium text-white transition-colors bg-red-600 rounded-lg hover:bg-red-700 focus:ring-4 focus:ring-red-300 dark:bg-red-700 dark:hover:bg-red-600">
                        Delete Branch
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentBranchId = null;
let deleteBranchId = null;

// Real-time Clock Function
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

// Modal Functions
function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Add New Branch';
    document.getElementById('formMethod').innerHTML = ''; // Clear any previous method
    document.getElementById('branchForm').action = '{{ route("branches.store") }}';
    document.getElementById('branchForm').reset();
    document.getElementById('is_main').checked = false;
    document.getElementById('submitBtn').textContent = 'Create Branch';
    document.getElementById('branchModal').classList.remove('hidden');
}

function editBranch(branchId) {
    console.log('Editing branch ID:', branchId);

    const button = event.target;
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    button.disabled = true;

    // Use the correct URL (without /api prefix)
    fetch(`/branches/${branchId}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);

        if (data.success && data.branch) {
            document.getElementById('modalTitle').textContent = 'Edit Branch';

            // Set the form action
            document.getElementById('branchForm').action = `/branches/${branchId}`;

            // Fill form fields
            document.getElementById('name').value = data.branch.name || '';
            document.getElementById('location').value = data.branch.location || '';
            document.getElementById('phone').value = data.branch.phone || '';
            document.getElementById('email').value = data.branch.email || '';
            document.getElementById('is_main').checked = data.branch.is_main || false;

            document.getElementById('submitBtn').textContent = 'Update Branch';
            currentBranchId = branchId;
            document.getElementById('branchModal').classList.remove('hidden');

            button.innerHTML = originalHTML;
            button.disabled = false;
        } else {
            showToast(data.message || 'Failed to load branch data', 'error');
            button.innerHTML = originalHTML;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error fetching branch:', error);
        showToast('An error occurred. Please try again.', 'error');
        button.innerHTML = originalHTML;
        button.disabled = false;
    });
}

// Form Submission Handler
document.getElementById('branchForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = this;
    const formData = new FormData(form);
    const url = form.action;

    // Check if this is an edit (URL has branch ID) or create
    const isEdit = url.includes('/branches/') && url.split('/').filter(Boolean).length > 1;

    // Convert FormData to object
    const jsonData = {};
    for (let [key, value] of formData.entries()) {
        if (key === '_token') continue; // Keep CSRF token in headers
        jsonData[key] = value;
    }

    // Handle checkbox
    jsonData.is_main = document.getElementById('is_main').checked ? 1 : 0;

    console.log('URL:', url);
    console.log('Is Edit?', isEdit);
    console.log('Data:', jsonData);

    const button = document.getElementById('submitBtn');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    button.disabled = true;

    // Use the correct HTTP method
    const method = isEdit ? 'PUT' : 'POST';

    // For PUT requests, don't include _method field in JSON
    if (isEdit) {
        delete jsonData._method;
    }

    fetch(url, {
        method: method, // Direct HTTP method
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(jsonData)
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json().then(data => {
            if (!response.ok) {
                throw {
                    status: response.status,
                    data: data
                };
            }
            return data;
        });
    })
    .then(data => {
        console.log('Success:', data);
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast(data.message || 'Operation failed', 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);

        if (error.status === 422) {
            // Validation errors
            let errorMessages = [];
            if (error.data && error.data.errors) {
                for (let field in error.data.errors) {
                    errorMessages.push(error.data.errors[field][0]);
                }
            } else {
                errorMessages.push('Validation failed');
            }
            showToast(errorMessages.join('<br>'), 'error');
        } else if (error.status === 405) {
            // Method not allowed - fallback to POST with _method
            submitWithMethodOverride(url, jsonData, isEdit, button, originalText);
        } else {
            showToast('An error occurred. Please try again.', 'error');
        }

        button.innerHTML = originalText;
        button.disabled = false;
    });
});

// Fallback method using POST with _method override
function submitWithMethodOverride(url, jsonData, isEdit, button, originalText) {
    // Add _method field for Laravel to detect
    if (isEdit) {
        jsonData._method = 'PUT';
    }

    fetch(url, {
        method: 'POST', // Always POST
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(jsonData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast(data.message || 'Operation failed', 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Fallback error:', error);
        showToast('Submission failed', 'error');
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function closeModal() {
    document.getElementById('branchModal').classList.add('hidden');
    currentBranchId = null;
}

// Delete Functions
function deleteBranch(branchId, branchName) {
    deleteBranchId = branchId;
    document.getElementById('deleteBranchName').textContent = `Delete "${branchName}"?`;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    deleteBranchId = null;
}

function confirmDelete() {
    if (!deleteBranchId) return;

    const button = document.getElementById('deleteConfirmBtn');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
    button.disabled = true;

    fetch(`/branches/${deleteBranchId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Branch deleted successfully', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast(data.message || 'Failed to delete branch', 'error');
            button.innerHTML = originalText;
            button.disabled = false;
            closeDeleteModal();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred. Please try again.', 'error');
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Form Submission
// Form Submission - FIXED for JSON
document.getElementById('branchForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = this;
    const url = form.action;
    const method = form.querySelector('input[name="_method"]') ? 'PUT' : 'POST';

    // Create JSON data from form
    const formData = new FormData(form);
    const jsonData = {};

    // Convert FormData to JSON object
    for (let [key, value] of formData.entries()) {
        if (key === '_method' || key === '_token') continue; // Skip these
        jsonData[key] = value;
    }

    // Convert boolean value properly
    jsonData.is_main = document.getElementById('is_main').checked ? 1 : 0;

    console.log('Sending JSON data:', jsonData);
    console.log('URL:', url);
    console.log('Method:', method);

    const button = document.getElementById('submitBtn');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    button.disabled = true;

    // Send as JSON
    fetch(url, {
        method: method === 'PUT' ? 'POST' : 'POST', // Laravel handles PUT via POST with _method
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(jsonData)
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json().then(data => {
            if (!response.ok) {
                throw {
                    status: response.status,
                    data: data
                };
            }
            return data;
        });
    })
    .then(data => {
        console.log('Success response:', data);
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast(data.message || 'Operation failed', 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Full error:', error);

        if (error.status === 422) {
            // Validation error
            let errorMessages = [];
            if (error.data && error.data.errors) {
                for (let field in error.data.errors) {
                    errorMessages.push(error.data.errors[field][0]);
                }
            } else if (error.data && error.data.message) {
                errorMessages.push(error.data.message);
            } else {
                errorMessages.push('Validation failed. Please check your inputs.');
            }
            showToast(errorMessages.join('<br>'), 'error');
        } else if (error.status === 419) {
            // CSRF token mismatch
            showToast('Session expired. Please refresh the page and try again.', 'error');
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            showToast('An error occurred. Please try again.', 'error');
        }

        button.innerHTML = originalText;
        button.disabled = false;
    });
});

// Phone validation
function validatePhone(input) {
    const value = input.value.replace(/\D/g, '');
    const errorElement = document.getElementById('phoneError');

    // Limit to 11 digits
    if (value.length > 11) {
        input.value = value.substring(0, 11);
    }

    // Validate format
    if (value.length === 11) {
        if (value.startsWith('09')) {
            // Valid
            errorElement.classList.add('hidden');
            input.classList.remove('border-red-500', 'text-red-600');
            input.classList.add('border-green-500', 'text-green-600');
        } else {
            // Invalid - doesn't start with 09
            errorElement.textContent = 'Philippine numbers must start with 09';
            errorElement.classList.remove('hidden');
            input.classList.add('border-red-500', 'text-red-600');
            input.classList.remove('border-green-500', 'text-green-600');
        }
    } else if (value.length > 0) {
        // Too short
        errorElement.textContent = `Must be 11 digits (${value.length}/11)`;
        errorElement.classList.remove('hidden');
        input.classList.add('border-red-500', 'text-red-600');
        input.classList.remove('border-green-500', 'text-green-600');
    } else {
        // Empty
        errorElement.classList.add('hidden');
        input.classList.remove('border-red-500', 'text-red-600', 'border-green-500', 'text-green-600');
        input.classList.add('border-gray-300');
    }
}

// Update the form validation to include phone
document.getElementById('branchForm').addEventListener('submit', function(e) {
    const phoneInput = document.getElementById('phone');
    const phoneValue = phoneInput.value.replace(/\D/g, '');

    // Only validate if phone has value
    if (phoneValue.length > 0 && (phoneValue.length !== 11 || !phoneValue.startsWith('09'))) {
        e.preventDefault();
        showToast('Please enter a valid Philippine mobile number (11 digits starting with 09)', 'error');
        phoneInput.focus();
        return false;
    }
});

// Add this after your other functions
function testUpdate() {
    const testData = {
        name: "Test Update " + new Date().getTime(),
        location: "Test Location",
        phone: "09123456789",
        email: "test@example.com",
        is_main: 0
    };

    console.log('Testing update with:', testData);

    fetch('/branches/1', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-HTTP-Method-Override': 'PUT'
        },
        body: JSON.stringify(testData)
    })
    .then(response => response.json())
    .then(data => console.log('Test result:', data))
    .catch(error => console.error('Test error:', error));
}

// Toast function
function showToast(message, type = 'info') {
    const existingToasts = document.querySelectorAll('.branch-toast');
    existingToasts.forEach(toast => toast.remove());

    const toast = document.createElement('div');
    toast.className = `branch-toast fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg transform transition-all duration-300 translate-x-0 ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;

    toast.innerHTML = `
        <div class="flex items-center">
            <i class="mr-2 fa-solid ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Close modals on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeDeleteModal();
    }
});

// Close modals when clicking outside
document.getElementById('branchModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

document.getElementById('deleteModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});

// Initialize clock
document.addEventListener('DOMContentLoaded', function () {
    updateClock();
    setInterval(updateClock, 1000);
});
</script>

<style>
.branch-toast {
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Modal animations */
.hidden {
    display: none !important;
}

/* Ensure modals appear above everything */
#branchModal, #deleteModal {
    z-index: 9999;
}
</style>
@endsection
