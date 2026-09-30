<?php

namespace App\Http\Controllers;

use App\Models\BranchProductStock;
use App\Models\Branch;
use Carbon\Carbon;
use App\Models\Product;
use App\Models\ProductLog;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function products(Request $request)
    {
        $user = $request->user();
        $spaId = $user->spa_id;
        $branchId = $user->currentBranchId();

        if (!$branchId) {
            return back()->with('error', 'Please select a branch first.');
        }

        $products = Product::where('spa_id', $spaId)
            ->whereHas('branchStocks', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->with([
                'branchStocks' => function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId);
                },
                'batches' => function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId)
                        ->where('remaining_quantity', '>', 0)
                        ->orderByRaw('expiration_date IS NULL')
                        ->orderBy('expiration_date')
                        ->orderBy('received_at');
                },
            ])
            ->orderBy('name')
            ->paginate(5)
            ->withQueryString();

        $products->getCollection()->transform(function ($product) {
            $product->branch_stock = $product->branchStocks->first();
            $product->nearest_expiration = $product->batches->first()?->expiration_date;

            return $product;
        });

        return view('inventory.products', compact('products'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $spaId = $user->spa_id;
        $branchId = $user->currentBranchId();

        if (!$branchId) {
            return back()->with('error', 'Please select a branch first.');
        }

        $data = $request->validate([
            'sku' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'sku')
                    ->where(fn ($query) => $query->where('spa_id', $spaId)),
            ],
            'barcode' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'inventory_type' => ['required', Rule::in(['retail', 'backbar', 'both'])],
            'purchase_unit' => ['nullable', 'string', 'max:30'],
            'usage_unit' => [
                'required',
                Rule::in(['ml', 'L', 'g', 'kg', 'pcs']),
            ],
            'conversion_factor' => ['required', 'numeric', 'gt:0'],
            'retail_price' => ['nullable', 'numeric', 'min:0'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'opening_stock' => ['required', 'numeric', 'min:0'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'maximum_stock' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $spaId, $branchId, $user) {
            $product = Product::create([
                'spa_id' => $spaId,
                'sku' => $data['sku'] ?? null,
                'barcode' => $data['barcode'] ?? null,
                'name' => $data['name'],
                'brand' => $data['brand'] ?? null,
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? null,
                'inventory_type' => $data['inventory_type'],
                'stock_quantity' => 0,
                'unit_value' => 0,
                'unit' => $data['usage_unit'],
                'purchase_unit' => $data['purchase_unit'] ?? null,
                'usage_unit' => $data['usage_unit'],
                'conversion_factor' => $data['conversion_factor'],
                'retail_price' => $data['retail_price'] ?? null,
                'acquisition_cost' => $data['acquisition_cost'] ?? null,
                'expiration_date' => null,
                'is_active' => true,
            ]);

            $stock = BranchProductStock::create([
                'spa_id' => $spaId,
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'on_hand_quantity' => $data['opening_stock'],
                'reorder_level' => $data['reorder_level'],
                'minimum_stock' => $data['minimum_stock'] ?? null,
                'maximum_stock' => $data['maximum_stock'] ?? null,
            ]);

            if ((float) $data['opening_stock'] > 0) {
                StockMovement::create([
                    'spa_id' => $spaId,
                    'branch_id' => $branchId,
                    'product_id' => $product->id,
                    'product_batch_id' => null,
                    'user_id' => $user->id,
                    'booking_id' => null,
                    'movement_type' => 'opening_balance',
                    'direction' => 'in',
                    'quantity' => $data['opening_stock'],
                    'unit' => $data['usage_unit'],
                    'balance_before' => 0,
                    'balance_after' => $stock->on_hand_quantity,
                    'reference_type' => 'product_creation',
                    'reference_id' => $product->id,
                    'notes' => 'Opening inventory balance.',
                    'occurred_at' => now(),
                ]);
            }

            ProductLog::create([
                'spa_id' => $spaId,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'description' => "{$product->name} was added to the current branch inventory",
                'logged_at' => now(),
            ]);
        });

        return back()->with('success', 'Product added successfully.');
    }

    public function update(Request $request, Product $product)
    {
        $user = $request->user();
        $spaId = $user->spa_id;
        $branchId = $user->currentBranchId();

        abort_unless($product->spa_id === $spaId, 403);

        if (!$branchId) {
            return back()->with('error', 'Please select a branch first.');
        }

        $stock = BranchProductStock::where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $data = $request->validate([
            'sku' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'sku')
                    ->ignore($product->id)
                    ->where(fn ($query) => $query->where('spa_id', $spaId)),
            ],
            'barcode' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'inventory_type' => ['required', Rule::in(['retail', 'backbar', 'both'])],
            'purchase_unit' => ['nullable', 'string', 'max:30'],
            'usage_unit' => [
                'required',
                Rule::in(['ml', 'L', 'g', 'kg', 'pcs']),
            ],
            'conversion_factor' => ['required', 'numeric', 'gt:0'],
            'retail_price' => ['nullable', 'numeric', 'min:0'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'maximum_stock' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $product, $stock, $spaId, $user) {
            $product->update([
                'sku' => $data['sku'] ?? null,
                'barcode' => $data['barcode'] ?? null,
                'name' => $data['name'],
                'brand' => $data['brand'] ?? null,
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? null,
                'inventory_type' => $data['inventory_type'],
                'purchase_unit' => $data['purchase_unit'] ?? null,
                'usage_unit' => $data['usage_unit'],
                'unit' => $data['usage_unit'],
                'conversion_factor' => $data['conversion_factor'],
                'retail_price' => $data['retail_price'] ?? null,
                'acquisition_cost' => $data['acquisition_cost'] ?? null,
            ]);

            $stock->update([
                'reorder_level' => $data['reorder_level'],
                'minimum_stock' => $data['minimum_stock'] ?? null,
                'maximum_stock' => $data['maximum_stock'] ?? null,
            ]);

            ProductLog::create([
                'spa_id' => $spaId,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'description' => "{$product->name} product information was updated",
                'logged_at' => now(),
            ]);
        });

        return back()->with('success', 'Product updated successfully.');
    }

    public function adjustStock(Request $request, Product $product)
    {
        $user = $request->user();
        $spaId = $user->spa_id;
        $branchId = $user->currentBranchId();

        abort_unless($product->spa_id === $spaId, 403);

        if (!$branchId) {
            return back()->with('error', 'Please select a branch first.');
        }

        $data = $request->validateWithBag('adjustStock', [
            'adjustment_type' => ['required', Rule::in(['increase', 'decrease'])],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($data, $product, $spaId, $branchId, $user) {
            $stock = BranchProductStock::where('spa_id', $spaId)
                ->where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = (float) $stock->on_hand_quantity;
            $quantity = (float) $data['quantity'];

            if ($data['adjustment_type'] === 'decrease' && $before < $quantity) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'quantity' => 'The adjustment exceeds the available stock.',
                ])->errorBag('adjustStock');
            }

            $after = $data['adjustment_type'] === 'increase'
                ? $before + $quantity
                : $before - $quantity;

            $stock->update([
                'on_hand_quantity' => $after,
            ]);

            StockMovement::create([
                'spa_id' => $spaId,
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'product_batch_id' => null,
                'user_id' => $user->id,
                'booking_id' => null,
                'movement_type' => $data['adjustment_type'] === 'increase'
                    ? 'adjustment_in'
                    : 'adjustment_out',
                'direction' => $data['adjustment_type'] === 'increase' ? 'in' : 'out',
                'quantity' => $quantity,
                'unit' => $product->usage_unit ?: $product->unit ?: 'pcs',
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => 'manual_adjustment',
                'reference_id' => null,
                'notes' => $data['reason'],
                'occurred_at' => now(),
            ]);

            ProductLog::create([
                'spa_id' => $spaId,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'description' => "{$product->name} stock adjusted from {$before} to {$after}",
                'logged_at' => now(),
            ]);
        });

        return back()->with('success', 'Stock adjusted successfully.');
    }

    public function receiveStock(Request $request, Product $product)
    {
        $user = $request->user();
        $spaId = $user->spa_id;
        $branchId = $user->currentBranchId();

        abort_unless($product->spa_id === $spaId, 403);

        if (!$branchId) {
            return back()->with('error', 'Please select a branch first.');
        }

        $data = $request->validateWithBag('receiveStock', [
            'batch_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_batches', 'batch_number')
                    ->where(fn ($query) => $query
                        ->where('spa_id', $spaId)
                        ->where('branch_id', $branchId)
                        ->where('product_id', $product->id)),
            ],
            'received_quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'manufactured_at' => ['nullable', 'date'],
            'expiration_date' => [
                'nullable',
                'date',
                'after_or_equal:manufactured_at',
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($data, $product, $spaId, $branchId, $user) {
            $stock = BranchProductStock::where('spa_id', $spaId)
                ->where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = (float) $stock->on_hand_quantity;
            $quantity = (float) $data['received_quantity'];
            $after = $before + $quantity;

            $batch = ProductBatch::create([
                'spa_id' => $spaId,
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'batch_number' => $data['batch_number'],
                'received_quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'unit_cost' => $data['unit_cost'] ?? null,
                'manufactured_at' => $data['manufactured_at'] ?? null,
                'expiration_date' => $data['expiration_date'] ?? null,
                'received_at' => now(),
            ]);

            $stock->update([
                'on_hand_quantity' => $after,
            ]);

            StockMovement::create([
                'spa_id' => $spaId,
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'product_batch_id' => $batch->id,
                'user_id' => $user->id,
                'booking_id' => null,
                'movement_type' => 'purchase_receipt',
                'direction' => 'in',
                'quantity' => $quantity,
                'unit' => $product->usage_unit ?: $product->unit ?: 'pcs',
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => 'product_batch',
                'reference_id' => $batch->id,
                'notes' => $data['notes'] ?: 'Stock received into inventory.',
                'occurred_at' => now(),
            ]);

            ProductLog::create([
                'spa_id' => $spaId,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'description' => "{$product->name} received {$quantity} {$product->usage_unit}",
                'logged_at' => now(),
            ]);
        });

        return back()->with('success', 'Stock received successfully.');
    }

    public function batches(Request $request)
    {
        $user = $request->user();
        $spaId = $user->spa_id;
        $branchId = $user->currentBranchId();

        if (!$spaId) {
            return back()->with('error', 'No spa associated with your account.');
        }

        if (!$branchId) {
            return back()->with('error', 'Please select a branch first.');
        }

        $query = ProductBatch::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->with('product');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($query) use ($search) {
                $query->where('batch_number', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $today = now()->toDateString();
            $expiringSoon = now()->addDays(30)->toDateString();

            if ($request->status === 'active') {
                $query->where('remaining_quantity', '>', 0)
                    ->where(function ($query) use ($expiringSoon) {
                        $query->whereNull('expiration_date')
                            ->orWhere('expiration_date', '>', $expiringSoon);
                    });
            }

            if ($request->status === 'expiring') {
                $query->where('remaining_quantity', '>', 0)
                    ->whereNotNull('expiration_date')
                    ->whereBetween('expiration_date', [$today, $expiringSoon]);
            }

            if ($request->status === 'expired') {
                $query->where('remaining_quantity', '>', 0)
                    ->whereNotNull('expiration_date')
                    ->where('expiration_date', '<', $today);
            }

            if ($request->status === 'depleted') {
                $query->where('remaining_quantity', '<=', 0);
            }
        }

        $batches = $query
            ->orderByRaw('remaining_quantity <= 0')
            ->orderByRaw('expiration_date IS NULL')
            ->orderBy('expiration_date')
            ->orderByDesc('received_at')
            ->paginate(5)
            ->withQueryString();

        $products = Product::query()
            ->where('spa_id', $spaId)
            ->whereHas('branchStocks', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $baseBatchQuery = ProductBatch::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId);

        $today = now()->toDateString();
        $expiringSoon = now()->addDays(30)->toDateString();

        $summary = [
            'active' => (clone $baseBatchQuery)
                ->where('remaining_quantity', '>', 0)
                ->where(function ($query) use ($expiringSoon) {
                    $query->whereNull('expiration_date')
                        ->orWhere('expiration_date', '>', $expiringSoon);
                })
                ->count(),

            'expiring' => (clone $baseBatchQuery)
                ->where('remaining_quantity', '>', 0)
                ->whereNotNull('expiration_date')
                ->whereBetween('expiration_date', [$today, $expiringSoon])
                ->count(),

            'expired' => (clone $baseBatchQuery)
                ->where('remaining_quantity', '>', 0)
                ->whereNotNull('expiration_date')
                ->where('expiration_date', '<', $today)
                ->count(),

            'depleted' => (clone $baseBatchQuery)
                ->where('remaining_quantity', '<=', 0)
                ->count(),
        ];

        return view('inventory.batches', compact(
            'batches',
            'products',
            'summary'
        ));
    }

    public function recordBatchLoss(Request $request, ProductBatch $batch)
    {
        $user = $request->user();
        $spaId = $user->spa_id;
        $branchId = $user->currentBranchId();

        if (!$spaId) {
            return back()->with('error', 'No spa associated with your account.');
        }

        if (!$branchId) {
            return back()->with('error', 'Please select a branch first.');
        }

        abort_unless(
            $batch->spa_id === $spaId &&
            $batch->branch_id === $branchId,
            403
        );

        $data = $request->validateWithBag('batchLoss', [
            'loss_type' => [
                'required',
                Rule::in(['wastage', 'damage', 'expiry']),
            ],
            'quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        DB::transaction(function () use ($data, $batch, $spaId, $branchId, $user) {
            $lockedBatch = ProductBatch::query()
                ->where('spa_id', $spaId)
                ->where('branch_id', $branchId)
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            $product = Product::query()
                ->where('spa_id', $spaId)
                ->findOrFail($lockedBatch->product_id);

            $stock = BranchProductStock::query()
                ->where('spa_id', $spaId)
                ->where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $quantity = (float) $data['quantity'];
            $batchRemaining = (float) $lockedBatch->remaining_quantity;
            $branchOnHand = (float) $stock->on_hand_quantity;

            if ($quantity > $batchRemaining) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'quantity' => 'The quantity exceeds the remaining stock in this batch.',
                ])->errorBag('batchLoss');
            }

            if ($quantity > $branchOnHand) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'quantity' => 'The quantity exceeds the available branch stock.',
                ])->errorBag('batchLoss');
            }

            if (
                $data['loss_type'] === 'expiry' &&
                (
                    !$lockedBatch->expiration_date ||
                    !$lockedBatch->expiration_date->lt(today())
                )
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'loss_type' => 'Only an expired batch can be recorded as expired stock.',
                ])->errorBag('batchLoss');
            }

            $before = $branchOnHand;
            $after = $before - $quantity;

            $lockedBatch->update([
                'remaining_quantity' => $batchRemaining - $quantity,
            ]);

            $stock->update([
                'on_hand_quantity' => $after,
            ]);

            StockMovement::create([
                'spa_id' => $spaId,
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'product_batch_id' => $lockedBatch->id,
                'user_id' => $user->id,
                'booking_id' => null,
                'movement_type' => $data['loss_type'],
                'direction' => 'out',
                'quantity' => $quantity,
                'unit' => $product->usage_unit ?: $product->unit ?: 'pcs',
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => 'product_batch',
                'reference_id' => $lockedBatch->id,
                'notes' => $data['reason'],
                'occurred_at' => now(),
            ]);

            ProductLog::create([
                'spa_id' => $spaId,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'description' => sprintf(
                    '%s: %s %s removed from batch %s. Reason: %s',
                    ucwords($data['loss_type']),
                    rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.'),
                    $product->usage_unit ?: $product->unit ?: 'pcs',
                    $lockedBatch->batch_number,
                    $data['reason']
                ),
                'logged_at' => now(),
            ]);
        });

        return back()->with('success', 'Inventory loss recorded successfully.');
    }

    public function deduct(Request $request, Product $product)
    {
        $request->merge([
            'adjustment_type' => 'decrease',
            'quantity' => $request->input('amount'),
            'reason' => $request->input('reason', 'Legacy manual stock deduction.'),
        ]);

        return $this->adjustStock($request, $product);
    }

    public function destroy(Request $request, Product $product)
    {
        $user = $request->user();
        $spaId = $user->spa_id;
        $branchId = $user->currentBranchId();

        abort_unless($product->spa_id === $spaId, 403);

        if (!$branchId) {
            return back()->with('error', 'Please select a branch first.');
        }

        $stock = BranchProductStock::where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->where('product_id', $product->id)
            ->first();

        if (!$stock) {
            return back()->with('error', 'This product is not assigned to the selected branch.');
        }

        if ((float) $stock->on_hand_quantity > 0) {
            return back()->with(
                'error',
                'This product still has stock. Adjust the branch stock to zero before removing it.'
            );
        }

        DB::transaction(function () use ($product, $stock, $spaId, $user) {
            ProductLog::create([
                'spa_id' => $spaId,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'description' => "{$product->name} was removed from the current branch inventory",
                'logged_at' => now(),
            ]);

            $stock->delete();

            if (!$product->branchStocks()->exists()) {
                $product->delete();
            }
        });

        return back()->with('success', 'Product removed from this branch.');
    }

    public function logs(Request $request)
    {
        $user = $request->user();
        $spaId = $user->spa_id;
        $branchId = $user->currentBranchId();

        if (!$spaId) {
            return back()->with('error', 'No spa associated with your account.');
        }

        if (!$branchId) {
            return back()->with('error', 'Please select a branch first.');
        }

        $query = StockMovement::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->with([
                'product',
                'user',
                'batch',
            ]);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->movement_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('occurred_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('occurred_at', '<=', $request->date_to);
        }

        $logs = $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(5)
            ->withQueryString();

        $products = Product::query()
            ->where('spa_id', $spaId)
            ->whereHas('branchStocks', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $movementTypes = StockMovement::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->whereNotNull('movement_type')
            ->distinct()
            ->orderBy('movement_type')
            ->pluck('movement_type');

        return view('inventory.logs', compact(
            'logs',
            'products',
            'movementTypes'
        ));
    }

    public function exportLogsPdf(Request $request)
    {
        $user = $request->user();
        $spaId = $user->spa_id;
        $branchId = $user->currentBranchId();

        if (!$spaId) {
            return back()->with('error', 'No spa associated with your account.');
        }

        if (!$branchId) {
            return back()->with('error', 'Please select a branch first.');
        }

        $spa = $user->spa;

        $branch = Branch::query()
            ->where('spa_id', $spaId)
            ->whereKey($branchId)
            ->first();

        $query = StockMovement::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->with([
                'product',
                'user',
                'batch',
            ]);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->movement_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('occurred_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('occurred_at', '<=', $request->date_to);
        }

        $logs = $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        $selectedProduct = null;

        if ($request->filled('product_id')) {
            $selectedProduct = Product::query()
                ->where('spa_id', $spaId)
                ->find($request->integer('product_id'));
        }

        $filters = [
            'product' => $selectedProduct?->name,
            'movementType' => $request->movement_type,
            'dateFrom' => $request->filled('date_from')
                ? Carbon::parse($request->date_from)->format('M d, Y')
                : null,
            'dateTo' => $request->filled('date_to')
                ? Carbon::parse($request->date_to)->format('M d, Y')
                : null,
        ];

        $data = [
            'logs' => $logs,
            'filters' => $filters,
            'branchName' => $branch?->name ?? 'Current Branch',
            'spaName' => $spa?->spa_name ?? $spa?->name ?? 'Levictas Spa & Wellness',
            'generatedAt' => now()->format('F d, Y h:i A'),
            'totalLogs' => $logs->count(),
        ];

        $pdf = Pdf::loadView('inventory.logs-pdf', $data);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download(
            'inventory-stock-movements-' . now()->format('Y-m-d-His') . '.pdf'
        );
    }
}