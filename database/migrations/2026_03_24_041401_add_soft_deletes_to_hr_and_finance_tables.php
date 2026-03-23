<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('interviews', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('staff_attendance', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('payroll', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('interviews', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('staff_attendance', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('payroll', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};