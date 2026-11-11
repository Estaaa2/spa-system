<?php

declare(strict_types=1);

namespace App\Services\Payroll\ValueObjects;

/**
 * Everything the earnings services need about one staff member's cutoff,
 * already loaded. $holidays covers the lookback window before periodStart too
 * (for the preceding-workday rule). $closedWeekdays = day names on which the
 * home branch is closed (operating_hours.is_closed) — "non-working day in the
 * establishment" for Omnibus Rules Book III Rule IV Sec. 6(c).
 * $closedWeekdaysByBranch = the same per branch id, so a date is checked against
 * the branch the staff member worked at that day (deployments; staff scheduling
 * Phase 1, decision Q4). A branch missing from the map falls back to the home list.
 */
final readonly class CutoffContext
{
    /**
     * @param array<string, Holiday> $holidays       keyed by Y-m-d
     * @param list<string>           $closedWeekdays e.g. ['Monday']
     * @param array<int, list<string>> $closedWeekdaysByBranch branch id → day names
     */
    public function __construct(
        public int $staffId,
        public int $spaId,
        public int $homeBranchId,
        public string $periodStart,
        public string $periodEnd,
        public int $cutoffNo,
        public ?int $payrollRunId,
        public ProfileTimeline $timeline,
        public array $holidays,
        public array $closedWeekdays = [],
        public array $closedWeekdaysByBranch = [],
    ) {
    }

    /**
     * Is $ymd a closed day at $branchId (the attendance row's branch), or at the
     * home branch when no branch is given or the branch is not in the map?
     */
    public function isBranchClosed(string $ymd, ?int $branchId = null): bool
    {
        $closed = $branchId !== null && array_key_exists($branchId, $this->closedWeekdaysByBranch)
            ? $this->closedWeekdaysByBranch[$branchId]
            : $this->closedWeekdays;

        return in_array((new \DateTimeImmutable($ymd))->format('l'), $closed, true);
    }

    /** @return list<string> every date in [periodStart, periodEnd] */
    public function dates(): array
    {
        return self::range($this->periodStart, $this->periodEnd);
    }

    public function contains(string $ymd): bool
    {
        return $ymd >= $this->periodStart && $ymd <= $this->periodEnd;
    }

    /** Regular or special non-working (special working days are ordinary days — §6). */
    public function nonWorkingHoliday(string $ymd): ?Holiday
    {
        $h = $this->holidays[$ymd] ?? null;

        return $h !== null && $h->type !== Holiday::SPECIAL_WORKING ? $h : null;
    }

    /** @return list<string> */
    public static function range(string $from, string $to): array
    {
        $out = [];
        for ($d = new \DateTimeImmutable($from); $d->format('Y-m-d') <= $to; $d = $d->modify('+1 day')) {
            $out[] = $d->format('Y-m-d');
        }

        return $out;
    }
}
