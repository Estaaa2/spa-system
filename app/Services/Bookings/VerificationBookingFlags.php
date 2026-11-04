<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\ReassignmentRequest;
use App\Models\User;

/**
 * Keeps online home-service bookings honest when a therapist's verified status
 * changes (decisions Q33, Q34).
 *
 * Lost: each upcoming (reserved/pending) online home-service booking of theirs
 * gets a pending reassignment request, so the branch sees it in the existing
 * Reassignment Requests screen and either moves it to a verified therapist
 * (Approve) or keeps it (Reject). Nothing is changed or cancelled automatically.
 * A session already in progress ('ongoing') is left alone.
 *
 * Regained: their still-pending 'unverified' requests are closed, since the
 * reason no longer applies.
 */
class VerificationBookingFlags
{
    public const SOURCE = 'unverified';

    private const REASONS = [
        'photo_changed' => 'changed their profile photo',
        'name_changed'  => 'had their name changed',
        'withdrawn'     => 'withdrew consent to identity verification',
    ];

    /** @return int number of bookings flagged */
    public function flagAfterVerificationLost(User $therapist, string $why): int
    {
        $bookings = Booking::query()
            ->where('therapist_id', $therapist->id)
            ->where('booking_source', 'online')
            ->where('service_type', 'in_home')
            ->whereIn('status', ['reserved', 'pending'])
            ->whereDate('appointment_date', '>=', today()->toDateString())
            ->whereDoesntHave('reassignmentRequests', fn ($q) => $q->where('status', 'pending'))
            ->get(['id', 'therapist_id']);

        $what = self::REASONS[$why] ?? 'is no longer identity-verified';

        foreach ($bookings as $booking) {
            ReassignmentRequest::create([
                'booking_id'       => $booking->id,
                'requested_by'     => $therapist->id,
                'old_therapist_id' => $therapist->id,
                'source'           => self::SOURCE,
                'status'           => 'pending',
                'reason'           => "Not identity-verified anymore: {$therapist->first_name} {$what}. "
                    .'Online home-service bookings need a verified therapist. Approve to move it to a verified therapist, or reject to keep it with them.',
            ]);
        }

        return $bookings->count();
    }

    /** @return int number of requests closed */
    public function closeAfterVerified(User $therapist): int
    {
        return ReassignmentRequest::query()
            ->where('source', self::SOURCE)
            ->where('status', 'pending')
            ->where('old_therapist_id', $therapist->id)
            ->whereHas('booking', fn ($q) => $q->where('therapist_id', $therapist->id))
            ->update([
                'status'           => 'rejected',
                'rejection_reason' => 'Closed automatically: the therapist is identity-verified again.',
                'reviewed_at'      => now(),
                'updated_at'       => now(),
            ]);
    }
}
