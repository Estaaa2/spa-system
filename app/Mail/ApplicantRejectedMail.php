<?php

namespace App\Mail;

use App\Models\Interview;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to an applicant whose interview was rejected. Never includes the
 * internal rejection_reason; only the optional applicant_message.
 */
class ApplicantRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Interview $interview
    ) {}

    public function envelope(): Envelope
    {
        $spaName = $this->interview->spa?->name ?? config('app.name');

        return new Envelope(
            subject: "Update on your application at {$spaName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.applicant-rejected',
            with: [
                'applicant' => $this->interview->applicant,
                'spaName' => $this->interview->spa?->name ?? config('app.name'),
                'position' => ucwords(str_replace('_', ' ', (string) $this->interview->applicant?->position_applied)),
                'applicantMessage' => $this->interview->applicant_message,
            ],
        );
    }
}
