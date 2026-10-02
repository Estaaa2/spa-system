<?php

declare(strict_types=1);

namespace App\Services\Payroll\ValueObjects;

/**
 * Something a reviewer must see before approving a run. Never a silent drop.
 * `code` lets the run page (Unit 6) group or badge warnings; `message` is
 * display-ready. staffId is null for run-level warnings.
 */
final readonly class RunWarning
{
    // Staff not paid by this run
    public const MISSING_PROFILE          = 'missing_profile';
    public const NOT_IN_SUITE_BRANCH      = 'not_in_suite_branch';
    public const HOME_BRANCH_MISSING      = 'home_branch_missing';
    public const INACTIVE_WITH_ATTENDANCE = 'inactive_with_attendance';
    public const MISSING_MIN_WAGE         = 'missing_min_wage';
    public const MISSING_EEMR_FACTOR      = 'missing_eemr_factor';   // v3.1 replacement for "missing divisor"
    public const SETUP_ERROR              = 'setup_error';
    public const NOT_ELIGIBLE_13TH        = 'not_eligible_13th';

    // Paid, but check
    public const EARNINGS                 = 'earnings';              // passed through from Unit 3
    public const UNVERIFIED_RULE          = 'unverified_rule';
    public const UNAPPLIED_DEDUCTION      = 'unapplied_deduction';
    public const DEDUCTION_UNAUTHORIZED   = 'deduction_without_authorization';
    public const DEDUCTION_INVALID        = 'deduction_invalid';
    public const MISSING_STATUTORY_ID     = 'missing_statutory_id';
    public const ORPHANED_MANUAL_LINES    = 'orphaned_manual_lines';
    public const CONTRIBUTIONS_SKIPPED    = 'contributions_skipped';
    public const CUMULATIVE_AVERAGE       = 'cumulative_average_condition';  // RR 11-2018 2.79(B)(5)(a) — decision E

    // Run-level
    public const PERIOD_ADJUSTED          = 'period_adjusted';
    public const PERIOD_NOT_ENDED         = 'period_not_ended';
    public const PAY_INTERVAL             = 'pay_interval';
    public const PAY_DATE_LATE            = 'pay_date_late';
    public const UNFINALIZED_RUNS         = 'unfinalized_runs';

    public function __construct(
        public string $code,
        public string $message,
        public ?int $staffId = null,
    ) {
    }

    /** @return array{code: string, message: string, staff_id: ?int} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'message' => $this->message, 'staff_id' => $this->staffId];
    }
}
