<?php

namespace App\Console\Commands;

use App\Models\PayrollRun;
use App\Models\PayslipLine;
use App\Models\StaffAttendance;
use App\Services\Attendance\NonWorkingDays;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Staff scheduling Phase 1 data fix. Before Phase 1, attendance:finalize-day wrote
 * an `absent` / `on_leave` row (source = system) on days that were not working days
 * for the staff member: days their branch was closed, and pay-profile rest days.
 * This command finds those rows and removes them.
 *
 * Rows already used by a payroll run are handled by run status:
 *  - draft:              removed; the draft is listed so HR regenerates it.
 *  - approved:           kept; send the run back to draft, then re-run this command.
 *  - finalized/released: kept; a suggested ADJ_EARNING refund per payslip is listed
 *                        for HR to add as a manual line in the next run (decision Q3).
 *
 * Closed days use each branch's CURRENT operating hours (they have no history), so
 * --from is required: start from a date after the last change to any branch's
 * closed days (decision Q6). Removed rows are written to a CSV first (decision Q5).
 * Safe to re-run: a second run finds nothing new.
 */
class CleanFalseAbsences extends Command
{
    protected $signature = 'attendance:clean-false-absences
        {--from= : First date to check (Y-m-d). Required}
        {--to= : Last date to check (Y-m-d). Default: today}
        {--spa= : Limit to one spa id}
        {--dry-run : Report only; change nothing}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Removes system-written absent/on_leave rows on branch-closed days and pay-profile rest days (dry run first).';

    private const LOCKED_FOR_REFUND = [PayrollRun::STATUS_FINALIZED, PayrollRun::STATUS_RELEASED];

    public function handle(NonWorkingDays $nonWorking): int
    {
        $from = $this->dateOption('from');
        $to = $this->option('to') !== null ? $this->dateOption('to') : today()->toDateString();
        if ($from === null || $to === null) {
            $this->error('--from is required and dates must be Y-m-d (e.g. --from=2026-08-01).');

            return self::INVALID;
        }
        if ($from > $to) {
            $this->error("--from ({$from}) is after --to ({$to}).");

            return self::INVALID;
        }

        $dry = (bool) $this->option('dry-run');
        $this->line(($dry ? '[DRY RUN] ' : '')."Checking system absent/on_leave rows from {$from} to {$to}"
            .($this->option('spa') ? ' for spa #'.$this->option('spa') : '').'.');

        // 1) Candidates: system-written absences on a non-working day.
        $candidates = $this->candidates($nonWorking, $from, $to);
        if ($candidates->isEmpty()) {
            $this->info('No false absences found. Nothing to do.');

            return self::SUCCESS;
        }

        // 2) Which payroll runs already used them.
        $usage = $this->payrollUsage($candidates->pluck('row.id')->all());

        $removable = $candidates->filter(fn ($c) => $this->blockingStatus($usage[$c['row']->id] ?? []) === null);
        $approved = $candidates->filter(fn ($c) => $this->blockingStatus($usage[$c['row']->id] ?? []) === PayrollRun::STATUS_APPROVED);
        $locked = $candidates->filter(fn ($c) => in_array($this->blockingStatus($usage[$c['row']->id] ?? []), self::LOCKED_FOR_REFUND, true));

        $this->reportSummary($candidates);
        $this->reportDrafts($removable, $usage);
        $this->reportApproved($approved, $usage);
        $this->reportRefunds($locked, $usage);

        $csv = $this->writeCsv($candidates, $usage, $dry ? 'dry-run' : 'removed');
        $this->line('Row list written to '.Storage::disk('local')->path($csv));

        if ($removable->isEmpty()) {
            $this->warn('Every false absence found is used by an approved, finalized or released payroll run. Nothing removed.');

            return self::SUCCESS;
        }

        if ($dry) {
            $this->info("[DRY RUN] {$removable->count()} row(s) would be removed. Nothing changed.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Remove {$removable->count()} attendance row(s)?", false)) {
            $this->warn('Cancelled. Nothing changed.');

            return self::FAILURE;
        }

        $removed = DB::transaction(function () use ($removable) {
            $ids = $removable->pluck('row.id')->all();

            // Re-check inside the transaction: a run may have been approved since.
            $nowBlocked = $this->payrollUsage($ids);
            $ids = array_values(array_filter($ids, fn ($id) => $this->blockingStatus($nowBlocked[$id] ?? []) === null));

            return StaffAttendance::query()->whereIn('id', $ids)->delete();
        });

        $this->info("Removed {$removed} attendance row(s).");

        return self::SUCCESS;
    }

