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

    public string $planName;

    public function __construct(Spa $spa, Subscription $subscription)
    {
        $this->spa = $spa;
        $this->subscription = $subscription;

        $plan = strtolower((string) $subscription->business_tier);

        $this->planName = match ($plan) {
            'business' => 'Business',
            'premium', 'professional' => 'Premium',
            'basic' => 'Basic',
            default => ucfirst($plan ?: 'Subscription'),
        };
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Levictas {$this->planName} subscription expires in 3 days",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-expiring',
            with: [
                'spaName' => $this->spa->name ?? 'your spa',
                'planName' => $this->planName,
                'expiresAt' => $this->subscription->expires_at,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
