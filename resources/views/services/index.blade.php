@extends('layouts.app')

@section('title', 'Services')
@section('content')

@php
    use Illuminate\Support\Str;

    $user = auth()->user();

    // ── STOP-AND-FLAG ZONE ───────────────────────────────────────────────────
    // These eight gates are carried over byte-identical from the previous file.
    // $canViewServices is computed and never read (audit X1). It is left in
    // place deliberately: removing it, or wiring it into a view-level check,
    // is an RBAC change and is out of scope for this rewrite.
    $canViewServices      = $user?->hasBranchPermission('view services') ?? false;
    $canViewPromos        = $user?->hasBranchPermission('view promos') ?? false;

    $canCreateTreatments  = $user?->hasBranchPermission('create treatments') ?? false;
    $canEditTreatments    = $user?->hasBranchPermission('edit treatments') ?? false;
    $canDeleteTreatments  = $user?->hasBranchPermission('delete treatments') ?? false;

    $canCreatePackages    = $user?->hasBranchPermission('create packages') ?? false;
    $canEditPackages      = $user?->hasBranchPermission('edit packages') ?? false;
    $canDeletePackages    = $user?->hasBranchPermission('delete packages') ?? false;

    $canManageTreatments  = $canCreateTreatments || $canEditTreatments || $canDeleteTreatments;
    $canManagePackages    = $canCreatePackages   || $canEditPackages   || $canDeletePackages;

    $showTreatmentActions = $canEditTreatments || $canDeleteTreatments;
    $showPackageActions   = $canEditPackages   || $canDeletePackages;
    // ── END STOP-AND-FLAG ZONE ───────────────────────────────────────────────

    $hasTreatments = $treatments->count() > 0;
    $hasPackages   = $packages->count() > 0;

    // One source for the upload limit — used in the hint text and the JS
    // pre-check below. ⚠️ This does NOT change server-side validation.
    $maxImageMb = 5;

    // Button classes live here, in one place, so the same string can be reused
    // across both section toolbars and both action columns without drifting.
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        // Main affirmative action for each section.
        'primary'   => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        // Non-destructive alternatives — row Edit, modal Cancel.
        'secondary' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                     . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        // Outlined brand, used for the CSV/promo toolbar links.
        'outline'   => $btnBase . ' border border-[#8B7355]/40 bg-white text-[#8B7355] hover:bg-[#8B7355]/5 '
                     . 'dark:bg-gray-800 dark:text-[#C4A97D] dark:border-[#C4A97D]/30 dark:hover:bg-[#C4A97D]/10',
        // Solid red is reserved for actions that permanently delete a record.
        'danger'    => $btnBase . ' bg-red-700 text-white hover:bg-red-800',
        // Neutral grey dismiss inside modals.
        'dismiss'   => $btnBase . ' bg-gray-200 text-gray-700 hover:bg-gray-300 '
                     . 'dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500',
        // Unavailable. Greyed out but still focusable, so a keyboard user can
        // reach it and be told why — a real disabled attribute would take it
        // out of the tab order and the explanation with it.
        'disabled'  => $btnBase . ' border border-gray-300 bg-gray-100 text-gray-400 cursor-not-allowed '
                     . 'dark:border-gray-700 dark:bg-gray-900 dark:text-gray-600',
    ];

    // Shared input recipe. focus: (not focus-visible:) is correct for text
    // controls — the ring should show on click as well as on keyboard focus.
    $input = 'w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl '
           . 'dark:border-gray-600 dark:bg-gray-700 dark:text-white '
           . 'focus:outline-none focus:ring-2 focus:ring-[#8B7355]';

    $inputReadonly = $input . ' bg-gray-100 cursor-not-allowed dark:bg-gray-900 dark:text-gray-400';

    $label = 'block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300';
    $hint  = 'mt-1 text-xs text-gray-500 dark:text-gray-400';
    $err   = 'hidden mt-1 text-xs text-red-600 dark:text-red-400';

    $th = 'px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400';

    // The two CSV help modals were near-identical copies. They are now one
    // template driven by this array.
    $importGuides = [
        [
            'id'      => 'treatmentImportHelpModal',
            'title'   => 'Treatments CSV Format',
            'columns' => 'name, duration, price, service_type, description',
            'fields'  => [
                ['name',         'required'],
                ['duration',     'required, minutes (whole number)'],
                ['price',        'required, ₱300–₱1,400'],
                ['service_type', 'required, exactly: in_branch_only or in_branch_and_home'],
                ['description',  'optional'],
            ],
            'example'  => 'Swedish Massage, 60, 800, in_branch_only, Relaxing full-body massage',
            'image'    => 'images/treatmentexcelsample.png',
            'imageAlt' => 'Example of the treatments CSV file opened in a spreadsheet, showing the name, duration, price, service_type, and description columns',
            'sample'   => route('treatments.sample-csv'),
        ],
        [
            'id'      => 'packageImportHelpModal',
            'title'   => 'Packages CSV Format',
            'columns' => 'name, total_duration, price, description',
            'fields'  => [
                ['name',           'required'],
                ['total_duration', 'optional, minutes (whole number)'],
                ['price',          'required, number'],
                ['description',    'optional'],
            ],
            'example'  => 'Relax & Renew Package, 120, 1500, Full body massage plus facial combo',
            'image'    => 'images/packageexcelsample.png',
            'imageAlt' => 'Example of the packages CSV file opened in a spreadsheet, showing the name, total_duration, price, and description columns',
            'sample'   => route('packages.sample-csv'),
        ],
    ];

    // Every modal id the shared open/close/Escape machinery needs to know about.
    $modalIds = array_values(array_filter([
        $canCreateTreatments ? 'addTreatmentModal'    : null,
        $canEditTreatments   ? 'editTreatmentModal'   : null,
        $canDeleteTreatments ? 'deleteTreatmentModal' : null,
        $canCreatePackages   ? 'addPackageModal'      : null,
        $canEditPackages     ? 'editPackageModal'     : null,
        $canDeletePackages   ? 'deletePackageModal'   : null,
        'treatmentImportHelpModal',
        'packageImportHelpModal',
    ]));

    // Backdrop click only dismisses these — on a form modal a stray tap would
    // throw away everything the user typed.
    $backdropDismissIds = array_values(array_filter([
        $canDeleteTreatments ? 'deleteTreatmentModal' : null,
        $canDeletePackages   ? 'deletePackageModal'   : null,
        'treatmentImportHelpModal',
        'packageImportHelpModal',
    ]));
@endphp

