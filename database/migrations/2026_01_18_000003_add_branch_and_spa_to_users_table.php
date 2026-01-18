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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('spa_id')->nullable()->constrained('spas')->onDelete('set null');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->boolean('is_owner')->default(false);
            $table->string('temp_password')->nullable();
            $table->boolean('password_reset_required')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['spa_id', 'branch_id']);
            $table->dropColumn(['spa_id', 'branch_id', 'is_owner', 'temp_password', 'password_reset_required']);
        });
    }
};
