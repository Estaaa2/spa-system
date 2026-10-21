<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row = one checkout attempt and, once paid, one prepaid period.
 *
 * A paid period counts for access from starts_at until expires_at, unless
 * superseded_at is set (its remaining value was credited to an upgrade).
 * Periods can be queued: an early renewal starts when the current period
 * ends, and a payment made during the free trial starts when the trial ends.
 *
 * kind:
 *   new      first paid period (no earlier paid access)
 *   renewal  continues or restarts the same plan (including a cycle change)
 *   upgrade  Basic -> Premium mid-period, priced with credit_amount
 *   downgrade Premium -> Basic, always queued after current paid access
 */
class Subscription extends Model
{
    use HasFactory;

    public const KINDS = ['new', 'renewal', 'upgrade', 'downgrade'];

    protected $fillable = [
        'spa_id',
        'business_tier',
        'kind',
        'amount',
        'credit_amount',
        'billing_cycle',
        'paymongo_checkout_id',
        'paymongo_payment_id',
        'payment_method',
        'payment_status',
        'status',
        'starts_at',
        'expires_at',
        'cancelled_at',
        'superseded_at',
        'expiry_notice_sent_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'superseded_at' => 'datetime',
        'expiry_notice_sent_at' => 'datetime',
    ];

    public function spa()
    {
        return $this->belongsTo(Spa::class);
    }

    /** Paid periods that still count for access (not credited to an upgrade). */
    public function scopeCountable(Builder $query): Builder
    {
        return $query
            ->where('payment_status', 'paid')
            ->whereNull('superseded_at');
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /** Paid, not superseded. */
    public function isCountable(): bool
    {
        return $this->isPaid() && $this->superseded_at === null;
    }

    /** Countable and running at $at (default now). */
    public function isInEffect(?\DateTimeInterface $at = null): bool
    {
        if (! $this->isCountable()) {
            return false;
        }

        $at = $at ? \Illuminate\Support\Carbon::instance($at) : now();

        $started = $this->starts_at === null || $this->starts_at->lte($at);
        $notEnded = $this->expires_at === null || $this->expires_at->gt($at);

        return $started && $notEnded;
    }

    /**
     * One display state for history lists and admin pages:
     * active, scheduled, ended, credited, pending, failed, expired.
     */
    public function state(): string
    {
        if ($this->isPaid()) {
            return match (true) {
                $this->superseded_at !== null => 'credited',
                $this->isScheduled() => 'scheduled',
                $this->isInEffect() => 'active',
                default => 'ended',
            };
        }

        return match ($this->payment_status) {
            'pending' => 'pending',
            'expired' => 'expired',
            default => 'failed',
        };
    }

    public function paymentMethodLabel(): string
    {
        $method = (string) $this->payment_method;

        return config("subscription.payment_method_labels.{$method}")
            ?? ($method !== '' ? ucwords(str_replace('_', ' ', $method)) : 'PayMongo');
    }

    /** Countable and starting in the future (an early renewal or a payment made during the trial). */
    public function isScheduled(): bool
    {
        return $this->isCountable()
            && $this->starts_at !== null
            && $this->starts_at->isFuture();
    }
}
