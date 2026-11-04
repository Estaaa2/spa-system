<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Package;
use App\Models\Treatment;
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
    /** @var array<string, string> display names, per run (warnings and line notes only) */
    private array $serviceNames = [];
    /** @var array<int, string> */
    private array $branchNames = [];

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
            $on = 'Booking #'.$booking->id.' on '.$booking->appointment_date->format('M j, Y');
            $profile = $ctx->timeline->on($date);

            if ($profile === null) {
                if ($ctx->contains($date)) {
                    $warnings[] = "{$on}: no commission — there was no pay profile on that date.";
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
                $warnings[] = "{$on}: no commission — the booking total is ₱0 and {$this->serviceName($booking->treatment)} has no list price. [ref: {$this->ref($booking->treatment)}]";
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
                $warnings[] = "{$on}: no commission — no commission rule covers {$this->serviceName($booking->treatment)} at {$this->branchName($booking->branch_id)} on that date. Add one in Payroll Setup. [ref: {$this->ref($booking->treatment)} · branch #{$booking->branch_id}]";
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
        $baseNote = sprintf('base ₱%s (%s)', Decimal::peso($base), $fromTotal ? 'booking total' : 'list price of '.$this->serviceName($booking->treatment));
        $ruleNote = sprintf('rule #%d (%s, %s)', $rule->id, $rule->branch_id === null ? 'all branches' : 'this branch', $rule->target_type === CommissionRule::TARGET_DEFAULT ? 'default' : 'this '.$rule->target_type);

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

    /** "Hot Stone Massage" for treatment_12; a readable fallback when the record is gone. */
    private function serviceName(?string $code): string
    {
        $key = (string) $code;
        if (! isset($this->serviceNames[$key])) {
            $target = $this->prices->parse($code);
            $name = null;
            if ($target !== null) {
                $model = $target['type'] === CommissionRule::TARGET_TREATMENT ? Treatment::class : Package::class;
                $name = $model::withoutGlobalScopes()->whereKey($target['id'])->value('name');
            }
            $this->serviceNames[$key] = $name !== null
                ? (string) $name
                : ($target !== null ? 'a deleted '.$target['type'] : ($key !== '' ? '“'.$key.'”' : 'an unnamed service'));
        }

        return $this->serviceNames[$key];
    }

    private function branchName(mixed $branchId): string
    {
        if ($branchId === null) {
            return 'its branch';
        }
        $id = (int) $branchId;

        return $this->branchNames[$id] ??= (string) (Branch::withoutGlobalScopes()->whereKey($id)->value('name') ?? 'a deleted branch');
    }

    private function ref(?string $code): string
    {
        return $code === null || $code === '' ? 'no service' : $code;
    }
}
