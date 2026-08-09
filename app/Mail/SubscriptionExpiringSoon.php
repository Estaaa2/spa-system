<?php

namespace App\Mail;

use App\Models\Spa;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiringSoon extends Mailable
{
    use Queueable, SerializesModels;

    public Spa $spa;
    public Subscription $subscription;

    public function __construct(Spa $spa, Subscription $subscription)
    {
        $this->spa = $spa;
        $this->subscription = $subscription;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Levictas Professional subscription expires in 3 days',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-expiring',
            with: [
                'spaName'  => $this->spa->name ?? 'your spa',
                'expiresAt' => $this->subscription->expires_at,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
