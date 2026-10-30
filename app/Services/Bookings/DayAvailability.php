<?php

namespace App\Services\Bookings;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * One branch and one date: the pool, that day's active bookings, who is on
 * leave and the Schedule availability rows. Built by TherapistAssigner::day();
 * answers "who is free between these times" without more queries.
 */
class DayAvailability
{
    /** @var array<int, list<array{0: Carbon, 1: Carbon}>> therapist id => busy windows */
    private array $busy = [];

    /** @var list<array{0: Carbon, 1: Carbon}> bookings with no therapist yet */
    private array $unassigned = [];

    /** @var list<int> */
    private array $onLeave;

    /**
     * @param  Collection<int, User>  $pool
     * @param  list<int>  $onLeave
     */
    public function __construct(
        public readonly Collection $pool,
        Collection $bookings,
        array $onLeave,
        private readonly Collection $availability,
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
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        return $this->pool
            ->filter(fn (User $u) => ! $requireVerified || $u->isIdentityVerified())
            ->reject(fn (User $u) => in_array((int) $u->id, $this->onLeave, true))
            ->reject(fn (User $u) => $this->overlapsAny($this->busy[(int) $u->id] ?? [], $start, $end))
            ->reject(fn (User $u) => $this->markedUnavailable((int) $u->id, $start, $end))
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

    public function hasVerifiedMember(): bool
    {
        return $this->pool->contains(fn (User $u) => $u->isIdentityVerified());
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

    /** Same rule as the old BookingController::getAvailableTherapists(). */
    private function markedUnavailable(int $userId, Carbon $start, Carbon $end): bool
    {
        $row = $this->availability->get($userId);

        if (! $row) {
            return false;
        }

        if ($row->status === 'unavailable') {
            return true;
        }

        if ($row->status === 'partial') {
            if (! $row->start_time || ! $row->end_time) {
                return true;
            }

            return $start->lt(Carbon::parse($row->start_time)) || $end->gt(Carbon::parse($row->end_time));
        }

        return false;
    }
}
