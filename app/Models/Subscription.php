<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    /** Countable and starting in the future (an early renewal or a payment made during the trial). */
    public function isScheduled(): bool
    {
        return $this->isCountable()
            && $this->starts_at !== null
            && $this->starts_at->isFuture();
    }
}