<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\LeaveRequest;
use App\Models\StaffAvailability;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The single place that decides who can be assigned a booking (decision Q8).
 *
 * Used by online checkout, the slot grid, the PayMongo webhook, the Flutter
 * payment fallback, staff booking, appointment edit, reassignment and leave.
 *
 * Pool (Q1): users with the therapist role, or staff marked "Performs
 * treatments", with an active staff record at the branch.
 * Free: no overlapping reserved/pending/ongoing booking, no approved leave that
 * day, and not marked unavailable for that time (Schedule availability).
 * Verified (D2): required only for ONLINE bookings that are HOME SERVICE.
 * Pick: least jobs that day, then earliest last end time, then first name
 * (Q8b — the rule the staff booking page already used).
 */
class TherapistAssigner
{
    public const ACTIVE_STATUSES = ['reserved', 'pending', 'ongoing'];

    /** D2: only online home-service bookings need an identity-verified therapist. */
    public static function requiresVerified(?string $bookingSource, ?string $serviceType): bool
    {
        return $bookingSource === 'online' && $serviceType === 'in_home';
    }

    /** Everyone who can be assigned at this branch (no time check), first name order. */
    public function pool(int $spaId, int $branchId): Collection
    {
        return User::query()
            ->treatmentProviders()
            ->whereHas('staff', fn ($q) => $q
                ->where('spa_id', $spaId)
                ->where('branch_id', $branchId)
                ->where('employment_status', 'active'))
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();
    }

    /** Q35b: can this branch take online home-service bookings at all right now? */
    public function branchHasVerifiedProvider(int $spaId, int $branchId): bool
    {
        return User::query()
            ->treatmentProviders()
            ->identityVerified()
            ->whereHas('staff', fn ($q) => $q
                ->where('spa_id', $spaId)
                ->where('branch_id', $branchId)
                ->where('employment_status', 'active'))
            ->exists();
    }

    /**
     * Everything needed to answer "who is free" for one branch and date, loaded
     * once (4 queries) so the slot grid can test every slot in memory.
     */
    public function day(int $spaId, int $branchId, string $date, ?int $excludeBookingId = null): DayAvailability
    {
        $pool = $this->pool($spaId, $branchId);
        $ids = $pool->pluck('id');

        $bookings = Booking::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->when($excludeBookingId, fn ($q) => $q->whereKeyNot($excludeBookingId))
            ->get(['id', 'therapist_id', 'start_time', 'end_time']);

        $onLeave = LeaveRequest::approvedUserIdsOnDate($spaId, $branchId, $date);

        $availability = StaffAvailability::query()
            ->where('branch_id', $branchId)
            ->whereDate('date', $date)
            ->whereIn('user_id', $ids)
            ->get()
            ->keyBy('user_id');

        return new DayAvailability($pool, $bookings, $onLeave, $availability);
    }

    /** Free pool members for one time range, first name order. */
    public function available(
        int $spaId,
        int $branchId,
        string $date,
        string $startTime,
        string $endTime,
        bool $requireVerified = false,
        ?int $excludeBookingId = null
    ): Collection {
        return $this->day($spaId, $branchId, $date, $excludeBookingId)
            ->freeBetween($startTime, $endTime, $requireVerified);
    }

    /** The therapist the system assigns: least busy that day. Null when nobody is free. */
    public function pick(
        int $spaId,
        int $branchId,
        string $date,
        string $startTime,
        string $endTime,
        bool $requireVerified = false,
        ?int $excludeBookingId = null
    ): ?User {
        $available = $this->available($spaId, $branchId, $date, $startTime, $endTime, $requireVerified, $excludeBookingId);

        return $this->leastBusy($available, $spaId, $branchId, $date, $excludeBookingId);
    }

    /**
     * Same rule as pick(), applied to a list the caller already has (the staff
     * booking page shows the list and pre-selects this one).
     */
    public function leastBusy(Collection $available, int $spaId, int $branchId, string $date, ?int $excludeBookingId = null): ?User
    {
        if ($available->isEmpty()) {
            return null;
        }

        $stats = Booking::query()
            ->select('therapist_id', DB::raw('COUNT(*) as total_jobs'), DB::raw('MAX(end_time) as last_end_time'))
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->whereDate('appointment_date', $date)
            ->whereIn('therapist_id', $available->pluck('id'))
            ->where('status', '!=', 'cancelled')
            ->when($excludeBookingId, fn ($q) => $q->whereKeyNot($excludeBookingId))
            ->groupBy('therapist_id')
            ->get()
            ->keyBy('therapist_id');

        return $available->sort(function (User $a, User $b) use ($stats) {
            $aJobs = (int) ($stats[$a->id]->total_jobs ?? 0);
            $bJobs = (int) ($stats[$b->id]->total_jobs ?? 0);

            if ($aJobs !== $bJobs) {
                return $aJobs <=> $bJobs;
            }

            $aEnd = (string) ($stats[$a->id]->last_end_time ?? '00:00:00');
            $bEnd = (string) ($stats[$b->id]->last_end_time ?? '00:00:00');

            if ($aEnd !== $bEnd) {
                return strcmp($aEnd, $bEnd);
            }

            return strcmp(mb_strtolower((string) $a->first_name), mb_strtolower((string) $b->first_name));
        })->first();
    }

    /**
     * Why $therapistId can't take $booking's current slot, or null when they can.
     * Used to validate a manual choice (appointment edit, reassignment, leave).
     */
    public function rejectionFor(int $therapistId, Booking $booking, ?string $date = null, ?string $start = null, ?string $end = null, ?string $serviceType = null): ?string
    {
        $date ??= $booking->appointment_date->format('Y-m-d');
        $start ??= (string) $booking->start_time;
        $end ??= (string) $booking->end_time;
        $serviceType ??= $booking->service_type;

        $day = $this->day((int) $booking->spa_id, (int) $booking->branch_id, $date, $booking->exists ? (int) $booking->id : null);
        $therapist = $day->pool->firstWhere('id', $therapistId);

        if (! $therapist) {
            return 'This person can\'t be assigned treatments at this branch.';
        }

        if (self::requiresVerified($booking->booking_source, $serviceType) && ! $therapist->isIdentityVerified()) {
            return 'Online home-service bookings need an identity-verified therapist.';
        }

        if (! $day->freeBetween($start, $end)->contains('id', $therapistId)) {
            return 'This therapist is not free at that time (another booking, approved leave, or marked unavailable).';
        }

        return null;
    }
}
