<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spa_id')->constrained()->cascadeOnDelete();
            $table->string('role_name');           // e.g. 'therapist'
            $table->string('permission_name');     // e.g. 'view inventory'
            $table->boolean('granted')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'role_name', 'permission_name'], 'branch_role_perm_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_role_permissions');
    }
};
