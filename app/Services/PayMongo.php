<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * One place that knows whether Levictas charges in PayMongo test or live
 * mode, and which key, webhook secret and payment methods go with it.
 *
 * Switch with PAYMONGO_MODE=test|live in .env, then run
 * `php artisan config:cache`. Nothing else changes.
 */
class PayMongo
{
    public static function mode(): string
    {
        return config('services.paymongo.mode') === 'live' ? 'live' : 'test';
    }

    public static function isLive(): bool
    {
        return self::mode() === 'live';
    }

    /** Secret API key for the current mode. */
    public static function secretKey(): string
    {
        $key = (string) config('services.paymongo.' . self::mode() . '_secret_key');

        if ($key === '') {
            Log::error('PayMongo secret key is not set for ' . self::mode() . ' mode.');
        }

        return $key;
    }

    /**
     * Signing secret of the webhook for the event's own mode. A live event
     * is checked with the live secret even while the site is in test mode.
     */
    public static function webhookSecret(bool $live): string
    {
        return (string) config('services.paymongo.' . ($live ? 'live' : 'test') . '_webhook_secret');
    }

    /**
     * Checkout payment methods. In live mode the configured live list wins,
     * because a live account only accepts what PayMongo approved for it.
     *
     * @param  array<int, string>  $testMethods  methods to offer in test mode
     * @return array<int, string>
     */
    public static function paymentMethods(array $testMethods): array
    {
        if (! self::isLive()) {
            return array_values($testMethods);
        }

        $live = (array) config('services.paymongo.live_payment_methods', []);

        return $live !== [] ? array_values($live) : ['qrph'];
    }
}
