<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_profiles', function (Blueprint $table) {
            $table->string('city', 100)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('branch_profiles', function (Blueprint $table) {
            $table->dropColumn('city');
        });
    }
};