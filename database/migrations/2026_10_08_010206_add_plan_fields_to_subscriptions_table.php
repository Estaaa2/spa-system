<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'billing_cycle')) {
                $table->enum('billing_cycle', ['monthly', 'yearly'])
                    ->default('monthly')
                    ->after('amount');
            }

            if (!Schema::hasColumn('subscriptions', 'status')) {
                $table->enum('status', [
                    'pending',
                    'trialing',
                    'active',
                    'past_due',
                    'expired',
                    'cancelled',
                ])->default('pending')
                    ->after('payment_status');
            }

            if (!Schema::hasColumn('subscriptions', 'cancelled_at')) {
                $table->timestamp('cancelled_at')
                    ->nullable()
                    ->after('expires_at');
            }

            if (!Schema::hasColumn('subscriptions', 'paymongo_payment_id')) {
                $table->string('paymongo_payment_id')
                    ->nullable()
                    ->after('paymongo_checkout_id');
            }

            if (!Schema::hasColumn('subscriptions', 'payment_method')) {
                $table->string('payment_method')
                    ->nullable()
                    ->after('paymongo_payment_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $columns = [];

            foreach ([
                'billing_cycle',
                'status',
                'cancelled_at',
                'paymongo_payment_id',
                'payment_method',
            ] as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};