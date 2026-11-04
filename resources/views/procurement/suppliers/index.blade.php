@extends('layouts.app')

@section('title', 'Suppliers')

@section('content')
@php
    $user = auth()->user();

    $canCreate = $user->hasBranchPermission('create suppliers');
    $canEdit = $user->hasBranchPermission('edit suppliers');
    $canManageProducts = $user->hasBranchPermission('manage supplier products');

    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'secondary' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'state' => $btnBase . ' border border-amber-300 bg-white text-amber-800 hover:bg-amber-50 dark:border-amber-700 dark:bg-gray-800 dark:text-amber-300 dark:hover:bg-amber-900/20',
    ];

    $modalFooter = 'flex flex-col-reverse gap-3 px-5 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:flex-row sm:justify-end sm:px-6 dark:bg-gray-900/30 dark:border-gray-700';

    $modalClose = 'inline-flex items-center justify-center shrink-0 w-11 h-11 text-gray-500 rounded-xl hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $inputBase = 'w-full min-h-[44px] px-3 py-2 mt-1 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-[#8B7355] dark:border-gray-600 dark:bg-gray-700 dark:text-white';

    $supplierUpdateUrl = route('procurement.suppliers.update', '__ID__');
    $supplierStatusUrl = route('procurement.suppliers.status', '__ID__');
    $supplierProductStoreUrl = route('procurement.suppliers.products.store', '__ID__');

    $supplierProductUpdateUrl = route(
        'procurement.suppliers.products.update',
        [
            'supplier' => '__SUPPLIER__',
            'supplierProduct' => '__ITEM__',
        ]
    );
@endphp

<div
    class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl"
    x-data="{
        createOpen: {{ $errors->createSupplier->any() ? 'true' : 'false' }},
        editOpen: false,
        statusOpen: false,
        productOpen: false,
        editProductOpen: false,

        supplier: {
            id: null,
            name: '',
            contact_person: '',
            email: '',
            phone: '',
            address: '',
            status: 'active'
        },

        productItem: {
            id: null,
            supplier_id: null,
            supplier_name: '',
            product_name: '',
            unit_cost: '',
            lead_time_days: '',
            minimum_order_quantity: '',
            is_preferred: false,
            is_active: true
        },

        openEdit(data) {
            this.supplier = {
                id: data.id,
                name: data.name ?? '',
                contact_person: data.contact_person ?? '',
                email: data.email ?? '',
                phone: data.phone ?? '',
                address: data.address ?? '',
                status: data.status ?? 'active'
            };

            this.editOpen = true;
        },

        openStatus(data) {
            this.supplier = {
                id: data.id,
                name: data.name ?? '',
                contact_person: data.contact_person ?? '',
                email: data.email ?? '',
                phone: data.phone ?? '',
                address: data.address ?? '',
                status: data.status ?? 'active'
            };

            this.statusOpen = true;
        },

        openProducts(data) {
            this.supplier = {
                id: data.id,
                name: data.name ?? '',
                contact_person: data.contact_person ?? '',
                email: data.email ?? '',
                phone: data.phone ?? '',
                address: data.address ?? '',
                status: data.status ?? 'active'
            };

            this.productOpen = true;
        },

        openEditProduct(data) {
            this.productItem = {
                id: data.id,
                supplier_id: data.supplier_id,
                supplier_name: data.supplier_name ?? '',
                product_name: data.product_name ?? '',
                unit_cost: data.unit_cost ?? '',
                lead_time_days: data.lead_time_days ?? '',
                minimum_order_quantity: data.minimum_order_quantity ?? '',
                is_preferred: Boolean(data.is_preferred),
                is_active: Boolean(data.is_active)
            };

            this.editProductOpen = true;
        }
    }"
