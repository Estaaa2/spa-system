<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingConsumption;
use App\Models\BookingConsumptionItem;
use App\Models\BranchProductStock;
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
        if (!str_starts_with((string) $booking->treatment, 'treatment_')) {
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

            $treatmentId = (int) str_replace(
                'treatment_',
                '',
                $lockedBooking->treatment
            );

            $treatment = Treatment::withoutGlobalScopes()
                ->whereKey($treatmentId)
                ->where('spa_id', $lockedBooking->spa_id)
                ->where('branch_id', $lockedBooking->branch_id)
                ->with([
                    'recipeItems' => fn ($query) => $query->orderBy('product_id'),
                    'recipeItems.product',
                ])
                ->firstOrFail();

            $recipeItems = $treatment->recipeItems;

            $consumption = BookingConsumption::create([
                'booking_id' => $lockedBooking->id,
                'spa_id' => $lockedBooking->spa_id,
                'branch_id' => $lockedBooking->branch_id,
                'treatment_id' => $treatment->id,
                'processed_by' => $user?->id,
                'service_reference' => $lockedBooking->treatment,
                'service_name' => $treatment->name,
                'consumed_at' => now(),
            ]);

            foreach ($recipeItems as $recipeItem) {
                $product = $recipeItem->product;

                if (!$product || !$product->is_active) {
                    throw ValidationException::withMessages([
                        'inventory' => "A recipe product for {$treatment->name} is unavailable.",
                    ]);
                }

                if ((int) $product->spa_id !== (int) $lockedBooking->spa_id) {
                    throw ValidationException::withMessages([
                        'inventory' => "A recipe product for {$treatment->name} belongs to another spa.",
                    ]);
                }

                $requiredQuantity = (float) $recipeItem->quantity;

                $stock = BranchProductStock::query()
                    ->where('spa_id', $lockedBooking->spa_id)
                    ->where('branch_id', $lockedBooking->branch_id)
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
                    ->where('spa_id', $lockedBooking->spa_id)
                    ->where('branch_id', $lockedBooking->branch_id)
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
                        'spa_id' => $lockedBooking->spa_id,
                        'branch_id' => $lockedBooking->branch_id,
                        'product_id' => $product->id,
                        'product_batch_id' => $batch->id,
                        'user_id' => $user?->id,
                        'booking_id' => $lockedBooking->id,
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
                        'product_name' => $product->name,
                        'batch_number' => $batch->batch_number,
                        'quantity' => $deductQuantity,
                        'unit' => $product->usage_unit ?: $product->unit ?: 'pcs',
                    ]);

                    $remainingToConsume -= $deductQuantity;

                    $stock->refresh();
                }
            }

            return $consumption->load('items');
        });
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(
            rtrim(number_format($quantity, 3, '.', ''), '0'),
            '.'
        );
    }
}
