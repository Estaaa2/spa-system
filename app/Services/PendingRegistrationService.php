<?php

namespace App\Services;

use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class PendingRegistrationService
{
    public function start(array $data, string $type): PendingRegistration
    {
        return DB::transaction(function () use ($data, $type) {
            $email = strtolower($data['email']);

            $pending = PendingRegistration::firstOrNew([
                'email' => $email,
            ]);

            if (!$pending->exists) {
                $pending->uuid = (string) Str::uuid();
            }

            $pending->forceFill([
                'registration_type' => $type,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'suffix' => $data['suffix'] ?? null,
                'email' => $email,
                'password' => Hash::make($data['password']),
                'otp_hash' => null,
                'otp_expires_at' => null,
                'otp_sent_at' => null,
                'otp_attempts' => 0,
                'expires_at' => now()->addDay(),
            ]);

            $pending->save();

            return $pending;
        });
    }

    public function complete(PendingRegistration $pendingRegistration): User
    {
        $user = DB::transaction(function () use ($pendingRegistration) {
            $pending = PendingRegistration::query()
                ->whereKey($pendingRegistration->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($pending->expires_at->isPast()) {
                throw new RuntimeException(
                    'This pending registration has expired.'
                );
            }

            if (
                User::withTrashed()
                    ->where('email', $pending->email)
                    ->exists()
            ) {
                throw new RuntimeException(
                    'An account with this email address already exists.'
                );
            }

            $isBusiness = $pending->registration_type === 'business';

            $user = User::create([
                'first_name' => $pending->first_name,
                'middle_name' => $pending->middle_name,
                'last_name' => $pending->last_name,
                'suffix' => $pending->suffix,
                'email' => $pending->email,
                'password' => $pending->password,
                'is_owner' => $isBusiness,
            ]);

            $user->forceFill([
                'email_verified_at' => now(),
            ])->saveQuietly();

            $user->assignRole(
                $isBusiness
                    ? 'owner'
                    : 'customer'
            );

            $pending->delete();

            return $user;
        });

        event(new Registered($user));

        return $user;
    }
}
