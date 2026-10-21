<?php

namespace App\Mail;

use App\Models\Spa;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrialEndingSoon extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Spa $spa) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Levictas free trial ends on ' . $this->spa->trial_ends_at?->format('F d, Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.trial-ending',
            with: [
                'spaName' => $this->spa->name ?? 'your spa',
                'endsAt' => $this->spa->trial_ends_at,
                'daysLeft' => $this->spa->trialDaysLeft(),
                'plans' => collect(config('subscription.purchasable_plans', ['basic', 'premium']))
                    ->mapWithKeys(fn (string $key) => [$key => config("plans.{$key}")])
                    ->filter(fn ($plan) => is_array($plan))
                    ->all(),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
