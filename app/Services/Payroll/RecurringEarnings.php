<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\PayslipLine;
use App\Models\Staff;
use App\Models\StaffRecurringItem;
use App\Services\Payroll\Support\Decimal;
use App\Services\Payroll\ValueObjects\ComponentFlags;
use App\Services\Payroll\ValueObjects\CutoffContext;
use App\Services\Payroll\ValueObjects\EarningLine;
use App\Services\Payroll\ValueObjects\RecurringItemSnapshot;
use App\Services\Payroll\ValueObjects\WorkedDay;

/**
 * Recurring EARNING items active in the cutoff (deductions are a later unit):
 *  - per_cutoff:         the amount once (not prorated when the item starts/ends mid-cutoff)
 *  - per_day_worked:     amount × worked days (present/late) inside the item's active range
 *  - second_cutoff_only: the amount once, cutoff 2 only
 * Branch = home branch (a recurring item is not tied to a work location).
 */
final class RecurringEarnings
{
    public function __construct(private readonly StatutoryCalculator $calc)
    {
    }

    /**
     * @param list<WorkedDay> $workedDays
     * @return array{lines: list<EarningLine>, warnings: list<string>}
     */
    public function forPeriod(Staff $staff, CutoffContext $ctx, array $workedDays): array
    {
        $items = StaffRecurringItem::query()
            ->where('staff_id', $staff->id)
            ->where('kind', StaffRecurringItem::KIND_EARNING)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $ctx->periodEnd)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $ctx->periodStart))
            ->orderBy('id')
            ->get()
            ->map(fn (StaffRecurringItem $i) => RecurringItemSnapshot::fromModel($i))
            ->all();

        return $this->compute($items, $ctx, $workedDays);
    }

    /**
     * Pure computation.
     *
     * @param list<RecurringItemSnapshot> $items
     * @param list<WorkedDay>             $workedDays
     * @return array{lines: list<EarningLine>, warnings: list<string>}
     */
    public function compute(array $items, CutoffContext $ctx, array $workedDays): array
    {
        $lines = [];
        $warnings = [];

        foreach ($items as $item) {
            if (! $item->isActive || $item->kind !== 'earning'
                || $item->startDate > $ctx->periodEnd || ($item->endDate !== null && $item->endDate < $ctx->periodStart)) {
                continue;
            }

            try {
                $c = $this->calc->component($item->componentCode);
            } catch (\InvalidArgumentException) {
                $warnings[] = "Recurring item #{$item->id} ({$item->label}) has unknown component [{$item->componentCode}] — skipped.";
                continue;
            }
            if ($c->kind !== ComponentFlags::EARNING) {
                $warnings[] = "Recurring item #{$item->id} ({$item->label}) is an earning but [{$item->componentCode}] is a {$c->kind} component — skipped.";
                continue;
            }

            $qty = match ($item->frequency) {
                StaffRecurringItem::FREQ_PER_CUTOFF => '1',
                StaffRecurringItem::FREQ_SECOND_CUTOFF_ONLY => $ctx->cutoffNo === 2 ? '1' : '0',
                StaffRecurringItem::FREQ_PER_DAY_WORKED => (string) count(array_filter(
                    $workedDays,
                    static fn (WorkedDay $d): bool => $d->date >= $item->startDate && ($item->endDate === null || $d->date <= $item->endDate),
                )),
                default => null,
            };

            if ($qty === null) {
                $warnings[] = "Recurring item #{$item->id} ({$item->label}) has unknown frequency [{$item->frequency}] — skipped.";
                continue;
            }
            if (! Decimal::isPositive($qty) || ! Decimal::isPositive($item->amount)) {
                continue;
            }

            $note = match ($item->frequency) {
                StaffRecurringItem::FREQ_PER_DAY_WORKED => "₱".Decimal::peso($item->amount)." × {$qty} day(s) worked",
                StaffRecurringItem::FREQ_SECOND_CUTOFF_ONLY => 'Second cutoff only',
                default => 'Per cutoff',
            };

            $lines[] = EarningLine::priced($c, $qty, $item->amount, $ctx->homeBranchId,
                PayslipLine::SOURCE_RECURRING_ITEM, $item->id, $note, null, $item->label);
        }

        return ['lines' => $lines, 'warnings' => $warnings];
    }
}