@php
    // Aggregates for the summary cards. Each is computed once here rather than
    // twice inline (once for the ternary test, once for the output).
    $serviceTypeCounts = $treatments->groupBy('service_type')->map->count();
    $minPrice        = $treatments->min('price');
    $maxPrice        = $treatments->max('price');
    $avgPrice        = $treatments->avg('price');
    $avgTreatmentMin = $treatments->avg('duration');
    $avgPackageMin   = $packages->avg('duration');

    $summaryCard = 'p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700';
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">

    <x-page-header
        title="Services"
        subtitle="Manage your treatments and packages."
    >
        {{-- Promos are page-scoped, not section-scoped, so this sits in the
             header's right slot instead of being repeated in both section
             toolbars. The permission test is the union of what the two old
             copies were gated on, so nobody gains or loses access to it. --}}
        <x-slot name="right">
            @if($canViewPromos && ($canManageTreatments || $canManagePackages))
                <a href="{{ route('promos.index') }}"
                   class="inline-flex items-center gap-2 min-h-[44px] px-4 py-2 text-sm font-semibold text-white rounded-xl
                          bg-gradient-to-r from-[#8B7355] to-[#6F5430] shadow-sm hover:opacity-90 transition-opacity active:translate-y-0.5
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2
                          dark:focus-visible:ring-offset-gray-900">
                    <i class="text-xs fa-solid fa-tag" aria-hidden="true"></i>
                    Manage Promos
                </a>
            @endif
        </x-slot>
    </x-page-header>

    {{-- ══════════════════════════════════════════════════
         SUMMARY — same card shape as appointments: label on top,
         value and caption on one row that stacks below sm.
         ══════════════════════════════════════════════════ --}}
    @php
        $kpiLabel   = 'text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400';
        $kpiRow     = 'flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between';
        $kpiValue   = 'text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white';
        $kpiCaption = 'text-xs text-gray-500 sm:text-sm dark:text-gray-400';
    @endphp

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <div class="{{ $summaryCard }}">
            <p class="{{ $kpiLabel }}">Treatments</p>
            <div class="{{ $kpiRow }}">
                <p class="{{ $kpiValue }}">{{ $treatments->count() }}</p>
                <span class="{{ $kpiCaption }}">
                    {{ $serviceTypeCounts->count() }} service type{{ $serviceTypeCounts->count() === 1 ? '' : 's' }}
                </span>
            </div>
        </div>

        <div class="{{ $summaryCard }}">
            <p class="{{ $kpiLabel }}">Packages</p>
            <div class="{{ $kpiRow }}">
                <p class="{{ $kpiValue }}">{{ $packages->count() }}</p>
                <span class="{{ $kpiCaption }}">Bundled offers</span>
            </div>
        </div>

        {{-- FLAG (audit D6): 0dp here vs 2dp in the tables below.
             Finance-adjacent, left exactly as it was. --}}
        <div class="{{ $summaryCard }}">
            <p class="{{ $kpiLabel }}">Average Price</p>
            <div class="{{ $kpiRow }}">
                <p class="{{ $kpiValue }}">₱{{ $avgPrice ? number_format($avgPrice, 0) : '0' }}</p>
                <span class="{{ $kpiCaption }}">
                    ₱{{ $minPrice ? number_format($minPrice, 0) : '0' }}–₱{{ $maxPrice ? number_format($maxPrice, 0) : '0' }}
                </span>
            </div>
        </div>

        <div class="{{ $summaryCard }}">
            <p class="{{ $kpiLabel }}">Average Duration</p>
            <div class="{{ $kpiRow }}">
                <p class="{{ $kpiValue }}">{{ $avgTreatmentMin ? round($avgTreatmentMin) : '0' }} mins</p>
                <span class="{{ $kpiCaption }}">
                    Packages {{ $avgPackageMin ? round($avgPackageMin) : '0' }} mins
                </span>
            </div>
        </div>
    </div>

    {{-- The service-type breakdown is a list, not a single figure, so it sits
         below the stat row rather than inside it. Hidden when there are none. --}}
    @if($serviceTypeCounts->count() > 0)
        <div class="{{ $summaryCard }}">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
                <p class="{{ $kpiLabel }}">Service Type Distribution</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($serviceTypeCounts as $type => $count)
                        <span class="inline-flex items-center gap-2 px-3 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-200">
                            {{ ucwords(str_replace('_', ' ', $type)) }}
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $count }}</span>
                        </span>
                    @endforeach
                    <span class="inline-flex items-center gap-2 px-3 py-1 text-xs font-medium rounded-full bg-[#8B7355]/10 text-[#6F5430] dark:bg-[#C4A97D]/10 dark:text-[#C4A97D]">
                        Total services
                        <span class="font-semibold">{{ $treatments->count() + $packages->count() }}</span>
                    </span>
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════
         TREATMENTS
         ══════════════════════════════════════════════════ --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-col gap-4 px-4 py-4 border-b border-gray-200 sm:px-6 lg:flex-row lg:items-start lg:justify-between dark:border-gray-700">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Treatments</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $treatments->count() }} treatment{{ $treatments->count() === 1 ? '' : 's' }} available in this branch.
                </p>
            </div>

            @if($canManageTreatments)
                <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    @if($canCreateTreatments)
                        <button type="button" onclick="openAddTreatmentModal()" class="{{ $btn['primary'] }} whitespace-nowrap">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            <span>Add Treatment</span>
                        </button>
                    @endif

                    {{-- The old file rendered a grey button that looked disabled but
                         was live and fired a toast. This one is actually disabled, so
                         the greyed-out look and the behaviour agree. sr-only text
                         carries the reason for screen readers. --}}
                    @if($hasTreatments)
                        <a href="{{ route('treatments.export') }}" class="{{ $btn['outline'] }} whitespace-nowrap">
                            <i class="fa-solid fa-file-export" aria-hidden="true"></i>
                            <span>Export CSV</span>
                        </a>
                    @else
                        <button type="button" aria-disabled="true"
                                onclick="explainUnavailable('treatmentsExportReason')"
                                aria-describedby="treatmentsExportReason"
                                class="{{ $btn['disabled'] }} whitespace-nowrap">
                            <i class="fa-solid fa-file-export" aria-hidden="true"></i>
                            <span>Export CSV</span>
                        </button>
                        <p id="treatmentsExportReason" role="status"
                           class="self-center hidden text-sm text-gray-500 dark:text-gray-400">
                            Add at least one treatment before exporting.
                        </p>
                    @endif

                    <form id="treatmentsImportForm" action="{{ route('treatments.import') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="file" id="treatmentsCsvFile" name="file" accept=".csv" required class="hidden">
                        <button type="button" onclick="triggerCsvPicker('treatmentsCsvFile')" class="{{ $btn['outline'] }} w-full whitespace-nowrap">
                            <i class="fa-solid fa-file-import" aria-hidden="true"></i>
                            <span>Import CSV</span>
                        </button>
                    </form>

                    {{-- Labelled rather than icon-only: the old tooltip was not an
                         accessible name, and the icon alone didn't say what it did. --}}
                    <button type="button" onclick="openModalById('treatmentImportHelpModal')"
                            class="{{ $btn['outline'] }} whitespace-nowrap">
                        <i class="fa-solid fa-file-csv" aria-hidden="true"></i>
                        <span>CSV Format Guide</span>
                    </button>
                </div>
            @endif
        </div>

        <div class="md:overflow-x-auto">
            <table role="table" class="rt min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" class="{{ $th }}">Service Name</th>
                        <th role="columnheader" class="{{ $th }}">Duration</th>
                        <th role="columnheader" class="{{ $th }}">Price</th>
                        <th role="columnheader" class="{{ $th }}">Service Type</th>
                        @if($showTreatmentActions)
                            <th role="columnheader" class="{{ $th }}">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody role="rowgroup" class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse($treatments as $treatment)
                        <tr role="row" class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td role="cell" data-label="Service Name" class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-[#8B7355] flex items-center justify-center text-white overflow-hidden shrink-0">
                                        @if($treatment->image_url)
                                            <img src="{{ $treatment->image_url }}" alt="" class="object-cover w-full h-full">
                                        @else
                                            <i class="fa-solid fa-spa" aria-hidden="true"></i>
                                        @endif
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
                            <td role="cell" data-label="Duration" class="px-6 py-4">
                                <span class="inline-flex items-center px-3 py-1 text-xs font-medium text-gray-800 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-200">
                                    {{ $treatment->duration }} mins
                                </span>
                            </td>
                            <td role="cell" data-label="Price" class="px-6 py-4">
                                {{-- FLAG (audit D6): 2dp here, 0dp in the summary card below.
                                     Finance-adjacent, left exactly as it was. --}}
                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                    ₱{{ number_format($treatment->price, 2) }}
                                </span>
                            </td>
                            <td role="cell" data-label="Service Type" class="px-6 py-4">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $treatment->service_type_label }}
                                </span>
                            </td>
                            @if($showTreatmentActions)
                                <td role="cell" data-label="Actions" class="px-6 py-4 rt-actions">
                                    <div class="flex flex-wrap gap-2">
                                        @if($canEditTreatments)
                                            <button type="button"
                                                    onclick="openEditTreatmentModal(this)"
                                                    data-id="{{ $treatment->id }}"
                                                    data-name="{{ $treatment->name }}"
                                                    data-duration="{{ $treatment->duration }}"
                                                    data-price="{{ $treatment->price }}"
                                                    data-service-type="{{ $treatment->service_type }}"
                                                    data-description="{{ $treatment->description }}"
                                                    data-image="{{ $treatment->image_url }}"
                                                    class="{{ $btn['secondary'] }}">
                                                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                                <span>Edit</span>
                                            </button>
                                        @endif

                                        @if($canDeleteTreatments)
                                            {{-- Name travels as a data attribute, not through
                                                 addslashes() into a JS string literal. --}}
                                            <button type="button"
                                                    onclick="openDeleteTreatmentModal(this)"
                                                    data-id="{{ $treatment->id }}"
                                                    data-name="{{ $treatment->name }}"
                                                    class="{{ $btn['danger'] }}">
                                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                <span>Remove</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr role="row">
                            <td role="cell" colspan="{{ $showTreatmentActions ? 5 : 4 }}"
                                class="px-6 py-12 text-center rt-empty">
                                <i class="mb-3 text-4xl text-gray-300 fa-solid fa-spa dark:text-gray-600" aria-hidden="true"></i>
                                <p class="text-sm text-gray-600 dark:text-gray-300">No treatments available</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by adding your first treatment.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════
         PACKAGES
         ══════════════════════════════════════════════════ --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-col gap-4 px-4 py-4 border-b border-gray-200 sm:px-6 lg:flex-row lg:items-start lg:justify-between dark:border-gray-700">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Packages</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $packages->count() }} package{{ $packages->count() === 1 ? '' : 's' }} available in this branch.
                </p>
            </div>

            @if($canManagePackages)
                <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    @if($canCreatePackages)
                        <button type="button" onclick="openAddPackageModal()" class="{{ $btn['primary'] }} whitespace-nowrap">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            <span>Add Package</span>
                        </button>
                    @endif

                    @if($hasPackages)
                        <a href="{{ route('packages.export') }}" class="{{ $btn['outline'] }} whitespace-nowrap">
                            <i class="fa-solid fa-file-export" aria-hidden="true"></i>
                            <span>Export CSV</span>
                        </a>
                    @else
                        <button type="button" aria-disabled="true"
                                onclick="explainUnavailable('packagesExportReason')"
                                aria-describedby="packagesExportReason"
                                class="{{ $btn['disabled'] }} whitespace-nowrap">
                            <i class="fa-solid fa-file-export" aria-hidden="true"></i>
                            <span>Export CSV</span>
                        </button>
                        <p id="packagesExportReason" role="status"
                           class="self-center hidden text-sm text-gray-500 dark:text-gray-400">
                            Add at least one package before exporting.
                        </p>
                    @endif

                    <form id="packagesImportForm" action="{{ route('packages.import') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="file" id="packagesCsvFile" name="file" accept=".csv" required class="hidden">
                        <button type="button" onclick="triggerCsvPicker('packagesCsvFile')" class="{{ $btn['outline'] }} w-full whitespace-nowrap">
                            <i class="fa-solid fa-file-import" aria-hidden="true"></i>
                            <span>Import CSV</span>
                        </button>
                    </form>

                    <button type="button" onclick="openModalById('packageImportHelpModal')"
                            class="{{ $btn['outline'] }} whitespace-nowrap">
                        <i class="fa-solid fa-file-csv" aria-hidden="true"></i>
                        <span>CSV Format Guide</span>
                    </button>
                </div>
            @endif
        </div>

        <div class="md:overflow-x-auto">
            <table role="table" class="rt min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" class="{{ $th }}">Package Name</th>
                        <th role="columnheader" class="{{ $th }}">Duration</th>
                        <th role="columnheader" class="{{ $th }}">Price</th>
                        <th role="columnheader" class="{{ $th }}">Included Treatments</th>
                        @if($showPackageActions)
                            <th role="columnheader" class="{{ $th }}">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody role="rowgroup" class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse($packages as $package)
                        <tr role="row" class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td role="cell" data-label="Package Name" class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-10 h-10 overflow-hidden text-white rounded-full bg-[#6F5430] shrink-0">
                                        @if($package->image_url)
                                            <img src="{{ $package->image_url }}" alt="" class="object-cover w-full h-full">
                                        @else
                                            <i class="fa-solid fa-gift" aria-hidden="true"></i>
                                        @endif
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
                            <td role="cell" data-label="Duration" class="px-6 py-4">
                                <span class="inline-flex items-center px-3 py-1 text-xs font-medium text-gray-800 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-200">
                                    {{ $package->duration }} mins
                                </span>
                            </td>
                            <td role="cell" data-label="Price" class="px-6 py-4">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                    ₱{{ number_format($package->price, 2) }}
                                </span>
                            </td>
                            <td role="cell" data-label="Included Treatments" class="px-6 py-4">
                                @php $members = $package->treatments; @endphp
                                @if($members->count() > 0)
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($members->take(3) as $member)
                                            <span class="px-2 py-1 text-xs text-blue-800 bg-blue-100 rounded-lg dark:bg-blue-900/40 dark:text-blue-300">
                                                {{ $member->name }}
                                            </span>
                                        @endforeach
                                        @if($members->count() > 3)
                                            <span class="px-2 py-1 text-xs text-gray-800 bg-gray-100 rounded-lg dark:bg-gray-700 dark:text-gray-200">
                                                +{{ $members->count() - 3 }} more
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-sm text-gray-500 dark:text-gray-400">No treatments included</span>
                                @endif
                            </td>
                            @if($showPackageActions)
                                <td role="cell" data-label="Actions" class="px-6 py-4 rt-actions">
                                    <div class="flex flex-wrap gap-2">
                                        @if($canEditPackages)
                                            {{-- data-duration and data-price are not read by the
                                                 edit modal (audit F2 / X2). They are kept because
                                                 the fix for F2, if it goes that way, needs them. --}}
                                            <button type="button"
                                                    onclick="openEditPackageModal(this)"
                                                    data-id="{{ $package->id }}"
                                                    data-name="{{ $package->name }}"
                                                    data-duration="{{ $package->duration }}"
                                                    data-price="{{ $package->price }}"
                                                    data-description="{{ $package->description }}"
                                                    data-treatments="{{ $members->pluck('id')->join(',') }}"
                                                    data-image="{{ $package->image_url }}"
                                                    class="{{ $btn['secondary'] }}">
                                                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                                <span>Edit</span>
                                            </button>
                                        @endif

                                        @if($canDeletePackages)
                                            <button type="button"
                                                    onclick="openDeletePackageModal(this)"
                                                    data-id="{{ $package->id }}"
                                                    data-name="{{ $package->name }}"
                                                    class="{{ $btn['danger'] }}">
                                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                <span>Remove</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr role="row">
                            <td role="cell" colspan="{{ $showPackageActions ? 5 : 4 }}"
                                class="px-6 py-12 text-center rt-empty">
                                <i class="mb-3 text-4xl text-gray-300 fa-solid fa-gift dark:text-gray-600" aria-hidden="true"></i>
                                <p class="text-sm text-gray-600 dark:text-gray-300">No packages available</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create packages to offer combined services.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- ══════════════════════════════════════════════════
     ADD TREATMENT MODAL
     ══════════════════════════════════════════════════ --}}
