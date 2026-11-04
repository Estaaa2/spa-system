<?php

namespace Database\Factories;

use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: one computed BASIC earning line, unsourced.
 * Component codes are placeholders until config/payroll.php exists (later unit).
 *
 * @extends Factory<PayslipLine>
 */
class PayslipLineFactory extends Factory
{
    protected $model = PayslipLine::class;

    public function definition(): array
    {
        $qty  = 1;
        $rate = fake()->randomFloat(4, 500, 900); // arbitrary test value

        return [
            'payslip_id'     => Payslip::factory(),
            'component_code' => 'BASIC',
            'label'          => 'Basic pay',
            'kind'           => PayslipLine::KIND_EARNING,
            'branch_id'      => null,
            'quantity'       => $qty,
            'rate'           => $rate,
            'amount'         => round($qty * $rate, 2),
            'source_type'    => null,
            'source_id'      => null,
            'is_manual'      => false,
            'created_by'     => null,
            'note'           => null,
        ];
    }

    public function manualAdjustment(string $code = 'ADJ_EARNING'): static
    {
        return $this->state(fn () => [
            'component_code' => $code,
            'label'          => 'Manual adjustment',
            'kind'           => $code === 'ADJ_DEDUCTION' ? PayslipLine::KIND_DEDUCTION : PayslipLine::KIND_EARNING,
            'is_manual'      => true,
            'created_by'     => User::factory(),
            'note'           => 'Test adjustment',
        ]);
    }

    public function fromBooking(int $bookingId): static
    {
        return $this->state(fn () => [
            'component_code' => 'COMMISSION',
            'label'          => 'Commission',
            'source_type'    => PayslipLine::SOURCE_BOOKING,
            'source_id'      => $bookingId,
        ]);
    }

    public function fromAttendance(int $attendanceId, string $code = 'BASIC'): static
    {
        return $this->state(fn () => [
            'component_code' => $code,
            'source_type'    => PayslipLine::SOURCE_ATTENDANCE,
            'source_id'      => $attendanceId,
        ]);
    }
}
