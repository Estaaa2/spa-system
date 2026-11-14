<?php

namespace App\Services\Payroll\Support;

/**
 * Input limits for the payroll forms (validation hardening, Nov 2026).
 *
 * Money caps are realistic business limits, not column limits (same rule as the modal
 * validation workstream). They also keep every computed value inside the payslip
 * columns: payslip_lines.rate is DECIMAL(12,4) (max ₱99,999,999.9999) and payslip
 * totals DECIMAL(12,2). Before these caps a 10-digit base rate or adjustment passed
 * validation and then failed the INSERT under MySQL strict mode (HTTP 500).
 *
 * Statutory ID digit counts follow each agency's own format (sources in
 * StaffPayProfileController::statutoryIdWarnings()); they are now enforced.
 */
final class InputLimits
{
    public const BASE_RATE_DAILY_MAX   = 50000;     // ₱ per day
    public const BASE_RATE_MONTHLY_MAX = 1000000;   // ₱ per month
    public const RECURRING_MAX         = 100000;    // ₱ per cutoff / per day worked
    public const ADJUSTMENT_MAX        = 1000000;   // ₱ per manual payslip line
    public const MIN_WAGE_MAX          = 5000;      // ₱ per day (highest PH regional minimum is under ₱1,000)
    public const COMMISSION_FLAT_MAX   = 50000;     // ₱ per booking

    /** Payroll dates outside this window are typing mistakes (e.g. year 0202 or 20266). */
    public const DATE_MIN = '2000-01-01';
    public const DATE_MAX = '2099-12-31';

    /** Allowed digit counts. TIN: 9, or 9 + 3- or 5-digit branch code. */
    public const ID_DIGITS = [
        'tin'           => [9, 12, 14],
        'sss_no'        => [10],
        'philhealth_no' => [12],
        'pagibig_no'    => [12],
    ];

    /**
     * Year 2000–2099 on a Y-m-d date. A regex (not after_or_equal) so it never
     * shares a message key with the existing `after_or_equal:effective_from` rules.
     *
     * @return list<string>
     */
    public static function dateBounds(): array
    {
        return ['regex:/^20\d{2}-/'];
    }

    /** @return array<string, string> messages for dateBounds() on the given fields */
    public static function dateMessages(array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            $out[$f.'.regex'] = 'Enter a date between the years 2000 and 2099.';
        }

        return $out;
    }

    public static function peso(int|float $n): string
    {
        return '₱'.number_format($n);
    }
}
