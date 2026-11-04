<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Payroll v3, table 6. Payslip lines are the individual earnings/deductions/employer shares that make up a payslip.
return new class extends Migration
{
    public function up()
    {
        Schema::create('payslip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained('payslips')->cascadeOnDelete();
            $table->string('component_code', 32); // code from config/payroll.php components
            $table->string('label', 100);
            $table->enum('kind', ['earning', 'deduction', 'employer_share']);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete(); // where earned
            $table->decimal('quantity', 10, 2);
            $table->decimal('rate', 12, 4);
            $table->decimal('amount', 12, 2);
            $table->enum('source_type', ['booking', 'attendance', 'recurring_item'])->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('unique_source_id')->nullable()->storedAs(
                "CASE WHEN source_type IN ('booking', 'attendance') THEN source_id ELSE NULL END"
            );
            $table->boolean('is_manual')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); // manual lines
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['component_code', 'source_type', 'unique_source_id'], 'payslip_lines_paid_once_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payslip_lines');
    }
};