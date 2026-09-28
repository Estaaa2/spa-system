<?php

declare(strict_types=1);

namespace App\Services\Payroll\ValueObjects;

/** A day that counts as worked (present/late, with a pay profile) — input to top-up and recurring items. */
final readonly class WorkedDay
{
    public function __construct(
        public string $date,
        public int $attendanceId,
        public int $branchId,
        public PayProfileSnapshot $profile,
        public string $dailyRate,
    ) {
    }
}
