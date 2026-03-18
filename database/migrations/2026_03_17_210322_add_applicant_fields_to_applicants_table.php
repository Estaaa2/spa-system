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
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('role')->nullable()->after('branch_id');
            $table->string('gender')->nullable()->after('role');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('civil_status')->nullable()->after('date_of_birth');
            $table->string('address')->nullable()->after('civil_status');
            $table->string('education')->nullable()->after('address');
            $table->text('work_experience')->nullable()->after('education');
            $table->text('skills')->nullable()->after('work_experience');
            $table->string('emergency_contact_name')->nullable()->after('skills');
            $table->string('emergency_contact_relation')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_relation');
            $table->date('expected_start_date')->nullable()->after('emergency_contact_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            //
        });
    }
};