@if($canCreateTreatments)
<div id="addTreatmentModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="addTreatmentTitle"
             class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <h2 id="addTreatmentTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Add Treatment</h2>
                <button type="button" onclick="closeModalById('addTreatmentModal')" aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <form action="{{ route('treatments.store') }}" method="POST" id="addTreatmentForm"
                  enctype="multipart/form-data" novalidate class="px-4 py-6 space-y-4 sm:px-6">
                @csrf

                <div>
                    <label for="add_treatment_name" class="{{ $label }}">Name *</label>
                    <input type="text" name="name" id="add_treatment_name" required class="{{ $input }}">
                    <p class="{{ $err }}" id="add_name_error">Treatment name is required.</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="add_treatment_duration" class="{{ $label }}">Duration *</label>
                        <select name="duration" id="add_treatment_duration" required class="{{ $input }}">
                            <option value="" disabled selected>Select duration</option>
                            <option value="30">30 mins</option>
                            <option value="60">60 mins</option>
                            <option value="90">90 mins</option>
                            <option value="120">120 mins</option>
                        </select>
                        <p class="{{ $err }}" id="add_duration_error">Please select a duration.</p>
                    </div>

                    <div>
                        <label for="add_treatment_price" class="{{ $label }}">Price (₱) *</label>
                        <input type="number" name="price" id="add_treatment_price"
                               step="0.01" min="300" max="1400" required placeholder="300 – 1,400" class="{{ $input }}">
                        <p class="{{ $err }}" id="add_price_error">Price must be between ₱300 and ₱1,400.</p>
                    </div>
                </div>

                <div>
                    <label for="add_treatment_service_type" class="{{ $label }}">Service Type *</label>
                    <select name="service_type" id="add_treatment_service_type" required class="{{ $input }}">
                        <option value="in_branch_only">In Branch Only</option>
                        <option value="in_branch_and_home">In Branch &amp; Home</option>
                    </select>
                </div>

                <div>
                    <label for="add_treatment_image" class="{{ $label }}">Image</label>
                    <input type="file" name="image" id="add_treatment_image" accept="image/*" class="{{ $input }}">
                    <p class="{{ $hint }}">JPG, PNG or WEBP, max {{ $maxImageMb }}MB.</p>
                </div>

                <div>
                    <label for="add_treatment_description" class="{{ $label }}">Description</label>
                    <textarea name="description" id="add_treatment_description" rows="3" class="{{ $input }}"></textarea>
                </div>

                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                    <button type="button" onclick="closeModalById('addTreatmentModal')" class="{{ $btn['dismiss'] }}">Cancel</button>
                    <button type="submit" class="{{ $btn['primary'] }}">Add Treatment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════
     EDIT TREATMENT MODAL
     ══════════════════════════════════════════════════ --}}
