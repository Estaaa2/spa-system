<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Overview Rev 3, F-19: jobs that other code relies on must actually be scheduled.
 */
class ScheduledJobsTest extends TestCase
{
    public function test_plan_limits_are_enforced_hourly(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'subscriptions:enforce-limits'));

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events->first()->expression);
    }
}
