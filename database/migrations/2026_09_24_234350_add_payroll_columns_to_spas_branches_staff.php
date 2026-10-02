<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('spas', function (Blueprint $table) {
            $table->unsignedTinyInteger('payroll_first_cutoff_day')->default(15);
            $table->unsignedTinyInteger('payroll_pay_day_offset')->default(5)
                ->comment('Days after cutoff end');
            // UNVERIFIED — required before monthly-paid staff can be run.
            $table->unsignedSmallInteger('monthly_rate_divisor')->nullable()
                ->comment('UNVERIFIED; required before monthly-paid staff can be run');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->decimal('min_daily_wage', 10, 2)->nullable();
            $table->string('wage_order_ref')->nullable();
            $table->date('min_wage_effective_from')->nullable();
        });

        // TEXT, not VARCHAR: `encrypted` cast output is longer than the
        // plaintext and its length is not fixed.
        Schema::table('staff', function (Blueprint $table) {
            $table->text('tin')->nullable();
            $table->text('sss_no')->nullable();
            $table->text('philhealth_no')->nullable();
            $table->text('pagibig_no')->nullable();
        });
    }

    public function down()
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['tin', 'sss_no', 'philhealth_no', 'pagibig_no']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['min_daily_wage', 'wage_order_ref', 'min_wage_effective_from']);
        });

        Schema::table('spas', function (Blueprint $table) {
            $table->dropColumn(['payroll_first_cutoff_day', 'payroll_pay_day_offset', 'monthly_rate_divisor']);
        });
    }
};
