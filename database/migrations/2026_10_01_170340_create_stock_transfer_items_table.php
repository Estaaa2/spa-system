<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stock_transfer_id')
                ->constrained('stock_transfers')
                ->cascadeOnDelete();

            $table->foreignId('source_product_batch_id')
                ->nullable()
                ->constrained('product_batches')
                ->nullOnDelete();

            $table->foreignId('destination_product_batch_id')
                ->nullable()
                ->constrained('product_batches')
                ->nullOnDelete();

            $table->string('source_batch_number');
            $table->decimal('quantity', 14, 3);

            $table->date('expiration_date')
                ->nullable();

            $table->timestamps();

            $table->index(
                ['stock_transfer_id', 'source_product_batch_id'],
                'sti_transfer_batch_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
    }
};
