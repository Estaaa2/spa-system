<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('staff_recurring_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->enum('kind', ['earning', 'deduction']);
            $table->string('component_code', 32); // ALLOWANCE | LOAN_DEDUCTION | OTHER_DEDUCTION (config)
            $table->string('label', 100);
            $table->decimal('amount', 12, 2);
            $table->enum('frequency', ['per_cutoff', 'per_day_worked', 'second_cutoff_only']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('authorization_ref')->nullable(); // required when kind = deduction (enforced in model)
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('staff_recurring_items');
    }
};
