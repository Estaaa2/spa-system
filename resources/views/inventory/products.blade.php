@extends('layouts.app')

@section('title', 'Product Inventory')

@section('content')
@php
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
        receiveOpen: false,
        deleteOpen: false,
        importHelpOpen: false,

        receiveProduct: {
            id: null,
            name: '',
            usage_unit: 'pcs',
            current_stock: 0
        },

        edit: {
            id: null,
            sku: '',
            barcode: '',
            name: '',
            brand: '',
            description: '',
            category: '',
            inventory_type: 'backbar',
            purchase_unit: '',
            usage_unit: 'pcs',
            conversion_factor: 1,
            retail_price: '',
            acquisition_cost: '',
            reorder_level: 0,
            minimum_stock: '',
            maximum_stock: '',
            current_stock: 0
        },

        deleteProduct: {
            id: null,
            name: ''
        },

        openEdit(p) {
            this.edit = {
                id: p.id,
                sku: p.sku ?? '',
                barcode: p.barcode ?? '',
                name: p.name ?? '',
                brand: p.brand ?? '',
                description: p.description ?? '',
                category: p.category ?? '',
                inventory_type: p.inventory_type ?? 'backbar',
                purchase_unit: p.purchase_unit ?? '',
                usage_unit: p.usage_unit ?? 'pcs',
                conversion_factor: p.conversion_factor ?? 1,
                retail_price: p.retail_price ?? '',
                acquisition_cost: p.acquisition_cost ?? '',
                reorder_level: p.reorder_level ?? 0,
                minimum_stock: p.minimum_stock ?? '',
                maximum_stock: p.maximum_stock ?? '',
                current_stock: p.current_stock ?? 0
            };

            this.editOpen = true;
        },

        openReceive(p) {
            this.receiveProduct = {
                id: p.id,
                name: p.name ?? '',
                usage_unit: p.usage_unit ?? 'pcs',
                current_stock: p.current_stock ?? 0
            };

            this.receiveOpen = true;
        },

        openDelete(p) {
            this.deleteProduct = {
                id: p.id,
                name: p.name
            };

            this.deleteOpen = true;
        }
    }">

    <x-page-header
        title="Product Inventory"
        subtitle="Manage branch inventory, product information, stock levels, and inventory records."
    />

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">

        <div class="flex flex-col gap-4 px-4 py-4 border-b border-gray-200 sm:px-6 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    Product Inventory
                </h2>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    View and manage products available in this branch.
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">

                <button type="button"
                    @click="addOpen = true"
                    class="{{ $btn['primary'] }}">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    Add Product
                </button>

                <a href="{{ route('inventory.batches') }}"
                    class="{{ $btn['outline'] }}">
                    <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>
                    Batches
                </a>

                <a href="{{ route('inventory.products.export') }}"
                    class="{{ $btn['outline'] }}">
                    <i class="fa-solid fa-file-export" aria-hidden="true"></i>
                    Export CSV
                </a>

                <form id="productsImportForm"
                    action="{{ route('inventory.products.import') }}"
                    method="POST"
                    enctype="multipart/form-data">

                    @csrf

                    <input type="file"
                        id="productsCsvFile"
                        name="file"
                        accept=".csv"
                        required
                        class="hidden"
                        onchange="document.getElementById('productsImportForm').submit()">

                    <button type="button"
                        onclick="document.getElementById('productsCsvFile').click()"
                        class="w-full {{ $btn['outline'] }}">
                        <i class="fa-solid fa-file-import" aria-hidden="true"></i>
                        Import CSV
                    </button>
                </form>

                <button type="button"
                    @click="triggerSampleCsvDownload('{{ route('inventory.products.sample-csv') }}'); importHelpOpen = true"
                    class="{{ $btn['outline'] }}"
                    title="CSV format guide">
                    <i class="fa-solid fa-file-csv" aria-hidden="true"></i>
                    CSV Format Guide
                </button>

            </div>
        </div>

        <div class="md:overflow-x-auto">
            <table role="table"
                class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">

                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader"
                            class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Product
                        </th>

                        <th role="columnheader"
                            class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Brand
                        </th>

                        <th role="columnheader"
                            class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Stock
                        </th>

                        <th role="columnheader"
                            class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Unit
                        </th>

                        <th role="columnheader"
                            class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Expiration
                        </th>

                        <th role="columnheader"
                            class="px-6 py-3 text-xs font-medium text-center text-gray-500 uppercase dark:text-gray-400">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody role="rowgroup"
                    class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse($products as $product)
                        @php
                            $stock = $product->branch_stock;
                            $onHand = (float) ($stock?->on_hand_quantity ?? 0);
                            $reorderLevel = (float) ($stock?->reorder_level ?? 0);
                        @endphp
                        <tr role="row"
                            class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td role="cell"
                                data-label="Product"
                                class="px-6 py-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $product->name }}
                                    </p>

                                    @if($product->sku)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            SKU: {{ $product->sku }}
                                        </p>
                                    @endif
                                </div>
                            </td>
                            <td role="cell"
                                data-label="Brand"
                                class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ $product->brand ?? '—' }}
                            </td>
                            <td role="cell"
                                data-label="Stock"
                                class="px-6 py-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ rtrim(rtrim(number_format($onHand, 3, '.', ''), '0'), '.') }}
                                        {{ $product->usage_unit ?? $product->unit ?? 'pcs' }}
                                    </span>

                                    @if($reorderLevel > 0 && $onHand <= $reorderLevel)
                                        <span class="inline-flex items-center px-3 py-1 text-xs font-medium text-red-700 bg-red-100 rounded-full dark:bg-red-900/40 dark:text-red-300">
                                            Low Stock
                                        </span>
                                    @endif

                                    @if(!$product->inventory_reconciled)
                                        <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full text-amber-800 bg-amber-100 dark:bg-amber-900/40 dark:text-amber-300"
                                            title="Branch stock and batch stock do not match. Inventory should be reconciled before further deductions.">
                                            <i class="mr-1 fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                            Stock Mismatch
                                        </span>
                                    @endif

                                </div>
                                @if($reorderLevel > 0)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Reorder at
                                        {{ rtrim(rtrim(number_format($reorderLevel, 3, '.', ''), '0'), '.') }}
                                    </p>
                                @endif
                                @if(!$product->inventory_reconciled)
                                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                                        Batch total:
                                        {{ rtrim(rtrim(number_format((float) $product->batch_quantity, 3, '.', ''), '0'), '.') }}
                                        {{ $product->usage_unit ?? $product->unit ?? 'pcs' }}

                                        · Difference:
                                        {{ rtrim(rtrim(number_format((float) $product->stock_difference, 3, '.', ''), '0'), '.') }}
                                    </p>
                                @endif
                            </td>

                            <td role="cell"
                                data-label="Unit"
                                class="px-6 py-4">
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    <p>
                                        {{ $product->usage_unit ?? $product->unit ?? '—' }}
                                    </p>
                                    @if($product->purchase_unit)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            1 {{ $product->purchase_unit }}
                                            =
                                            {{ rtrim(rtrim(number_format((float) $product->conversion_factor, 3, '.', ''), '0'), '.') }}
                                            {{ $product->usage_unit }}
                                        </p>
                                    @endif
                                </div>
                            </td>

                            <td role="cell"
                                data-label="Expiration"
                                class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                @if($product->nearest_expiration)
                                    {{ $product->nearest_expiration->format('M d, Y') }}
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">—</span>
                                @endif
                            </td>

                            <td role="cell"
                                data-label="Actions"
                                class="px-6 py-4 text-center rt-actions">

                                <div class="flex flex-wrap gap-2 md:justify-center">
                                    <button type="button"
                                        @click="openReceive({
                                            id: {{ $product->id }},
                                            name: @js($product->name),
                                            usage_unit: @js($product->usage_unit ?? $product->unit ?? 'pcs'),
                                            current_stock: {{ (float) ($product->branch_stock?->on_hand_quantity ?? 0) }}
                                        })"
                                        class="{{ $btn['outline'] }}">
                                        <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>
                                        Receive
                                    </button>
                                    
                                    <button type="button"
                                        @click="openEdit({
                                            id: {{ $product->id }},
                                            sku: @js($product->sku),
                                            barcode: @js($product->barcode),
                                            name: @js($product->name),
                                            brand: @js($product->brand),
                                            description: @js($product->description),
                                            category: @js($product->category),
                                            inventory_type: @js($product->inventory_type ?? 'backbar'),
                                            purchase_unit: @js($product->purchase_unit),
                                            usage_unit: @js($product->usage_unit ?? $product->unit ?? 'pcs'),
                                            conversion_factor: {{ (float) ($product->conversion_factor ?? 1) }},
                                            retail_price: @js($product->retail_price),
                                            acquisition_cost: @js($product->acquisition_cost),
                                            reorder_level: {{ (float) ($product->branch_stock?->reorder_level ?? 0) }},
                                            minimum_stock: @js($product->branch_stock?->minimum_stock),
                                            maximum_stock: @js($product->branch_stock?->maximum_stock),
                                            current_stock: {{ (float) ($product->branch_stock?->on_hand_quantity ?? 0) }}
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
                            <td role="cell"
                                colspan="6"
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

        @if($products->hasPages() || $products->total() > 0)
            <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Showing
                        <span class="font-medium text-gray-700 dark:text-gray-200">
                            {{ $products->firstItem() ?? 0 }}
                        </span>
                        to
                        <span class="font-medium text-gray-700 dark:text-gray-200">
                            {{ $products->lastItem() ?? 0 }}
                        </span>
                        of
                        <span class="font-medium text-gray-700 dark:text-gray-200">
                            {{ $products->total() }}
                        </span>
                        results
                    </p>
                    @if($products->hasPages())
                        <div class="pagination-wrapper">
                            {{ $products->links() }}
                        </div>
                    @endif
                </div>
            </div>
        @endif

    </div>

    {{-- Delete Modal --}}
    <div x-show="deleteOpen"
        x-transition.opacity
        class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50"
        :class="{ 'hidden': !deleteOpen }"
        @keydown.escape.window="deleteOpen = false">

        <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
            <div role="alertdialog"
                aria-modal="true"
                aria-labelledby="deleteProductTitle"
                aria-describedby="deleteProductDescription"
                @click.outside="deleteOpen = false"
                x-transition
                class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
                <h2 id="deleteProductTitle"
                    class="text-lg font-semibold text-gray-900 dark:text-white">
                    Remove Product
                </h2>
                <p id="deleteProductDescription"
                    class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to remove
                    <span class="font-medium text-gray-900 dark:text-white"
                        x-text="deleteProduct.name"></span>
                    from this branch?
                </p>
                <div class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">

                    <button type="button"
                        @click="deleteOpen = false"
                        class="w-full {{ $btn['secondary'] }} sm:w-auto">
                        Cancel
                    </button>

                    <form method="POST"
                        :action="`{{ url('/inventory/products') }}/${deleteProduct.id}`">

                        @csrf
                        @method('DELETE')

                        <button type="submit"
                            class="w-full {{ $btn['danger'] }} sm:w-auto">
                            Yes, Remove
                        </button>

                    </form>

                </div>

            </div>
        </div>
    </div>

    {{-- Add Product Modal --}}
    <template x-teleport="body">
        <div x-show="addOpen"
            x-transition.opacity
            class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50"
            :class="{ 'hidden': !addOpen }"
            @keydown.escape.window="addOpen = false">

            <div class="flex items-start justify-center min-h-full p-4 sm:items-center">

                <div role="dialog"
                    aria-modal="true"
                    aria-labelledby="addProductTitle"
                    @click.outside="addOpen = false"
                    x-transition
                    class="w-full max-w-2xl bg-white shadow-xl rounded-2xl dark:bg-gray-800">

                    <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">

                        <div>
                            <h2 id="addProductTitle"
                                class="text-lg font-semibold text-gray-900 dark:text-white">
                                Add Product
                            </h2>

                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Add a new product to the current branch inventory.
                            </p>
                        </div>

                        <button type="button"
                            @click="addOpen = false"
                            aria-label="Close dialog"
                            class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">

                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>

                    </div>

                    <form method="POST"
                        action="{{ route('inventory.products.store') }}">

                        @csrf

                        <div class="px-4 py-5 space-y-4 overflow-y-auto sm:px-6 max-h-[calc(100vh-12rem)]">

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                                <div>
                                    <label for="add_sku"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        SKU
                                    </label>

                                    <input id="add_sku"
                                        name="sku"
                                        value="{{ old('sku') }}"
                                        placeholder="Optional"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                    @error('sku')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="add_barcode"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Barcode
                                    </label>

                                    <input id="add_barcode"
                                        name="barcode"
                                        value="{{ old('barcode') }}"
                                        placeholder="Optional"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                    @error('barcode')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                            </div>

                            <div>
                                <label for="add_product_name"
                                    class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Product Name
                                </label>

                                <input id="add_product_name"
                                    name="name"
                                    value="{{ old('name') }}"
                                    required
                                    class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                @error('name')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                                <div>
                                    <label for="add_product_brand"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Brand Name
                                    </label>

                                    <input id="add_product_brand"
                                        name="brand"
                                        value="{{ old('brand') }}"
                                        placeholder="Optional"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                    @error('brand')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="add_category"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Category
                                    </label>

                                    <input id="add_category"
                                        name="category"
                                        value="{{ old('category') }}"
                                        placeholder="Example: Massage Oils"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                    @error('category')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                            </div>

                            <div>
                                <label for="add_description"
                                    class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Description
                                </label>

                                <textarea id="add_description"
                                    name="description"
                                    rows="3"
                                    placeholder="Optional"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">{{ old('description') }}</textarea>

                                @error('description')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div>
                                <label for="add_inventory_type"
                                    class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Inventory Type
                                </label>

                                <select id="add_inventory_type"
                                    name="inventory_type"
                                    required
                                    class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                    <option value="backbar"
                                        @selected(old('inventory_type', 'backbar') === 'backbar')>
                                        Backbar / Service Use
                                    </option>

                                    <option value="retail"
                                        @selected(old('inventory_type') === 'retail')>
                                        Retail
                                    </option>

                                    <option value="both"
                                        @selected(old('inventory_type') === 'both')>
                                        Retail & Backbar
                                    </option>

                                </select>

                                @error('inventory_type')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                                <div>
                                    <label for="add_purchase_unit"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Purchase Unit
                                    </label>

                                    <input id="add_purchase_unit"
                                        name="purchase_unit"
                                        value="{{ old('purchase_unit') }}"
                                        placeholder="Example: bottle"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                    @error('purchase_unit')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="add_usage_unit"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Usage Unit
                                    </label>

                                    <select id="add_usage_unit"
                                        name="usage_unit"
                                        required
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                        @foreach (['ml', 'L', 'g', 'kg', 'pcs'] as $unitOption)
                                            <option value="{{ $unitOption }}"
                                                @selected(old('usage_unit', 'pcs') === $unitOption)>
                                                {{ $unitOption }}
                                            </option>
                                        @endforeach

                                    </select>

                                    @error('usage_unit')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                            </div>

                            <div>
                                <label for="add_conversion_factor"
                                    class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Conversion Factor
                                </label>

                                <input type="number"
                                    id="add_conversion_factor"
                                    name="conversion_factor"
                                    min="0.001"
                                    max="20000"
                                    step="0.001"
                                    inputmode="decimal"
                                    data-stock-limit="20000"
                                    data-stock-decimals="3"
                                    value="{{ old('conversion_factor', 1) }}"
                                    required
                                    class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Example: if 1 bottle contains 1000 ml, enter 1000.
                                </p>

                                @error('conversion_factor')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                                <div>
                                    <label for="add_acquisition_cost"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Acquisition Cost
                                    </label>

                                    <input type="number"
                                        id="add_acquisition_cost"
                                        name="acquisition_cost"
                                        step="0.01"
                                        min="0"
                                        value="{{ old('acquisition_cost') }}"
                                        placeholder="Optional"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                    @error('acquisition_cost')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="add_retail_price"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Retail Price
                                    </label>

                                    <input type="number"
                                        id="add_retail_price"
                                        name="retail_price"
                                        step="0.01"
                                        min="0"
                                        value="{{ old('retail_price') }}"
                                        placeholder="Optional"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                    @error('retail_price')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                                <div>
                                    <label for="add_opening_stock"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Opening Stock
                                    </label>

                                    <input type="number"
                                        id="add_opening_stock"
                                        name="opening_stock"
                                        min="0"
                                        max="20000"
                                        step="0.001"
                                        inputmode="decimal"
                                        data-stock-limit="20000"
                                        data-stock-decimals="3"
                                        value="{{ old('opening_stock', 0) }}"
                                        required
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                    @error('opening_stock')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="add_reorder_level"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Reorder Level
                                    </label>

                                    <input type="number"
                                        id="add_reorder_level"
                                        name="reorder_level"
                                        min="0"
                                        max="20000"
                                        step="0.001"
                                        inputmode="decimal"
                                        data-stock-limit="20000"
                                        data-stock-decimals="3"
                                        value="{{ old('reorder_level', 5) }}"
                                        required
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                    @error('reorder_level')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                                <div>
                                    <label for="add_minimum_stock"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Minimum Stock
                                    </label>

                                    <input type="number"
                                        id="add_minimum_stock"
                                        name="minimum_stock"
                                        min="0"
                                        max="20000"
                                        step="0.001"
                                        inputmode="decimal"
                                        data-stock-limit="20000"
                                        data-stock-decimals="3"
                                        value="{{ old('minimum_stock') }}"
                                        placeholder="Optional"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                    @error('minimum_stock')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="add_maximum_stock"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Maximum Stock
                                    </label>

                                    <input type="number"
                                        id="add_maximum_stock"
                                        name="maximum_stock"
                                        min="0"
                                        max="20000"
                                        step="0.001"
                                        inputmode="decimal"
                                        data-stock-limit="20000"
                                        data-stock-decimals="3"
                                        value="{{ old('maximum_stock') }}"
                                        placeholder="Optional"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                    @error('maximum_stock')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                            </div>

                        </div>

                        <div class="flex flex-col-reverse gap-2 px-4 py-4 border-t border-gray-200 sm:flex-row sm:justify-end sm:px-6 dark:border-gray-700">

                            <button type="button"
                                @click="addOpen = false"
                                class="w-full {{ $btn['secondary'] }} sm:w-auto">
                                Cancel
                            </button>

                            <button type="submit"
                                class="w-full {{ $btn['primary'] }} sm:w-auto">
                                Save Product
                            </button>

                        </div>

                    </form>

                </div>
            </div>
        </div>
    </template>

    {{-- Receive Stock Modal --}}
    <template x-teleport="body">
        <div x-show="receiveOpen"
            x-transition.opacity
            class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50"
            :class="{ 'hidden': !receiveOpen }"
            @keydown.escape.window="receiveOpen = false">

            <div class="flex items-start justify-center min-h-full p-4 sm:items-center">

                <div role="dialog"
                    aria-modal="true"
                    aria-labelledby="receiveStockTitle"
                    x-transition
                    class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">

                    <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">

                        <div>
                            <h2 id="receiveStockTitle"
                                class="text-lg font-semibold text-gray-900 dark:text-white">
                                Receive Stock
                            </h2>

                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Record newly received inventory as a product batch.
                            </p>
                        </div>

                        <button type="button"
                            @click="receiveOpen = false"
                            aria-label="Close dialog"
                            class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">

                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>

                    </div>

                    <form method="POST"
                        :action="`{{ url('/inventory/products') }}/${receiveProduct.id}/receive-stock`">

                        @csrf

                        <div class="px-4 py-5 space-y-4 sm:px-6">

                            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/40">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white"
                                    x-text="receiveProduct.name">
                                </p>

                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Current Stock:
                                    <span class="font-medium text-gray-700 dark:text-gray-200"
                                        x-text="receiveProduct.current_stock">
                                    </span>

                                    <span x-text="receiveProduct.usage_unit"></span>
                                </p>
                            </div>

                            <div>
                                <label for="receive_batch_number"
                                    class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Batch Number
                                </label>

                                <input type="text"
                                    id="receive_batch_number"
                                    name="batch_number"
                                    value="{{ old('batch_number') }}"
                                    placeholder="Example: LAV-2026-001"
                                    required
                                    class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                @error('batch_number', 'receiveStock')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div>
                                <label for="receive_quantity"
                                    class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Quantity Received
                                </label>

                                <div class="flex">
                                    <input type="number"
                                        id="receive_quantity"
                                        name="received_quantity"
                                        min="0"
                                        max="20000"
                                        step="0.001"
                                        inputmode="decimal"
                                        data-stock-limit="20000"
                                        data-stock-decimals="3"
                                        value="{{ old('received_quantity') }}"
                                        required
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-l-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                    <div class="inline-flex items-center px-3 text-sm text-gray-500 border border-l-0 border-gray-300 rounded-r-xl bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400"
                                        x-text="receiveProduct.usage_unit">
                                    </div>
                                </div>

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Enter the quantity using the product's usage unit.
                                </p>

                                @error('received_quantity', 'receiveStock')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div>
                                <label for="receive_unit_cost"
                                    class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Unit Cost
                                </label>

                                <input type="number"
                                    id="receive_unit_cost"
                                    name="unit_cost"
                                    step="0.01"
                                    min="0"
                                    value="{{ old('unit_cost') }}"
                                    placeholder="Optional"
                                    class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                @error('unit_cost', 'receiveStock')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                                <div>
                                    <label for="receive_manufactured_at"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Manufactured Date
                                    </label>

                                    <input type="date"
                                        id="receive_manufactured_at"
                                        name="manufactured_at"
                                        value="{{ old('manufactured_at') }}"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>

                                <div>
                                    <label for="receive_expiration_date"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Expiration Date
                                    </label>

                                    <input type="date"
                                        id="receive_expiration_date"
                                        name="expiration_date"
                                        value="{{ old('expiration_date') }}"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                    @error('expiration_date', 'receiveStock')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                            </div>

                            <div>
                                <label for="receive_notes"
                                    class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Notes
                                </label>

                                <textarea id="receive_notes"
                                    name="notes"
                                    rows="3"
                                    placeholder="Example: Delivered by main supplier"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">{{ old('notes') }}</textarea>
                            </div>

                        </div>

                        <div class="flex flex-col-reverse gap-2 px-4 py-4 border-t border-gray-200 sm:flex-row sm:justify-end sm:px-6 dark:border-gray-700">

                            <button type="button"
                                @click="receiveOpen = false"
                                class="w-full {{ $btn['secondary'] }} sm:w-auto">
                                Cancel
                            </button>

                            <button type="submit"
                                class="w-full {{ $btn['primary'] }} sm:w-auto">

                                <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>
                                Receive Stock
                            </button>

                        </div>

                    </form>

                </div>
            </div>
        </div>
    </template>

    {{-- Edit Product Modal --}}
    <template x-teleport="body">
        <div x-show="editOpen"
            x-transition.opacity
            class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50"
            :class="{ 'hidden': !editOpen }"
            @keydown.escape.window="editOpen = false">

            <div class="flex items-start justify-center min-h-full p-4 sm:items-center">

                <div role="dialog"
                    aria-modal="true"
                    aria-labelledby="editProductTitle"
                    @click.outside="editOpen = false"
                    x-transition
                    class="w-full max-w-2xl bg-white shadow-xl rounded-2xl dark:bg-gray-800">

                    <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">

                        <div>
                            <h2 id="editProductTitle"
                                class="text-lg font-semibold text-gray-900 dark:text-white">
                                Edit Product
                            </h2>

                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Update product information or adjust this branch's available stock.
                            </p>
                        </div>

                        <button type="button"
                            @click="editOpen = false"
                            aria-label="Close dialog"
                            class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">

                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>

                    </div>

                    <div class="px-4 py-4 space-y-5 overflow-y-auto sm:px-6 sm:py-5 max-h-[calc(100vh-10rem)] sm:max-h-[75vh]">

                        <form method="POST"
                            id="updateProductForm"
                            :action="`{{ url('/inventory/products') }}/${edit.id}`"
                            class="space-y-4">

                            @csrf
                            @method('PUT')

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                                <div>
                                    <label for="edit_sku"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        SKU
                                    </label>

                                    <input id="edit_sku"
                                        name="sku"
                                        x-model="edit.sku"
                                        placeholder="Optional"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>

                                <div>
                                    <label for="edit_barcode"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Barcode
                                    </label>

                                    <input id="edit_barcode"
                                        name="barcode"
                                        x-model="edit.barcode"
                                        placeholder="Optional"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>

                            </div>

                            <div>
                                <label for="edit_product_name"
                                    class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Product Name
                                </label>

                                <input id="edit_product_name"
                                    name="name"
                                    x-model="edit.name"
                                    required
                                    class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                                <div>
                                    <label for="edit_product_brand"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Brand Name
                                    </label>

                                    <input id="edit_product_brand"
                                        name="brand"
                                        x-model="edit.brand"
                                        placeholder="Optional"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>

                                <div>
                                    <label for="edit_category"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Category
                                    </label>

                                    <input id="edit_category"
                                        name="category"
                                        x-model="edit.category"
                                        placeholder="Optional"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>

                            </div>

                            <div>
                                <label for="edit_description"
                                    class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Description
                                </label>

                                <textarea id="edit_description"
                                    name="description"
                                    rows="3"
                                    x-model="edit.description"
                                    placeholder="Optional"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"></textarea>
                            </div>

                            <div>
                                <label for="edit_inventory_type"
                                    class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Inventory Type
                                </label>

                                <select id="edit_inventory_type"
                                    name="inventory_type"
                                    x-model="edit.inventory_type"
                                    required
                                    class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                    <option value="backbar">
                                        Backbar / Service Use
                                    </option>

                                    <option value="retail">
                                        Retail
                                    </option>

                                    <option value="both">
                                        Retail & Backbar
                                    </option>

                                </select>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                                <div>
                                    <label for="edit_purchase_unit"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Purchase Unit
                                    </label>

                                    <input id="edit_purchase_unit"
                                        name="purchase_unit"
                                        x-model="edit.purchase_unit"
                                        placeholder="Example: bottle"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>

                                <div>
                                    <label for="edit_usage_unit"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Usage Unit
                                    </label>

                                    <select id="edit_usage_unit"
                                        name="usage_unit"
                                        x-model="edit.usage_unit"
                                        required
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                        @foreach (['ml', 'L', 'g', 'kg', 'pcs'] as $unitOption)
                                            <option value="{{ $unitOption }}">
                                                {{ $unitOption }}
                                            </option>
                                        @endforeach

                                    </select>
                                </div>

                            </div>

                            <div>
                                <label for="edit_conversion_factor"
                                    class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Conversion Factor
                                </label>

                                <input type="number"
                                    id="edit_conversion_factor"
                                    name="conversion_factor"
                                    min="0.001"
                                    max="20000"
                                    step="0.001"
                                    inputmode="decimal"
                                    data-stock-limit="20000"
                                    data-stock-decimals="3"
                                    x-model="edit.conversion_factor"
                                    required
                                    class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Number of usage units contained in one purchase unit.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                                <div>
                                    <label for="edit_acquisition_cost"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Acquisition Cost
                                    </label>

                                    <input type="number"
                                        id="edit_acquisition_cost"
                                        name="acquisition_cost"
                                        step="0.01"
                                        min="0"
                                        x-model="edit.acquisition_cost"
                                        placeholder="Optional"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>

                                <div>
                                    <label for="edit_retail_price"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Retail Price
                                    </label>

                                    <input type="number"
                                        id="edit_retail_price"
                                        name="retail_price"
                                        step="0.01"
                                        min="0"
                                        x-model="edit.retail_price"
                                        placeholder="Optional"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>

                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

                                <div>
                                    <label for="edit_reorder_level"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Reorder Level
                                    </label>

                                    <input type="number"
                                        id="edit_reorder_level"
                                        name="reorder_level"
                                        step="0.001"
                                        min="0"
                                        x-model="edit.reorder_level"
                                        required
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>

                                <div>
                                    <label for="edit_minimum_stock"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Minimum
                                    </label>

                                    <input type="number"
                                        id="edit_minimum_stock"
                                        name="minimum_stock"
                                        min="0"
                                        max="20000"
                                        step="0.001"
                                        inputmode="decimal"
                                        data-stock-limit="20000"
                                        data-stock-decimals="3"
                                        x-model="edit.minimum_stock"
                                        placeholder="Optional"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>

                                <div>
                                    <label for="edit_maximum_stock"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Maximum
                                    </label>

                                    <input type="number"
                                        id="edit_maximum_stock"
                                        name="maximum_stock"
                                        min="0"
                                        max="20000"
                                        step="0.001"
                                        inputmode="decimal"
                                        data-stock-limit="20000"
                                        data-stock-decimals="3"
                                        x-model="edit.maximum_stock"
                                        placeholder="Optional"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>

                            </div>

                        </form>

                        {{-- Stock Adjustment --}}
                        <div class="pt-5 border-t border-gray-200 dark:border-gray-700">

                            <div class="mb-4">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                    Stock Adjustment
                                </h3>

                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Update this branch's available stock with a recorded reason.
                                </p>
                            </div>

                            <form method="POST"
                                :action="`{{ url('/inventory/products') }}/${edit.id}/adjust-stock`"
                                id="adjustStockForm"
                                class="space-y-4">

                                @csrf

                                <input type="hidden"
                                    name="product_id"
                                    :value="edit.id">

                                <div>
                                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Current Stock
                                    </label>

                                    <div class="flex items-center w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl bg-gray-50 dark:bg-gray-900 dark:border-gray-600">

                                        <span class="font-medium text-gray-900 dark:text-white"
                                            x-text="edit.current_stock"></span>

                                        <span class="ml-1 text-gray-500 dark:text-gray-400"
                                            x-text="edit.usage_unit"></span>

                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                                    <div>
                                        <label for="adjustment_type"
                                            class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Adjustment Type
                                        </label>

                                        <select id="adjustment_type"
                                            name="adjustment_type"
                                            required
                                            class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                            <option value="increase">
                                                Increase Stock
                                            </option>

                                            <option value="decrease">
                                                Decrease Stock
                                            </option>

                                        </select>
                                    </div>

                                    <div>
                                        <label for="adjustment_quantity"
                                            class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Quantity
                                        </label>

                                        <div class="flex">

                                            <input type="number"
                                                id="adjustment_quantity"
                                                name="quantity"
                                                min="0.001"
                                                max="20000"
                                                step="0.001"
                                                inputmode="decimal"
                                                data-stock-limit="20000"
                                                data-stock-decimals="3"
                                                value="{{ old('quantity') }}"
                                                placeholder="Enter quantity"
                                                required
                                                class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-l-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                            <div class="inline-flex items-center px-3 text-sm text-gray-500 border border-l-0 border-gray-300 rounded-r-xl bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400"
                                                x-text="edit.usage_unit">
                                            </div>

                                        </div>

                                        @error('quantity', 'adjustStock')
                                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>

                                </div>

                                <div>
                                    <label for="adjustment_reason"
                                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Reason
                                    </label>

                                    <textarea id="adjustment_reason"
                                        name="reason"
                                        rows="3"
                                        required
                                        placeholder="Example: Physical stock count correction"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">{{ old('reason') }}</textarea>

                                    @error('reason', 'adjustStock')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>
                            </form>
                        </div>

                    </div>

                    <div class="flex flex-col gap-2 px-4 py-4 border-t border-gray-200 sm:flex-row sm:items-center sm:justify-end sm:px-6 dark:border-gray-700">
                        <div class="relative w-full group sm:w-auto">
                            <button type="button"
                                @click="editOpen = false"
                                aria-describedby="closeProductHelp"
                                class="w-full {{ $btn['secondary'] }} sm:w-auto">
                                Close
                            </button>
                            <div id="closeProductHelp"
                                role="tooltip"
                                class="absolute z-20 hidden w-56 p-3 mb-2 text-xs text-white -translate-x-1/2 bg-gray-900 shadow-lg pointer-events-none bottom-full left-1/2 rounded-xl group-hover:block group-focus-within:block dark:bg-gray-950">
                                Close this window without submitting product information or stock changes.
                            </div>
                        </div>
                        <div class="relative w-full group sm:w-auto">
                            <button type="submit"
                                form="adjustStockForm"
                                aria-describedby="adjustStockHelp"
                                class="w-full {{ $btn['outline'] }} sm:w-auto">
                                Apply Stock Adjustment
                            </button>
                            <div id="adjustStockHelp"
                                role="tooltip"
                                class="absolute z-20 hidden w-64 p-3 mb-2 text-xs text-white -translate-x-1/2 bg-gray-900 shadow-lg pointer-events-none bottom-full left-1/2 rounded-xl group-hover:block group-focus-within:block dark:bg-gray-950">
                                Changes the actual branch stock quantity. Decreases use FEFO and create permanent inventory movement records.
                            </div>
                        </div>
                        <div class="relative w-full group sm:w-auto">
                            <button type="submit"
                                form="updateProductForm"
                                aria-describedby="saveProductHelp"
                                class="w-full {{ $btn['primary'] }} sm:w-auto">
                                Save Changes
                            </button>
                            <div id="saveProductHelp"
                                role="tooltip"
                                class="absolute z-20 hidden w-64 p-3 mb-2 text-xs text-white -translate-x-1/2 bg-gray-900 shadow-lg pointer-events-none bottom-full left-1/2 rounded-xl group-hover:block group-focus-within:block dark:bg-gray-950">
                                Saves product information such as name, units, prices, category, and stock thresholds. It does not change the current stock quantity.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- CSV Guide --}}
    <div x-show="importHelpOpen"
        x-transition.opacity
        class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50"
        :class="{ 'hidden': !importHelpOpen }"
        @keydown.escape.window="importHelpOpen = false">

        <div class="flex items-start justify-center min-h-full p-4 sm:items-center">

            <div role="dialog"
                aria-modal="true"
                aria-labelledby="csvHelpTitle"
                @click.outside="importHelpOpen = false"
                x-transition
                class="w-full max-w-xl bg-white shadow-xl rounded-2xl dark:bg-gray-800">

                <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">

                    <div>
                        <h2 id="csvHelpTitle"
                            class="text-lg font-semibold text-gray-900 dark:text-white">
                            CSV Import Format
                        </h2>

                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Use the ERP product format when importing product information.
                        </p>
                    </div>

                    <button type="button"
                        @click="importHelpOpen = false"
                        aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">

                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>

                </div>

                <div class="px-4 py-6 space-y-4 overflow-y-auto sm:px-6 max-h-[75vh]">

                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Your CSV file must include these columns:
                    </p>

                    <div class="p-4 overflow-x-auto rounded-xl bg-gray-50 dark:bg-gray-900/40">
                        <code class="text-xs text-gray-800 dark:text-gray-200 whitespace-nowrap">
                            sku, barcode, name, brand, description, category, inventory_type, purchase_unit, usage_unit, conversion_factor, retail_price, acquisition_cost, reorder_level, minimum_stock, maximum_stock
                        </code>
                    </div>

                    <div class="p-4 border border-blue-200 rounded-xl bg-blue-50 dark:border-blue-900/50 dark:bg-blue-900/20">

                        <div class="flex items-start gap-3">
                            <i class="mt-0.5 text-blue-600 fa-solid fa-circle-info dark:text-blue-400"
                                aria-hidden="true"></i>

                            <div>
                                <p class="text-sm font-medium text-blue-800 dark:text-blue-300">
                                    Stock quantities are not imported from CSV.
                                </p>

                                <p class="mt-1 text-xs text-blue-700 dark:text-blue-400">
                                    Use Stock Adjustment and future inventory transactions to change available stock.
                                </p>
                            </div>
                        </div>

                    </div>

                    <div class="space-y-3">

                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                inventory_type
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Allowed values: retail, backbar, or both.
                            </p>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                usage_unit
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Allowed values: ml, L, g, kg, or pcs.
                            </p>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                conversion_factor
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Number of usage units inside one purchase unit.
                            </p>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                reorder_level
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                The inventory level where the Low Stock warning begins.
                            </p>
                        </div>

                    </div>

                    <div>
                        <p class="mb-2 text-sm font-medium text-gray-900 dark:text-white">
                            Example row
                        </p>

                        <div class="p-4 overflow-x-auto rounded-xl bg-gray-50 dark:bg-gray-900/40">
                            <code class="text-xs text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                OIL-LAV-001,,Lavender Massage Oil,ZenCare,Lavender massage oil,Massage Oils,backbar,bottle,ml,1000,,250,1000,500,10000
                            </code>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="button"
                            @click="importHelpOpen = false"
                            class="{{ $btn['secondary'] }}">
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
.pagination-wrapper nav > div:first-child {
    display: none;
}

