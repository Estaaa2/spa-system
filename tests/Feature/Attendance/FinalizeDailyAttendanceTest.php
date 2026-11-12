<?php

namespace Tests\Feature\Attendance;

use App\Models\LeaveRequest;
use App\Models\OperatingHours;
use App\Models\StaffAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Payroll\Concerns\PayrollFixtures;
use Tests\TestCase;

/**
 * Staff scheduling Phase 1 — attendance:finalize-day writes no row on a day the
 * staff member's branch is closed or that is a pay-profile rest day (Q1 A, Q2 A).
 * Calendar (2026): Aug 16 Sun · Aug 17 Mon · Aug 18 Tue.
 */
class FinalizeDailyAttendanceTest extends TestCase
{
    use RefreshDatabase;
    use PayrollFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeWorld();
        // Both branches open every day (Phase 4: no hours row = closed, as for
        // clock-in and booking); closeOn() then marks single days closed.
        foreach ([$this->branchA, $this->branchB] as $branch) {
            foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
                OperatingHours::create(['branch_id' => $branch->id, 'day_of_week' => $day,
                    'opening_time' => '09:00:00', 'closing_time' => '18:00:00', 'is_closed' => false]);
            }
        }
    }

    private function closeOn(string $weekday, $branch = null): void
    {
        OperatingHours::updateOrCreate(
            ['branch_id' => ($branch ?? $this->branchA)->id, 'day_of_week' => $weekday],
            ['opening_time' => '09:00:00', 'closing_time' => '18:00:00', 'is_closed' => true]
        );
    }

    private function finalizeOn(string $date): ?StaffAttendance
    {
        $this->travelTo($date.' 23:55:00');
        $this->artisan('attendance:finalize-day')->assertSuccessful();

        return StaffAttendance::where('staff_id', $this->staff->id)->whereDate('date', $date)->first();
    }

    private function approvedLeave(string $from, string $to): void
    {
        LeaveRequest::create([
            'user_id' => $this->user->id, 'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id,
            'leave_type' => 'vacation', 'start_date' => $from, 'end_date' => $to,
            'reason' => 'Trip', 'status' => 'approved',
        ]);
    }

    public function test_normal_day_without_record_is_absent(): void
    {
        $this->profile();
        $row = $this->finalizeOn('2026-08-18');

        $this->assertNotNull($row);
        $this->assertSame('absent', $row->status);
        $this->assertSame('system', $row->source);
    }

    public function test_branch_closed_day_writes_no_row(): void
    {
        $this->profile();
        $this->closeOn('Monday');

        $this->assertNull($this->finalizeOn('2026-08-17'));
    }

    public function test_pay_profile_rest_day_writes_no_row(): void
    {
        $this->profile(); // rest day Sunday

        $this->assertNull($this->finalizeOn('2026-08-16'));
    }

    public function test_staff_without_pay_profile_has_no_rest_day(): void
    {
        $row = $this->finalizeOn('2026-08-16');

        $this->assertSame('absent', $row?->status);
    }

    public function test_rest_day_follows_the_profile_in_effect_that_day(): void
    {
        $this->profile(['effective_to' => '2026-08-10']); // Sunday rest day, ended
        $this->profile(['effective_from' => '2026-08-11', 'rest_days' => ['Tuesday']]);

        $this->assertSame('absent', $this->finalizeOn('2026-08-16')?->status);
        $this->assertNull($this->finalizeOn('2026-08-18'));
    }

    public function test_approved_leave_on_working_day_is_on_leave(): void
    {
        $this->profile();
        $this->approvedLeave('2026-08-17', '2026-08-18');

        $this->assertSame('on_leave', $this->finalizeOn('2026-08-18')?->status);
    }

    public function test_approved_leave_on_closed_or_rest_day_writes_no_row(): void
    {
        $this->profile();
        $this->closeOn('Monday');
        $this->approvedLeave('2026-08-16', '2026-08-18');

        $this->assertNull($this->finalizeOn('2026-08-16'));   // rest day
        $this->assertNull($this->finalizeOn('2026-08-17'));   // closed
        $this->assertSame('on_leave', $this->finalizeOn('2026-08-18')?->status);
    }

    public function test_deployed_staff_use_the_branch_they_are_at(): void
    {
        $this->profile();
        $this->closeOn('Monday', $this->branchA);              // closed at home only
        $this->staff->update(['branch_id' => $this->branchB->id]); // deployments:process moves branch_id

        $row = $this->finalizeOn('2026-08-17');
        $this->assertSame('absent', $row?->status);
        $this->assertSame($this->branchB->id, $row->branch_id);

        $this->closeOn('Tuesday', $this->branchB);
        $this->assertNull($this->finalizeOn('2026-08-18'));
    }

    public function test_existing_record_is_left_alone_and_open_clock_in_is_closed(): void
    {
        $this->profile();
        $this->closeOn('Monday');
        $row = $this->attend('2026-08-17', 'present', '09:00:00', null);

        $this->finalizeOn('2026-08-17');

        $row->refresh();
        $this->assertSame('present', $row->status);
        $this->assertTrue((bool) $row->auto_closed);
        $this->assertNotNull($row->time_out);
    }

    public function test_inactive_staff_are_skipped(): void
    {
        $this->profile();
        $this->staff->update(['employment_status' => 'inactive']);

        $this->assertNull($this->finalizeOn('2026-08-18'));
    }
}
