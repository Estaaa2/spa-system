<?php

namespace App\Console\Commands;

use App\Services\Bookings\BookingStatusSync;
use Illuminate\Console\Command;

/**
 * Replaces `bookings:mark-completed` (booking lifecycle fix, Phase 1, Q5-B).
 *
 * Keeps open bookings' statuses current even when nobody opens a web page,
 * so the mobile app and the nav badges never read stale values. It never
 * completes a booking: only staff checkout does (D1).
 */
class SyncBookingStatuses extends Command
{
    protected $signature = 'bookings:sync-statuses';

    protected $description = 'Move open bookings between reserved and pending by the clock (never completes them)';

    public function handle(BookingStatusSync $sync): int
    {
        $changed = $sync->sync();

        $this->info("Updated {$changed} booking status(es).");

        return self::SUCCESS;
    }
}
