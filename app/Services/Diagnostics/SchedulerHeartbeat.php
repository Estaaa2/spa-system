<?php

namespace App\Services\Diagnostics;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The scheduler writes the time here every minute (routes/console.php), so
 * demo:check can tell whether the server's cron is actually running.
 */
class SchedulerHeartbeat
{
    public const CACHE_KEY = 'levictas:scheduler-heartbeat';

    public static function beat(): void
    {
        Cache::forever(self::CACHE_KEY, now()->toIso8601String());
    }

    public static function lastBeat(): ?Carbon
    {
        $value = Cache::get(self::CACHE_KEY);

        return is_string($value) ? Carbon::parse($value) : null;
    }
}
