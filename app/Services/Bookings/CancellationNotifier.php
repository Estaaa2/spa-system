<?php

namespace App\Services\Bookings;

use App\Mail\BookingCancelledMail;
use App\Mail\RefundSentMail;
use App\Models\Booking;
use App\Models\BookingRefund;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Cancellation and refund emails to the customer (customer cancellation
 * follow-up #15, Nov 2026). Online bookings only. A mail failure is logged
 * and never undoes the cancel or the refund (same as BookingNotifier).
 */
class CancellationNotifier
{
    public function __construct(private CustomerCancellation $rule)
    {
    }

    /** @param string $by customer | staff */
    public function cancelled(?Booking $booking, string $by): void
    {
        if (! $booking || $booking->booking_source !== 'online' || blank($booking->customer_email)) {
            return;
        }

        $booking->loadMissing(['spa', 'branch.profile', 'reservation', 'refund']);
        ['paid' => $paid, 'downpayment' => $downpayment] = $this->rule->money($booking);
        $refund = (float) ($booking->refund?->amount ?? 0);

        $this->send($booking->customer_email, new BookingCancelledMail(
            booking: $booking,
            by: $by,
            paid: $paid,
            refund: $refund,
            kept: round(max($paid - $refund, 0), 2),
        ), ['booking_id' => $booking->id, 'kind' => 'cancelled']);
    }

    public function refundSent(BookingRefund $refund): void
    {
        $refund->loadMissing(['booking' => fn ($b) => $b->withTrashed()->with(['spa', 'branch'])]);
        $email = $refund->booking?->customer_email;

        if (blank($email)) {
            return;
        }

        $this->send($email, new RefundSentMail($refund), ['refund_id' => $refund->id, 'kind' => 'refund_sent']);
    }

    private function send(string $to, $mail, array $context): void
    {
        try {
            Mail::to($to)->send($mail);
        } catch (\Throwable $e) {
            Log::error('Failed to send cancellation email.', $context + ['message' => $e->getMessage()]);
        }
    }
}
