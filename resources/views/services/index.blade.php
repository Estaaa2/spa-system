@extends('layouts.app')

@section('content')

@php
    $user = auth()->user();

    $canViewServices      = $user?->hasBranchPermission('view services') ?? false;

    $canCreateTreatments  = $user?->hasBranchPermission('create treatments') ?? false;
    $canEditTreatments    = $user?->hasBranchPermission('edit treatments') ?? false;
    $canDeleteTreatments  = $user?->hasBranchPermission('delete treatments') ?? false;

    $canCreatePackages    = $user?->hasBranchPermission('create packages') ?? false;
    $canEditPackages      = $user?->hasBranchPermission('edit packages') ?? false;
    $canDeletePackages    = $user?->hasBranchPermission('delete packages') ?? false;

    $canManageTreatments  = $canCreateTreatments || $canEditTreatments || $canDeleteTreatments;
    $canManagePackages    = $canCreatePackages || $canEditPackages || $canDeletePackages;

    $showTreatmentActions = $canEditTreatments || $canDeleteTreatments;
    $showPackageActions   = $canEditPackages || $canDeletePackages;

    $hasTreatments = $treatments->count() > 0;
    $hasPackages = $packages->count() > 0;
@endphp

<div class="p-6 mx-auto space-y-6 max-w-7xl">
    <!-- Services Header -->
    <x-page-header
        title="Services"
        subtitle="Manage your treatments and packages."
    />

    <!-- Treatments Section -->
    <div class="mt-8">
        <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-col gap-4 mb-4 lg:flex-row lg:items-center lg:justify-between">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Treatments</h2>
                <div class="flex flex-col flex-wrap items-stretch gap-2 sm:flex-row sm:items-center">
                    <span class="self-center text-sm text-gray-500 dark:text-gray-400 sm:self-auto">
                        {{ $treatments->count() }} treatment(s) available
                    </span>

                    @if($canManageTreatments)
                        @if($canCreateTreatments)
                            <button onclick="openAddTreatmentModal()"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348] whitespace-nowrap">
                                <i class="text-xs fas fa-plus"></i>
                                <span>Add Treatment</span>
                            </button>
                        @endif

                        @if($hasTreatments)
                            <a href="{{ route('treatments.export') }}"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm border rounded-lg bg-white text-[#8B7355] border-[#8B7355] hover:bg-[#F8F5F1] whitespace-nowrap dark:bg-gray-800 dark:text-[#D2B48C] dark:border-[#8B7355] dark:hover:bg-gray-700">
                                <i class="text-xs fas fa-download"></i>
                                <span>Export CSV</span>
                            </a>
                        @else
                            <button type="button"
                                onclick="showSpaToast('No treatments available to export. Please add treatments first.', 'error')"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm text-gray-400 bg-white border border-gray-300 rounded-lg cursor-not-allowed whitespace-nowrap dark:bg-gray-800 dark:text-gray-500 dark:border-gray-600">
                                <i class="text-xs fas fa-download"></i>
                                <span>Export CSV</span>
                            </button>
                        @endif

                        <form id="treatmentsImportForm" action="{{ route('treatments.import') }}" method="POST" enctype="multipart/form-data" class="inline">
                            @csrf
                            <input
                                type="file"
                                id="treatmentsCsvFile"
                                name="file"
                                accept=".csv"
                                required
                                class="hidden"
                                onchange="document.getElementById('treatmentsImportForm').submit()">
                            <button
                                type="button"
                                onclick="document.getElementById('treatmentsCsvFile').click()"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm border rounded-lg bg-white text-[#8B7355] border-[#8B7355] hover:bg-[#F8F5F1] whitespace-nowrap dark:bg-gray-800 dark:text-[#D2B48C] dark:border-[#8B7355] dark:hover:bg-gray-700">
                                <i class="text-xs fas fa-upload"></i>
                                <span>Import CSV</span>
                            </button>
                        </form>
                    @endif
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
                            @if($showTreatmentActions)
                                <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>
                            @endif
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
                            @if($showTreatmentActions)
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        @if($canEditTreatments)
                                            <button
                                                type="button"
                                                onclick="openEditTreatmentModal(this)"
                                                data-id="{{ $treatment->id }}"
                                                data-name="{{ $treatment->name }}"
                                                data-duration="{{ $treatment->duration }}"
                                                data-price="{{ $treatment->price }}"
                                                data-service-type="{{ $treatment->service_type }}"
                                                data-description="{{ $treatment->description }}"
                                                class="px-3 py-1 text-sm text-white bg-yellow-500 rounded hover:bg-yellow-600">
                                                Edit
                                            </button>
                                        @endif

                                        @if($canDeleteTreatments)
                                            <button
                                                type="button"
                                                onclick="openDeleteTreatmentModal({{ $treatment->id }}, '{{ addslashes($treatment->name) }}')"
                                                class="px-3 py-1 text-sm text-white bg-red-600 rounded hover:bg-red-700">
                                                Remove
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ $showTreatmentActions ? 5 : 4 }}" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
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
            <div class="flex flex-col gap-4 mb-4 lg:flex-row lg:items-center lg:justify-between">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Packages</h2>

                <div class="flex flex-col flex-wrap items-stretch gap-2 sm:flex-row sm:items-center">
                    <span class="self-center text-sm text-gray-500 dark:text-gray-400 sm:self-auto">
                        {{ $packages->count() }} package(s) available
                    </span>

                    @if($canManagePackages)
                        @if($canCreatePackages)
                            <button onclick="openAddPackageModal()"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348] whitespace-nowrap">
                                <i class="fas fa-plus"></i>
                                <span>Add Package</span>
                            </button>
                        @endif

                        @if($hasPackages)
                            <a href="{{ route('packages.export') }}"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm border rounded-lg bg-white text-[#8B7355] border-[#8B7355] hover:bg-[#F8F5F1] whitespace-nowrap dark:bg-gray-800 dark:text-[#D2B48C] dark:border-[#8B7355] dark:hover:bg-gray-700">
                                <i class="fas fa-download"></i>
                                <span>Export CSV</span>
                            </a>
                        @else
                            <button type="button"
                                onclick="showSpaToast('No packages available to export. Please add packages first.', 'error')"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm text-gray-400 bg-white border border-gray-300 rounded-lg cursor-not-allowed whitespace-nowrap dark:bg-gray-800 dark:text-gray-500 dark:border-gray-600">
                                <i class="fas fa-download"></i>
                                <span>Export CSV</span>
                            </button>
                        @endif

                        <form id="packagesImportForm" action="{{ route('packages.import') }}" method="POST" enctype="multipart/form-data" class="inline">
                            @csrf
                            <input
                                type="file"
                                id="packagesCsvFile"
                                name="file"
                                accept=".csv"
                                required
                                class="hidden"
                                onchange="document.getElementById('packagesImportForm').submit()">
                            <button
                                type="button"
                                onclick="document.getElementById('packagesCsvFile').click()"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm border rounded-lg bg-white text-[#8B7355] border-[#8B7355] hover:bg-[#F8F5F1] whitespace-nowrap dark:bg-gray-800 dark:text-[#D2B48C] dark:border-[#8B7355] dark:hover:bg-gray-700">
                                <i class="fas fa-upload"></i>
                                <span>Import CSV</span>
                            </button>
                        </form>
                    @endif
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
                            @if($showPackageActions)
                                <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>
                            @endif
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
                                @if($package->treatments->count() > 0)
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($package->treatments->take(3) as $treatment)
                                            <span class="px-2 py-1 text-xs text-blue-800 bg-blue-100 rounded dark:bg-blue-900 dark:text-blue-300">
                                                {{ $treatment->name }}
                                            </span>
                                        @endforeach
                                        @if($package->treatments->count() > 3)
                                            <span class="px-2 py-1 text-xs text-gray-800 bg-gray-100 rounded dark:bg-gray-700 dark:text-gray-300">
                                                +{{ $package->treatments->count() - 3 }} more
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-sm text-gray-500 dark:text-gray-400">No treatments included</span>
                                @endif
                            </td>
                            @if($showPackageActions)
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        @if($canEditPackages)
                                            <button
                                                type="button"
                                                onclick="openEditPackageModal(this)"
                                                data-id="{{ $package->id }}"
                                                data-name="{{ $package->name }}"
                                                data-duration="{{ $package->duration }}"
                                                data-price="{{ $package->price }}"
                                                data-description="{{ $package->description }}"
                                                data-treatments="{{ $package->treatments->pluck('id')->join(',') }}"
                                                class="px-3 py-1 text-sm text-white bg-yellow-500 rounded hover:bg-yellow-600">
                                                Edit
                                            </button>
                                        @endif

                                        @if($canDeletePackages)
                                            <button
                                                type="button"
                                                onclick="openDeletePackageModal({{ $package->id }}, '{{ addslashes($package->name) }}')"
                                                class="px-3 py-1 text-sm text-white bg-red-600 rounded hover:bg-red-700">
                                                Remove
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ $showPackageActions ? 5 : 4 }}" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
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
                <div class="p-6 rounded-lg shadow-lg bg-gradient-to-r from-[#8B7355] to-[#6F5430] text-white">
                    <p class="text-xs tracking-widest opacity-80">SERVICE TYPES</p>
                    <p class="text-lg font-semibold">DISTRIBUTION</p>
                    <div class="mt-4 space-y-2">
                        @php $serviceTypeCounts = $treatments->groupBy('service_type')->map->count(); @endphp
                        @foreach($serviceTypeCounts->take(3) as $type => $count)
                        <div class="flex items-center justify-between">
                            <span class="text-sm">{{ ucwords(str_replace('_', ' ', $type)) }}</span>
                            <span class="text-sm font-medium">{{ $count }}</span>
                        </div>
                        @endforeach
                        @if($serviceTypeCounts->count() > 3)
                        <div class="pt-2 mt-2 border-t border-white/20">
                            <span class="text-sm opacity-80">+{{ $serviceTypeCounts->count() - 3 }} more types</span>
                        </div>
                        @endif
                    </div>
                </div>
                <div class="p-6 bg-white border rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
                    <p class="text-xs tracking-widest text-gray-500 dark:text-gray-400">PRICE</p>
                    <p class="text-lg font-semibold text-gray-800 dark:text-white">RANGE</p>
                    <div class="mt-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Lowest</span>
                            <span class="text-sm font-medium text-gray-800 dark:text-white">₱{{ $treatments->min('price') ? number_format($treatments->min('price'), 0) : '0' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Highest</span>
                            <span class="text-sm font-medium text-gray-800 dark:text-white">₱{{ $treatments->max('price') ? number_format($treatments->max('price'), 0) : '0' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Average</span>
                            <span class="text-sm font-medium text-gray-800 dark:text-white">₱{{ $treatments->avg('price') ? number_format($treatments->avg('price'), 0) : '0' }}</span>
                        </div>
                    </div>
                </div>
                <div class="p-6 rounded-lg shadow-lg bg-gradient-to-r from-[#8B7355] to-[#6F5430] text-white">
                    <p class="text-xs tracking-widest opacity-80">AVERAGE</p>
                    <p class="text-lg font-semibold">DURATION</p>
                    <div class="mt-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm">Treatments</span>
                            <span class="text-sm font-medium">{{ $treatments->avg('duration') ? round($treatments->avg('duration')) : '0' }} mins</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm">Packages</span>
                            <span class="text-sm font-medium">{{ $packages->avg('duration') ? round($packages->avg('duration')) : '0' }} mins</span>
                        </div>
                        <div class="pt-2 mt-2 border-t border-white/20">
                            <span class="text-sm opacity-80">Total Services: {{ $treatments->count() + $packages->count() }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ADD TREATMENT MODAL --}}
@if($canCreateTreatments)
<div id="addTreatmentModal" class="fixed inset-0 z-50 items-start justify-center hidden px-4 pt-16 bg-black bg-opacity-50">
    <div class="w-full max-w-md p-6 bg-white rounded-lg shadow-xl dark:bg-gray-800">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Add Treatment</h2>
            <button type="button" onclick="closeAddTreatmentModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form action="{{ route('treatments.store') }}" method="POST" id="addTreatmentForm" novalidate>
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Name *</label>
                    <input type="text" name="name" id="add_treatment_name" required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355]">
                    <p class="hidden mt-1 text-xs text-red-500" id="add_name_error">Treatment name is required.</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Duration *</label>
                        <select name="duration" id="add_treatment_duration" required
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355]">
                            <option value="" disabled selected>Select duration</option>
                            <option value="30">30 mins</option>
                            <option value="60">60 mins</option>
                            <option value="90">90 mins</option>
                            <option value="120">120 mins</option>
                        </select>
                        <p class="hidden mt-1 text-xs text-red-500" id="add_duration_error">Please select a duration.</p>
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Price (₱) *</label>
                        <input type="number" name="price" id="add_treatment_price"
                            step="0.01" min="300" max="1400" required placeholder="300 – 1,400"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355]">
                        <p class="hidden mt-1 text-xs text-red-500" id="add_price_error">Price must be between ₱300 and ₱1,400.</p>
                    </div>
                </div>

                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Service Type *</label>
                    <select name="service_type" required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355]">
                        <option value="in_branch_only">In Branch Only</option>
                        <option value="in_branch_and_home">In Branch & Home</option>
                    </select>
                </div>

                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                    <textarea name="description" rows="3"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355]"></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button type="button" onclick="closeAddTreatmentModal()"
                    class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 text-sm text-white bg-[#8B7355] rounded hover:bg-[#7A6348]">
                    Add Treatment
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- EDIT TREATMENT MODAL --}}
@if($canEditTreatments)
<div id="editTreatmentModal" class="fixed inset-0 z-50 items-start justify-center hidden px-4 pt-16 bg-black bg-opacity-50">
    <div class="w-full max-w-md p-6 bg-white rounded-lg shadow-xl dark:bg-gray-800">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Edit Treatment</h2>
            <button type="button" onclick="closeEditTreatmentModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="editTreatmentForm" method="POST">
            @csrf
            @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Name *</label>
                    <input type="text" id="edit_treatment_name" name="name" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355]">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Duration *</label>
                        <select name="duration" id="edit_treatment_duration" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355]">
                            <option value="30">30 mins</option>
                            <option value="60">60 mins</option>
                            <option value="90">90 mins</option>
                            <option value="120">120 mins</option>
                        </select>
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Price (₱) *</label>
                        <input type="number" id="edit_treatment_price" name="price" step="0.01" required min="300" max="1400" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355]">
                    </div>
                </div>

                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Service Type *</label>
                    <select name="service_type" id="edit_treatment_service_type" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355]">
                        <option value="in_branch_only">In Branch Only</option>
                        <option value="in_branch_and_home">In Branch & Home</option>
                    </select>
                </div>

                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                    <textarea id="edit_treatment_description" name="description" rows="3" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355]"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button type="button" onclick="closeEditTreatmentModal()" class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm text-white bg-[#8B7355] rounded hover:bg-[#7A6348]">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- DELETE TREATMENT MODAL --}}
