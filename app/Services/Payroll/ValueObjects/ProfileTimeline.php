<?php

declare(strict_types=1);

namespace App\Services\Payroll\ValueObjects;

/**
 * A staff member's pay profiles, oldest first. StaffPayProfile blocks overlaps on
 * save, so at most one profile covers a date.
 */
final readonly class ProfileTimeline
{
    /** @var list<PayProfileSnapshot> */
    public array $profiles;

    /** @param list<PayProfileSnapshot> $profiles */
    public function __construct(array $profiles)
    {
        usort($profiles, static fn (PayProfileSnapshot $a, PayProfileSnapshot $b): int => strcmp($a->effectiveFrom, $b->effectiveFrom));
        $this->profiles = $profiles;
    }

    public function on(string $ymd): ?PayProfileSnapshot
    {
        foreach ($this->profiles as $p) {
            if ($p->covers($ymd)) {
                return $p;
            }
        }

        return null;
    }

    /** @return list<PayProfileSnapshot> profiles intersecting [from, to] */
    public function overlapping(string $from, string $to): array
    {
        return array_values(array_filter(
            $this->profiles,
            static fn (PayProfileSnapshot $p): bool => $p->effectiveFrom <= $to && ($p->effectiveTo === null || $p->effectiveTo >= $from),
        ));
    }
}
