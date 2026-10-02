<?php

namespace App\Services;

use App\Models\BranchProductStock;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductLog;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceiptService
{
    public function receive(
        PurchaseOrder $purchaseOrder,
        User $user,
        array $items,
        ?string $deliveryReference = null,
        ?string $receivedAt = null,
        ?string $notes = null
    ): GoodsReceipt {
        return DB::transaction(function () use (
            $purchaseOrder,
            $user,
            $items,
            $deliveryReference,
            $receivedAt,
            $notes
        ) {
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->with('items')
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureAccessible($purchaseOrder,$user);
            $this->ensureReceivable($purchaseOrder);

            if (empty($items)) {
                throw ValidationException::withMessages([
                    'items' => 'At least one purchase order item must be received.',
                ]);
            }

            $receivedDateTime = $receivedAt
                ? Carbon::parse($receivedAt)
                : now();

            if ($receivedDateTime->isFuture()) {
                throw ValidationException::withMessages([
                    'received_at' => 'The received date and time cannot be in the future.',
                ]);
            }

            $goodsReceipt = GoodsReceipt::create([
                'spa_id' => $purchaseOrder->spa_id,
                'branch_id' => $purchaseOrder->branch_id,
                'purchase_order_id' => $purchaseOrder->id,
                'received_by' => $user->id,
                'delivery_reference' => $deliveryReference,
                'received_at' => $receivedDateTime,
                'notes' => $notes,
            ]);

            $acceptedThisReceipt = [];

            foreach ($items as $index => $data) {
                $this->receiveItem(
                    $goodsReceipt,
                    $purchaseOrder,
                    $user,
                    $data,
                    $index,
                    $acceptedThisReceipt
                );
            }

            $this->updatePurchaseOrderStatus(
                $purchaseOrder,
                $goodsReceipt,
                $user
            );

            return $goodsReceipt->fresh([
                'purchaseOrder',
                'receiver',
                'items.product',
                'items.purchaseOrderItem',
                'items.productBatch',
            ]);
        });
    }

    private function receiveItem(
        GoodsReceipt $goodsReceipt,
        PurchaseOrder $purchaseOrder,
        User $user,
        array $data,
        int $index,
        array &$acceptedThisReceipt
    ): void {
        $itemKey = "items.{$index}";

        $purchaseOrderItemId = (int) ($data['purchase_order_item_id'] ?? 0);

        $purchaseOrderItem = PurchaseOrderItem::query()
            ->whereKey($purchaseOrderItemId)
            ->where('purchase_order_id', $purchaseOrder->id)
            ->lockForUpdate()
            ->first();

        if (!$purchaseOrderItem) {
            throw ValidationException::withMessages([
                "{$itemKey}.purchase_order_item_id" => 'The selected purchase order item is invalid.',
            ]);
        }

        $receivedQuantity = $this->quantity(
            $data['received_quantity'] ?? null,
            "{$itemKey}.received_quantity",
            'Received quantity'
        );

        $acceptedQuantity = $this->quantity(
            $data['accepted_quantity'] ?? null,
            "{$itemKey}.accepted_quantity",
            'Accepted quantity',
            true
        );

        $rejectedQuantity = $this->quantity(
            $data['rejected_quantity'] ?? 0,
            "{$itemKey}.rejected_quantity",
            'Rejected quantity',
            true
        );

        if ($receivedQuantity <= 0) {
            throw ValidationException::withMessages([
                "{$itemKey}.received_quantity" => 'Received quantity must be greater than zero.',
            ]);
        }

        if (abs($receivedQuantity - ($acceptedQuantity + $rejectedQuantity)) > 0.0005) {
            throw ValidationException::withMessages([
                "{$itemKey}.received_quantity" => 'Received quantity must equal accepted quantity plus rejected quantity.',
            ]);
        }

        if ($rejectedQuantity > 0 && blank($data['rejection_reason'] ?? null)) {
            throw ValidationException::withMessages([
                "{$itemKey}.rejection_reason" => 'A rejection reason is required when rejected quantity is greater than zero.',
            ]);
        }

        $alreadyAccepted = round(
            (float) GoodsReceiptItem::query()
                ->where('purchase_order_item_id', $purchaseOrderItem->id)
                ->sum('accepted_quantity'),
            3
        );

        $acceptedInCurrentReceipt = $acceptedThisReceipt[$purchaseOrderItem->id] ?? 0;

        $orderedQuantity = round((float) $purchaseOrderItem->quantity, 3);

        $outstandingQuantity = round(
            $orderedQuantity - $alreadyAccepted - $acceptedInCurrentReceipt,
            3
        );

        if ($acceptedQuantity - $outstandingQuantity > 0.0005) {
            throw ValidationException::withMessages([
                "{$itemKey}.accepted_quantity" =>
                    'Accepted quantity exceeds the outstanding purchase order quantity of ' .
                    $this->formatQuantity(max($outstandingQuantity, 0)) .
                    ' ' .
                    ($purchaseOrderItem->unit ?: 'unit(s)') .
                    '.',
            ]);
        }

        $product = Product::query()
            ->whereKey($purchaseOrderItem->product_id)
            ->where('spa_id', $purchaseOrder->spa_id)
            ->first();

        if (!$product) {
            throw ValidationException::withMessages([
                "{$itemKey}.product" => 'The product for this purchase order item is unavailable.',
            ]);
        }

        [$conversionFactor, $inventoryUnit] = $this->resolveConversion(
            $purchaseOrderItem,
            $product,
            $itemKey
        );

        $inventoryQuantity = round(
            $acceptedQuantity * $conversionFactor,
            3
        );

        $batch = null;

        if ($acceptedQuantity > 0) {
            $batchNumber = trim((string) ($data['batch_number'] ?? ''));

            if ($batchNumber === '') {
                throw ValidationException::withMessages([
                    "{$itemKey}.batch_number" => 'Batch number is required for accepted stock.',
                ]);
            }

            $batchExists = ProductBatch::query()
                ->where('spa_id', $purchaseOrder->spa_id)
                ->where('branch_id', $purchaseOrder->branch_id)
                ->where('product_id', $product->id)
                ->where('batch_number', $batchNumber)
                ->exists();

            if ($batchExists) {
                throw ValidationException::withMessages([
                    "{$itemKey}.batch_number" => 'This batch number already exists for the product in this branch.',
                ]);
            }

            $manufacturedAt = filled($data['manufactured_at'] ?? null)
                ? Carbon::parse($data['manufactured_at'])->startOfDay()
                : null;

            $expirationDate = filled($data['expiration_date'] ?? null)
                ? Carbon::parse($data['expiration_date'])->startOfDay()
                : null;

            if (
                $manufacturedAt &&
                $expirationDate &&
                $expirationDate->lt($manufacturedAt)
            ) {
                throw ValidationException::withMessages([
                    "{$itemKey}.expiration_date" => 'Expiration date cannot be earlier than the manufactured date.',
                ]);
            }

            $stock = BranchProductStock::query()
                ->where('spa_id', $purchaseOrder->spa_id)
                ->where('branch_id', $purchaseOrder->branch_id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                throw ValidationException::withMessages([
                    "{$itemKey}.product" => 'This product is not configured in the receiving branch inventory.',
                ]);
            }

            $balanceBefore = round((float) $stock->on_hand_quantity, 3);
            $balanceAfter = round($balanceBefore + $inventoryQuantity, 3);

            $batch = ProductBatch::create([
                'spa_id' => $purchaseOrder->spa_id,
                'branch_id' => $purchaseOrder->branch_id,
                'product_id' => $product->id,
                'batch_number' => $batchNumber,
                'received_quantity' => $inventoryQuantity,
                'remaining_quantity' => $inventoryQuantity,
                'unit_cost' => $data['unit_cost'] ?? null,
                'manufactured_at' => $manufacturedAt,
                'expiration_date' => $expirationDate,
                'received_at' => $goodsReceipt->received_at,
            ]);

            $stock->update([
                'on_hand_quantity' => $balanceAfter,
            ]);

            $receiptItem = $goodsReceipt->items()->create([
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'product_id' => $product->id,
                'product_batch_id' => $batch->id,
                'received_quantity' => $receivedQuantity,
                'accepted_quantity' => $acceptedQuantity,
                'rejected_quantity' => $rejectedQuantity,
                'unit' => $purchaseOrderItem->unit,
                'conversion_factor' => $conversionFactor,
                'inventory_quantity' => $inventoryQuantity,
                'batch_number' => $batchNumber,
                'manufactured_at' => $manufacturedAt,
                'expiration_date' => $expirationDate,
                'rejection_reason' => $data['rejection_reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            StockMovement::create([
                'spa_id' => $purchaseOrder->spa_id,
                'branch_id' => $purchaseOrder->branch_id,
                'product_id' => $product->id,
                'product_batch_id' => $batch->id,
                'user_id' => $user->id,
                'booking_id' => null,
                'movement_type' => 'purchase_receipt',
                'direction' => 'in',
                'quantity' => $inventoryQuantity,
                'unit' => $inventoryUnit,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => 'goods_receipt_item',
                'reference_id' => $receiptItem->id,
                'notes' => $data['notes']
                    ?? 'Goods received through GRN #' .
                        str_pad($goodsReceipt->id, 5, '0', STR_PAD_LEFT) .
                        ' for PO #' .
                        str_pad($purchaseOrder->id, 5, '0', STR_PAD_LEFT) .
                        '.',
                'occurred_at' => $goodsReceipt->received_at,
            ]);

            ProductLog::create([
                'spa_id' => $purchaseOrder->spa_id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'description' =>
                    "{$product->name} received " .
                    $this->formatQuantity($inventoryQuantity) .
                    " {$inventoryUnit} through GRN #" .
                    str_pad($goodsReceipt->id, 5, '0', STR_PAD_LEFT),
                'logged_at' => $goodsReceipt->received_at,
            ]);
        } else {
            $goodsReceipt->items()->create([
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'product_id' => $product->id,
                'product_batch_id' => null,
                'received_quantity' => $receivedQuantity,
                'accepted_quantity' => 0,
                'rejected_quantity' => $rejectedQuantity,
                'unit' => $purchaseOrderItem->unit,
                'conversion_factor' => $conversionFactor,
                'inventory_quantity' => 0,
                'batch_number' => null,
                'manufactured_at' => null,
                'expiration_date' => null,
                'rejection_reason' => $data['rejection_reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        }

        $acceptedThisReceipt[$purchaseOrderItem->id] =
            round($acceptedInCurrentReceipt + $acceptedQuantity, 3);
    }

    private function updatePurchaseOrderStatus(
        PurchaseOrder $purchaseOrder,
        GoodsReceipt $goodsReceipt,
        User $user
    ): void {
        $ordered = 0;
        $accepted = 0;

        foreach ($purchaseOrder->items as $purchaseOrderItem) {
            $ordered += (float) $purchaseOrderItem->quantity;

            $accepted += (float) GoodsReceiptItem::query()
                ->where('purchase_order_item_id', $purchaseOrderItem->id)
                ->sum('accepted_quantity');
        }

        $ordered = round($ordered, 3);
        $accepted = round($accepted, 3);

        if ($accepted <= 0) {
            return;
        }

        $newStatus = $accepted + 0.0005 >= $ordered
            ? PurchaseOrder::STATUS_RECEIVED
            : PurchaseOrder::STATUS_PARTIALLY_RECEIVED;

        if ($purchaseOrder->status === $newStatus) {
            return;
        }

        $fromStatus = $purchaseOrder->status;

        $purchaseOrder->update([
            'status' => $newStatus,
        ]);

        $purchaseOrder->statusHistory()->create([
            'from_status' => $fromStatus,
            'to_status' => $newStatus,
            'changed_by' => $user->id,
            'reason' =>
                'Goods receipt #' .
                str_pad($goodsReceipt->id, 5, '0', STR_PAD_LEFT) .
                ' posted.',
        ]);
    }

    private function ensureAccessible(PurchaseOrder $purchaseOrder,User $user): void
    {
        if (
            (int) $purchaseOrder->spa_id !== (int) $user->spa_id ||
            (int) $purchaseOrder->branch_id !== (int) $user->currentBranchId()
        ) {
            throw ValidationException::withMessages([
                'purchase_order' => 'The purchase order is not accessible from the current branch.',
            ]);
        }
    }

    private function ensureReceivable(PurchaseOrder $purchaseOrder): void
    {
        if (!in_array(
            $purchaseOrder->status,
            [
                PurchaseOrder::STATUS_ISSUED,
                PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            ],
            true
        )) {
            throw ValidationException::withMessages([
                'purchase_order' => 'Only an issued or partially received purchase order can receive goods.',
            ]);
        }
    }

    private function resolveConversion(
        PurchaseOrderItem $purchaseOrderItem,
        Product $product,
        string $itemKey
    ): array {
        $usageUnit = trim((string) (
            $product->usage_unit
            ?: $product->unit
            ?: 'pcs'
        ));

        $purchaseUnit = trim((string) ($product->purchase_unit ?? ''));

        $orderUnit = trim((string) (
            $purchaseOrderItem->unit
            ?: $purchaseUnit
            ?: $usageUnit
        ));

        if (strcasecmp($orderUnit, $usageUnit) === 0) {
            return [1, $usageUnit];
        }

        if (
            $purchaseUnit !== '' &&
            strcasecmp($orderUnit, $purchaseUnit) === 0
        ) {
            $conversionFactor = round(
                (float) ($product->conversion_factor ?? 0),
                3
            );

            if ($conversionFactor <= 0) {
                throw ValidationException::withMessages([
                    "{$itemKey}.conversion_factor" => 'The product does not have a valid purchase-to-usage conversion factor.',
                ]);
            }

            return [$conversionFactor, $usageUnit];
        }

        throw ValidationException::withMessages([
            "{$itemKey}.unit" =>
                "Purchase order unit '{$orderUnit}' does not match the product purchase or usage unit.",
        ]);
    }

    private function quantity(
        mixed $value,
        string $key,
        string $label,
        bool $allowZero = false
    ): float {
        if (!is_numeric($value)) {
            throw ValidationException::withMessages([
                $key => "{$label} must be numeric.",
            ]);
        }

        $quantity = round((float) $value, 3);

        if ($allowZero ? $quantity < 0 : $quantity <= 0) {
            throw ValidationException::withMessages([
                $key => $allowZero
                    ? "{$label} cannot be negative."
                    : "{$label} must be greater than zero.",
            ]);
        }

        return $quantity;
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(
            rtrim(number_format($quantity, 3, '.', ''), '0'),
            '.'
        );
    }
}
