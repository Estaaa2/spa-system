<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the checkout was made with live PayMongo keys. Test-mode
     * payments are not real money, so revenue figures only count rows of
     * the current mode. Every existing row was made in test mode.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('subscriptions', 'livemode')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->boolean('livemode')->default(false)->after('paymongo_payment_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscriptions', 'livemode')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('livemode');
            });
        }
    }
};
