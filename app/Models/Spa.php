<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Spa extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Per-instance cache of every paid subscription row.
     *
     * hasAccess(), hasFeature(), currentPlan() and canStartTrial() all read
     * it, and the landing page calls them for every spa. When the
     * "subscriptions" relation is already eager-loaded it is reused, so a
     * listing page costs no extra query per spa.
     */
    private ?Collection $paidPeriodsCache = null;

    /** Per-instance cache for branchIdsWithinPlanLimit(). */
    private ?array $branchIdsWithinPlanLimitCache = null;

    protected $fillable = [
        'owner_id',
        'name',
        'business_tier',
        'verification_status',
        'verification_remarks',
        'verified_at',
        'verified_by',
        'trial_plan',
        'trial_started_at',
        'trial_ends_at',
        'trial_used',
        'trial_reminder_sent_at',
        'plan_swap_used_at',
        'payroll_first_cutoff_day',
        'payroll_pay_day_offset',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'trial_started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'trial_used' => 'boolean',
        'trial_reminder_sent_at' => 'datetime',
        'plan_swap_used_at' => 'datetime',
        'payroll_first_cutoff_day' => 'integer',
        'payroll_pay_day_offset' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Landing page (D11): the owner's first and last name only — no middle
     * name, photo or contact details. Eager-load `owner` for lists.
     */
    public function ownerDisplayName(): ?string
    {
        $name = trim(($this->owner?->first_name ?? '').' '.($this->owner?->last_name ?? ''));

        return $name !== '' ? $name : null;
    }

    /** The spa's business documents were approved by a Levictas admin. */
    public function isBusinessVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    /** Columns the landing page needs from the owner (for eager loading). */
    public const OWNER_PUBLIC_COLUMNS = 'owner:id,first_name,last_name';

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function verificationDocuments(): HasMany
    {
        return $this->hasMany(SpaVerificationDocument::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Paid periods
    |--------------------------------------------------------------------------
    |
    | Access is decided by dates, not by the newest row: a paid row counts
    | from starts_at until expires_at. Rows can be queued (early renewal,
    | payment during the trial), so the newest row is not always the one in
    | effect.
    |
    */

    /** Every paid row, including ones credited to an upgrade, ordered by start. */
    public function paidPeriods(): Collection
    {
        if ($this->paidPeriodsCache !== null) {
            return $this->paidPeriodsCache;
        }

        $rows = $this->relationLoaded('subscriptions')
            ? $this->subscriptions->where('payment_status', 'paid')
            : $this->subscriptions()->where('payment_status', 'paid')->get();

        return $this->paidPeriodsCache = $rows
            ->sortBy(fn (Subscription $row) => [
                $row->starts_at?->getTimestamp() ?? 0,
                $row->id,
            ])
            ->values();
    }

    /** Paid rows that still count for access. */
    public function countablePeriods(): Collection
    {
        return $this->paidPeriods()
            ->filter(fn (Subscription $row) => $row->superseded_at === null)
            ->values();
    }

    /**
     * Clears the cached rows. Call after creating or updating a
     * subscription in the same request.
     */
    public function forgetSubscriptionCache(): void
    {
        $this->paidPeriodsCache = null;
        $this->branchIdsWithinPlanLimitCache = null;
        $this->unsetRelation('subscriptions');
    }

    /** The paid period running right now, or null. */
    public function currentPeriod(): ?Subscription
    {
        return $this->countablePeriods()
            ->filter(fn (Subscription $row) => $row->isInEffect())
            ->last();
    }

    /** Paid periods that start in the future, earliest first. */
    public function scheduledPeriods(): Collection
    {
        return $this->countablePeriods()
            ->filter(fn (Subscription $row) => $row->isScheduled())
            ->values();
    }

    /** The most recent paid period that has already ended, or null. */
    public function lastEndedPeriod(): ?Subscription
    {
        return $this->countablePeriods()
            ->filter(fn (Subscription $row) =>
                $row->expires_at !== null && $row->expires_at->lte(now()))
            ->sortBy(fn (Subscription $row) => $row->expires_at->getTimestamp())
            ->last();
    }

    /**
     * End of all paid time, including queued periods.
     * Null when nothing is paid, or when a legacy row has no expiry
     * (check hasUnlimitedPeriod()).
     */
    public function paidThroughAt(): ?Carbon
    {
        $ends = $this->countablePeriods()
            ->map(fn (Subscription $row) => $row->expires_at)
            ->filter();

        return $ends->isEmpty() ? null : $ends->max()->copy();
    }

    /** Legacy rows without an expiry date grant permanent access. */
    public function hasUnlimitedPeriod(): bool
    {
        return $this->countablePeriods()
            ->contains(fn (Subscription $row) => $row->expires_at === null);
    }

    /**
     * When a newly bought period should start: right after the trial
     * and after every period already paid for, never in the past.
     */
    public function nextPeriodStartsAt(): Carbon
    {
        $start = now();

        if ($this->isTrialing() && $this->trial_ends_at->gt($start)) {
            $start = $this->trial_ends_at->copy();
        }

        $paidThrough = $this->paidThroughAt();

        if ($paidThrough && $paidThrough->gt($start)) {
            $start = $paidThrough;
        }

        return $start;
    }

    public function hasEverPaid(): bool
    {
        return $this->paidPeriods()->isNotEmpty();
    }

    /**
     * Newest paid row by id.
     *
     * @deprecated Kept for older callers. It is not always the period in
     *             effect; use currentPeriod() for access decisions.
     */
    public function latestPaidSubscription(): ?Subscription
    {
        return $this->paidPeriods()->sortBy('id')->last();
    }

    /** The paid period currently determining access. */
    public function activeSubscription(): ?Subscription
    {
        return $this->currentPeriod();
    }

    /*
    |--------------------------------------------------------------------------
    | Access
    |--------------------------------------------------------------------------
    */

    /**
     * The single access rule for a spa. Access is available during:
     * - the one-time free trial;
     * - a paid period in effect; or
     * - the grace period after the last paid period ended.
     */
    public function hasAccess(): bool
    {
        return $this->isTrialing()
            || $this->currentPeriod() !== null
            || $this->isInGracePeriod();
    }

    public function isLocked(): bool
    {
        return ! $this->hasAccess();
    }

    /**
     * Same as hasAccess().
     *
     * @deprecated Name kept for existing callers.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->hasAccess();
    }

    /** No trial and no paid period running, but still inside the grace days. */
    public function isInGracePeriod(): bool
    {
        if ($this->isTrialing() || $this->currentPeriod() !== null) {
            return false;
        }

        $endsAt = $this->graceEndsAt();

        return $endsAt !== null && $endsAt->isFuture();
    }

    /** When the grace period after the last ended paid period runs out. */
    public function graceEndsAt(): ?Carbon
    {
        $last = $this->lastEndedPeriod();

        return $last
            ? $last->expires_at->copy()->addDays((int) config('subscription.grace_days', 3))
            : null;
    }

    /** "basic", "premium", or "expired". */
    public function currentPlan(): string
    {
        $period = $this->currentPeriod();

        if ($period) {
            return self::normalisePlanKey($period->business_tier);
        }

        if ($this->isTrialing()) {
            return $this->trialPlan();
        }

        if ($this->isInGracePeriod()) {
            return self::normalisePlanKey($this->lastEndedPeriod()?->business_tier);
        }

        return 'expired';
    }

    public function hasFeature(string $feature): bool
    {
        if (! $this->hasAccess()) {
            return false;
        }

        return in_array(
            $feature,
            config("plans.{$this->currentPlan()}.features", []),
            true
        );
    }

    public function planLimit(string $limit): int
    {
        return (int) config(
            "plans.{$this->currentPlan()}.max_{$limit}",
            0
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Free trial
    |--------------------------------------------------------------------------
    */

    /** Verified, never trialed, never paid. */
    public function canStartTrial(): bool
    {
        return $this->verification_status === 'verified'
            && $this->trial_used === false
            && ! $this->hasEverPaid();
    }

    public function isTrialing(): bool
    {
        return $this->trial_used === true
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    /**
     * The plan the trial runs on. Every trial gives full access, so this
     * is the configured trial plan; spas.trial_plan is kept only as a
     * record of what the owner picked under the old rules.
     */
    public function trialPlan(): string
    {
        $plan = self::normalisePlanKey(config('subscription.trial_plan', 'premium'));

        return $plan === 'expired' ? 'premium' : $plan;
    }

    /** Whole days left, rounded up (shows 1 on the last day, not 0). */
    public function trialDaysLeft(): int
    {
        if (! $this->isTrialing()) {
            return 0;
        }

        return self::daysUntil($this->trial_ends_at);
    }

    /** Admin extensions of the free trial, newest first. */
    public function trialExtensions(): HasMany
    {
        return $this->hasMany(TrialExtension::class)->latest('id');
    }

    /** Total days an admin has added to this spa's trial. */
    public function extendedTrialDays(): int
    {
        return (int) $this->trialExtensions()->sum('days');
    }

    /*
    |--------------------------------------------------------------------------
    | Billing state (admin and status displays)
    |--------------------------------------------------------------------------
    */

    /**
     * One state for status badges:
     * trial   free trial running
     * active  a paid period is running
     * grace   paid time ended, inside the grace days
     * locked  had a trial or paid before, no access now
     * new     never started a trial or paid
     */
    public function billingState(): string
    {
        return match (true) {
            $this->isTrialing() => 'trial',
            $this->currentPeriod() !== null => 'active',
            $this->isInGracePeriod() => 'grace',
            $this->trial_used === true || $this->hasEverPaid() => 'locked',
            default => 'new',
        };
    }

    /**
     * When the spa loses access: the grace end while in grace, now when
     * already locked, null while it has access through a trial or paid time.
     */
    public function lockDate(): ?Carbon
    {
        return match ($this->billingState()) {
            'grace' => $this->graceEndsAt(),
            'locked' => now(),
            default => null,
        };
    }

    /**
     * Upcoming customer bookings that fall on or after the lock date, so
     * the owner can contact those customers. Existing bookings are never
     * cancelled by a lapse. Null when the spa is not lapsing.
     */
    public function bookingsAffectedByLock(): ?\Illuminate\Database\Eloquent\Builder
    {
        $lockDate = $this->lockDate();

        if ($lockDate === null) {
            return null;
        }

        $from = $lockDate->isPast() ? today() : $lockDate->copy()->startOfDay();

        return Booking::query()
            ->where('spa_id', $this->id)
            ->whereIn('status', ['reserved', 'pending'])
            ->whereDate('appointment_date', '>=', $from->toDateString())
            ->orderBy('appointment_date')
            ->orderBy('start_time');
    }

    /**
     * Plain-text plan line, e.g. "Premium (trial until Nov 15, 2026)" or
     * "Basic, paid through Dec 01, 2026".
     */
    public function billingSummary(): string
    {
        $planName = fn (string $key) => config("plans.{$key}.name", ucfirst($key));
        $date = fn (?Carbon $at) => $at?->format('M d, Y');

        return match ($this->billingState()) {
            'trial' => sprintf('%s (free trial until %s)', $planName($this->trialPlan()), $date($this->trial_ends_at)),
            'active' => $this->hasUnlimitedPeriod()
                ? sprintf('%s, no end date', $planName($this->currentPlan()))
                : sprintf('%s, paid through %s', $planName($this->currentPlan()), $date($this->paidThroughAt())),
            'grace' => sprintf('%s, ended %s (grace until %s)', $planName($this->currentPlan()), $date($this->lastEndedPeriod()?->expires_at), $date($this->graceEndsAt())),
            'locked' => 'No plan (access locked)',
            default => 'No plan yet',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Plan limits
    |--------------------------------------------------------------------------
    */

    public function canAddBranch(): bool
    {
        if (! $this->hasAccess()) {
            return false;
        }

        $limit = $this->planLimit('branches');

        if ($limit <= 0) {
            return false;
        }

        $activeBranchCount = $this->branches()
            ->whereNull('deleted_at')
            ->count();

        return $activeBranchCount < $limit;
    }

    public function mainBranch(): ?Branch
    {
        return $this->branches()
            ->orderByDesc('is_main')
            ->orderBy('id')
            ->first();
    }

    /**
     * IDs of the branches the current plan covers: the main branch first,
     * then the branches the owner chose to keep (Plan limits page), then the
     * oldest, up to the plan's branch limit.
     *
     * Nothing else is stored. After a downgrade the extra branches drop out
     * of this list, and after an upgrade they come back automatically.
     */
    public function branchIdsWithinPlanLimit(): array
    {
        if ($this->branchIdsWithinPlanLimitCache !== null) {
            return $this->branchIdsWithinPlanLimitCache;
        }

        $limit = $this->hasAccess()
            ? $this->planLimit('branches')
            : 0;

        if ($limit <= 0) {
            return $this->branchIdsWithinPlanLimitCache = [];
        }

        return $this->branchIdsWithinPlanLimitCache = $this->branches()
            ->orderByDesc('is_main')
            ->orderByDesc('keep_active')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function isBranchWithinPlanLimit(Branch $branch): bool
    {
        return in_array(
            (int) $branch->id,
            $this->branchIdsWithinPlanLimit(),
            true
        );
    }

    public function canAddStaff(): bool
    {
        if (! $this->hasAccess()) {
            return false;
        }

        $limit = $this->planLimit('staff');

        if ($limit <= 0) {
            return false;
        }

        return Staff::query()
            ->where('spa_id', $this->id)
            ->where('employment_status', 'active')
            ->whereNull('deleted_at')
            ->count() < $limit;
    }

    /** Staff paused because the spa is over its plan's staff limit. */
    public function planPausedStaffCount(): int
    {
        return Staff::query()
            ->where('spa_id', $this->id)
            ->whereNotNull('plan_paused_at')
            ->whereNull('deleted_at')
            ->count();
    }

    /** Branches locked because the spa has more branches than its plan allows. */
    public function branchesOverPlanLimitCount(): int
    {
        if (! $this->hasAccess()) {
            return 0;
        }

        return max(
            0,
            $this->branches()->whereNull('deleted_at')->count() - count($this->branchIdsWithinPlanLimit())
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Plan-limit swaps
    |--------------------------------------------------------------------------
    |
    | When the spa is over its limits, the owner may pause something active
    | to activate something else once per billing period (or once during the
    | trial). Without this, paused branches and staff could be rotated in and
    | out to use more than the plan allows. Filling a genuinely free slot is
    | not a swap and is always allowed.
    |
    */

    /** Start of the period the swap allowance belongs to. */
    public function planSwapPeriodStart(): ?Carbon
    {
        if ($period = $this->currentPeriod()) {
            return $period->starts_at ?? $period->created_at;
        }

        if ($this->isTrialing()) {
            return $this->trial_started_at;
        }

        if ($this->isInGracePeriod()) {
            $last = $this->lastEndedPeriod();

            return $last?->starts_at ?? $last?->created_at;
        }

        return null;
    }

    public function canSwapPlanSelection(): bool
    {
        if ($this->plan_swap_used_at === null) {
            return true;
        }

        $start = $this->planSwapPeriodStart();

        return $start === null || $this->plan_swap_used_at->lt($start);
    }

    /** When the next swap becomes available, or null when one is available now. */
    public function nextPlanSwapAt(): ?Carbon
    {
        if ($this->canSwapPlanSelection()) {
            return null;
        }

        if ($period = $this->currentPeriod()) {
            return $period->expires_at;
        }

        if ($this->isTrialing()) {
            return $this->trial_ends_at;
        }

        return null;
    }

    public function isOverPlanLimits(): bool
    {
        return $this->planPausedStaffCount() > 0 || $this->branchesOverPlanLimitCount() > 0;
    }

    /**
     * Compatibility method.
     *
     * @deprecated Use hasFeature() or currentPlan() instead.
     */
    public function isProfessional(): bool
    {
        return $this->hasFeature('manpower');
    }

    /*
    |--------------------------------------------------------------------------
    | Verification documents
    |--------------------------------------------------------------------------
    */

    public function hasCompleteVerificationDocuments(): bool
    {
        $required = [
            'government_id',
            'dti_sec',
            'bir_certificate',
            'business_permit',
        ];

        $mainBranchId = $this->mainBranch()?->id;

        // Count spa-level documents plus the main branch's own documents.
        // Documents of additional branches must not count for the spa.
        $uploaded = $this->verificationDocuments()
            ->where(function ($query) use ($mainBranchId) {
                $query->whereNull('branch_id');

                if ($mainBranchId) {
                    $query->orWhere('branch_id', $mainBranchId);
                }
            })
            ->pluck('document_type')
            ->unique()
            ->toArray();

        return count(array_intersect($required, $uploaded)) === count($required);
    }

    /** Documents that belong to the spa itself (Government ID, DTI/SEC). */
    public function spaDocuments(): HasMany
    {
        return $this->verificationDocuments()
            ->whereNull('branch_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Maps any stored plan value to a current plan key.
     * Legacy "professional" and "business" are now "premium".
     */
    public static function normalisePlanKey(?string $plan): string
    {
        return match (strtolower((string) $plan)) {
            'basic' => 'basic',
            'premium', 'professional', 'business' => 'premium',
            default => 'expired',
        };
    }

    /** Whole days until $date, rounded up; 0 when it has passed. */
    public static function daysUntil(?Carbon $date): int
    {
        if ($date === null) {
            return 0;
        }

        $seconds = now()->diffInSeconds($date, false);

        return $seconds <= 0 ? 0 : (int) ceil($seconds / 86400);
    }
}
