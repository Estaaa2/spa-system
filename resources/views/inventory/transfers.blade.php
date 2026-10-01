@extends('layouts.app')

@section('title', 'Stock Transfers')

@section('content')
@php
    $user = auth()->user();
    $sourceBranch = $branches->firstWhere('id', $branchId);

    $canCreateTransfer = $user?->hasBranchPermission('create stock transfers') ?? false;
    $canProcessTransfer = $user?->hasBranchPermission('process stock transfers') ?? false;
    $canCancelTransfer = $user?->hasBranchPermission('cancel stock transfers') ?? false;

    $transferValidationFailed = $errors->stockTransfer->any();

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
        transferOpen: {{ $transferValidationFailed ? 'true' : 'false' }},
        processOpen: false,
        cancelOpen: false,

        processTransfer: {
            id: null,
            product: '',
            quantity: '',
            unit: '',
            destination: ''
        },

        cancelTransfer: {
            id: null,
            product: '',
            quantity: '',
            unit: '',
            destination: ''
        },

        openProcess(transfer) {
            this.processTransfer = transfer;
            this.processOpen = true;
        },

        closeProcess() {
            this.processOpen = false;
        },

        openCancel(transfer) {
            this.cancelTransfer = transfer;
            this.cancelOpen = true;
        },

        closeCancel() {
            this.cancelOpen = false;
        }
    }">

    <x-page-header
        title="Stock Transfers"
        subtitle="Transfer inventory safely between branches while preserving FEFO batch history."
    />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Current Branch
            </p>

            <h3 class="mt-3 text-lg font-semibold text-gray-900 dark:text-white">
                {{ $sourceBranch?->name ?? 'No Branch Selected' }}
            </h3>

            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Stock will be transferred from this branch.
            </p>
        </div>

        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Pending
            </p>

            <h3 class="mt-3 text-2xl font-semibold text-gray-900 dark:text-white">
                {{ $transfers->getCollection()->where('status', 'pending')->count() }}
            </h3>

            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Transfers waiting to be processed.
            </p>
        </div>

        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Completed
            </p>

            <h3 class="mt-3 text-2xl font-semibold text-gray-900 dark:text-white">
                {{ $transfers->getCollection()->where('status', 'completed')->count() }}
            </h3>

            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Completed transfers on this page.
            </p>
        </div>
    </div>

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">

        <div class="flex flex-col gap-4 px-4 py-4 border-b border-gray-200 sm:px-6 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">

            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    Transfer History
                </h2>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Review pending and completed inventory transfers.
                </p>
            </div>

            @if($canCreateTransfer)
                <button type="button"
                    @click="transferOpen = true"
                    class="{{ $btn['primary'] }}">

                    <i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>
                    New Transfer
                </button>
            @endif
        </div>

        <form method="GET"
            action="{{ route('inventory.transfers') }}"
            class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">

            <div class="flex flex-col gap-4 sm:flex-row sm:items-end">

                <div class="flex-1">
                    <label for="transfer_status"
                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Status
                    </label>

                    <select id="transfer_status"
                        name="status"
                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                        <option value="">All Statuses</option>

                        <option value="pending"
                            @selected(request('status') === 'pending')>
                            Pending
                        </option>

                        <option value="completed"
                            @selected(request('status') === 'completed')>
                            Completed
                        </option>

                        <option value="cancelled"
                            @selected(request('status') === 'cancelled')>
                            Cancelled
                        </option>
                    </select>
                </div>

                <div class="flex-1">
                    <label for="transfer_product_filter"
                        class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Product
                    </label>

                    <select id="transfer_product_filter"
                        name="product_id"
                        class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                        <option value="">All Products</option>

                        @foreach($products as $product)
                            <option value="{{ $product->id }}"
                                @selected((string) request('product_id') === (string) $product->id)>
                                {{ $product->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit"
                        class="{{ $btn['primary'] }}">
                        <i class="fa-solid fa-filter" aria-hidden="true"></i>
                        Apply
                    </button>

                    <a href="{{ route('inventory.transfers') }}"
                        class="{{ $btn['secondary'] }}">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        Clear
                    </a>
                </div>

            </div>
        </form>

        <div class="lg:overflow-x-auto">

            <table role="table"
                class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">

                <thead role="rowgroup"
                    class="bg-gray-50 dark:bg-gray-900">

                    <tr role="row">
                        <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Product
                        </th>

                        <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            From
                        </th>

                        <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            To
                        </th>

                        <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Quantity
                        </th>

                        <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Status
                        </th>

                        <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">
                            Requested
                        </th>

                        <th class="px-4 py-3 text-xs font-medium text-center text-gray-500 uppercase dark:text-gray-400">
                            Actions
                        </th>
                    </tr>

                </thead>

                <tbody role="rowgroup"
                    class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">

                    @forelse($transfers as $transfer)
                        @php
                            $statusClass = match($transfer->status) {
                                'completed' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
                                'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                                default => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                            };
                        @endphp

                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/40">

                            <td data-label="Product"
                                class="px-4 py-4">

                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $transfer->product?->name ?? 'Deleted Product' }}
                                </p>

                                @if($transfer->items->isNotEmpty())
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $transfer->items->count() }}
                                        {{ $transfer->items->count() === 1 ? 'batch' : 'batches' }}
                                    </p>
                                @endif
                            </td>

                            <td data-label="From"
                                class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ $transfer->sourceBranch?->name ?? '—' }}
                            </td>

                            <td data-label="To"
                                class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ $transfer->destinationBranch?->name ?? '—' }}
                            </td>

                            <td data-label="Quantity"
                                class="px-4 py-4 text-sm font-medium text-gray-900 dark:text-white">

                                {{ rtrim(rtrim(number_format((float) $transfer->quantity, 3, '.', ''), '0'), '.') }}
                                {{ $transfer->unit }}
                            </td>

                            <td data-label="Status"
                                class="px-4 py-4">

                                <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full {{ $statusClass }}">
                                    {{ ucfirst($transfer->status) }}
                                </span>
                            </td>

                            <td data-label="Requested"
                                class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">

                                <p>
                                    {{ $transfer->requestedBy?->name ?? 'System' }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $transfer->created_at?->format('M d, Y h:i A') }}
                                </p>
                            </td>

                            <td data-label="Actions"
                                class="px-4 py-4 text-center rt-actions">

                                @if($transfer->status === 'pending' && (int) $transfer->source_branch_id === (int) $branchId)

                                    <div class="flex flex-col justify-center gap-2 sm:flex-row">

                                        @if($canProcessTransfer)
                                            <button type="button"
                                                @click="openProcess({
                                                    id: {{ $transfer->id }},
                                                    product: @js($transfer->product?->name ?? 'Deleted Product'),
                                                    quantity: @js(rtrim(rtrim(number_format((float) $transfer->quantity, 3, '.', ''), '0'), '.')),
                                                    unit: @js($transfer->unit),
                                                    destination: @js($transfer->destinationBranch?->name ?? 'Unknown Branch')
                                                })"
                                                class="{{ $btn['primary'] }}">

                                                <i class="fa-solid fa-check" aria-hidden="true"></i>
                                                Process
                                            </button>
                                        @endif

                                        @if($canCancelTransfer)
                                            <button type="button"
                                                @click="openCancel({
                                                    id: {{ $transfer->id }},
                                                    product: @js($transfer->product?->name ?? 'Deleted Product'),
                                                    quantity: @js(rtrim(rtrim(number_format((float) $transfer->quantity, 3, '.', ''), '0'), '.')),
                                                    unit: @js($transfer->unit),
                                                    destination: @js($transfer->destinationBranch?->name ?? 'Unknown Branch')
                                                })"
                                                class="{{ $btn['danger'] }}">

                                                <i class="fa-solid fa-ban" aria-hidden="true"></i>
                                                Cancel
                                            </button>
                                        @endif

                                        @if(!$canProcessTransfer && !$canCancelTransfer)
                                            <span class="text-sm text-gray-400 dark:text-gray-500">
                                                —
                                            </span>
                                        @endif

                                    </div>

                                @elseif($transfer->status === 'completed')

                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <i class="mr-1 text-green-600 fa-solid fa-circle-check" aria-hidden="true"></i>
                                        Completed
                                    </div>

                                @elseif($transfer->status === 'cancelled')

                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <i class="mr-1 text-red-600 fa-solid fa-ban" aria-hidden="true"></i>
                                        Cancelled
                                    </div>

                                @else

                                    <span class="text-sm text-gray-400 dark:text-gray-500">
                                        —
                                    </span>

                                @endif
                            </td>
                        </tr>

                    @empty

                        <tr>
                            <td colspan="7"
                                class="px-6 py-12 text-sm text-center text-gray-500 rt-empty dark:text-gray-400">

                                <div class="flex flex-col items-center justify-center">

                                    <div class="flex items-center justify-center w-12 h-12 mb-3 text-gray-400 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-500">
                                        <i class="text-lg fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>
                                    </div>

                                    <p>No stock transfers found.</p>

                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                        Create a transfer to move stock between branches.
                                    </p>

                                </div>
                            </td>
                        </tr>

                    @endforelse

                </tbody>
            </table>
        </div>

        @if($transfers->total() > 0)
            <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
                {{ $transfers->links() }}
            </div>
        @endif
    </div>

    <template x-teleport="body">
        <div x-show="transferOpen"
            x-transition.opacity
            class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50"
            :class="{ 'hidden': !transferOpen }"
            @keydown.escape.window="transferOpen = false">

            <div class="flex items-start justify-center min-h-full p-4 sm:items-center">

                <div role="dialog"
                    aria-modal="true"
                    aria-labelledby="newTransferTitle"
                    @click.outside="transferOpen = false"
                    x-transition
                    class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">

                    <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">

                        <div>
                            <h2 id="newTransferTitle"
                                class="text-lg font-semibold text-gray-900 dark:text-white">
                                New Stock Transfer
                            </h2>

                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Transfer usable stock from the current branch to another branch.
                            </p>
                        </div>

                        <button type="button"
                            @click="transferOpen = false"
                            aria-label="Close dialog"
                            class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">

                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>

                    </div>

                    <form method="POST"
                        action="{{ route('inventory.transfers.store') }}">

                        @csrf

                        <div class="px-4 py-5 space-y-4 sm:px-6">

                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Source Branch
                                </label>

                                <div class="flex items-center w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl bg-gray-50 dark:bg-gray-900 dark:border-gray-600">

                                    <i class="mr-2 text-gray-400 fa-solid fa-location-dot" aria-hidden="true"></i>

                                    <span class="font-medium text-gray-900 dark:text-white">
                                        {{ $sourceBranch?->name ?? 'No branch selected' }}
                                    </span>
                                </div>
                            </div>

                            <div>
                                <label for="destination_branch_id"
                                    class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Destination Branch
                                </label>

                                <select id="destination_branch_id"
                                    name="destination_branch_id"
                                    required
                                    class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                    <option value="">
                                        Select destination branch
                                    </option>

                                    @foreach($branches as $branch)
                                        @if((int) $branch->id !== (int) $branchId)
                                            <option value="{{ $branch->id }}"
                                                @selected((string) old('destination_branch_id') === (string) $branch->id)>
                                                {{ $branch->name }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>

                                @error('destination_branch_id', 'stockTransfer')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div>
                                <label for="transfer_product_id"
                                    class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Product
                                </label>

                                <select id="transfer_product_id"
                                    name="product_id"
                                    required
                                    class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                    <option value="">
                                        Select product
                                    </option>

                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}"
                                            @selected((string) old('product_id') === (string) $product->id)>
                                            {{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>

                                @error('product_id', 'stockTransfer')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div>
                                <label for="transfer_quantity"
                                    class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Quantity
                                </label>

                                <input type="number"
                                    id="transfer_quantity"
                                    name="quantity"
                                    min="0.001"
                                    max="20000"
                                    step="0.001"
                                    inputmode="decimal"
                                    data-stock-limit="20000"
                                    data-stock-decimals="3"
                                    value="{{ old('quantity') }}"
                                    placeholder="Enter transfer quantity"
                                    required
                                    class="w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                @error('quantity', 'stockTransfer')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div>
                                <label for="transfer_notes"
                                    class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Notes
                                </label>

                                <textarea id="transfer_notes"
                                    name="notes"
                                    rows="3"
                                    maxlength="1000"
                                    placeholder="Optional transfer notes"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">{{ old('notes') }}</textarea>

                                @error('notes', 'stockTransfer')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div class="p-4 border border-blue-200 rounded-xl bg-blue-50 dark:border-blue-900/50 dark:bg-blue-900/10">

                                <div class="flex items-start gap-3">

                                    <i class="mt-0.5 text-blue-600 fa-solid fa-circle-info dark:text-blue-400" aria-hidden="true"></i>

                                    <div>
                                        <p class="text-sm font-medium text-blue-800 dark:text-blue-300">
                                            FEFO will be applied when this transfer is processed.
                                        </p>

                                        <p class="mt-1 text-xs text-blue-700 dark:text-blue-400">
                                            The earliest-expiring usable batches will be transferred first.
                                        </p>
                                    </div>

                                </div>
                            </div>

                        </div>

                        <div class="flex flex-col-reverse gap-2 px-4 py-4 border-t border-gray-200 sm:flex-row sm:justify-end sm:px-6 dark:border-gray-700">

                            <button type="button"
                                @click="transferOpen = false"
                                class="w-full {{ $btn['secondary'] }} sm:w-auto">
                                Cancel
                            </button>

                            <button type="submit"
                                class="w-full {{ $btn['primary'] }} sm:w-auto">

                                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                Create Transfer
                            </button>

                        </div>

                    </form>

                </div>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="processOpen"
            x-transition.opacity
            class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50"
            :class="{ 'hidden': !processOpen }"
            @keydown.escape.window="closeProcess()">

            <div class="flex items-start justify-center min-h-full p-4 sm:items-center">

                <div role="dialog"
                    aria-modal="true"
                    aria-labelledby="processTransferTitle"
                    @click.outside="closeProcess()"
                    x-transition
                    class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">

                    <div class="px-4 py-5 sm:px-6">

                        <div class="flex items-center justify-center w-12 h-12 mx-auto text-[#8B7355] bg-[#F8F5F1] rounded-full dark:bg-gray-700">
                            <i class="text-lg fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>
                        </div>

                        <h2 id="processTransferTitle"
                            class="mt-4 text-lg font-semibold text-center text-gray-900 dark:text-white">
                            Process Stock Transfer
                        </h2>

                        <p class="mt-2 text-sm text-center text-gray-500 dark:text-gray-400">
                            This will move real inventory from the current branch to
                            <span class="font-medium text-gray-700 dark:text-gray-200"
                                x-text="processTransfer.destination"></span>.
                        </p>

                        <div class="p-4 mt-5 rounded-xl bg-gray-50 dark:bg-gray-900/40">

                            <p class="text-sm font-semibold text-gray-900 dark:text-white"
                                x-text="processTransfer.product"></p>

                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Quantity:
                                <span class="font-medium text-gray-700 dark:text-gray-200"
                                    x-text="`${processTransfer.quantity} ${processTransfer.unit}`"></span>
                            </p>
                        </div>

                        <div class="p-4 mt-4 border border-amber-200 rounded-xl bg-amber-50 dark:border-amber-900/50 dark:bg-amber-900/10">

                            <p class="text-sm text-amber-800 dark:text-amber-300">
                                FEFO batches will be deducted from the source and recreated in the destination branch. This operation is recorded permanently.
                            </p>
                        </div>

                    </div>

                    <div class="flex flex-col-reverse gap-2 px-4 py-4 border-t border-gray-200 sm:flex-row sm:justify-end sm:px-6 dark:border-gray-700">

                        <button type="button"
                            @click="closeProcess()"
                            class="w-full {{ $btn['secondary'] }} sm:w-auto">
                            Cancel
                        </button>

                        <form method="POST"
                            :action="`{{ url('/inventory/transfers') }}/${processTransfer.id}/process`"
                            class="w-full sm:w-auto">

                            @csrf

                            <button type="submit"
                                class="w-full {{ $btn['primary'] }}">
                                <i class="fa-solid fa-check" aria-hidden="true"></i>
                                Process Transfer
                            </button>
                        </form>

                    </div>

                </div>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="cancelOpen"
            x-transition.opacity
            class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50"
            :class="{ 'hidden': !cancelOpen }"
            @keydown.escape.window="closeCancel()">

            <div class="flex items-start justify-center min-h-full p-4 sm:items-center">

                <div role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="cancelTransferTitle"
                    aria-describedby="cancelTransferDescription"
                    @click.outside="closeCancel()"
                    x-transition
                    class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">

                    <div class="px-4 py-5 sm:px-6">

                        <div class="flex items-center justify-center w-12 h-12 mx-auto text-red-700 bg-red-100 rounded-full dark:bg-red-900/30 dark:text-red-300">
                            <i class="text-lg fa-solid fa-ban" aria-hidden="true"></i>
                        </div>

                        <h2 id="cancelTransferTitle"
                            class="mt-4 text-lg font-semibold text-center text-gray-900 dark:text-white">
                            Cancel Stock Transfer
                        </h2>

                        <p id="cancelTransferDescription"
                            class="mt-2 text-sm text-center text-gray-500 dark:text-gray-400">
                            This pending transfer will be cancelled. No inventory has been moved yet.
                        </p>

                        <div class="p-4 mt-5 rounded-xl bg-gray-50 dark:bg-gray-900/40">

                            <p class="text-sm font-semibold text-gray-900 dark:text-white"
                                x-text="cancelTransfer.product"></p>

                            <div class="mt-2 space-y-1 text-sm text-gray-500 dark:text-gray-400">

                                <p>
                                    Quantity:
                                    <span class="font-medium text-gray-700 dark:text-gray-200"
                                        x-text="`${cancelTransfer.quantity} ${cancelTransfer.unit}`"></span>
                                </p>

                                <p>
                                    Destination:
                                    <span class="font-medium text-gray-700 dark:text-gray-200"
                                        x-text="cancelTransfer.destination"></span>
                                </p>

                            </div>
                        </div>

                        <div class="p-4 mt-4 border border-red-200 rounded-xl bg-red-50 dark:border-red-900/50 dark:bg-red-900/10">

                            <div class="flex items-start gap-3">

                                <i class="mt-0.5 text-red-600 fa-solid fa-triangle-exclamation dark:text-red-400"
                                    aria-hidden="true"></i>

                                <div>
                                    <p class="text-sm font-medium text-red-800 dark:text-red-300">
                                        This action only cancels pending transfers.
                                    </p>

                                    <p class="mt-1 text-xs text-red-700 dark:text-red-400">
                                        Completed transfers cannot be cancelled directly because inventory has already moved.
                                    </p>
                                </div>

                            </div>
                        </div>

                    </div>

                    <div class="flex flex-col-reverse gap-2 px-4 py-4 border-t border-gray-200 sm:flex-row sm:justify-end sm:px-6 dark:border-gray-700">

                        <button type="button"
                            @click="closeCancel()"
                            class="w-full {{ $btn['secondary'] }} sm:w-auto">
                            Keep Transfer
                        </button>

                        <form method="POST"
                            :action="`{{ url('/inventory/transfers') }}/${cancelTransfer.id}/cancel`"
                            class="w-full sm:w-auto">

                            @csrf

                            <button type="submit"
                                class="w-full {{ $btn['danger'] }}">
                                <i class="fa-solid fa-ban" aria-hidden="true"></i>
                                Cancel Transfer
                            </button>

                        </form>

                    </div>

                </div>
            </div>
        </div>
    </template>

</div>

<style>
@media (max-width: 1023px) {
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
        width: 38%;
        text-align: left;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .rt td > * {
        min-width: 0;
        max-width: 62%;
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

    .rt td.rt-actions > * {
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

@media (max-width: 1023px) and (prefers-color-scheme: dark) {
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
