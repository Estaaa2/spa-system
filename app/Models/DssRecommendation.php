<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Decision Support recommendation and the decision taken on it (DSS revamp).
 * Written by App\Services\DecisionSupport\RecommendationEngine::sync().
 */
class DssRecommendation extends Model
{
    public const OPEN      = 'open';
    public const ACCEPTED  = 'accepted';
    public const DISMISSED = 'dismissed';
    public const EXPIRED   = 'expired';   // the situation went away before anyone decided

    public const SCOPE_BRANCH = 'branch';
    public const SCOPE_SPA    = 'spa';     // R6: owner only

    public const PRIORITY_ORDER = ['high' => 0, 'medium' => 1, 'low' => 2];

    public const DISMISS_REASONS = [
        'already_handled' => 'Already handled',
        'not_accurate'    => 'Not accurate',
        'not_now'         => 'Not now',
        'other'           => 'Other',
    ];

    public const RULE_LABELS = [
        'r1_staffing'    => 'Staffing (next 7 days)',
        'r2_hire'        => 'Hire more therapists',
        'r3_overstaffed' => 'Over-staffed day',
        'r4_promo'       => 'Treatment promo',
        'r6_expansion'   => 'Open another branch',
    ];

    protected $fillable = [
        'spa_id', 'branch_id', 'scope', 'rule_key', 'fingerprint', 'priority',
        'title', 'message', 'details', 'action', 'status', 'dismiss_reason',
        'note', 'decided_by', 'decided_at', 'first_seen_at', 'last_seen_at',
    ];

    protected $casts = [
        'details'       => 'array',
        'action'        => 'array',
        'decided_at'    => 'datetime',
        'first_seen_at' => 'datetime',
        'last_seen_at'  => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withoutGlobalScopes();
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * What $user may see for $branchId: branch-level items for that branch, plus
     * spa-level items (R6) for the owner only (Q7 A). Status is not filtered here.
     */
    public function scopeVisibleTo(Builder $query, User $user, ?int $branchId): Builder
    {
        $isOwner = $user->hasRole('owner');

        return $query->where('spa_id', $user->spa_id)
            ->where(function (Builder $q) use ($branchId, $isOwner) {
                $q->where(fn (Builder $b) => $b->where('scope', self::SCOPE_BRANCH)->where('branch_id', $branchId));
                if ($isOwner) {
                    $q->orWhere('scope', self::SCOPE_SPA);
                }
            });
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::OPEN);
    }

    /** High first, then medium, then low; newest first within a priority. Portable SQL. */
    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->orderByDesc('first_seen_at')
            ->orderByDesc('id');
    }

    public function ruleLabel(): string
    {
        return self::RULE_LABELS[$this->rule_key] ?? $this->rule_key;
    }

    public function dismissReasonLabel(): ?string
    {
        return $this->dismiss_reason ? (self::DISMISS_REASONS[$this->dismiss_reason] ?? $this->dismiss_reason) : null;
    }
}
