<?php

namespace App\Services;

use App\Models\BranchProductStock;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use Illuminate\Validation\ValidationException;

class InventoryStockService
{
    private const PRECISION = 3;
    private const TOLERANCE = 0.001;

    public function consumeFefo(
        Product $product,
        int $spaId,
        int $branchId,
        float $quantity,
        string $movementType,
        ?int $userId = null,
        ?int $bookingId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        string $errorBag = 'adjustStock'
    ): array {
        $quantity = $this->normalize($quantity);

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'The quantity must be greater than zero.',
            ])->errorBag($errorBag);
        }

        if ((int) $product->spa_id !== $spaId) {
            abort(403);
        }

        $stock = BranchProductStock::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->firstOrFail();

        $allBatches = ProductBatch::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->where('product_id', $product->id)
            ->where('remaining_quantity', '>', 0)
            ->orderByRaw('expiration_date IS NULL')
            ->orderBy('expiration_date')
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $branchQuantity = $this->normalize(
            (float) $stock->on_hand_quantity
        );

        $batchQuantity = $this->normalize(
            (float) $allBatches->sum(
                fn ($batch) => (float) $batch->remaining_quantity
            )
        );

        $this->assertReconciled(
            $product,
            $branchQuantity,
            $batchQuantity,
            null,
            $errorBag
        );

        $usableBatches = $allBatches->filter(function ($batch) {
            return !$batch->expiration_date ||
                $batch->expiration_date->gte(today());
        });

        $usableQuantity = $this->normalize(
            (float) $usableBatches->sum(
                fn ($batch) => (float) $batch->remaining_quantity
            )
        );

        if ($quantity > $usableQuantity + self::TOLERANCE) {
            throw ValidationException::withMessages([
                'quantity' => sprintf(
                    'Cannot deduct %s %s. Only %s %s of usable non-expired stock is available.',
                    $this->formatQuantity($quantity),
                    $product->usage_unit ?: $product->unit ?: 'pcs',
                    $this->formatQuantity($usableQuantity),
                    $product->usage_unit ?: $product->unit ?: 'pcs'
                ),
            ])->errorBag($errorBag);
        }

        $remainingToConsume = $quantity;
        $balance = $branchQuantity;
        $consumedBatches = [];

        foreach ($usableBatches as $batch) {
            if ($remainingToConsume <= self::TOLERANCE) {
                break;
            }

            $batchRemaining = $this->normalize(
                (float) $batch->remaining_quantity
            );

            if ($batchRemaining <= self::TOLERANCE) {
                continue;
            }

            $take = $this->normalize(
                min($batchRemaining, $remainingToConsume)
            );

            if ($take <= 0) {
                continue;
            }

            $before = $balance;
            $after = $this->normalize($before - $take);

            if ($after < -self::TOLERANCE) {
                throw ValidationException::withMessages([
                    'quantity' => 'The deduction would result in negative branch stock.',
                ])->errorBag($errorBag);
            }

            if (abs($after) <= self::TOLERANCE) {
                $after = 0.0;
            }

            $newBatchRemaining = $this->normalize(
                $batchRemaining - $take
            );

            if (abs($newBatchRemaining) <= self::TOLERANCE) {
                $newBatchRemaining = 0.0;
            }

            $batch->update([
                'remaining_quantity' => $newBatchRemaining,
            ]);

            StockMovement::create([
                'spa_id' => $spaId,
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'product_batch_id' => $batch->id,
                'user_id' => $userId,
                'booking_id' => $bookingId,
                'movement_type' => $movementType,
                'direction' => 'out',
                'quantity' => $take,
                'unit' => $product->usage_unit ?: $product->unit ?: 'pcs',
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'occurred_at' => now(),
            ]);

            $consumedBatches[] = [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'quantity' => $take,
                'expiration_date' => $batch->expiration_date
                    ? $batch->expiration_date->toDateString()
                    : null,
                'manufactured_at' => $batch->manufactured_at
                    ? $batch->manufactured_at->toDateString()
                    : null,
                'unit_cost' => $batch->unit_cost,
            ];

            $balance = $after;

            $remainingToConsume = $this->normalize(
                $remainingToConsume - $take
            );
        }

        if ($remainingToConsume > self::TOLERANCE) {
            throw ValidationException::withMessages([
                'quantity' => 'The full quantity could not be deducted from the available batches.',
            ])->errorBag($errorBag);
        }

        $stock->update([
            'on_hand_quantity' => $balance,
        ]);

        $newBatchQuantity = $this->normalize(
            (float) ProductBatch::query()
                ->where('spa_id', $spaId)
                ->where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->sum('remaining_quantity')
        );

        $this->assertReconciled(
            $product,
            $balance,
            $newBatchQuantity,
            'Inventory reconciliation failed after the deduction. No stock changes were saved.',
            $errorBag
        );

        return [
            'quantity' => $quantity,
            'balance_after' => $balance,
            'batches' => $consumedBatches,
        ];
    }

    public function reconciliationStatus(
        Product $product,
        int $spaId,
        int $branchId
    ): array {
        if ((int) $product->spa_id !== $spaId) {
            abort(403);
        }

        $stock = BranchProductStock::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->where('product_id', $product->id)
            ->first();

        $branchQuantity = $this->normalize(
            (float) ($stock?->on_hand_quantity ?? 0)
        );

        $batchQuantity = $this->normalize(
            (float) ProductBatch::query()
                ->where('spa_id', $spaId)
                ->where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->sum('remaining_quantity')
        );

        $usableQuantity = $this->normalize(
            (float) ProductBatch::query()
                ->where('spa_id', $spaId)
                ->where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->where('remaining_quantity', '>', 0)
                ->where(function ($query) {
                    $query
                        ->whereNull('expiration_date')
                        ->orWhereDate('expiration_date', '>=', today());
                })
                ->sum('remaining_quantity')
        );

        $difference = $this->normalize(
            $branchQuantity - $batchQuantity
        );

        $isReconciled = abs($difference) <= self::TOLERANCE;

        return [
            'branch_quantity' => $branchQuantity,
            'batch_quantity' => $batchQuantity,
            'usable_quantity' => $usableQuantity,
            'difference' => $difference,
            'is_reconciled' => $isReconciled,
            'reconciled' => $isReconciled,
        ];
    }

    private function assertReconciled(
        Product $product,
        float $branchQuantity,
        float $batchQuantity,
        ?string $message = null,
        string $errorBag = 'adjustStock'
    ): void {
        if (abs($branchQuantity - $batchQuantity) <= self::TOLERANCE) {
            return;
        }

        throw ValidationException::withMessages([
            'quantity' => $message ?: sprintf(
                'Inventory mismatch detected. Branch stock is %s %s while batch stock totals %s %s.',
                $this->formatQuantity($branchQuantity),
                $product->usage_unit ?: $product->unit ?: 'pcs',
                $this->formatQuantity($batchQuantity),
                $product->usage_unit ?: $product->unit ?: 'pcs'
            ),
        ])->errorBag($errorBag);
    }

    private function normalize(float $quantity): float
    {
        return round($quantity, self::PRECISION);
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(
            rtrim(
                number_format($quantity, self::PRECISION, '.', ''),
                '0'
            ),
            '.'
        );
    }
}
