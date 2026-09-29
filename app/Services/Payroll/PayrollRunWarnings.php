<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Exceptions\PayrollStateException;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Payroll\ValueObjects\RunResult;
use App\Services\Payroll\ValueObjects\RunWarning;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a run's warnings, and the reviewer's confirmation, on the run itself (Unit 6;
 * data model v3.2 column payroll_runs.review).
 *
 * Unit 4 returns warnings but does not persist them ("the caller must show or flash
 * them"). A flash would reach only the person who clicked Generate, so an approver
 * would approve blind, and nothing would show later what was known when the run was
 * approved. Payroll audit practice asks for exactly that record: what the reviewer
 * saw, who approved, and when.
 *
 * Every write happens while the run is DRAFT (generation, manual-line changes, the
 * confirmation just before approval), inside a transaction that locks the run row.
 * PayrollRun's guard refuses the column on any later status, so the record is frozen
 * with the register.
 */
final class PayrollRunWarnings
{
    /** Codes PayslipSettler emits for one payslip; replaced after a manual-line change. */
    private const SETTLER_CODES = [
        RunWarning::CONTRIBUTIONS_SKIPPED,
        RunWarning::UNVERIFIED_RULE,
        RunWarning::UNAPPLIED_DEDUCTION,
        RunWarning::CUMULATIVE_AVERAGE,
    ];

    public function recordGeneration(RunResult $result, User $by): void
    {
        $this->write((int) $result->run->id, fn (?array $old) => [
            'generated_at'   => now()->toIso8601String(),
            'generated_by'   => (int) $by->getKey(),
            'regenerated'    => $result->regenerated,
            'adjusted_at'    => null,
            'warnings'       => array_map(static fn (RunWarning $w) => $w->toArray(), $result->warnings),
            // A regenerated draft must be reviewed again.
            'reviewed_by'    => null,
            'reviewed_at'    => null,
            'reviewed_count' => null,
        ]);
    }

    /**
     * After a manual line is added or removed, replace that staff member's settlement
     * warnings with the new ones. The run-level cumulative-average note from generation
     * is kept as is (Unit 4 folds it into one run-level line), so per-payslip ones are
     * not re-added.
     *
     * @param list<RunWarning> $warnings
     */
    public function recordSettlement(int $runId, int $staffId, array $warnings, bool $payslipDeleted): void
    {
        $this->write($runId, function (?array $data) use ($staffId, $warnings, $payslipDeleted) {
            if ($data === null) {
                return null;   // run generated before v3.2 — nothing to adjust; the page says to regenerate
            }

            $drop = $payslipDeleted ? [...self::SETTLER_CODES, RunWarning::ORPHANED_MANUAL_LINES] : self::SETTLER_CODES;
            $kept = array_values(array_filter($data['warnings'] ?? [], static fn (array $w) => ! (
                ($w['staff_id'] ?? null) === $staffId && in_array($w['code'], $drop, true)
            )));
            foreach ($warnings as $w) {
                if ($w->code !== RunWarning::CUMULATIVE_AVERAGE) {
                    $kept[] = $w->toArray();
                }
            }

            return array_merge($data, [
                'warnings'       => $kept,
                'adjusted_at'    => now()->toIso8601String(),
                'reviewed_by'    => null,
                'reviewed_at'    => null,
                'reviewed_count' => null,
            ]);
        });
    }

    /** Record who confirmed the warnings, immediately before approval (still draft). */
    public function recordReview(PayrollRun $run, User $by): void
    {
        $this->write((int) $run->id, function (?array $data) use ($by) {
            $data ??= ['warnings' => []];

            return array_merge($data, [
                'reviewed_by'    => (int) $by->getKey(),
                'reviewed_at'    => now()->toIso8601String(),
                'reviewed_count' => count($data['warnings'] ?? []),
            ]);
        });
    }

    /** @return array<string, mixed>|null null for runs generated before v3.2 */
    public function get(PayrollRun $run): ?array
    {
        return is_array($run->review) ? $run->review : null;
    }

    public function count(PayrollRun $run): int
    {
        return count($this->get($run)['warnings'] ?? []);
    }

    /** @param callable(?array): ?array $change */
    private function write(int $runId, callable $change): void
    {
        DB::transaction(function () use ($runId, $change) {
            $run = PayrollRun::query()->whereKey($runId)->lockForUpdate()->firstOrFail();
            if (! $run->isDraft()) {
                throw new PayrollStateException("Payroll run #{$run->id} is {$run->status}; its review record is frozen.");
            }

            $new = $change(is_array($run->review) ? $run->review : null);
            if ($new !== null) {
                $run->forceFill(['review' => $new])->save();
            }
        });
    }
}
