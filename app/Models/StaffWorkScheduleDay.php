<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One weekday of a StaffWorkSchedule: a rest day, a workday on the branch's
 * hours (no times), or a workday with its own start and end time.
 */
class StaffWorkScheduleDay extends Model
{
    protected $fillable = ['staff_work_schedule_id', 'day_of_week', 'is_rest_day', 'start_time', 'end_time'];

    protected $casts = ['is_rest_day' => 'boolean'];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(StaffWorkSchedule::class, 'staff_work_schedule_id');
    }

    public function followsBranchHours(): bool
    {
        return ! $this->is_rest_day && ($this->start_time === null || $this->end_time === null);
    }
}
