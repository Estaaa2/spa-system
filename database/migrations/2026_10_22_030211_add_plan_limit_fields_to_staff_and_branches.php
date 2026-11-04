<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (! Schema::hasColumn('staff', 'keep_active')) {
                $table->boolean('keep_active')->default(false)->after('employment_status');
            }

            if (! Schema::hasColumn('staff', 'plan_paused_at')) {
                $table->timestamp('plan_paused_at')->nullable()->after('keep_active');
            }
        });

        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'keep_active')) {
                $table->boolean('keep_active')->default(false)->after('is_main');
            }
        });

        Schema::table('spas', function (Blueprint $table) {
            if (! Schema::hasColumn('spas', 'plan_swap_used_at')) {
                $table->timestamp('plan_swap_used_at')->nullable()->after('trial_reminder_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['keep_active', 'plan_paused_at'],
                fn (string $column) => Schema::hasColumn('staff', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'keep_active')) {
                $table->dropColumn('keep_active');
            }
        });

        Schema::table('spas', function (Blueprint $table) {
            if (Schema::hasColumn('spas', 'plan_swap_used_at')) {
                $table->dropColumn('plan_swap_used_at');
            }
        });
    }
};
