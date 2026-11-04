<?php

declare(strict_types=1);

namespace App\Services\Payroll\ValueObjects;

/**
 * A pay component's definition from config('payroll.components').
 * kind: earning | deduction | employer_share (matches payslip_lines.kind)
 */
final readonly class ComponentFlags
{
    public const EARNING = 'earning';
    public const DEDUCTION = 'deduction';
    public const EMPLOYER_SHARE = 'employer_share';

    public function __construct(
        public string $code,
        public string $kind,
        public string $label,
        public bool $isTaxable,
        public bool $in13thMonthBasis,
        public bool $countsTowardMinWage,
        public bool $inSssCompensation,
        public bool $inPhilhealthMbs,
        public bool $inPagibigFundSalary,
    ) {
    }
}
