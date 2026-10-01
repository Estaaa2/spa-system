<?php

namespace Tests\Feature\Payroll\Concerns;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\CommissionRule;
use App\Models\PayrollRun;
use App\Models\Spa;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffPayProfile;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Payroll\EarningsCalculator;
use App\Services\Payroll\ValueObjects\EarningsResult;
use Database\Factories\BookingFactory;
use Database\Factories\BranchFactory;
use Database\Factories\SpaFactory;
use Database\Factories\StaffAttendanceFactory;
use Database\Factories\StaffFactory;
use Database\Factories\PayrollUserFactory;
use Database\Factories\TreatmentFactory;

/**
 * Every fixture the payroll feature tests create lives here, so a schema mismatch
 * is fixed in one place. Base models use XFactory::new() (no HasFactory needed on
 * Spa/Branch). Unit 1 payroll models use Model::create() with explicit values, so
 * these tests do not depend on how the Unit 1 factories are defined.
 *
 * World: spa; branch A (min wage 600) = home; branch B (min wage 650); one staff
 * member; daily-paid ₱600 profile, Sunday rest day, commission enabled, from 2026-01-01.
 */
trait PayrollFixtures
{
    protected Spa $spa;
    protected Branch $branchA;
    protected Branch $branchB;
    protected User $user;
    protected Staff $staff;
    protected User $admin;

    protected function makeWorld(string $homeMinWage = '600.00'): void
    {
        // spas.owner_id is required and users.spa_id is nullable: owner first, then spa.
        $this->admin = PayrollUserFactory::new()->create(['is_owner' => true]);
        $this->spa = SpaFactory::new()->create(['owner_id' => $this->admin->id]);
        $this->branchA = BranchFactory::new()->create(['spa_id' => $this->spa->id, 'name' => 'Branch A', 'is_main' => true, 'min_daily_wage' => $homeMinWage]);
        $this->branchB = BranchFactory::new()->create(['spa_id' => $this->spa->id, 'name' => 'Branch B', 'min_daily_wage' => '650.00']);

        $this->admin->forceFill(['spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id])->save();
        $this->user = PayrollUserFactory::new()->create(['spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id]);

        $this->staff = StaffFactory::new()->create([
            'user_id' => $this->user->id, 'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id,
        ]);
    }

    protected function profile(array $overrides = []): StaffPayProfile
    {
        return StaffPayProfile::create(array_merge([
            'staff_id'           => $this->staff->id,
            'effective_from'     => '2026-01-01',
            'effective_to'       => null,
            'pay_basis'          => 'daily',
            'base_rate'          => '600.00',
            'commission_enabled' => true,
            'rest_days'          => ['Sunday'],
            'created_by'         => $this->admin->id,
        ], $overrides));
    }

    protected function payrollRun(string $start = '2026-08-16', string $end = '2026-08-31', int $cutoff = 2): PayrollRun
    {
        return PayrollRun::create([
            'spa_id'         => $this->spa->id,
            'run_type'       => PayrollRun::TYPE_REGULAR,
            'period_start'   => $start,
            'period_end'     => $end,
            'cutoff_no'      => $cutoff,
            'pay_date'       => (new \DateTimeImmutable($end))->modify('+5 days')->format('Y-m-d'),
            'config_version' => config('payroll.version'),
            'generated_by'   => $this->admin->id,
        ]);
    }

    protected function attend(string $date, string $status = 'present', ?string $in = '09:00:00', ?string $out = '18:00:00', ?Branch $branch = null, bool $autoClosed = false): StaffAttendance
    {
        return StaffAttendanceFactory::new()->create([
            'staff_id'    => $this->staff->id,
            'spa_id'      => $this->spa->id,
            'branch_id'   => ($branch ?? $this->branchA)->id,
            'date'        => $date,
            'status'      => $status,
            'time_in'     => $in,
            'time_out'    => $out,
            'auto_closed' => $autoClosed,
        ]);
    }

    protected function treatment(string $price = '1500.00', ?Branch $branch = null): Treatment
    {
        return TreatmentFactory::new()->create([
            'spa_id' => $this->spa->id, 'branch_id' => ($branch ?? $this->branchA)->id, 'price' => $price,
        ]);
    }

    protected function booking(string $date, array $overrides = []): Booking
    {
        return BookingFactory::new()->create(array_merge([
            'spa_id'             => $this->spa->id,
            'branch_id'          => $this->branchA->id,
            'created_by_user_id' => $this->admin->id,
            'therapist_id'       => $this->user->id,   // users.id, not staff.id
            'treatment'          => 'treatment_'.$this->treatment()->id,
            'appointment_date'   => $date,
        ], $overrides));
    }

    protected function percentRule(string $value = '10.0000', ?Branch $branch = null): CommissionRule
    {
        return CommissionRule::create([
            'spa_id'         => $this->spa->id,
            'branch_id'      => $branch?->id,
            'target_type'    => CommissionRule::TARGET_DEFAULT,
            'target_id'      => null,
            'method'         => CommissionRule::METHOD_PERCENT,
            'value'          => $value,
            'effective_from' => '2026-01-01',
            'created_by'     => $this->admin->id,
        ]);
    }

    protected function calculate(?PayrollRun $run = null): EarningsResult
    {
        return app(EarningsCalculator::class)->calculate($this->staff->fresh(), $run ?? $this->payrollRun());
    }
}
