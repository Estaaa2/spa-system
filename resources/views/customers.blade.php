@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl">

    <!-- Header -->
    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">
            Customers
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
                    <span class="inline-flex items-center gap-1 px-3 py-1 text-xs text-gray-700 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-300">
                        A-Z
                        <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" onclick="removeFilter('sort', 'A-Z')">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </span>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="relative w-full md:w-auto">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <i class="text-gray-400 fa-solid fa-search"></i>
                </div>
                <input type="search"
                       class="block w-full pl-10 pr-4 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 focus:ring-2 focus:ring-[#8B7355] focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                       placeholder="Search customers...">
            </div>
        </div>
    </div>

    <!-- Customers Table -->
    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="w-full min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-700 uppercase dark:text-gray-300">
                            Fullname
                        </th>
                        <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-700 uppercase dark:text-gray-300">
                            Contact Info
                        </th>
                        <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-700 uppercase dark:text-gray-300">
                            Last Visit
                        </th>
                        <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-700 uppercase dark:text-gray-300">
                            Total Visit
                        </th>
                        <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-700 uppercase dark:text-gray-300">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    <!-- Row 1 -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-10 h-10">
                                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-gradient-to-br from-blue-100 to-blue-200 dark:from-blue-900/30 dark:to-blue-800/30">
                                        <span class="text-sm font-medium text-blue-600 dark:text-blue-400">CH</span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">Cedie Heyrosa</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <i class="w-4 h-4 text-gray-400 fa-solid fa-phone"></i>
                                    <span class="text-sm text-gray-900 dark:text-white">+63801481294</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <i class="w-4 h-4 text-gray-400 fa-solid fa-envelope"></i>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">cedieheyrosa@gmail.com</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900 dark:text-white">Today</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">08:00 AM</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <span class="inline-flex items-center px-3 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full dark:bg-green-900/30 dark:text-green-300">
                                    <i class="mr-1 fa-solid fa-calendar-check"></i>
                                    12 visits
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm font-medium whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <!-- View Profile Button -->
                                <button type="button" class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700" title="View Profile">
                                    <i class="w-4 h-4 fa-solid fa-eye"></i>
                                </button>
                                <!-- Edit Button -->
                                <button type="button" class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700" title="Edit">
                                    <i class="w-4 h-4 fa-solid fa-pen-to-square"></i>
                                </button>
                                <!-- More Options Dropdown -->
                                <div class="relative">
                                    <button type="button"
                                            class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 more-options-btn"
                                            data-id="1"
                                            title="More Options">
                                        <i class="w-4 h-4 fa-solid fa-ellipsis-vertical"></i>
                                    </button>

                                    <!-- Options Dropdown -->
                                    <div id="optionsDropdown1" class="absolute right-0 z-50 hidden w-48 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
                                        <div class="py-2">
                                            <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                                                <i class="w-4 h-4 mr-2 fa-solid fa-clock-rotate-left"></i>
                                                View History
                                            </a>
                                            <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                                                <i class="w-4 h-4 mr-2 fa-solid fa-calendar-plus"></i>
                                                Book Appointment
                                            </a>
                                            <a href="#" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:text-red-400 dark:hover:bg-gray-700">
                                                <i class="w-4 h-4 mr-2 fa-solid fa-ban"></i>
                                                Deactivate
                                            </a>
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
                                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-gradient-to-br from-purple-100 to-purple-200 dark:from-purple-900/30 dark:to-purple-800/30">
                                        <span class="text-sm font-medium text-purple-600 dark:text-purple-400">MC</span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">Marjo Catibod</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <i class="w-4 h-4 text-gray-400 fa-solid fa-phone"></i>
                                    <span class="text-sm text-gray-900 dark:text-white">+63481209401</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <i class="w-4 h-4 text-gray-400 fa-solid fa-envelope"></i>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">marjocatibod@gmail.com</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900 dark:text-white">2 days ago</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">10:30 AM</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <span class="inline-flex items-center px-3 py-1 text-xs font-semibold text-blue-800 bg-blue-100 rounded-full dark:bg-blue-900/30 dark:text-blue-300">
                                    <i class="mr-1 fa-solid fa-calendar-check"></i>
                                    8 visits
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm font-medium whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <button type="button" class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700" title="View Profile">
                                    <i class="w-4 h-4 fa-solid fa-eye"></i>
                                </button>
                                <button type="button" class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700" title="Edit">
                                    <i class="w-4 h-4 fa-solid fa-pen-to-square"></i>
                                </button>
                                <div class="relative">
                                    <button type="button"
                                            class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 more-options-btn"
                                            data-id="2"
                                            title="More Options">
                                        <i class="w-4 h-4 fa-solid fa-ellipsis-vertical"></i>
                                    </button>
                                    <div id="optionsDropdown2" class="absolute right-0 z-50 hidden w-48 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
                                        <div class="py-2">
                                            <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                                                <i class="w-4 h-4 mr-2 fa-solid fa-clock-rotate-left"></i>
                                                View History
                                            </a>
                                            <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                                                <i class="w-4 h-4 mr-2 fa-solid fa-calendar-plus"></i>
                                                Book Appointment
                                            </a>
                                            <a href="#" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:text-red-400 dark:hover:bg-gray-700">
                                                <i class="w-4 h-4 mr-2 fa-solid fa-ban"></i>
                                                Deactivate
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <!-- Row 3 -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-10 h-10">
                                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-gradient-to-br from-green-100 to-green-200 dark:from-green-900/30 dark:to-green-800/30">
                                        <span class="text-sm font-medium text-green-600 dark:text-green-400">PL</span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">Piolo Lingo</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <i class="w-4 h-4 text-gray-400 fa-solid fa-phone"></i>
                                    <span class="text-sm text-gray-900 dark:text-white">+639357816489</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <i class="w-4 h-4 text-gray-400 fa-solid fa-envelope"></i>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">piololingo@gmail.com</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900 dark:text-white">1 week ago</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">02:15 PM</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <span class="inline-flex items-center px-3 py-1 text-xs font-semibold text-yellow-800 bg-yellow-100 rounded-full dark:bg-yellow-900/30 dark:text-yellow-300">
                                    <i class="mr-1 fa-solid fa-calendar-check"></i>
                                    3 visits
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm font-medium whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <button type="button" class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700" title="View Profile">
                                    <i class="w-4 h-4 fa-solid fa-eye"></i>
                                </button>
                                <button type="button" class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700" title="Edit">
                                    <i class="w-4 h-4 fa-solid fa-pen-to-square"></i>
                                </button>
                                <div class="relative">
                                    <button type="button"
                                            class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 more-options-btn"
                                            data-id="3"
                                            title="More Options">
                                        <i class="w-4 h-4 fa-solid fa-ellipsis-vertical"></i>
                                    </button>
                                    <div id="optionsDropdown3" class="absolute right-0 z-50 hidden w-48 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
                                        <div class="py-2">
                                            <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                                                <i class="w-4 h-4 mr-2 fa-solid fa-clock-rotate-left"></i>
                                                View History
                                            </a>
                                            <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                                                <i class="w-4 h-4 mr-2 fa-solid fa-calendar-plus"></i>
                                                Book Appointment
                                            </a>
                                            <a href="#" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:text-red-400 dark:hover:bg-gray-700">
                                                <i class="w-4 h-4 mr-2 fa-solid fa-ban"></i>
                                                Deactivate
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>

        <!-- Table Footer -->
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Showing <span class="font-medium text-gray-700 dark:text-gray-300">3</span> of <span class="font-medium text-gray-700 dark:text-gray-300">45</span> customers
            </div>
            <div class="flex space-x-2">
                <button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 transition-colors bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:hover:bg-gray-600">
                    Previous
                </button>
                <button type="button" class="px-4 py-2 text-sm font-medium text-white transition-colors bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg hover:opacity-90">
                    Next
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
                    Sort Customers
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
                    <p class="text-sm text-gray-600 dark:text-gray-400">Sort customers alphabetically by name</p>

                    <!-- A-Z Sort Options -->
                    <div class="space-y-3">
                        <label class="flex items-center justify-between p-3 border rounded-lg cursor-pointer hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700/50">
                            <div class="flex items-center">
                                <i class="mr-3 text-gray-400 fa-solid fa-arrow-down-a-z"></i>
                                <div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">A to Z</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Sort by name ascending</p>
                                </div>
                            </div>
                            <input type="radio" name="sortOption" value="A-Z" checked
                                   class="w-4 h-4 text-[#8B7355] border-gray-300 focus:ring-[#8B7355] dark:border-gray-600">
                        </label>

                        <label class="flex items-center justify-between p-3 border rounded-lg cursor-pointer hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700/50">
                            <div class="flex items-center">
                                <i class="mr-3 text-gray-400 fa-solid fa-arrow-up-a-z"></i>
                                <div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Z to A</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Sort by name descending</p>
                                </div>
                            </div>
                            <input type="radio" name="sortOption" value="Z-A"
                                   class="w-4 h-4 text-[#8B7355] border-gray-300 focus:ring-[#8B7355] dark:border-gray-600">
                        </label>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50">
                <div class="flex justify-end space-x-3">
                    <button type="button"
                            onclick="closeFilterModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-600 dark:border-gray-500 dark:text-white dark:hover:bg-gray-500">
                        Cancel
                    </button>
                    <button type="button"
                            onclick="applySort()"
                            class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg hover:opacity-90">
                        Apply Sort
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

    function applySort() {
        const selectedSort = document.querySelector('input[name="sortOption"]:checked').value;

        // Update active filters display
        const container = document.getElementById('activeFilters');
        container.innerHTML = `
            <span class="inline-flex items-center gap-1 px-3 py-1 text-xs text-gray-700 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-300">
                ${selectedSort}
                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" onclick="removeSort()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </span>
        `;

        // Here you would normally sort the table data
        console.log('Applying sort:', selectedSort);

        closeFilterModal();
    }

    function removeSort() {
        // Reset to default A-Z
        document.getElementById('activeFilters').innerHTML = `
            <span class="inline-flex items-center gap-1 px-3 py-1 text-xs text-gray-700 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-300">
                A-Z
                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" onclick="removeSort()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </span>
        `;

        // Reset radio button
        document.querySelector('input[value="A-Z"]').checked = true;

        console.log('Removed sort, reset to A-Z');
    }

    // More Options Functions
    function toggleOptionsDropdown(id) {
        const dropdown = document.getElementById(`optionsDropdown${id}`);
        if (dropdown) {
            dropdown.classList.toggle('hidden');

            // Close other dropdowns
            document.querySelectorAll('[id^="optionsDropdown"]').forEach(dd => {
                if (dd.id !== `optionsDropdown${id}`) {
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

        // More options buttons
        document.querySelectorAll('.more-options-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                const id = this.getAttribute('data-id');
                toggleOptionsDropdown(id);
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            document.querySelectorAll('[id^="optionsDropdown"]').forEach(dd => {
                dd.classList.add('hidden');
            });
        });
    });
</script>
@endsection
