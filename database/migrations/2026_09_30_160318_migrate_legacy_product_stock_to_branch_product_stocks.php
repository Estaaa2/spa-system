<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $products = DB::table('products')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        foreach ($products as $product) {
            $branch = DB::table('branches')
                ->where('spa_id', $product->spa_id)
                ->whereNull('deleted_at')
                ->orderByDesc('is_main')
                ->orderBy('id')
                ->first();

            if (!$branch) {
                continue;
            }

            DB::table('branch_product_stocks')->updateOrInsert(
                [
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                ],
                [
                    'spa_id' => $product->spa_id,
                    'on_hand_quantity' => $product->stock_quantity ?? 0,
                    'reorder_level' => 5,
                    'minimum_stock' => null,
                    'maximum_stock' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            if (($product->stock_quantity ?? 0) > 0) {
                DB::table('stock_movements')->insert([
                    'spa_id' => $product->spa_id,
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'product_batch_id' => null,
                    'user_id' => null,
                    'booking_id' => null,
                    'movement_type' => 'opening_balance',
                    'direction' => 'in',
                    'quantity' => $product->stock_quantity,
                    'unit' => $product->unit ?? 'pcs',
                    'balance_before' => 0,
                    'balance_after' => $product->stock_quantity,
                    'reference_type' => 'legacy_product_migration',
                    'reference_id' => $product->id,
                    'notes' => 'Opening balance migrated from legacy products.stock_quantity.',
                    'occurred_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $productIds = DB::table('stock_movements')
            ->where('reference_type', 'legacy_product_migration')
            ->pluck('product_id');

        DB::table('stock_movements')
            ->where('reference_type', 'legacy_product_migration')
            ->delete();

        DB::table('branch_product_stocks')
            ->whereIn('product_id', $productIds)
            ->delete();
    }
};
