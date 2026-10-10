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
        'min_daily_wage',
        'wage_order_ref',
        'min_wage_effective_from',
        'verification_status',
        'verification_remarks',
        'verified_at',
        'verified_by',
        'verification_due_at',
    ];

    protected $casts = [
        'is_main'                     => 'boolean',
        'has_workforce_finance_suite' => 'boolean',
        'min_daily_wage'              => 'decimal:2',
        'min_wage_effective_from'     => 'date',
        'verified_at'                 => 'datetime',
        'verification_due_at'         => 'datetime',
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

    public function productStocks()
    {
        return $this->hasMany(BranchProductStock::class);
    }

    public function productBatches()
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function purchaseRequests()
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function outgoingStockTransfers()
    {
        return $this->hasMany(
            StockTransfer::class,
            'source_branch_id'
        );
    }

    public function incomingStockTransfers()
    {
        return $this->hasMany(
            StockTransfer::class,
            'destination_branch_id'
        );
    }


    // ── Branch verification ───────────────────────────────────────────────────

    /** Documents every branch must upload for itself. */
    public const BRANCH_DOCUMENT_TYPES = [
        'bir_certificate',
        'business_permit',
    ];

    /** Days a branch keeps operating after its Business Permit expires. */
    public const DOCUMENT_GRACE_DAYS = 30;

    public function verificationDocuments(): HasMany
    {
        return $this->hasMany(SpaVerificationDocument::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * The document this branch relies on for a given type.
     *
     * BIR and Business Permit must be the branch's own. Government ID and
     * DTI/SEC fall back to the spa-level document (branch_id is NULL) when
     * the branch has no copy of its own, so they are reused, not duplicated.
     */
    public function documentFor(string $type): ?SpaVerificationDocument
    {
        $own = $this->verificationDocuments()
            ->where('document_type', $type)
            ->first();

        if ($own || in_array($type, self::BRANCH_DOCUMENT_TYPES, true)) {
            return $own;
        }

        return SpaVerificationDocument::query()
            ->where('spa_id', $this->spa_id)
            ->whereNull('branch_id')
            ->where('document_type', $type)
            ->first();
    }

    public function hasRequiredDocuments(): bool
    {
        foreach ([
            'government_id',
            'dti_sec',
            'bir_certificate',
            'business_permit',
        ] as $type) {
            if (! $this->documentFor($type)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Query filter: branches an admin has approved, plus branches that
     * existed before this feature and are still inside their deadline
     * (or are already waiting for the admin's review).
     */
    public function scopeVerificationCleared($query)
    {
        return $query->where(function ($q) {
            $q->where('verification_status', 'verified')
                ->orWhere(function ($legacy) {
                    $legacy->whereNotNull('verification_due_at')
                        ->where(function ($window) {
                            $window->where('verification_due_at', '>', now())
                                ->orWhere('verification_status', 'pending');
                        });
                });
        });
    }

    /** Same rule as scopeVerificationCleared(), for one loaded branch. */
    public function isVerificationCleared(): bool
    {
        if ($this->verification_status === 'verified') {
            return true;
        }

        if ($this->verification_due_at === null) {
            return false;
        }

        return $this->verification_due_at->isFuture()
            || $this->verification_status === 'pending';
    }

    public function hasExpiredDocuments(): bool
    {
        $permit = $this->documentFor('business_permit');

        return $permit !== null
            && $permit->isExpiredPastGrace(self::DOCUMENT_GRACE_DAYS);
    }

    /**
     * Why this branch cannot operate, or null when it can.
     * Values: verification, documents_expired, plan_limit.
     */
    public function lockReason(): ?string
    {
        if (! $this->isVerificationCleared()) {
            return 'verification';
        }

        if ($this->hasExpiredDocuments()) {
            return 'documents_expired';
        }

        if (! $this->spa?->isBranchWithinPlanLimit($this)) {
            return 'plan_limit';
        }

        return null;
    }

    /** The single rule for "can this branch be used, listed and booked". */
    public function isOperational(): bool
    {
        return $this->lockReason() === null;
    }

    public function getUsesWorkforceFinanceSuiteAttribute(): bool
    {
        return (bool) $this->has_workforce_finance_suite;
    }

    public function isClosedOn($date): bool
    {
        $dayName = \Carbon\Carbon::parse($date)->format('l'); // e.g. "Monday"

        $row = $this->operatingHours->firstWhere('day_of_week', $dayName);

        // No row for that day at all → treat as closed (safer default).
        return $row ? (bool) $row->is_closed : true;
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
