<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Totals default to zero; tests set the figures they assert on.
 * The parent run must be draft (default PayrollRun factory state).
 *
 * @extends Factory<Payslip>
 */
class PayslipFactory extends Factory
{
    protected $model = Payslip::class;

    public function definition(): array
    {
        return [
            'payroll_run_id'       => PayrollRun::factory(),
            'staff_id'             => Staff::factory(),
            // Staff's branch at generation; falls back to a new branch if the staff has none.
            'home_branch_id'       => fn (array $attrs) =>
                Staff::withTrashed()->whereKey($attrs['staff_id'])->value('branch_id') ?? Branch::factory(),
            'gross_pay'            => 0,
            'total_deductions'     => 0,
            'net_pay'              => 0,
            'taxable_compensation' => 0,
            'days_worked'          => 0,
            'is_mwe'               => false,
            'snapshot'             => [],
        ];
    }

    public function mwe(): static
    {
        return $this->state(fn () => ['is_mwe' => true]);
    }
}
