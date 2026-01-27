<?php
// database/migrations/xxxx_create_schedules_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['available', 'booked', 'break', 'time_off'])->default('available');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'date', 'start_time']);
        });
    }
};
