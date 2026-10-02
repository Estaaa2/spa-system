<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('goods_receipt_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('purchase_order_item_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('product_batch_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->decimal('received_quantity', 14, 3);

            $table->decimal('accepted_quantity', 14, 3);

            $table->decimal('rejected_quantity', 14, 3)
                ->default(0);

            $table->string('unit')->nullable();

            $table->decimal('conversion_factor', 14, 3)
                ->default(1);

            $table->decimal('inventory_quantity', 14, 3)
                ->default(0);

            $table->string('batch_number', 100)->nullable();

            $table->date('manufactured_at')->nullable();

            $table->date('expiration_date')->nullable();

            $table->text('rejection_reason')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(
                ['purchase_order_item_id', 'goods_receipt_id'],
                'grn_items_po_item_receipt_idx'
            );

            $table->index(
                ['product_id', 'batch_number'],
                'grn_items_product_batch_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
    }
};
