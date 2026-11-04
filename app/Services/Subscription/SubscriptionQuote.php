<?php

namespace App\Services\Subscription;

use Illuminate\Support\Carbon;

/**
 * The price and dates of one plan purchase, worked out before checkout.
 *
 * $allowed = false means the purchase is blocked and $message says why.
 * The dates are estimates: the webhook works them out again when the
 * payment is confirmed, so a slow payment never shortens a paid period.
 */
final class SubscriptionQuote
{
    /**
     * @param  array<int, int>  $creditedIds  Basic rows whose unused value is credited (upgrade only)
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $message,
        public readonly string $plan,
        public readonly string $cycle,
        public readonly string $kind,
        public readonly float $listPrice,
        public readonly float $credit,
        public readonly float $amount,
        public readonly ?Carbon $startsAt,
        public readonly ?Carbon $expiresAt,
        public readonly array $creditedIds = [],
    ) {}

    public static function blocked(string $plan, string $cycle, float $listPrice, string $message): self
    {
        return new self(
            allowed: false,
            message: $message,
            plan: $plan,
            cycle: $cycle,
            kind: 'new',
            listPrice: $listPrice,
            credit: 0.0,
            amount: $listPrice,
            startsAt: null,
            expiresAt: null,
        );
    }

    /** True when the period starts later (paid during the trial or ahead of time). */
    public function isScheduled(): bool
    {
        return $this->startsAt !== null && $this->startsAt->isFuture();
    }

    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'message' => $this->message,
            'plan' => $this->plan,
            'cycle' => $this->cycle,
            'kind' => $this->kind,
            'list_price' => $this->listPrice,
            'credit' => $this->credit,
            'amount' => $this->amount,
            'starts_at' => $this->startsAt?->toIso8601String(),
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'is_scheduled' => $this->isScheduled(),
        ];
    }
}
