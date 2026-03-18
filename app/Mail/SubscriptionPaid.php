<?php

namespace App\Mail;

use App\Models\Spa;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionPaid extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Spa          $spa,
        public Subscription $subscription,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '✅ Subscription Confirmed — ' . $this->spa->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-paid',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
