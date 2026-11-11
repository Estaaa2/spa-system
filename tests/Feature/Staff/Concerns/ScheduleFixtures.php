<?php

namespace Tests\Feature\Staff\Concerns;

use App\Models\Branch;
use App\Models\LeaveRequest;
use App\Models\OperatingHours;
use App\Models\StaffWorkSchedule;
use Tests\Feature\Payroll\Concerns\PayrollFixtures;

/**
 * Staff scheduling Phase 2 fixtures on top of the payroll world
 * (spa, branch A/B, $this->staff on branch A, $this->admin = owner).
 */
trait ScheduleFixtures
{
    use PayrollFixtures;

    /** Opening hours for every weekday; $closed weekdays are closed. */
    protected function hours(Branch $branch, string $open = '09:00:00', string $close = '18:00:00', array $closed = []): void
    {
        foreach (StaffWorkSchedule::WEEKDAYS as $day) {
            OperatingHours::updateOrCreate(
                ['branch_id' => $branch->id, 'day_of_week' => $day],
                ['opening_time' => $open, 'closing_time' => $close, 'is_closed' => in_array($day, $closed, true)]
            );
        }
    }

    protected function approvedLeave(string $from, string $to): LeaveRequest
    {
        return LeaveRequest::create([
            'user_id' => $this->user->id, 'spa_id' => $this->spa->id, 'branch_id' => $this->branchA->id,
            'leave_type' => 'vacation', 'start_date' => $from, 'end_date' => $to,
            'reason' => 'Trip', 'status' => 'approved',
        ]);
    }

    /** Pattern: every day on branch hours except the given rest days / custom times. */
    protected function pattern(array $rest = ['Sunday'], array $custom = []): array
    {
        $days = [];
        foreach (StaffWorkSchedule::WEEKDAYS as $d) {
            $days[$d] = in_array($d, $rest, true)
                ? ['mode' => 'rest']
                : (isset($custom[$d]) ? ['mode' => 'custom', 'start' => $custom[$d][0], 'end' => $custom[$d][1]] : ['mode' => 'branch']);
        }

        return $days;
    }
}
