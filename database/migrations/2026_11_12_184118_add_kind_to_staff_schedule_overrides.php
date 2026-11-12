<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff scheduling Phase 4 (Q2 A) — how a one-off change counts for pay.
 *
 * kind:
 *  - 'unpaid' (day_off only): an unpaid day off on a workday. Monthly-paid staff
 *    are deducted at the daily rate; daily-paid are simply not paid.
 *  - 'swap'   (day_off + shift pair): a rest-day swap within one Mon–Sun week.
 *    The day off becomes that week's rest day; the usual rest day becomes an
 *    ordinary workday (no premium). The two rows point at each other.
 *  - null     (shift only): changed hours on a workday, or an extra shift on a
 *    rest day (rest-day premium applies if worked).
 *
 * Existing day-off rows become 'unpaid'. Safe to re-run the data step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_schedule_overrides', function (Blueprint $table) {
            $table->string('kind', 10)->nullable()->after('type');
            $table->foreignId('paired_override_id')->nullable()->after('kind')
                ->constrained('staff_schedule_overrides')->nullOnDelete();
        });

        DB::table('staff_schedule_overrides')
            ->where('type', 'day_off')
            ->whereNull('kind')
            ->update(['kind' => 'unpaid']);
    }

    public function down(): void
    {
        Schema::table('staff_schedule_overrides', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paired_override_id');
            $table->dropColumn('kind');
        });
    }
};
