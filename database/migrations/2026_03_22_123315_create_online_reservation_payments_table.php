<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('online_reservation_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('spa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();

            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->text('customer_address')->nullable();

            $table->unsignedBigInteger('bookable_id'); // treatment/package id
            $table->string('bookable_type'); // treatment or package

            $table->string('bookable_name');
            $table->decimal('full_amount', 10, 2);
            $table->decimal('downpayment_amount', 10, 2);

            $table->enum('service_type', ['in_branch', 'in_home']);
            $table->date('appointment_date');
            $table->time('start_time');

            $table->string('paymongo_checkout_session_id')->nullable()->unique();
            $table->string('paymongo_payment_intent_id')->nullable();
            $table->string('paymongo_payment_id')->nullable();
            $table->string('payment_reference')->nullable();

            $table->enum('payment_status', ['pending', 'paid', 'failed', 'expired', 'cancelled'])->default('pending');
            $table->enum('reservation_status', ['awaiting_payment', 'reserved', 'failed', 'cancelled'])->default('awaiting_payment');

            $table->json('paymongo_payload')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_reservation_payments');
    }
};