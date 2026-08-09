<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reassignment_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');       // therapist who flagged it
            $table->foreignId('old_therapist_id')->constrained('users');   // snapshot at request time
            $table->foreignId('new_therapist_id')->nullable()->constrained('users'); // filled on approval

            $table->text('reason');
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->text('rejection_reason')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reassignment_requests');
    }
};