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

/**
 * Deducts a booking's recipe stock when staff check it out.
 *
 * Booking lifecycle fix, Phase 1 (D7, Q6-A, Q7-A): this never blocks a
 * checkout. It consumes whatever usable (non-expired) stock exists, oldest
 * expiry first, and records the rest as a shortfall:
 *
 *   - not enough usable stock        → consume what's there, a "short" item for the rest
 *   - no stock record for the branch → a "short" item for the full amount
 *   - product inactive               → a "short" item for the full amount
 *   - deleted service, empty package, product from another spa
 *                                    → a line in shortfall_notes (no product row to point at)
 *
 * Stock never goes below zero (InventoryStockService and its reconciliation
 * rule stay intact). Runs once per booking: a second call returns the first
 * consumption unchanged.
 */
class BookingConsumptionService
{
    private const TOLERANCE = 0.0005;

    public function consume(Booking $booking, ?User $user = null): ?BookingConsumption
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

            $consumption = str_starts_with((string) $lockedBooking->treatment, 'treatment_')
                ? $this->consumeTreatment($lockedBooking, $user)
                : $this->consumePackage($lockedBooking, $user);

            return $consumption->load('items');
        });
    }

    private function consumeTreatment(Booking $booking, ?User $user): BookingConsumption
    {
        $treatmentId = (int) str_replace('treatment_', '', $booking->treatment);

        $treatment = Treatment::withoutGlobalScopes()
            ->whereKey($treatmentId)
            ->where('spa_id', $booking->spa_id)
            ->where('branch_id', $booking->branch_id)
            ->with([
                'recipeItems' => fn ($query) => $query->orderBy('product_id'),
                'recipeItems.product',
            ])
            ->first();

        $consumption = $this->startConsumption($booking, $user, [
            'treatment_id' => $treatment?->id,
            'service_name' => $treatment?->name ?? "Unknown treatment (#{$treatmentId})",
        ]);

        if (!$treatment) {
            $this->addNote($consumption, "The treatment for this booking no longer exists in this branch, so no stock was deducted.");

            return $consumption;
        }

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

        return $consumption;
    }

    private function consumePackage(Booking $booking, ?User $user): BookingConsumption
    {
        $packageId = (int) str_replace('package_', '', $booking->treatment);

        $package = Package::withoutGlobalScopes()
            ->whereKey($packageId)
            ->where('spa_id', $booking->spa_id)
            ->where('branch_id', $booking->branch_id)
            ->with([
                'treatments' => fn ($query) => $query->orderBy('treatments.id'),
                'treatments.recipeItems' => fn ($query) => $query->orderBy('product_id'),
                'treatments.recipeItems.product',
            ])
            ->first();

        $consumption = $this->startConsumption($booking, $user, [
            'package_id' => $package?->id,
            'service_name' => $package?->name ?? "Unknown package (#{$packageId})",
        ]);

        if (!$package) {
            $this->addNote($consumption, "The package for this booking no longer exists in this branch, so no stock was deducted.");

            return $consumption;
        }

        if ($package->treatments->isEmpty()) {
            $this->addNote($consumption, "{$package->name} does not contain any treatments, so no stock was deducted.");

            return $consumption;
        }

        foreach ($package->treatments as $treatment) {
            if (
                (int) $treatment->spa_id !== (int) $booking->spa_id
                || (int) $treatment->branch_id !== (int) $booking->branch_id
            ) {
                $this->addNote($consumption, "{$treatment->name} inside {$package->name} belongs to another spa or branch and was skipped.");

                continue;
            }

            $packageQuantity = max((int) $treatment->pivot->quantity, 1);

            foreach ($treatment->recipeItems as $recipeItem) {
                $this->consumeRecipeItem(
                    $booking,
                    $consumption,
                    $treatment,
                    $recipeItem->product,
                    (float) $recipeItem->quantity * $packageQuantity,
                    $user
                );
            }
        }

        return $consumption;
    }

    private function startConsumption(Booking $booking, ?User $user, array $attributes): BookingConsumption
    {
        return BookingConsumption::create([
            'booking_id' => $booking->id,
            'spa_id' => $booking->spa_id,
            'branch_id' => $booking->branch_id,
            'treatment_id' => null,
            'package_id' => null,
            'processed_by' => $user?->id,
            'service_reference' => $booking->treatment,
            'consumed_at' => now(),
            'has_shortfall' => false,
            ...$attributes,
        ]);
    }

    private function consumeRecipeItem(
        Booking $booking,
        BookingConsumption $consumption,
        Treatment $treatment,
        $product,
        float $requiredQuantity,
        ?User $user
    ): void {
        if ($requiredQuantity <= self::TOLERANCE) {
            return;
        }

        if (!$product) {
            $this->addNote($consumption, "A recipe product for {$treatment->name} no longer exists.");

            return;
        }

        if ((int) $product->spa_id !== (int) $booking->spa_id) {
            $this->addNote($consumption, "A recipe product for {$treatment->name} belongs to another spa and was skipped.");

            return;
        }

        if (!$product->is_active) {
            $this->recordShort($consumption, $treatment, $product, $requiredQuantity);

            return;
        }

        $stock = BranchProductStock::query()
            ->where('spa_id', $booking->spa_id)
            ->where('branch_id', $booking->branch_id)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->first();

        if (!$stock) {
            $this->recordShort($consumption, $treatment, $product, $requiredQuantity);

            return;
        }

        // Usable = not expired, oldest expiry first (FEFO), no-expiry last.
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

        $unit = $product->usage_unit ?: $product->unit ?: 'pcs';
        $remainingToConsume = $requiredQuantity;

        foreach ($batches as $batch) {
            if ($remainingToConsume <= self::TOLERANCE) {
                break;
            }

            $before = (float) $stock->on_hand_quantity;

            // Never below zero, even if branch stock and batches have drifted apart.
            $deductQuantity = min((float) $batch->remaining_quantity, $remainingToConsume, max($before, 0));

            if ($deductQuantity <= self::TOLERANCE) {
                break;
            }

            $after = $before - $deductQuantity;

            $batch->update([
                'remaining_quantity' => (float) $batch->remaining_quantity - $deductQuantity,
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
                'unit' => $unit,
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
                'short_quantity' => 0,
                'unit' => $unit,
            ]);

            $remainingToConsume -= $deductQuantity;
            $stock->refresh();
        }

        if ($remainingToConsume > self::TOLERANCE) {
            $this->recordShort($consumption, $treatment, $product, $remainingToConsume);
        }
    }

    /** A product row that couldn't be (fully) consumed: quantity 0, no batch. */
    private function recordShort(BookingConsumption $consumption, Treatment $treatment, $product, float $shortQuantity): void
    {
        BookingConsumptionItem::create([
            'booking_consumption_id' => $consumption->id,
            'product_id' => $product->id,
            'product_batch_id' => null,
            'treatment_id' => $treatment->id,
            'treatment_name' => $treatment->name,
            'product_name' => $product->name,
            'batch_number' => null,
            'quantity' => 0,
            'short_quantity' => round($shortQuantity, 3),
            'unit' => $product->usage_unit ?: $product->unit ?: 'pcs',
        ]);

        if (!$consumption->has_shortfall) {
            $consumption->update(['has_shortfall' => true]);
        }
    }

    private function addNote(BookingConsumption $consumption, string $note): void
    {
        $consumption->update([
            'has_shortfall' => true,
            'shortfall_notes' => trim(((string) $consumption->shortfall_notes) . "\n" . $note),
        ]);
    }
}
