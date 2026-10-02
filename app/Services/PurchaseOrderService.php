<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderStatusHistory;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        protected PurchaseRequestService $purchaseRequestService
    ) {
    }

    public function createFromPurchaseRequest(
        PurchaseRequest $purchaseRequest,
        Supplier $supplier,
        User $user,
        ?string $notes=null,
        ?string $expectedDeliveryDate=null
    ): PurchaseOrder {
        return DB::transaction(function () use (
            $purchaseRequest,
            $supplier,
            $user,
            $notes,
            $expectedDeliveryDate
        ) {
            $purchaseRequest = PurchaseRequest::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($purchaseRequest->id);

            $this->ensureSameSpa($purchaseRequest,$user);
            $this->ensureApproved($purchaseRequest);

            $branch = Branch::query()
                ->where('id', $purchaseRequest->branch_id)
                ->where('spa_id', $user->spa_id)
                ->lockForUpdate()
                ->first();

            if (!$branch) {
                throw ValidationException::withMessages([
                    'purchase_request' => 'The purchase request branch does not belong to your spa.',
                ]);
            }

            $supplier = Supplier::query()
                ->where('id', $supplier->id)
                ->where('spa_id', $user->spa_id)
                ->first();

            if (!$supplier) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'The selected supplier does not belong to your spa.',
                ]);
            }

            if ($purchaseRequest->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'purchase_request' => 'The purchase request has no items.',
                ]);
            }

            $existingPurchaseOrder = PurchaseOrder::query()
                ->where('purchase_request_id', $purchaseRequest->id)
                ->first();

            if ($existingPurchaseOrder) {
                throw ValidationException::withMessages([
                    'purchase_request' =>
                        'This purchase request already has a purchase order ' .
                        '(PO #' .
                        str_pad($existingPurchaseOrder->id, 5, '0', STR_PAD_LEFT) .
                        ').',
                ]);
            }

            try {
                $purchaseOrder = PurchaseOrder::create([
                    'spa_id' => $purchaseRequest->spa_id,
                    'branch_id' => $purchaseRequest->branch_id,
                    'purchase_request_id' => $purchaseRequest->id,
                    'supplier_id' => $supplier->id,
                    'created_by' => $user->id,
                    'status' => PurchaseOrder::STATUS_DRAFT,
                    'notes' => $notes,
                    'expected_delivery_date' => $expectedDeliveryDate,
                ]);

                foreach ($purchaseRequest->items as $item) {
                    $purchaseOrder->items()->create([
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'unit' => $item->unit,
                    ]);
                }

                $this->recordStatusChange(
                    $purchaseOrder,
                    null,
                    PurchaseOrder::STATUS_DRAFT,
                    $user,
                    'Purchase order created from approved purchase request.'
                );

                $this->purchaseRequestService->markConverted(
                    $purchaseRequest,
                    $user
                );

                return $purchaseOrder->fresh([
                    'purchaseRequest',
                    'supplier',
                    'creator',
                    'items.product',
                    'statusHistory',
                ]);
            } catch (QueryException $exception) {
                if ($exception->getCode() === '23000') {
                    throw ValidationException::withMessages([
                        'purchase_request' => 'This purchase request already has a purchase order.',
                    ]);
                }

                throw $exception;
            }
        });
    }

    public function issue(PurchaseOrder $purchaseOrder,User $user,?string $reason=null): PurchaseOrder
    {
        return DB::transaction(function () use (
            $purchaseOrder,
            $user,
            $reason
        ) {
            $purchaseOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            $this->ensurePurchaseOrderSameSpa($purchaseOrder,$user);

            if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Only a draft purchase order can be issued.',
                ]);
            }

            $fromStatus = $purchaseOrder->status;

            $purchaseOrder->update([
                'status' => PurchaseOrder::STATUS_ISSUED,
                'issued_at' => now(),
            ]);

            $this->recordStatusChange(
                $purchaseOrder,
                $fromStatus,
                PurchaseOrder::STATUS_ISSUED,
                $user,
                $reason
            );

            return $purchaseOrder->fresh([
                'purchaseRequest',
                'supplier',
                'creator',
                'items.product',
                'statusHistory',
            ]);
        });
    }

    public function cancel(PurchaseOrder $purchaseOrder,User $user,string $reason): PurchaseOrder
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A cancellation reason is required.',
            ]);
        }

        return DB::transaction(function () use (
            $purchaseOrder,
            $user,
            $reason
        ) {
            $purchaseOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            $this->ensurePurchaseOrderSameSpa($purchaseOrder,$user);

            if (!in_array($purchaseOrder->status, [
                PurchaseOrder::STATUS_DRAFT,
                PurchaseOrder::STATUS_ISSUED,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only a draft or issued purchase order can be cancelled.',
                ]);
            }

            $fromStatus = $purchaseOrder->status;

            $purchaseOrder->update([
                'status' => PurchaseOrder::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            $this->recordStatusChange(
                $purchaseOrder,
                $fromStatus,
                PurchaseOrder::STATUS_CANCELLED,
                $user,
                $reason
            );

            return $purchaseOrder->fresh([
                'purchaseRequest',
                'supplier',
                'creator',
                'items.product',
                'statusHistory',
            ]);
        });
    }

    private function ensureSameSpa(PurchaseRequest $purchaseRequest,User $user): void
    {
        if (
            !$user->spa_id ||
            (int) $purchaseRequest->spa_id !== (int) $user->spa_id
        ) {
            throw ValidationException::withMessages([
                'purchase_request' => 'This purchase request does not belong to your spa.',
            ]);
        }
    }

    private function ensureApproved(PurchaseRequest $purchaseRequest): void
    {
        if ($purchaseRequest->status !== PurchaseRequest::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'purchase_request' => 'Only an approved purchase request can be converted to a purchase order.',
            ]);
        }
    }

    private function ensurePurchaseOrderSameSpa(PurchaseOrder $purchaseOrder,User $user): void
    {
        if (
            !$user->spa_id ||
            (int) $purchaseOrder->spa_id !== (int) $user->spa_id
        ) {
            throw ValidationException::withMessages([
                'purchase_order' => 'This purchase order does not belong to your spa.',
            ]);
        }
    }

    private function recordStatusChange(
        PurchaseOrder $purchaseOrder,
        ?string $fromStatus,
        string $toStatus,
        User $user,
        ?string $reason=null
    ): void {
        PurchaseOrderStatusHistory::create([
            'purchase_order_id' => $purchaseOrder->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $user->id,
            'reason' => $reason,
        ]);
    }
}
