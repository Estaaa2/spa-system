<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff scheduling & hiring Phase 5 (Job Openings).
 *
 * job_postings gets what an opening needs: how many people, the job type, the
 * weekly schedule (same shape as a staff work schedule, Phase 2), optional pay
 * (Q5 A) and why it closed (Q6 A). No age field (RA 10911).
 *
 * applicants gets the "Can you work this schedule?" answer (Q9 A).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->unsignedSmallInteger('openings')->default(1)->after('role');
            $table->string('employment_type', 20)->default('full_time')->after('openings');
            // {"Monday": {"mode": "branch"}, "Sunday": {"mode": "rest"}, "Tuesday": {"mode": "custom", "start": "10:00", "end": "19:00"}, ...}
            $table->json('schedule')->nullable()->after('employment_type');
            $table->string('pay_basis', 20)->nullable()->after('schedule');
            $table->decimal('pay_min', 10, 2)->nullable()->after('pay_basis');
            $table->decimal('pay_max', 10, 2)->nullable()->after('pay_min');
            $table->boolean('show_pay')->default(false)->after('pay_max');
            $table->string('closed_reason', 20)->nullable()->after('status');
            $table->timestamp('closed_at')->nullable()->after('closed_reason');

            $table->index(['branch_id', 'status'], 'job_postings_branch_status_index');
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->boolean('schedule_fit')->nullable()->after('availability');
            $table->string('schedule_fit_note', 255)->nullable()->after('schedule_fit');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn(['schedule_fit', 'schedule_fit_note']);
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropIndex('job_postings_branch_status_index');
            $table->dropColumn([
                'openings', 'employment_type', 'schedule', 'pay_basis', 'pay_min',
                'pay_max', 'show_pay', 'closed_reason', 'closed_at',
            ]);
        });
    }
};
