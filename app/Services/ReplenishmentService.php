<?php

namespace App\Services;

use App\Models\BranchProductStock;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReplenishmentService
{
    public function forBranch(int $spaId,int $branchId): Collection
    {
        return BranchProductStock::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->with('product')
            ->orderBy('product_id')
            ->get()
            ->map(fn($stock) => $this->buildProductSnapshot($stock,$spaId,$branchId));
    }

    public function forProduct(
        int $spaId,
        int $branchId,
        int $productId
    ): array {
        $stock = BranchProductStock::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->with('product')
            ->first();

        if (!$stock) {
            throw ValidationException::withMessages([
                'product' => 'The product is not configured in this branch inventory.',
            ]);
        }

        return $this->buildProductSnapshot($stock,$spaId,$branchId);
    }

    private function buildProductSnapshot(
        BranchProductStock $stock,
        int $spaId,
        int $branchId
    ): array {
        $product = $stock->product;

        $onHand = $this->roundQuantity($stock->on_hand_quantity);

        $incoming = $this->incomingInventoryQuantity(
            $spaId,
            $branchId,
            $stock->product_id
        );

        $projectedStock = $this->roundQuantity(
            $onHand + $incoming
        );

        $reorderLevel = $this->nullableQuantity(
            $stock->reorder_level
        ) ?? 0;

        $minimumStock = $this->nullableQuantity(
            $stock->minimum_stock
        );

        $maximumStock = $this->nullableQuantity(
            $stock->maximum_stock
        );

        $needsReorder = $reorderLevel > 0 &&
            $projectedStock <= $reorderLevel;

        $recommendedInventoryQuantity = $needsReorder
            ? $this->recommendedInventoryQuantity(
                $projectedStock,
                $reorderLevel,
                $minimumStock,
                $maximumStock
            )
            : 0;

        $recommendedPurchaseQuantity = $this->toPurchaseQuantity(
            $recommendedInventoryQuantity,
            $product?->purchase_unit,
            $product?->usage_unit ?: $product?->unit,
            $product?->conversion_factor
        );

        return [
            'product_id' => $stock->product_id,
            'product_name' => $product?->name ?? 'Unknown Product',
            'sku' => $product?->sku,

            'usage_unit' => $product?->usage_unit
                ?: $product?->unit
                ?: 'unit',

            'purchase_unit' => $product?->purchase_unit,

            'conversion_factor' => $this->roundQuantity(
                $product?->conversion_factor ?: 1
            ),

            'on_hand_quantity' => $onHand,

            'incoming_quantity' => $incoming,

            'projected_stock' => $projectedStock,

            'reorder_level' => $reorderLevel,

            'minimum_stock' => $minimumStock,

            'maximum_stock' => $maximumStock,

            'needs_reorder' => $needsReorder,

            'recommended_inventory_quantity' =>
                $recommendedInventoryQuantity,

            'recommended_purchase_quantity' =>
                $recommendedPurchaseQuantity,

            'recommendation_target' =>
                $this->recommendationTarget(
                    $reorderLevel,
                    $minimumStock,
                    $maximumStock
                ),

            'status' => $this->status(
                $projectedStock,
                $reorderLevel,
                $minimumStock
            ),
        ];
    }

    private function incomingInventoryQuantity(
        int $spaId,
        int $branchId,
        int $productId
    ): float {
        $items = PurchaseOrderItem::query()
            ->where('product_id', $productId)
            ->whereHas('purchaseOrder', function ($query) use ($spaId,$branchId) {
                $query
                    ->where('spa_id', $spaId)
                    ->where('branch_id', $branchId)
                    ->whereIn('status', [
                        PurchaseOrder::STATUS_ISSUED,
                        PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                    ]);
            })
            ->with([
                'product',
                'goodsReceiptItems',
                'purchaseOrder',
            ])
            ->get();

        $incoming = 0;

        foreach ($items as $item) {
            $ordered = $this->roundQuantity($item->quantity);

            $accepted = $this->roundQuantity(
                $item->goodsReceiptItems->sum('accepted_quantity')
            );

            $outstanding = max(
                $this->roundQuantity($ordered - $accepted),
                0
            );

            if ($outstanding <= 0) {
                continue;
            }

            $incoming += $this->convertToInventoryUnit(
                $item,
                $outstanding
            );
        }

        return $this->roundQuantity($incoming);
    }

    private function convertToInventoryUnit(
        PurchaseOrderItem $item,
        float $quantity
    ): float {
        $product = $item->product;

        $usageUnit = trim((string) (
            $product?->usage_unit
            ?: $product?->unit
            ?: ''
        ));

        $purchaseUnit = trim((string) (
            $product?->purchase_unit
            ?: ''
        ));

        $orderUnit = trim((string) (
            $item->unit
            ?: $purchaseUnit
            ?: $usageUnit
        ));

        if (
            $usageUnit !== '' &&
            strcasecmp($orderUnit, $usageUnit) === 0
        ) {
            return $this->roundQuantity($quantity);
        }

        if (
            $purchaseUnit !== '' &&
            strcasecmp($orderUnit, $purchaseUnit) === 0
        ) {
            $factor = $this->roundQuantity(
                $product?->conversion_factor ?: 0
            );

            if ($factor <= 0) {
                throw ValidationException::withMessages([
                    'conversion_factor' =>
                        "Product {$product->name} does not have a valid purchase-to-usage conversion factor.",
                ]);
            }

            return $this->roundQuantity(
                $quantity * $factor
            );
        }

        throw ValidationException::withMessages([
            'unit' =>
                "Purchase Order item unit '{$orderUnit}' does not match the configured units for {$product?->name}.",
        ]);
    }

    private function recommendedInventoryQuantity(
        float $projectedStock,
        float $reorderLevel,
        ?float $minimumStock,
        ?float $maximumStock
    ): ?float {
        $target = $this->recommendationTarget(
            $reorderLevel,
            $minimumStock,
            $maximumStock
        );

        if ($target === null) {
            return null;
        }

        return max(
            $this->roundQuantity($target - $projectedStock),
            0
        );
    }

    private function recommendationTarget(
        float $reorderLevel,
        ?float $minimumStock,
        ?float $maximumStock
    ): ?float {
        if (
            $maximumStock !== null &&
            $maximumStock > $reorderLevel
        ) {
            return $maximumStock;
        }

        if (
            $minimumStock !== null &&
            $minimumStock > $reorderLevel
        ) {
            return $minimumStock;
        }

        return null;
    }

    private function toPurchaseQuantity(
        ?float $inventoryQuantity,
        ?string $purchaseUnit,
        ?string $usageUnit,
        mixed $conversionFactor
    ): ?float {
        if ($inventoryQuantity === null) {
            return null;
        }

        if ($inventoryQuantity <= 0) {
            return 0;
        }

        if (
            blank($purchaseUnit) ||
            strcasecmp(
                trim((string) $purchaseUnit),
                trim((string) $usageUnit)
            ) === 0
        ) {
            return $this->roundQuantity(
                $inventoryQuantity
            );
        }

        $factor = $this->roundQuantity(
            $conversionFactor ?: 0
        );

        if ($factor <= 0) {
            return null;
        }

        return $this->roundQuantity(
            ceil(($inventoryQuantity / $factor) * 1000) / 1000
        );
    }

    private function status(
        float $projectedStock,
        float $reorderLevel,
        ?float $minimumStock
    ): string {
        if ($projectedStock <= 0) {
            return 'out_of_stock';
        }

        if (
            $minimumStock !== null &&
            $projectedStock <= $minimumStock
        ) {
            return 'critical';
        }

        if (
            $reorderLevel > 0 &&
            $projectedStock <= $reorderLevel
        ) {
            return 'reorder';
        }

        return 'healthy';
    }

    private function nullableQuantity(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->roundQuantity($value);
    }

    private function roundQuantity(mixed $value): float
    {
        return round((float) $value, 3);
    }
}
