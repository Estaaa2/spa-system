<?php

namespace App\Services\Bookings;

use App\Models\User;
use App\Services\Staff\ScheduledDay;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * One branch and one date: the pool, that day's active bookings, who is on
 * leave, and each pool member's work schedule for the day (staff scheduling
 * Phase 3: replaces the old Schedule-availability rows). Built by
 * TherapistAssigner::day(); answers "who is free between these times" without
 * more queries.
 *
 * Free = on shift for the WHOLE range (WorkScheduleResolver: closed day, day
 * off, leave and rest day all mean "not working"), not on leave, and no
 * overlapping booking.
 */
class DayAvailability
{
    public const NOT_ON_SHIFT = 'not_on_shift';
    public const ON_LEAVE = 'on_leave';
    public const BUSY = 'busy';

    /** @var array<int, list<array{0: Carbon, 1: Carbon}>> therapist id => busy windows */
    private array $busy = [];

    /** @var list<array{0: Carbon, 1: Carbon}> bookings with no therapist yet */
    private array $unassigned = [];

    /** @var list<int> */
    private array $onLeave;

    /**
     * @param  Collection<int, User>  $pool
     * @param  list<int>  $onLeave
     * @param  array<int, ScheduledDay>  $shifts  user id => that day's schedule; a pool
     *                                            member missing here is treated as on shift
     */
    public function __construct(
        public readonly Collection $pool,
        Collection $bookings,
        array $onLeave,
        private readonly array $shifts = [],
    ) {
        $this->onLeave = array_map('intval', $onLeave);
        foreach ($bookings as $booking) {
            $window = [Carbon::parse($booking->start_time), Carbon::parse($booking->end_time)];

            if ($booking->therapist_id) {
                $this->busy[(int) $booking->therapist_id][] = $window;
            } else {
                $this->unassigned[] = $window;
            }
        }
    }

    /** Pool members free for the whole range, first name order. */
    public function freeBetween(string $startTime, string $endTime, bool $requireVerified = false): Collection
    {
        return $this->pool
            ->filter(fn (User $u) => ! $requireVerified || $u->isIdentityVerified())
            ->filter(fn (User $u) => $this->reasonNotFree((int) $u->id, $startTime, $endTime) === null)
            ->values();
    }

    /**
     * How many more bookings this range can take: free people minus bookings in
     * the range that still need someone (paid but unassigned, decision Q17).
     */
    public function capacityBetween(string $startTime, string $endTime, bool $requireVerified = false): int
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        $waiting = count(array_filter($this->unassigned, fn ($w) => $start->lt($w[1]) && $end->gt($w[0])));

        return max($this->freeBetween($startTime, $endTime, $requireVerified)->count() - $waiting, 0);
    }

    /** Pool members whose shift covers the whole range, ignoring bookings. */
    public function onShiftBetween(string $startTime, string $endTime, bool $requireVerified = false): Collection
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        return $this->pool
            ->filter(fn (User $u) => ! $requireVerified || $u->isIdentityVerified())
            ->filter(fn (User $u) => $this->covers((int) $u->id, $start, $end))
            ->values();
    }

    /** Is anyone in the pool working at all this day? */
    public function anyoneWorking(bool $requireVerified = false): bool
    {
        return $this->pool->contains(fn (User $u) => (! $requireVerified || $u->isIdentityVerified())
            && ($this->shifts[(int) $u->id] ?? null)?->isWorking() !== false);
    }

    public function shiftFor(int $userId): ?ScheduledDay
    {
        return $this->shifts[$userId] ?? null;
    }

    /** self::NOT_ON_SHIFT | self::ON_LEAVE | self::BUSY, or null when free. */
    public function reasonNotFree(int $userId, string $startTime, string $endTime): ?string
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        if (! $this->covers($userId, $start, $end)) {
            return ($this->shifts[$userId] ?? null)?->status === ScheduledDay::ON_LEAVE ? self::ON_LEAVE : self::NOT_ON_SHIFT;
        }
        if (in_array($userId, $this->onLeave, true)) {
            return self::ON_LEAVE;
        }
        if ($this->overlapsAny($this->busy[$userId] ?? [], $start, $end)) {
            return self::BUSY;
        }

        return null;
    }

    public function hasVerifiedMember(): bool
    {
        return $this->pool->contains(fn (User $u) => $u->isIdentityVerified());
    }

    /** The whole range sits inside the person's shift for the day. */
    private function covers(int $userId, Carbon $start, Carbon $end): bool
    {
        $shift = $this->shifts[$userId] ?? null;
        if ($shift === null) {
            return true;
        }
        if (! $shift->isWorking() || $shift->start === null || $shift->end === null) {
            return false;
        }

        return $start->gte(Carbon::parse($shift->start)) && $end->lte(Carbon::parse($shift->end));
    }

    private function overlapsAny(array $windows, Carbon $start, Carbon $end): bool
    {
        foreach ($windows as [$from, $to]) {
            if ($start->lt($to) && $end->gt($from)) {
                return true;
            }
        }

        return false;
    }
}
