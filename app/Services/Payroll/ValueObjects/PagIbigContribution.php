<?php

declare(strict_types=1);

namespace App\Services\Payroll\ValueObjects;

/**
 * Monthly Pag-IBIG / HDMF contribution (rates sheet §3).
 */
final readonly class PagIbigContribution
{
    public function __construct(
        public string $ee,
        public string $er,
    ) {
    }
}
