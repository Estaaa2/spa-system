<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Models\Staff;
use App\Models\StaffScheduleOverride;
use App\Models\StaffWorkSchedule;
use Illuminate\Support\Facades\DB;

/**
 * Writes weekly patterns and one-off changes (staff scheduling Phase 2).
 * Used by the Staff page, the backfill command and (Phase 5) hiring pre-fill.
 * Validation of who/when lives in the caller; this class keeps the
 * effective-dated history consistent.
 */
final class WorkScheduleWriter
{
    public const MODE_REST = 'rest';
    public const MODE_BRANCH = 'branch';
    public const MODE_CUSTOM = 'custom';

    public function __construct(private readonly WorkScheduleResolver $resolver)
    {
    }

    /**
     * Save a weekly pattern starting $effectiveFrom. Versions starting on or after
     * that date are replaced; the version in effect the day before is ended there.
     *
     * @param array<string, array{mode: string, start?: ?string, end?: ?string}> $days weekday → mode/times
     */
    public function savePattern(Staff $staff, string $effectiveFrom, array $days, ?int $userId): StaffWorkSchedule
    {
        $schedule = DB::transaction(function () use ($staff, $effectiveFrom, $days, $userId) {
            StaffWorkSchedule::query()
                ->where('staff_id', $staff->id)
                ->whereDate('effective_from', '>=', $effectiveFrom)
                ->get()
                ->each->delete();

            $dayBefore = (new \DateTimeImmutable($effectiveFrom))->modify('-1 day')->format('Y-m-d');
            StaffWorkSchedule::query()
                ->where('staff_id', $staff->id)
                ->effectiveOn($effectiveFrom)
                ->get()
                ->each(fn (StaffWorkSchedule $s) => $s->update(['effective_to' => $dayBefore]));

            $schedule = StaffWorkSchedule::create([
                'staff_id'       => $staff->id,
                'effective_from' => $effectiveFrom,
                'effective_to'   => null,
                'created_by'     => $userId,
            ]);

            foreach (StaffWorkSchedule::WEEKDAYS as $weekday) {
                $d = $days[$weekday] ?? ['mode' => self::MODE_BRANCH];
                $mode = $d['mode'] ?? self::MODE_BRANCH;
                $schedule->days()->create([
                    'day_of_week' => $weekday,
                    'is_rest_day' => $mode === self::MODE_REST,
                    'start_time'  => $mode === self::MODE_CUSTOM ? $d['start'] : null,
                    'end_time'    => $mode === self::MODE_CUSTOM ? $d['end'] : null,
                ]);
            }

            return $schedule;
        });

        $this->resolver->flush();

        return $schedule->load('days');
    }

    /** Add or replace the one-off change on $date. */
    public function saveOverride(Staff $staff, string $date, string $type, ?string $start, ?string $end, ?string $reason, ?int $userId): StaffScheduleOverride
    {
        $override = StaffScheduleOverride::query()
            ->where('staff_id', $staff->id)
            ->whereDate('date', $date)
            ->first() ?? new StaffScheduleOverride(['staff_id' => $staff->id, 'date' => $date]);

        $override->fill([
                'type'       => $type,
                'start_time' => $type === StaffScheduleOverride::SHIFT ? $start : null,
                'end_time'   => $type === StaffScheduleOverride::SHIFT ? $end : null,
                'reason'     => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
                'created_by' => $userId,
        ])->save();
        $this->resolver->flush();

        return $override;
    }

    public function deleteOverride(StaffScheduleOverride $override): void
    {
        $override->delete();
        $this->resolver->flush();
    }

    /** @param array<string, array{mode: string}> $days */
    public static function hasRestDay(array $days): bool
    {
        foreach ($days as $d) {
            if (($d['mode'] ?? null) === self::MODE_REST) {
                return true;
            }
        }

        return false;
    }
}
