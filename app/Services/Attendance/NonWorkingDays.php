<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Models\OperatingHours;
use App\Models\Staff;
use App\Models\StaffPayProfile;

/**
 * Interim answer to "is this date a non-working day for this staff member?"
 * (staff scheduling Phase 1). Phase 4 replaces it with the work-schedule resolver.
 *
 * A date is a non-working day when:
 *  - the branch the staff member works at that day is closed on that weekday
 *    (operating_hours.is_closed) — "non-working day in the establishment",
 *    Omnibus Rules Book III Rule IV Sec. 6(c); or
 *  - it is a rest day in the staff member's pay profile in effect that day
 *    (staff_pay_profiles.rest_days; staff with no profile have no rest days).
 *
 * Closed days are checked first, so a closed day that is also a rest day reports
 * BRANCH_CLOSED. Lookups are memoized per instance; create one per command run.
 */
final class NonWorkingDays
{
    public const BRANCH_CLOSED = 'branch_closed';
    public const REST_DAY = 'rest_day';

    /** @var array<int, list<string>> branch id → closed weekday names */
    private array $closed = [];

    /** @var array<string, list<string>> "staffId|date" → rest weekday names */
    private array $rest = [];

    /** @return self::BRANCH_CLOSED|self::REST_DAY|null */
    public function reason(Staff|int $staff, int $branchId, string $date): ?string
    {
        $staffId = $staff instanceof Staff ? (int) $staff->id : $staff;
        $weekday = (new \DateTimeImmutable($date))->format('l');

        if (in_array($weekday, $this->closedWeekdays($branchId), true)) {
            return self::BRANCH_CLOSED;
        }

        if (in_array($weekday, $this->restWeekdays($staffId, $date), true)) {
            return self::REST_DAY;
        }

        return null;
    }

    /** @return list<string> */
    public function closedWeekdays(int $branchId): array
    {
        return $this->closed[$branchId] ??= OperatingHours::query()
            ->where('branch_id', $branchId)
            ->where('is_closed', true)
            ->pluck('day_of_week')
            ->map(fn ($d) => ucfirst(strtolower(trim((string) $d))))
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function restWeekdays(int $staffId, string $date): array
    {
        return $this->rest[$staffId.'|'.$date] ??= array_values(
            StaffPayProfile::effectiveFor($staffId, $date)?->rest_days ?? []
        );
    }
}
