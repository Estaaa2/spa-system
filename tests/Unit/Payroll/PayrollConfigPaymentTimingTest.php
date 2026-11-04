<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use PHPUnit\Framework\TestCase;

/**
 * config 2026.4 `payment_timing` block — rates sheet §12 (Rev. 4). Read by
 * PayrollRunService (not StatutoryCalculator), so it is checked straight from the file.
 */
final class PayrollConfigPaymentTimingTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $timing;

    protected function setUp(): void
    {
        $config = require dirname(__DIR__, 3).'/config/payroll.php';
        $this->timing = $config['payment_timing'];
    }

    public function test_max_pay_interval_is_16_days(): void
    {
        // §12: Labor Code Art. 103, "intervals not exceeding sixteen (16) days" — VERIFY
        $this->assertSame(16, $this->timing['max_pay_interval_days']);
    }

    public function test_thirteenth_month_deadline_is_december_24(): void
    {
        // §12: PD 851; DOLE LA 16-25 §IV "on or before 24 December" — OFFICIAL
        $this->assertSame('12-24', $this->timing['thirteenth_month_deadline']);
    }
}
