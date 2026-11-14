<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer cancellation (Nov 2026). Who cancelled a booking and when, and
 * whether it used the customer's free cancellation (one per 30 days, read by
 * App\Services\Bookings\CustomerCancellation). Existing cancelled rows keep
 * nulls: they were cancelled by staff before this existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->string('cancelled_by', 20)->nullable()->after('cancelled_at'); // customer | staff
            $table->string('cancel_reason', 500)->nullable()->after('cancelled_by');
            $table->boolean('free_cancellation')->default(false)->after('cancel_reason');

            // The 30-day free-cancellation lookup.
            $table->index(['customer_user_id', 'free_cancellation', 'cancelled_at'], 'bookings_free_cancel_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_free_cancel_lookup');
            $table->dropColumn(['cancelled_at', 'cancelled_by', 'cancel_reason', 'free_cancellation']);
        });
    }
};
