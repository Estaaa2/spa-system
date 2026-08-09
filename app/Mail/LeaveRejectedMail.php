<?php

namespace App\Mail;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeaveRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public LeaveRequest $leaveRequest
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Leave Request Was Not Approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.leave-rejected',
        );
    }
}