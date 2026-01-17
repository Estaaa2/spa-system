@vite(['resources/css/appointment.css'])
@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl">

    <!-- Header -->
    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">
            Appointments
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

    <!-- Filtering and Search Bar -->
    <div class="p-6 mb-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <!-- Filtering Section -->
            <div class="flex flex-wrap items-center gap-3">
                <!-- Filter Trigger Button -->
                <button id="filterTrigger"
                        class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:hover:bg-gray-600"
                        type="button">
                    <i class="fa-solid fa-filter"></i>
                    Filtering
                    <i class="fa-solid fa-chevron-down"></i>
                </button>

                <!-- Active Filters Display -->
                <div class="flex flex-wrap gap-2" id="activeFilters">
                    <!-- Active filters will appear here -->
                </div>
            </div>

            <!-- Search Bar -->
            <div class="relative w-full md:w-auto">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <i class="text-gray-400 fa-solid fa-search"></i>
                </div>
                <input type="search"
                       class="block w-full pl-10 pr-4 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 focus:ring-2 focus:ring-[#8B7355] focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                       placeholder="Search appointments...">
            </div>
        </div>
    </div>

    <!-- Appointments Table -->
    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="w-full min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-700 uppercase dark:text-gray-300">
                            Name
                        </th>
                        <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-700 uppercase dark:text-gray-300">
                            Service Type
                        </th>
                        <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-700 uppercase dark:text-gray-300">
                            Treatment
                        </th>
                        <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-700 uppercase dark:text-gray-300">
                            Therapist
                        </th>
                        <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-700 uppercase dark:text-gray-300">
                            Location
                        </th>
                        <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-700 uppercase dark:text-gray-300">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-700 uppercase dark:text-gray-300">
                            Action
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    <!-- Row 1 -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-10 h-10">
                                    <div class="flex items-center justify-center w-10 h-10 bg-gray-100 rounded-full dark:bg-gray-700">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-300">CH</span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">Cedie Heyrosa</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">08:00 AM</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-gray-900 dark:text-white">Spa Treatment</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-gray-900 dark:text-white">Full Body Massage</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-8 h-8">
                                    <div class="flex items-center justify-center w-8 h-8 bg-blue-100 rounded-full dark:bg-blue-900/30">
                                        <i class="text-xs text-blue-600 fa-solid fa-user dark:text-blue-400"></i>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">Sarah Rebate</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-gray-900 dark:text-white">Room 3</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold text-yellow-800 bg-yellow-100 rounded-full dark:bg-yellow-900/30 dark:text-yellow-300">
                                Pending
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm font-medium whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <!-- Edit Button -->
                                <button type="button" class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700" title="Edit">
                                    <i class="w-4 h-4 fa-solid fa-pen-to-square"></i>
                                </button>
                                <!-- View Details Dropdown -->
                                <div class="relative">
                                    <button type="button"
                                            class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 view-details-btn"
                                            data-id="1"
                                            title="View Details">
                                        <i class="w-4 h-4 fa-solid fa-ellipsis-vertical"></i>
                                    </button>

                                    <!-- Details Dropdown -->
                                    <div id="detailsDropdown1" class="absolute right-0 z-50 hidden w-48 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
                                        <div class="p-4">
                                            <h4 class="mb-2 text-sm font-semibold text-gray-900 dark:text-white">Appointment Details</h4>
                                            <div class="space-y-2 text-xs text-gray-600 dark:text-gray-400">
                                                <p><span class="font-medium">Date:</span> Today</p>
                                                <p><span class="font-medium">Duration:</span> 60 mins</p>
                                                <p><span class="font-medium">Notes:</span> Prefers room temperature</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <!-- Row 2 -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-10 h-10">
                                    <div class="flex items-center justify-center w-10 h-10 bg-gray-100 rounded-full dark:bg-gray-700">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-300">MC</span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">Marjo Catibod</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">10:30 AM</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-gray-900 dark:text-white">Hair Service</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-gray-900 dark:text-white">Hair Coloring</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-8 h-8">
                                    <div class="flex items-center justify-center w-8 h-8 bg-green-100 rounded-full dark:bg-green-900/30">
                                        <i class="text-xs text-green-600 fa-solid fa-user dark:text-green-400"></i>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">Alex Cristobal</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-gray-900 dark:text-white">Room 1</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full dark:bg-green-900/30 dark:text-green-300">
                                Confirmed
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm font-medium whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <button type="button" class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700" title="Edit">
                                    <i class="w-4 h-4 fa-solid fa-pen-to-square"></i>
                                </button>
                                <div class="relative">
                                    <button type="button"
                                            class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 view-details-btn"
                                            data-id="2"
                                            title="View Details">
                                        <i class="w-4 h-4 fa-solid fa-ellipsis-vertical"></i>
                                    </button>
                                    <div id="detailsDropdown2" class="absolute right-0 z-50 hidden w-48 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
                                        <div class="p-4">
                                            <h4 class="mb-2 text-sm font-semibold text-gray-900 dark:text-white">Appointment Details</h4>
                                            <div class="space-y-2 text-xs text-gray-600 dark:text-gray-400">
                                                <p><span class="font-medium">Date:</span> Today</p>
                                                <p><span class="font-medium">Duration:</span> 90 mins</p>
                                                <p><span class="font-medium">Notes:</span> Allergy to ammonia</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>

        <!-- Table Footer with Reschedule and Cancel -->
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Showing <span class="font-medium text-gray-700 dark:text-gray-300">2</span> of <span class="font-medium text-gray-700 dark:text-gray-300">12</span> appointments
            </div>
            <div class="flex space-x-3">
                <button type="button" class="px-4 py-2 text-sm font-medium text-white transition-colors bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg hover:opacity-90">
                    <i class="mr-2 fa-solid fa-calendar-clock"></i>
                    Reschedule Selected
                </button>
                <button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 transition-colors bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:hover:bg-gray-600">
                    <i class="mr-2 fa-solid fa-ban"></i>
                    Cancel Selected
                </button>
            </div>
        </div>
    </div>

