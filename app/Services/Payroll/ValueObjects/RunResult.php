<?php

declare(strict_types=1);

namespace App\Services\Payroll\ValueObjects;

use App\Models\PayrollRun;

/**
 * Returned by generateRegular() / generateThirteenthMonth().
 * Totals are 2-decimal strings summed from the saved payslips and lines.
 * Warnings are NOT persisted (the locked model has no column for them) —
 * the caller must show or flash them.
 */
final readonly class RunResult
{
    /**
     * @param array{payslips: int, gross: string, deductions: string, net: string, employer_share: string} $totals
     * @param list<RunWarning> $warnings
     */
    public function __construct(
        public PayrollRun $run,
        public bool $regenerated,
        public array $totals,
        public array $warnings,
    ) {
    }

    /** @return list<RunWarning> */
    public function warningsWithCode(string $code): array
    {
        return array_values(array_filter($this->warnings, static fn (RunWarning $w): bool => $w->code === $code));
    }

    public function hasWarning(string $code): bool
    {
        return $this->warningsWithCode($code) !== [];
    }
}
