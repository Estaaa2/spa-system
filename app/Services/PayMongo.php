<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One place that knows whether Levictas charges in PayMongo test or live
 * mode, and which key, webhook secret and payment methods go with it.
 *
 * Switch with PAYMONGO_MODE=test|live in .env, then run
 * `php artisan config:cache`. Nothing else changes.
 */
class PayMongo
{
    public const API_BASE = 'https://api.paymongo.com/v1';

    /**
     * Seconds to resolve PayMongo's address and open the connection. A healthy
     * lookup takes milliseconds; this stops a broken DNS server from holding
     * the customer for Guzzle's default 10 s (the Thesis A crash).
     */
    public const CONNECT_TIMEOUT = 5;

    /** Seconds for the whole request, including PayMongo's reply. */
    public const TIMEOUT = 15;

    /** Milliseconds to wait before the single retry. */
    public const RETRY_DELAY_MS = 300;

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
     * The HTTP client every PayMongo call uses: the current mode's key, JSON,
     * a short connect timeout, the 15 s total timeout, and at most one retry.
     *
     * The retry happens only when the request never left this server (the
     * name didn't resolve, or the connection couldn't be opened). Once the
     * request may have reached PayMongo it is never repeated, so a slow
     * reply can't create a second checkout session (D6).
     *
     * Error responses (4xx/5xx) are returned, not thrown, as before: callers
     * keep checking $response->successful(). A connection that still fails
     * after the retry throws Illuminate\Http\Client\ConnectionException.
     */
    public static function http(): PendingRequest
    {
        return Http::withBasicAuth(self::secretKey(), '')
            ->acceptJson()
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::TIMEOUT)
            ->retry(2, self::RETRY_DELAY_MS, function (Throwable $e): bool {
                $retry = self::failedBeforeSending($e);

                if ($retry) {
                    Log::warning('PayMongo: connection failed before the request was sent; retrying once.', [
                        'message' => $e->getMessage(),
                    ]);
                }

                return $retry;
            }, throw: false);
    }

    /**
     * True only when cURL failed before sending anything:
     *  - errno 6  could not resolve host
     *  - errno 7  could not connect
     *  - errno 28 timed out while resolving or connecting
     *    ("Resolving timed out…" / "Connection timed out…"). A timeout while
     *    waiting for the reply ("Operation timed out…") is NOT retried.
     */
    public static function failedBeforeSending(Throwable $e): bool
    {
        $errno = self::curlErrno($e);
        $message = $e->getMessage();

        if ($errno === 6 || $errno === 7) {
            return true;
        }

        if ($errno === 28) {
            return (bool) preg_match('/Resolving timed out|Connection timed out/i', $message);
        }

        return false;
    }

    /** cURL error number from Guzzle's handler context, or from the message. */
    public static function curlErrno(Throwable $e): ?int
    {
        for ($cur = $e; $cur !== null; $cur = $cur->getPrevious()) {
            if (method_exists($cur, 'getHandlerContext')) {
                $errno = $cur->getHandlerContext()['errno'] ?? null;

                if (is_numeric($errno)) {
                    return (int) $errno;
                }
            }
        }

        return preg_match('/cURL error (\d+):/', $e->getMessage(), $m) ? (int) $m[1] : null;
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
