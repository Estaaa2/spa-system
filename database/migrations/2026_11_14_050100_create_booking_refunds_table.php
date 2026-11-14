<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refunds owed to customers (customer cancellation, Nov 2026). All online
 * payments go to the Levictas PayMongo account, so a Levictas admin sends each
 * refund by hand (PayMongo dashboard or another transfer) and marks it sent
 * with a reference number. One refund per booking: the unique key stops a
 * double tap from queueing two.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('online_reservation_payment_id')->nullable()
                ->constrained('online_reservation_payments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // the customer

            $table->decimal('amount', 10, 2);
            $table->string('kind', 30);                        // free_cancellation | late_cancellation
            $table->string('status', 20)->default('pending');  // pending | sent

            $table->string('payment_method', 30)->nullable();  // how it was sent back (gcash, maya, bank…)
            $table->string('reference', 100)->nullable();      // PayMongo refund ID or transfer reference
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_refunds');
    }
};
