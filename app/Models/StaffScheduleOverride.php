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

    /** How the change counts for pay (Phase 4, Q2 A). See the add_kind migration. */
    public const KIND_UNPAID = 'unpaid';
    public const KIND_SWAP = 'swap';

    protected $fillable = ['staff_id', 'date', 'type', 'kind', 'paired_override_id', 'start_time', 'end_time', 'reason', 'created_by'];

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

    public function isSwap(): bool
    {
        return $this->kind === self::KIND_SWAP;
    }

    /** The other half of a rest-day swap. */
    public function paired(): BelongsTo
    {
        return $this->belongsTo(self::class, 'paired_override_id');
    }

    /** "Day off (unpaid)", "Rest-day swap: day off", "Rest-day swap: works", "Shift". */
    public function kindLabel(): string
    {
        return match (true) {
            $this->isSwap() && $this->isDayOff() => 'Rest-day swap: day off',
            $this->isSwap()                      => 'Rest-day swap: works',
            $this->isDayOff()                    => 'Day off (unpaid)',
            default                              => 'Shift',
        };
    }
}
