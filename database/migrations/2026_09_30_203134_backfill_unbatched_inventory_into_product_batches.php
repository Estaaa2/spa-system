<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $stocks = DB::table('branch_product_stocks')
            ->where('on_hand_quantity', '>', 0)
            ->orderBy('id')
            ->get();

        foreach ($stocks as $stock) {
            $batchedQuantity = (float) DB::table('product_batches')
                ->where('spa_id', $stock->spa_id)
                ->where('branch_id', $stock->branch_id)
                ->where('product_id', $stock->product_id)
                ->sum('remaining_quantity');

            $onHandQuantity = (float) $stock->on_hand_quantity;
            $difference = $onHandQuantity - $batchedQuantity;

            if ($difference <= 0) {
                continue;
            }

            DB::table('product_batches')->insert([
                'spa_id' => $stock->spa_id,
                'branch_id' => $stock->branch_id,
                'product_id' => $stock->product_id,
                'batch_number' => 'LEGACY-' . $stock->branch_id . '-' . $stock->product_id,
                'received_quantity' => $difference,
                'remaining_quantity' => $difference,
                'unit_cost' => null,
                'manufactured_at' => null,
                'expiration_date' => null,
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('product_batches')
            ->where('batch_number', 'like', 'LEGACY-%')
            ->delete();
    }
};