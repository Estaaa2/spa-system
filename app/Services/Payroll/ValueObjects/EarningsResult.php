<?php

declare(strict_types=1);

namespace App\Services\Payroll\ValueObjects;

use App\Services\Payroll\Support\Decimal;

/**
 * Output of EarningsCalculator::calculate() for one staff member and one cutoff.
 * Nothing here is saved; Unit 4 persists it.
 */
final readonly class EarningsResult
{
    /**
     * @param list<EarningLine> $lines
     * @param string            $daysWorked days with status present/late and a pay profile (payslips.days_worked)
     * @param list<string>      $warnings   things a reviewer must see before approving (never silent drops)
     */
    public function __construct(
        public array $lines,
        public bool $isMwe,
        public string $daysWorked,
        public array $warnings,
    ) {
    }

    public function gross(): string
    {
        $sum = '0';
        foreach ($this->lines as $line) {
            $sum = Decimal::add($sum, $line->amount);
        }

        return Decimal::round2($sum);
    }

    /** @return list<EarningLine> */
    public function linesFor(string $componentCode): array
    {
        return array_values(array_filter($this->lines, static fn (EarningLine $l): bool => $l->componentCode === $componentCode));
    }

    public function totalFor(string $componentCode): string
    {
        $sum = '0';
        foreach ($this->linesFor($componentCode) as $line) {
            $sum = Decimal::add($sum, $line->amount);
        }

        return Decimal::round2($sum);
    }
}
