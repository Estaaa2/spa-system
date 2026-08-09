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

    /**
     * Compare a clock-in time against the branch's operating hours for that
     * day to decide present vs late, instead of leaving it as a manual
     * guess. Falls back to 'present' if the branch has no configured hours
     * for that day of week (nothing reliable to compare against).
     */
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
}