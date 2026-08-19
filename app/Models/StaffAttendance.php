<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class StaffAttendance extends Model
{
    use HasFactory;

    protected $table = 'staff_attendance';

    protected $fillable = [
        'staff_id', 'spa_id', 'branch_id',
        'date', 'status', 'remarks',
        'time_in', 'time_out', 'marked_by', 'source', 'auto_closed',
    ];

    protected $casts = [
        'date'        => 'date',
        'auto_closed' => 'boolean',
    ];

    public function staff()    { return $this->belongsTo(Staff::class); }
    public function spa()      { return $this->belongsTo(Spa::class); }
    public function branch()   { return $this->belongsTo(Branch::class); }
    public function markedBy() { return $this->belongsTo(User::class, 'marked_by'); }

    public function isClockedIn(): bool
    {
        return !is_null($this->time_in) && is_null($this->time_out);
    }

    // Returns the number of hours worked, as a decimal (e.g. 7.5 for 7 hours 30 minutes).
    public static function resolveStatusForClockIn(int $branchId, string $date, string $clockInTime, int $graceMinutes = 15): string
    {
        $dayOfWeek = Carbon::parse($date)->format('l');

        $hours = OperatingHours::where('branch_id', $branchId)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (!$hours || $hours->is_closed || !$hours->opening_time) {
            return 'present';
        }

        $opening = Carbon::parse($hours->opening_time)->addMinutes($graceMinutes);
        $clockIn = Carbon::parse($clockInTime);

        return $clockIn->gt($opening) ? 'late' : 'present';
    }

    /**
     * Self-service clock-in gate. Decides whether a staff member is allowed
     * to clock themselves in right now, based on the branch's operating
     * hours for that day of week.
     *
     * Deliberately permissive when there is nothing to validate against
     * (no OperatingHours row, or opening/closing time unset) — the goal is
     * to catch the "clocked in at 11PM on a 7AM–9PM branch" case, not to
     * block staff because the schedule data is incomplete.
     *
     * This gate is for the self-service clockIn() action only. Manager/HR
     * corrections via recordFor() intentionally bypass it — that's the
     * override path for legitimate exceptions (forgotten clock-ins,
     * early opens for inventory, etc.), the same way most commercial time
     * clock tools let managers force a punch regardless of restrictions.
     *
     * @return array{allowed: bool, reason: ?string}
     */
    public static function evaluateClockInWindow(int $branchId, string $date, string $clockInTime, int $graceMinutes = 15): array
    {
        $dayOfWeek = Carbon::parse($date)->format('l');

        $hours = OperatingHours::where('branch_id', $branchId)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (!$hours) {
            return ['allowed' => true, 'reason' => null];
        }

        if ($hours->is_closed) {
            return [
                'allowed' => false,
                'reason'  => 'The branch is marked closed today. If this is wrong, ask a manager to log your attendance manually.',
            ];
        }

        if (!$hours->opening_time || !$hours->closing_time) {
            return ['allowed' => true, 'reason' => null];
        }

        $earliestAllowed = Carbon::parse($hours->opening_time)->subMinutes($graceMinutes);
        $closing         = Carbon::parse($hours->closing_time);
        $clockIn         = Carbon::parse($clockInTime);

        if ($clockIn->lt($earliestAllowed)) {
            return [
                'allowed' => false,
                'reason'  => 'Too early to clock in — the branch opens at '
                    . Carbon::parse($hours->opening_time)->format('h:i A')
                    . '. Ask a manager to log this manually if you need to.',
            ];
        }

        if ($clockIn->gt($closing)) {
            return [
                'allowed' => false,
                'reason'  => 'The branch closed at ' . $closing->format('h:i A')
                    . '. Ask a manager to log this manually if you need to.',
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    // Returns a human-readable string describing why a clock-out time is flagged as outside operating hours, or null if it's within hours. 
    // This is for display purposes only; the actual attendance record is still saved.
    public static function flagIfOutsideOperatingHours(int $branchId, string $date, string $clockOutTime): ?string
    {
        $dayOfWeek = Carbon::parse($date)->format('l');

        $hours = OperatingHours::where('branch_id', $branchId)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (!$hours || $hours->is_closed || !$hours->closing_time) {
            return null;
        }

        $closing  = Carbon::parse($hours->closing_time);
        $clockOut = Carbon::parse($clockOutTime);

        if ($clockOut->gt($closing)) {
            return 'Auto-flag: clocked out after branch closing time (' . $closing->format('h:i A') . ').';
        }

        return null;
    }
}