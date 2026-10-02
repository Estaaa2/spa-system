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
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('spa_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('purchase_order_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('received_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('delivery_reference')->nullable();

            $table->timestamp('received_at');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(
                ['spa_id', 'branch_id', 'received_at'],
                'grn_spa_branch_received_idx'
            );

            $table->index(
                ['purchase_order_id', 'received_at'],
                'grn_po_received_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
