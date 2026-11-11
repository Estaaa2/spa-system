<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff scheduling Phase 2 — a fixed weekly work pattern per staff member,
 * effective-dated, plus one-off changes for single dates (decision D1).
 *
 * staff_work_schedule_days: one row per weekday (Q1 A). A workday with no
 * start/end time follows the branch's opening hours that day (Q2 A).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['staff_id', 'effective_from'], 'sws_staff_from_idx');
        });

        Schema::create('staff_work_schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_work_schedule_id')->constrained('staff_work_schedules')->cascadeOnDelete();
            $table->string('day_of_week', 9);           // Monday … Sunday (same names as operating_hours)
            $table->boolean('is_rest_day')->default(false);
            $table->time('start_time')->nullable();      // null on a workday = branch hours
            $table->time('end_time')->nullable();
            $table->timestamps();

            $table->unique(['staff_work_schedule_id', 'day_of_week'], 'swsd_schedule_day_unique');
        });

        Schema::create('staff_schedule_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 10);                  // day_off | shift
            $table->time('start_time')->nullable();      // shift only
            $table->time('end_time')->nullable();
            $table->string('reason', 150)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['staff_id', 'date'], 'sso_staff_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_schedule_overrides');
        Schema::dropIfExists('staff_work_schedule_days');
        Schema::dropIfExists('staff_work_schedules');
    }
};
