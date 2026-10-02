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
        Schema::create('purchase_request_status_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_request_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('from_status')->nullable();
            $table->string('to_status');

            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(
                ['purchase_request_id', 'to_status'],
                'pr_status_history_request_status_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_request_status_histories');
    }
};
