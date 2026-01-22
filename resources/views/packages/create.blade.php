@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">Add New Package</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create a new spa package with multiple treatments</p>
        </div>
        <a href="{{ route('services.index') }}"
            class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] border border-gray-300 rounded-lg hover:bg-[#7A6348] focus:ring-4 focus:ring-[#8B7355]/50 dark:bg-[#8B7355] dark:border-gray-600 dark:hover:bg-[#7A6348]">
            Back to Services
        </a>
    </div>

    <!-- Form Card -->
    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <form action="{{ route('packages.store') }}" method="POST">
            @csrf

            <!-- Name -->
            <div class="mb-6">
                <label for="name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                    Package Name *
                </label>
                <input type="text" id="name" name="name"
                       class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#8B7355] focus:border-[#8B7355] block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-[#8B7355] dark:focus:border-[#8B7355]"
                       placeholder="e.g., Ultimate Spa Package, Couple's Retreat"
                       required>
            </div>

            <!-- Duration & Price -->
            <div class="grid grid-cols-1 gap-6 mb-6 md:grid-cols-2">
                <!-- Duration -->
                <div>
                    <label for="duration" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                        Duration (minutes) *
                    </label>
                    <input type="number" id="duration" name="duration" min="5" max="600" step="5"
                           class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#8B7355] focus:border-[#8B7355] block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-[#8B7355] dark:focus:border-[#8B7355]"
                           placeholder="e.g., 180"
                           required>
                </div>

                <!-- Price -->
                <div>
                    <label for="price" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                        Package Price (₱) *
                    </label>
                    <input type="number" id="price" name="price" min="0" step="0.01"
                           class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#8B7355] focus:border-[#8B7355] block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-[#8B7355] dark:focus:border-[#8B7355]"
                           placeholder="e.g., 3500.00"
                           required>
                </div>
            </div>

            <!-- Included Treatments -->
            <div class="mb-6">
                <label for="included_treatments" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                    Included Treatments
                </label>
                <select id="included_treatments" name="included_treatments[]" multiple
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#8B7355] focus:border-[#8B7355] block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-[#8B7355] dark:focus:border-[#8B7355]">
                    @foreach($treatments as $treatment)
                        <option value="{{ $treatment->id }}">
                            {{ $treatment->name }} ({{ $treatment->duration }} mins - ₱{{ number_format($treatment->price, 2) }})
                        </option>
                    @endforeach
                </select>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Hold <kbd class="px-1 py-0.5 text-xs font-semibold text-gray-800 bg-gray-100 border border-gray-200 rounded dark:bg-gray-600 dark:text-gray-100 dark:border-gray-500">Ctrl</kbd> or <kbd class="px-1 py-0.5 text-xs font-semibold text-gray-800 bg-gray-100 border border-gray-200 rounded dark:bg-gray-600 dark:text-gray-100 dark:border-gray-500">Cmd</kbd> to select multiple treatments
                </p>
            </div>

            <!-- Description -->
            <div class="mb-6">
                <label for="description" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                    Description (Optional)
                </label>
                <textarea id="description" name="description" rows="4"
                          class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-[#8B7355] dark:focus:border-[#8B7355]"
                          placeholder="Describe what's included in this package, special benefits, or any terms..."></textarea>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                <a href="{{ route('services.index') }}"
                   class="px-4 py-2.5 text-sm font-medium text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-700 dark:focus:ring-gray-700">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2.5 text-sm font-medium text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348] focus:ring-4 focus:outline-none focus:ring-[#8B7355]/50 dark:bg-[#8B7355] dark:hover:bg-[#7A6348] dark:focus:ring-[#8B7355]/50">
                    Save Package
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
