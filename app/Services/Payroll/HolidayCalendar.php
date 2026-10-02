<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Exceptions\PayrollSetupException;
use App\Services\Payroll\ValueObjects\Holiday;
use OutOfRangeException;

/**
 * National holidays for a cutoff plus its lookback window, keyed by date.
 *
 *  - A missing holiday list for a year INSIDE the cutoff throws (never pay a year
 *    as holiday-free). A missing list for a lookback-only year becomes a warning.
 *  - Two holidays on one date (e.g. Araw ng Kagitingan on Maundy Thursday — a
 *    "double holiday", 200% even if unworked per the DOLE Handbook) are NOT
 *    implemented (rates sheet §6 — UNVERIFIED, none in 2026/2027). Rather than
 *    silently keeping one, this throws so the run is paid manually.
 */
final class HolidayCalendar
{
    public function __construct(private readonly StatutoryCalculator $calc)
    {
    }

    /**
     * @param list<string> $warnings
     * @return array<string, Holiday>
     */
    public function forCutoff(string $periodStart, string $periodEnd, int $lookbackDays, array &$warnings): array
    {
        $from = (new \DateTimeImmutable($periodStart))->modify("-{$lookbackDays} days")->format('Y-m-d');
        $map = [];

        for ($y = (int) substr($from, 0, 4); $y <= (int) substr($periodEnd, 0, 4); $y++) {
            $a = max($from, "{$y}-01-01");
            $b = min($periodEnd, "{$y}-12-31");
            try {
                $list = $this->calc->holidaysBetween($a, $b);
            } catch (OutOfRangeException $e) {
                if ($b >= $periodStart) {
                    throw $e;
                }
                $warnings[] = "No national holiday list for {$y}: the preceding-workday check treats {$a} to {$b} as ordinary days.";
                continue;
            }

            foreach ($list as $h) {
                if (isset($map[$h->date]) && $h->type !== Holiday::SPECIAL_WORKING && $map[$h->date]->type !== Holiday::SPECIAL_WORKING) {
                    throw new PayrollSetupException(sprintf(
                        '%s is both "%s" and "%s". Double holidays are not supported by the earnings engine — pay this cutoff\'s holiday lines with manual adjustments.',
                        $h->date, $map[$h->date]->name, $h->name,
                    ));
                }
                $map[$h->date] = $h;
            }
        }

        return $map;
    }
}
