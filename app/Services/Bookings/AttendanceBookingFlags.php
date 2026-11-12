<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\ReassignmentRequest;
use App\Models\Staff;
use App\Models\StaffAttendance;
use Illuminate\Support\Collection;

/**
 * Hands off the bookings someone leaves behind when they clock out, or are
 * recorded absent / on leave, for TODAY (attendance-aware booking Phase 2, D4).
 * Same pattern as ScheduleBookingFlags / VerificationBookingFlags: each open
 * booking gets a pending reassignment request in the existing Reassignment
 * Requests screen (Approve moves it, Reject keeps it). Nothing is cancelled.
 *
 *   Flagged    reserved/pending, not ended, ends after the time out (Phase 1
 *              Q1 B), or any of the day for absent / on leave.
 *   Still open ongoing, or ended but not checked out: listed, never flagged
 *              (Q5 A; they can't be reassigned).
 *   Undo       the time out is cleared or moved later: their pending
 *              'clocked_out' requests that no longer apply are closed.
 *   Past days  nothing: every booking on a past day has ended.
 *
 * Re-running is safe: a booking with a pending request is not flagged again.
 */
class AttendanceBookingFlags
{
    public const SOURCE = 'clocked_out';

    /**
     * Today's open bookings of this person that $record (or the preview values)
     * would hand off. Empty for other days.
     *
     * @return Collection<int, Booking>
     */
    public function toFlag(Staff $staff, string $date, string $status, ?string $timeOut): Collection
    {
        if (! $staff->user_id || $date !== today()->toDateString()) {
            return collect();
        }

        $gone = in_array($status, TherapistAttendance::GONE_STATUSES, true);
        if (! $gone && blank($timeOut)) {
            return collect();
        }

        return $this->today($staff)
            ->whereIn('status', ['reserved', 'pending'])
            ->notEnded()
            ->get()
            ->filter(fn (Booking $b) => $gone || self::minutes((string) $b->end_time) > self::minutes((string) $timeOut))
            ->values();
    }

    /**
     * Open sessions that can't be handed off: ongoing, or ended without checkout.
     *
     * @return Collection<int, Booking>
     */
    public function stillOpen(Staff $staff, string $date): Collection
    {
        if (! $staff->user_id || $date !== today()->toDateString()) {
            return collect();
        }

        $now = now();

        return $this->today($staff)
            ->whereIn('status', Booking::ACTIVE_STATUSES)
            ->get()
            ->filter(fn (Booking $b) => $b->status === 'ongoing' || ($b->endsAt() && $now->gte($b->endsAt())))
            ->values();
    }

    /** Every active booking of this person today (clock-in list). */
    public function todaysBookings(Staff $staff): Collection
    {
        if (! $staff->user_id) {
            return collect();
        }

        return $this->today($staff)->whereIn('status', Booking::ACTIVE_STATUSES)->get();
    }

    /**
     * Close what no longer applies, then flag what does, for $staff's record on
     * $date. Returns how many bookings were newly flagged.
     */
    public function sync(Staff $staff, string $date): int
    {
        if (! $staff->user_id || $date !== today()->toDateString()) {
            return 0;
        }

        $record = StaffAttendance::where('staff_id', $staff->id)->whereDate('date', $date)->first();
        $status = (string) ($record?->status ?? '');
        $timeOut = $record?->time_out;

        $wanted = $this->toFlag($staff, $date, $status, $timeOut)->keyBy('id');
        $this->closeNoLongerNeeded($staff, $wanted->keys()->all());

        $staff->loadMissing('user');
        $name = $staff->user?->first_name ?: 'The therapist';
        $why = in_array($status, TherapistAttendance::GONE_STATUSES, true)
            ? ($status === 'absent' ? "{$name} is marked absent today." : "{$name} is marked on leave today.")
            : "{$name} clocked out today at ".\Carbon\Carbon::parse((string) $timeOut)->format('g:i A').'.';

        $flagged = 0;
        foreach ($wanted as $booking) {
            if ($booking->reassignmentRequests()->where('status', ReassignmentRequest::STATUS_PENDING)->exists()) {
                continue;
            }

            ReassignmentRequest::create([
                'booking_id'       => $booking->id,
                'requested_by'     => $staff->user_id,
                'old_therapist_id' => $staff->user_id,
                'source'           => self::SOURCE,
                'status'           => ReassignmentRequest::STATUS_PENDING,
                'reason'           => "Left before this booking: {$why} "
                    .'Approve to move it to a therapist who is here, or reject to keep it with them.',
            ]);
            $flagged++;
        }

        return $flagged;
    }

    /** @param  list<int>  $keepBookingIds */
    private function closeNoLongerNeeded(Staff $staff, array $keepBookingIds): int
    {
        return ReassignmentRequest::query()
            ->where('source', self::SOURCE)
            ->where('status', ReassignmentRequest::STATUS_PENDING)
            ->where('old_therapist_id', $staff->user_id)
            ->whereNotIn('booking_id', $keepBookingIds ?: [0])
            // Ended bookings are left to StaleRequestExpiry (marked expired).
            ->whereHas('booking', fn ($q) => $q
                ->where('therapist_id', $staff->user_id)
                ->whereDate('appointment_date', today()->toDateString())
                ->notEnded())
            ->update([
                'status'           => ReassignmentRequest::STATUS_REJECTED,
                'rejection_reason' => 'Closed automatically: the therapist\'s attendance was corrected and they can take this booking.',
                'reviewed_at'      => now(),
                'updated_at'       => now(),
            ]);
    }

    private function today(Staff $staff)
    {
        return Booking::query()
            ->where('therapist_id', $staff->user_id)
            ->where('spa_id', $staff->spa_id)
            ->whereDate('appointment_date', today()->toDateString())
            ->orderBy('start_time');
    }

    private static function minutes(string $time): int
    {
        $c = \Carbon\Carbon::parse($time);

        return $c->hour * 60 + $c->minute;
    }
}
