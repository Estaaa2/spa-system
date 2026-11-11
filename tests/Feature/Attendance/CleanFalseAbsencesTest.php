<?php

namespace Tests\Feature\Attendance;

use App\Models\OperatingHours;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Models\StaffAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Payroll\Concerns\PayrollFixtures;
use Tests\TestCase;

/**
 * Staff scheduling Phase 1 — attendance:clean-false-absences.
 * Calendar (2026): Aug 16/23 Sun (rest day) · Aug 17/24 Mon (branch A closed) · Aug 18 Tue.
 */
class CleanFalseAbsencesTest extends TestCase
{
    use RefreshDatabase;
    use PayrollFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->makeWorld();
        $this->profile(['pay_basis' => 'monthly', 'base_rate' => '18250.00']); // rest day Sunday
        OperatingHours::create([
            'branch_id' => $this->branchA->id, 'day_of_week' => 'Monday',
            'opening_time' => '09:00:00', 'closing_time' => '18:00:00', 'is_closed' => true,
        ]);
    }

    private function systemRow(string $date, string $status = 'absent', ?\App\Models\Branch $branch = null): StaffAttendance
    {
        $row = $this->attend($date, $status, null, null, $branch);
        $row->forceFill(['source' => 'system'])->save();

        return $row;
    }

    /** A run whose payslip deducted $row, then moved to $status without the model guards. */
    private function runUsing(StaffAttendance $row, string $status): PayrollRun
    {
        $run = $this->payrollRun();
        $payslip = Payslip::create([
            'payroll_run_id' => $run->id, 'staff_id' => $this->staff->id, 'home_branch_id' => $this->branchA->id,
            'gross_pay' => '8525.00', 'total_deductions' => '0.00', 'net_pay' => '8525.00',
            'taxable_compensation' => '8525.00', 'days_worked' => '0.00', 'is_mwe' => false, 'snapshot' => [],
        ]);
        PayslipLine::create([
            'payslip_id' => $payslip->id, 'component_code' => 'BASIC', 'label' => 'Basic pay', 'kind' => 'earning',
            'branch_id' => $this->branchA->id, 'quantity' => '-1.00', 'rate' => '600.0000', 'amount' => '-600.00',
            'source_type' => PayslipLine::SOURCE_ATTENDANCE, 'source_id' => $row->id,
        ]);
        DB::table('payroll_runs')->where('id', $run->id)->update(['status' => $status]);

        return $run;
    }

    private function clean(array $options = [])
    {
        return $this->artisan('attendance:clean-false-absences', array_merge(['--from' => '2026-08-01', '--to' => '2026-08-31', '--force' => true], $options));
    }

    public function test_from_is_required(): void
    {
        $this->artisan('attendance:clean-false-absences', ['--dry-run' => true])->assertExitCode(2);
    }

    public function test_dry_run_reports_and_changes_nothing(): void
    {
        $this->systemRow('2026-08-16');                 // rest day
        $this->systemRow('2026-08-17');                 // closed
        $this->systemRow('2026-08-24', 'on_leave');     // closed, on leave
        $this->systemRow('2026-08-18');                 // real absence

        $this->clean(['--dry-run' => true])
            ->expectsOutputToContain('3 row(s) would be removed')
            ->assertSuccessful();

        $this->assertSame(4, StaffAttendance::count());
        $this->assertCount(1, Storage::disk('local')->files('attendance-cleanup'));
    }

    public function test_real_run_removes_only_false_absences_and_rerun_finds_nothing(): void
    {
        $this->systemRow('2026-08-16');
        $this->systemRow('2026-08-17');
        $real = $this->systemRow('2026-08-18');
        $manual = $this->attend('2026-08-24', 'absent', null, null);   // HR-entered: not touched
        $worked = $this->attend('2026-08-23');                            // worked rest day: not touched

        $this->clean()->expectsOutputToContain('Removed 2 attendance row(s)')->assertSuccessful();

        $this->assertEqualsCanonicalizing([$real->id, $manual->id, $worked->id], StaffAttendance::pluck('id')->all());

        $csv = Storage::disk('local')->get(Storage::disk('local')->files('attendance-cleanup')[0]);
        $this->assertStringContainsString('2026-08-16', $csv);
        $this->assertStringContainsString('branch_closed', $csv);

        $this->clean()->expectsOutputToContain('No false absences found')->assertSuccessful();
    }

    public function test_date_range_limits_the_cleanup(): void
    {
        $earlier = $this->systemRow('2026-08-17');
        $later = $this->systemRow('2026-08-24');

        $this->clean(['--from' => '2026-08-20'])->assertSuccessful();

        $this->assertTrue(StaffAttendance::whereKey($earlier->id)->exists());  // before --from: kept
        $this->assertFalse(StaffAttendance::whereKey($later->id)->exists());
    }

    public function test_deployed_row_is_checked_against_its_own_branch(): void
    {
        $this->systemRow('2026-08-17', 'absent', $this->branchB); // Monday at B (open) → real absence

        $this->clean()->expectsOutputToContain('No false absences found')->assertSuccessful();
        $this->assertSame(1, StaffAttendance::count());
    }

    public function test_row_in_draft_run_is_removed_and_draft_listed(): void
    {
        $row = $this->systemRow('2026-08-17');
        $run = $this->runUsing($row, PayrollRun::STATUS_DRAFT);

        $this->clean()
            ->expectsOutputToContain("regenerate them after the real run: #{$run->id}")
            ->assertSuccessful();

        $this->assertSame(0, StaffAttendance::count());
    }

    public function test_row_in_approved_run_is_kept_with_instructions(): void
    {
        $row = $this->systemRow('2026-08-17');
        $run = $this->runUsing($row, PayrollRun::STATUS_APPROVED);

        $this->clean()
            ->expectsOutputToContain("used by APPROVED run(s) #{$run->id}")
            ->assertSuccessful();

        $this->assertSame(1, StaffAttendance::count());
    }

    public function test_row_in_finalized_run_is_kept_and_refund_suggested(): void
    {
        $locked = $this->systemRow('2026-08-17');
        $this->runUsing($locked, PayrollRun::STATUS_FINALIZED);
        $free = $this->systemRow('2026-08-24');

        $this->clean()
            ->expectsOutputToContain('Suggested refunds')
            ->expectsOutputToContain('Removed 1 attendance row(s)')
            ->assertSuccessful();

        $this->assertTrue(StaffAttendance::whereKey($locked->id)->exists());
        $this->assertFalse(StaffAttendance::whereKey($free->id)->exists());

        $csv = Storage::disk('local')->get(Storage::disk('local')->files('attendance-cleanup')[0]);
        $this->assertStringContainsString('keep (suggested refund)', $csv);
    }

    public function test_refund_amount_is_the_deducted_amount(): void
    {
        $row = $this->systemRow('2026-08-17');
        $this->runUsing($row, PayrollRun::STATUS_RELEASED);

        $this->clean(['--dry-run' => true])
            ->expectsOutputToContain('Suggested refunds')
            ->expectsOutputToContain('600.00')
            ->assertSuccessful();
    }
}
