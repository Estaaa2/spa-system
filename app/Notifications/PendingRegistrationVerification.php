<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendingRegistrationVerification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $otp,
        private readonly string $verificationUrl
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify Your Levictas Email Address')
            ->view('emails.pending-registration-verification', [
                'otp' => $this->otp,
                'verificationUrl' => $this->verificationUrl,
            ]);
    }
}
