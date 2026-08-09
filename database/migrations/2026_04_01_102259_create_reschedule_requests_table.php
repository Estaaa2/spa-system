<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reschedule_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('requested_by')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->date('requested_date');
            $table->time('requested_time');
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])
                  ->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reschedule_requests');
    }
};
