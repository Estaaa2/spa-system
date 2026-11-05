<?php

namespace App\Console\Commands;

use App\Services\Bookings\BookingStatusSync;
use App\Services\Bookings\StaleRequestExpiry;
use Illuminate\Console\Command;

/**
 * Replaces `bookings:mark-completed` (booking lifecycle fix, Phase 1, Q5-B).
 *
 * Keeps open bookings' statuses current even when nobody opens a web page,
 * so the mobile app and the nav badges never read stale values. It never
 * completes a booking: only staff checkout does (D1).
 *
 * Phase 3: also expires reschedule requests nobody reviewed before the
 * original start (D5) and reassignment requests left after the booking ended
 * (D6). See StaleRequestExpiry.
 */
class SyncBookingStatuses extends Command
{
    protected $signature = 'bookings:sync-statuses';

    protected $description = 'Move open bookings between reserved and pending by the clock (never completes them), and expire stale reschedule/reassignment requests';

    public function handle(BookingStatusSync $sync, StaleRequestExpiry $expiry): int
    {
        $changed = $sync->sync();

        $this->info("Updated {$changed} booking status(es).");

        $expired = $expiry->run();

        $this->info("Expired {$expired['reschedules']} reschedule request(s) ({$expired['emails']} customer email(s)) and {$expired['reassignments']} reassignment request(s).");

        return self::SUCCESS;
    }
}
