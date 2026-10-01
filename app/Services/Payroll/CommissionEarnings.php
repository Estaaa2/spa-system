<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Booking;
use App\Models\CommissionRule;
use App\Models\PayslipLine;
use App\Models\Staff;
use App\Services\Bookings\ServicePriceResolver;
use App\Services\Payroll\Support\Decimal;
use App\Services\Payroll\ValueObjects\ComponentFlags;
use App\Services\Payroll\ValueObjects\CutoffContext;
use App\Services\Payroll\ValueObjects\EarningLine;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * COMMISSION lines (locked computation rule):
 *  - bookings.status = 'completed' AND payment_status = 'paid' — 'completed' alone
 *    is set by BookingController::syncAutomaticStatuses() from the end time and does
 *    not prove the service happened;
 *  - appointment_date <= period_end (no lower bound: late-settled bookings are picked
 *    up by the next run);
 *  - no COMMISSION payslip line for the booking in any OTHER run (lines in the run
 *    being regenerated are ignored, since regeneration deletes them);
 *  - therapist_id is a users.id → matched via staff.user_id (+ spa_id);
 *  - the staff member's pay profile on the APPOINTMENT date must have
 *    commission_enabled. Bookings before the first profile therefore never pay
 *    out, which keeps pre-payroll history from flooding the first run;
 *  - base = total_amount, or the treatment/package list price when it is 0;
 *  - rule resolved on the appointment date for the booking's target and branch,
 *    in the locked order (CommissionRule::resolveFor);
 *  - line branch_id = booking.branch_id; line date = appointment date.
 */
final class CommissionEarnings
{
    public function __construct(
        private readonly StatutoryCalculator $calc,
        private readonly ServicePriceResolver $prices,
    ) {
    }

    /** @return array{lines: list<EarningLine>, warnings: list<string>} */
    public function forPeriod(Staff $staff, CutoffContext $ctx): array
    {
        if ($staff->user_id === null) {
            return ['lines' => [], 'warnings' => []];
        }

        $bookings = Booking::query()
            ->where('spa_id', $staff->spa_id)
            ->where('therapist_id', $staff->user_id)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->whereDate('appointment_date', '<=', $ctx->periodEnd)
            ->whereNotExists(function (QueryBuilder $q) use ($ctx) {
                $q->selectRaw('1')
                    ->from('payslip_lines')
                    ->join('payslips', 'payslips.id', '=', 'payslip_lines.payslip_id')
                    ->where('payslip_lines.component_code', 'COMMISSION')
                    ->where('payslip_lines.source_type', PayslipLine::SOURCE_BOOKING)
                    ->whereColumn('payslip_lines.source_id', 'bookings.id');
                if ($ctx->payrollRunId !== null) {
                    $q->where('payslips.payroll_run_id', '!=', $ctx->payrollRunId);
                }
            })
            ->orderBy('appointment_date')
            ->orderBy('id')
            ->get();

        $lines = [];
        $warnings = [];
        $c = $this->calc->component('COMMISSION');

        foreach ($bookings as $booking) {
            $date = $booking->appointment_date->toDateString();
            $profile = $ctx->timeline->on($date);

            if ($profile === null) {
                if ($ctx->contains($date)) {
                    $warnings[] = "Booking #{$booking->id} ({$date}): no pay profile on the appointment date — no commission.";
                }
                continue;
            }
            if (! $profile->commissionEnabled) {
                continue;
            }

            $fromTotal = Decimal::isPositive(ServicePriceResolver::normalize($booking->total_amount));
            $base = $fromTotal
                ? ServicePriceResolver::normalize($booking->total_amount)
                : $this->prices->listPrice($booking->treatment);

            if (! Decimal::isPositive($base)) {
                $warnings[] = "Booking #{$booking->id} ({$date}): total_amount is 0 and no list price was found for [{$booking->treatment}] — no commission.";
                continue;
            }

            $target = $this->prices->parse($booking->treatment);
            $rule = CommissionRule::resolveFor(
                (int) $booking->spa_id,
                $booking->branch_id !== null ? (int) $booking->branch_id : null,
                $target['type'] ?? CommissionRule::TARGET_DEFAULT,
                $target['id'] ?? null,
                $date,
            );

            if ($rule === null) {
                $warnings[] = "Booking #{$booking->id} ({$date}): no commission rule in effect for [{$booking->treatment}] at branch #{$booking->branch_id} — no commission.";
                continue;
            }

            $line = $this->line($c, $booking, $rule, $base, $fromTotal, $date);
            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return ['lines' => $lines, 'warnings' => $warnings];
    }

    private function line(ComponentFlags $c, Booking $booking, CommissionRule $rule, string $base, bool $fromTotal, string $date): ?EarningLine
    {
        $value = Decimal::fromDb($rule->value, 'commission_rules.value');
        $baseNote = sprintf('base ₱%s (%s)', Decimal::peso($base), $fromTotal ? 'total_amount' : "list price of {$booking->treatment}");
        $ruleNote = sprintf('rule #%d (%s%s)', $rule->id, $rule->branch_id === null ? 'spa' : 'branch', $rule->target_type === CommissionRule::TARGET_DEFAULT ? ' default' : ' + '.$rule->target_type);

        if ($rule->method === CommissionRule::METHOD_PERCENT) {
            $amount = Decimal::div(Decimal::mul($base, $value), '100');
            $note = sprintf('Booking #%d · appt %s · %s%% of %s · %s', $booking->id, $date, rtrim(rtrim($value, '0'), '.'), $baseNote, $ruleNote);
            $line = EarningLine::withAmount($c, $base, Decimal::div($value, '100'), $amount, (int) $booking->branch_id,
                PayslipLine::SOURCE_BOOKING, (int) $booking->id, $note, $date, 'Booking #'.$booking->id);
        } else {
            $note = sprintf('Booking #%d · appt %s · flat ₱%s · %s · %s', $booking->id, $date, Decimal::peso($value), $baseNote, $ruleNote);
            $line = EarningLine::priced($c, '1', $value, (int) $booking->branch_id,
                PayslipLine::SOURCE_BOOKING, (int) $booking->id, $note, $date, 'Booking #'.$booking->id);
        }

        return Decimal::isPositive($line->amount) ? $line : null;
    }
}
