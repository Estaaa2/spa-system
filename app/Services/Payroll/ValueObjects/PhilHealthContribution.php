<?php

declare(strict_types=1);

namespace App\Services\Payroll\ValueObjects;

/**
 * Monthly PhilHealth premium (rates sheet §2). ee + er always equals total.
 */
final readonly class PhilHealthContribution
{
    public function __construct(
        public string $total,
        public string $ee,
        public string $er,
    ) {
    }
}
