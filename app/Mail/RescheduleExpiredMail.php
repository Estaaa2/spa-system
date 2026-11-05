<?php

namespace App\Mail;

use App\Models\RescheduleRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Booking lifecycle fix, Phase 3 (D5, Q3-A): the branch didn't review the
 * customer's reschedule request before the original start time, so the
 * original booking stands. Same pattern as RescheduleRejectedMail.
 */
class RescheduleExpiredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RescheduleRequest $rescheduleRequest
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Reschedule Request Was Not Reviewed in Time',
        );
    }

    public function content(): Content
    {
        $booking = $this->rescheduleRequest->booking;

        return new Content(
            markdown: 'emails.reschedule-expired',
            with: [
                'requestedWhen' => $this->rescheduleRequest->requested_date->format('F j, Y')
                    . ' at ' . \Illuminate\Support\Carbon::parse($this->rescheduleRequest->requested_time)->format('g:i A'),
                'originalWhen'  => $booking->appointment_date->format('F j, Y')
                    . ' at ' . \Illuminate\Support\Carbon::parse($booking->start_time)->format('g:i A'),
                'branchName'    => $booking->branch?->name ?? 'the branch',
                'branchPhone'   => $booking->branch?->profile?->phone,
            ],
        );
    }
}
