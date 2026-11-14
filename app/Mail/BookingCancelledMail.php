<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A booking was cancelled, by the customer or by the spa, and what happens to
 * the money (customer cancellation follow-up #15, Nov 2026).
 */
class BookingCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $by,        // customer | staff
        public float $paid,
        public float $refund,
        public float $kept,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->by === 'staff'
            ? 'Levictas: The spa cancelled your booking'
            : 'Levictas: Your booking is cancelled');
    }

    public function content(): Content
    {
        $b = $this->booking;

        return new Content(
            view: 'emails.booking-cancelled',
            with: [
                'by'           => $this->by,
                'customerName' => $b->customer_name ?: 'there',
                'spaName'      => $b->spa?->name ?? 'your spa',
                'branchName'   => $b->branch?->name,
                'branchPhone'  => $b->branch?->profile?->phone,
                'treatment'    => $b->treatment_label,
                'date'         => $b->appointment_date?->format('l, F j, Y'),
                'time'         => \Carbon\Carbon::parse($b->start_time)->format('g:i A'),
                'paid'         => $this->paid,
                'refund'       => $this->refund,
                'kept'         => $this->kept,
                'url'          => url('/'),
            ],
        );
    }
}
