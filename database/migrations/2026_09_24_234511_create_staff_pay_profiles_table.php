<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('staff_pay_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->enum('pay_basis', ['monthly', 'daily']);
            $table->decimal('base_rate', 12, 2); // 0 allowed (e.g. commission-only)
            $table->boolean('commission_enabled')->default(false);
            $table->json('rest_days');           // e.g. ["Sunday"]
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('staff_pay_profiles');
    }
};
