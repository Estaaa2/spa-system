<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseRequestService
{
    public function create(User $requester,int $branchId,array $items,?string $reason=null): PurchaseRequest
    {
        if (!$requester->spa_id) {
            throw ValidationException::withMessages([
                'branch_id' => 'Your account is not assigned to a spa.',
            ]);
        }

        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => 'At least one product is required.',
            ]);
        }

        $normalizedItems = collect($items)->map(function ($item) {
            return [
                'product_id' => (int) ($item['product_id'] ?? 0),
                'quantity' => (float) ($item['quantity'] ?? 0),
            ];
        })->values();

        if ($normalizedItems->contains(
            fn ($item) => $item['product_id'] <= 0
        )) {
            throw ValidationException::withMessages([
                'items' => 'Each requested item must have a valid product.',
            ]);
        }

        if ($normalizedItems->contains(
            fn ($item) => $item['quantity'] <= 0
        )) {
            throw ValidationException::withMessages([
                'items' => 'Requested quantities must be greater than zero.',
            ]);
        }

        $productIds = $normalizedItems
            ->pluck('product_id')
            ->values();

        if ($productIds->unique()->count() !== $productIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'The same product cannot appear more than once in a purchase request.',
            ]);
        }

        $products = Product::query()
            ->where('spa_id', $requester->spa_id)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        if ($products->count() !== $productIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'One or more selected products do not belong to your spa.',
            ]);
        }

        $inactiveProduct = $products->first(function ($product) {
            return isset($product->is_active) && !$product->is_active;
        });

        if ($inactiveProduct) {
            throw ValidationException::withMessages([
                'items' => "{$inactiveProduct->name} is inactive and cannot be requested.",
            ]);
        }

        return DB::transaction(function () use (
            $requester,
            $branchId,
            $normalizedItems,
            $productIds,
            $products,
            $reason
        ) {
            $branch = Branch::query()
                ->where('id', $branchId)
                ->where('spa_id', $requester->spa_id)
                ->lockForUpdate()
                ->first();

            if (!$branch) {
                throw ValidationException::withMessages([
                    'branch_id' => 'The selected branch does not belong to your spa.',
                ]);
            }

            $openRequest = PurchaseRequest::query()
                ->where('spa_id', $requester->spa_id)
                ->where('branch_id', $branchId)
                ->whereIn('status', [
                    PurchaseRequest::STATUS_PENDING,
                    PurchaseRequest::STATUS_APPROVED,
                ])
                ->whereHas('items', function ($query) use ($productIds) {
                    $query->whereIn('product_id', $productIds);
                })
                ->with([
                    'items' => function ($query) use ($productIds) {
                        $query
                            ->whereIn('product_id', $productIds)
                            ->with('product');
                    },
                ])
                ->orderBy('id')
                ->first();

            if ($openRequest) {
                $item = $openRequest->items->first();

                $productName = $item?->product?->name ?? 'Selected product';

                throw ValidationException::withMessages([
                    'items' =>
                        "{$productName} already has an open purchase request " .
                        "(PR #" .
                        str_pad($openRequest->id, 5, '0', STR_PAD_LEFT) .
                        " - " .
                        ucfirst($openRequest->status) .
                        ").",
                ]);
            }

            $purchaseRequest = PurchaseRequest::create([
                'spa_id' => $requester->spa_id,
                'branch_id' => $branch->id,
                'requested_by' => $requester->id,
                'reason' => $reason,
                'status' => PurchaseRequest::STATUS_PENDING,
            ]);

            foreach ($normalizedItems as $item) {
                $product = $products->get($item['product_id']);

                $purchaseRequest->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit' => $product->purchase_unit
                        ?: $product->usage_unit
                        ?: null,
                ]);
            }

            $this->recordStatusChange(
                $purchaseRequest,
                null,
                PurchaseRequest::STATUS_PENDING,
                $requester,
                $reason
            );

            return $purchaseRequest->fresh([
                'branch',
                'requester',
                'items.product',
                'statusHistory',
            ]);
        });
    }

    public function approve(PurchaseRequest $purchaseRequest,User $reviewer,?string $reason=null): PurchaseRequest
    {
        return DB::transaction(function () use (
            $purchaseRequest,
            $reviewer,
            $reason
        ) {
            $purchaseRequest = PurchaseRequest::query()
                ->lockForUpdate()
                ->findOrFail($purchaseRequest->id);

            $this->ensureSameSpa($purchaseRequest,$reviewer);
            $this->ensurePending($purchaseRequest);

            $fromStatus = $purchaseRequest->status;

            $purchaseRequest->update([
                'status' => PurchaseRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'review_reason' => $reason,
                'reviewed_at' => now(),
            ]);

            $this->recordStatusChange(
                $purchaseRequest,
                $fromStatus,
                PurchaseRequest::STATUS_APPROVED,
                $reviewer,
                $reason
            );

            return $purchaseRequest->fresh([
                'branch',
                'requester',
                'reviewer',
                'items.product',
                'statusHistory',
            ]);
        });
    }

    public function reject(PurchaseRequest $purchaseRequest,User $reviewer,string $reason): PurchaseRequest
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'review_reason' => 'A rejection reason is required.',
            ]);
        }

        return DB::transaction(function () use (
            $purchaseRequest,
            $reviewer,
            $reason
        ) {
            $purchaseRequest = PurchaseRequest::query()
                ->lockForUpdate()
                ->findOrFail($purchaseRequest->id);

            $this->ensureSameSpa($purchaseRequest,$reviewer);
            $this->ensurePending($purchaseRequest);

            $fromStatus = $purchaseRequest->status;

            $purchaseRequest->update([
                'status' => PurchaseRequest::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'review_reason' => $reason,
                'reviewed_at' => now(),
            ]);

            $this->recordStatusChange(
                $purchaseRequest,
                $fromStatus,
                PurchaseRequest::STATUS_REJECTED,
                $reviewer,
                $reason
            );

            return $purchaseRequest->fresh([
                'branch',
                'requester',
                'reviewer',
                'items.product',
                'statusHistory',
            ]);
        });
    }

    public function markConverted(PurchaseRequest $purchaseRequest,User $user): PurchaseRequest
    {
        return DB::transaction(function () use ($purchaseRequest,$user) {
            $purchaseRequest = PurchaseRequest::query()
                ->lockForUpdate()
                ->findOrFail($purchaseRequest->id);

            $this->ensureSameSpa($purchaseRequest,$user);

            if ($purchaseRequest->status !== PurchaseRequest::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'status' => 'Only an approved purchase request can be converted.',
                ]);
            }

            $fromStatus = $purchaseRequest->status;

            $purchaseRequest->update([
                'status' => PurchaseRequest::STATUS_CONVERTED,
                'converted_at' => now(),
            ]);

            $this->recordStatusChange(
                $purchaseRequest,
                $fromStatus,
                PurchaseRequest::STATUS_CONVERTED,
                $user,
                'Converted to purchase order.'
            );

            return $purchaseRequest->fresh([
                'branch',
                'requester',
                'reviewer',
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

    private function ensurePending(PurchaseRequest $purchaseRequest): void
    {
        if ($purchaseRequest->status !== PurchaseRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only a pending purchase request can be reviewed.',
            ]);
        }
    }

    private function recordStatusChange(
        PurchaseRequest $purchaseRequest,
        ?string $fromStatus,
        string $toStatus,
        User $user,
        ?string $reason=null
    ): void {
        PurchaseRequestStatusHistory::create([
            'purchase_request_id' => $purchaseRequest->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $user->id,
            'reason' => $reason,
        ]);
    }
}
