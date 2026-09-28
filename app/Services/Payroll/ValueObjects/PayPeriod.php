<?php

declare(strict_types=1);

namespace App\Services\Payroll\ValueObjects;

/**
 * One pay period as built by PayrollRunService::buildPeriod() (locked data model v3.1):
 *  - cutoff 1 = day 1 .. spas.payroll_first_cutoff_day
 *  - cutoff 2 = the next day .. month end
 *  - pay_date = period_end + spas.payroll_pay_day_offset
 * Dates are Y-m-d strings. cutoffNo is null for the 13th-month run.
 */
final readonly class PayPeriod
{
    public function __construct(
        public string $start,
        public string $end,
        public string $payDate,
        public ?int $cutoffNo,
    ) {
    }

    /** Calendar days in the period, both ends inclusive. */
    public function lengthInDays(): int
    {
        return (int) (new \DateTimeImmutable($this->start))->diff(new \DateTimeImmutable($this->end))->days + 1;
    }
}
