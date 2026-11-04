<?php

namespace App\Services;

use App\Models\VendorBill;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ThreeWayMatchService
{
    private const QUANTITY_TOLERANCE = 0.0005;
    private const PRICE_TOLERANCE = 0.005;

    public function match(VendorBill $vendorBill,User $user): VendorBill
    {
        return DB::transaction(function () use ($vendorBill,$user) {
            $vendorBill = VendorBill::query()
                ->lockForUpdate()
                ->findOrFail($vendorBill->id);

            $this->ensureAccessible($vendorBill,$user);
            $this->ensureMatchable($vendorBill);

            $vendorBill->load([
                'purchaseOrder.items.goodsReceiptItems.goodsReceipt',
                'items.product',
            ]);

            $purchaseOrder = $vendorBill->purchaseOrder;

            if (!$purchaseOrder) {
                throw ValidationException::withMessages([
                    'vendor_bill' => 'The vendor bill does not have a valid purchase order.',
                ]);
            }

            if (
                (int) $purchaseOrder->spa_id !== (int) $vendorBill->spa_id ||
                (int) $purchaseOrder->branch_id !== (int) $vendorBill->branch_id ||
                (int) $purchaseOrder->supplier_id !== (int) $vendorBill->supplier_id
            ) {
                throw ValidationException::withMessages([
                    'vendor_bill' => 'The vendor bill and purchase order do not belong to the same supplier and branch.',
                ]);
            }

            if ($vendorBill->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'vendor_bill' => 'The vendor bill has no invoice items to match.',
                ]);
            }

            $lines = [];
            $hasDiscrepancy = false;

            foreach ($vendorBill->items as $billItem) {
                $purchaseOrderItem = $purchaseOrder->items
                    ->firstWhere('id', $billItem->purchase_order_item_id);

                if (!$purchaseOrderItem) {
                    $hasDiscrepancy = true;

                    $lines[] = [
                        'vendor_bill_item_id' => $billItem->id,
                        'purchase_order_item_id' => $billItem->purchase_order_item_id,
                        'product_id' => $billItem->product_id,
                        'product' => $billItem->product?->name ?? 'Unknown Product',
                        'matched' => false,
                        'issues' => [
                            [
                                'type' => 'missing_po_item',
                                'message' => 'The billed item does not exist on the linked purchase order.',
                            ],
                        ],
                    ];

                    continue;
                }

                $orderedQuantity = $this->quantity($purchaseOrderItem->quantity);
                $billedQuantity = $this->quantity($billItem->billed_quantity);

                $acceptedQuantity = $this->quantity(
                    $purchaseOrderItem->goodsReceiptItems
                        ->filter(function ($receiptItem) use ($vendorBill,$purchaseOrder) {
                            $goodsReceipt = $receiptItem->goodsReceipt;

                            return $goodsReceipt &&
                                (int) $goodsReceipt->spa_id === (int) $vendorBill->spa_id &&
                                (int) $goodsReceipt->branch_id === (int) $vendorBill->branch_id &&
                                (int) $goodsReceipt->purchase_order_id === (int) $purchaseOrder->id;
                        })
                        ->sum(fn($receiptItem) => (float) $receiptItem->accepted_quantity)
                );

                $rejectedQuantity = $this->quantity(
                    $purchaseOrderItem->goodsReceiptItems
                        ->filter(function ($receiptItem) use ($vendorBill,$purchaseOrder) {
                            $goodsReceipt = $receiptItem->goodsReceipt;

                            return $goodsReceipt &&
                                (int) $goodsReceipt->spa_id === (int) $vendorBill->spa_id &&
                                (int) $goodsReceipt->branch_id === (int) $vendorBill->branch_id &&
                                (int) $goodsReceipt->purchase_order_id === (int) $purchaseOrder->id;
                        })
                        ->sum(fn($receiptItem) => (float) $receiptItem->rejected_quantity)
                );

                $poUnitCost = $purchaseOrderItem->unit_cost !== null
                    ? $this->money($purchaseOrderItem->unit_cost)
                    : null;

                $billedUnitCost = $this->money($billItem->unit_cost);

                $issues = [];

                if ($acceptedQuantity <= self::QUANTITY_TOLERANCE) {
                    $issues[] = [
                        'type' => 'missing_grn',
                        'message' => 'No accepted goods have been recorded for this purchase order item.',
                    ];
                }

                $exceedsOrder =
                    $billedQuantity >
                    $orderedQuantity + self::QUANTITY_TOLERANCE;

                $exceedsAccepted =
                    $billedQuantity >
                    $acceptedQuantity + self::QUANTITY_TOLERANCE;

                if ($exceedsOrder || $exceedsAccepted) {
                    $billedUnit = $this->formatUnit(
                        $billedQuantity,
                        $billItem->unit
                    );

                    $orderedUnit = $this->formatUnit(
                        $orderedQuantity,
                        $purchaseOrderItem->unit
                    );

                    $acceptedUnit = $this->formatUnit(
                        $acceptedQuantity,
                        $purchaseOrderItem->unit
                    );

                    if ($exceedsOrder && $exceedsAccepted) {
                        $unsupportedQuantity = $this->quantity(
                            $billedQuantity -
                            min($orderedQuantity,$acceptedQuantity)
                        );

                        $message =
                            'Supplier invoice contains ' .
                            $this->formatQuantity($billedQuantity) .
                            ' ' .
                            $billedUnit .
                            ', while the purchase order contains ' .
                            $this->formatQuantity($orderedQuantity) .
                            ' ' .
                            $orderedUnit .
                            ' and Goods Receipt accepted ' .
                            $this->formatQuantity($acceptedQuantity) .
                            ' ' .
                            $acceptedUnit .
                            '. ' .
                            $this->formatQuantity($unsupportedQuantity) .
                            ' ' .
                            $this->formatUnit(
                                $unsupportedQuantity,
                                $purchaseOrderItem->unit
                            ) .
                            ' cannot be matched for payment.';
                    } elseif ($exceedsAccepted) {
                        $unsupportedQuantity = $this->quantity(
                            $billedQuantity - $acceptedQuantity
                        );

                        $message =
                            'Supplier invoice contains ' .
                            $this->formatQuantity($billedQuantity) .
                            ' ' .
                            $billedUnit .
                            ' against ' .
                            $this->formatQuantity($orderedQuantity) .
                            ' ' .
                            $orderedUnit .
                            ' ordered, but Goods Receipt accepted only ' .
                            $this->formatQuantity($acceptedQuantity) .
                            ' ' .
                            $acceptedUnit .
                            (
                                $rejectedQuantity > self::QUANTITY_TOLERANCE
                                    ? ' and rejected ' .
                                        $this->formatQuantity($rejectedQuantity) .
                                        ' ' .
                                        $this->formatUnit(
                                            $rejectedQuantity,
                                            $purchaseOrderItem->unit
                                        )
                                    : ''
                            ) .
                            '. ' .
                            $this->formatQuantity($unsupportedQuantity) .
                            ' ' .
                            $this->formatUnit(
                                $unsupportedQuantity,
                                $purchaseOrderItem->unit
                            ) .
                            ' cannot be matched for payment.';
                    } else {
                        $unsupportedQuantity = $this->quantity(
                            $billedQuantity - $orderedQuantity
                        );

                        $message =
                            'Supplier invoice contains ' .
                            $this->formatQuantity($billedQuantity) .
                            ' ' .
                            $billedUnit .
                            ', while the purchase order only authorizes ' .
                            $this->formatQuantity($orderedQuantity) .
                            ' ' .
                            $orderedUnit .
                            '. The invoice exceeds the order by ' .
                            $this->formatQuantity($unsupportedQuantity) .
                            ' ' .
                            $this->formatUnit(
                                $unsupportedQuantity,
                                $purchaseOrderItem->unit
                            ) .
                            '.';
                    }

                    $issues[] = [
                        'type' => 'quantity_mismatch',
                        'message' => $message,
                    ];
                }

                if (
                    $acceptedQuantity >
                    $orderedQuantity + self::QUANTITY_TOLERANCE
                ) {
                    $issues[] = [
                        'type' => 'received_exceeds_order',
                        'message' =>
                            'Accepted Goods Receipt quantity exceeds the original purchase order quantity.',
                    ];
                }

                if ($poUnitCost === null) {
                    $issues[] = [
                        'type' => 'missing_po_unit_cost',
                        'message' => 'The purchase order item does not contain a historical unit cost.',
                    ];
                } elseif (
                    abs($billedUnitCost - $poUnitCost) >
                    self::PRICE_TOLERANCE
                ) {
                    $issues[] = [
                        'type' => 'price_mismatch',
                        'message' =>
                            'The supplier billed ₱' .
                            number_format($billedUnitCost, 2) .
                            ' per ' .
                            $billItem->unit .
                            ', while the purchase order price is ₱' .
                            number_format($poUnitCost, 2) .
                            '.',
                    ];
                }

                $lineMatched = empty($issues);

                if (!$lineMatched) {
                    $hasDiscrepancy = true;
                }

                $lines[] = [
                    'vendor_bill_item_id' => $billItem->id,
                    'purchase_order_item_id' => $purchaseOrderItem->id,
                    'product_id' => $billItem->product_id,
                    'product' => $billItem->product?->name ?? 'Unknown Product',
                    'unit' => $billItem->unit,

                    'purchase_order' => [
                        'quantity' => $orderedQuantity,
                        'unit_cost' => $poUnitCost,
                    ],

                    'goods_receipt' => [
                        'accepted_quantity' => $acceptedQuantity,
                        'rejected_quantity' => $rejectedQuantity,
                    ],

                    'vendor_bill' => [
                        'quantity' => $billedQuantity,
                        'unit_cost' => $billedUnitCost,
                        'line_total' => $this->money($billItem->line_total),
                    ],

                    'matched' => $lineMatched,
                    'issues' => $issues,
                ];
            }

            $status = $hasDiscrepancy
                ? VendorBill::STATUS_DISCREPANCY
                : VendorBill::STATUS_MATCHED;

            $matchDetails = [
                'matched' => !$hasDiscrepancy,
                'checked_at' => now()->toIso8601String(),
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id' => $vendorBill->supplier_id,
                'invoice_number' => $vendorBill->invoice_number,
                'summary' => [
                    'total_lines' => count($lines),
                    'matched_lines' => collect($lines)
                        ->where('matched', true)
                        ->count(),
                    'discrepancy_lines' => collect($lines)
                        ->where('matched', false)
                        ->count(),
                ],
                'lines' => $lines,
            ];

            $vendorBill->update([
                'status' => $status,
                'match_details' => $matchDetails,
                'matched_at' => now(),
            ]);

            return $vendorBill->fresh([
                'supplier',
                'purchaseOrder',
                'items.product',
            ]);
        });
    }

    private function ensureAccessible(VendorBill $vendorBill,User $user): void
    {
        if (
            !$user->spa_id ||
            (int) $vendorBill->spa_id !== (int) $user->spa_id ||
            (int) $vendorBill->branch_id !== (int) $user->currentBranchId()
        ) {
            throw ValidationException::withMessages([
                'vendor_bill' => 'This vendor bill does not belong to your current branch.',
            ]);
        }
    }

    private function ensureMatchable(VendorBill $vendorBill): void
    {
        if (in_array($vendorBill->status, [
            VendorBill::STATUS_APPROVED,
            VendorBill::STATUS_PAID,
            VendorBill::STATUS_CANCELLED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'This vendor bill can no longer be changed by 3-way matching.',
            ]);
        }
    }

    private function quantity($value): float
    {
        return round((float) $value, 3);
    }

    private function money($value): float
    {
        return round((float) $value, 2);
    }

    private function formatQuantity(float $value): string
    {
        return rtrim(
            rtrim(number_format($value, 3, '.', ''), '0'),
            '.'
        );
    }
    private function formatUnit(float $quantity,string $unit): string
    {
        $unit = trim($unit);
        $normalized = strtolower($unit);

        if (
            abs($quantity - 1) <= self::QUANTITY_TOLERANCE ||
            in_array($normalized, [
                'ml',
                'l',
                'g',
                'kg',
                'oz',
                'pcs',
            ], true) ||
            str_ends_with($normalized, 's')
        ) {
            return $unit;
        }

        return $unit . 's';
    }
}
