<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingConsumption;
use App\Models\BookingConsumptionItem;
use App\Models\BranchProductStock;
use App\Models\Package;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingConsumptionService
{
    public function consume(Booking $booking,?User $user = null): ?BookingConsumption
    {
        if (
            !str_starts_with((string) $booking->treatment, 'treatment_')
            && !str_starts_with((string) $booking->treatment, 'package_')
        ) {
            return null;
        }

        return DB::transaction(function () use ($booking, $user) {
            $lockedBooking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = BookingConsumption::query()
                ->where('booking_id', $lockedBooking->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            if (str_starts_with((string) $lockedBooking->treatment, 'treatment_')) {
                return $this->consumeTreatment($lockedBooking, $user);
            }

            return $this->consumePackage($lockedBooking, $user);
        });
    }

    private function consumeTreatment(Booking $booking,?User $user): BookingConsumption
    {
        $treatmentId = (int) str_replace(
            'treatment_',
            '',
            $booking->treatment
        );

        $treatment = Treatment::withoutGlobalScopes()
            ->whereKey($treatmentId)
            ->where('spa_id', $booking->spa_id)
            ->where('branch_id', $booking->branch_id)
            ->with([
                'recipeItems' => fn ($query) => $query->orderBy('product_id'),
                'recipeItems.product',
            ])
            ->firstOrFail();

        $consumption = BookingConsumption::create([
            'booking_id' => $booking->id,
            'spa_id' => $booking->spa_id,
            'branch_id' => $booking->branch_id,
            'treatment_id' => $treatment->id,
            'package_id' => null,
            'processed_by' => $user?->id,
            'service_reference' => $booking->treatment,
            'service_name' => $treatment->name,
            'consumed_at' => now(),
        ]);

        foreach ($treatment->recipeItems as $recipeItem) {
            $this->consumeRecipeItem(
                $booking,
                $consumption,
                $treatment,
                $recipeItem->product,
                (float) $recipeItem->quantity,
                $user
            );
        }

        return $consumption->load('items');
    }

    private function consumePackage(Booking $booking,?User $user): BookingConsumption
    {
        $packageId = (int) str_replace(
            'package_',
            '',
            $booking->treatment
        );

        $package = Package::withoutGlobalScopes()
            ->whereKey($packageId)
            ->where('spa_id', $booking->spa_id)
            ->where('branch_id', $booking->branch_id)
            ->with([
                'treatments' => fn ($query) => $query->orderBy('treatments.id'),
                'treatments.recipeItems' => fn ($query) => $query->orderBy('product_id'),
                'treatments.recipeItems.product',
            ])
            ->firstOrFail();

        if ($package->treatments->isEmpty()) {
            throw ValidationException::withMessages([
                'inventory' => "{$package->name} does not contain any treatments.",
            ]);
        }

        $consumption = BookingConsumption::create([
            'booking_id' => $booking->id,
            'spa_id' => $booking->spa_id,
            'branch_id' => $booking->branch_id,
            'treatment_id' => null,
            'package_id' => $package->id,
            'processed_by' => $user?->id,
            'service_reference' => $booking->treatment,
            'service_name' => $package->name,
            'consumed_at' => now(),
        ]);

        foreach ($package->treatments as $treatment) {
            if (
                (int) $treatment->spa_id !== (int) $booking->spa_id
                || (int) $treatment->branch_id !== (int) $booking->branch_id
            ) {
                throw ValidationException::withMessages([
                    'inventory' => "A treatment inside {$package->name} belongs to another spa or branch.",
                ]);
            }

            $packageQuantity = max((int) $treatment->pivot->quantity, 1);

            foreach ($treatment->recipeItems as $recipeItem) {
                $requiredQuantity = (float) $recipeItem->quantity * $packageQuantity;

                $this->consumeRecipeItem(
                    $booking,
                    $consumption,
                    $treatment,
                    $recipeItem->product,
                    $requiredQuantity,
                    $user
                );
            }
        }

        return $consumption->load('items');
    }

    private function consumeRecipeItem(
        Booking $booking,
        BookingConsumption $consumption,
        Treatment $treatment,
        $product,
        float $requiredQuantity,
        ?User $user
    ): void {
        if (!$product || !$product->is_active) {
            throw ValidationException::withMessages([
                'inventory' => "A recipe product for {$treatment->name} is unavailable.",
            ]);
        }

        if ((int) $product->spa_id !== (int) $booking->spa_id) {
            throw ValidationException::withMessages([
                'inventory' => "A recipe product for {$treatment->name} belongs to another spa.",
            ]);
        }

        $stock = BranchProductStock::query()
            ->where('spa_id', $booking->spa_id)
            ->where('branch_id', $booking->branch_id)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->first();

        if (!$stock) {
            throw ValidationException::withMessages([
                'inventory' => "{$product->name} has no inventory record for this branch.",
            ]);
        }

        if ((float) $stock->on_hand_quantity < $requiredQuantity) {
            throw ValidationException::withMessages([
                'inventory' => "Insufficient {$product->name} stock. Required: "
                    . $this->formatQuantity($requiredQuantity)
                    . " {$product->usage_unit}. Available: "
                    . $this->formatQuantity((float) $stock->on_hand_quantity)
                    . " {$product->usage_unit}.",
            ]);
        }

        $batches = ProductBatch::query()
            ->where('spa_id', $booking->spa_id)
            ->where('branch_id', $booking->branch_id)
            ->where('product_id', $product->id)
            ->where('remaining_quantity', '>', 0)
            ->where(function ($query) {
                $query
                    ->whereNull('expiration_date')
                    ->orWhereDate('expiration_date', '>=', today());
            })
            ->orderByRaw('CASE WHEN expiration_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiration_date')
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $usableBatchQuantity = (float) $batches->sum('remaining_quantity');

        if ($usableBatchQuantity < $requiredQuantity) {
            throw ValidationException::withMessages([
                'inventory' => "Not enough usable batch stock for {$product->name}. Required: "
                    . $this->formatQuantity($requiredQuantity)
                    . " {$product->usage_unit}. Usable batch stock: "
                    . $this->formatQuantity($usableBatchQuantity)
                    . " {$product->usage_unit}.",
            ]);
        }

        $remainingToConsume = $requiredQuantity;

        foreach ($batches as $batch) {
            if ($remainingToConsume <= 0) {
                break;
            }

            $batchAvailable = (float) $batch->remaining_quantity;
            $deductQuantity = min($batchAvailable, $remainingToConsume);

            $before = (float) $stock->on_hand_quantity;
            $after = $before - $deductQuantity;

            $batch->update([
                'remaining_quantity' => $batchAvailable - $deductQuantity,
            ]);

            $stock->update([
                'on_hand_quantity' => $after,
            ]);

            StockMovement::create([
                'spa_id' => $booking->spa_id,
                'branch_id' => $booking->branch_id,
                'product_id' => $product->id,
                'product_batch_id' => $batch->id,
                'user_id' => $user?->id,
                'booking_id' => $booking->id,
                'movement_type' => 'service_consumption',
                'direction' => 'out',
                'quantity' => $deductQuantity,
                'unit' => $product->usage_unit ?: $product->unit ?: 'pcs',
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => 'booking_consumption',
                'reference_id' => $consumption->id,
                'notes' => "Consumed for {$treatment->name}.",
                'occurred_at' => now(),
            ]);

            BookingConsumptionItem::create([
                'booking_consumption_id' => $consumption->id,
                'product_id' => $product->id,
                'product_batch_id' => $batch->id,
                'treatment_id' => $treatment->id,
                'treatment_name' => $treatment->name,
                'product_name' => $product->name,
                'batch_number' => $batch->batch_number,
                'quantity' => $deductQuantity,
                'unit' => $product->usage_unit ?: $product->unit ?: 'pcs',
            ]);

            $remainingToConsume -= $deductQuantity;
            $stock->refresh();
        }
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(
            rtrim(number_format($quantity, 3, '.', ''), '0'),
            '.'
        );
    }
}
