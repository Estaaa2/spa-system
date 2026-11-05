<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Booking lifecycle fix, Phase 3 (D5): a reschedule request nobody reviewed
 * before the original appointment started becomes `expired`.
 *
 * reschedule_requests.status is an enum, so the value list is widened. Existing
 * rows keep their values. reassignment_requests.status is a plain string and
 * needs no change. Safe to re-run: widening to the same list is a no-op.
 *
 * down() narrows it back; it refuses while any row is `expired`, because MySQL
 * would otherwise fail or blank those rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reschedule_requests', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('reschedule_requests')->where('status', 'expired')->exists()) {
            throw new RuntimeException('Some reschedule requests are expired. Change them to rejected before rolling back.');
        }

        Schema::table('reschedule_requests', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending')
                ->change();
        });
    }
};