    /** @return Collection<int, array{row: StaffAttendance, reason: string}> */
    private function candidates(NonWorkingDays $nonWorking, string $from, string $to): Collection
    {
        $out = collect();

        StaffAttendance::query()
            ->where('source', 'system')
            ->whereIn('status', ['absent', 'on_leave'])
            ->whereNull('time_in')
            ->whereNull('deleted_at')
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->when($this->option('spa'), fn ($q, $spa) => $q->where('spa_id', (int) $spa))
            ->orderBy('date')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($nonWorking, $out) {
                foreach ($rows as $row) {
                    $reason = $nonWorking->reason((int) $row->staff_id, (int) $row->branch_id, $row->date->toDateString());
                    if ($reason !== null) {
                        $out->push(['row' => $row, 'reason' => $reason]);
                    }
                }
            });

        return $out;
    }

    /**
     * attendance id → list of [run_id, status, payslip_id, staff_id, amount] for every
     * payslip line that used the row.
     *
     * @param list<int> $ids
     * @return array<int, list<array{run_id: int, status: string, payslip_id: int, staff_id: int, amount: string}>>
     */
    private function payrollUsage(array $ids): array
    {
        $out = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            PayslipLine::query()
                ->join('payslips', 'payslips.id', '=', 'payslip_lines.payslip_id')
                ->join('payroll_runs', 'payroll_runs.id', '=', 'payslips.payroll_run_id')
                ->where('payslip_lines.source_type', PayslipLine::SOURCE_ATTENDANCE)
                ->whereIn('payslip_lines.source_id', $chunk)
                ->get([
                    'payslip_lines.source_id', 'payslip_lines.amount', 'payslips.id as payslip_id',
                    'payslips.staff_id', 'payroll_runs.id as run_id', 'payroll_runs.status',
                ])
                ->each(function ($l) use (&$out) {
                    $out[(int) $l->source_id][] = [
                        'run_id'     => (int) $l->run_id,
                        'status'     => (string) $l->status,
                        'payslip_id' => (int) $l->payslip_id,
                        'staff_id'   => (int) $l->staff_id,
                        'amount'     => (string) $l->amount,
                    ];
                });
        }

        return $out;
    }

    /** The most locked non-draft status among a row's uses, or null if only drafts (or none). */
    private function blockingStatus(array $uses): ?string
    {
        $statuses = array_column($uses, 'status');
        foreach ([PayrollRun::STATUS_RELEASED, PayrollRun::STATUS_FINALIZED, PayrollRun::STATUS_APPROVED] as $s) {
            if (in_array($s, $statuses, true)) {
                return $s;
            }
        }

        return null;
    }

    private function reportSummary(Collection $candidates): void
    {
        $this->newLine();
        $this->line('False absences by branch, weekday and reason (check the closed days match what each branch had on those dates):');
        $this->table(
            ['Branch', 'Weekday', 'Reason', 'absent', 'on_leave', 'First', 'Last'],
            $candidates
                ->groupBy(fn ($c) => $c['row']->branch_id.'|'.$c['row']->date->format('l').'|'.$c['reason'])
                ->map(function (Collection $g) {
                    $r = $g->first()['row'];
                    $dates = $g->map(fn ($c) => $c['row']->date->toDateString())->sort()->values();

                    return [
                        '#'.$r->branch_id.' '.($r->branch?->name ?? ''),
                        $r->date->format('l'),
                        $g->first()['reason'] === NonWorkingDays::BRANCH_CLOSED ? 'branch closed' : 'rest day',
                        $g->where('row.status', 'absent')->count(),
                        $g->where('row.status', 'on_leave')->count(),
                        $dates->first(),
                        $dates->last(),
                    ];
                })
                ->sortBy(fn ($r) => $r[0].$r[1])
                ->values()
                ->all()
        );
    }

    private function reportDrafts(Collection $removable, array $usage): void
    {
        $runs = $removable->flatMap(fn ($c) => $usage[$c['row']->id] ?? [])->pluck('run_id')->unique()->sort()->values();
        if ($runs->isEmpty()) {
            return;
        }
        $this->warn('Draft payroll runs that used removable rows — regenerate them after the real run: #'.$runs->implode(', #'));
    }

    private function reportApproved(Collection $approved, array $usage): void
    {
        if ($approved->isEmpty()) {
            return;
        }
        $runs = $approved->flatMap(fn ($c) => $usage[$c['row']->id] ?? [])
            ->where('status', PayrollRun::STATUS_APPROVED)->pluck('run_id')->unique()->sort()->values();
        $this->warn("{$approved->count()} row(s) kept: used by APPROVED run(s) #".$runs->implode(', #')
            .'. Send those runs back to draft, re-run this command, then regenerate them.');
    }

    private function reportRefunds(Collection $locked, array $usage): void
    {
        if ($locked->isEmpty()) {
            return;
        }

        $rows = [];
        foreach ($locked as $c) {
            foreach ($usage[$c['row']->id] ?? [] as $u) {
                if (! in_array($u['status'], self::LOCKED_FOR_REFUND, true)) {
                    continue;
                }
                $key = $u['payslip_id'];
                $rows[$key] ??= [
                    'run' => $u['run_id'], 'payslip' => $u['payslip_id'], 'staff' => $u['staff_id'],
                    'name' => $c['row']->staff?->user?->name ?? '', 'dates' => [], 'refund' => '0.00',
                ];
                $rows[$key]['dates'][] = $c['row']->date->format('M j');
                // Deduction lines carry a negative amount; the refund is what was taken.
                if (bccomp($u['amount'], '0', 2) < 0) {
                    $rows[$key]['refund'] = bcsub($rows[$key]['refund'], $u['amount'], 2);
                }
            }
        }

        $this->newLine();
        $this->warn("{$locked->count()} row(s) kept: used by FINALIZED/RELEASED runs. Suggested refunds (nothing is applied — add each as a manual ADJ_EARNING line in the next draft run):");
        $this->table(
            ['Run', 'Payslip', 'Staff', 'Dates deducted', 'Suggested refund (₱)', 'Label'],
            collect($rows)->map(fn ($r) => [
                '#'.$r['run'], '#'.$r['payslip'], '#'.$r['staff'].' '.$r['name'],
                implode(', ', array_unique($r['dates'])), number_format((float) $r['refund'], 2),
                'Refund: absence deducted on non-working day(s)',
            ])->values()->all()
        );
    }

    private function writeCsv(Collection $candidates, array $usage, string $prefix): string
    {
        $path = 'attendance-cleanup/'.$prefix.'-'.now()->format('Ymd-His').'.csv';
        $fh = fopen('php://temp', 'w+');
        fputcsv($fh, ['attendance_id', 'staff_id', 'staff_name', 'spa_id', 'branch_id', 'date', 'weekday', 'status', 'source', 'reason', 'payroll_runs', 'action', 'created_at']);
        foreach ($candidates as $c) {
            $r = $c['row'];
            $uses = $usage[$r->id] ?? [];
            $blocking = $this->blockingStatus($uses);
            fputcsv($fh, [
                $r->id, $r->staff_id, $r->staff?->user?->name ?? '', $r->spa_id, $r->branch_id,
                $r->date->toDateString(), $r->date->format('l'), $r->status, $r->source, $c['reason'],
                collect($uses)->map(fn ($u) => '#'.$u['run_id'].' '.$u['status'])->unique()->implode('; '),
                match ($blocking) {
                    null => 'remove',
                    PayrollRun::STATUS_APPROVED => 'keep (approved run)',
                    default => 'keep (suggested refund)',
                },
                (string) $r->created_at,
            ]);
        }
        rewind($fh);
        Storage::disk('local')->put($path, stream_get_contents($fh));
        fclose($fh);

        return $path;
    }

    private function dateOption(string $name): ?string
    {
        $v = $this->option($name);
        if (! is_string($v) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);

        return $d && $d->format('Y-m-d') === $v ? $v : null;
    }
}
