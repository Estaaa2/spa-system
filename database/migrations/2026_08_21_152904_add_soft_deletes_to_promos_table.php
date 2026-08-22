<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('promo_treatment', function (Blueprint $table) {
            $table->timestamps();
        });

        Schema::table('promo_package', function (Blueprint $table) {
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('promo_treatment', function (Blueprint $table) {
            $table->dropTimestamps();
        });

        Schema::table('promo_package', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }
};
