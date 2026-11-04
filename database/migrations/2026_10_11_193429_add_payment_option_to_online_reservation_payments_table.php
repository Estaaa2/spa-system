<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_reservation_payments', function (Blueprint $table) {
            // 'downpayment' (20%) or 'full' (100%). Existing rows are down payments.
            $table->string('payment_option')->default('downpayment')->after('downpayment_amount');
            // What was actually sent to PayMongo. Null on rows created before this column.
            $table->decimal('amount_charged', 10, 2)->nullable()->after('payment_option');
        });
    }

    public function down(): void
    {
        Schema::table('online_reservation_payments', function (Blueprint $table) {
            $table->dropColumn(['payment_option', 'amount_charged']);
        });
    }
};
