<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data model v3.1: spas.monthly_rate_divisor is deprecated and never read (the
 * days-per-year factor is derived from pay_basis + rest_days). A new migration is used
 * instead of editing the Unit 1 migration, so this works whether or not Unit 1 has
 * already run on the server.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('spas', 'monthly_rate_divisor')) {
            Schema::table('spas', function (Blueprint $table) {
                $table->dropColumn('monthly_rate_divisor');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('spas', 'monthly_rate_divisor')) {
            Schema::table('spas', function (Blueprint $table) {
                $table->unsignedSmallInteger('monthly_rate_divisor')->nullable()
                    ->comment('DEPRECATED (data model v3.1) — never read');
            });
        }
    }
};
