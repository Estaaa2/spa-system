<?php

declare(strict_types=1);

namespace App\Services\Payroll\ValueObjects;

use App\Models\StaffPayProfile;
use App\Services\Payroll\Support\Decimal;

/** Plain copy of a staff_pay_profiles row, so the math runs without Eloquent. */
final readonly class PayProfileSnapshot
{
    public const DAILY = 'daily';
    public const MONTHLY = 'monthly';

    /** @param list<string> $restDays day names, same format as Carbon 'l' */
    public function __construct(
        public int $id,
        public string $effectiveFrom,
        public ?string $effectiveTo,
        public string $payBasis,
        public string $baseRate,
        public bool $commissionEnabled,
        public array $restDays,
    ) {
    }

    public static function fromModel(StaffPayProfile $p): self
    {
        return new self(
            id: (int) $p->id,
            effectiveFrom: $p->effective_from->toDateString(),
            effectiveTo: $p->effective_to?->toDateString(),
            payBasis: (string) $p->pay_basis,
            baseRate: Decimal::fromDb($p->base_rate, 'base_rate'),
            commissionEnabled: (bool) $p->commission_enabled,
            restDays: array_values($p->rest_days ?? []),
        );
    }

    public function covers(string $ymd): bool
    {
        return $this->effectiveFrom <= $ymd && ($this->effectiveTo === null || $this->effectiveTo >= $ymd);
    }

    public function isDaily(): bool
    {
        return $this->payBasis === self::DAILY;
    }

    public function isRestDay(string $ymd): bool
    {
        return in_array((new \DateTimeImmutable($ymd))->format('l'), $this->restDays, true);
    }
}
