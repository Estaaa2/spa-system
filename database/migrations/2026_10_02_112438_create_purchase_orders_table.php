<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('spa_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('purchase_request_id')
                ->unique()
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('supplier_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('status')->default('draft');

            $table->text('notes')->nullable();

            $table->timestamp('issued_at')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(
                ['spa_id', 'branch_id', 'status'],
                'po_spa_branch_status_idx'
            );

            $table->index(
                ['supplier_id', 'status'],
                'po_supplier_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
