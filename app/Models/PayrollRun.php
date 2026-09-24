<?php

namespace App\Models;

use App\Exceptions\PayrollStateException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

// Payroll runs (payroll v3, table 2). A run is a snapshot of the spa's pay profiles and recurring items at the time of payroll generation.
class PayrollRun extends Model
{
    use HasFactory;

    public const TYPE_REGULAR          = 'regular';
    public const TYPE_THIRTEENTH_MONTH = 'thirteenth_month';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_FINALIZED = 'finalized';
    public const STATUS_RELEASED  = 'released';

    /** from => allowed targets */
    public const TRANSITIONS = [
        self::STATUS_DRAFT     => [self::STATUS_APPROVED],
        self::STATUS_APPROVED  => [self::STATUS_DRAFT, self::STATUS_FINALIZED],
        self::STATUS_FINALIZED => [self::STATUS_RELEASED],
        self::STATUS_RELEASED  => [],
    ];

    /** Columns that must be set when entering a status. */
    protected const TRANSITION_ACTOR = [
        self::STATUS_APPROVED  => ['approved_by', 'approved_at'],
        self::STATUS_FINALIZED => ['finalized_by', 'finalized_at'],
        self::STATUS_RELEASED  => ['released_by', 'released_at'],
    ];

    /** Only these may change on a run that is not draft (and only alongside a status change). */
    protected const TRANSITION_COLUMNS = [
        'status',
        'approved_by', 'approved_at',
        'finalized_by', 'finalized_at',
        'released_by', 'released_at',
        'updated_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected $fillable = [
        'spa_id',
        'run_type',
        'period_start',
        'period_end',
        'cutoff_no',
        'pay_date',
        'status',
        'config_version',
        'generated_by',
        'approved_by',
        'approved_at',
        'finalized_by',
        'finalized_at',
        'released_by',
        'released_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'pay_date'     => 'date',
        'cutoff_no'    => 'integer',
        'approved_at'  => 'datetime',
        'finalized_at' => 'datetime',
        'released_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $run) {
            if ($run->status !== self::STATUS_DRAFT) {
                throw new PayrollStateException('A payroll run must be created as draft.');
            }
        });

        static::saving(function (self $run) {
            $run->assertValidShape();
        });

        static::updating(function (self $run) {
            $run->assertValidUpdate();

            if ($run->getOriginal('status') === self::STATUS_APPROVED && $run->status === self::STATUS_DRAFT) {
                $run->approved_by = null;
                $run->approved_at = null;
            }
        });

        static::deleting(function (self $run) {
            if (! $run->canDelete()) {
                throw new PayrollStateException('Only draft payroll runs can be deleted.');
            }
        });
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function spa(): BelongsTo
    {
        return $this->belongsTo(Spa::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    // ── State helpers ────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canTransitionTo(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function canRegenerate(): bool
    {
        return $this->isDraft();
    }

    public function canDelete(): bool
    {
        return $this->isDraft();
    }

    public function canApprove(): bool
    {
        return $this->canTransitionTo(self::STATUS_APPROVED);
    }

    public function canSendBack(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canFinalize(): bool
    {
        return $this->canTransitionTo(self::STATUS_FINALIZED);
    }

    public function canRelease(): bool
    {
        return $this->canTransitionTo(self::STATUS_RELEASED);
    }

    // ── Guards ───────────────────────────────────────────────────────────────

    protected function assertValidShape(): void
    {
        $errors = [];

        if ($this->run_type === self::TYPE_REGULAR && ! in_array($this->cutoff_no, [1, 2], true)) {
            $errors['cutoff_no'] = 'Regular runs need cutoff 1 or 2.';
        }
        if ($this->run_type === self::TYPE_THIRTEENTH_MONTH && $this->cutoff_no !== null) {
            $errors['cutoff_no'] = '13th-month runs have no cutoff number.';
        }
        if ($this->period_start && $this->period_end && $this->period_end->lt($this->period_start)) {
            $errors['period_end'] = 'Period end cannot be before period start.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function assertValidUpdate(): void
    {
        $from = $this->getOriginal('status');
        $to   = $this->status;

        if ($from !== $to) {
            if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
                throw new PayrollStateException("Invalid payroll run transition: {$from} -> {$to}.");
            }
            foreach (self::TRANSITION_ACTOR[$to] ?? [] as $column) {
                if ($this->{$column} === null) {
                    throw new PayrollStateException("Moving a run to {$to} requires {$column}.");
                }
            }
        }

        if ($from !== self::STATUS_DRAFT) {
            $illegal = array_diff(array_keys($this->getDirty()), self::TRANSITION_COLUMNS);
            if ($from === $to || $illegal !== []) {
                throw new PayrollStateException(
                    "A {$from} payroll run cannot be edited"
                    . ($illegal ? ' (' . implode(', ', $illegal) . ')' : '') . '.'
                );
            }
        }
    }
}