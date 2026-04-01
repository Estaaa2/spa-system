<?php

namespace App\Mail;

use App\Models\RescheduleRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RescheduleRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RescheduleRequest $rescheduleRequest
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Reschedule Request Was Not Approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reschedule-rejected',
        );
    }
}