@if($canDeleteTreatments)
<div id="deleteTreatmentModal" class="fixed inset-0 z-50 items-center justify-center hidden bg-black bg-opacity-50">
    <div class="w-full max-w-md p-6 mx-4 bg-white rounded-lg dark:bg-gray-800">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Remove Treatment</h2>
            <button type="button" onclick="closeDeleteTreatmentModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <p class="text-gray-500 dark:text-gray-400">Are you sure you want to remove <span id="deleteTreatmentName" class="font-semibold text-gray-800 dark:text-white"></span>? This action cannot be undone.</p>
        <div class="flex justify-end gap-2 mt-6">
            <button type="button" onclick="closeDeleteTreatmentModal()" class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200">Cancel</button>
            <form id="deleteTreatmentForm" method="POST">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-sm text-white bg-red-600 rounded hover:bg-red-700">Yes, Remove</button>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ADD PACKAGE MODAL --}}
@if($canCreatePackages)
<div id="addPackageModal" class="fixed inset-0 z-50 items-start justify-center hidden px-4 pt-10 overflow-y-auto bg-black bg-opacity-50">
    <div class="w-full max-w-lg p-6 my-8 bg-white rounded-lg dark:bg-gray-800">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Add Package</h2>
            <button type="button" onclick="closeAddPackageModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form action="{{ route('packages.store') }}" method="POST" id="addPackageForm">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Package Name *</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355]">
                </div>

                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Included Treatments</label>
                    <div id="addIncludedTreatments" class="p-3 space-y-2 overflow-y-auto border border-gray-300 rounded-md max-h-48 dark:border-gray-600 dark:bg-gray-700">
                        @foreach($treatments as $treatment)
                        <label class="flex items-center gap-3">
                            <input
                                type="checkbox"
                                name="included_treatments[]"
                                value="{{ $treatment->id }}"
                                data-duration="{{ $treatment->duration }}"
                                data-price="{{ $treatment->price }}"
                                class="rounded border-gray-300 text-[#8B7355] focus:ring-[#8B7355] package-treatment-checkbox"
                            >
                            <span class="text-sm text-gray-700 dark:text-gray-200">
                                {{ $treatment->name }} ({{ $treatment->duration }} mins — ₱{{ number_format($treatment->price, 2) }})
                            </span>
                        </label>
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Select one or more treatments</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Duration (mins) *</label>
                        <input type="number" name="duration" id="addDuration" required min="1" readonly class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355] bg-gray-100 cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Price (₱) *</label>
                        <input type="number" name="price" id="addPrice" step="0.01" required min="0" readonly class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355] bg-gray-100 cursor-not-allowed">
                    </div>
                </div>

                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                    <textarea name="description" rows="3" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355]"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button type="button" onclick="closeAddPackageModal()" class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm text-white bg-[#8B7355] rounded hover:bg-[#7A6348]">Add Package</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- EDIT PACKAGE MODAL --}}
