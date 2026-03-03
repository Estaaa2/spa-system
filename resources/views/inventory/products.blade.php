@extends('layouts.app')

@section('content')
<div class="p-6"
     x-data="{
    addOpen: false,
    editOpen: false,
    deleteOpen: false,
    edit: { id: null, name: '', brand: '', stock_quantity: 0, unit: 0, expiration_date: '' },
    deleteProduct: { id: null, name: '' },

    openEdit(p) {
        this.edit = {
            id: p.id,
            name: p.name ?? '',
            brand: p.brand ?? '',
            stock_quantity: p.stock_quantity ?? 0,
            unit: p.unit ?? 0,
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
}">

    <x-page-header
        title="Inventory Products"
        subtitle="Manage inventory products."
    />


    <div class="bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                Inventory List
            </h2>

            <button type="button"
                @click="addOpen = true"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-lg bg-[#8B7355] hover:opacity-90">
                <i class="fa-solid fa-plus"></i>
                Add Product
            </button>
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

                                    @if($product->stock_quantity <= 5)
                                        <span class="px-2 py-1 text-xs font-semibold text-white bg-red-500 rounded-full">
                                            Low
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-3 text-gray-700 dark:text-gray-200">{{ $product->unit ?? 0}}ml</td>
                            <td class="px-6 py-3 text-gray-700 dark:text-gray-200">
                                {{ $product->expiration_date?->format('M d, Y') ?? '—' }}
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-2">
                                    <form method="POST"
                                        action="{{ route('inventory.products.deduct', $product) }}"
                                        class="inline-block">
                                        @csrf
                                        <button type="button"
                                            @click="openEdit({
                                                id: {{ $product->id }},
                                                name: @js($product->name),
                                                brand: @js($product->brand),
                                                stock_quantity: {{ (int) $product->stock_quantity }},
                                                unit: @js($product->unit ?? 'ml'),
                                                expiration_date: @js(optional($product->expiration_date)->format('Y-m-d'))
                                            })"
                                            class="px-3 py-1 text-sm text-white bg-yellow-500 rounded hover:bg-yellow-600 whitespace-nowrap">
                                            Edit
                                        </button>
                                    </form>

                                    <button type="button"
                                        @click="openDelete({
                                            id: {{ $product->id }},
                                            name: @js($product->name)
                                        })"
                                        class="px-3 py-1 text-sm text-white bg-red-600 rounded hover:bg-red-700 whitespace-nowrap">
                                        Delete
                                    </button>
                                </div>

                                @error('amount')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-gray-500">
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
    <div x-show="deleteOpen"
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="display:none;">

        <!-- backdrop -->
        <div class="absolute inset-0 bg-black/50"
            @click="deleteOpen = false"></div>

        <div x-transition
            class="relative w-full max-w-md bg-white border shadow-lg rounded-xl dark:bg-gray-800 dark:border-gray-700">

            <div class="px-6 py-4 border-b dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
                    Delete Product
                </h3>
            </div>

            <div class="p-6 space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Are you sure you want to delete
                    <span class="font-semibold text-red-600" x-text="deleteProduct.name"></span>?
                </p>

                <div class="flex justify-end gap-2">
                    <button type="button"
                            @click="deleteOpen = false"
                            class="px-4 py-2 text-sm font-medium bg-white border rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200">
                        Cancel
                    </button>

                    <form method="POST"
                        :action="`{{ url('/inventory/products') }}/${deleteProduct.id}`">
                        @csrf
                        @method('DELETE')

                        <button class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">
                            Yes, Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div x-show="addOpen"
         x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="display:none;">
        <!-- backdrop -->
        <div class="absolute inset-0 bg-black/50"
             @click="addOpen = false"></div>

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
                    @error('name') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">Brand Name</label>
                    <input name="brand" value="{{ old('brand') }}"
                           class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                           placeholder="Optional">
                    @error('brand') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">Stock Quantity</label>
                        <input type="number" name="stock_quantity" min="0" value="{{ old('stock_quantity', 0) }}"
                               class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                               required>
                        @error('stock_quantity') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">Expiration Date</label>
                        <input type="date" name="expiration_date" value="{{ old('expiration_date') }}"
                               class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                        @error('expiration_date') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
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
    <div x-show="editOpen"
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="display:none;">
        <!-- backdrop -->
        <div class="absolute inset-0 bg-black/50"
            @click="editOpen = false"></div>

        <div x-transition
            class="relative w-full max-w-lg bg-white border shadow-lg rounded-xl dark:bg-gray-800 dark:border-gray-700">
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
                    :action="`{{ url('/inventory/products') }}/${edit.id}`"
                    class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">Product Name</label>
                        <input name="name" x-model="edit.name"
                            class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                            required>
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">Brand Name</label>
                        <input name="brand" x-model="edit.brand"
                            class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                            placeholder="Optional">
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">Stock Quantity</label>
                            <input type="number" name="stock_quantity" min="0" x-model="edit.stock_quantity"
                                class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                required>
                        </div>

                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">Unit</label>
                            <input type="number" name="unit" min="0" x-model="edit.unit"
                            class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                            placeholder="30">
                        </div>
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">Expiration Date</label>
                        <input type="date" name="expiration_date" x-model="edit.expiration_date"
                            class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="editOpen = false"
                                class="px-4 py-2 text-sm font-medium bg-white border rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200">
                            Close
                        </button>
                        <button class="px-4 py-2 text-sm font-medium text-white rounded-lg bg-[#8B7355] hover:opacity-90">
                            Save Changes
                        </button>
                    </div>
                </form>

                <!-- DEDUCT FORM (logs + reduces stock) -->
                <div class="pt-4 border-t dark:border-gray-700">
                    <h4 class="mb-3 text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                        Deduct Stock
                    </h4>

                    <form method="POST"
                        :action="`{{ url('/inventory/products') }}/${edit.id}/deduct`"
                        class="flex flex-wrap items-center gap-2">
                        @csrf

                        <input type="number" name="amount" min="1"
                            class="px-3 py-2 text-sm border rounded-lg w-28"
                            placeholder="Qty" required>

                        <button class="px-3 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:opacity-90">
                            Deduct
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
