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
        Schema::table('staff', function (Blueprint $table) {
            $table->decimal('basic_salary', 10, 2)->default(0)->after('hire_date');
            $table->decimal('daily_rate', 10, 2)->default(0)->after('basic_salary');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['basic_salary', 'daily_rate']);
        });
    }
};
