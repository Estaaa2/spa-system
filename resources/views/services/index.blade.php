@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl">

    <!-- Services Header -->
    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">Services</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage treatments and packages for your spa</p>
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

    <!-- Treatments Section -->
    <div class="mt-8">
        <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Treatments</h2>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $treatments->count() }} treatment(s) available
                    </span>
                    <a href="{{ route('treatments.create') }}"
                       class="px-4 py-2 text-sm text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348] flex items-center gap-2">
                        <i class="fas fa-plus"></i>
                        Add Treatment
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Service Name</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Duration</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Price</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Service Type</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @forelse($treatments as $treatment)
                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-[#8B7355] flex items-center justify-center text-white">
                                        <i class="fas fa-spa"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $treatment->name }}</p>
                                        @if($treatment->description)
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ Str::limit($treatment->description, 40) }}
                                        </p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 text-xs font-medium text-gray-800 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-300">
                                    {{ $treatment->duration }} mins
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-medium text-gray-800 dark:text-white">
                                    ₱{{ number_format($treatment->price, 2) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-medium text-gray-800 dark:text-white">
                                    {{ $treatment->service_type_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-end">
                                <div class="flex items-center gap-2">
                                    <!-- Edit Button -->
                                    <button onclick="editTreatment({{ $treatment->id }})"
                                            class="p-2 text-gray-600 transition-colors bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600">
                                        <i class="w-4 h-4 fas fa-edit"></i>
                                    </button>

                                    <!-- Delete Form -->
                                    <form action="{{ route('treatments.destroy', $treatment->id) }}" method="POST"
                                        onsubmit="return confirm('Delete this treatment?')">
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
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="mb-3 text-4xl text-gray-400 fas fa-spa"></i>
                                    <p class="mb-2 text-gray-600 dark:text-gray-400">No treatments available</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-500">Get started by adding your first treatment</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Packages Section -->
    <div class="mt-8">
        <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Packages</h2>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $packages->count() }} package(s) available
                    </span>
                    <a href="{{ route('packages.create') }}"
                       class="px-4 py-2 text-sm text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348] flex items-center gap-2">
                        <i class="fas fa-plus"></i>
                        Add Package
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Package Name</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Duration</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Price</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Included Treatments</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @forelse($packages as $package)
                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-[#8B7355] to-[#6F5430] flex items-center justify-center text-white">
                                        <i class="fas fa-gift"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $package->name }}</p>
                                        @if($package->description)
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ Str::limit($package->description, 40) }}
                                        </p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 text-xs font-medium text-gray-800 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-300">
                                    {{ $package->duration }} mins
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-medium text-gray-800 dark:text-white">
                                    ₱{{ number_format($package->price, 2) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if($package->included_treatments && count($package->included_treatments) > 0)
                                    <div class="flex flex-wrap gap-1">
                                        @php
                                            $treatmentNames = [];
                                            foreach($package->included_treatments as $treatmentId) {
                                                $treatment = App\Models\Treatment::find($treatmentId);
                                                if($treatment) {
                                                    $treatmentNames[] = $treatment->name;
                                                }
                                            }
                                        @endphp
                                        @foreach(array_slice($treatmentNames, 0, 3) as $name)
                                        <span class="px-2 py-1 text-xs text-blue-800 bg-blue-100 rounded dark:bg-blue-900 dark:text-blue-300">
                                            {{ $name }}
                                        </span>
                                        @endforeach
                                        @if(count($treatmentNames) > 3)
                                        <span class="px-2 py-1 text-xs text-gray-800 bg-gray-100 rounded dark:bg-gray-700 dark:text-gray-300">
                                            +{{ count($treatmentNames) - 3 }} more
                                        </span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-sm text-gray-500 dark:text-gray-400">No treatments included</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-end">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('packages.edit', $package->id) }}"
                                       class="p-2 text-gray-600 transition-colors bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600">
                                        <i class="w-4 h-4 fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('packages.destroy', $package->id) }}" method="POST"
                                          onsubmit="return confirm('Delete this package?')">
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
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="mb-3 text-4xl text-gray-400 fas fa-gift"></i>
                                    <p class="mb-2 text-gray-600 dark:text-gray-400">No packages available</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-500">Create packages to offer combined services</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="mt-8">
        <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">Service Summary</h2>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <!-- Service Types Distribution -->
                <div class="p-6 rounded-lg shadow-lg bg-gradient-to-r from-[#8B7355] to-[#6F5430] text-white">
                    <p class="text-xs tracking-widest opacity-80">SERVICE TYPES</p>
                    <p class="text-lg font-semibold">DISTRIBUTION</p>
                    <div class="mt-4 space-y-2">
                        @php
                            $serviceTypeCounts = $treatments->groupBy('service_type')->map->count();
                        @endphp
                        @foreach($serviceTypeCounts->take(3) as $type => $count)
                        <div class="flex items-center justify-between">
                            <span class="text-sm">{{ $type }}</span>
                            <span class="text-sm font-medium">{{ $count }}</span>
                        </div>
                        @endforeach
                        @if($serviceTypeCounts->count() > 3)
                        <div class="pt-2 mt-2 border-t border-white/20">
                            <span class="text-sm opacity-80">
                                +{{ $serviceTypeCounts->count() - 3 }} more types
                            </span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Price Range -->
                <div class="p-6 bg-white border rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
                    <p class="text-xs tracking-widest text-gray-500 dark:text-gray-400">PRICE</p>
                    <p class="text-lg font-semibold text-gray-800 dark:text-white">RANGE</p>
                    <div class="mt-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Lowest</span>
                            <span class="text-sm font-medium text-gray-800 dark:text-white">
                                ₱{{ $treatments->min('price') ? number_format($treatments->min('price'), 0) : '0' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Highest</span>
                            <span class="text-sm font-medium text-gray-800 dark:text-white">
                                ₱{{ $treatments->max('price') ? number_format($treatments->max('price'), 0) : '0' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Average</span>
                            <span class="text-sm font-medium text-gray-800 dark:text-white">
                                ₱{{ $treatments->avg('price') ? number_format($treatments->avg('price'), 0) : '0' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Duration Stats -->
                <div class="p-6 rounded-lg shadow-lg bg-gradient-to-r from-[#8B7355] to-[#6F5430] text-white">
                    <p class="text-xs tracking-widest opacity-80">AVERAGE</p>
                    <p class="text-lg font-semibold">DURATION</p>
                    <div class="mt-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm">Treatments</span>
                            <span class="text-sm font-medium">
                                {{ $treatments->avg('duration') ? round($treatments->avg('duration')) : '0' }} mins
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm">Packages</span>
                            <span class="text-sm font-medium">
                                {{ $packages->avg('duration') ? round($packages->avg('duration')) : '0' }} mins
                            </span>
                        </div>
                        <div class="pt-2 mt-2 border-t border-white/20">
                            <span class="text-sm opacity-80">
                                Total Services: {{ $treatments->count() + $packages->count() }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Treatment Modal -->
<div id="editTreatmentModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>

        <!-- Modal panel -->
        <div class="inline-block w-full max-w-md my-8 overflow-hidden text-left align-middle transition-all transform bg-white rounded-lg shadow-xl dark:bg-gray-800">
            <form id="editTreatmentForm" method="POST">
                @csrf
                @method('PUT')
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit Treatment</h3>
                        <button type="button" onclick="closeEditTreatmentModal()"
                                class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="space-y-4" id="editTreatmentFormContent">
                        <!-- Form fields will be injected here via JS. -->
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900">
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeEditTreatmentModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-md hover:bg-[#7A6348]">
                            Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Success Toast Notification -->
@if(session('success'))
<div class="fixed bottom-0 right-0 z-50 p-4" id="toast-container">
    <div class="flex items-center w-full max-w-xs p-4 mb-4 text-gray-500 bg-white rounded-lg shadow-lg dark:text-gray-400 dark:bg-gray-800" role="alert">
        <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 text-green-500 bg-green-100 rounded-lg dark:bg-green-800 dark:text-green-200">
            <i class="fas fa-check"></i>
        </div>
        <div class="text-sm font-normal ms-3">{{ session('success') }}</div>
        <button type="button" class="ms-auto -mx-1.5 -my-1.5 bg-white text-gray-400 hover:text-gray-900 rounded-lg focus:ring-2 focus:ring-gray-300 p-1.5 hover:bg-gray-100 inline-flex items-center justify-center h-8 w-8 dark:text-gray-500 dark:hover:text-white dark:bg-gray-800 dark:hover:bg-gray-700" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>

<script>
    // Auto-hide toast after 5 seconds
    setTimeout(() => {
        const toast = document.getElementById('toast-container');
        if (toast) {
            toast.remove();
        }
    }, 5000);
</script>
@endif

<script>
// Edit Treatment Modal.
function editTreatment(treatmentId) {
    // Inject form fields
    document.getElementById('editTreatmentFormContent').innerHTML = `
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Name *</label>
            <input type="text" name="name" required class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#8B7355] focus:border-[#8B7355] block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-[#8B7355] dark:focus:border-[#8B7355]">
        </div>
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Duration *</label>
            <input type="number" name="duration" required class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#8B7355] focus:border-[#8B7355] block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-[#8B7355] dark:focus:border-[#8B7355]">
        </div>
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Price *</label>
            <input type="number" name="price" step="0.01" required class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#8B7355] focus:border-[#8B7355] block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-[#8B7355] dark:focus:border-[#8B7355]">
        </div>
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Service Type *</label>
            <select name="service_type" required class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#8B7355] focus:border-[#8B7355] block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-[#8B7355] dark:focus:border-[#8B7355]">
                <option value="in_branch_only">In Branch Only</option>
                <option value="in_branch_and_home">In Branch & Home</option>
            </select>
        </div>
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Description</label>
            <textarea name="description" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#8B7355] focus:border-[#8B7355] block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-[#8B7355] dark:focus:border-[#8B7355]"></textarea>
        </div>
    `;

    const form = document.getElementById('editTreatmentForm');
    form.action = `/treatments/${treatmentId}`;

    // Fetch current treatment data
    fetch(`/treatments/${treatmentId}`) // <-- use show() route
        .then(response => response.json())
        .then(data => {
            form.querySelector('[name="name"]').value = data.name;
            form.querySelector('[name="duration"]').value = data.duration;
            form.querySelector('[name="price"]').value = data.price;
            form.querySelector('[name="service_type"]').value = data.service_type;
            form.querySelector('[name="description"]').value = data.description || '';
        });

    document.getElementById('editTreatmentModal').classList.remove('hidden');
}

function closeEditTreatmentModal() {
    document.getElementById('editTreatmentModal').classList.add('hidden');
}

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

// Initialize and start the clock
document.addEventListener('DOMContentLoaded', function() {
    // Initialize clock immediately
    updateClock();

    // Update clock every second
    setInterval(updateClock, 1000);
});
</script>
@endsection
