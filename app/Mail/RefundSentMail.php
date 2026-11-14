<?php

namespace App\Mail;

use App\Http\Controllers\Admin\RefundController;
use App\Models\BookingRefund;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A Levictas admin marked a refund as sent (customer cancellation follow-up #15). */
class RefundSentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BookingRefund $refund)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Levictas: Your refund has been sent');
    }

    public function content(): Content
    {
        $r = $this->refund;
        $b = $r->booking;

        return new Content(
            view: 'emails.refund-sent',
            with: [
                'customerName' => $b?->customer_name ?: 'there',
                'amount'       => (float) $r->amount,
                'method'       => RefundController::METHODS[$r->payment_method] ?? $r->payment_method,
                'reference'    => $r->reference,
                'sentOn'       => $r->processed_at?->format('F j, Y'),
                'spaName'      => $b?->spa?->name ?? 'your spa',
                'branchName'   => $b?->branch?->name,
                'treatment'    => $b?->treatment_label,
                'date'         => $b?->appointment_date?->format('l, F j, Y'),
                'url'          => url('/'),
            ],
        );
    }
}