.pagination-wrapper nav > div:last-child {
    display: flex;
    align-items: center;
    justify-content: flex-end;
}

.pagination-wrapper nav > div:last-child > div:first-child {
    display: none;
}

@media (max-width: 639px) {
    .pagination-wrapper {
        width: 100%;
    }

    .pagination-wrapper nav {
        width: 100%;
    }

    .pagination-wrapper nav > div:last-child {
        justify-content: space-between;
    }
}
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

    .rt tbody {
        display: grid;
        gap: 0.75rem;
        padding: 0.75rem;
        background: #f9fafb;
    }

    .rt tr {
        padding: 0.875rem 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.875rem;
        background: #ffffff;
    }

    .rt td {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.5rem 0 !important;
        text-align: right !important;
        border: 0 !important;
    }

    .rt td[data-label]::before {
        content: attr(data-label);
        flex-shrink: 0;
        max-width: 42%;
        text-align: left;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .rt td > * {
        min-width: 0;
        max-width: 58%;
    }

    .rt td[data-label="Stock"] > div,
    .rt td[data-label="Unit"] > div {
        justify-content: flex-end;
        text-align: right;
    }

    .rt td.rt-actions {
        display: block;
        padding-top: 0.875rem !important;
        margin-top: 0.375rem;
        border-top: 1px solid #e5e7eb !important;
    }

    .rt td.rt-actions::before {
        display: none;
    }

    .rt td.rt-actions > div {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.5rem;
        max-width: none;
    }

    .rt td.rt-actions button {
        width: 100%;
    }

    .rt td.rt-empty {
        display: block;
        padding: 2rem 1rem !important;
        text-align: center !important;
    }

    .rt td.rt-empty::before {
        display: none;
    }

    .rt td.rt-empty > * {
        max-width: none;
    }
}

@media (min-width: 768px) and (max-width: 1023px) {
    .rt th,
    .rt td {
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .rt-actions > div {
        flex-direction: column;
        align-items: stretch;
    }

    .rt-actions button {
        width: 100%;
    }
}

@media (max-width: 767px) and (prefers-color-scheme: dark) {
    .rt tbody {
        background: #111827;
    }

    .rt tr {
        background: #1f2937;
        border-color: #374151;
    }

    .rt td[data-label]::before {
        color: #9ca3af;
    }

    .rt td.rt-actions {
        border-top-color: #374151 !important;
    }
}
</style>

@endsection