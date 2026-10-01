<?php

declare(strict_types=1);

namespace App\Services\Payroll\Display;

use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipLine;
use Illuminate\Support\Collection;

/**
 * Builds the employee copy of a payslip for resources/views/hr/payroll/partials/payslip
 * (Unit 6). Every value is a display-ready string, so the partial does no arithmetic.
 *
 * Employee copy: earnings and deductions only. Employer shares (SSS ER/EC, PhilHealth
 * ER, Pag-IBIG ER) are not part of the employee's pay and are left out, per the Unit 6
 * spec; HR sees them on the run register.
 */
final class PayslipDocument
{
    /**
     * @param array{spa: string, employee: string, position: ?string, branch: string, location: ?string} $parties
     * @param array<int, array{appointment_date: string, service: ?string}> $bookings commission bookings by id
     *        (PayrollRunReadModel::bookings) — lets a therapist see which service each commission is for
     * @param Collection<int, PayslipLine>|null $lines defaults to $payslip->lines
     * @return array<string, mixed>
     */
    public static function build(Payslip $payslip, PayrollRun $run, array $parties, array $bookings = [], ?Collection $lines = null): array
    {
        $rank = PayrollDisplay::componentRank();
        $lines = ($lines ?? $payslip->lines)
            ->sortBy(fn (PayslipLine $l) => sprintf('%03d-%012d', $rank[$l->component_code] ?? 999, $l->id))
            ->values();

        $row = function (PayslipLine $l) use ($bookings) {
            $detail = PayrollDisplay::lineDetail($l);
            if ($l->source_type === PayslipLine::SOURCE_BOOKING && isset($bookings[(int) $l->source_id])) {
                $b = $bookings[(int) $l->source_id];
                $detail = implode(' · ', array_filter([
                    PayrollDisplay::date($b['appointment_date'], 'M j'),
                    $b['service'] ?? null,
                    $detail !== '' ? $detail : null,
                ]));
            }

            return [
                'label'  => $l->label,
                'detail' => $detail,
                'amount' => PayrollDisplay::peso($l->amount),
                'short'  => PayrollDisplay::isShort($l),
                'manual' => (bool) $l->is_manual,
                'note'   => $l->is_manual ? $l->note : null,   // manual notes are written for the employee; computed notes are internal
            ];
        };

        $isThirteenth = $run->run_type === PayrollRun::TYPE_THIRTEENTH_MONTH;

        return [
            'id'           => (int) $payslip->id,
            'spa'          => $parties['spa'],
            'branch'       => $parties['branch'],
            'location'     => $parties['location'] ?? null,
            'employee'     => $parties['employee'],
            'position'     => $parties['position'] ?? null,
            'staff_id'     => (int) $payslip->staff_id,
            'run_type'     => PayrollDisplay::TYPE[$run->run_type] ?? $run->run_type,
            'is_thirteenth' => $isThirteenth,
            'period'       => $isThirteenth
                ? 'January 1 – December 31, '.$run->period_start->format('Y')
                : $run->period_start->format('M j').' – '.$run->period_end->format('M j, Y'),
            'cutoff'       => $isThirteenth ? null : PayrollDisplay::cutoffLabel($run),
            'pay_date'     => $run->pay_date->format('F j, Y'),
            'days_worked'  => $isThirteenth ? null : PayrollDisplay::qty($payslip->days_worked),
            'status'       => $run->status,
            'is_final'     => in_array($run->status, [PayrollRun::STATUS_FINALIZED, PayrollRun::STATUS_RELEASED], true),
            'status_label' => PayrollDisplay::STATUS[$run->status]['label'] ?? $run->status,
            'is_mwe'       => (bool) $payslip->is_mwe,
            'earnings'     => $lines->where('kind', PayslipLine::KIND_EARNING)->map($row)->values()->all(),
            'deductions'   => $lines->where('kind', PayslipLine::KIND_DEDUCTION)->map($row)->values()->all(),
            'gross'        => PayrollDisplay::peso($payslip->gross_pay),
            'deductions_total' => PayrollDisplay::peso($payslip->total_deductions),
            'net'          => PayrollDisplay::peso($payslip->net_pay),
            'has_short'    => $lines->contains(fn (PayslipLine $l) => PayrollDisplay::isShort($l)),
        ];
    }
}
