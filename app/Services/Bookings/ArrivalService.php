<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Arrival code for online home-service bookings (D7, Q6, Q41, Q42).
 *
 * The customer sees a 4-digit code. On arrival, the therapist asks for it and
 * enters it. A correct code records the arrival time; it does NOT change the
 * booking status (that stays clock-driven, Q6). Five wrong codes lock it; then
 * someone at the branch with "edit appointments" (not the therapist) confirms
 * by hand with a reason (Q42a). Every outcome is logged, never the code itself.
 *
 * What it proves: the person at the door holds the customer's account, and a
 * hijacked therapist account can't record a fake arrival (it never sees the
 * code). What it does NOT prove: that the therapist is genuine — the verified
 * photo does that.
 */
class ArrivalService
{
    public const MAX_ATTEMPTS = 5;

    /**
     * Home visits a user should see: their own as the assigned therapist (today
     * and the next 7 days), plus every visit today at their branch when they can
     * edit appointments.
     *
     * @return array{mine: Collection<int, Booking>, branch: Collection<int, Booking>}
     */
    public function visitsFor(User $user): array
    {
        $base = fn () => Booking::query()
            ->where('booking_source', 'online')
            ->where('service_type', 'in_home')
            ->whereIn('status', Booking::ACTIVE_STATUSES)
            ->where('spa_id', $user->spa_id)
            ->with(['customer', 'therapist', 'arrivalConfirmedBy'])
            ->orderBy('appointment_date')
            ->orderBy('start_time');

        $mine = $user->spa_id
            ? $base()
                ->where('therapist_id', $user->id)
                ->whereDate('appointment_date', '>=', today()->toDateString())
                ->whereDate('appointment_date', '<=', today()->addDays(7)->toDateString())
                ->get()
            : collect();

        $branch = collect();

        if ($user->spa_id && $user->hasBranchPermission('edit appointments')) {
            $branchId = $user->currentBranchId();

            $branch = $base()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->whereDate('appointment_date', today()->toDateString())
                ->get();
        }

        return ['mine' => $mine, 'branch' => $branch];
    }

    /**
     * Arrival details for the therapist's own booking list (mobile API). Never
     * includes the code.
     *
     * @return array{required: bool, confirmed_at: ?string, method: ?string, can_enter_code: bool, locked: bool, attempts_left: int}|null
     */
    public function stateFor(Booking $booking, User $viewer): ?array
    {
        if (! $booking->requiresArrivalCode()) {
            return null;
        }

        $locked = $booking->arrival_locked_at !== null;

        return [
            'required'       => true,
            'confirmed_at'   => $booking->arrival_confirmed_at?->toIso8601String(),
            'method'         => $booking->arrival_method,
            'can_enter_code' => ! $booking->isArrivalConfirmed()
                && ! $locked
                && in_array($booking->status, Booking::ACTIVE_STATUSES, true)
                && (bool) $booking->appointment_date?->isToday()
                && (int) $booking->therapist_id === (int) $viewer->id,
            'locked'         => $locked,
            'attempts_left'  => max(self::MAX_ATTEMPTS - (int) $booking->arrival_attempts, 0),
        ];
    }

    /** The assigned therapist enters the code the customer tells them. */
    public function confirmWithCode(Booking $booking, User $therapist, string $code): Booking
    {
        $code = preg_replace('/\D/', '', $code);

        $outcome = DB::transaction(function () use ($booking, $therapist, $code) {
            $row = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $this->assertCanConfirm($row);

            if ((int) $row->therapist_id !== (int) $therapist->id) {
                return 'Only the therapist assigned to this booking can enter its code.';
            }

            if ($row->arrival_locked_at) {
                return 'Too many wrong codes. Ask the branch to confirm your arrival.';
            }

            $row->ensureArrivalCode();

            if (strlen($code) === 4 && hash_equals((string) $row->arrival_code, $code)) {
                $row->forceFill([
                    'arrival_confirmed_at' => now(),
                    'arrival_confirmed_by' => $therapist->id,
                    'arrival_method'       => 'code',
                ])->saveQuietly();

                Log::info('Arrival confirmed with code.', ['booking_id' => $row->id, 'user_id' => $therapist->id]);

                return $row;
            }

            $attempts = $row->arrival_attempts + 1;
            $row->forceFill([
                'arrival_attempts'  => $attempts,
                'arrival_locked_at' => $attempts >= self::MAX_ATTEMPTS ? now() : null,
            ])->saveQuietly();

            Log::warning('Wrong arrival code.', ['booking_id' => $row->id, 'user_id' => $therapist->id, 'attempt' => $attempts]);

            $left = self::MAX_ATTEMPTS - $attempts;

            return $left > 0
                ? "That code is not right. {$left} ".($left === 1 ? 'try' : 'tries').' left.'
                : 'That code is not right, and the code is now locked. Ask the branch to confirm your arrival.';
        });

        if (is_string($outcome)) {
            // Thrown after the transaction so a wrong attempt is still counted.
            throw ValidationException::withMessages(['arrival_code' => $outcome]);
        }

        return $outcome;
    }

    /** Q42a: the branch confirms by hand when the code can't be used. */
    public function confirmManually(Booking $booking, User $staff, string $reason): Booking
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['arrival_reason' => 'Write why the code could not be used (at least 5 characters).']);
        }

        return DB::transaction(function () use ($booking, $staff, $reason) {
            $row = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $this->assertCanConfirm($row);

            if ((int) $row->therapist_id === (int) $staff->id) {
                throw ValidationException::withMessages(['arrival_reason' => 'Someone else at the branch must confirm your own arrival.']);
            }

            $row->forceFill([
                'arrival_confirmed_at'  => now(),
                'arrival_confirmed_by'  => $staff->id,
                'arrival_method'        => 'manual',
                'arrival_manual_reason' => mb_substr($reason, 0, 500),
            ])->saveQuietly();

            Log::info('Arrival confirmed manually.', ['booking_id' => $row->id, 'user_id' => $staff->id]);

            return $row;
        });
    }

    private function assertCanConfirm(Booking $row): void
    {
        $problem = match (true) {
            ! $row->requiresArrivalCode()                                       => 'Only online home-service bookings use an arrival code.',
            $row->isArrivalConfirmed()                                          => 'Arrival was already confirmed.',
            ! in_array($row->status, Booking::ACTIVE_STATUSES, true)            => 'This booking is '.$row->status.'.',
            ! $row->appointment_date?->isToday()                                => 'Arrival can only be confirmed on the day of the appointment.',
            $row->therapist_id === null                                         => 'Assign a therapist first.',
            default                                                             => null,
        };

        if ($problem) {
            throw ValidationException::withMessages(['arrival_code' => $problem]);
        }
    }
}
