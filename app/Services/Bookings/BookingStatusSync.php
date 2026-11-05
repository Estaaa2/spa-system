<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Moves open bookings along the clock (booking lifecycle fix, Phase 1).
 *
 *   before the start time   → reserved
 *   from the start time on  → pending   (waiting for staff)
 *
 * The clock never sets `ongoing` (only staff "Start" does, decision Q1-B) and
 * never sets `completed` (only staff checkout does, D1). A booking stays
 * `pending`/`ongoing` after its end time until staff check it out, mark it a
 * no-show, or cancel it.
 *
 * Used by the page-load sync in BookingController and by the
 * `bookings:sync-statuses` scheduled command, so both follow one rule.
 */
class BookingStatusSync
{
    /** Statuses the clock may change. `ongoing` is staff-set and left alone. */
    public const CLOCK_STATUSES = ['reserved', 'pending'];

    /**
     * Sync every clock-driven booking matched by $scope (null = all spas).
     * Returns how many bookings changed status.
     */
    public function sync(?callable $scope = null, ?Carbon $now = null): int
    {
        $now ??= Carbon::now();

        $query = Booking::query()->whereIn('status', self::CLOCK_STATUSES);

        if ($scope) {
            $scope($query);
        }

        $changed = 0;

        $query->chunkById(200, function ($bookings) use ($now, &$changed) {
            foreach ($bookings as $booking) {
                $target = $this->targetStatus($booking, $now);

                if ($target !== null && $target !== $booking->status) {
                    $booking->update(['status' => $target]);
                    $changed++;
                }
            }
        });

        return $changed;
    }

    /** Sync one spa (and optionally one branch). */
    public function syncFor(?int $spaId, ?int $branchId = null, ?Carbon $now = null): int
    {
        return $this->sync(function (Builder $q) use ($spaId, $branchId) {
            if ($spaId) {
                $q->where('spa_id', $spaId);
            }

            if ($branchId) {
                $q->where('branch_id', $branchId);
            }
        }, $now);
    }

    /** The status the clock says this booking should have, or null to leave it. */
    public function targetStatus(Booking $booking, ?Carbon $now = null): ?string
    {
        if (! in_array($booking->status, self::CLOCK_STATUSES, true)) {
            return null;
        }

        $start = $booking->startsAt();

        if ($start === null) {
            return null;
        }

        return ($now ?? Carbon::now())->lt($start) ? 'reserved' : 'pending';
    }
}
