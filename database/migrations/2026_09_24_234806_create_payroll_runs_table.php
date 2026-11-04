<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spa_id')->constrained('spas')->restrictOnDelete();
            $table->enum('run_type', ['regular', 'thirteenth_month']);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedTinyInteger('cutoff_no')->nullable(); // 1/2, regular only.
            $table->date('pay_date');
            $table->enum('status', ['draft', 'approved', 'finalized', 'released'])->default('draft');
            $table->string('config_version');

            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->unique(['spa_id', 'run_type', 'period_start'], 'payroll_runs_spa_type_period_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payroll_runs');
    }
};
