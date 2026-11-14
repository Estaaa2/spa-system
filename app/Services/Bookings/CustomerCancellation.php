<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\BookingRefund;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The customer cancellation rule (Nov 2026), in one place. The web details
 * panel, the cancel endpoint and (later) the app API all read it.
 *
 *   Who        an online booking of this customer, still `reserved`, before it
 *              starts (same check as Booking::canRequestReschedule). After that
 *              the customer calls the branch, as before.
 *   Free       until 24 hours after payment or 3 hours before the start,
 *              whichever comes first, and only if the customer hasn't used a
 *              free cancellation in the last 30 days (rolling, per account).
 *              Free = everything paid comes back, the 20% included.
 *   Not free   the 20% downpayment is kept; anything paid above it (a full
 *              payment) comes back. Downpayment only = nothing comes back, and
 *              no refund is queued.
 *
 * Applies to bookings made under the older Terms too: it is more generous
 * than they were (decision, Nov 2026).
 *
 * Money comes from the online payment row (amount_charged, downpayment_amount).
 * Old bookings without one fall back to the booking's amount_paid and 20% of
 * its total.
 */
final class CustomerCancellation
{
    public const FREE_HOURS_AFTER_PAYMENT = 24;
    public const FREE_CUTOFF_HOURS_BEFORE = 3;
    public const FREE_LIMIT_DAYS = 30;
    public const DOWNPAYMENT_RATE = 0.20;

    /** Why a cancel is or isn't free; the panel picks its wording from this. */
    public const FREE_OK          = 'free';
    public const NOT_FREE_CLOSED  = 'window_closed';   // the window has passed
    public const NOT_FREE_NONE    = 'no_window';       // booked too close to the start to have one
    public const NOT_FREE_USED    = 'free_used';       // used one in the last 30 days

    /** freeUsedUntil() per customer for this instance; a list of bookings asks once. */
    private array $usedUntil = [];

    /** May this customer cancel this booking at all? */
    public function canCancel(Booking $booking, ?int $userId = null): bool
    {
        $start = $booking->startsAt();

        return $booking->booking_source === 'online'
            && $booking->customer_user_id !== null
            && ($userId === null || (int) $booking->customer_user_id === $userId)
            && $booking->status === 'reserved'
            && $start !== null
            && now()->lt($start);
    }

    /** When the booking was paid; the free window starts here. */
    public function paidAt(Booking $booking): CarbonInterface
    {
        $booking->loadMissing('reservation');

        return $booking->reservation?->paid_at ?? $booking->created_at ?? now();
    }

    /**
     * End of the free window, or null if the booking never had one (it was
     * paid less than 3 hours before the start).
     */
    public function freeUntil(Booking $booking): ?CarbonInterface
    {
        $start = $booking->startsAt();

        if ($start === null) {
            return null;
        }

        $paidAt = $this->paidAt($booking);
        $end = Carbon::instance($paidAt)->addHours(self::FREE_HOURS_AFTER_PAYMENT)
            ->min($start->copy()->subHours(self::FREE_CUTOFF_HOURS_BEFORE));

        return $end->gt($paidAt) ? $end : null;
    }

    /** If the customer used a free cancellation in the last 30 days: when they get it back. */
    public function freeUsedUntil(int $userId): ?CarbonInterface
    {
        if (array_key_exists($userId, $this->usedUntil)
            && ($this->usedUntil[$userId] === null || now()->lt($this->usedUntil[$userId]))) {
            return $this->usedUntil[$userId];
        }

        $last = Booking::withTrashed()
            ->where('customer_user_id', $userId)
            ->where('free_cancellation', true)
            ->where('cancelled_at', '>', now()->subDays(self::FREE_LIMIT_DAYS))
            ->max('cancelled_at');

        return $this->usedUntil[$userId] = $last ? Carbon::parse($last)->addDays(self::FREE_LIMIT_DAYS) : null;
    }

