<?php

namespace App\Mail;

use App\Models\IdentityVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a staff member the result of their identity verification (decision Q13:
 * email only). Never includes the ID, the selfie or any ID details.
 */
class IdentityVerificationResult extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public IdentityVerification $verification)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: match (true) {
                $this->verification->isApproved() => 'Levictas: Your identity is verified',
                $this->verification->isExpired()  => 'Levictas: Your identity verification request was closed',
                default                           => 'Levictas: Your identity verification was not approved',
            },
        );
    }

    public function content(): Content
    {
        $approved = $this->verification->isApproved();

        return new Content(
            view: 'emails.identity-verification-result',
            with: [
                'firstName' => $this->verification->user?->first_name ?: 'there',
                'approved'  => $approved,
                'expired'   => $this->verification->isExpired(),
                'maxDays'   => (int) config('identity.pending_max_days', 30),
                'reason'    => $approved ? null : $this->verification->rejection_reason,
                'url'       => route('profile.edit').'#identity',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
