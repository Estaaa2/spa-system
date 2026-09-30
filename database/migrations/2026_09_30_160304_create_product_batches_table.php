<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            $table->string('batch_number')->nullable();
            $table->decimal('received_quantity', 14, 3)->default(0);
            $table->decimal('remaining_quantity', 14, 3)->default(0);
            $table->decimal('unit_cost', 12, 4)->nullable();

            $table->date('manufactured_at')->nullable();
            $table->date('expiration_date')->nullable();
            $table->timestamp('received_at')->nullable();

            $table->timestamps();

            $table->index([
                'branch_id',
                'product_id',
                'expiration_date',
            ], 'product_batches_fefo_index');

            $table->index(['spa_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};