@if($canEditPackages)
<div id="editPackageModal" class="fixed inset-0 z-50 items-start justify-center hidden px-4 pt-10 overflow-y-auto bg-black bg-opacity-50">
    <div class="w-full max-w-lg p-6 my-8 bg-white rounded-lg dark:bg-gray-800">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Edit Package</h2>
            <button type="button" onclick="closeEditPackageModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="editPackageForm" method="POST">
            @csrf
            @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Package Name *</label>
                    <input type="text" id="edit_package_name" name="name" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355]">
                </div>

                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Included Treatments</label>
                    <div id="editIncludedTreatments" class="p-3 space-y-2 overflow-y-auto border border-gray-300 rounded-md max-h-48 dark:border-gray-600 dark:bg-gray-700">
                        @foreach($treatments as $treatment)
                        <label class="flex items-center gap-3">
                            <input
                                type="checkbox"
                                name="included_treatments[]"
                                value="{{ $treatment->id }}"
                                data-duration="{{ $treatment->duration }}"
                                data-price="{{ $treatment->price }}"
                                class="rounded border-gray-300 text-[#8B7355] focus:ring-[#8B7355] package-treatment-checkbox"
                            >
                            <span class="text-sm text-gray-700 dark:text-gray-200">
                                {{ $treatment->name }} ({{ $treatment->duration }} mins — ₱{{ number_format($treatment->price, 2) }})
                            </span>
                        </label>
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Select one or more treatments</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Duration (mins) *</label>
                        <input type="number" id="edit_package_duration" name="duration" required min="1" readonly class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355] bg-gray-100 dark:bg-gray-600 cursor-not-allowed">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Auto-calculated from selected treatments</p>
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Price (₱) *</label>
                        <input type="number" id="edit_package_price" name="price" step="0.01" required min="0" readonly class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355] bg-gray-100 dark:bg-gray-600 cursor-not-allowed">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Auto-calculated from selected treatments</p>
                    </div>
                </div>

                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                    <textarea id="edit_package_description" name="description" rows="3" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#8B7355]"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button type="button" onclick="closeEditPackageModal()" class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-md hover:bg-[#7A6348]">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- DELETE PACKAGE MODAL --}}
