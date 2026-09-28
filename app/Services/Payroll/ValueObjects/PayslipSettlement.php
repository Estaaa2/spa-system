<?php

declare(strict_types=1);

namespace App\Services\Payroll\ValueObjects;

use App\Models\Payslip;

/** Result of a manual-line change: the recomputed payslip and its warnings. */
final readonly class PayslipSettlement
{
    /** @param list<RunWarning> $warnings */
    public function __construct(
        public ?Payslip $payslip,   // null when removing the last manual line deleted an orphaned payslip
        public array $warnings,
    ) {
    }
}
