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
        $table->string('service_type');
        $table->string('treatment');
        $table->string('therapist');
        $table->string('phone');
        $table->string('fullname');
        $table->string('address');
        $table->string('email');
        $table->date('date');
        $table->string('time');
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
