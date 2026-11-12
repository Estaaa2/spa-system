<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Models\User;
use App\Services\Staff\ScheduledDay;
use App\Services\Staff\WorkScheduleResolver;
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
 * Free: on shift for the whole booking (work schedule via WorkScheduleResolver,
 * staff scheduling Phase 3: rest days, one-off days off, closed days and leave
 * all mean "not working"; no schedule = branch hours, D8), no approved leave
 * that day, and no overlapping reserved/pending/ongoing booking.
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

    public function __construct(private readonly WorkScheduleResolver $schedules)
    {
    }

    /**
     * Everything needed to answer "who is free" for one branch and date, loaded
     * once so the slot grid can test every slot in memory.
     */
    public function day(int $spaId, int $branchId, string $date, ?int $excludeBookingId = null): DayAvailability
    {
        $pool = $this->pool($spaId, $branchId);

        $bookings = Booking::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->when($excludeBookingId, fn ($q) => $q->whereKeyNot($excludeBookingId))
            ->get(['id', 'therapist_id', 'start_time', 'end_time']);

        $onLeave = LeaveRequest::approvedUserIdsOnDate($spaId, $branchId, $date);

        return new DayAvailability($pool, $bookings, $onLeave, $this->shiftsFor($pool, $spaId, $branchId, $date));
    }

    /**
     * Each pool member's schedule for $date at $branchId, keyed by user id.
     *
     * @return array<int, ScheduledDay>
     */
    public function shiftsFor(Collection $pool, int $spaId, int $branchId, string $date): array
    {
        $staff = $this->staffFor($pool, $spaId, $branchId);
        $this->schedules->prime($staff, $date, $date);

        $out = [];
        foreach ($staff as $userId => $s) {
            $out[$userId] = $this->schedules->forDate($s, $date, $branchId);
        }

        return $out;
    }

    /**
     * Dates in [$from, $to] on which at least one pool member (verified only, when
     * asked) is working at this branch. Used for the online date picker.
     *
     * @param  list<string>  $dates  Y-m-d, already limited to open days
     * @return list<string>
     */
    public function datesWithSomeoneWorking(int $spaId, int $branchId, array $dates, bool $requireVerified = false): array
    {
        if ($dates === []) {
            return [];
        }
        $pool = $this->pool($spaId, $branchId)
            ->filter(fn (User $u) => ! $requireVerified || $u->isIdentityVerified());
        $staff = $this->staffFor($pool, $spaId, $branchId);
        if ($staff->isEmpty()) {
            return [];
        }
        $this->schedules->prime($staff, min($dates), max($dates));

        return array_values(array_filter($dates, fn (string $date) => $staff->contains(
            fn (Staff $s) => $this->schedules->forDate($s, $date, $branchId)->isWorking()
        )));
    }

    /** @return Collection<int, Staff> keyed by user id */
    private function staffFor(Collection $pool, int $spaId, int $branchId): Collection
    {
        if ($pool->isEmpty()) {
            return collect();
        }

        return Staff::query()
            ->whereIn('user_id', $pool->pluck('id'))
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->where('employment_status', 'active')
            ->get()
            ->keyBy('user_id');
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
            ->whereNotIn('status', ['cancelled', Booking::NO_SHOW])
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

        $reason = $day->reasonNotFree($therapistId, substr($start, 0, 5), substr($end, 0, 5));
        if ($reason !== null) {
            return self::rejectionMessage($therapist, $reason, $day->shiftFor($therapistId));
        }

        return null;
    }

    /** Staff-facing reason, e.g. "Outside Ana's shift (1:00 PM – 9:00 PM)." */
    public static function rejectionMessage(User $therapist, string $reason, ?ScheduledDay $shift): string
    {
        $name = trim((string) $therapist->first_name) ?: 'This therapist';

        if ($reason === DayAvailability::BUSY) {
            return "{$name} already has another booking at that time.";
        }
        if ($reason === DayAvailability::ON_LEAVE) {
            return "{$name} is on approved leave that day.";
        }

        return match ($shift?->status) {
            ScheduledDay::REST_DAY => "It's {$name}'s rest day. To book anyway, add a one-off shift on the Staff page first.",
            ScheduledDay::DAY_OFF  => "{$name} has a day off that date. To book anyway, remove or change it on the Staff page first.",
            ScheduledDay::CLOSED   => 'The branch is closed that day.',
            ScheduledDay::WORKING  => "Outside {$name}'s shift ({$shift->label()}). To book anyway, add a one-off shift on the Staff page first.",
            default                => "{$name} is not working at that time.",
        };
    }
}
