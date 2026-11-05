<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Booking lifecycle fix, Phase 1 (D7, Q6-A/Q7-A): checkout completes a booking
 * even when stock is short. What couldn't be consumed is recorded here.
 *
 *   booking_consumption_items.short_quantity   how much of this product was missing
 *                                              (a "short" row has quantity 0 and no batch)
 *   booking_consumptions.has_shortfall         true when anything was short or a recipe
 *                                              couldn't be read (quick filter for the list)
 *   booking_consumptions.shortfall_notes       setup problems with no product row to point
 *                                              at (deleted service, empty package, ...)
 *
 * Additive only; existing rows get 0 / false / null. Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('booking_consumption_items', 'short_quantity')) {
            Schema::table('booking_consumption_items', function (Blueprint $table) {
                $table->decimal('short_quantity', 14, 3)->default(0)->after('quantity');
            });
        }

        if (! Schema::hasColumn('booking_consumptions', 'has_shortfall')) {
            Schema::table('booking_consumptions', function (Blueprint $table) {
                $table->boolean('has_shortfall')->default(false)->after('consumed_at');
                $table->text('shortfall_notes')->nullable()->after('has_shortfall');
                $table->index(['branch_id', 'has_shortfall', 'consumed_at'], 'booking_consumptions_shortfall_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('booking_consumptions', 'has_shortfall')) {
            Schema::table('booking_consumptions', function (Blueprint $table) {
                $table->dropIndex('booking_consumptions_shortfall_idx');
                $table->dropColumn(['has_shortfall', 'shortfall_notes']);
            });
        }

        if (Schema::hasColumn('booking_consumption_items', 'short_quantity')) {
            Schema::table('booking_consumption_items', function (Blueprint $table) {
                $table->dropColumn('short_quantity');
            });
        }
    }
};
