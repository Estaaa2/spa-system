<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Exceptions\PayrollSetupException;
use App\Services\Payroll\HolidayCalendar;
use App\Services\Payroll\StatutoryCalculator;
use OutOfRangeException;
use PHPUnit\Framework\TestCase;

final class HolidayCalendarTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        $this->config = require dirname(__DIR__, 3).'/config/payroll.php';
    }

    public function test_includes_lookback_window(): void
    {
        $w = [];
        $map = (new HolidayCalendar(new StatutoryCalculator($this->config)))->forCutoff('2026-09-01', '2026-09-15', 14, $w);

        $this->assertSame(['2026-08-21', '2026-08-31'], array_keys($map)); // lookback reaches Aug 18
        $this->assertSame([], $w);
    }

    public function test_unconfigured_lookback_year_warns_but_cutoff_year_throws(): void
    {
        $w = [];
        $map = (new HolidayCalendar(new StatutoryCalculator($this->config)))->forCutoff('2026-01-01', '2026-01-15', 14, $w);
        $this->assertSame(['2026-01-01'], array_keys($map));
        $this->assertStringContainsString('No national holiday list for 2025', $w[0]);

        $this->expectException(OutOfRangeException::class);
        (new HolidayCalendar(new StatutoryCalculator($this->config)))->forCutoff('2028-01-01', '2028-01-15', 14, $w);
    }

    public function test_double_holiday_throws_instead_of_dropping_one(): void
    {
        $cfg = $this->config;
        $cfg['holidays'][2026][] = ['date' => '2026-04-02', 'name' => 'Test Holiday', 'type' => 'regular', 'proclamation' => 'test'];

        $this->expectException(PayrollSetupException::class);
        $this->expectExceptionMessage('Double holidays are not supported');
        $w = [];
        (new HolidayCalendar(new StatutoryCalculator($cfg)))->forCutoff('2026-04-01', '2026-04-15', 14, $w);
    }
}
