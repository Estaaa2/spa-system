<?php

namespace App\Services\Attendance;

use App\Models\Booking;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Services\Bookings\AttendanceBookingFlags;
use App\Services\Staff\ScheduledDay;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * What a clock-in, clock-out or manual record WILL do, before it is saved
 * (attendance-aware booking Phase 2, D9). Display only: every submit re-checks.
 * Web (HR\AttendanceController) and mobile (Api\AttendanceController) return
 * the same shape.
 */
class ClockPreview
{
    public function __construct(
        private readonly ClockInRules $rules,
        private readonly AttendanceBookingFlags $flags,
    ) {
    }

    /** Self-service clock-in / clock-out, at now. */
    public function self(Staff $staff, string $action): array
    {
        $date = today()->toDateString();
        $time = now()->format('H:i:s');
        $record = StaffAttendance::where('staff_id', $staff->id)->whereDate('date', $date)->first();
        $day = $this->rules->today($staff, $date);

        $base = [
            'action'          => $action,
            'date'            => $date,
            'time'            => substr($time, 0, 5),
            'time_label'      => Carbon::parse($time)->format('g:i A'),
            'shift'           => $this->shift($day),
            'allowed'         => true,
            'refusal'         => null,
            'timing'          => null,
            'reason_required' => false,
            'bookings_today'  => [],
            'to_flag'         => [],
            'still_open'      => [],
        ];

        if ($action === 'in') {
            if ($record && $record->time_in) {
                return ['allowed' => false, 'refusal' => 'You are already clocked in for today.'] + $base;
            }
            $window = $this->rules->clockIn($staff, $date, $time);
            if (! $window['allowed']) {
                return ['allowed' => false, 'refusal' => $window['reason']] + $base;
            }

            // The branch-hours fallback for a branch with no hours at all has no shift to compare.
            $timing = ClockInRules::inTiming($day, $time);

            return [
                'timing'         => $this->timing($timing, $day),
                'bookings_today' => $this->list($this->flags->todaysBookings($staff)),
            ] + $base;
        }

        if (! $record || ! $record->time_in) {
            return ['allowed' => false, 'refusal' => 'You need to clock in before you can clock out.'] + $base;
        }
        if ($record->time_out) {
            return ['allowed' => false, 'refusal' => 'You are already clocked out for today.'] + $base;
        }

        $timing = ClockInRules::outTiming($day, $time);

        return [
            'timing'          => $this->timing($timing, $day),
            'reason_required' => $timing['kind'] === 'early',
            'to_flag'         => $this->list($this->flags->toFlag($staff, $date, (string) $record->status, $time)),
            'still_open'      => $this->list($this->flags->stillOpen($staff, $date)),
        ] + $base;
    }

    /** Manager's manual record for one person and day (recordAttendanceModal). */
    public function manual(Staff $staff, string $date, string $status, ?string $timeIn, ?string $timeOut): array
    {
        $day = $this->rules->today($staff, $date);
        $gone = in_array($status, ['absent', 'on_leave'], true);
        if ($gone) {
            $timeIn = $timeOut = null;
        }

        return [
            'date'        => $date,
            'is_today'    => $date === today()->toDateString(),
            'shift'       => $this->shift($day),
            'time_in'     => $timeIn ? $this->timing(ClockInRules::inTiming($day, $timeIn), $day) : null,
            'time_out'    => $timeOut ? $this->timing(ClockInRules::outTiming($day, $timeOut), $day) : null,
            'to_flag'     => $this->list($this->flags->toFlag($staff, $date, $status, $timeOut)),
            'still_open'  => ($gone || $timeOut) ? $this->list($this->flags->stillOpen($staff, $date)) : [],
        ];
    }

    private function shift(ScheduledDay $day): array
    {
        return [
            'status'  => $day->status,
            'working' => $day->isWorking(),
            'start'   => $day->start ? substr($day->start, 0, 5) : null,
            'end'     => $day->end ? substr($day->end, 0, 5) : null,
            'label'   => $day->label(),
        ];
    }

    /** @param array{kind: ?string, minutes: int} $t */
    private function timing(array $t, ScheduledDay $day): ?array
    {
        if ($t['kind'] === null) {
            return null;
        }
        $d = ClockInRules::duration($t['minutes']);
        $start = $day->start ? Carbon::parse($day->start)->format('g:i A') : '';
        $end = $day->end ? Carbon::parse($day->end)->format('g:i A') : '';

        return [
            'kind'    => $t['kind'],
            'minutes' => $t['minutes'],
            'label'   => match ($t['kind']) {
                'late'        => "Late by {$d} (shift starts {$start})",
                'early'       => "{$d} before the shift ends ({$end})",
                'after_shift' => "{$d} after the shift ended ({$end})",
                default       => 'On time',
            },
        ];
    }

    /** @param Collection<int, Booking> $bookings */
    private function list(Collection $bookings): array
    {
        $now = now();

        return $bookings->map(fn (Booking $b) => [
            'id'           => $b->id,
            'start_time'   => substr((string) $b->start_time, 0, 5),
            'end_time'     => substr((string) $b->end_time, 0, 5),
            'time_label'   => Carbon::parse($b->start_time)->format('g:i A').' – '.Carbon::parse($b->end_time)->format('g:i A'),
            'treatment'    => $b->treatment_label,
            'customer'     => $b->customer_name ?: 'Walk-in customer',
            'service_type' => $b->service_type,
            'status'       => $b->status,
            // Start time passed and nobody started it: the customer may be waiting.
            'waiting'      => $b->status === 'pending' || ($b->status === 'reserved' && $b->startsAt() && $now->gte($b->startsAt())),
        ])->values()->all();
    }
}
