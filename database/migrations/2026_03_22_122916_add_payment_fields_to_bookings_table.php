<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_status')->default('unpaid')->after('status');
            $table->decimal('amount_paid', 10, 2)->default(0)->after('payment_status');
            $table->decimal('total_amount', 10, 2)->default(0)->after('amount_paid');
            $table->decimal('balance_amount', 10, 2)->default(0)->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'payment_status',
                'amount_paid',
                'total_amount',
                'balance_amount',
            ]);
        });
    }
};