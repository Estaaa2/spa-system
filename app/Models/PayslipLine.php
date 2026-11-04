<?php

namespace App\Models;

use App\Exceptions\PayrollStateException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

// Payslip lines (payroll v3, table 5a). One per payslip per component.
class PayslipLine extends Model
{
    use HasFactory;

    public const KIND_EARNING        = 'earning';
    public const KIND_DEDUCTION      = 'deduction';
    public const KIND_EMPLOYER_SHARE = 'employer_share';

    public const SOURCE_BOOKING        = 'booking';
    public const SOURCE_ATTENDANCE     = 'attendance';
    public const SOURCE_RECURRING_ITEM = 'recurring_item';

    protected $fillable = [
        'payslip_id',
        'component_code',
        'label',
        'kind',
        'branch_id',
        'quantity',
        'rate',
        'amount',
        'source_type',
        'source_id',
        'is_manual',
        'created_by',
        'note',
    ];

    protected $casts = [
        'quantity'  => 'decimal:2',
        'rate'      => 'decimal:4',
        'amount'    => 'decimal:2',
        'is_manual' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $l) => self::assertPayslipRunIsDraft($l->payslip_id));

        static::updating(function (self $l) {
            self::assertPayslipRunIsDraft($l->getOriginal('payslip_id'));
            if ($l->isDirty('payslip_id')) {
                self::assertPayslipRunIsDraft($l->payslip_id);
            }
        });

        static::deleting(fn (self $l) => self::assertPayslipRunIsDraft($l->getOriginal('payslip_id')));

        static::saving(function (self $l) {
            if ($l->is_manual && $l->created_by === null) {
                throw ValidationException::withMessages([
                    'created_by' => 'Manual payslip lines must record who created them.',
                ]);
            }
            if (($l->source_type === null) !== ($l->source_id === null)) {
                throw ValidationException::withMessages([
                    'source_id' => 'source_type and source_id must both be set or both be empty.',
                ]);
            }
        });
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    /** Branch where the line was earned (supports deployments). */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // ── Guard ────────────────────────────────────────────────────────────────

    protected static function assertPayslipRunIsDraft($payslipId): void
    {
        $runId = $payslipId
            ? Payslip::query()->whereKey($payslipId)->value('payroll_run_id')
            : null;

        Payslip::assertRunIsDraft($runId);
    }
}