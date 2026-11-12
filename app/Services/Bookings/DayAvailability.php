<?php

namespace App\Services\Bookings;

use App\Models\StaffAttendance;
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
 * off, leave and rest day all mean "not working"), not on leave, not gone
 * according to today's attendance, and no overlapping booking.
 *
 * Attendance (attendance-aware booking, Phase 1): only today, only explicit
 * records (D1/D2, Q2 A). A time out blocks ranges that end after it (Q1 B);
 * Absent / On leave blocks the whole day. Someone who is gone also no longer
 * counts as "on shift", so the slot grid says no_therapist_on_shift (Q3 A).
 */
class DayAvailability
{
    public const NOT_ON_SHIFT = 'not_on_shift';
    public const ON_LEAVE = 'on_leave';
    public const BUSY = 'busy';
    public const CLOCKED_OUT = 'clocked_out';
    public const ABSENT = 'absent';

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
     * @param  array<int, StaffAttendance>  $attendance  user id => today's "gone" record
     *                                                   (TherapistAttendance::goneOn)
     */
    public function __construct(
        public readonly Collection $pool,
        Collection $bookings,
        array $onLeave,
        private readonly array $shifts = [],
        private readonly array $attendance = [],
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
            ->filter(fn (User $u) => $this->attendanceReason((int) $u->id, $endTime) === null)
            ->values();
    }

    /** Is anyone in the pool working at all this day (and not gone for the day)? */
    public function anyoneWorking(bool $requireVerified = false): bool
    {
        return $this->pool->contains(fn (User $u) => (! $requireVerified || $u->isIdentityVerified())
            && ($this->shifts[(int) $u->id] ?? null)?->isWorking() !== false
            && ! isset($this->attendance[(int) $u->id]));
    }

    /** Today's "gone" record for this person, or null. */
    public function attendanceFor(int $userId): ?StaffAttendance
    {
        return $this->attendance[$userId] ?? null;
    }

    public function shiftFor(int $userId): ?ScheduledDay
    {
        return $this->shifts[$userId] ?? null;
    }

    /** self::NOT_ON_SHIFT | ON_LEAVE | CLOCKED_OUT | ABSENT | BUSY, or null when free. */
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
        if ($reason = $this->attendanceReason($userId, $endTime)) {
            return $reason;
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

    /** self::CLOCKED_OUT / self::ABSENT when today's attendance rules out a range ending at $endTime. */
    private function attendanceReason(int $userId, string $endTime): ?string
    {
        $record = $this->attendance[$userId] ?? null;
        if ($record === null || ! TherapistAttendance::blocksRangeEnding($record, $endTime)) {
            return null;
        }

        return in_array($record->status, TherapistAttendance::GONE_STATUSES, true) ? self::ABSENT : self::CLOCKED_OUT;
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
