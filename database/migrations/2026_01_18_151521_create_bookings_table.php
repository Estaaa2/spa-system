<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete(); // Staff/receptionist who created the booking (nullable).
            $table->string('booking_source')->nullable(); //if they booked online or walk-in.
            $table->string('status')->default('reserved');   //if reserved, confirmed, completed.
            $table->string('service_type');//if in-branch or home service.
            $table->string('treatment');//type of treatment.
            $table->string('customer_phone')->nullable(); //phone for contacting walk-in.
            $table->string('customer_name')->nullable();  //name if walk-in.
            $table->string('customer_address')->nullable(); //address if walk-in.
            $table->string('customer_email')->nullable(); //email for contacting walk-in.
            $table->date('appointment_date');
            $table->time('appointment_time');
            $table->foreignId('therapist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
