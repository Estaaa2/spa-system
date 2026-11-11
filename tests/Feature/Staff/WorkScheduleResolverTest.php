<?php

namespace Tests\Feature\Staff;

use App\Models\StaffScheduleOverride;
use App\Models\StaffWorkSchedule;
use App\Services\Staff\ScheduledDay;
use App\Services\Staff\WorkScheduleResolver;
use App\Services\Staff\WorkScheduleWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Staff\Concerns\ScheduleFixtures;
use Tests\TestCase;

/**
 * Staff scheduling Phase 2 — WorkScheduleResolver precedence (Q3 A).
 * Calendar (2026): Nov 15 Sun · Nov 16 Mon · Nov 17 Tue · Nov 18 Wed · Nov 22 Sun.
 */
class WorkScheduleResolverTest extends TestCase
{
    use RefreshDatabase;
    use ScheduleFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeWorld();
        $this->hours($this->branchA, closed: ['Monday']);
        $this->travelTo('2026-11-12 10:00:00');
    }

    private function day(string $date, ?int $branchId = null): ScheduledDay
    {
        return app(WorkScheduleResolver::class)->forDate($this->staff->fresh(), $date, $branchId);
    }

    private function writer(): WorkScheduleWriter
    {
        return app(WorkScheduleWriter::class);
    }

    public function test_no_schedule_works_branch_hours_on_open_days(): void
    {
        $d = $this->day('2026-11-17');
        $this->assertSame(ScheduledDay::WORKING, $d->status);
        $this->assertSame(['09:00', '18:00'], [$d->start, $d->end]);
        $this->assertSame(ScheduledDay::SOURCE_BRANCH_HOURS, $d->source);
        $this->assertFalse($d->hasSchedule);

        $this->assertSame(ScheduledDay::WORKING, $this->day('2026-11-15')->status); // no rest days by default
    }

    public function test_closed_day_and_missing_hours_row_are_closed(): void
    {
        $this->assertSame(ScheduledDay::CLOSED, $this->day('2026-11-16')->status);
        $this->assertSame(ScheduledDay::CLOSED, $this->day('2026-11-17', $this->branchB->id)->status); // B has no hours rows
    }

    public function test_pattern_rest_day_custom_times_and_branch_hours(): void
    {
        $this->writer()->savePattern($this->staff, '2026-11-12', $this->pattern(['Sunday'], ['Wednesday' => ['13:00', '21:00']]), $this->admin->id);

        $this->assertSame(ScheduledDay::REST_DAY, $this->day('2026-11-15')->status);
        $wed = $this->day('2026-11-18');
        $this->assertSame([ScheduledDay::WORKING, '13:00', '21:00', ScheduledDay::SOURCE_SCHEDULE], [$wed->status, $wed->start, $wed->end, $wed->source]);
        $tue = $this->day('2026-11-17');
        $this->assertSame(['09:00', '18:00', ScheduledDay::SOURCE_BRANCH_HOURS], [$tue->start, $tue->end, $tue->source]);
        $this->assertTrue($tue->hasSchedule);
    }

    public function test_branch_hours_days_follow_a_change_to_branch_hours(): void
    {
        $this->writer()->savePattern($this->staff, '2026-11-12', $this->pattern(), $this->admin->id);
        $this->hours($this->branchA, '10:00:00', '20:00:00', ['Monday']);

        $this->assertSame(['10:00', '20:00'], [$this->day('2026-11-17')->start, $this->day('2026-11-17')->end]);
    }

    public function test_effective_dates_end_the_previous_version(): void
    {
        $this->writer()->savePattern($this->staff, '2026-11-12', $this->pattern(['Sunday']), $this->admin->id);
        $this->writer()->savePattern($this->staff, '2026-11-20', $this->pattern(['Tuesday']), $this->admin->id);

        $this->assertSame(ScheduledDay::REST_DAY, $this->day('2026-11-15')->status);   // old: Sunday
        $this->assertSame(ScheduledDay::WORKING, $this->day('2026-11-17')->status);    // old: Tuesday works
        $this->assertSame(ScheduledDay::WORKING, $this->day('2026-11-22')->status);    // new: Sunday works
        $this->assertSame(ScheduledDay::REST_DAY, $this->day('2026-11-24')->status);   // new: Tuesday rest

        $first = StaffWorkSchedule::orderBy('effective_from')->first();
        $this->assertSame('2026-11-19', $first->effective_to->toDateString());
    }

    public function test_saving_again_from_an_earlier_date_replaces_later_versions(): void
    {
        $this->writer()->savePattern($this->staff, '2026-11-20', $this->pattern(['Tuesday']), $this->admin->id);
        $this->writer()->savePattern($this->staff, '2026-11-13', $this->pattern(['Wednesday']), $this->admin->id);

        $this->assertSame(1, StaffWorkSchedule::count());
        $this->assertSame(ScheduledDay::REST_DAY, $this->day('2026-11-25')->status);   // Wednesday
        $this->assertSame(ScheduledDay::WORKING, $this->day('2026-11-24')->status);
    }

    public function test_one_off_day_off_and_extra_shift(): void
    {
        $this->writer()->savePattern($this->staff, '2026-11-12', $this->pattern(['Sunday']), $this->admin->id);
        $this->writer()->saveOverride($this->staff, '2026-11-17', StaffScheduleOverride::DAY_OFF, null, null, 'Errand', $this->admin->id);
        $this->writer()->saveOverride($this->staff, '2026-11-15', StaffScheduleOverride::SHIFT, '10:00', '16:00', 'Event', $this->admin->id);

        $off = $this->day('2026-11-17');
        $this->assertSame([ScheduledDay::DAY_OFF, 'Errand'], [$off->status, $off->note]);
        $sun = $this->day('2026-11-15');
        $this->assertSame([ScheduledDay::WORKING, '10:00', '16:00', ScheduledDay::SOURCE_OVERRIDE], [$sun->status, $sun->start, $sun->end, $sun->source]);
    }

    public function test_override_replaces_the_one_on_the_same_date(): void
    {
        $this->writer()->saveOverride($this->staff, '2026-11-17', StaffScheduleOverride::DAY_OFF, null, null, null, $this->admin->id);
        $this->writer()->saveOverride($this->staff, '2026-11-17', StaffScheduleOverride::SHIFT, '12:00', '18:00', null, $this->admin->id);

        $this->assertSame(1, StaffScheduleOverride::count());
        $this->assertSame('12:00', $this->day('2026-11-17')->start);
    }

    public function test_leave_on_workday_is_on_leave_but_rest_day_stays_rest_day(): void
    {
        $this->writer()->savePattern($this->staff, '2026-11-12', $this->pattern(['Sunday']), $this->admin->id);
        $this->approvedLeave('2026-11-14', '2026-11-17');

        $this->assertSame(ScheduledDay::ON_LEAVE, $this->day('2026-11-14')->status);
        $this->assertSame(ScheduledDay::REST_DAY, $this->day('2026-11-15')->status);
        $this->assertSame(ScheduledDay::CLOSED, $this->day('2026-11-16')->status);
        $this->assertSame(ScheduledDay::ON_LEAVE, $this->day('2026-11-17')->status);
    }

    public function test_precedence_closed_then_day_off_then_leave_then_shift(): void
    {
        $this->approvedLeave('2026-11-16', '2026-11-18');
        $w = $this->writer();
        $w->saveOverride($this->staff, '2026-11-16', StaffScheduleOverride::SHIFT, '10:00', '12:00', null, null); // closed Monday
        $w->saveOverride($this->staff, '2026-11-17', StaffScheduleOverride::DAY_OFF, null, null, null, null);
        $w->saveOverride($this->staff, '2026-11-18', StaffScheduleOverride::SHIFT, '10:00', '12:00', null, null);

        $this->assertSame(ScheduledDay::CLOSED, $this->day('2026-11-16')->status);
        $this->assertSame(ScheduledDay::DAY_OFF, $this->day('2026-11-17')->status);  // day off: leave not used
        $this->assertSame(ScheduledDay::ON_LEAVE, $this->day('2026-11-18')->status); // leave beats a shift
    }

    public function test_pattern_follows_the_person_to_another_branch(): void
    {
        $this->hours($this->branchB, '11:00:00', '19:00:00', ['Tuesday']);
        $this->writer()->savePattern($this->staff, '2026-11-12', $this->pattern(['Sunday']), $this->admin->id);

        $this->assertSame(ScheduledDay::REST_DAY, $this->day('2026-11-15', $this->branchB->id)->status);
        $this->assertSame(ScheduledDay::CLOSED, $this->day('2026-11-17', $this->branchB->id)->status);
        $this->assertSame('11:00', $this->day('2026-11-18', $this->branchB->id)->start);
        $this->assertSame('11:00', $this->day('2026-11-16', $this->branchB->id)->start); // Monday open at B
    }

    public function test_range_returns_one_day_per_date(): void
    {
        $days = app(WorkScheduleResolver::class)->forRange($this->staff, '2026-11-12', '2026-11-18');
        $this->assertCount(7, $days);
        $this->assertSame('2026-11-18', $days[6]->date);
    }
}
