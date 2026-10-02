<?php

declare(strict_types=1);

namespace App\Services\Payroll\ValueObjects;

/**
 * One national holiday (rates sheet §7).
 * type: regular | special_non_working | special_working
 */
final readonly class Holiday
{
    public const REGULAR = 'regular';
    public const SPECIAL_NON_WORKING = 'special_non_working';
    public const SPECIAL_WORKING = 'special_working';

    public function __construct(
        public string $date,   // Y-m-d
        public string $name,
        public string $type,
    ) {
    }

    /** @return array{date: string, name: string, type: string} for payslip snapshots */
    public function toArray(): array
    {
        return ['date' => $this->date, 'name' => $this->name, 'type' => $this->type];
    }
}