@if($canEditTreatments)
<div id="editTreatmentModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="editTreatmentTitle"
             class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <h2 id="editTreatmentTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Edit Treatment</h2>
                <button type="button" onclick="closeModalById('editTreatmentModal')" aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <form id="editTreatmentForm" method="POST" enctype="multipart/form-data" novalidate
                  class="px-4 py-6 space-y-4 sm:px-6">
                @csrf
                @method('PUT')

                <div>
                    <label for="edit_treatment_name" class="{{ $label }}">Name *</label>
                    <input type="text" id="edit_treatment_name" name="name" required class="{{ $input }}">
                    <p class="{{ $err }}" id="edit_name_error">Treatment name is required.</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="edit_treatment_duration" class="{{ $label }}">Duration *</label>
                        <select name="duration" id="edit_treatment_duration" required class="{{ $input }}">
                            <option value="30">30 mins</option>
                            <option value="60">60 mins</option>
                            <option value="90">90 mins</option>
                            <option value="120">120 mins</option>
                        </select>
                        <p class="{{ $err }}" id="edit_duration_error">Please select a duration.</p>
                        <p id="edit_duration_notice" class="hidden mt-1 text-xs text-amber-700 dark:text-amber-400">
                            This treatment's saved duration isn't one of the standard options. It has been
                            added to the list so saving won't change it.
                        </p>
                    </div>

                    <div>
                        <label for="edit_treatment_price" class="{{ $label }}">Price (₱) *</label>
                        <input type="number" id="edit_treatment_price" name="price" step="0.01" required
                               min="300" max="1400" class="{{ $input }}">
                        <p class="{{ $err }}" id="edit_price_error">Price must be between ₱300 and ₱1,400.</p>
                    </div>
                </div>

                <div>
                    <label for="edit_treatment_service_type" class="{{ $label }}">Service Type *</label>
                    <select name="service_type" id="edit_treatment_service_type" required class="{{ $input }}">
                        <option value="in_branch_only">In Branch Only</option>
                        <option value="in_branch_and_home">In Branch &amp; Home</option>
                    </select>
                </div>

                <div>
                    <label for="edit_treatment_image_input" class="{{ $label }}">Image</label>
                    <img id="edit_treatment_image_preview" src="" alt=""
                         class="hidden object-cover w-20 h-20 mb-2 border border-gray-300 rounded-xl dark:border-gray-600">
                    <input type="file" name="image" id="edit_treatment_image_input" accept="image/*" class="{{ $input }}">
                    <p class="{{ $hint }}">Leave blank to keep current image.</p>
                </div>

                <div>
                    <label for="edit_treatment_description" class="{{ $label }}">Description</label>
                    <textarea id="edit_treatment_description" name="description" rows="3" class="{{ $input }}"></textarea>
                </div>

                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                    <button type="button" onclick="closeModalById('editTreatmentModal')" class="{{ $btn['dismiss'] }}">Cancel</button>
                    <button type="submit" class="{{ $btn['primary'] }}">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════
     DELETE TREATMENT MODAL
     ══════════════════════════════════════════════════ --}}
@if($canDeleteTreatments)
<div id="deleteTreatmentModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="alertdialog" aria-modal="true" aria-labelledby="deleteTreatmentTitle" aria-describedby="deleteTreatmentDesc"
             class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <h2 id="deleteTreatmentTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Remove Treatment</h2>
            <p id="deleteTreatmentDesc" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                This permanently deletes <span id="deleteTreatmentName" class="font-semibold text-gray-900 dark:text-white"></span>
                and cannot be undone. Any package that includes it may also change.
            </p>
            <div class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">
                <button type="button" onclick="closeModalById('deleteTreatmentModal')" class="{{ $btn['dismiss'] }}">Keep Treatment</button>
                <form id="deleteTreatmentForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="{{ $btn['danger'] }} w-full sm:w-auto">Yes, Remove</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════
     ADD PACKAGE MODAL
     ══════════════════════════════════════════════════ --}}
