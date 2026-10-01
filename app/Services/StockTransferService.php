<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockTransfer;
use App\Models\StockMovement;
use App\Models\StockTransferItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    private const MAX_TRANSFER_QUANTITY = 20000;
    private const PRECISION = 3;
    private const TOLERANCE = 0.001;

    public function __construct(
        private InventoryStockService $inventoryStockService
    ) {
    }

    public function createPendingTransfer(
        User $user,
        int $sourceBranchId,
        int $destinationBranchId,
        int $productId,
        float $quantity,
        ?string $notes = null
    ): StockTransfer {
        $spaId = (int) $user->spa_id;
        $quantity = $this->normalize($quantity);

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Transfer quantity must be greater than zero.',
            ])->errorBag('stockTransfer');
        }

        if ($quantity > self::MAX_TRANSFER_QUANTITY) {
            throw ValidationException::withMessages([
                'quantity' => 'A single transfer cannot exceed 20,000 units.',
            ])->errorBag('stockTransfer');
        }

        if ($sourceBranchId === $destinationBranchId) {
            throw ValidationException::withMessages([
                'destination_branch_id' => 'The destination branch must be different from the source branch.',
            ])->errorBag('stockTransfer');
        }

        $sourceBranch = Branch::query()
            ->where('id', $sourceBranchId)
            ->where('spa_id', $spaId)
            ->first();

        if (!$sourceBranch) {
            throw ValidationException::withMessages([
                'source_branch_id' => 'The source branch is invalid.',
            ])->errorBag('stockTransfer');
        }

        $destinationBranch = Branch::query()
            ->where('id', $destinationBranchId)
            ->where('spa_id', $spaId)
            ->first();

        if (!$destinationBranch) {
            throw ValidationException::withMessages([
                'destination_branch_id' => 'The destination branch is invalid.',
            ])->errorBag('stockTransfer');
        }

        $product = Product::query()
            ->where('id', $productId)
            ->where('spa_id', $spaId)
            ->where('is_active', true)
            ->first();

        if (!$product) {
            throw ValidationException::withMessages([
                'product_id' => 'The selected product is invalid or inactive.',
            ])->errorBag('stockTransfer');
        }

        $this->validateSourceAvailability(
            $product,
            $spaId,
            $sourceBranchId,
            $quantity
        );

        return DB::transaction(function () use (
            $user,
            $spaId,
            $sourceBranchId,
            $destinationBranchId,
            $product,
            $quantity,
            $notes
        ) {
            return StockTransfer::create([
                'spa_id' => $spaId,
                'source_branch_id' => $sourceBranchId,
                'destination_branch_id' => $destinationBranchId,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit' => $product->usage_unit
                    ?: $product->unit
                    ?: 'pcs',
                'status' => 'pending',
                'requested_by' => $user->id,
                'completed_by' => null,
                'notes' => $notes,
                'transferred_at' => null,
            ]);
        });
    }

    public function processTransfer(StockTransfer $transfer,User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user) {
            $lockedTransfer = StockTransfer::query()
                ->whereKey($transfer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTransfer->status !== 'pending') {
                throw ValidationException::withMessages([
                    'transfer' => 'Only pending stock transfers can be processed.',
                ])->errorBag('stockTransfer');
            }

            if ((int) $lockedTransfer->spa_id !== (int) $user->spa_id) {
                abort(403);
            }

            $this->lockTransferBranchStocks($lockedTransfer);

            $this->consumeSourceStock(
                $lockedTransfer,
                $user
            );

            $this->receiveDestinationStock(
                $lockedTransfer,
                $user
            );

            $lockedTransfer->update([
                'status' => 'completed',
                'completed_by' => $user->id,
                'transferred_at' => now(),
            ]);

            return $lockedTransfer->fresh([
                'sourceBranch',
                'destinationBranch',
                'product',
                'requestedBy',
                'completedBy',
                'items.sourceBatch',
                'items.destinationBatch',
            ]);
        });
    }

    public function consumeSourceStock(
        StockTransfer $transfer,
        User $user
    ): array {
        if ($transfer->status !== 'pending') {
            throw ValidationException::withMessages([
                'transfer' => 'Only pending stock transfers can be processed.',
            ])->errorBag('stockTransfer');
        }

        if ((int) $transfer->spa_id !== (int) $user->spa_id) {
            abort(403);
        }

        if (
            (int) $transfer->source_branch_id ===
            (int) $transfer->destination_branch_id
        ) {
            throw ValidationException::withMessages([
                'destination_branch_id' => 'The destination branch must be different from the source branch.',
            ])->errorBag('stockTransfer');
        }

        $product = Product::query()
            ->where('id', $transfer->product_id)
            ->where('spa_id', $transfer->spa_id)
            ->where('is_active', true)
            ->first();

        if (!$product) {
            throw ValidationException::withMessages([
                'product_id' => 'The selected product is invalid or inactive.',
            ])->errorBag('stockTransfer');
        }

        $quantity = $this->normalize(
            (float) $transfer->quantity
        );

        $result = $this->inventoryStockService->consumeFefo(
            $product,
            (int) $transfer->spa_id,
            (int) $transfer->source_branch_id,
            $quantity,
            'transfer',
            $user->id,
            null,
            StockTransfer::class,
            $transfer->id,
            sprintf(
                'Stock transfer #%d from branch %d to branch %d.',
                $transfer->id,
                $transfer->source_branch_id,
                $transfer->destination_branch_id
            ),
            'stockTransfer'
        );

        foreach ($result['batches'] as $batchData) {
            StockTransferItem::create([
                'stock_transfer_id' => $transfer->id,
                'source_product_batch_id' => $batchData['batch_id'],
                'destination_product_batch_id' => null,
                'source_batch_number' => $batchData['batch_number'],
                'quantity' => $this->normalize(
                    (float) $batchData['quantity']
                ),
                'expiration_date' => $batchData['expiration_date'] ?? null,
            ]);
        }

        return $result;
    }

    public function receiveDestinationStock(
        StockTransfer $transfer,
        User $user
    ): array {
        if ($transfer->status !== 'pending') {
            throw ValidationException::withMessages([
                'transfer' => 'Only pending stock transfers can be received.',
            ])->errorBag('stockTransfer');
        }

        if ((int) $transfer->spa_id !== (int) $user->spa_id) {
            abort(403);
        }

        $product = Product::query()
            ->where('id', $transfer->product_id)
            ->where('spa_id', $transfer->spa_id)
            ->where('is_active', true)
            ->first();

        if (!$product) {
            throw ValidationException::withMessages([
                'product_id' => 'The selected product is invalid or inactive.',
            ])->errorBag('stockTransfer');
        }

        $destinationBranch = Branch::query()
            ->where('id', $transfer->destination_branch_id)
            ->where('spa_id', $transfer->spa_id)
            ->first();

        if (!$destinationBranch) {
            throw ValidationException::withMessages([
                'destination_branch_id' => 'The destination branch is invalid.',
            ])->errorBag('stockTransfer');
        }

        $destinationStock = BranchProductStock::query()
            ->where('spa_id', $transfer->spa_id)
            ->where('branch_id', $transfer->destination_branch_id)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->firstOrFail();

        $destinationBatches = ProductBatch::query()
            ->where('spa_id', $transfer->spa_id)
            ->where('branch_id', $transfer->destination_branch_id)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->get();

        $branchQuantity = $this->normalize(
            (float) $destinationStock->on_hand_quantity
        );

        $batchQuantity = $this->normalize(
            (float) $destinationBatches->sum(
                fn ($batch) => (float) $batch->remaining_quantity
            )
        );

        if (
            abs($branchQuantity - $batchQuantity) >
            self::TOLERANCE
        ) {
            throw ValidationException::withMessages([
                'quantity' => sprintf(
                    'Destination inventory mismatch detected. Branch stock is %s %s while batch stock totals %s %s.',
                    $this->formatQuantity($branchQuantity),
                    $product->usage_unit ?: $product->unit ?: 'pcs',
                    $this->formatQuantity($batchQuantity),
                    $product->usage_unit ?: $product->unit ?: 'pcs'
                ),
            ])->errorBag('stockTransfer');
        }

        $transferQuantity = $this->normalize(
            (float) $transfer->quantity
        );

        $after = $this->normalize(
            $branchQuantity + $transferQuantity
        );

        if ($after > self::MAX_TRANSFER_QUANTITY + self::TOLERANCE) {
            throw ValidationException::withMessages([
                'quantity' => sprintf(
                    'The destination branch cannot receive this transfer because the resulting stock would be %s %s. Branch stock cannot exceed 20,000 units.',
                    $this->formatQuantity($after),
                    $product->usage_unit ?: $product->unit ?: 'pcs'
                ),
            ])->errorBag('stockTransfer');
        }

        if (
            $destinationStock->maximum_stock !== null &&
            $after >
                $this->normalize(
                    (float) $destinationStock->maximum_stock
                ) + self::TOLERANCE
        ) {
            throw ValidationException::withMessages([
                'quantity' => sprintf(
                    'The destination branch cannot receive this transfer because it would exceed the configured maximum stock of %s %s.',
                    $this->formatQuantity(
                        (float) $destinationStock->maximum_stock
                    ),
                    $product->usage_unit ?: $product->unit ?: 'pcs'
                ),
            ])->errorBag('stockTransfer');
        }

        $items = StockTransferItem::query()
            ->with('sourceBatch')
            ->where('stock_transfer_id', $transfer->id)
            ->lockForUpdate()
            ->get();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'transfer' => 'No FEFO source batches were recorded for this transfer.',
            ])->errorBag('stockTransfer');
        }

        $itemsQuantity = $this->normalize(
            (float) $items->sum(
                fn ($item) => (float) $item->quantity
            )
        );

        if (
            abs($itemsQuantity - $transferQuantity) >
            self::TOLERANCE
        ) {
            throw ValidationException::withMessages([
                'transfer' => sprintf(
                    'Transfer batch quantities total %s %s but the transfer quantity is %s %s.',
                    $this->formatQuantity($itemsQuantity),
                    $product->usage_unit ?: $product->unit ?: 'pcs',
                    $this->formatQuantity($transferQuantity),
                    $product->usage_unit ?: $product->unit ?: 'pcs'
                ),
            ])->errorBag('stockTransfer');
        }

        $createdBatches = [];

        foreach ($items as $item) {
            if ($item->destination_product_batch_id) {
                throw ValidationException::withMessages([
                    'transfer' => 'One or more transfer batches have already been received by the destination branch.',
                ])->errorBag('stockTransfer');
            }

            $sourceBatch = $item->sourceBatch;

            $destinationBatch = ProductBatch::create([
                'spa_id' => $transfer->spa_id,
                'branch_id' => $transfer->destination_branch_id,
                'product_id' => $product->id,
                'batch_number' => $this->destinationBatchNumber(
                    $transfer,
                    $item
                ),
                'received_quantity' => $this->normalize(
                    (float) $item->quantity
                ),
                'remaining_quantity' => $this->normalize(
                    (float) $item->quantity
                ),
                'unit_cost' => $sourceBatch?->unit_cost,
                'manufactured_at' => $sourceBatch?->manufactured_at
                    ? $sourceBatch->manufactured_at->toDateString()
                    : null,
                'expiration_date' => $item->expiration_date
                    ? $item->expiration_date->toDateString()
                    : (
                        $sourceBatch?->expiration_date
                            ? $sourceBatch->expiration_date->toDateString()
                            : null
                    ),
                'received_at' => now(),
            ]);

            $itemQuantity = $this->normalize(
                (float) $item->quantity
            );

            $movementBefore = $branchQuantity;

            foreach ($createdBatches as $createdBatch) {
                $movementBefore = $this->normalize(
                    $movementBefore + (float) $createdBatch['quantity']
                );
            }

            $movementAfter = $this->normalize(
                $movementBefore + $itemQuantity
            );

            StockMovement::create([
                'spa_id' => $transfer->spa_id,
                'branch_id' => $transfer->destination_branch_id,
                'product_id' => $product->id,
                'product_batch_id' => $destinationBatch->id,
                'user_id' => $user->id,
                'booking_id' => null,
                'movement_type' => 'transfer',
                'direction' => 'in',
                'quantity' => $itemQuantity,
                'unit' => $product->usage_unit ?: $product->unit ?: 'pcs',
                'balance_before' => $movementBefore,
                'balance_after' => $movementAfter,
                'reference_type' => StockTransfer::class,
                'reference_id' => $transfer->id,
                'notes' => sprintf(
                    'Stock transfer #%d received from branch %d.',
                    $transfer->id,
                    $transfer->source_branch_id
                ),
                'occurred_at' => now(),
            ]);

            $item->update([
                'destination_product_batch_id' => $destinationBatch->id,
            ]);

            $createdBatches[] = [
                'transfer_item_id' => $item->id,
                'source_batch_id' => $item->source_product_batch_id,
                'destination_batch_id' => $destinationBatch->id,
                'batch_number' => $destinationBatch->batch_number,
                'quantity' => $this->normalize(
                    (float) $item->quantity
                ),
                'expiration_date' => $destinationBatch->expiration_date
                    ? $destinationBatch->expiration_date->toDateString()
                    : null,
            ];
        }

        $destinationStock->update([
            'on_hand_quantity' => $after,
        ]);

        $newBatchQuantity = $this->normalize(
            (float) ProductBatch::query()
                ->where('spa_id', $transfer->spa_id)
                ->where('branch_id', $transfer->destination_branch_id)
                ->where('product_id', $product->id)
                ->sum('remaining_quantity')
        );

        if (
            abs($after - $newBatchQuantity) >
            self::TOLERANCE
        ) {
            throw ValidationException::withMessages([
                'quantity' => sprintf(
                    'Destination inventory reconciliation failed. Branch stock is %s %s while batch stock totals %s %s.',
                    $this->formatQuantity($after),
                    $product->usage_unit ?: $product->unit ?: 'pcs',
                    $this->formatQuantity($newBatchQuantity),
                    $product->usage_unit ?: $product->unit ?: 'pcs'
                ),
            ])->errorBag('stockTransfer');
        }

        return [
            'quantity' => $transferQuantity,
            'balance_before' => $branchQuantity,
            'balance_after' => $after,
            'batches' => $createdBatches,
        ];
    }

    private function lockTransferBranchStocks(StockTransfer $transfer): void
    {
        $branchIds = [
            (int) $transfer->source_branch_id,
            (int) $transfer->destination_branch_id,
        ];

        sort($branchIds);

        $branches = Branch::query()
            ->where('spa_id', $transfer->spa_id)
            ->whereIn('id', $branchIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($branches->count() !== 2) {
            throw ValidationException::withMessages([
                'destination_branch_id' => 'One or more transfer branches are invalid.',
            ])->errorBag('stockTransfer');
        }

        $destinationStock = BranchProductStock::firstOrCreate(
            [
                'spa_id' => $transfer->spa_id,
                'branch_id' => $transfer->destination_branch_id,
                'product_id' => $transfer->product_id,
            ],
            [
                'on_hand_quantity' => 0,
                'reorder_level' => 0,
                'minimum_stock' => null,
                'maximum_stock' => null,
            ]
        );

        $stocks = BranchProductStock::query()
            ->where('spa_id', $transfer->spa_id)
            ->where('product_id', $transfer->product_id)
            ->whereIn('branch_id', $branchIds)
            ->orderBy('branch_id')
            ->lockForUpdate()
            ->get();

        if (!$stocks->contains(
            fn ($stock) =>
                (int) $stock->branch_id ===
                (int) $transfer->source_branch_id
        )) {
            throw ValidationException::withMessages([
                'quantity' => 'Source branch inventory is unavailable for this product.',
            ])->errorBag('stockTransfer');
        }

        if (!$stocks->contains(
            fn ($stock) =>
                (int) $stock->branch_id ===
                (int) $destinationStock->branch_id
        )) {
            throw ValidationException::withMessages([
                'quantity' => 'Destination branch inventory could not be prepared for this transfer.',
            ])->errorBag('stockTransfer');
        }
    }

    private function validateSourceAvailability(
        Product $product,
        int $spaId,
        int $branchId,
        float $quantity
    ): void {
        $status = $this->inventoryStockService->reconciliationStatus(
            $product,
            $spaId,
            $branchId
        );

        if (!$status['reconciled']) {
            throw ValidationException::withMessages([
                'quantity' => sprintf(
                    'Inventory mismatch detected for %s. Reconcile branch stock before creating a transfer.',
                    $product->name
                ),
            ])->errorBag('stockTransfer');
        }

        $usableQuantity = $this->normalize(
            (float) $status['usable_quantity']
        );

        if ($quantity > $usableQuantity + self::TOLERANCE) {
            throw ValidationException::withMessages([
                'quantity' => sprintf(
                    'Cannot transfer %s %s. Only %s %s of usable stock is available.',
                    $this->formatQuantity($quantity),
                    $product->usage_unit ?: $product->unit ?: 'pcs',
                    $this->formatQuantity($usableQuantity),
                    $product->usage_unit ?: $product->unit ?: 'pcs'
                ),
            ])->errorBag('stockTransfer');
        }
    }

    private function destinationBatchNumber(
        StockTransfer $transfer,
        StockTransferItem $item
    ): string {
        return sprintf(
            'TRF-%d-%d-%s',
            $transfer->id,
            $item->id,
            $item->source_batch_number
        );
    }

    private function normalize(float $quantity): float
    {
        return round($quantity, self::PRECISION);
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(
            rtrim(
                number_format(
                    $this->normalize($quantity),
                    self::PRECISION,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        );
    }
}
