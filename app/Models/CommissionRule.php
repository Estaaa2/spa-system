<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

// Commission rules (payroll v3, table 1). A rule is a snapshot of a spa's commission policy for a given target (treatment/package/default) and branch (or spa-wide).
class CommissionRule extends Model
{
    use HasFactory;

    public const TARGET_TREATMENT = 'treatment';
    public const TARGET_PACKAGE   = 'package';
    public const TARGET_DEFAULT   = 'default';

    public const METHOD_PERCENT = 'percent';
    public const METHOD_FLAT    = 'flat';

    protected $fillable = [
        'spa_id',
        'branch_id',
        'target_type',
        'target_id',
        'method',
        'value',
        'effective_from',
        'effective_to',
        'created_by',
    ];

    protected $casts = [
        'value'          => 'decimal:4',
        'effective_from' => 'date',
        'effective_to'   => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $rule) {
            $rule->assertValid();
        });
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function spa(): BelongsTo
    {
        return $this->belongsTo(Spa::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // ── Scopes / resolution ──────────────────────────────────────────────────

    public function scopeEffectiveOn(Builder $query, $date): Builder
    {
        $d = Carbon::parse($date)->toDateString();

        return $query
            ->where('effective_from', '<=', $d)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $d));
    }

    /**
     * Resolve the rule in effect on $date for a (spa, branch, target).
     *
     * Order (locked): branch+target > spa+target > branch default > spa default.
     * Overlaps are blocked on save, so each tier has at most one match per date;
     * the effective_from/id tie-break only keeps the result deterministic.
     *
     * @param  string    $targetType  treatment | package | default
     * @param  int|null  $targetId    treatments.id / packages.id; ignored for default
     */
    public static function resolveFor(int $spaId, ?int $branchId, string $targetType, ?int $targetId, $date): ?self
    {
        $hasTarget = $targetType !== self::TARGET_DEFAULT && $targetId !== null;

        $candidates = static::query()
            ->where('spa_id', $spaId)
            ->where(function (Builder $q) use ($branchId) {
                $q->whereNull('branch_id');
                if ($branchId !== null) {
                    $q->orWhere('branch_id', $branchId);
                }
            })
            ->where(function (Builder $q) use ($hasTarget, $targetType, $targetId) {
                $q->where('target_type', self::TARGET_DEFAULT);
                if ($hasTarget) {
                    $q->orWhere(fn (Builder $t) => $t->where('target_type', $targetType)->where('target_id', $targetId));
                }
            })
            ->effectiveOn($date)
            ->get();

        return $candidates
            ->sort(function (self $a, self $b) {
                return [self::tier($a), $b->effective_from->getTimestamp(), $b->id]
                   <=> [self::tier($b), $a->effective_from->getTimestamp(), $a->id];
            })
            ->first();
    }

    /** 0 = branch+target, 1 = spa+target, 2 = branch default, 3 = spa default. */
    protected static function tier(self $rule): int
    {
        return ($rule->target_type === self::TARGET_DEFAULT ? 2 : 0)
             + ($rule->branch_id === null ? 1 : 0);
    }

    // ── Validation ───────────────────────────────────────────────────────────

    protected function assertValid(): void
    {
        $errors = [];

        if ($this->target_type === self::TARGET_DEFAULT && $this->target_id !== null) {
            $errors['target_id'] = 'A default rule cannot have a target.';
        }
        if ($this->target_type !== self::TARGET_DEFAULT && $this->target_id === null) {
            $errors['target_id'] = 'Treatment and package rules need a target.';
        }

        if ($this->value === null || (float) $this->value < 0) {
            $errors['value'] = 'Commission value must be zero or more.';
        } elseif ($this->method === self::METHOD_PERCENT && (float) $this->value > 100) {
            $errors['value'] = 'A percentage commission cannot exceed 100%.';
        }

        if (! $this->effective_from) {
            $errors['effective_from'] = 'An effective-from date is required.';
        } elseif ($this->effective_to && $this->effective_to->lt($this->effective_from)) {
            $errors['effective_to'] = 'Effective-to date cannot be before the effective-from date.';
        }

        if ($this->branch_id !== null) {
            $branchSpa = Branch::withTrashed()->whereKey($this->branch_id)->value('spa_id');
            if ((int) $branchSpa !== (int) $this->spa_id) {
                $errors['branch_id'] = 'The branch does not belong to this spa.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        // Serialize concurrent writers for this spa (effective in a transaction).
        Spa::withTrashed()->whereKey($this->spa_id)->lockForUpdate()->first(['id']);

        $clash = static::query()
            ->where('spa_id', $this->spa_id)
            ->when(
                $this->branch_id === null,
                fn (Builder $q) => $q->whereNull('branch_id'),
                fn (Builder $q) => $q->where('branch_id', $this->branch_id)
            )
            ->where('target_type', $this->target_type)
            ->when(
                $this->target_id === null,
                fn (Builder $q) => $q->whereNull('target_id'),
                fn (Builder $q) => $q->where('target_id', $this->target_id)
            )
            ->when($this->exists, fn (Builder $q) => $q->whereKeyNot($this->getKey()))
            ->when(
                $this->effective_to !== null,
                fn (Builder $q) => $q->where('effective_from', '<=', $this->effective_to->toDateString())
            )
            ->where(fn (Builder $q) => $q->whereNull('effective_to')
                ->orWhere('effective_to', '>=', $this->effective_from->toDateString()))
            ->first(['id', 'effective_from', 'effective_to']);

        if ($clash) {
            throw ValidationException::withMessages([
                'effective_from' => sprintf(
                    'This overlaps an existing rule for the same branch and target (%s to %s). End that rule first.',
                    $clash->effective_from->toDateString(),
                    $clash->effective_to?->toDateString() ?? 'open-ended'
                ),
            ]);
        }
    }
}