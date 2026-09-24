<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->foreignId('home_branch_id')->constrained('branches')->restrictOnDelete(); // staff branch at generation.

            $table->decimal('gross_pay', 12, 2);
            $table->decimal('total_deductions', 12, 2);
            $table->decimal('net_pay', 12, 2);
            $table->decimal('taxable_compensation', 12, 2);
            $table->decimal('days_worked', 5, 2);
            $table->boolean('is_mwe');
            $table->json('snapshot'); // pay profile, branch min wage, holidays used, spa settings, rule ids used.
            $table->timestamps();

            $table->unique(['payroll_run_id', 'staff_id'], 'payslips_run_staff_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payslips');
    }
};
