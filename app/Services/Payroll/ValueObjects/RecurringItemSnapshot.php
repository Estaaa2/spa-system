<?php

declare(strict_types=1);

namespace App\Services\Payroll\ValueObjects;

use App\Models\StaffRecurringItem;
use App\Services\Payroll\Support\Decimal;

/** Plain copy of a staff_recurring_items row. */
final readonly class RecurringItemSnapshot
{
    public function __construct(
        public int $id,
        public string $kind,
        public string $componentCode,
        public string $label,
        public string $amount,
        public string $frequency,
        public string $startDate,
        public ?string $endDate,
        public bool $isActive,
    ) {
    }

    public static function fromModel(StaffRecurringItem $i): self
    {
        return new self(
            id: (int) $i->id,
            kind: (string) $i->kind,
            componentCode: (string) $i->component_code,
            label: (string) $i->label,
            amount: Decimal::fromDb($i->amount, 'amount'),
            frequency: (string) $i->frequency,
            startDate: $i->start_date->toDateString(),
            endDate: $i->end_date?->toDateString(),
            isActive: (bool) $i->is_active,
        );
    }
}
