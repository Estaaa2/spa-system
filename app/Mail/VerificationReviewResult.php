<?php

namespace App\Mail;

use App\Models\Spa;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the owner the result of an administrator review:
 * a branch application or a single document, approved or sent back.
 */
class VerificationReviewResult extends Mailable
{
    use Queueable, SerializesModels;

    public Spa $spa;

    public string $heading;

    public string $body;

    public ?string $remarks;

    public bool $approved;

    public function __construct(
        Spa $spa,
        string $heading,
        string $body,
        bool $approved,
        ?string $remarks = null
    ) {
        $this->spa = $spa;
        $this->heading = $heading;
        $this->body = $body;
        $this->approved = $approved;
        $this->remarks = $remarks;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Levictas: ' . $this->heading,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-review-result',
            with: [
                'spaName' => $this->spa->name ?? 'your spa',
                'heading' => $this->heading,
                'body' => $this->body,
                'approved' => $this->approved,
                'remarks' => $this->remarks,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}