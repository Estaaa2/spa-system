<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class StaffPayProfile extends Model
{
    use HasFactory;

    public const BASIS_MONTHLY = 'monthly';
    public const BASIS_DAILY   = 'daily';

    /** Same day-name format as operating_hours.day_of_week (Carbon 'l'). */
    public const DAY_NAMES = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    protected $fillable = [
        'staff_id',
        'effective_from',
        'effective_to',
        'pay_basis',
        'base_rate',
        'commission_enabled',
        'rest_days',
        'created_by',
    ];

    protected $casts = [
        'effective_from'     => 'date',
        'effective_to'       => 'date',
        'base_rate'          => 'decimal:2',
        'commission_enabled' => 'boolean',
        'rest_days'          => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $profile) {
            $profile->assertValid();
        });
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    // ── Scopes / resolution ──────────────────────────────────────────────────

    /** Profiles in effect on $date (inclusive). */
    public function scopeEffectiveOn(Builder $query, $date): Builder
    {
        $d = Carbon::parse($date)->toDateString();

        return $query
            ->where('effective_from', '<=', $d)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $d));
    }

    /** Profiles whose range intersects [$from, $to]; $to NULL = open-ended. */
    public function scopeOverlapping(Builder $query, $from, $to = null): Builder
    {
        $from = Carbon::parse($from)->toDateString();
        $to   = $to ? Carbon::parse($to)->toDateString() : null;

        return $query
            ->when($to !== null, fn (Builder $q) => $q->where('effective_from', '<=', $to))
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from));
    }

    /** The single profile in effect for a staff member on $date, or null. */
    public static function effectiveFor(int $staffId, $date): ?self
    {
        return static::query()
            ->where('staff_id', $staffId)
            ->effectiveOn($date)
            ->orderByDesc('effective_from')
            ->first();
    }

    // ── Validation ───────────────────────────────────────────────────────────

    protected function assertValid(): void
    {
        $errors = [];

        if (! $this->effective_from) {
            $errors['effective_from'] = 'An effective-from date is required.';
        } elseif ($this->effective_to && $this->effective_to->lt($this->effective_from)) {
            $errors['effective_to'] = 'Effective-to date cannot be before the effective-from date.';
        }

        if ($this->base_rate === null || (float) $this->base_rate < 0) {
            $errors['base_rate'] = 'Base rate must be zero or more.';
        }

        $restDays = $this->rest_days;
        if (! is_array($restDays)
            || array_diff($restDays, self::DAY_NAMES) !== []
            || count($restDays) !== count(array_unique($restDays))) {
            $errors['rest_days'] = 'Rest days must be a list of distinct day names (e.g. "Sunday").';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        // Serialize concurrent writers for the same staff member (effective in a transaction).
        Staff::withTrashed()->whereKey($this->staff_id)->lockForUpdate()->first(['id']);

        $clash = static::query()
            ->where('staff_id', $this->staff_id)
            ->when($this->exists, fn (Builder $q) => $q->whereKeyNot($this->getKey()))
            ->overlapping($this->effective_from, $this->effective_to)
            ->first(['id', 'effective_from', 'effective_to']);

        if ($clash) {
            throw ValidationException::withMessages([
                'effective_from' => sprintf(
                    'This range overlaps an existing pay profile (%s to %s).',
                    $clash->effective_from->toDateString(),
                    $clash->effective_to?->toDateString() ?? 'open-ended'
                ),
            ]);
        }
    }
}