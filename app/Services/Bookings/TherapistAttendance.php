<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\StaffAttendance;
use App\Models\User;
use Carbon\Carbon;

/**
 * Attendance as it affects bookings (attendance-aware booking, Phase 1).
 *
 * Only explicit "not here" records count (D1/D2, Q2 A): a time out recorded for
 * the day, or a manual Absent / On leave. A missing clock-in hides nobody.
 *
 *   Q1 B  a time out blocks only time ranges that END after it, so sessions
 *         finished before the clock-out can still be edited and checked out.
 *   D3    Start is refused for a therapist who is gone (today only); Checkout
 *         needs a confirmation when the clock-out came before the booking's
 *         start (Q4 B), or the person was marked absent / on leave by a manager.
 */
class TherapistAttendance
{
    public const GONE_STATUSES = ['absent', 'on_leave'];

    /**
     * Records that say "gone" on $date, keyed by user id. Users with no such
     * record are left out.
     *
     * @param  iterable<int>  $userIds
     * @return array<int, StaffAttendance>
     */
    public function goneOn(iterable $userIds, int $spaId, string $date): array
    {
        $ids = collect($userIds)->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $rows = StaffAttendance::query()
            ->join('staff', 'staff.id', '=', 'staff_attendance.staff_id')
            ->whereIn('staff.user_id', $ids)
            ->where('staff.spa_id', $spaId)
            ->whereDate('staff_attendance.date', $date)
            // The table has a deleted_at column but the model no SoftDeletes (same
            // filter as payroll's AttendanceEarnings).
            ->whereNull('staff_attendance.deleted_at')
            ->where(fn ($q) => $q
                ->whereIn('staff_attendance.status', self::GONE_STATUSES)
                ->orWhereNotNull('staff_attendance.time_out'))
            ->get(['staff_attendance.*', 'staff.user_id as attendance_user_id']);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->attendance_user_id] = $row;
        }

        return $out;
    }

    /** One user's "gone" record on $date, or null. */
    public function goneRecord(int $userId, int $spaId, string $date): ?StaffAttendance
    {
        return $this->goneOn([$userId], $spaId, $date)[$userId] ?? null;
    }

    /** The booking's therapist's "gone" record on the booking's date, or null. */
    public function recordFor(Booking $booking): ?StaffAttendance
    {
        if (! $booking->therapist_id || ! $booking->appointment_date) {
            return null;
        }

        return $this->goneRecord((int) $booking->therapist_id, (int) $booking->spa_id, $booking->appointment_date->toDateString());
    }

    /**
     * Both answers with one query, for list pages.
     *
     * @return array{start_block: ?string, clockout_warning: ?string}
     */
    public function actionsFor(Booking $booking): array
    {
        $record = $this->recordFor($booking);

        return [
            'start_block'      => $this->startBlocker($booking, $record),
            'clockout_warning' => $this->checkoutWarning($booking, $record),
        ];
    }

    /** Does this record rule the person out of a range ending at $end (H:i or H:i:s)? */
    public static function blocksRangeEnding(StaffAttendance $record, string $end): bool
    {
        if (in_array($record->status, self::GONE_STATUSES, true)) {
            return true;
        }

        return $record->time_out !== null
            && self::minutes($end) > self::minutes((string) $record->time_out);
    }

    /** "Ana clocked out today at 5:03 PM." / "Ana is marked absent today." */
    public static function message(?User $therapist, StaffAttendance $record, string $when = 'today'): string
    {
        $name = trim((string) $therapist?->first_name) ?: 'This therapist';

        return match (true) {
            $record->status === 'absent'   => "{$name} is marked absent {$when}.",
            $record->status === 'on_leave' => "{$name} is marked on leave {$when}.",
            default                        => "{$name} clocked out {$when} at ".self::clock((string) $record->time_out).'.',
        };
    }

    /**
     * Why Start is refused (D3), or null. Pass $record when already loaded
     * (null = no record); leave it out to load it. Today's bookings only: attendance for
     * other days says nothing about whether the person is here now.
     */
    public function startBlocker(Booking $booking, StaffAttendance|false|null $record = false): ?string
    {
        if (! $booking->therapist_id || ! $booking->appointment_date?->isToday()) {
            return null;
        }

        $record = $record === false ? $this->recordFor($booking) : $record;
        if (! $record || ! self::blocksRangeEnding($record, (string) $booking->end_time)) {
            return null;
        }

        return self::message($booking->therapist, $record).' Edit the appointment and choose another therapist before starting it.';
    }

    /**
     * Why Checkout needs a confirmation (D3, Q4 B), or null: the therapist's
     * time out came before the booking's start, or a manager marked them absent
     * or on leave that day. Rows the nightly job wrote (auto-closed time out,
     * system absent) are left out: they record a forgotten clock-in or
     * clock-out, not that the person left.
     */
    public function checkoutWarning(Booking $booking, StaffAttendance|false|null $record = false): ?string
    {
        if (! $booking->therapist_id || ! $booking->appointment_date) {
            return null;
        }

        $record = $record === false ? $this->recordFor($booking) : $record;
        if (! $record) {
            return null;
        }

        $isGoneStatus = in_array($record->status, self::GONE_STATUSES, true);
        $leftBeforeStart = $record->time_out !== null
            && ! $record->auto_closed
            && self::minutes((string) $record->time_out) < self::minutes((string) $booking->start_time);

        if ($isGoneStatus && $record->source === 'system') {
            return null;
        }
        if (! $isGoneStatus && ! $leftBeforeStart) {
            return null;
        }

        $name = trim((string) $booking->therapist?->first_name) ?: 'The therapist';
        $when = $booking->appointment_date->isToday() ? 'today' : 'on '.$booking->appointment_date->format('M j');
        $what = $isGoneStatus
            ? rtrim(self::message($booking->therapist, $record, $when), '.')
            : "{$name} clocked out {$when} at ".self::clock((string) $record->time_out)
              .', before this session\'s '.self::clock((string) $booking->start_time).' start';

        return $what.'. Check out only if '.$name.' really did this session; otherwise change the therapist first.';
    }

    private static function minutes(string $time): int
    {
        $c = Carbon::parse($time);

        return $c->hour * 60 + $c->minute;
    }

    private static function clock(string $time): string
    {
        return Carbon::parse($time)->format('g:i A');
    }
}
