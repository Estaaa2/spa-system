<?php

declare(strict_types=1);

namespace App\Services\Payroll\ValueObjects;

final readonly class AttendanceResult
{
    /**
     * @param list<EarningLine> $lines
     * @param list<WorkedDay>   $workedDays
     * @param list<string>      $warnings
     */
    public function __construct(
        public array $lines,
        public array $workedDays,
        public array $warnings,
    ) {
    }
}
