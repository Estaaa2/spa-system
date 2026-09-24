<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class StaffRecurringItem extends Model
{
    use HasFactory;

    public const KIND_EARNING   = 'earning';
    public const KIND_DEDUCTION = 'deduction';

    public const FREQ_PER_CUTOFF         = 'per_cutoff';
    public const FREQ_PER_DAY_WORKED     = 'per_day_worked';
    public const FREQ_SECOND_CUTOFF_ONLY = 'second_cutoff_only';

    protected $fillable = [
        'staff_id',
        'kind',
        'component_code',
        'label',
        'amount',
        'frequency',
        'start_date',
        'end_date',
        'authorization_ref',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_active'  => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $item->assertValid();
        });
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    protected function assertValid(): void
    {
        $errors = [];

        if ($this->kind === self::KIND_DEDUCTION && blank($this->authorization_ref)) {
            $errors['authorization_ref'] =
                'A written authorization reference is required for deductions (Labor Code Art. 113).';
        }

        if ($this->amount === null || (float) $this->amount < 0) {
            $errors['amount'] = 'Amount must be zero or more.';
        }

        if (! $this->start_date) {
            $errors['start_date'] = 'A start date is required.';
        } elseif ($this->end_date && $this->end_date->lt($this->start_date)) {
            $errors['end_date'] = 'End date cannot be before the start date.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}