    /** @return array{paid: float, downpayment: float} */
    public function money(Booking $booking): array
    {
        $booking->loadMissing('reservation');
        $reservation = $booking->reservation;

        $paid = (float) ($reservation?->amount_charged ?? $reservation?->downpayment_amount ?? $booking->amount_paid);
        $downpayment = $reservation?->downpayment_amount !== null
            ? (float) $reservation->downpayment_amount
            : round((float) $booking->total_amount * self::DOWNPAYMENT_RATE, 2);

        return ['paid' => round($paid, 2), 'downpayment' => round(min($downpayment, $paid), 2)];
    }

    /**
     * What cancelling right now would mean. Safe to send to the customer.
     *
     * @return array{can_cancel: bool, free: bool, reason: ?string, free_until: ?string,
     *               free_again_on: ?string, paid: float, refund: float, kept: float}
     */
    public function quote(Booking $booking): array
    {
        $empty = [
            'can_cancel' => false, 'free' => false, 'reason' => null, 'free_until' => null,
            'free_again_on' => null, 'paid' => 0.0, 'refund' => 0.0, 'kept' => 0.0,
        ];

        if (! $this->canCancel($booking)) {
            return $empty;
        }

        $freeUntil = $this->freeUntil($booking);
        $usedUntil = $this->freeUsedUntil((int) $booking->customer_user_id);

        $reason = match (true) {
            $freeUntil === null           => self::NOT_FREE_NONE,
            now()->gte($freeUntil)        => self::NOT_FREE_CLOSED,
            $usedUntil !== null           => self::NOT_FREE_USED,
            default                       => self::FREE_OK,
        };
        $free = $reason === self::FREE_OK;

        ['paid' => $paid, 'downpayment' => $downpayment] = $this->money($booking);
        $kept = $free ? 0.0 : $downpayment;

        return [
            'can_cancel'    => true,
            'free'          => $free,
            'reason'        => $reason,
            'free_until'    => $freeUntil?->toIso8601String(),
            'free_again_on' => $usedUntil?->toIso8601String(),
            'paid'          => $paid,
            'refund'        => round(max($paid - $kept, 0), 2),
            'kept'          => round($kept, 2),
        ];
    }

    /**
     * Cancel for the customer and queue the refund. Re-checks everything under
     * row locks, so a double tap or two tabs can't cancel twice or use the free
     * cancellation twice.
     *
     * @return array the quote that was applied
     * @throws CancellationNotAllowed
     */
    public function cancel(Booking $booking, User $customer, ?string $reason = null): array
    {
        // A remembered answer may be stale here; re-read under the lock.
        $this->usedUntil = [];

        $quote = DB::transaction(function () use ($booking, $customer, $reason) {
            // One customer at a time: serialises the 30-day check across their bookings.
            User::whereKey($customer->id)->lockForUpdate()->first();

            /** @var Booking $locked */
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->customer_user_id !== (int) $customer->id) {
                throw new CancellationNotAllowed('This booking isn\'t yours.');
            }

            if (! $this->canCancel($locked)) {
                throw new CancellationNotAllowed(
                    'This appointment can no longer be cancelled here. Please call the branch.'
                );
            }

            $quote = $this->quote($locked);

            $locked->forceFill([
                'status'            => 'cancelled',
                'cancelled_at'      => now(),
                'cancelled_by'      => 'customer',
                'cancel_reason'     => filled($reason) ? mb_substr(trim($reason), 0, 500) : null,
                'free_cancellation' => $quote['free'],
            ])->save();

            if ($quote['refund'] > 0) {
                BookingRefund::create([
                    'booking_id'                    => $locked->id,
                    'online_reservation_payment_id' => $locked->reservation?->id,
                    'user_id'                       => $customer->id,
                    'amount'                        => $quote['refund'],
                    'kind'                          => $quote['free'] ? BookingRefund::KIND_FREE : BookingRefund::KIND_LATE,
                    'status'                        => BookingRefund::STATUS_PENDING,
                ]);
            }

            return $quote;
        });

        $this->usedUntil = [];

        return $quote;
    }
}
