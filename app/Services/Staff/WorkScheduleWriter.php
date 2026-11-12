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

    /**
     * Add or replace the one-off change on $date. A day off is an unpaid day off
     * (Phase 4, Q2 A); a shift is changed or extra hours. Replacing one half of a
     * rest-day swap removes the other half too.
     */
    public function saveOverride(Staff $staff, string $date, string $type, ?string $start, ?string $end, ?string $reason, ?int $userId): StaffScheduleOverride
    {
        $override = DB::transaction(function () use ($staff, $date, $type, $start, $end, $reason, $userId) {
            $this->clearDate($staff, $date);

            return StaffScheduleOverride::create([
                'staff_id'   => $staff->id,
                'date'       => $date,
                'type'       => $type,
                'kind'       => $type === StaffScheduleOverride::DAY_OFF ? StaffScheduleOverride::KIND_UNPAID : null,
                'start_time' => $type === StaffScheduleOverride::SHIFT ? $start : null,
                'end_time'   => $type === StaffScheduleOverride::SHIFT ? $end : null,
                'reason'     => $this->cleanReason($reason),
                'created_by' => $userId,
            ]);
        });
        $this->resolver->flush();

        return $override;
    }

    /**
     * Rest-day swap (Phase 4, Q2 A): a day off on $offDate (a workday) and a shift
     * on $workDate (that week's rest day), saved together and linked. Null times on
     * the work day = the branch's hours. Callers validate the dates.
     *
     * @return array{0: StaffScheduleOverride, 1: StaffScheduleOverride} [day off, shift]
     */
    public function saveSwap(Staff $staff, string $offDate, string $workDate, ?string $start, ?string $end, ?string $reason, ?int $userId): array
    {
        $pair = DB::transaction(function () use ($staff, $offDate, $workDate, $start, $end, $reason, $userId) {
            $this->clearDate($staff, $offDate);
            $this->clearDate($staff, $workDate);

            $reason = $this->cleanReason($reason);
            $off = StaffScheduleOverride::create([
                'staff_id' => $staff->id, 'date' => $offDate, 'type' => StaffScheduleOverride::DAY_OFF,
                'kind' => StaffScheduleOverride::KIND_SWAP, 'reason' => $reason, 'created_by' => $userId,
            ]);
            $work = StaffScheduleOverride::create([
                'staff_id' => $staff->id, 'date' => $workDate, 'type' => StaffScheduleOverride::SHIFT,
                'kind' => StaffScheduleOverride::KIND_SWAP, 'paired_override_id' => $off->id,
                'start_time' => $start, 'end_time' => $end, 'reason' => $reason, 'created_by' => $userId,
            ]);
            $off->update(['paired_override_id' => $work->id]);

            return [$off->fresh(), $work];
        });
        $this->resolver->flush();

        return $pair;
    }

    /** Remove a one-off change; a swap is removed as a pair. */
    public function deleteOverride(StaffScheduleOverride $override): void
    {
        DB::transaction(function () use ($override) {
            $pairId = $override->paired_override_id;
            $override->delete();
            if ($pairId) {
                StaffScheduleOverride::whereKey($pairId)->delete();
            }
        });
        $this->resolver->flush();
    }

    /** Delete whatever change is on $date (and its swap partner). */
    private function clearDate(Staff $staff, string $date): void
    {
        $existing = StaffScheduleOverride::query()
            ->where('staff_id', $staff->id)
            ->whereDate('date', $date)
            ->first();
        if ($existing === null) {
            return;
        }
        $pairId = $existing->paired_override_id;
        $existing->delete();
        if ($pairId) {
            StaffScheduleOverride::whereKey($pairId)->delete();
        }
    }

    private function cleanReason(?string $reason): ?string
    {
        return $reason !== null && trim($reason) !== '' ? trim($reason) : null;
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
