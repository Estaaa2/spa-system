<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Spa extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Per-instance cache for the latest paid subscription.
     *
     * hasAccess(), hasFeature() and currentPlan() each need it, and the
     * landing page calls them for every spa, so without this each spa would
     * trigger the same query several times per request.
     */
    private bool $latestPaidSubscriptionLoaded = false;

    private ?Subscription $latestPaidSubscriptionCache = null;
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
        'payroll_first_cutoff_day',
        'payroll_pay_day_offset',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'trial_started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'trial_used' => 'boolean',
        'payroll_first_cutoff_day' => 'integer',
        'payroll_pay_day_offset' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

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

    /**
     * Returns the newest paid subscription record.
     *
     * Expired records are intentionally included because they are needed
     * to calculate the three-day grace period.
     *
     * The result is cached on this model instance. Call
     * forgetSubscriptionCache() after creating or updating a subscription
     * within the same request.
     */
    public function latestPaidSubscription(): ?Subscription
    {
        if ($this->latestPaidSubscriptionLoaded) {
            return $this->latestPaidSubscriptionCache;
        }

        $this->latestPaidSubscriptionCache = $this->subscriptions()
            ->where('payment_status', 'paid')
            ->whereIn('business_tier', ['basic', 'premium', 'business'])
            ->latest('id')
            ->first();

        $this->latestPaidSubscriptionLoaded = true;

        return $this->latestPaidSubscriptionCache;
    }

    public function forgetSubscriptionCache(): void
    {
        $this->latestPaidSubscriptionLoaded = false;
        $this->latestPaidSubscriptionCache = null;
        $this->branchIdsWithinPlanLimitCache = null;
    }

    public function canStartTrial(): bool
    {
        return $this->verification_status === 'verified'
            && $this->trial_used === false
            && $this->latestPaidSubscription() === null;
    }

    /**
     * Returns the paid subscription currently determining access.
     */
    public function activeSubscription(): ?Subscription
    {
        $subscription = $this->latestPaidSubscription();

        if (!$subscription) {
            return null;
        }

        if ($subscription->expires_at === null) {
            return $subscription;
        }

        return $subscription->expires_at->isFuture()
            ? $subscription
            : null;
    }

    /**
     * Returns true during an active trial or paid access period.
     *
     * The method name is retained for compatibility with existing callers.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->isTrialing() || $this->hasAccess();
    }

    /**
     * The single access rule for a spa.
     *
     * Access is available during:
     * - an active one-time trial;
     * - an unexpired paid period; or
     * - the three-day grace period after the paid period ends.
     */
    public function hasAccess(): bool
    {
        if ($this->isTrialing()) {
            return true;
        }

        $subscription = $this->latestPaidSubscription();

        if (!$subscription) {
            return false;
        }

        if (!$subscription->expires_at) {
            return true;
        }

        if ($subscription->expires_at->isFuture()) {
            return true;
        }

        return $subscription->expires_at
            ->copy()
            ->addDays(3)
            ->isFuture();
    }

    public function isLocked(): bool
    {
        return !$this->hasAccess();
    }

    public function currentPlan(): string
    {
        $subscription = $this->latestPaidSubscription();

        if ($subscription) {
            $plan = $this->normalisePlan($subscription->business_tier);

            if (
                $subscription->expires_at === null
                || $subscription->expires_at->isFuture()
            ) {
                return $plan;
            }
        }

        if ($this->isTrialing()) {
            return $this->normalisePlan($this->trial_plan);
        }

        if (
            $subscription
            && $subscription->expires_at
            && $subscription->expires_at
                ->copy()
                ->addDays(3)
                ->isFuture()
        ) {
            return $this->normalisePlan($subscription->business_tier);
        }

        return 'expired';
    }

    public function hasFeature(string $feature): bool
    {
        if (!$this->hasAccess()) {
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

    public function isTrialing(): bool
    {
        return $this->trial_used === true
            && in_array($this->trial_plan, ['basic', 'premium'], true)
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    public function trialDaysLeft(): int
    {
        if (! $this->isTrialing()) {
            return 0;
        }

        $today = now()->copy()->startOfDay();
        $trialEndsAt = $this->trial_ends_at->copy()->startOfDay();

        return (int) max(
            0,
            $today->diffInDays($trialEndsAt, false)
        );
    }

    public function canAddBranch(): bool
    {
        if (!$this->hasAccess()) {
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
     * then the oldest branches, up to the plan's branch limit.
     *
     * Nothing is stored. After a downgrade the extra branches drop out of
     * this list, and after an upgrade they come back automatically.
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
        if (!$this->hasAccess()) {
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

    /**
     * Compatibility method.
     *
     * @deprecated Use hasFeature() or currentPlan() instead.
     */
    public function isProfessional(): bool
    {
        return $this->hasFeature('manpower');
    }

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

    private function normalisePlan(?string $plan): string
    {
        return match ($plan) {
            'professional' => 'premium',
            'basic', 'premium', 'business' => $plan,
            default => 'expired',
        };
    }
}
