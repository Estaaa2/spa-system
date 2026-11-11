<?php

namespace App\Console\Commands;

use App\Models\Staff;
use App\Models\StaffPayProfile;
use App\Models\StaffWorkSchedule;
use App\Services\Staff\WorkScheduleWriter;
use Illuminate\Console\Command;

/**
 * Staff scheduling Phase 2 (D8). Gives every active staff member who has no work
 * schedule yet a weekly pattern starting today:
 *  - rest days = the rest days of their pay profile in effect today;
 *  - every other day = "Branch hours" (follows the branch, Phase 2 Q2 A).
 *
 * Staff with no pay profile (or a profile with no rest days) are skipped and
 * listed: a pattern with no rest days and all branch hours is exactly the
 * default, so writing one adds nothing. Set their rest days on the Staff page.
 *
 * Safe to re-run: staff who already have any schedule are never touched.
 */
class BackfillWorkSchedules extends Command
{
    protected $signature = 'staff:backfill-work-schedules
        {--spa= : Limit to one spa id}
        {--dry-run : Report only; change nothing}';

    protected $description = 'Creates a weekly work schedule from pay-profile rest days for active staff who have none (dry run first).';

    public function handle(WorkScheduleWriter $writer): int
    {
        $dry = (bool) $this->option('dry-run');
        $today = today()->toDateString();

        $staff = Staff::query()
            ->with(['user', 'branch'])
            ->where('employment_status', 'active')
            ->whereDoesntHave('workSchedules')
            ->when($this->option('spa'), fn ($q, $spa) => $q->where('spa_id', (int) $spa))
            ->orderBy('spa_id')->orderBy('id')
            ->get();

        $create = [];
        $skipped = [];
        foreach ($staff as $s) {
            $rest = array_values(array_intersect(
                StaffWorkSchedule::WEEKDAYS,
                StaffPayProfile::effectiveFor((int) $s->id, $today)?->rest_days ?? []
            ));
            $row = ['#'.$s->id.' '.($s->user?->name ?? ''), '#'.$s->spa_id, $s->branch?->name ?? '—'];
            if ($rest === []) {
                $skipped[] = [...$row, 'no pay-profile rest days'];
            } else {
                $create[] = ['staff' => $s, 'rest' => $rest, 'row' => [...$row, implode(', ', $rest)]];
            }
        }

        $this->line(($dry ? '[DRY RUN] ' : '')."Active staff without a work schedule: {$staff->count()}.");

        if ($create !== []) {
            $this->table(['Staff', 'Spa', 'Branch', 'Rest days (from pay profile)'], array_column($create, 'row'));
        }
        if ($skipped !== []) {
            $this->warn(count($skipped).' staff skipped — they keep the default (branch hours every open day, no rest days) until a schedule is set on the Staff page:');
            $this->table(['Staff', 'Spa', 'Branch', 'Reason'], $skipped);
        }

        if ($create === []) {
            $this->info('Nothing to create.');

            return self::SUCCESS;
        }

        if ($dry) {
            $this->info('[DRY RUN] '.count($create)." schedule(s) would be created, effective {$today}. Nothing changed.");

            return self::SUCCESS;
        }

        foreach ($create as $c) {
            $days = [];
            foreach (StaffWorkSchedule::WEEKDAYS as $d) {
                $days[$d] = ['mode' => in_array($d, $c['rest'], true) ? WorkScheduleWriter::MODE_REST : WorkScheduleWriter::MODE_BRANCH];
            }
            $writer->savePattern($c['staff'], $today, $days, null);
        }

        $this->info('Created '.count($create)." work schedule(s), effective {$today}.");

        return self::SUCCESS;
    }
}