>

    <x-page-header
        title="Suppliers"
        subtitle="Manage suppliers, product sourcing terms, lead times, and preferred supplier relationships."
    >
        <x-slot name="right">
            @if($canCreate)
                <button
                    type="button"
                    @click="createOpen = true"
                    class="{{ $btn['primary'] }}"
                >
                    <i class="text-xs fa-solid fa-plus" aria-hidden="true"></i>
                    Add Supplier
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Suppliers
            </p>

            <div class="flex items-end justify-between mt-3">
                <p class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                    {{ $suppliers->total() }}
                </p>

                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                    Registered
                </span>
            </div>
        </div>

        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Active
            </p>

            <div class="flex items-end justify-between mt-3">
                <p class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                    {{ $suppliers->getCollection()->where('status', 'active')->count() }}
                </p>

                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                    This page
                </span>
            </div>
        </div>

        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Linked Products
            </p>

            <div class="flex items-end justify-between mt-3">
                <p class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">
                    {{ $suppliers->getCollection()->sum(fn ($supplier) => $supplier->supplierProducts->count()) }}
                </p>

                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                    This page
                </span>
            </div>
        </div>
    </div>

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                Supplier Directory
            </h2>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Suppliers are shared across your spa while access remains controlled by branch permissions.
            </p>
        </div>

        @if($suppliers->isEmpty())
            <div class="flex flex-col items-center justify-center px-4 py-16 text-center">
                <i
                    class="mb-3 text-3xl text-gray-400 fa-solid fa-truck-field dark:text-gray-500"
                    aria-hidden="true"
                ></i>

                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    No suppliers yet
                </p>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Add your first supplier to start building your procurement network.
                </p>

                @if($canCreate)
                    <button
                        type="button"
                        @click="createOpen = true"
                        class="inline-flex items-center justify-center min-h-[44px] mt-2 px-2 text-sm font-semibold rounded-xl text-[#8B7355] hover:text-[#7A6348] dark:text-[#C4A97D] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800"
                    >
                        Add supplier

                        <i
                            class="ml-1.5 text-xs fa-solid fa-arrow-right"
                            aria-hidden="true"
                        ></i>
                    </button>
                @endif
            </div>
        @else
            <div class="w-full overflow-x-auto overscroll-x-contain">
                <table class="w-full min-w-[850px] text-sm text-left">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-gray-900/40 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 sm:px-6">
                                Supplier
                            </th>

                            <th class="px-4 py-3">
                                Contact
                            </th>

                            <th class="px-4 py-3">
                                Products
                            </th>

                            <th class="px-4 py-3">
                                Status
                            </th>

                            <th class="px-4 py-3 text-right sm:px-6">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($suppliers as $supplier)
                            @php
                                $supplierPayload = [
                                    'id' => $supplier->id,
                                    'name' => $supplier->name,
                                    'contact_person' => $supplier->contact_person,
                                    'email' => $supplier->email,
                                    'phone' => $supplier->phone,
                                    'address' => $supplier->address,
                                    'status' => $supplier->status,
                                ];
                            @endphp

                            <tr class="align-top">
                                <td class="px-4 py-4 sm:px-6">
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ $supplier->name }}
                                    </p>

                                    @if($supplier->address)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $supplier->address }}
                                        </p>
                                    @endif
                                </td>

                                <td class="px-4 py-4">
                                    <div class="space-y-1 text-gray-600 dark:text-gray-300">
                                        @if($supplier->contact_person)
                                            <p>
                                                {{ $supplier->contact_person }}
                                            </p>
                                        @endif

                                        @if($supplier->email)
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $supplier->email }}
                                            </p>
                                        @endif

                                        @if($supplier->phone)
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $supplier->phone }}
                                            </p>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-4 py-4">
                                    @if($supplier->supplierProducts->isEmpty())
                                        <span class="text-sm text-gray-500 dark:text-gray-400">
                                            No products
                                        </span>
                                    @else
                                        <div class="space-y-2">
                                            @foreach($supplier->supplierProducts as $supplierProduct)
                                                @php
                                                    $productPayload = [
                                                        'id' => $supplierProduct->id,
                                                        'supplier_id' => $supplier->id,
                                                        'supplier_name' => $supplier->name,
                                                        'product_name' => $supplierProduct->product?->name,
                                                        'unit_cost' => $supplierProduct->unit_cost,
                                                        'lead_time_days' => $supplierProduct->lead_time_days,
                                                        'minimum_order_quantity' => $supplierProduct->minimum_order_quantity,
                                                        'is_preferred' => (bool) $supplierProduct->is_preferred,
                                                        'is_active' => (bool) $supplierProduct->is_active,
                                                    ];
                                                @endphp

                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="text-sm text-gray-700 dark:text-gray-200">
                                                        {{ $supplierProduct->product?->name ?? 'Unavailable product' }}
                                                    </span>

                                                    @if($supplierProduct->is_preferred)
                                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                                            Preferred
                                                        </span>
                                                    @endif

                                                    @if(!$supplierProduct->is_active)
                                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300">
                                                            Inactive
                                                        </span>
                                                    @endif

                                                    @if($canManageProducts)
                                                        <button
                                                            type="button"
                                                            @click='openEditProduct(@json($productPayload))'
                                                            class="inline-flex items-center justify-center min-h-[44px] min-w-[44px] px-2 text-gray-500 rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800"
                                                        >
                                                            <i
                                                                class="text-xs fa-solid fa-pen"
                                                                aria-hidden="true"
                                                            ></i>

                                                            <span class="sr-only">
                                                                Edit {{ $supplierProduct->product?->name }}
                                                            </span>
                                                        </button>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>

                                <td class="px-4 py-4">
                                    @if($supplier->status === 'active')
                                        <span class="px-3 py-1.5 text-xs font-medium rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                            Active
                                        </span>
                                    @else
                                        <span class="px-3 py-1.5 text-xs font-medium rounded-full bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300">
                                            Inactive
                                        </span>
                                    @endif
                                </td>

                                <td class="px-4 py-4 sm:px-6">
                                    <div class="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap sm:justify-end">
                                        @if($canManageProducts && $supplier->status === 'active')
                                            <button
                                                type="button"
                                                @click='openProducts(@json($supplierPayload))'
                                                class="{{ $btn['secondary'] }}"
                                            >
                                                <i
                                                    class="text-xs fa-solid fa-box"
                                                    aria-hidden="true"
                                                ></i>

                                                Add Product
                                            </button>
                                        @endif

                                        @if($canEdit)
                                            <button
                                                type="button"
                                                @click='openEdit(@json($supplierPayload))'
                                                class="{{ $btn['secondary'] }}"
                                            >
                                                <i
                                                    class="text-xs fa-solid fa-pen"
                                                    aria-hidden="true"
                                                ></i>

                                                Edit
                                            </button>

                                            <button
                                                type="button"
                                                @click='openStatus(@json($supplierPayload))'
                                                class="{{ $btn['state'] }}"
                                            >
                                                <i
                                                    class="text-xs fa-solid {{ $supplier->status === 'active' ? 'fa-ban' : 'fa-check' }}"
                                                    aria-hidden="true"
                                                ></i>

                                                {{ $supplier->status === 'active' ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($suppliers->hasPages())
                <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
                    {{ $suppliers->links() }}
                </div>
            @endif
        @endif
    </div>

    @if($canCreate)
        <x-app-modal
            show="createOpen"
            max-width="lg"
            labelledby="createSupplierTitle"
        >
            <form
                method="POST"
                action="{{ route('procurement.suppliers.store') }}"
            >
                @csrf

                <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div class="min-w-0">
                        <h2
                            id="createSupplierTitle"
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Add Supplier
                        </h2>

                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Add a supplier to your spa procurement directory.
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="createOpen = false"
                        aria-label="Close add supplier dialog"
                        class="{{ $modalClose }}"
                    >
                        <i
                            class="fa-solid fa-xmark"
                            aria-hidden="true"
                        ></i>
                    </button>
                </div>

                <div class="p-5 space-y-4 sm:p-6">
                    <div>
                        <label
                            for="supplier-name"
                            class="text-sm font-medium text-gray-700 dark:text-gray-200"
                        >
                            Supplier Name
                        </label>

                        <input
                            id="supplier-name"
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
                            required
                            class="{{ $inputBase }}"
                        >

                        @error('name', 'createSupplier')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label
                            for="supplier-contact"
                            class="text-sm font-medium text-gray-700 dark:text-gray-200"
                        >
                            Contact Person
                        </label>

                        <input
                            id="supplier-contact"
                            type="text"
                            name="contact_person"
                            value="{{ old('contact_person') }}"
                            class="{{ $inputBase }}"
                        >
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                for="supplier-email"
                                class="text-sm font-medium text-gray-700 dark:text-gray-200"
                            >
                                Email
                            </label>

                            <input
                                id="supplier-email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                class="{{ $inputBase }}"
                            >
                        </div>

                        <div>
                            <label
                                for="supplier-phone"
                                class="text-sm font-medium text-gray-700 dark:text-gray-200"
                            >
                                Phone
                            </label>

                            <input
                                id="supplier-phone"
                                type="text"
                                name="phone"
                                value="{{ old('phone') }}"
                                class="{{ $inputBase }}"
                            >
                        </div>
                    </div>

                    <div>
                        <label
                            for="supplier-address"
                            class="text-sm font-medium text-gray-700 dark:text-gray-200"
                        >
                            Address
                        </label>

                        <textarea
                            id="supplier-address"
                            name="address"
                            rows="3"
                            class="{{ $inputBase }}"
                        >{{ old('address') }}</textarea>
                    </div>
                </div>

                <div class="{{ $modalFooter }}">
                    <button
                        type="button"
                        @click="createOpen = false"
                        class="{{ $btn['secondary'] }}"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="{{ $btn['primary'] }}"
                    >
                        <i
                            class="text-xs fa-solid fa-plus"
                            aria-hidden="true"
                        ></i>

                        Add Supplier
                    </button>
                </div>
            </form>
        </x-app-modal>
    @endif

    @if($canEdit)
        <x-app-modal
            show="editOpen"
            max-width="lg"
            labelledby="editSupplierTitle"
        >
            <form
                method="POST"
                :action="'{{ $supplierUpdateUrl }}'.replace('__ID__', supplier.id)"
            >
                @csrf
                @method('PUT')

                <div class="flex items-center justify-between gap-4 px-5 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <h2
                        id="editSupplierTitle"
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Edit Supplier
                    </h2>

                    <button
                        type="button"
                        @click="editOpen = false"
                        aria-label="Close edit supplier dialog"
                        class="{{ $modalClose }}"
                    >
                        <i
                            class="fa-solid fa-xmark"
                            aria-hidden="true"
                        ></i>
                    </button>
                </div>

                <div class="p-5 space-y-4 sm:p-6">
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            Supplier Name
                        </label>

                        <input
                            type="text"
                            name="name"
                            x-model="supplier.name"
                            required
                            class="{{ $inputBase }}"
                        >
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            Contact Person
                        </label>

                        <input
                            type="text"
                            name="contact_person"
                            x-model="supplier.contact_person"
                            class="{{ $inputBase }}"
                        >
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                x-model="supplier.email"
                                class="{{ $inputBase }}"
                            >
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                Phone
                            </label>

                            <input
                                type="text"
                                name="phone"
                                x-model="supplier.phone"
                                class="{{ $inputBase }}"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            Address
                        </label>

                        <textarea
                            name="address"
                            rows="3"
                            x-model="supplier.address"
                            class="{{ $inputBase }}"
                        ></textarea>
                    </div>
                </div>

                <div class="{{ $modalFooter }}">
                    <button
                        type="button"
                        @click="editOpen = false"
                        class="{{ $btn['secondary'] }}"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="{{ $btn['primary'] }}"
                    >
                        <i
                            class="text-xs fa-solid fa-floppy-disk"
                            aria-hidden="true"
                        ></i>

                        Save Changes
                    </button>
                </div>
            </form>
        </x-app-modal>

        <x-app-modal
            show="statusOpen"
            max-width="lg"
            role="alertdialog"
            labelledby="supplierStatusTitle"
            describedby="supplierStatusDescription"
        >
            <form
                method="POST"
                :action="'{{ $supplierStatusUrl }}'.replace('__ID__', supplier.id)"
            >
                @csrf
                @method('PATCH')

                <input
                    type="hidden"
                    name="status"
                    :value="supplier.status === 'active' ? 'inactive' : 'active'"
                >

                <div class="p-5 sm:p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex items-center justify-center shrink-0 w-11 h-11 rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                            <i
                                class="fa-solid fa-circle-exclamation"
                                aria-hidden="true"
                            ></i>
                        </div>

                        <div class="min-w-0">
                            <h2
                                id="supplierStatusTitle"
                                class="text-lg font-semibold text-gray-900 dark:text-white"
                                x-text="supplier.status === 'active' ? 'Deactivate Supplier' : 'Activate Supplier'"
                            ></h2>

                            <div
                                id="supplierStatusDescription"
                                class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                            >
                                <template x-if="supplier.status === 'active'">
                                    <p>
                                        Deactivating this supplier will also disable its supplier-product relationships and remove preferred status.
                                    </p>
                                </template>

                                <template x-if="supplier.status !== 'active'">
                                    <p>
                                        Activating the supplier does not automatically reactivate its product relationships.
                                    </p>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="{{ $modalFooter }}">
                    <button
                        type="button"
                        @click="statusOpen = false"
                        class="{{ $btn['secondary'] }}"
                    >
                        Keep Current Status
                    </button>

                    <button
                        type="submit"
                        class="{{ $btn['state'] }}"
                    >
                        <i
                            class="text-xs fa-solid"
                            :class="supplier.status === 'active' ? 'fa-ban' : 'fa-check'"
                            aria-hidden="true"
                        ></i>

                        <span
                            x-text="supplier.status === 'active' ? 'Deactivate' : 'Activate'"
                        ></span>
                    </button>
                </div>
            </form>
        </x-app-modal>
    @endif

    @if($canManageProducts)
        <x-app-modal
            show="productOpen"
            max-width="lg"
            labelledby="supplierProductTitle"
        >
            <form
                method="POST"
                :action="'{{ $supplierProductStoreUrl }}'.replace('__ID__', supplier.id)"
            >
                @csrf

                <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div class="min-w-0">
                        <h2
                            id="supplierProductTitle"
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Assign Product
                        </h2>

                        <p
                            class="mt-1 text-sm text-gray-500 truncate dark:text-gray-400"
                            x-text="supplier.name"
                        ></p>
                    </div>

                    <button
                        type="button"
                        @click="productOpen = false"
                        aria-label="Close product assignment dialog"
                        class="{{ $modalClose }}"
                    >
                        <i
                            class="fa-solid fa-xmark"
                            aria-hidden="true"
                        ></i>
                    </button>
                </div>

                <div class="p-5 space-y-4 sm:p-6">
                    <div>
                        <label
                            for="supplier-product"
                            class="text-sm font-medium text-gray-700 dark:text-gray-200"
                        >
                            Product
                        </label>

                        <select
                            id="supplier-product"
                            name="product_id"
                            required
                            class="{{ $inputBase }}"
                        >
                            <option value="">
                                Select a product
                            </option>

                            @foreach($products as $product)
                                <option value="{{ $product->id }}">
                                    {{ $product->name }}
                                    @if($product->purchase_unit)
                                        — {{ $product->purchase_unit }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label
                            for="supplier-cost"
                            class="text-sm font-medium text-gray-700 dark:text-gray-200"
                        >
                            Supplier Unit Cost
                        </label>

                        <input
                            id="supplier-cost"
                            type="number"
                            name="unit_cost"
                            min="0"
                            step="0.01"
                            class="{{ $inputBase }}"
                        >
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                for="supplier-lead-time"
                                class="text-sm font-medium text-gray-700 dark:text-gray-200"
                            >
                                Lead Time
                            </label>

                            <input
                                id="supplier-lead-time"
                                type="number"
                                name="lead_time_days"
                                min="0"
                                step="1"
                                class="{{ $inputBase }}"
                            >
                        </div>

                        <div>
                            <label
                                for="supplier-moq"
                                class="text-sm font-medium text-gray-700 dark:text-gray-200"
                            >
                                Minimum Order Qty
                            </label>

                            <input
                                id="supplier-moq"
                                type="number"
                                name="minimum_order_quantity"
                                min="0.001"
                                step="0.001"
                                class="{{ $inputBase }}"
                            >
                        </div>
                    </div>

                    <label class="flex items-start gap-3 min-h-[44px] py-2">
                        <input
                            type="checkbox"
                            name="is_preferred"
                            value="1"
                            class="mt-0.5 rounded border-gray-300 text-[#8B7355] focus:ring-[#8B7355]"
                        >

                        <span class="text-sm text-gray-700 dark:text-gray-200">
                            Make this the preferred supplier for the product
                        </span>
                    </label>
                </div>

                <div class="{{ $modalFooter }}">
                    <button
                        type="button"
                        @click="productOpen = false"
                        class="{{ $btn['secondary'] }}"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="{{ $btn['primary'] }}"
                    >
                        <i
                            class="text-xs fa-solid fa-box"
                            aria-hidden="true"
                        ></i>

                        Assign Product
                    </button>
                </div>
            </form>
        </x-app-modal>

        <x-app-modal
            show="editProductOpen"
            max-width="lg"
            labelledby="editSupplierProductTitle"
        >
            <form
                method="POST"
                :action="'{{ $supplierProductUpdateUrl }}'
                    .replace('__SUPPLIER__', productItem.supplier_id)
                    .replace('__ITEM__', productItem.id)"
            >
                @csrf
                @method('PUT')

                <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div class="min-w-0">
                        <h2
                            id="editSupplierProductTitle"
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Supplier Product Terms
                        </h2>

                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            <span x-text="productItem.product_name"></span>
                            <span aria-hidden="true"> · </span>
                            <span x-text="productItem.supplier_name"></span>
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="editProductOpen = false"
                        aria-label="Close supplier product dialog"
                        class="{{ $modalClose }}"
                    >
                        <i
                            class="fa-solid fa-xmark"
                            aria-hidden="true"
                        ></i>
                    </button>
                </div>

                <div class="p-5 space-y-4 sm:p-6">
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            Unit Cost
                        </label>

                        <input
                            type="number"
                            name="unit_cost"
                            min="0"
                            step="0.01"
                            x-model="productItem.unit_cost"
                            class="{{ $inputBase }}"
                        >
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                Lead Time
                            </label>

                            <input
                                type="number"
                                name="lead_time_days"
                                min="0"
                                step="1"
                                x-model="productItem.lead_time_days"
                                class="{{ $inputBase }}"
                            >
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                Minimum Order Qty
                            </label>

                            <input
                                type="number"
                                name="minimum_order_quantity"
                                min="0.001"
                                step="0.001"
                                x-model="productItem.minimum_order_quantity"
                                class="{{ $inputBase }}"
                            >
                        </div>
                    </div>

                    <label class="flex items-start gap-3 min-h-[44px] py-2">
                        <input
                            type="checkbox"
                            name="is_preferred"
                            value="1"
                            x-model="productItem.is_preferred"
                            class="mt-0.5 rounded border-gray-300 text-[#8B7355] focus:ring-[#8B7355]"
                        >

                        <span class="text-sm text-gray-700 dark:text-gray-200">
                            Preferred supplier
                        </span>
                    </label>

                    <label class="flex items-start gap-3 min-h-[44px] py-2">
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            x-model="productItem.is_active"
                            class="mt-0.5 rounded border-gray-300 text-[#8B7355] focus:ring-[#8B7355]"
                        >

                        <span class="text-sm text-gray-700 dark:text-gray-200">
                            Active supplier-product relationship
                        </span>
                    </label>
                </div>

                <div class="{{ $modalFooter }}">
                    <button
                        type="button"
                        @click="editProductOpen = false"
                        class="{{ $btn['secondary'] }}"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="{{ $btn['primary'] }}"
                    >
                        <i
                            class="text-xs fa-solid fa-floppy-disk"
                            aria-hidden="true"
                        ></i>

                        Save Terms
                    </button>
                </div>
            </form>
        </x-app-modal>
    @endif

</div>
@endsection
