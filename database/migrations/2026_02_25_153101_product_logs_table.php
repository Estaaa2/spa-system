<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('product_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // who deducted
            $table->string('description');
            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();

            $table->index(['spa_id', 'logged_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('product_logs');
    }
};
