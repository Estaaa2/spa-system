<?php

namespace App\Services\Bookings;

use App\Mail\BookingTherapistMail;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Customer emails that carry the therapist card (photo, name, verified badge)
 * and, for home service, the arrival code (staff verification D6, D7, Q43).
 *
 * Only online bookings (the customer has an account and booked themselves).
 * A mail problem is logged and never undoes the booking change.
 */
class BookingNotifier
{
    /** After payment: the booking exists (with or without a therapist). */
    public function confirmed(Booking $booking): void
    {
        $this->send($booking, BookingTherapistMail::KIND_CONFIRMED);
    }

    /** Q43: a therapist was assigned to an unassigned booking, or replaced. */
    public function therapistChanged(?Booking $booking, bool $wasUnassigned): void
    {
        $this->send($booking, $wasUnassigned ? BookingTherapistMail::KIND_ASSIGNED : BookingTherapistMail::KIND_CHANGED);
    }

    private function send(?Booking $booking, string $kind): void
    {
        if (! $booking
            || $booking->booking_source !== 'online'
            || blank($booking->customer_email)
            || ! in_array($booking->status, Booking::ACTIVE_STATUSES, true)
            || $booking->appointment_date?->isBefore(today())) {
            return;
        }

        try {
            $booking->ensureArrivalCode();
            $booking->loadMissing(['therapist', 'spa', 'branch.profile']);

            Mail::to($booking->customer_email)->send(new BookingTherapistMail($booking, $kind));
        } catch (\Throwable $e) {
            Log::error('Failed to send booking therapist email.', [
                'booking_id' => $booking->id,
                'kind'       => $kind,
                'message'    => $e->getMessage(),
            ]);
        }
    }
}
