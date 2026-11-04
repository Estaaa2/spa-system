<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailWithOtp extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $otp
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        return (new MailMessage)
            ->subject('Verify Your Levictas Email Address')
            ->greeting('Welcome to Levictas Spa & Wellness!')
            ->line('Please verify your email address to finish setting up your account.')
            ->action('Verify Email Address', $verificationUrl)
            ->line('Or use this 6-digit verification code:')
            ->line($this->otp)
            ->line('This OTP expires in 10 minutes.')
            ->line('If you did not create this account, you may ignore this email.');
    }
}
