<?php

namespace App\Console\Commands;

use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffScheduleOverride;
use App\Services\Staff\ScheduledDay;
use App\Services\Staff\WorkScheduleResolver;
use Illuminate\Console\Command;

class FinalizeDailyAttendance extends Command
{
    protected $signature = 'attendance:finalize-day';

    protected $description = 'Auto-closes forgotten clock-outs and marks active staff with no record by their work schedule: absent on a scheduled workday, on leave for approved leave or an unpaid day off, nothing on a rest day, swap day off or closed day.';

    public function handle(WorkScheduleResolver $schedules): int
    {
        $today = today()->toDateString();

        // 1) Auto-close anyone who clocked in but forgot to clock out.
        StaffAttendance::whereDate('date', $today)
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->get()
            ->each(function (StaffAttendance $record) {
                $record->update([
                    'time_out'    => now()->format('H:i:s'),
                    'auto_closed' => true,
                ]);
            });

        // 2) Active staff with NO record today, by their work schedule (staff
        //    scheduling Phase 4; replaces Phase 1's pay-profile / closed-day check):
        //    - scheduled workday                → absent
        //    - approved leave on a workday      → on_leave
        //    - unpaid one-off day off (Q2 A)    → on_leave, remark "Day off (unpaid)"
        //    - rest day, swap day off, closed   → no row (not a working day)
        //    staff.branch_id is where they work today: deployments move it at 00:05
        //    (deployments:process), before this job runs at 23:55.
        $staffWithRecordIds = StaffAttendance::whereDate('date', $today)->pluck('staff_id');

        $staff = Staff::where('employment_status', 'active')
            ->whereNotIn('id', $staffWithRecordIds)
            ->whereNotNull('branch_id')
            ->get();
        $schedules->prime($staff, $today, $today);

        $counts = ['absent' => 0, 'on_leave' => 0, 'skipped' => 0];

        foreach ($staff as $member) {
            $day = $schedules->forDate($member, $today, (int) $member->branch_id);

            // A branch with no opening hours configured at all (test data only) keeps
            // the old rule: every day is a workday.
            $noHours = $day->status === ScheduledDay::CLOSED && ! $schedules->branchHasHours((int) $member->branch_id);

            [$status, $remarks] = match (true) {
                $day->status === ScheduledDay::WORKING || $noHours => ['absent', null],
                $day->status === ScheduledDay::ON_LEAVE => ['on_leave', null],
                $day->status === ScheduledDay::DAY_OFF && $day->kind !== StaffScheduleOverride::KIND_SWAP
                                                        => ['on_leave', 'Day off (unpaid)'.($day->note ? ': '.$day->note : '')],
                default                                 => [null, null],
            };

            if ($status === null) {
                $counts['skipped']++;

                continue;
            }

            StaffAttendance::create([
                'staff_id'  => $member->id,
                'spa_id'    => $member->spa_id,
                'branch_id' => $member->branch_id,
                'date'      => $today,
                'status'    => $status,
                'remarks'   => $remarks !== null ? mb_substr($remarks, 0, 255) : null,
                'source'    => 'system',
            ]);
            $counts[$status]++;
        }

        $this->info(sprintf(
            'Attendance finalized for %s: %d absent, %d on leave, %d not scheduled (rest day, swap day off or closed).',
            $today, $counts['absent'], $counts['on_leave'], $counts['skipped']
        ));

        return self::SUCCESS;
    }
}
