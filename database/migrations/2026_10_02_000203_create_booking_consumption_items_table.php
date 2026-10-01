<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_consumption_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_consumption_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('product_batch_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('product_name');
            $table->string('batch_number')->nullable();
            $table->decimal('quantity', 14, 3);
            $table->string('unit');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_consumption_items');
    }
};