</div>

<!-- Filter Overlay Modal -->
<div id="filterModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Overlay Background -->
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75"
             onclick="closeFilterModal()"></div>

        <!-- Modal Content -->
        <div class="inline-block w-full max-w-md my-8 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl dark:bg-gray-800 sm:my-8 sm:align-middle sm:max-w-lg">
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Filter Appointments
                </h3>
                <button type="button"
                        onclick="closeFilterModal()"
                        class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                    <i class="text-xl fa-solid fa-times"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="px-6 py-4">
                <div class="space-y-4">
                    <!-- Date Filter -->
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Date Range</label>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block mb-1 text-xs text-gray-500 dark:text-gray-400">From</label>
                                <input type="date" id="filterDateFrom"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                            <div>
                                <label class="block mb-1 text-xs text-gray-500 dark:text-gray-400">To</label>
                                <input type="date" id="filterDateTo"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                        </div>
                    </div>

                    <!-- Service Filter -->
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Service Type</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="service" value="hair" class="w-4 h-4 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355] dark:border-gray-600">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Hair Service</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="service" value="spa" class="w-4 h-4 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355] dark:border-gray-600">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Spa Treatment</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="service" value="massage" class="w-4 h-4 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355] dark:border-gray-600">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Massage</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="service" value="nails" class="w-4 h-4 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355] dark:border-gray-600">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Nails</span>
                            </label>
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="status" value="pending" class="w-4 h-4 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355] dark:border-gray-600">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Pending</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="status" value="confirmed" class="w-4 h-4 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355] dark:border-gray-600">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Confirmed</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="status" value="completed" class="w-4 h-4 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355] dark:border-gray-600">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Completed</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="status" value="cancelled" class="w-4 h-4 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355] dark:border-gray-600">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Cancelled</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50">
                <div class="flex justify-end space-x-3">
                    <button type="button"
                            onclick="clearFilters()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-600 dark:border-gray-500 dark:text-white dark:hover:bg-gray-500">
                        Clear All
                    </button>
                    <button type="button"
                            onclick="applyFilters()"
                            class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg hover:opacity-90">
                        Apply Filters
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Update clock function
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

    // Filter Modal Functions
    function openFilterModal() {
        document.getElementById('filterModal').classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeFilterModal() {
        document.getElementById('filterModal').classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    function applyFilters() {
        const activeFilters = [];

        // Get date filters
        const dateFrom = document.getElementById('filterDateFrom').value;
        const dateTo = document.getElementById('filterDateTo').value;

        if (dateFrom) activeFilters.push({ type: 'date', value: `From ${dateFrom}` });
        if (dateTo) activeFilters.push({ type: 'date', value: `To ${dateTo}` });

        // Get service filters
        const serviceCheckboxes = document.querySelectorAll('input[name="service"]:checked');
        serviceCheckboxes.forEach(cb => {
            activeFilters.push({ type: 'service', value: cb.parentElement.textContent.trim() });
        });

        // Get status filters
        const statusCheckboxes = document.querySelectorAll('input[name="status"]:checked');
        statusCheckboxes.forEach(cb => {
            activeFilters.push({ type: 'status', value: cb.parentElement.textContent.trim() });
        });

        // Update active filters display
        updateActiveFiltersDisplay(activeFilters);

        // Here you would normally filter the table data
        console.log('Applying filters:', activeFilters);

        closeFilterModal();
    }

    function clearFilters() {
        // Clear all inputs
        document.getElementById('filterDateFrom').value = '';
        document.getElementById('filterDateTo').value = '';

        document.querySelectorAll('input[name="service"]').forEach(cb => cb.checked = false);
        document.querySelectorAll('input[name="status"]').forEach(cb => cb.checked = false);

        // Clear active filters display
        document.getElementById('activeFilters').innerHTML = '';
    }

    function updateActiveFiltersDisplay(filters) {
        const container = document.getElementById('activeFilters');
        container.innerHTML = '';

        filters.forEach(filter => {
            const badge = document.createElement('span');
            badge.className = 'inline-flex items-center gap-1 px-3 py-1 text-xs text-gray-700 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-300';
            badge.innerHTML = `
                ${filter.value}
                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" onclick="removeFilter('${filter.type}', '${filter.value}')">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            `;
            container.appendChild(badge);
        });
    }

    function removeFilter(type, value) {
        // Logic to remove specific filter
        console.log('Removing filter:', type, value);
        // You would update the filter state here
    }

    // View Details Functions
    function toggleDetailsDropdown(id) {
        const dropdown = document.getElementById(`detailsDropdown${id}`);
        if (dropdown) {
            dropdown.classList.toggle('hidden');

            // Close other dropdowns
            document.querySelectorAll('[id^="detailsDropdown"]').forEach(dd => {
                if (dd.id !== `detailsDropdown${id}`) {
                    dd.classList.add('hidden');
                }
            });
        }
    }

    // Initialize everything
    document.addEventListener('DOMContentLoaded', function() {
        updateClock();
        setInterval(updateClock, 1000);

        // Open filter modal
        document.getElementById('filterTrigger').addEventListener('click', openFilterModal);

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeFilterModal();
            }
        });

        // View details buttons
        document.querySelectorAll('.view-details-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                const id = this.getAttribute('data-id');
                toggleDetailsDropdown(id);
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            document.querySelectorAll('[id^="detailsDropdown"]').forEach(dd => {
                dd.classList.add('hidden');
            });
        });
    });
</script>

@endsection
