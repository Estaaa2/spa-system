<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('quantity', 14, 3);

            $table->string('unit')->nullable();

            $table->timestamps();

            $table->unique(
                ['purchase_order_id', 'product_id'],
                'po_items_order_product_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
