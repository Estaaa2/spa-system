<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'spa_id',
        'name',
        'location',
        'is_main',
        'has_workforce_finance_suite',
    ];

    protected $casts = [
        'is_main'                     => 'boolean',
        'has_workforce_finance_suite' => 'boolean',
    ];

    public function spa(): BelongsTo
    {
        return $this->belongsTo(Spa::class);
    }

    public function operatingHours(): HasMany
    {
        return $this->hasMany(OperatingHours::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function staff()
    {
        return $this->hasMany(User::class)->whereHas('roles', function ($q) {
            $q->whereIn('name', ['staff', 'therapist', 'admin']);
        });
    }

    public function profile()
    {
        return $this->hasOne(BranchProfile::class);
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function getUsesWorkforceFinanceSuiteAttribute(): bool
    {
        return (bool) $this->has_workforce_finance_suite;
    }

    // ── Flutter API helpers ───────────────────────────────────────────────────

    /**
     * Returns closed day indices Flutter expects (0=Sun, 1=Mon … 6=Sat).
     * Matches Flutter's: date.weekday % 7
     */
    public function getClosedDaysForApi(): array
    {
        return $this->operatingHours
            ->where('is_closed', true)
            ->map(fn($h) => OperatingHours::dayNameToInt($h->day_of_week))
            ->filter(fn($d) => $d >= 0)
            ->values()
            ->toArray();
    }

    /**
     * Returns opening time as "HH:MM" from the first non-closed day.
     */
    public function getOpenTimeForApi(): string
    {
        $row = $this->operatingHours->where('is_closed', false)->first();
        return $row ? substr($row->opening_time, 0, 5) : '09:00';
    }

    /**
     * Returns closing time as "HH:MM" from the first non-closed day.
     */
    public function getCloseTimeForApi(): string
    {
        $row = $this->operatingHours->where('is_closed', false)->first();
        return $row ? substr($row->closing_time, 0, 5) : '21:00';
    }
}