@if($canCreatePackages)
<div id="addPackageModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="addPackageTitle"
             class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <h2 id="addPackageTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Add Package</h2>
                <button type="button" onclick="closeModalById('addPackageModal')" aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <form action="{{ route('packages.store') }}" method="POST" id="addPackageForm"
                  enctype="multipart/form-data" novalidate class="px-4 py-6 space-y-4 sm:px-6">
                @csrf

                <div>
                    <label for="add_package_name" class="{{ $label }}">Package Name *</label>
                    <input type="text" name="name" id="add_package_name" required class="{{ $input }}">
                    <p class="{{ $err }}" id="add_package_name_error">Package name is required.</p>
                </div>

                <fieldset>
                    <legend class="{{ $label }}">Included Treatments *</legend>
                    <div id="addIncludedTreatments"
                         class="p-3 space-y-2 overflow-y-auto border border-gray-300 rounded-xl max-h-48 dark:border-gray-600 dark:bg-gray-700">
                        @forelse($treatments as $treatment)
                            <label class="flex items-center gap-3 min-h-[44px]">
                                <input type="checkbox"
                                       name="included_treatments[]"
                                       value="{{ $treatment->id }}"
                                       data-duration="{{ $treatment->duration }}"
                                       data-price="{{ $treatment->price }}"
                                       class="rounded border-gray-300 text-[#8B7355] focus:ring-[#8B7355] package-treatment-checkbox">
                                <span class="text-sm text-gray-700 dark:text-gray-200">
                                    {{ $treatment->name }} ({{ $treatment->duration }} mins — ₱{{ number_format($treatment->price, 2) }})
                                </span>
                            </label>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">No treatments exist yet. Add a treatment first.</p>
                        @endforelse
                    </div>
                    <p class="{{ $hint }}">Select one or more treatments.</p>
                    <p class="{{ $err }}" id="add_package_treatments_error">Select at least one treatment.</p>
                </fieldset>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="addDuration" class="{{ $label }}">Duration (mins) *</label>
                        <input type="number" name="duration" id="addDuration" required min="1" readonly class="{{ $inputReadonly }}">
                        <p class="{{ $hint }}">Auto-calculated from selected treatments.</p>
                    </div>
                    <div>
                        <label for="addPrice" class="{{ $label }}">Price (₱) *</label>
                        <input type="number" name="price" id="addPrice" step="0.01" required min="0" readonly class="{{ $inputReadonly }}">
                        <p class="{{ $hint }}">Auto-calculated from selected treatments.</p>
                    </div>
                </div>

                <div>
                    <label for="add_package_image" class="{{ $label }}">Image</label>
                    <input type="file" name="image" id="add_package_image" accept="image/*" class="{{ $input }}">
                    <p class="{{ $hint }}">JPG, PNG or WEBP, max {{ $maxImageMb }}MB.</p>
                </div>

                <div>
                    <label for="add_package_description" class="{{ $label }}">Description</label>
                    <textarea name="description" id="add_package_description" rows="3" class="{{ $input }}"></textarea>
                </div>

                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                    <button type="button" onclick="closeModalById('addPackageModal')" class="{{ $btn['dismiss'] }}">Cancel</button>
                    <button type="submit" class="{{ $btn['primary'] }}">Add Package</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════
     EDIT PACKAGE MODAL
     ══════════════════════════════════════════════════ --}}
@if($canEditPackages)
<div id="editPackageModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="editPackageTitle"
             class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <h2 id="editPackageTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Edit Package</h2>
                <button type="button" onclick="closeModalById('editPackageModal')" aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <form id="editPackageForm" method="POST" enctype="multipart/form-data" novalidate
                  class="px-4 py-6 space-y-4 sm:px-6">
                @csrf
                @method('PUT')

                {{-- Shown only when this package includes a treatment that has no
                     checkbox in the list below — saving would drop it silently
                     and recalculate the totals downward (audit F3). --}}
                <div id="editPackageMissingWarning" role="alert"
                     class="hidden p-3 text-sm border rounded-xl border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/10 dark:text-amber-300">
                    <span id="editPackageMissingCount"></span> treatment(s) currently in this package are not
                    shown in the list below. Saving now will remove them from the package and recalculate the
                    duration and price. Close this dialog and report it rather than saving.
                </div>

                <div>
                    <label for="edit_package_name" class="{{ $label }}">Package Name *</label>
                    <input type="text" id="edit_package_name" name="name" required class="{{ $input }}">
                    <p class="{{ $err }}" id="edit_package_name_error">Package name is required.</p>
                </div>

                <fieldset>
                    <legend class="{{ $label }}">Included Treatments *</legend>
                    <div id="editIncludedTreatments"
                         class="p-3 space-y-2 overflow-y-auto border border-gray-300 rounded-xl max-h-48 dark:border-gray-600 dark:bg-gray-700">
                        @forelse($treatments as $treatment)
                            <label class="flex items-center gap-3 min-h-[44px]">
                                <input type="checkbox"
                                       name="included_treatments[]"
                                       value="{{ $treatment->id }}"
                                       data-duration="{{ $treatment->duration }}"
                                       data-price="{{ $treatment->price }}"
                                       class="rounded border-gray-300 text-[#8B7355] focus:ring-[#8B7355] package-treatment-checkbox">
                                <span class="text-sm text-gray-700 dark:text-gray-200">
                                    {{ $treatment->name }} ({{ $treatment->duration }} mins — ₱{{ number_format($treatment->price, 2) }})
                                </span>
                            </label>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">No treatments exist yet.</p>
                        @endforelse
                    </div>
                    <p class="{{ $hint }}">Select one or more treatments.</p>
                    <p class="{{ $err }}" id="edit_package_treatments_error">Select at least one treatment.</p>
                </fieldset>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="edit_package_duration" class="{{ $label }}">Duration (mins) *</label>
                        <input type="number" id="edit_package_duration" name="duration" required min="1" readonly class="{{ $inputReadonly }}">
                        <p class="{{ $hint }}">Auto-calculated from selected treatments.</p>
                    </div>
                    <div>
                        <label for="edit_package_price" class="{{ $label }}">Price (₱) *</label>
                        <input type="number" id="edit_package_price" name="price" step="0.01" required min="0" readonly class="{{ $inputReadonly }}">
                        <p class="{{ $hint }}">Auto-calculated from selected treatments.</p>
                    </div>
                </div>

                <div>
                    <label for="edit_package_image_input" class="{{ $label }}">Image</label>
                    <img id="edit_package_image_preview" src="" alt=""
                         class="hidden object-cover w-20 h-20 mb-2 border border-gray-300 rounded-xl dark:border-gray-600">
                    <input type="file" name="image" id="edit_package_image_input" accept="image/*" class="{{ $input }}">
                    <p class="{{ $hint }}">Leave blank to keep current image.</p>
                </div>

                <div>
                    <label for="edit_package_description" class="{{ $label }}">Description</label>
                    <textarea id="edit_package_description" name="description" rows="3" class="{{ $input }}"></textarea>
                </div>

                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                    <button type="button" onclick="closeModalById('editPackageModal')" class="{{ $btn['dismiss'] }}">Cancel</button>
                    <button type="submit" class="{{ $btn['primary'] }}">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════
     DELETE PACKAGE MODAL
     ══════════════════════════════════════════════════ --}}
