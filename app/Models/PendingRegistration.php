<?php

namespace App\Models;

use App\Notifications\PendingRegistrationVerification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Throwable;

class PendingRegistration extends Model
{
    use Notifiable;

    protected $fillable = [
        'uuid',
        'registration_type',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'email',
        'password',
        'expires_at',
    ];

    protected $hidden = [
        'password',
        'otp_hash',
    ];

    protected function casts(): array
    {
        return [
            'otp_expires_at' => 'datetime',
            'otp_sent_at' => 'datetime',
            'otp_attempts' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    public function generateOtp(): string
    {
        $otp = str_pad(
            (string) random_int(0, 999999),
            6,
            '0',
            STR_PAD_LEFT
        );

        $this->forceFill([
            'otp_hash' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(10),
            'otp_sent_at' => now(),
            'otp_attempts' => 0,
        ])->saveQuietly();

        return $otp;
    }

    public function sendVerificationNotification(): void
    {
        $otp = $this->generateOtp();

        $verificationUrl = URL::temporarySignedRoute(
            'pending.verification.verify',
            now()->addMinutes(60),
            [
                'pendingRegistration' => $this->uuid,
            ]
        );

        try {
            $this->notify(
                new PendingRegistrationVerification(
                    $otp,
                    $verificationUrl
                )
            );
        } catch (Throwable $e) {
            $this->forceFill([
                'otp_sent_at' => null,
            ])->saveQuietly();

            throw $e;
        }
    }
}
