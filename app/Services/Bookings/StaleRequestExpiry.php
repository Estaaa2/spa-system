<?php

namespace App\Services\Bookings;

use App\Mail\RescheduleExpiredMail;
use App\Models\ReassignmentRequest;
use App\Models\RescheduleRequest;
use App\Services\Navigation\NavBadges;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Closes requests nobody answered in time (booking lifecycle fix, Phase 3).
 *
 *   Reschedule (D5)    pending past the booking's ORIGINAL start time → expired.
 *                      The booking keeps its schedule; if it's still open, the
 *                      customer is emailed (Q3-A). A booking that was already
 *                      cancelled / completed / no-show expires silently.
 *   Reassignment (D6)  pending after the booking ENDED → expired. Includes the
 *                      ones VerificationBookingFlags creates (Q4-A). No email.
 *
 * Runs from `bookings:sync-statuses` (every 15 min). Approve/reject also check
 * the deadline themselves, so nothing is approvable between runs. Each row is
 * re-checked under a lock, so a request approved meanwhile is left alone.
 * Re-running finds nothing new.
 */
class StaleRequestExpiry
{
    /** @return array{reschedules: int, reassignments: int, emails: int} */
    public function run(): array
    {
        $counts = ['reschedules' => 0, 'reassignments' => 0, 'emails' => 0];

        RescheduleRequest::query()
            ->where('status', RescheduleRequest::STATUS_PENDING)
            ->with('booking')
            ->chunkById(200, function ($requests) use (&$counts) {
                foreach ($requests as $request) {
                    if ($request->isPastDeadline() && ($result = $this->expireReschedule($request))) {
                        $counts['reschedules']++;
                        $counts['emails'] += $result === 'emailed' ? 1 : 0;
                    }
                }
            });

        ReassignmentRequest::query()
            ->where('status', ReassignmentRequest::STATUS_PENDING)
            ->with('booking')
            ->chunkById(200, function ($requests) use (&$counts) {
                foreach ($requests as $request) {
                    if ($request->isPastDeadline() && $this->expireReassignment($request)) {
                        $counts['reassignments']++;
                    }
                }
            });

        return $counts;
    }

    /**
     * Expire one reschedule request if it is still pending. Returns null when
     * nothing changed (approved or rejected meanwhile), 'emailed' when the
     * customer was told, 'expired' otherwise. The caller checks the deadline.
     */
    public function expireReschedule(RescheduleRequest $request): ?string
    {
        $expired = DB::transaction(function () use ($request) {
            $row = RescheduleRequest::whereKey($request->id)->lockForUpdate()->first();

            if (! $row || ! $row->isPending()) {
                return null;
            }

            $row->update([
                'status'           => RescheduleRequest::STATUS_EXPIRED,
                'rejection_reason' => RescheduleRequest::EXPIRED_REASON,
                'reviewed_at'      => now(),
            ]);

            return $row;
        });

        if (! $expired) {
            return null;
        }

        $this->bumpBadges($expired->booking?->spa_id);

        return $this->emailCustomer($expired) ? 'emailed' : 'expired';
    }

    /** Expire one reassignment request if it is still pending. */
    public function expireReassignment(ReassignmentRequest $request): bool
    {
        $changed = ReassignmentRequest::whereKey($request->id)
            ->where('status', ReassignmentRequest::STATUS_PENDING)
            ->update([
                'status'           => ReassignmentRequest::STATUS_EXPIRED,
                'rejection_reason' => ReassignmentRequest::EXPIRED_REASON,
                'reviewed_at'      => now(),
                'updated_at'       => now(),
            ]);

        if ($changed) {
            $this->bumpBadges($request->booking?->spa_id);
        }

        return $changed > 0;
    }

    private function bumpBadges(?int $spaId): void
    {
        if ($spaId) {
            NavBadges::bump($spaId);
        }
    }

    /** Only while the booking is still open: its original time is what stands. */
    private function emailCustomer(RescheduleRequest $request): bool
    {
        $request->loadMissing(['booking.branch.profile', 'requestedBy']);
        $booking = $request->booking;
        $email = $request->requestedBy?->email;

        if (! $booking || ! $email || ! in_array($booking->status, \App\Models\Booking::ACTIVE_STATUSES, true)) {
            return false;
        }

        try {
            Mail::to($email)->send(new RescheduleExpiredMail($request));

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send reschedule expired email.', [
                'reschedule_request_id' => $request->id,
                'message'               => $e->getMessage(),
            ]);

            return false;
        }
    }
}
