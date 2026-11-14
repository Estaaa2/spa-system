<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\BookingRefund;
use Illuminate\Support\Facades\DB;

/**
 * Staff cancel a booking or record a no-show, and queue what Levictas owes
 * the customer (customer cancellation follow-up #16, Nov 2026). The Terms:
 *
 *   spa cancels   everything paid online comes back, the downpayment included
 *   no-show       the 20% downpayment is kept; a full payment's 80% comes back
 *
 * Only online bookings: walk-in money is handled at the counter. The amounts
 * come from CustomerCancellation::money(), so both sides read one source.
 */
final class StaffBookingEnd
{
    public function __construct(
        private CustomerCancellation $rule,
        private CancellationNotifier $notifier,
    ) {
    }

    /**
     * @param  string  $status  'cancelled' or Booking::NO_SHOW
     * @return BookingRefund|null the refund queued, if any
     */
    public function end(Booking $booking, string $status): ?BookingRefund
    {
        $refund = DB::transaction(function () use ($booking, $status) {
            /** @var Booking $locked */
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, Booking::FINISHED_STATUSES, true)) {
                return null;   // someone else ended it first
            }

            $locked->forceFill($status === 'cancelled'
                ? ['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => 'staff']
                : ['status' => $status]
            )->save();

            if ($locked->booking_source !== 'online' || $locked->refund()->exists()) {
                return null;
            }

            ['paid' => $paid, 'downpayment' => $downpayment] = $this->rule->money($locked);
            $amount = $status === 'cancelled' ? $paid : max($paid - $downpayment, 0);

            if ($amount <= 0) {
                return null;
            }

            return BookingRefund::create([
                'booking_id'                    => $locked->id,
                'online_reservation_payment_id' => $locked->reservation?->id,
                'user_id'                       => $locked->customer_user_id,
                'amount'                        => round($amount, 2),
                'kind'                          => $status === 'cancelled' ? BookingRefund::KIND_SPA : BookingRefund::KIND_NO_SHOW,
                'status'                        => BookingRefund::STATUS_PENDING,
            ]);
        });

        if ($status === 'cancelled') {
            $this->notifier->cancelled($booking->fresh(), 'staff');
        }

        return $refund;
    }
}
