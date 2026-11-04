<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_bills', function (Blueprint $table) {
            $table->id();

            $table->foreignId('spa_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('supplier_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('purchase_order_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('invoice_number');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);

            $table->string('status')->default('draft');

            $table->json('match_details')->nullable();
            $table->timestamp('matched_at')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['spa_id', 'supplier_id', 'invoice_number'],
                'vendor_bills_supplier_invoice_unique'
            );

            $table->index(
                ['spa_id', 'branch_id', 'status'],
                'vendor_bills_scope_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bills');
    }
};
