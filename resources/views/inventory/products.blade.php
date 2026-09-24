@extends('layouts.app')

@section('title', 'Inventory Products')

@section('content')
@php
    $deductFailedProduct = null;

    if ($errors->deductStock->any() && old('product_id')) {
        $deductFailedProduct = $products->firstWhere('id', (int) old('product_id'));
    }

    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'secondary' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'outline' => $btnBase . ' border border-[#8B7355] bg-white text-[#8B7355] hover:bg-[#F8F5F1] dark:bg-gray-800 dark:text-[#C4A97D] dark:border-[#8B7355] dark:hover:bg-gray-700',
        'danger' => $btnBase . ' bg-red-700 text-white hover:bg-red-800',
    ];
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl"
    x-data="{
        addOpen: false,
        editOpen: false,
        deleteOpen: false,
        importHelpOpen: false,
        edit: { id: null, name: '', brand: '', stock_quantity: 0, unit_value: 0, unit: 'ml', expiration_date: '' },
        deleteProduct: { id: null, name: '' },

        openEdit(p) {
            this.edit = {
                id: p.id,
                name: p.name ?? '',
                brand: p.brand ?? '',
                stock_quantity: p.stock_quantity ?? 0,
                unit_value: p.unit_value ?? 0,
                unit: p.unit ?? 'ml',
                expiration_date: p.expiration_date ?? ''
            };
            this.editOpen = true;
        },

        openDelete(p) {
            this.deleteProduct = {
                id: p.id,
                name: p.name
            };
            this.deleteOpen = true;
        }
    }"
    x-init="@if($deductFailedProduct)
        openEdit(@js([
            'id' => $deductFailedProduct->id,
            'name' => $deductFailedProduct->name,
            'brand' => $deductFailedProduct->brand,
            'stock_quantity' => (int) $deductFailedProduct->stock_quantity,
            'unit_value' => (int) ($deductFailedProduct->unit_value ?? 0),
            'unit' => $deductFailedProduct->unit ?? 'ml',
            'expiration_date' => optional($deductFailedProduct->expiration_date)->format('Y-m-d'),
        ]))
    @endif">

    <x-page-header
        title="Inventory Products"
        subtitle="Manage product stock, units, expiration dates, and inventory records."
    />

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-col gap-4 px-4 py-4 border-b border-gray-200 sm:px-6 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Inventory List</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">View and manage products available in this branch.</p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                <button type="button" @click="addOpen = true" class="{{ $btn['primary'] }}">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    Add Product
                </button>

                <a href="{{ route('inventory.products.export') }}" class="{{ $btn['outline'] }}">
                    <i class="fa-solid fa-file-export" aria-hidden="true"></i>
                    Export CSV
                </a>

                <form id="productsImportForm" action="{{ route('inventory.products.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="file" id="productsCsvFile" name="file" accept=".csv" required class="hidden"
                        onchange="document.getElementById('productsImportForm').submit()">
                    <button type="button" onclick="document.getElementById('productsCsvFile').click()"
                        class="w-full {{ $btn['outline'] }}">
                        <i class="fa-solid fa-file-import" aria-hidden="true"></i>
                        Import CSV
                    </button>
                </form>

                <button type="button"
                    @click="triggerSampleCsvDownload('{{ route('inventory.products.sample-csv') }}'); importHelpOpen = true"
                    class="{{ $btn['outline'] }}" title="CSV format guide">
                    <i class="fa-solid fa-file-csv" aria-hidden="true"></i>
                    CSV Format Guide
                </button>
            </div>
        </div>

        <div class="md:overflow-x-auto">
            <table role="table" class="rt min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Product</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Brand</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Stock</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Unit</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Expiration</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-center text-gray-500 uppercase dark:text-gray-400">Actions</th>
                    </tr>
                </thead>

                <tbody role="rowgroup" class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse($products as $product)
                        <tr role="row" class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td role="cell" data-label="Product" class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $product->name }}</p>
                            </td>

                            <td role="cell" data-label="Brand" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ $product->brand ?? '—' }}
                            </td>

                            <td role="cell" data-label="Stock" class="px-6 py-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $product->stock_quantity }}</span>

                                    @if ($product->stock_quantity <= 5)
                                        <span class="inline-flex items-center px-3 py-1 text-xs font-medium text-red-700 bg-red-100 rounded-full dark:bg-red-900/40 dark:text-red-300">
                                            Low Stock
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <td role="cell" data-label="Unit" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ $product->unit_value ?? 0 }}{{ $product->unit ?? 'ml' }}
                            </td>

                            <td role="cell" data-label="Expiration" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ $product->expiration_date?->format('M d, Y') ?? '—' }}
                            </td>

                            <td role="cell" data-label="Actions" class="px-6 py-4 text-center rt-actions">
                                <div class="flex flex-wrap gap-2 md:justify-center">
                                    <button type="button"
                                        @click="openEdit({
                                            id: {{ $product->id }},
                                            name: @js($product->name),
                                            brand: @js($product->brand),
                                            stock_quantity: {{ (int) $product->stock_quantity }},
                                            unit_value: {{ (int) ($product->unit_value ?? 0) }},
                                            unit: @js($product->unit ?? 'ml'),
                                            expiration_date: @js(optional($product->expiration_date)->format('Y-m-d'))
                                        })"
                                        class="{{ $btn['secondary'] }}">
                                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                        Edit
                                    </button>

                                    <button type="button"
                                        @click="openDelete({
                                            id: {{ $product->id }},
                                            name: @js($product->name)
                                        })"
                                        class="{{ $btn['danger'] }}">
                                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                        Remove
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr role="row">
                            <td role="cell" colspan="6"
                                class="px-6 py-12 text-sm text-center text-gray-500 rt-empty dark:text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="flex items-center justify-center w-12 h-12 mb-3 text-gray-400 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-500">
                                        <i class="text-lg fa-solid fa-box-open" aria-hidden="true"></i>
                                    </div>
                                    <p>No products found.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
            {{ $products->links() }}
        </div>
    </div>

    <div x-show="deleteOpen" x-transition.opacity
        class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/50"
        :class="{ 'hidden': !deleteOpen }">

        <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
            <div role="alertdialog" aria-modal="true"
                aria-labelledby="deleteProductTitle"
                aria-describedby="deleteProductDescription"
                @click.outside="deleteOpen = false"
                x-transition
                class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">

                <h2 id="deleteProductTitle" class="text-lg font-semibold text-gray-900 dark:text-white">
                    Remove Product
                </h2>

                <p id="deleteProductDescription" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to permanently remove
                    <span class="font-medium text-gray-900 dark:text-white" x-text="deleteProduct.name"></span>?
                </p>

                <div class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">
                    <button type="button" @click="deleteOpen = false" class="{{ $btn['secondary'] }}">
                        Cancel
                    </button>

                    <form method="POST" :action="`{{ url('/inventory/products') }}/${deleteProduct.id}`">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full {{ $btn['danger'] }} sm:w-auto">
                            Yes, Remove
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div x-show="addOpen" x-transition.opacity
        class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/50"
        :class="{ 'hidden': !addOpen }">

        <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
            <div role="dialog" aria-modal="true"
                aria-labelledby="addProductTitle"
                @click.outside="addOpen = false"
                x-transition
                class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">

                <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div>
                        <h2 id="addProductTitle" class="text-lg font-semibold text-gray-900 dark:text-white">
                            Add Product
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Add a new product to the branch inventory.
                        </p>
                    </div>

                    <button type="button" @click="addOpen = false" aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('inventory.products.store') }}"
                    class="px-4 py-6 space-y-4 sm:px-6">
                    @csrf

                    <div>
                        <label for="add_product_name" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Product Name
                        </label>
                        <input id="add_product_name" name="name" value="{{ old('name') }}" required
                            class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                        @error('name')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="add_product_brand" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Brand Name
                        </label>
                        <input id="add_product_brand" name="brand" value="{{ old('brand') }}" placeholder="Optional"
                            class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                        @error('brand')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="add_unit_value" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Unit Value
                            </label>
                            <input type="number" id="add_unit_value" name="unit_value" min="0"
                                value="{{ old('unit_value', 0) }}"
                                class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                            @error('unit_value')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="add_unit" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Unit
                            </label>
                            <select id="add_unit" name="unit"
                                class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                @foreach (['ml', 'L', 'g', 'kg', 'pcs'] as $unitOption)
                                    <option value="{{ $unitOption }}" @selected(old('unit', 'ml') === $unitOption)>
                                        {{ $unitOption }}
                                    </option>
                                @endforeach
                            </select>

                            @error('unit')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="add_stock_quantity" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Stock Quantity
                            </label>
                            <input type="number" id="add_stock_quantity" name="stock_quantity" min="0"
                                value="{{ old('stock_quantity', 0) }}" required
                                class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                            @error('stock_quantity')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="add_expiration_date" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Expiration Date
                            </label>
                            <input type="date" id="add_expiration_date" name="expiration_date"
                                value="{{ old('expiration_date') }}"
                                class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                            @error('expiration_date')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                        <button type="button" @click="addOpen = false" class="{{ $btn['secondary'] }}">
                            Cancel
                        </button>
                        <button type="submit" class="{{ $btn['primary'] }}">
                            Save Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <template x-teleport="body">
        <div x-show="editOpen" x-transition.opacity
        class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/50"
        :class="{ 'hidden': !editOpen }">

        <div class="flex items-start justify-center min-h-full p-4 pt-6 sm:pt-8">
            <div role="dialog" aria-modal="true" aria-labelledby="editProductTitle"
                x-transition
                class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">

                <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div>
                        <h2 id="editProductTitle" class="text-lg font-semibold text-gray-900 dark:text-white">
                            Edit Product
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Update product details or deduct available stock.
                        </p>
                    </div>

                    <button type="button" @click="editOpen = false" aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="px-4 pt-4 pb-5 space-y-5 overflow-y-auto sm:px-6 max-h-[75vh]">
                    <form method="POST" id="updateProductForm"
                        :action="`{{ url('/inventory/products') }}/${edit.id}`"
                        class="space-y-4">
                            @csrf
                            @method('PUT')

                            <div>
                                <label for="edit_product_name" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Product Name
                                </label>
                                <input id="edit_product_name" name="name" x-model="edit.name" required
                                    class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>

                            <div>
                                <label for="edit_product_brand" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Brand Name
                                </label>
                                <input id="edit_product_brand" name="brand" x-model="edit.brand" placeholder="Optional"
                                    class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="edit_stock_quantity" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Stock Quantity
                                    </label>
                                    <input type="number" id="edit_stock_quantity" name="stock_quantity" min="0"
                                        x-model="edit.stock_quantity" required
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>

                                <div>
                                    <label for="edit_unit_value" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Unit Value
                                    </label>
                                    <input type="number" id="edit_unit_value" name="unit_value" min="0"
                                        x-model="edit.unit_value" placeholder="30"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>
                            </div>

                            <div>
                                <label for="edit_unit" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Unit
                                </label>
                                <select id="edit_unit" name="unit" x-model="edit.unit"
                                    class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    @foreach (['ml', 'L', 'g', 'kg', 'pcs'] as $unitOption)
                                        <option value="{{ $unitOption }}">{{ $unitOption }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="edit_expiration_date" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Expiration Date
                                </label>
                                <input type="date" id="edit_expiration_date" name="expiration_date"
                                    x-model="edit.expiration_date"
                                    class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                        </form>

                        <div class="pt-5 border-t border-gray-200 dark:border-gray-700">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                Deduct Stock
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Reduce the available quantity of this product.
                            </p>

                            <form method="POST"
                                :action="`{{ url('/inventory/products') }}/${edit.id}/deduct`"
                                class="mt-4 space-y-4">
                                @csrf
                                <input type="hidden" name="product_id" :value="edit.id">

                                <div>
                                    <label for="deduct_amount" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Quantity to Deduct
                                    </label>

                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <input type="number" id="deduct_amount" name="amount" min="1"
                                            value="{{ old('amount') }}" placeholder="Enter quantity" required
                                            class="flex-1 min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                        <button type="submit" class="{{ $btn['danger'] }}">
                                            Deduct Stock
                                        </button>
                                    </div>

                                    @error('amount', 'deductStock')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-2 px-4 py-4 border-t border-gray-200 sm:flex-row sm:justify-end sm:px-6 dark:border-gray-700">
                        <button type="button" @click="editOpen = false" class="{{ $btn['secondary'] }}">
                            Close
                        </button>
                        <button type="submit" form="updateProductForm" class="{{ $btn['primary'] }}">
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <div x-show="importHelpOpen" x-transition.opacity
        class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/50"
        :class="{ 'hidden': !importHelpOpen }">

        <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
            <div role="dialog" aria-modal="true" aria-labelledby="csvHelpTitle"
                @click.outside="importHelpOpen = false"
                x-transition
                class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">

                <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div>
                        <h2 id="csvHelpTitle" class="text-lg font-semibold text-gray-900 dark:text-white">
                            CSV Import Format
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Follow the required format before importing inventory products.
                        </p>
                    </div>

                    <button type="button" @click="importHelpOpen = false" aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="px-4 py-6 space-y-4 sm:px-6">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Your CSV file must include the following columns, in this order:
                    </p>

                    <div class="p-4 overflow-x-auto rounded-2xl bg-gray-50 dark:bg-gray-900/40">
                        <code class="text-xs text-gray-800 dark:text-gray-200 whitespace-nowrap">
                            name, brand, stock_quantity, unit_value, unit, expiration_date
                        </code>
                    </div>

                    <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                        <li><span class="font-medium text-gray-900 dark:text-white">name</span> — required</li>
                        <li><span class="font-medium text-gray-900 dark:text-white">brand</span> — optional</li>
                        <li><span class="font-medium text-gray-900 dark:text-white">stock_quantity</span> — required, whole number</li>
                        <li><span class="font-medium text-gray-900 dark:text-white">unit_value</span> — number, for example 30 or 500</li>
                        <li><span class="font-medium text-gray-900 dark:text-white">unit</span> — ml, L, g, kg, or pcs</li>
                        <li><span class="font-medium text-gray-900 dark:text-white">expiration_date</span> — YYYY-MM-DD, optional</li>
                    </ul>

                    <div>
                        <p class="mb-2 text-sm text-gray-600 dark:text-gray-300">Example row:</p>
                        <div class="p-4 overflow-x-auto rounded-2xl bg-gray-50 dark:bg-gray-900/40">
                            <code class="text-xs text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                Aloe Vera Gel, Nature's Best, 24, 250, ml, 2027-03-15
                            </code>
                        </div>
                    </div>

                    <div>
                        <p class="mb-2 text-sm text-gray-600 dark:text-gray-300">
                            How it should look in Excel / Sheets:
                        </p>

                        <a href="{{ asset('images/excelsample.png') }}"
                            target="_blank" rel="noopener" class="block group">
                            <img src="{{ asset('images/excelsample.png') }}"
                                alt="Example of the products CSV file opened in a spreadsheet"
                                class="w-full transition-opacity border border-gray-200 rounded-xl cursor-zoom-in dark:border-gray-700 group-hover:opacity-90">
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                Click to view full size
                            </p>
                        </a>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="button" @click="importHelpOpen = false" class="{{ $btn['secondary'] }}">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function triggerSampleCsvDownload(url) {
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', '');
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

<style>
@media (max-width: 767px) {
    .rt,
    .rt tbody,
    .rt tr,
    .rt td {
        display: block;
        width: 100%;
    }

    .rt thead {
        display: none;
    }

    .rt tr {
        padding: 0.75rem 1rem;
    }

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

    .rt td.rt-actions {
        padding-top: 0.75rem !important;
    }

    .rt td.rt-empty {
        padding: 2rem 0 !important;
        text-align: center !important;
    }
}

@media (max-width: 767px) and (prefers-color-scheme: dark) {
    .rt td[data-label]::before {
        color: #9ca3af;
    }
}
</style>
@endsection
