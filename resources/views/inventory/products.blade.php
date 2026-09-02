@extends('layouts.app')

@section('title', 'Inventory Products')
@section('content')

    @php
        $deductFailedProduct = null;

        if ($errors->deductStock->any() && old('product_id')) {
            $deductFailedProduct = $products->firstWhere('id', (int) old('product_id'));
        }
    @endphp

    <div class="p-6" x-data="{
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
    @endif"
    >

        <x-page-header title="Inventory Products" subtitle="Manage inventory products." />


        <div class="bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b dark:border-gray-700">
                <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                    Inventory List
                </h2>

                <div class="flex items-center gap-3">
                    <button type="button" @click="addOpen = true"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-lg bg-[#8B7355] hover:opacity-90">
                        <i class="fa-solid fa-plus"></i>
                        Add Product
                    </button>

                    <a href="{{ route('inventory.products.export') }}"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border rounded-lg bg-white text-[#8B7355] border-[#8B7355] hover:bg-[#F8F5F1] dark:bg-gray-800 dark:text-[#D2B48C] dark:border-[#8B7355] dark:hover:bg-gray-700">
                        <i class="fa-solid fa-download"></i>
                        Export CSV
                    </a>

                    <form id="productsImportForm" action="{{ route('inventory.products.import') }}" method="POST"
                        enctype="multipart/form-data" class="inline">
                        @csrf
                        <input type="file" id="productsCsvFile" name="file" accept=".csv" required class="hidden"
                            onchange="document.getElementById('productsImportForm').submit()">
                        <button type="button" onclick="document.getElementById('productsCsvFile').click()"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border rounded-lg bg-white text-[#8B7355] border-[#8B7355] hover:bg-[#F8F5F1] dark:bg-gray-800 dark:text-[#D2B48C] dark:border-[#8B7355] dark:hover:bg-gray-700">
                            <i class="fa-solid fa-upload"></i>
                            Import CSV
                        </button>
                    </form>
                    <button type="button" @click="importHelpOpen = true"
                        class="inline-flex items-center justify-center w-9 h-9 text-[#8B7355] rounded-lg hover:bg-[#F8F5F1] dark:text-[#D2B48C] dark:border-[#8B7355] dark:hover:bg-gray-700"
                        title="CSV format guide">
                        <i class="fa-solid fa-circle-info"></i>
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr class="text-left">
                            <th class="px-6 py-3 font-medium text-gray-600 dark:text-gray-300">Product Name</th>
                            <th class="px-6 py-3 font-medium text-gray-600 dark:text-gray-300">Brand Name</th>
                            <th class="px-6 py-3 font-medium text-gray-600 dark:text-gray-300">Stock Quantity</th>
                            <th class="px-6 py-3 font-medium text-gray-600 dark:text-gray-300">Unit</th>
                            <th class="px-6 py-3 font-medium text-gray-600 dark:text-gray-300">Expiration Date</th>
                            <th class="px-6 py-3 font-medium text-gray-600 dark:text-gray-300">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y dark:divide-gray-700">
                        @forelse($products as $product)
                            <tr>
                                <td class="px-6 py-3 text-gray-800 dark:text-gray-100">{{ $product->name }}</td>
                                <td class="px-6 py-3 text-gray-700 dark:text-gray-200">{{ $product->brand ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-1 text-gray-800 rounded dark:bg-gray-700 dark:text-gray-100">
                                            {{ $product->stock_quantity }}
                                        </span>

                                        @if ($product->stock_quantity <= 5)
                                            <span
                                                class="px-2 py-1 text-xs font-semibold text-white bg-red-500 rounded-full">
                                                Low
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-gray-700 dark:text-gray-200">
                                    {{ $product->unit_value ?? 0 }}{{ $product->unit ?? 'ml' }}</td>
                                <td class="px-6 py-3 text-gray-700 dark:text-gray-200">
                                    {{ $product->expiration_date?->format('M d, Y') ?? '—' }}
                                </td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
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
                                            class="inline-flex items-center gap-1.5 px-3 py-1 text-sm text-[#6F5430] bg-[#F0E9E1] rounded hover:bg-[#E5D9C9] dark:bg-gray-700 dark:text-[#D2B48C] dark:hover:bg-gray-600 whitespace-nowrap">
                                            <i class="text-xs fa-solid fa-pen"></i>
                                            <span>Edit</span>
                                        </button>

                                        <button type="button"
                                            @click="openDelete({
                                            id: {{ $product->id }},
                                            name: @js($product->name)
                                        })"
                                            class="inline-flex items-center gap-1.5 px-3 py-1 text-sm text-white bg-red-600 rounded hover:bg-red-700 whitespace-nowrap">
                                            <i class="text-xs fa-solid fa-trash"></i>
                                            <span>Remove</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                                    No products found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4">
                {{ $products->links() }}
            </div>
        </div>

        <!-- Delete Product Modal -->
        <div x-show="deleteOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="display:none;">

            <!-- backdrop -->
            <div class="absolute inset-0 bg-black/50" @click="deleteOpen = false"></div>

            <div x-transition
                class="relative w-full max-w-md bg-white border shadow-lg rounded-xl dark:bg-gray-800 dark:border-gray-700">

                <div class="px-6 py-4 border-b dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
                        Remove Product
                    </h3>
                </div>

                <div class="p-6 space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Are you sure you want to remove
                        <span class="font-semibold text-red-600" x-text="deleteProduct.name"></span>?
                    </p>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="deleteOpen = false"
                            class="px-4 py-2 text-sm font-medium bg-white border rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200">
                            Cancel
                        </button>

                        <form method="POST" :action="`{{ url('/inventory/products') }}/${deleteProduct.id}`">
                            @csrf
                            @method('DELETE')

                            <button class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">
                                Yes, Remove
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Product Modal -->
        <div x-show="addOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="display:none;">
            <!-- backdrop -->
            <div class="absolute inset-0 bg-black/50" @click="addOpen = false"></div>

            <!-- modal -->
            <div x-transition
                class="relative w-full max-w-lg bg-white border shadow-lg rounded-xl dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between px-6 py-4 border-b dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Add Product</h3>
                    <button type="button" @click="addOpen = false"
                        class="text-gray-500 hover:text-gray-800 dark:hover:text-white">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('inventory.products.store') }}" class="p-6 space-y-4">
                    @csrf

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">Product Name</label>
                        <input name="name" value="{{ old('name') }}"
                            class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                            required>
                        @error('name')
                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">Brand Name</label>
                        <input name="brand" value="{{ old('brand') }}"
                            class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                            placeholder="Optional">
                        @error('brand')
                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">Unit
                                Value</label>
                            <input type="number" name="unit_value" min="0" value="{{ old('unit_value', 0) }}"
                                class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                            @error('unit_value')
                                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">Unit</label>
                            <select name="unit"
                                class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                                @foreach (['ml', 'L', 'g', 'kg', 'pcs'] as $unitOption)
                                    <option value="{{ $unitOption }}" @selected(old('unit', 'ml') === $unitOption)>{{ $unitOption }}
                                    </option>
                                @endforeach
                            </select>
                            @error('unit')
                                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">Stock
                                Quantity</label>
                            <input type="number" name="stock_quantity" min="0"
                                value="{{ old('stock_quantity', 0) }}"
                                class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                required>
                            @error('stock_quantity')
                                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">Expiration
                                Date</label>
                            <input type="date" name="expiration_date" value="{{ old('expiration_date') }}"
                                class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                            @error('expiration_date')
                                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" @click="addOpen = false"
                            class="px-4 py-2 text-sm font-medium bg-white border rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200">
                            Cancel
                        </button>
                        <button class="px-4 py-2 text-sm font-medium text-white rounded-lg bg-[#8B7355] hover:opacity-90">
                            Save Product
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Product Modal -->
        <div x-show="editOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto"
        style="display:none;">
        <!-- backdrop -->
        <div class="absolute inset-0 bg-black/50" @click="editOpen = false"></div>

        <div x-transition
            class="relative w-full max-w-lg my-8 bg-white border shadow-lg rounded-xl dark:bg-gray-800 dark:border-gray-700 max-h-[85vh] overflow-y-auto">
                <div class="flex items-center justify-between px-6 py-4 border-b dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Edit Product</h3>
                    <button type="button" @click="editOpen = false"
                        class="text-gray-500 hover:text-gray-800 dark:hover:text-white">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-6 space-y-6">
                    <!-- UPDATE FORM -->
                    <form method="POST"
                        id="updateProductForm"
                        :action="`{{ url('/inventory/products') }}/${edit.id}`"
                        class="space-y-4">

                        @csrf
                        @method('PUT')

                        <!-- Product Name -->
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                Product Name
                            </label>

                            <input name="name"
                                x-model="edit.name"
                                class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                required>
                        </div>

                        <!-- Brand Name -->
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                Brand Name
                            </label>

                            <input name="brand"
                                x-model="edit.brand"
                                class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                placeholder="Optional">
                        </div>

                        <!-- Stock + Unit Value -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Stock Quantity
                                </label>

                                <input type="number"
                                    name="stock_quantity"
                                    min="0"
                                    x-model="edit.stock_quantity"
                                    class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                    required>
                            </div>

                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Unit Value
                                </label>

                                <input type="number"
                                    name="unit_value"
                                    min="0"
                                    x-model="edit.unit_value"
                                    class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                    placeholder="30">
                            </div>
                        </div>

                        <!-- Unit -->
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                Unit
                            </label>

                            <select name="unit"
                                x-model="edit.unit"
                                class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                                @foreach (['ml', 'L', 'g', 'kg', 'pcs'] as $unitOption)
                                    <option value="{{ $unitOption }}">{{ $unitOption }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Expiration Date -->
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                Expiration Date
                            </label>

                            <input type="date"
                                name="expiration_date"
                                x-model="edit.expiration_date"
                                class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                        </div>

                    </form>

                    <!-- DEDUCT STOCK -->
                    <div class="pt-5 mt-5 border-t dark:border-gray-700">

                        <h4 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Deduct Stock
                        </h4>

                        <form method="POST"
                            :action="`{{ url('/inventory/products') }}/${edit.id}/deduct`"
                            class="space-y-4">

                            @csrf

                            <input type="hidden"
                                name="product_id"
                                :value="edit.id">

                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Quantity to Deduct
                                </label>

                                <div class="flex items-center gap-2">
                                    <input type="number"
                                        name="amount"
                                        min="1"
                                        value="{{ old('amount') }}"
                                        class="flex-1 px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                        placeholder="Enter quantity"
                                        required>

                                    <button type="submit"
                                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:opacity-90 whitespace-nowrap">
                                        Deduct Stock
                                    </button>
                                </div>

                                @error('amount', 'deductStock')
                                    <p class="mt-1 text-xs text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                        </form>
                    </div>

                    <!-- MODAL ACTIONS -->
                    <div class="flex items-center justify-end gap-2 pt-2">

                        <button type="button"
                            @click="editOpen = false"
                            class="px-4 py-2 text-sm font-medium bg-white border rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200">
                            Close
                        </button>

                        <button type="button"
                            form="updateProductForm"
                            @click="$el.closest('.p-6').querySelector('form[action*=\'/inventory/products/\']').submit()"
                            class="px-4 py-2 text-sm font-medium text-white rounded-lg bg-[#8B7355] hover:opacity-90">
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- CSV Import Help Modal -->
        <div x-show="importHelpOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="display:none;">

            <div class="absolute inset-0 bg-black/50" @click="importHelpOpen = false"></div>

            <div x-transition
                class="relative w-full max-w-lg bg-white border shadow-lg rounded-xl dark:bg-gray-800 dark:border-gray-700">

                <div class="flex items-center justify-between px-6 py-4 border-b dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white">CSV Import Format</h3>
                    <button type="button" @click="importHelpOpen = false"
                        class="text-gray-500 hover:text-gray-800 dark:hover:text-white">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Your CSV file must include the following columns, in this order:
                    </p>

                    <div class="p-3 overflow-x-auto text-xs rounded-lg bg-gray-50 dark:bg-gray-900">
                        <code class="text-gray-800 dark:text-gray-200 whitespace-nowrap">
                            name, brand, stock_quantity, unit_value, unit, expiration_date
                        </code>
                    </div>

                    <ul class="space-y-1 text-sm text-gray-600 list-disc list-inside dark:text-gray-300">
                        <li><span class="font-medium text-gray-800 dark:text-white">name</span> — required</li>
                        <li><span class="font-medium text-gray-800 dark:text-white">brand</span> — optional, leave blank if
                            none</li>
                        <li><span class="font-medium text-gray-800 dark:text-white">stock_quantity</span> — required, whole
                            number</li>
                        <li><span class="font-medium text-gray-800 dark:text-white">unit_value</span> — number (e.g. 30,
                            500)</li>
                        <li><span class="font-medium text-gray-800 dark:text-white">unit</span> — one of: ml, L, g, kg, pcs
                        </li>
                        <li><span class="font-medium text-gray-800 dark:text-white">expiration_date</span> — format
                            YYYY-MM-DD, optional</li>
                    </ul>

                    <div class="pt-2">
                        <p class="mb-2 text-sm text-gray-600 dark:text-gray-300">Example row:</p>
                        <div class="p-3 overflow-x-auto text-xs rounded-lg bg-gray-50 dark:bg-gray-900">
                            <code class="text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                Aloe Vera Gel, Nature's Best, 24, 250, ml, 2027-03-15
                            </code>
                        </div>
                    </div>

                    <div class="pt-2">
                        <p class="mb-2 text-sm text-gray-600 dark:text-gray-300">How it should look in Excel/Sheets:</p>
                        <a href="{{ asset('images/excelsample.png') }}" target="_blank" rel="noopener" class="block group">
                            <img src="{{ asset('images/excelsample.png') }}"
                                alt="Example of the products CSV file opened in a spreadsheet, showing the name, brand, stock_quantity, unit_value, unit, and expiration_date columns"
                                class="w-full transition-opacity border border-gray-200 rounded-lg cursor-zoom-in dark:border-gray-700 group-hover:opacity-90">
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Click to view full size</p>
                        </a>
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t dark:border-gray-700">
                        <a href="{{ route('inventory.products.sample-csv') }}"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-lg bg-[#8B7355] hover:opacity-90">
                            <i class="fa-solid fa-download"></i>
                            Download Sample CSV
                        </a>
                        <button type="button" @click="importHelpOpen = false"
                            class="px-4 py-2 text-sm font-medium bg-white border rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
