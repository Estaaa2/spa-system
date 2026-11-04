<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A spa/branch setting that payroll needs is missing or invalid (e.g. a branch
 * with no min_daily_wage). The message names the setting and the record so the
 * run page can show it as-is. Distinct from PayrollStateException (run status).
 */
class PayrollSetupException extends RuntimeException
{
}
