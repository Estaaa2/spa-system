<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a customer last changed their name (UI rework Phase 5). Customers may
 * change it once every 30 days, with 24 hours after a change to fix a typo
 * (User::NAME_CHANGE_DAYS, User::NAME_CORRECTION_HOURS). The rule is checked
 * when a name is saved, so nothing has to run on a schedule.
 *
 * Nullable on purpose: NULL means "never changed", and a non-null default
 * trips XAMPP's MariaDB timestamp mode (error 1067).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'name_changed_at')) {
                $table->timestamp('name_changed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'name_changed_at')) {
                $table->dropColumn('name_changed_at');
            }
        });
    }
};
