<?php

namespace App\Services\Attendance;

use App\Models\Booking;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Services\Staff\ScheduledDay;
use App\Services\Staff\WorkScheduleResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Dashboard "Therapist Status" badge for today (attendance-aware booking
 * Phase 3, D6 / Q3 A): attendance first, then bookings. ONE rule for the first
 * render (Blade) and the polling JSON, so they can't drift.
 *
 *   1 Absent · 2 On leave · 3 Clocked out · 4 In Session · 5 Not clocked in
 *   (shift started) · 6 Available / Finished / Free · Off today (rest day or
 *   day off with nothing booked)
 *
 * Badge colours live in dashboard.blade.php (Tailwind only scans views), keyed
 * by the status key.
 */
class TherapistDayStatus
{
    public const LABELS = [
        'absent'         => 'Absent',
        'on_leave'       => 'On leave',
        'clocked_out'    => 'Clocked out',
        'in_session'     => 'In Session',
        'not_clocked_in' => 'Not clocked in',
        'available'      => 'Available',
        'finished'       => 'Finished',
        'free'           => 'Free',
        'off_today'      => 'Off today',
    ];

    public function __construct(private readonly WorkScheduleResolver $schedules)
    {
    }

    /**
     * @param  Collection  $therapists  users with ongoing_count / remaining_count / completed_count / total_today
     * @return array<int, array{key: string, label: string, note: ?string, note_warn: bool}>  by user id
     */
    public function forTherapists(Collection $therapists, int $spaId, int $branchId): array
    {
        if ($therapists->isEmpty() || $branchId <= 0) {
            return [];
        }

        $today = today()->toDateString();
        $now = now();
        $ids = $therapists->pluck('id')->map(fn ($id) => (int) $id);

        $staff = Staff::query()
            ->whereIn('user_id', $ids)
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->where('employment_status', 'active')
            ->get()
            ->keyBy('user_id');
        $this->schedules->prime($staff, $today, $today);

        $attendance = StaffAttendance::query()
            ->whereIn('staff_id', $staff->pluck('id'))
            ->whereDate('date', $today)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('staff_id');

        // Open sessions that ended without checkout, for the "still open" note.
        $overdue = Booking::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->whereIn('therapist_id', $ids)
            ->whereDate('appointment_date', $today)
            ->whereIn('status', ['reserved', 'pending'])
            ->get(['id', 'therapist_id', 'appointment_date', 'end_time'])
            ->filter(fn (Booking $b) => $b->endsAt() && $now->gte($b->endsAt()))
            ->countBy('therapist_id');

        $out = [];
        foreach ($therapists as $t) {
            $id = (int) $t->id;
            $s = $staff->get($id);
            $shift = $s ? $this->schedules->forDate($s, $today, $branchId) : null;
            $att = $s ? $attendance->get($s->id) : null;

            $out[$id] = $this->decide(
                $att,
                $shift,
                (int) ($t->ongoing_count ?? 0),
                (int) ($t->remaining_count ?? 0),
                (int) ($t->completed_count ?? 0),
                (int) ($overdue[$id] ?? 0),
                $now,
            );
        }

        return $out;
    }

    /** @return array{key: string, label: string, note: ?string, note_warn: bool} */
    public function decide(?StaffAttendance $att, ?ScheduledDay $shift, int $ongoing, int $remaining, int $done, int $overdue, Carbon $now): array
    {
        $timeIn = $att?->time_in ? 'In '.self::clock($att->time_in) : null;
        $working = $shift?->isWorking() && $shift->start !== null;
        $shiftStarted = $working && $now->gte(Carbon::parse($shift->start));

        [$key, $note, $warn] = match (true) {
            $att?->status === 'absent' => ['absent', null, false],
            $att?->status === 'on_leave' || $shift?->status === ScheduledDay::ON_LEAVE => ['on_leave', null, false],
            $att?->time_out !== null => [
                'clocked_out',
                'Out '.self::clock($att->time_out).(($ongoing + $overdue) > 0
                    ? ' · '.($ongoing + $overdue).' '.(($ongoing + $overdue) === 1 ? 'session' : 'sessions').' still open'
                    : ''),
                ($ongoing + $overdue) > 0,
            ],
            $ongoing > 0 => ['in_session', $timeIn ?? 'Not clocked in', $timeIn === null],
            $timeIn === null && $shiftStarted => ['not_clocked_in', 'Shift '.$shift->label(), false],
            $remaining > 0 => ['available', $this->beforeOrIn($timeIn, $working, $shiftStarted, $shift), false],
            $done > 0 => ['finished', $timeIn, false],
            $shift !== null && ! $shift->isWorking() => ['off_today', null, false],
            default => ['free', $this->beforeOrIn($timeIn, $working, $shiftStarted, $shift), false],
        };

        return ['key' => $key, 'label' => self::LABELS[$key], 'note' => $note, 'note_warn' => $warn];
    }

    private function beforeOrIn(?string $timeIn, bool $working, bool $shiftStarted, ?ScheduledDay $shift): ?string
    {
        if ($timeIn !== null) {
            return $timeIn;
        }

        return $working && ! $shiftStarted ? 'Shift starts '.self::clock($shift->start) : null;
    }

    private static function clock(string $time): string
    {
        return Carbon::parse($time)->format('g:i A');
    }
}
