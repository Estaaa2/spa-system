<?php

namespace App\Services\Subscription;

use App\Models\Spa;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The billing rules for plan purchases. Used by checkout (quote) and by the
 * PayMongo webhook (activate), so the price shown, the amount charged and
 * the period granted always follow the same rules.
 *
 * Rules (decided for Levictas):
 * - Prepaid, manual renewal. Nothing renews automatically.
 * - A new period starts after the trial and after all paid time, so paying
 *   early never loses days.
 * - At most `max_scheduled_periods` periods may be queued ahead.
 * - Basic -> Premium while Basic time is left = upgrade: it starts now and
 *   is priced at the Premium price minus the unused Basic value. The
 *   credited Basic rows are marked superseded when the payment lands.
 * - Premium -> Basic is a downgrade: it is queued after the paid Premium
 *   time. No refunds.
 * - Changing monthly <-> yearly on the same plan is just a renewal with
 *   the other cycle.
 */
class SubscriptionBilling
{
    public const CYCLES = ['monthly', 'yearly'];

    /** @return array<int, string> */
    public static function purchasablePlans(): array
    {
        return config('subscription.purchasable_plans', ['basic', 'premium']);
    }

    public static function listPrice(string $plan, string $cycle): float
    {
        $key = $cycle === 'yearly' ? 'yearly_price' : 'monthly_price';

        return (float) config("plans.{$plan}.{$key}", 0);
    }

    public static function periodEnd(Carbon $start, string $cycle): Carbon
    {
        return $cycle === 'yearly'
            ? $start->copy()->addYearNoOverflow()
            : $start->copy()->addMonthNoOverflow();
    }

    /*
    |--------------------------------------------------------------------------
    | Quote
    |--------------------------------------------------------------------------
    */

    public function quote(Spa $spa, string $plan, string $cycle): SubscriptionQuote
    {
        $plan = Spa::normalisePlanKey($plan);
        $listPrice = self::listPrice($plan, $cycle);

        if (! in_array($plan, self::purchasablePlans(), true)
            || ! in_array($cycle, self::CYCLES, true)
            || $listPrice <= 0) {
            return SubscriptionQuote::blocked($plan, $cycle, $listPrice, 'The selected plan is unavailable.');
        }

        if ($spa->hasUnlimitedPeriod()) {
            return SubscriptionQuote::blocked(
                $plan,
                $cycle,
                $listPrice,
                'Your spa has a plan without an end date. Please contact Levictas support to change it.'
            );
        }

        $credited = $plan === 'premium' ? $this->creditableRows($spa) : collect();

        if ($credited->isNotEmpty()) {
            return $this->upgradeQuote($spa, $plan, $cycle, $listPrice, $credited);
        }

        $scheduled = $spa->scheduledPeriods();
        $maxScheduled = (int) config('subscription.max_scheduled_periods', 1);

        if ($scheduled->count() >= $maxScheduled) {
            $next = $scheduled->first();

            return SubscriptionQuote::blocked(
                $plan,
                $cycle,
                $listPrice,
                sprintf(
                    'You have already paid for your next period (%s, starting %s). You can pay again once it starts.',
                    ucfirst(Spa::normalisePlanKey($next->business_tier)),
                    $next->starts_at->format('F d, Y')
                )
            );
        }

        $start = $spa->nextPeriodStartsAt();

        return new SubscriptionQuote(
            allowed: true,
            message: null,
            plan: $plan,
            cycle: $cycle,
            kind: $this->kindFor($spa, $plan),
            listPrice: $listPrice,
            credit: 0.0,
            amount: $listPrice,
            startsAt: $start,
            expiresAt: self::periodEnd($start, $cycle),
        );
    }

