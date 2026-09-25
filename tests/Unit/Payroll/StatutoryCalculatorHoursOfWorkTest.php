<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Services\Payroll\StatutoryCalculator;
use OutOfRangeException;
use PHPUnit\Framework\TestCase;

/**
 * config 2026.3 `hours_of_work` block — rates sheet §6 (Rev. 3).
 * Separate file so StatutoryCalculatorTest.php (Unit 2) stays untouched.
 */
final class StatutoryCalculatorHoursOfWorkTest extends TestCase
{
    private StatutoryCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new StatutoryCalculator(require dirname(__DIR__, 3).'/config/payroll.php');
    }

    public function test_normal_daily_hours_is_8(): void
    {
        // §6: hourly rate = daily ÷ 8; Art. 87 OT beyond 8 hours — OFFICIAL
        $this->assertSame('8.00', $this->calc->normalDailyHours('2026-09-20'));
    }

    public function test_unpaid_meal_minutes_is_60(): void
    {
        // §6 Rev. 3: Labor Code Art. 85, not less than 60 minutes — VERIFY
        $this->assertSame(60, $this->calc->unpaidMealMinutes('2026-09-20'));
    }

    public function test_config_version_is_2026_3(): void
    {
        $this->assertSame('2026.3', $this->calc->version());
    }

    public function test_hours_of_work_before_adoption_date_throws(): void
    {
        $this->expectException(OutOfRangeException::class);
        $this->calc->unpaidMealMinutes('2025-12-31');
    }
}
