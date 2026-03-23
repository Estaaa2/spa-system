<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('branch_profiles', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('treatments', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('branch_profiles', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('treatments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};