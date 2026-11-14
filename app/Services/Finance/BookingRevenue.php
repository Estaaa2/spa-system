<?php

namespace App\Services\Finance;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;

/**
 * The money rules every report uses (booking lifecycle fix, Phase 4).
 * Revenue, Reports, the DSS and the Dashboard read from here, so their numbers
 * can't drift apart again.
 *
 *   Service revenue  (D9, Q1-A)  checked-out (`completed`) bookings, summing the
 *                                money actually collected (amount_paid). Since
 *                                checkout requires the full balance, this equals
 *                                total_amount for every new checkout; older
 *                                partially paid rows can't inflate it.
 *   Kept deposits    (Q2-A/Q3-A) amount_paid on cancelled / no-show bookings,
 *                                shown next to revenue, never added into it.
 *                                Refunds queued by a customer cancellation
 *                                (booking_refunds) are taken off; refunds made
 *                                outside the system still aren't tracked.
 *   Owed on open     (Q4-A)      remaining balance on bookings past their end
 *                                time that nobody closed (Needs Checkout).
 */
final class BookingRevenue
{
    public const KEPT_STATUSES = ['cancelled', Booking::NO_SHOW];

    /** Bookings of one spa (and branch), optionally within appointment dates. */
    public static function scope(int $spaId, ?int $branchId = null, ?string $from = null, ?string $to = null): Builder
    {
        return Booking::query()
            ->where('spa_id', $spaId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($from && $to, fn ($q) => $q->whereBetween('appointment_date', [$from, $to]));
    }

    /** Checked-out bookings: the ones that count as service revenue. */
    public static function serviceBookings(int $spaId, ?int $branchId = null, ?string $from = null, ?string $to = null): Builder
    {
        return self::scope($spaId, $branchId, $from, $to)->where('status', 'completed');
    }

    public static function serviceRevenue(int $spaId, ?int $branchId = null, ?string $from = null, ?string $to = null): float
    {
        return (float) self::serviceBookings($spaId, $branchId, $from, $to)->sum('amount_paid');
    }

    /**
     * @return array{amount: float, count: int} money kept on cancelled / no-show
     * bookings: what was paid minus any refund owed back through Levictas
     * (customer cancellation, Nov 2026). A fully refunded booking keeps nothing
     * and isn't counted.
     */
    public static function keptDeposits(int $spaId, ?int $branchId = null, ?string $from = null, ?string $to = null): array
    {
        $kept = 'bookings.amount_paid - COALESCE((SELECT SUM(booking_refunds.amount) FROM booking_refunds WHERE booking_refunds.booking_id = bookings.id), 0)';

        $row = self::scope($spaId, $branchId, $from, $to)
            ->whereIn('status', self::KEPT_STATUSES)
            ->where('amount_paid', '>', 0)
            ->selectRaw("COALESCE(SUM(CASE WHEN {$kept} > 0 THEN {$kept} ELSE 0 END), 0) as amount, COALESCE(SUM(CASE WHEN {$kept} > 0 THEN 1 ELSE 0 END), 0) as bookings")
            ->first();

        return ['amount' => (float) ($row->amount ?? 0), 'count' => (int) ($row->bookings ?? 0)];
    }

    /**
     * @return array{amount: float, count: int} balance still owed on bookings
     * that ended but were never closed (any date — the Needs Checkout list).
     */
    public static function owedOnOpen(int $spaId, ?int $branchId = null): array
    {
        $row = self::scope($spaId, $branchId)
            ->overdue()
            ->selectRaw('COALESCE(SUM(CASE WHEN total_amount > amount_paid THEN total_amount - amount_paid ELSE 0 END), 0) as amount, COUNT(*) as bookings')
            ->first();

        return ['amount' => (float) ($row->amount ?? 0), 'count' => (int) ($row->bookings ?? 0)];
    }
}
