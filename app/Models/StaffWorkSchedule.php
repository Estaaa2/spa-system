<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A staff member's weekly work pattern from effective_from to effective_to
 * (open-ended when null). Staff scheduling Phase 2 (D1). Read it through
 * App\Services\Staff\WorkScheduleResolver, not directly.
 */
class StaffWorkSchedule extends Model
{
    public const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    protected $fillable = ['staff_id', 'effective_from', 'effective_to', 'created_by'];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to'   => 'date',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(StaffWorkScheduleDay::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Schedules in effect on $date (at most one, by construction). */
    public function scopeEffectiveOn(Builder $q, string $date): Builder
    {
        return $q->whereDate('effective_from', '<=', $date)
            ->where(fn ($w) => $w->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }

    public function dayFor(string $weekday): ?StaffWorkScheduleDay
    {
        return $this->days->firstWhere('day_of_week', $weekday);
    }

    /** @return list<string> */
    public function restDays(): array
    {
        return $this->days->where('is_rest_day', true)->pluck('day_of_week')
            ->sortBy(fn ($d) => array_search($d, self::WEEKDAYS, true))->values()->all();
    }
}
