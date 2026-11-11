<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-off change to a staff member's schedule on a single date: a day off,
 * or a shift (changed hours on a workday, or an extra shift on a rest day).
 * Applies immediately (Phase 2, Q4). One per staff member per date.
 */
class StaffScheduleOverride extends Model
{
    public const DAY_OFF = 'day_off';
    public const SHIFT = 'shift';

    protected $fillable = ['staff_id', 'date', 'type', 'start_time', 'end_time', 'reason', 'created_by'];

    protected $casts = ['date' => 'date'];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDayOff(): bool
    {
        return $this->type === self::DAY_OFF;
    }
}