@if($canDeletePackages)
<div id="deletePackageModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="alertdialog" aria-modal="true" aria-labelledby="deletePackageTitle" aria-describedby="deletePackageDesc"
             class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <h2 id="deletePackageTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Remove Package</h2>
            <p id="deletePackageDesc" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                This permanently deletes <span id="deletePackageName" class="font-semibold text-gray-900 dark:text-white"></span>
                and cannot be undone. The treatments inside it are not deleted.
            </p>
            <div class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">
                <button type="button" onclick="closeModalById('deletePackageModal')" class="{{ $btn['dismiss'] }}">Keep Package</button>
                <form id="deletePackageForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="{{ $btn['danger'] }} w-full sm:w-auto">Yes, Remove</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════
     CSV FORMAT HELP MODALS — one template, two datasets
     ══════════════════════════════════════════════════ --}}
@foreach($importGuides as $guide)
<div id="{{ $guide['id'] }}" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="{{ $guide['id'] }}Title"
             class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <h2 id="{{ $guide['id'] }}Title" class="text-lg font-semibold text-gray-900 dark:text-white">{{ $guide['title'] }}</h2>
                <button type="button" onclick="closeModalById('{{ $guide['id'] }}')" aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="px-4 py-6 space-y-4 sm:px-6">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Your CSV file must include the following columns, in this order:
                </p>

                <div class="p-3 overflow-x-auto text-xs rounded-xl bg-gray-50 dark:bg-gray-900">
                    <code class="text-gray-800 dark:text-gray-200 whitespace-nowrap">{{ $guide['columns'] }}</code>
                </div>

                <ul class="space-y-1 text-sm text-gray-600 list-disc list-inside dark:text-gray-300">
                    @foreach($guide['fields'] as [$fieldName, $fieldRule])
                        <li><span class="font-medium text-gray-900 dark:text-white">{{ $fieldName }}</span> — {{ $fieldRule }}</li>
                    @endforeach
                </ul>

                <div>
                    <p class="mb-2 text-sm text-gray-600 dark:text-gray-300">Example row:</p>
                    <div class="p-3 overflow-x-auto text-xs rounded-xl bg-gray-50 dark:bg-gray-900">
                        <code class="text-gray-800 dark:text-gray-200 whitespace-nowrap">{{ $guide['example'] }}</code>
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-sm text-gray-600 dark:text-gray-300">How it should look in Excel/Sheets:</p>
                    <a href="{{ asset($guide['image']) }}" target="_blank" rel="noopener" class="block group">
                        <img src="{{ asset($guide['image']) }}"
                             alt="{{ $guide['imageAlt'] }}"
                             class="w-full transition-opacity border border-gray-200 rounded-xl cursor-zoom-in dark:border-gray-700 group-hover:opacity-90">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Opens the full-size image in a new tab.</p>
                    </a>
                </div>

                <div class="flex flex-col-reverse gap-2 pt-4 border-t border-gray-200 sm:flex-row sm:justify-between dark:border-gray-700">
                    {{-- download= keeps the browser from navigating the page away.
                         The filename still comes from the response's
                         Content-Disposition header — confirm the controller sets one. --}}
                    <a href="{{ $guide['sample'] }}" download class="{{ $btn['primary'] }}">
                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                        <span>Download Sample CSV</span>
                    </a>
                    <button type="button" onclick="closeModalById('{{ $guide['id'] }}')" class="{{ $btn['dismiss'] }}">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endforeach

{{-- ══════════════════════════════════════════════════
     SCRIPTS
     ══════════════════════════════════════════════════ --}}
