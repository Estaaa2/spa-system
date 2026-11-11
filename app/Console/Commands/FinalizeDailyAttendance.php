<?php

namespace App\Console\Commands;

use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Services\Attendance\NonWorkingDays;
use Illuminate\Console\Command;

class FinalizeDailyAttendance extends Command
{
    protected $signature = 'attendance:finalize-day';

    protected $description = 'Auto-closes forgotten clock-outs and marks unrecorded active staff absent (or on leave) for today. Skips staff whose branch is closed today or whose pay-profile rest day is today.';

    public function handle(NonWorkingDays $nonWorking): int
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

        // 2) Active staff with NO record at all: absent, or on leave when an approved
        //    leave covers today. No row on a non-working day (branch closed, or a
        //    pay-profile rest day) — staff scheduling Phase 1, decisions Q1/Q2: a
        //    non-working day is neither an absence nor a leave day.
        //    staff.branch_id is where the staff member works today: deployments move
        //    it at 00:05 (deployments:process), before this job runs at 23:55.
        $staffWithRecordIds = StaffAttendance::whereDate('date', $today)->pluck('staff_id');

        $skipped = 0;

        Staff::where('employment_status', 'active')
            ->whereNotIn('id', $staffWithRecordIds)
            ->get()
            ->each(function (Staff $staff) use ($today, $nonWorking, &$skipped) {
                if ($staff->branch_id && $nonWorking->reason($staff, (int) $staff->branch_id, $today) !== null) {
                    $skipped++;

                    return;
                }

                $onLeave = in_array(
                    $staff->user_id,
                    LeaveRequest::approvedUserIdsOnDate($staff->spa_id, $staff->branch_id, $today)
                );

                StaffAttendance::create([
                    'staff_id'  => $staff->id,
                    'spa_id'    => $staff->spa_id,
                    'branch_id' => $staff->branch_id,
                    'date'      => $today,
                    'status'    => $onLeave ? 'on_leave' : 'absent',
                    'source'    => 'system',
                ]);
            });

        $this->info('Attendance finalized for ' . $today . ($skipped ? " ({$skipped} staff on a rest day or closed day — no row written)" : ''));

        return self::SUCCESS;
    }
}
