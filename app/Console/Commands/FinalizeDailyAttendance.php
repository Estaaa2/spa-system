<?php

namespace App\Console\Commands;

use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Models\StaffAttendance;
use Illuminate\Console\Command;

class FinalizeDailyAttendance extends Command
{
    protected $signature = 'attendance:finalize-day';

    protected $description = 'Auto-closes forgotten clock-outs and marks unrecorded active staff absent for today. Scheduled late each day so no attendance day is ever left blank.';

    public function handle(): int
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

        // 2) Mark active staff with NO record at all as absent — unless an
        //    approved leave already covers today.
        $staffWithRecordIds = StaffAttendance::whereDate('date', $today)->pluck('staff_id');

        Staff::where('employment_status', 'active')
            ->whereNotIn('id', $staffWithRecordIds)
            ->get()
            ->each(function (Staff $staff) use ($today) {
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

        $this->info('Attendance finalized for ' . $today);

        return self::SUCCESS;
    }
}