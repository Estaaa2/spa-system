<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\ReassignmentRequest;
use App\Models\Staff;
use App\Services\Staff\WorkScheduleResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Keeps upcoming bookings honest when a staff member's work schedule changes
 * (staff scheduling Phase 3, Q2 A). Same pattern as VerificationBookingFlags.
 *
 * Outside: each upcoming (reserved/pending, not ended) booking of theirs that no
 * longer fits inside their shift that day gets a pending reassignment request in
 * the existing Reassignment Requests screen: Approve moves it to someone on shift,
 * Reject keeps it. Nothing is changed or cancelled automatically.
 *
 * Back inside: their still-pending 'schedule' requests whose booking fits the
 * schedule again are closed.
 */
class ScheduleBookingFlags
{
    public const SOURCE = 'schedule';

    public function __construct(private readonly WorkScheduleResolver $schedules)
    {
    }

    /**
     * Run both directions from today on. Returns the bookings newly flagged.
     *
     * @return Collection<int, array{booking: Booking, label: string}>
     */
    public function sync(Staff $staff): Collection
    {
        $this->closeNowInside($staff);

        return $this->flagOutside($staff);
    }

    /** @return Collection<int, array{booking: Booking, label: string}> */
    public function flagOutside(Staff $staff): Collection
    {
        $staff->loadMissing('user');
        if (! $staff->user_id) {
            return collect();
        }

        $flagged = collect();
        foreach ($this->upcoming($staff)->whereDoesntHave('reassignmentRequests', fn ($q) => $q->where('status', 'pending'))->get() as $booking) {
            $day = $this->schedules->forDate($staff, $booking->appointment_date->toDateString(), (int) $booking->branch_id);
            if ($this->fits($booking, $day)) {
                continue;
            }

            $name = $staff->user?->first_name ?: 'The therapist';
            $label = $day->isWorking() ? "shift {$day->label()}" : strtolower($day->label());

            ReassignmentRequest::create([
                'booking_id'       => $booking->id,
                'requested_by'     => $staff->user_id,
                'old_therapist_id' => $staff->user_id,
                'source'           => self::SOURCE,
                'status'           => 'pending',
                'reason'           => "Outside the work schedule: {$name}'s schedule on "
                    .$booking->appointment_date->format('D, M j')." is now {$label}. "
                    .'Approve to move it to a therapist on shift, or reject to keep it with them.',
            ]);

            $flagged->push(['booking' => $booking, 'label' => $label]);
        }

        return $flagged;
    }

    /** @return int number of requests closed */
    public function closeNowInside(Staff $staff): int
    {
        if (! $staff->user_id) {
            return 0;
        }

        $closed = 0;
        $requests = ReassignmentRequest::query()
            ->with('booking')
            ->where('source', self::SOURCE)
            ->where('status', 'pending')
            ->where('old_therapist_id', $staff->user_id)
            ->whereHas('booking', fn ($q) => $q->where('therapist_id', $staff->user_id))
            ->get();

        foreach ($requests as $r) {
            $b = $r->booking;
            $day = $this->schedules->forDate($staff, $b->appointment_date->toDateString(), (int) $b->branch_id);
            if ($this->fits($b, $day)) {
                $r->update([
                    'status'           => 'rejected',
                    'rejection_reason' => 'Closed automatically: the booking is inside the therapist\'s work schedule again.',
                    'reviewed_at'      => now(),
                ]);
                $closed++;
            }
        }

        return $closed;
    }

    private function upcoming(Staff $staff)
    {
        return Booking::query()
            ->where('therapist_id', $staff->user_id)
            ->where('spa_id', $staff->spa_id)
            ->whereIn('status', ['reserved', 'pending'])
            ->whereDate('appointment_date', '>=', today()->toDateString())
            ->notEnded()
            ->orderBy('appointment_date')
            ->orderBy('start_time');
    }

    private function fits(Booking $booking, $day): bool
    {
        if (! $day->isWorking() || $day->start === null || $day->end === null) {
            return false;
        }

        return Carbon::parse(substr((string) $booking->start_time, 0, 5))->gte(Carbon::parse($day->start))
            && Carbon::parse(substr((string) $booking->end_time, 0, 5))->lte(Carbon::parse($day->end));
    }
}