@if($canDeletePackages)
<div id="deletePackageModal" class="fixed inset-0 z-50 items-center justify-center hidden bg-black bg-opacity-50">
    <div class="w-full max-w-md p-6 mx-4 bg-white rounded-lg dark:bg-gray-800">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Remove Package</h2>
            <button type="button" onclick="closeDeletePackageModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <p class="text-gray-500 dark:text-gray-400">Are you sure you want to remove <span id="deletePackageName" class="font-semibold text-gray-800 dark:text-white"></span>? This action cannot be undone.</p>
        <div class="flex justify-end gap-2 mt-6">
            <button type="button" onclick="closeDeletePackageModal()" class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200">Cancel</button>
            <form id="deletePackageForm" method="POST">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-sm text-white bg-red-600 rounded hover:bg-red-700">Yes, Remove</button>
            </form>
        </div>
    </div>
</div>
@endif

<script>
    function openAddTreatmentModal() {
        const modal = document.getElementById('addTreatmentModal');
        if (modal) {
            // Reset form and errors on open
            const form = document.getElementById('addTreatmentForm');
            if (form) form.reset();

            ['add_name_error', 'add_duration_error', 'add_price_error'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.classList.add('hidden');
            });
            ['add_treatment_name', 'add_treatment_duration', 'add_treatment_price'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.classList.remove('border-red-500');
                    el.classList.add('border-gray-300');
                }
            });

            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }
    }

    function closeAddTreatmentModal() {
        const modal = document.getElementById('addTreatmentModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    }

    function openEditTreatmentModal(btn) {
        const d = btn.dataset;
        document.getElementById('edit_treatment_name').value = d.name || '';
        document.getElementById('edit_treatment_duration').value = d.duration || '';
        document.getElementById('edit_treatment_price').value = d.price || '';
        document.getElementById('edit_treatment_service_type').value = d.serviceType || '';
        document.getElementById('edit_treatment_description').value = d.description || '';
        document.getElementById('editTreatmentForm').action = '/treatments/' + d.id;

        const modal = document.getElementById('editTreatmentModal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }
    }

    function closeEditTreatmentModal() {
        const modal = document.getElementById('editTreatmentModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    }

    function openDeleteTreatmentModal(id, name) {
        document.getElementById('deleteTreatmentName').textContent = name;
        document.getElementById('deleteTreatmentForm').action = '/treatments/' + id;

        const modal = document.getElementById('deleteTreatmentModal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }
    }

    function closeDeleteTreatmentModal() {
        const modal = document.getElementById('deleteTreatmentModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    }

    function openAddPackageModal() {
        // Reset checkboxes
        document.querySelectorAll('#addIncludedTreatments input[type="checkbox"]').forEach(cb => {
            cb.checked = false;
        });
        const form = document.getElementById('addPackageForm');
        if (form) form.reset();
        updateAddPackageTotals();

        const modal = document.getElementById('addPackageModal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }
    }

    function closeAddPackageModal() {
        const modal = document.getElementById('addPackageModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    }

    function updateAddPackageTotals() {
        const checkboxes = document.querySelectorAll('#addIncludedTreatments input[type="checkbox"]:checked');
        let totalDuration = 0;
        let totalPrice = 0;

        checkboxes.forEach(cb => {
            totalDuration += parseInt(cb.dataset.duration) || 0;
            totalPrice += parseFloat(cb.dataset.price) || 0;
        });

        const durationField = document.getElementById('addDuration');
        const priceField = document.getElementById('addPrice');

        if (durationField) durationField.value = totalDuration;
        if (priceField) priceField.value = totalPrice.toFixed(2);
    }

    function updateEditPackageTotals() {
        const checkboxes = document.querySelectorAll('#editIncludedTreatments input[type="checkbox"]:checked');
        let totalDuration = 0;
        let totalPrice = 0;

        checkboxes.forEach(cb => {
            totalDuration += parseInt(cb.dataset.duration) || 0;
            totalPrice += parseFloat(cb.dataset.price) || 0;
        });

        const durationField = document.getElementById('edit_package_duration');
        const priceField = document.getElementById('edit_package_price');

        if (durationField) durationField.value = totalDuration;
        if (priceField) priceField.value = totalPrice.toFixed(2);
    }

    function openEditPackageModal(btn) {
        const d = btn.dataset;

        document.getElementById('edit_package_name').value = d.name || '';
        document.getElementById('edit_package_description').value = d.description || '';

        const selectedIds = d.treatments ? d.treatments.split(',').map(Number) : [];

        document.querySelectorAll('#editIncludedTreatments input[type="checkbox"]').forEach(cb => {
            cb.checked = selectedIds.includes(parseInt(cb.value));
        });

        document.getElementById('editPackageForm').action = '/packages/' + d.id;

        const modal = document.getElementById('editPackageModal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }

        updateEditPackageTotals();
    }

    function closeEditPackageModal() {
        const modal = document.getElementById('editPackageModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    }

    function openDeletePackageModal(id, name) {
        document.getElementById('deletePackageName').textContent = name;
        document.getElementById('deletePackageForm').action = '/packages/' + id;

        const modal = document.getElementById('deletePackageModal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }
    }

    function closeDeletePackageModal() {
        const modal = document.getElementById('deletePackageModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    }

    // Validation helper
    function showFieldError(fieldId, errorId, show) {
        const field = document.getElementById(fieldId);
        const error = document.getElementById(errorId);
        if (field && error) {
            if (show) {
                field.classList.add('border-red-500');
                field.classList.remove('border-gray-300');
                error.classList.remove('hidden');
            } else {
                field.classList.remove('border-red-500');
                field.classList.add('border-gray-300');
                error.classList.add('hidden');
            }
        }
    }

    // Make sure modals are hidden on page load
    document.addEventListener('DOMContentLoaded', function () {
        // Hide all modals on page load
        const modals = ['addTreatmentModal', 'editTreatmentModal', 'deleteTreatmentModal', 'addPackageModal', 'editPackageModal', 'deletePackageModal'];
        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
                modal.style.display = 'none';
            }
        });

        // ── Add Treatment Form Validation ─────────────────────────────
        const addForm = document.getElementById('addTreatmentForm');
        if (addForm) {
            addForm.addEventListener('submit', function (e) {
                e.preventDefault();

                let valid = true;

                // Name
                const name = document.getElementById('add_treatment_name').value.trim();
                showFieldError('add_treatment_name', 'add_name_error', name === '');
                if (name === '') valid = false;

                // Duration
                const duration = document.getElementById('add_treatment_duration').value;
                showFieldError('add_treatment_duration', 'add_duration_error', duration === '');
                if (duration === '') valid = false;

                // Price
                const price = parseFloat(document.getElementById('add_treatment_price').value);
                const priceInvalid = isNaN(price) || price < 300 || price > 1400;
                showFieldError('add_treatment_price', 'add_price_error', priceInvalid);
                if (priceInvalid) valid = false;

                if (valid) this.submit();
            });

            // Live feedback — price
            const priceInput = document.getElementById('add_treatment_price');
            if (priceInput) {
                priceInput.addEventListener('input', function () {
                    const price = parseFloat(this.value);
                    const invalid = isNaN(price) || price < 300 || price > 1400;
                    if (this.value !== '') {
                        showFieldError('add_treatment_price', 'add_price_error', invalid);
                    }
                });
            }

            // Live feedback — name
            const nameInput = document.getElementById('add_treatment_name');
            if (nameInput) {
                nameInput.addEventListener('input', function () {
                    if (this.value.trim() !== '') {
                        showFieldError('add_treatment_name', 'add_name_error', false);
                    }
                });
            }

            // Live feedback — duration
            const durationSelect = document.getElementById('add_treatment_duration');
            if (durationSelect) {
                durationSelect.addEventListener('change', function () {
                    if (this.value !== '') {
                        showFieldError('add_treatment_duration', 'add_duration_error', false);
                    }
                });
            }
        }

        // ── Package Checkbox Listeners ────────────────────────────
        const addCheckboxes = document.querySelectorAll('#addIncludedTreatments input[type="checkbox"]');
        addCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateAddPackageTotals);
        });

        const editCheckboxes = document.querySelectorAll('#editIncludedTreatments input[type="checkbox"]');
        editCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateEditPackageTotals);
        });
    });
</script>
@endsection