    private function upgradeQuote(Spa $spa, string $plan, string $cycle, float $listPrice, Collection $credited): SubscriptionQuote
    {
        $credit = round($credited->sum(fn (Subscription $row) => $this->remainingValue($row)), 2);

        if ($credit >= $listPrice) {
            $message = $cycle === 'monthly'
                ? sprintf(
                    'Your unused Basic time is worth ₱%s, more than one month of Premium. Choose yearly billing to upgrade.',
                    number_format($credit, 2)
                )
                : 'Your unused Basic time is worth more than a year of Premium. Please contact Levictas support to upgrade.';

            return SubscriptionQuote::blocked($plan, $cycle, $listPrice, $message);
        }

        $amount = round($listPrice - $credit, 2);
        $minimum = (float) config('subscription.minimum_charge', 1);

        if ($amount < $minimum) {
            return SubscriptionQuote::blocked(
                $plan,
                $cycle,
                $listPrice,
                'This upgrade is too small to charge. Please choose a different billing cycle.'
            );
        }

        $start = $this->upgradeStartsAt($spa, $credited->pluck('id')->all());

        return new SubscriptionQuote(
            allowed: true,
            message: null,
            plan: $plan,
            cycle: $cycle,
            kind: 'upgrade',
            listPrice: $listPrice,
            credit: $credit,
            amount: $amount,
            startsAt: $start,
            expiresAt: self::periodEnd($start, $cycle),
            creditedIds: $credited->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Activation (webhook)
    |--------------------------------------------------------------------------
    */

    /**
     * Marks a subscription row paid and gives it its period.
     * Safe to call more than once for the same row (PayMongo retries).
     *
     * @return bool true when this call activated the row
     */
    public function activate(Subscription $subscription, ?string $paymentId, ?string $paymentMethod): bool
    {
        return DB::transaction(function () use ($subscription, $paymentId, $paymentMethod): bool {
            // Lock the spa first so two payments for the same spa are
            // stacked one after the other, never on top of each other.
            $spa = Spa::withTrashed()
                ->whereKey($subscription->spa_id)
                ->lockForUpdate()
                ->firstOrFail();

            $row = Subscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($row->payment_status === 'paid') {
                return false;
            }

            $plan = Spa::normalisePlanKey($row->business_tier);
            $plan = $plan === 'expired' ? 'basic' : $plan;
            $cycle = in_array($row->billing_cycle, self::CYCLES, true) ? $row->billing_cycle : 'monthly';

            $credited = $row->kind === 'upgrade' && $plan === 'premium'
                ? $this->creditableRows($spa)
                : collect();

            if ($credited->isNotEmpty()) {
                $start = $this->upgradeStartsAt($spa, $credited->pluck('id')->all());

                Subscription::query()
                    ->whereIn('id', $credited->pluck('id'))
                    ->update(['superseded_at' => now()]);
            } else {
                $start = $spa->nextPeriodStartsAt();
            }

            $row->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
                'status' => 'active',
                'starts_at' => $start,
                'expires_at' => self::periodEnd($start, $cycle),
                'paymongo_payment_id' => $paymentId,
                'payment_method' => $paymentMethod,
            ]);

            $spa->forgetSubscriptionCache();

            $current = $spa->currentPlan();

            // spas.business_tier is only a display copy for older admin
            // screens; access is always worked out from the periods.
            if ($current !== 'expired') {
                $spa->update(['business_tier' => $current]);
            }

            $subscription->setRawAttributes($row->fresh()->getAttributes(), true);

            return true;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /** Basic rows with time left (running or queued). Their value is credited on upgrade. */
    public function creditableRows(Spa $spa): Collection
    {
        return $spa->countablePeriods()
            ->filter(fn (Subscription $row) =>
                Spa::normalisePlanKey($row->business_tier) === 'basic'
                && $row->expires_at !== null
                && $row->expires_at->isFuture())
            ->values();
    }

    /**
     * Unused value of a paid row: the full value if it has not started,
     * otherwise the share of the period that is left. The value is what
     * the period was worth (amount paid + credit used to buy it).
     */
    public function remainingValue(Subscription $row): float
    {
        if ($row->expires_at === null || $row->expires_at->lte(now())) {
            return 0.0;
        }

        $value = (float) $row->amount + (float) $row->credit_amount;
        $start = $row->starts_at ?? $row->created_at ?? now();

        if ($start->isFuture()) {
            return floor($value * 100) / 100;
        }

        $total = $start->diffInSeconds($row->expires_at, false);

        if ($total <= 0) {
            return 0.0;
        }

        $left = now()->diffInSeconds($row->expires_at, false);

        return floor(($value * ($left / $total)) * 100) / 100;
    }

    /**
     * An upgrade starts now, unless the spa is still on its free trial or
     * has other (non-credited) paid time, in which case it starts after it.
     */
    private function upgradeStartsAt(Spa $spa, array $creditedIds): Carbon
    {
        $start = now();

        if ($spa->isTrialing() && $spa->trial_ends_at->gt($start)) {
            $start = $spa->trial_ends_at->copy();
        }

        $otherEnd = $spa->countablePeriods()
            ->reject(fn (Subscription $row) => in_array((int) $row->id, array_map('intval', $creditedIds), true))
            ->map(fn (Subscription $row) => $row->expires_at)
            ->filter()
            ->max();

        if ($otherEnd && $otherEnd->gt($start)) {
            $start = $otherEnd->copy();
        }

        return $start;
    }

    /** new / renewal / upgrade / downgrade, compared with the latest paid plan. */
    private function kindFor(Spa $spa, string $plan): string
    {
        if (! $spa->hasEverPaid()) {
            return 'new';
        }

        $reference = $spa->countablePeriods()
            ->filter(fn (Subscription $row) => $row->expires_at !== null)
            ->sortBy(fn (Subscription $row) => $row->expires_at->getTimestamp())
            ->last();

        $previous = Spa::normalisePlanKey($reference?->business_tier);

        return match (true) {
            $previous === $plan, $previous === 'expired' => 'renewal',
            $previous === 'premium' && $plan === 'basic' => 'downgrade',
            default => 'upgrade',
        };
    }
}
