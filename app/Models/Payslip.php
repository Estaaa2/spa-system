<?php

namespace App\Models;

use App\Exceptions\PayrollStateException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Payslips (payroll v3, table 3). A payslip is a snapshot of a staff's pay profile and recurring items at the time of payroll run. 
// It is immutable once the payroll run is no longer draft.
class Payslip extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_run_id',
        'staff_id',
        'home_branch_id',
        'gross_pay',
        'total_deductions',
        'net_pay',
        'taxable_compensation',
        'days_worked',
        'is_mwe',
        'snapshot',
    ];

    protected $casts = [
        'gross_pay'            => 'decimal:2',
        'total_deductions'     => 'decimal:2',
        'net_pay'              => 'decimal:2',
        'taxable_compensation' => 'decimal:2',
        'days_worked'          => 'decimal:2',
        'is_mwe'               => 'boolean',
        'snapshot'             => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $p) => self::assertRunIsDraft($p->payroll_run_id));

        static::updating(function (self $p) {
            self::assertRunIsDraft($p->getOriginal('payroll_run_id'));
            if ($p->isDirty('payroll_run_id')) {
                self::assertRunIsDraft($p->payroll_run_id);
            }
        });

        static::deleting(fn (self $p) => self::assertRunIsDraft($p->getOriginal('payroll_run_id')));
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function homeBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'home_branch_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class);
    }

    // ── Guard ────────────────────────────────────────────────────────────────

    /** Throws unless the given run exists and is draft. Reads status fresh from the DB. */
    public static function assertRunIsDraft($payrollRunId): void
    {
        $status = $payrollRunId
            ? PayrollRun::query()->whereKey($payrollRunId)->value('status')
            : null;

        if ($status !== PayrollRun::STATUS_DRAFT) {
            throw new PayrollStateException(
                'Payslips can only be changed while their payroll run is draft'
                . ($status ? " (run is {$status})." : '.')
            );
        }
    }
}