<script>
// Everything lives inside one IIFE. Only the handful of functions that inline
// onclick= attributes call are attached to window at the bottom, so a second
// inline script on this page can't collide with anything in here.
(function () {
    'use strict';

    const MODAL_IDS = @json($modalIds);
    const BACKDROP_DISMISS_IDS = @json($backdropDismissIds);

    const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    // ── Routes ───────────────────────────────────────────────────────────────
    // UNRESOLVED: treatments.update / treatments.destroy / packages.update /
    // packages.destroy are assumed to exist because every other route on this
    // page is named under the same prefixes. The previous file hand-typed
    // '/treatments/' + id instead, which would break silently on a route-prefix
    // change. These will throw at render time if a name is wrong — a loud
    // failure you'll see on the first page load. Confirm with:
    //   php artisan route:list --name=treatments
    //   php artisan route:list --name=packages
    const ROUTE_TREATMENT_UPDATE  = @json(route('treatments.update', '__ID__'));
    const ROUTE_TREATMENT_DESTROY = @json(route('treatments.destroy', '__ID__'));
    const ROUTE_PACKAGE_UPDATE    = @json(route('packages.update', '__ID__'));
    const ROUTE_PACKAGE_DESTROY   = @json(route('packages.destroy', '__ID__'));

    function routeFor(template, id) {
        return template.replace('__ID__', encodeURIComponent(id));
    }


    // ════════════════════════════════════════════════════════════════
    // SHARED MODAL BEHAVIOUR
    // ════════════════════════════════════════════════════════════════
    let lastFocused = null;

    function topmostOpenModal() {
        for (let i = MODAL_IDS.length - 1; i >= 0; i--) {
            const el = document.getElementById(MODAL_IDS[i]);
            if (el && !el.classList.contains('hidden')) return el;
        }
        return null;
    }

    function openModalById(id, focusSelector) {
        const el = document.getElementById(id);
        if (!el) return;
        lastFocused = document.activeElement;
        el.classList.remove('hidden');
        const target = (focusSelector && el.querySelector(focusSelector)) || el.querySelector(FOCUSABLE);
        if (target) target.focus();
    }

    function closeModalById(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('hidden');
        if (lastFocused && document.contains(lastFocused)) lastFocused.focus();
        lastFocused = null;
    }

    // Escape closes the topmost open dialog; Tab stays inside it.
    document.addEventListener('keydown', function (e) {
        const modal = topmostOpenModal();
        if (!modal) return;

        if (e.key === 'Escape') {
            e.preventDefault();
            closeModalById(modal.id);
            return;
        }
        if (e.key !== 'Tab') return;

        const items = Array.from(modal.querySelectorAll(FOCUSABLE)).filter(el => el.offsetParent !== null);
        if (!items.length) return;
        const first = items[0];
        const last  = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault(); last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault(); first.focus();
        }
    });

    // Only the confirm and help dialogs close on a backdrop tap. On a form
    // dialog that would throw away everything typed, with no warning.
    BACKDROP_DISMISS_IDS.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('click', e => { if (e.target === el) closeModalById(id); });
    });


    // ════════════════════════════════════════════════════════════════
    // VALIDATION HELPERS
    // ════════════════════════════════════════════════════════════════
    function setFieldError(fieldId, errorId, show) {
        const field = document.getElementById(fieldId);
        const error = document.getElementById(errorId);
        if (field) field.classList.toggle('has-error', !!show);
        if (error) error.classList.toggle('hidden', !show);
    }

    function clearErrors(pairs) {
        pairs.forEach(([fieldId, errorId]) => setFieldError(fieldId, errorId, false));
    }

    // Panels scroll internally, so the first bad field can easily be off-screen.
    function revealFirstError(formId) {
        const form = document.getElementById(formId);
        if (!form) return;
        const bad = form.querySelector('.has-error');
        if (!bad) return;
        bad.scrollIntoView({ block: 'center', behavior: 'smooth' });
        if (typeof bad.focus === 'function') bad.focus({ preventScroll: true });
    }


    // ════════════════════════════════════════════════════════════════
    // TREATMENTS
    // ════════════════════════════════════════════════════════════════
    const ADD_TREATMENT_FIELDS = [
        ['add_treatment_name',     'add_name_error'],
        ['add_treatment_duration', 'add_duration_error'],
        ['add_treatment_price',    'add_price_error'],
    ];
    const EDIT_TREATMENT_FIELDS = [
        ['edit_treatment_name',     'edit_name_error'],
        ['edit_treatment_duration', 'edit_duration_error'],
        ['edit_treatment_price',    'edit_price_error'],
    ];

    function openAddTreatmentModal() {
        const form = document.getElementById('addTreatmentForm');
        if (form) form.reset();
        clearErrors(ADD_TREATMENT_FIELDS);
        openModalById('addTreatmentModal', '#add_treatment_name');
    }

    function openEditTreatmentModal(btn) {
        const d = btn.dataset;
        clearErrors(EDIT_TREATMENT_FIELDS);

        document.getElementById('edit_treatment_name').value = d.name || '';
        document.getElementById('edit_treatment_price').value = d.price || '';
        document.getElementById('edit_treatment_service_type').value = d.serviceType || '';
        document.getElementById('edit_treatment_description').value = d.description || '';
        document.getElementById('editTreatmentForm').action = routeFor(ROUTE_TREATMENT_UPDATE, d.id);

        // The select only offers 30/60/90/120, but a CSV import can create any
        // whole number. Assigning an unmatched value to a <select> fails
        // silently and leaves it showing 30 — so saving an unrelated edit would
        // overwrite the real duration. Add the stored value as an option first.
        const durationSelect = document.getElementById('edit_treatment_duration');
        const notice = document.getElementById('edit_duration_notice');
        const stored = (d.duration === undefined || d.duration === null) ? '' : String(d.duration);

        durationSelect.querySelectorAll('option.js-stored-duration').forEach(o => o.remove());

        const known = Array.from(durationSelect.options).some(o => o.value === stored);
        if (stored !== '' && !known) {
            const opt = document.createElement('option');
            opt.value = stored;
            opt.textContent = stored + ' mins (current)';
            opt.className = 'js-stored-duration';
            durationSelect.insertBefore(opt, durationSelect.firstChild);
            if (notice) notice.classList.remove('hidden');
        } else if (notice) {
            notice.classList.add('hidden');
        }
        durationSelect.value = stored;

        const preview = document.getElementById('edit_treatment_image_preview');
        const imageInput = document.getElementById('edit_treatment_image_input');
        if (imageInput) imageInput.value = '';
        if (preview) {
            preview.src = d.image || '';
            preview.classList.toggle('hidden', !d.image);
        }

        openModalById('editTreatmentModal', '#edit_treatment_name');
    }

    function openDeleteTreatmentModal(btn) {
        const d = btn.dataset;
        document.getElementById('deleteTreatmentName').textContent = d.name || '';
        document.getElementById('deleteTreatmentForm').action = routeFor(ROUTE_TREATMENT_DESTROY, d.id);
        openModalById('deleteTreatmentModal');
    }

    // Both treatment forms now run the same checks. Previously only the add
    // form had inline errors; edit fell back to browser bubbles.
    function validateTreatmentForm(prefix, pairs) {
        let valid = true;

        const name = document.getElementById(prefix + '_name').value.trim();
        setFieldError(prefix + '_name', pairs[0][1], name === '');
        if (name === '') valid = false;

        const duration = document.getElementById(prefix + '_duration').value;
        setFieldError(prefix + '_duration', pairs[1][1], duration === '');
        if (duration === '') valid = false;

        const price = parseFloat(document.getElementById(prefix + '_price').value);
        const priceInvalid = isNaN(price) || price < 300 || price > 1400;
        setFieldError(prefix + '_price', pairs[2][1], priceInvalid);
        if (priceInvalid) valid = false;

        return valid;
    }

    function wireTreatmentForm(formId, prefix, pairs) {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', function (e) {
            if (!validateTreatmentForm(prefix, pairs)) {
                e.preventDefault();
                revealFirstError(formId);
            }
        });

        // Clear an error as soon as the field looks acceptable again.
        const nameInput = document.getElementById(prefix + '_name');
        if (nameInput) nameInput.addEventListener('input', function () {
            if (this.value.trim() !== '') setFieldError(prefix + '_name', pairs[0][1], false);
        });

        const durationSelect = document.getElementById(prefix + '_duration');
        if (durationSelect) durationSelect.addEventListener('change', function () {
            if (this.value !== '') setFieldError(prefix + '_duration', pairs[1][1], false);
        });

        const priceInput = document.getElementById(prefix + '_price');
        if (priceInput) priceInput.addEventListener('input', function () {
            if (this.value === '') return;
            const price = parseFloat(this.value);
            setFieldError(prefix + '_price', pairs[2][1], isNaN(price) || price < 300 || price > 1400);
        });
    }

    wireTreatmentForm('addTreatmentForm',  'add_treatment',  ADD_TREATMENT_FIELDS);
    wireTreatmentForm('editTreatmentForm', 'edit_treatment', EDIT_TREATMENT_FIELDS);


    // ════════════════════════════════════════════════════════════════
    // PACKAGES
    // ════════════════════════════════════════════════════════════════

    // ⚠️ FLAGGED, DO NOT CHANGE (audit F2).
    // Package duration and price are the sum of the checked treatments, and
    // opening the edit modal recomputes both from scratch rather than reading
    // the stored values. That behaviour is carried over exactly as it was
    // because it is a price calculation and needs a decision before deployment.
    function updatePackageTotals(containerId, durationId, priceId) {
        const checked = document.querySelectorAll('#' + containerId + ' input[type="checkbox"]:checked');
        let totalDuration = 0;
        let totalPrice = 0;

        checked.forEach(cb => {
            totalDuration += parseInt(cb.dataset.duration, 10) || 0;
            totalPrice    += parseFloat(cb.dataset.price) || 0;
        });

        const durationField = document.getElementById(durationId);
        const priceField = document.getElementById(priceId);
        if (durationField) durationField.value = totalDuration;
        if (priceField) priceField.value = totalPrice.toFixed(2);
    }

    function updateAddPackageTotals() {
        updatePackageTotals('addIncludedTreatments', 'addDuration', 'addPrice');
    }

    function updateEditPackageTotals() {
        updatePackageTotals('editIncludedTreatments', 'edit_package_duration', 'edit_package_price');
    }

    function openAddPackageModal() {
        const form = document.getElementById('addPackageForm');
        if (form) form.reset();
        document.querySelectorAll('#addIncludedTreatments input[type="checkbox"]').forEach(cb => { cb.checked = false; });
        setFieldError('add_package_name', 'add_package_name_error', false);
        setFieldError('addIncludedTreatments', 'add_package_treatments_error', false);
        updateAddPackageTotals();
        openModalById('addPackageModal', '#add_package_name');
    }

    function openEditPackageModal(btn) {
        const d = btn.dataset;

        document.getElementById('edit_package_name').value = d.name || '';
        document.getElementById('edit_package_description').value = d.description || '';
        document.getElementById('editPackageForm').action = routeFor(ROUTE_PACKAGE_UPDATE, d.id);
        setFieldError('edit_package_name', 'edit_package_name_error', false);
        setFieldError('editIncludedTreatments', 'edit_package_treatments_error', false);

        const storedIds = d.treatments ? d.treatments.split(',').filter(Boolean) : [];
        const boxes = Array.from(document.querySelectorAll('#editIncludedTreatments input[type="checkbox"]'));
        boxes.forEach(cb => { cb.checked = storedIds.includes(cb.value); });

        // The checkbox list and the package's real members come from two
        // different sources. If a member has no checkbox it can't be checked,
        // so submitting would drop it and shrink the totals. Warn instead of
        // letting that happen quietly.
        const available = new Set(boxes.map(cb => cb.value));
        const missing = storedIds.filter(id => !available.has(id));
        const warning = document.getElementById('editPackageMissingWarning');
        const count = document.getElementById('editPackageMissingCount');
        if (warning) warning.classList.toggle('hidden', missing.length === 0);
        if (count) count.textContent = String(missing.length);

        const preview = document.getElementById('edit_package_image_preview');
        const imageInput = document.getElementById('edit_package_image_input');
        if (imageInput) imageInput.value = '';
        if (preview) {
            preview.src = d.image || '';
            preview.classList.toggle('hidden', !d.image);
        }

        openModalById('editPackageModal', '#edit_package_name');
        updateEditPackageTotals();
    }

    function openDeletePackageModal(btn) {
        const d = btn.dataset;
        document.getElementById('deletePackageName').textContent = d.name || '';
        document.getElementById('deletePackageForm').action = routeFor(ROUTE_PACKAGE_DESTROY, d.id);
        openModalById('deletePackageModal');
    }

    // With nothing checked the auto-filled duration is 0, which failed the
    // native min="1" on a readonly field — the browser blocked the submit and
    // tried to focus a control it couldn't focus, so pressing Save appeared to
    // do nothing. Both package forms now carry novalidate and check here.
    function wirePackageForm(formId, nameId, nameErrId, containerId, listErrId, recalc) {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', function (e) {
            let valid = true;

            const name = document.getElementById(nameId).value.trim();
            setFieldError(nameId, nameErrId, name === '');
            if (name === '') valid = false;

            const anyChecked = document.querySelector('#' + containerId + ' input[type="checkbox"]:checked') !== null;
            setFieldError(containerId, listErrId, !anyChecked);
            if (!anyChecked) valid = false;

            if (!valid) {
                e.preventDefault();
                revealFirstError(formId);
            }
        });

        const container = document.getElementById(containerId);
        if (container) {
            container.addEventListener('change', function (e) {
                if (e.target.matches('input[type="checkbox"]')) {
                    recalc();
                    setFieldError(containerId, listErrId, false);
                }
            });
        }

        const nameInput = document.getElementById(nameId);
        if (nameInput) nameInput.addEventListener('input', function () {
            if (this.value.trim() !== '') setFieldError(nameId, nameErrId, false);
        });
    }

    wirePackageForm('addPackageForm', 'add_package_name', 'add_package_name_error',
                    'addIncludedTreatments', 'add_package_treatments_error', updateAddPackageTotals);
    wirePackageForm('editPackageForm', 'edit_package_name', 'edit_package_name_error',
                    'editIncludedTreatments', 'edit_package_treatments_error', updateEditPackageTotals);


    // ════════════════════════════════════════════════════════════════
    // CSV IMPORT
    // ════════════════════════════════════════════════════════════════
    // Picking a file submits the surrounding form immediately.
    function triggerCsvPicker(inputId) {
        const input = document.getElementById(inputId);
        if (input) input.click();
    }

    ['treatmentsCsvFile', 'packagesCsvFile'].forEach(id => {
        const input = document.getElementById(id);
        if (!input) return;
        input.addEventListener('change', function () {
            if (this.files && this.files.length && this.form) this.form.submit();
        });
    });


    // ════════════════════════════════════════════════════════════════
    // UNAVAILABLE CONTROLS
    // ════════════════════════════════════════════════════════════════
    // The greyed-out Export buttons stay focusable on purpose. Activating one
    // reveals the reason instead of doing nothing.
    function explainUnavailable(reasonId) {
        const el = document.getElementById(reasonId);
        if (el) el.classList.remove('hidden');
    }


    // ════════════════════════════════════════════════════════════════
    // IMAGE UPLOAD SIZE
    // ════════════════════════════════════════════════════════════════
    // Same number as the hint text, from one PHP variable. This is a courtesy
    // check only — the controller's validation rule is what actually enforces
    // the limit, and it has to be raised separately.
    const MAX_IMAGE_MB = {{ $maxImageMb }};

    document.querySelectorAll('input[type="file"][accept="image/*"]').forEach(input => {
        input.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) return;
            if (file.size > MAX_IMAGE_MB * 1024 * 1024) {
                const mb = (file.size / (1024 * 1024)).toFixed(1);
                alert('That image is ' + mb + 'MB. The limit is ' + MAX_IMAGE_MB + 'MB — pick a smaller file.');
                this.value = '';
            }
        });
    });


    // ── Exposed to the inline onclick= attributes in the markup ──────────────
    window.explainUnavailable       = explainUnavailable;
    window.openModalById            = openModalById;
    window.closeModalById           = closeModalById;
    window.openAddTreatmentModal    = openAddTreatmentModal;
    window.openEditTreatmentModal   = openEditTreatmentModal;
    window.openDeleteTreatmentModal = openDeleteTreatmentModal;
    window.openAddPackageModal      = openAddPackageModal;
    window.openEditPackageModal     = openEditPackageModal;
    window.openDeletePackageModal   = openDeletePackageModal;
    window.triggerCsvPicker         = triggerCsvPicker;

}()); // end page script
</script>

<style>
/* Below md the two tables restack as cards. Changing display away from `table`
   strips native table semantics, which is why every element carries an explicit
   role= in the markup above. */
@media (max-width: 767px) {
    .rt,
    .rt tbody,
    .rt tr,
    .rt td { display: block; width: 100%; }

    .rt thead { display: none; }

    .rt tr { padding: 0.75rem 1rem; }

    .rt td {
        padding: 0.375rem 0 !important;
        text-align: left !important;
    }

    .rt td[data-label]::before {
        content: attr(data-label);
        display: block;
        margin-bottom: 0.125rem;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .rt td.rt-actions { padding-top: 0.75rem !important; }

    .rt td.rt-empty {
        padding: 2rem 0 !important;
        text-align: center !important;
    }
}

@media (max-width: 767px) and (prefers-color-scheme: dark) {
    .rt td[data-label]::before { color: #9ca3af; }
}

/* Invalid-field outline. darkMode is 'media', so there is no .dark class on
   <html> to hook — this has to be a media query, not a .dark selector. */
.has-error { border-color: #ef4444 !important; }
@media (prefers-color-scheme: dark) {
    .has-error { border-color: #f87171 !important; }
}
</style>
@endsection