<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('spa_id')
                ->constrained('spas')
                ->cascadeOnDelete();

            $table->foreignId('source_branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->foreignId('destination_branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->decimal('quantity', 14, 3);
            $table->string('unit', 50);

            $table->string('status', 30)
                ->default('pending');

            $table->foreignId('requested_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')
                ->nullable();

            $table->timestamp('transferred_at')
                ->nullable();

            $table->timestamps();

            $table->index(
                ['spa_id', 'source_branch_id', 'destination_branch_id'],
                'st_branch_route_idx'
            );

            $table->index(
                ['spa_id', 'product_id', 'status'],
                'st_product_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
