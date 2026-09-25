<?php

declare(strict_types=1);

namespace App\Services\Payroll\ValueObjects;

/**
 * Monthly SSS contribution for one MSC (rates sheet §1).
 * All amounts are 2-decimal strings.
 */
final readonly class SssContribution
{
    public function __construct(
        public string $msc,
        public string $eeSs,
        public string $eeMpf,
        public string $erSs,
        public string $erMpf,
        public string $ec,
    ) {
    }

    /** Employee total (SS + MPF) → SSS_EE line. */
    public function eeTotal(): string
    {
        return bcadd($this->eeSs, $this->eeMpf, 2);
    }

    /** Employer total excluding EC (SS + MPF) → SSS_ER line. EC is SSS_EC. */
    public function erTotal(): string
    {
        return bcadd($this->erSs, $this->erMpf, 2);
    }
}
