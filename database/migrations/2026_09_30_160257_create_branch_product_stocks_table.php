<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            $table->decimal('on_hand_quantity', 14, 3)->default(0);
            $table->decimal('reorder_level', 14, 3)->default(0);
            $table->decimal('minimum_stock', 14, 3)->nullable();
            $table->decimal('maximum_stock', 14, 3)->nullable();

            $table->timestamps();

            $table->unique(
                ['branch_id', 'product_id'],
                'branch_product_stocks_branch_product_unique'
            );

            $table->index(['spa_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_product_stocks');
    }
};
