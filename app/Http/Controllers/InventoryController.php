<?php

namespace App\Http\Controllers;

use App\Services\InventoryStockService;
use App\Models\BranchProductStock;
use App\Models\Branch;
use Carbon\Carbon;
use App\Models\Product;
use App\Models\ProductLog;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    private const MAX_STOCK_QUANTITY = 20000;

    public function products(Request $request, InventoryStockService $inventoryStockService)
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

        $products->getCollection()->transform(function ($product) use (
            $inventoryStockService,
            $spaId,
            $branchId
        ) {
            $product->branch_stock = $product->branchStocks->first();
            $product->nearest_expiration = $product->batches->first()?->expiration_date;

            $reconciliation = $inventoryStockService->reconciliationStatus(
                $product,
                $spaId,
                $branchId
            );

            $product->batch_quantity = $reconciliation['batch_quantity'];
            $product->stock_difference = $reconciliation['difference'];
            $product->inventory_reconciled = $reconciliation['is_reconciled'];

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
            'conversion_factor' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,3',
                'max:20000',
            ],
            'retail_price' => ['nullable', 'numeric', 'min:0'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'opening_stock' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,3',
                'max:' . self::MAX_STOCK_QUANTITY,
            ],
            'reorder_level' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,3',
                'max:' . self::MAX_STOCK_QUANTITY,
            ],
            'minimum_stock' => [
                'nullable',
                'numeric',
                'min:0',
                'decimal:0,3',
                'max:' . self::MAX_STOCK_QUANTITY,
            ],
            'maximum_stock' => [
                'nullable',
                'numeric',
                'min:0',
                'decimal:0,3',
                'max:' . self::MAX_STOCK_QUANTITY,
            ],
        ], [
            'opening_stock.max' => 'Opening stock cannot exceed 20,000 units.',
            'opening_stock.decimal' => 'Opening stock can have a maximum of 3 decimal places.',
            'reorder_level.max' => 'Reorder level cannot exceed 20,000 units.',
            'reorder_level.decimal' => 'Reorder level can have a maximum of 3 decimal places.',
            'minimum_stock.max' => 'Minimum stock cannot exceed 20,000 units.',
            'minimum_stock.decimal' => 'Minimum stock can have a maximum of 3 decimal places.',
            'maximum_stock.max' => 'Maximum stock cannot exceed 20,000 units.',
            'maximum_stock.decimal' => 'Maximum stock can have a maximum of 3 decimal places.',
            'conversion_factor.gt' => 'Conversion factor must be greater than zero.',
            'conversion_factor.decimal' => 'Conversion factor can have a maximum of 3 decimal places.',
            'conversion_factor.max' => 'Conversion factor cannot exceed 20,000 units per purchase unit.',
        ]);

        $this->validateStockThresholds($data);

        if (
            $data['maximum_stock'] !== null &&
            (float) $data['opening_stock'] > (float) $data['maximum_stock']
        ) {
            throw ValidationException::withMessages([
                'opening_stock' => 'Opening stock cannot be greater than the configured maximum stock.',
            ]);
        }

        DB::transaction(function () use ($data, $spaId, $branchId, $user) {
            $openingStock = round((float) $data['opening_stock'], 3);

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
                'on_hand_quantity' => $openingStock,
                'reorder_level' => $data['reorder_level'],
                'minimum_stock' => $data['minimum_stock'] ?? null,
                'maximum_stock' => $data['maximum_stock'] ?? null,
            ]);

            if ($openingStock > 0) {
                $batch = ProductBatch::create([
                    'spa_id' => $spaId,
                    'branch_id' => $branchId,
                    'product_id' => $product->id,
                    'batch_number' => 'OPENING-' . $branchId . '-' . $product->id,
                    'received_quantity' => $openingStock,
                    'remaining_quantity' => $openingStock,
                    'unit_cost' => $product->acquisition_cost,
                    'manufactured_at' => null,
                    'expiration_date' => null,
                    'received_at' => now(),
                ]);

                StockMovement::create([
                    'spa_id' => $spaId,
                    'branch_id' => $branchId,
                    'product_id' => $product->id,
                    'product_batch_id' => $batch->id,
                    'user_id' => $user->id,
                    'booking_id' => null,
                    'movement_type' => 'opening_balance',
                    'direction' => 'in',
                    'quantity' => $openingStock,
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
            'conversion_factor' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,3',
                'max:20000',
            ],
            'retail_price' => ['nullable', 'numeric', 'min:0'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,3',
                'max:' . self::MAX_STOCK_QUANTITY,
            ],
            'minimum_stock' => [
                'nullable',
                'numeric',
                'min:0',
                'decimal:0,3',
                'max:' . self::MAX_STOCK_QUANTITY,
            ],
            'maximum_stock' => [
                'nullable',
                'numeric',
                'min:0',
                'decimal:0,3',
                'max:' . self::MAX_STOCK_QUANTITY,
            ],
        ], [
            'reorder_level.max' => 'Reorder level cannot exceed 20,000 units.',
            'reorder_level.decimal' => 'Reorder level can have a maximum of 3 decimal places.',
            'minimum_stock.max' => 'Minimum stock cannot exceed 20,000 units.',
            'minimum_stock.decimal' => 'Minimum stock can have a maximum of 3 decimal places.',
            'maximum_stock.max' => 'Maximum stock cannot exceed 20,000 units.',
            'maximum_stock.decimal' => 'Maximum stock can have a maximum of 3 decimal places.',
        ]);

        $this->validateStockThresholds($data);

        if (
            $data['maximum_stock'] !== null &&
            (float) $stock->on_hand_quantity > (float) $data['maximum_stock']
        ) {
            throw ValidationException::withMessages([
                'maximum_stock' => sprintf(
                    'Maximum stock cannot be lower than the current branch stock of %s %s.',
                    $this->formatQuantity((float) $stock->on_hand_quantity),
                    $product->usage_unit ?: $product->unit ?: 'pcs'
                ),
            ]);
        }

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

    public function adjustStock(
        Request $request,
        Product $product,
        InventoryStockService $inventoryStockService
    ) {
        $user = $request->user();
        $spaId = $user->spa_id;
        $branchId = $user->currentBranchId();

        abort_unless($product->spa_id === $spaId, 403);

        if (!$branchId) {
            return back()->with('error', 'Please select a branch first.');
        }

        $data = $request->validateWithBag('adjustStock', [
            'adjustment_type' => [
                'required',
                Rule::in(['increase', 'decrease']),
            ],
            'quantity' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,3',
                'max:' . self::MAX_STOCK_QUANTITY,
            ],
            'reason' => [
                'required',
                'string',
                'min:3',
                'max:1000',
            ],
        ], [
            'quantity.gt' => 'The stock quantity must be greater than zero.',
            'quantity.decimal' => 'Stock quantity can have a maximum of 3 decimal places.',
            'quantity.max' => 'A single stock adjustment cannot exceed 20,000 units.',
            'reason.min' => 'Please provide a meaningful reason for the stock adjustment.',
        ]);

        DB::transaction(function () use (
            $data,
            $product,
            $spaId,
            $branchId,
            $user,
            $inventoryStockService
        ) {
            $quantity = round((float) $data['quantity'], 3);

            if ($data['adjustment_type'] === 'decrease') {
                $inventoryStockService->consumeFefo(
                    product: $product,
                    spaId: $spaId,
                    branchId: $branchId,
                    quantity: $quantity,
                    movementType: 'adjustment_out',
                    userId: $user->id,
                    referenceType: 'manual_adjustment',
                    referenceId: $product->id,
                    notes: $data['reason']
                );

                ProductLog::create([
                    'spa_id' => $spaId,
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'description' => sprintf(
                        '%s decreased by %s %s. Reason: %s',
                        $product->name,
                        $this->formatQuantity($quantity),
                        $product->usage_unit ?: $product->unit ?: 'pcs',
                        $data['reason']
                    ),
                    'logged_at' => now(),
                ]);

                return;
            }

            $stock = BranchProductStock::query()
                ->where('spa_id', $spaId)
                ->where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = round((float) $stock->on_hand_quantity, 3);
            $after = round($before + $quantity, 3);

            if ($after > self::MAX_STOCK_QUANTITY) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf(
                        'This adjustment would increase branch stock to %s %s. Branch stock cannot exceed 20,000 units.',
                        $this->formatQuantity($after),
                        $product->usage_unit ?: $product->unit ?: 'pcs'
                    ),
                ])->errorBag('adjustStock');
            }

            if (
                $stock->maximum_stock !== null &&
                $after > (float) $stock->maximum_stock
            ) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf(
                        'This adjustment would exceed the configured maximum stock of %s %s.',
                        $this->formatQuantity((float) $stock->maximum_stock),
                        $product->usage_unit ?: $product->unit ?: 'pcs'
                    ),
                ])->errorBag('adjustStock');
            }

            $batch = ProductBatch::create([
                'spa_id' => $spaId,
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'batch_number' => 'ADJ-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
                'received_quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'unit_cost' => null,
                'manufactured_at' => null,
                'expiration_date' => null,
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
                'movement_type' => 'adjustment_in',
                'direction' => 'in',
                'quantity' => $quantity,
                'unit' => $product->usage_unit ?: $product->unit ?: 'pcs',
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => 'manual_adjustment',
                'reference_id' => $product->id,
                'notes' => $data['reason'],
                'occurred_at' => now(),
            ]);

            ProductLog::create([
                'spa_id' => $spaId,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'description' => sprintf(
                    '%s increased by %s %s. Reason: %s',
                    $product->name,
                    $this->formatQuantity($quantity),
                    $product->usage_unit ?: $product->unit ?: 'pcs',
                    $data['reason']
                ),
                'logged_at' => now(),
            ]);
        });

        return back()->with('success', 'Stock adjustment recorded successfully.');
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
            'received_quantity' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,3',
                'max:' . self::MAX_STOCK_QUANTITY,
            ],
            'unit_cost' => [
                'nullable',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],
            'manufactured_at' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],
            'expiration_date' => [
                'nullable',
                'date',
                'after_or_equal:today',
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'received_quantity.gt' => 'Received quantity must be greater than zero.',
            'received_quantity.decimal' => 'Received quantity can have a maximum of 3 decimal places.',
            'received_quantity.max' => 'A single stock receipt cannot exceed 20,000 units.',
            'unit_cost.decimal' => 'Unit cost can have a maximum of 2 decimal places.',
            'manufactured_at.before_or_equal' => 'Manufacturing date cannot be in the future.',
            'expiration_date.after_or_equal' => 'Expiration date cannot be earlier than today.',
        ]);

        if (
            !empty($data['manufactured_at']) &&
            !empty($data['expiration_date']) &&
            Carbon::parse($data['expiration_date'])->lt(
                Carbon::parse($data['manufactured_at'])
            )
        ) {
            throw ValidationException::withMessages([
                'expiration_date' => 'Expiration date cannot be earlier than the manufacturing date.',
            ])->errorBag('receiveStock');
        }

        DB::transaction(function () use ($data, $product, $spaId, $branchId, $user) {
            $stock = BranchProductStock::where('spa_id', $spaId)
                ->where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = round((float) $stock->on_hand_quantity, 3);
            $quantity = round((float) $data['received_quantity'], 3);
            $after = round($before + $quantity, 3);

            if ($after > self::MAX_STOCK_QUANTITY) {
                throw ValidationException::withMessages([
                    'received_quantity' => sprintf(
                        'Receiving this stock would increase branch stock to %s %s. Branch stock cannot exceed 20,000 units.',
                        $this->formatQuantity($after),
                        $product->usage_unit ?: $product->unit ?: 'pcs'
                    ),
                ])->errorBag('receiveStock');
            }

            if (
                $stock->maximum_stock !== null &&
                $after > (float) $stock->maximum_stock
            ) {
                throw ValidationException::withMessages([
                    'received_quantity' => sprintf(
                        'Receiving this stock would exceed the configured maximum stock of %s %s.',
                        $this->formatQuantity((float) $stock->maximum_stock),
                        $product->usage_unit ?: $product->unit ?: 'pcs'
                    ),
                ])->errorBag('receiveStock');
            }

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
                'description' => sprintf(
                    '%s received %s %s',
                    $product->name,
                    $this->formatQuantity($quantity),
                    $product->usage_unit ?: $product->unit ?: 'pcs'
                ),
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
                'decimal:0,3',
                'max:' . self::MAX_STOCK_QUANTITY,
            ],
            'reason' => [
                'required',
                'string',
                'min:3',
                'max:1000',
            ],
        ], [
            'quantity.gt' => 'Loss quantity must be greater than zero.',
            'quantity.decimal' => 'Loss quantity can have a maximum of 3 decimal places.',
            'quantity.max' => 'Loss quantity cannot exceed 20,000 units.',
            'reason.min' => 'Please provide a meaningful reason for the inventory loss.',
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

            $quantity = round((float) $data['quantity'], 3);
            $batchRemaining = round((float) $lockedBatch->remaining_quantity, 3);
            $branchOnHand = round((float) $stock->on_hand_quantity, 3);

            if ($quantity > $batchRemaining) {
                throw ValidationException::withMessages([
                    'quantity' => 'The quantity exceeds the remaining stock in this batch.',
                ])->errorBag('batchLoss');
            }

            if ($quantity > $branchOnHand) {
                throw ValidationException::withMessages([
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
                throw ValidationException::withMessages([
                    'loss_type' => 'Only an expired batch can be recorded as expired stock.',
                ])->errorBag('batchLoss');
            }

            $before = $branchOnHand;
            $after = round($before - $quantity, 3);

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'This loss would result in negative branch stock.',
                ])->errorBag('batchLoss');
            }

            if (abs($after) <= 0.001) {
                $after = 0;
            }

            $newBatchRemaining = round($batchRemaining - $quantity, 3);

            if ($newBatchRemaining < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'This loss would result in negative batch stock.',
                ])->errorBag('batchLoss');
            }

            if (abs($newBatchRemaining) <= 0.001) {
                $newBatchRemaining = 0;
            }

            $lockedBatch->update([
                'remaining_quantity' => $newBatchRemaining,
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
                    $this->formatQuantity($quantity),
                    $product->usage_unit ?: $product->unit ?: 'pcs',
                    $lockedBatch->batch_number,
                    $data['reason']
                ),
                'logged_at' => now(),
            ]);
        });

        return back()->with('success', 'Inventory loss recorded successfully.');
    }

    public function deduct(
        Request $request,
        Product $product,
        InventoryStockService $inventoryStockService
    ) {
        $request->merge([
            'adjustment_type' => 'decrease',
            'quantity' => $request->input('amount'),
            'reason' => $request->input('reason', 'Legacy manual stock deduction.'),
        ]);

        return $this->adjustStock(
            $request,
            $product,
            $inventoryStockService
        );
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

    private function validateStockThresholds(array $data): void
    {
        $minimumStock = $data['minimum_stock'] ?? null;
        $reorderLevel = $data['reorder_level'] ?? null;
        $maximumStock = $data['maximum_stock'] ?? null;

        if (
            $minimumStock !== null &&
            $reorderLevel !== null &&
            (float) $minimumStock > (float) $reorderLevel
        ) {
            throw ValidationException::withMessages([
                'minimum_stock' => 'Minimum stock cannot be greater than the reorder level.',
            ]);
        }

        if (
            $maximumStock !== null &&
            $reorderLevel !== null &&
            (float) $reorderLevel > (float) $maximumStock
        ) {
            throw ValidationException::withMessages([
                'reorder_level' => 'Reorder level cannot be greater than maximum stock.',
            ]);
        }

        if (
            $minimumStock !== null &&
            $maximumStock !== null &&
            (float) $minimumStock > (float) $maximumStock
        ) {
            throw ValidationException::withMessages([
                'minimum_stock' => 'Minimum stock cannot be greater than maximum stock.',
            ]);
        }
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(
            rtrim(
                number_format($quantity, 3, '.', ''),
                '0'
            ),
            '.'
        );
    }
}