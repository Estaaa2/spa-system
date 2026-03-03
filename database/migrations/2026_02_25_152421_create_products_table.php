<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spa_id')->constrained()->cascadeOnDelete(); // if your system is multi-spa
            $table->string('name');
            $table->string('brand')->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->date('expiration_date')->nullable();
            $table->timestamps();

            $table->index(['spa_id', 'name']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('products');
    }
};
