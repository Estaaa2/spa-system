<?php
// database/migrations/xxxx_xx_xx_add_is_hiring_to_branch_profiles_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_profiles', function (Blueprint $table) {
            $table->boolean('is_hiring')->default(false)->after('is_listed');
            $table->string('hiring_note', 150)->nullable()->after('is_hiring');
        });
    }

    public function down(): void
    {
        Schema::table('branch_profiles', function (Blueprint $table) {
            $table->dropColumn(['is_hiring', 'hiring_note']);
        });
    }
};
