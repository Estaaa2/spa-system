<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_bill_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vendor_bill_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('purchase_order_item_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('billed_quantity', 12, 3);

            $table->string('unit', 50);

            $table->decimal('unit_cost', 14, 2);

            $table->decimal('line_total', 14, 2);

            $table->timestamps();

            $table->unique(
                ['vendor_bill_id', 'purchase_order_item_id'],
                'vendor_bill_po_item_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bill_items');
    }
};
