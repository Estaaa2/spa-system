<?php

namespace App\Http\Controllers\HR;

use App\Exceptions\PayrollSetupException;
use App\Exceptions\PayrollStateException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\HR\Concerns\HandlesPayrollSetupForms;
use App\Models\PayrollRun;
use App\Models\Spa;
use App\Models\User;
use App\Services\Payroll\Display\PayrollDisplay;
use App\Services\Payroll\PayrollRunReadModel;
use App\Services\Payroll\PayrollRunService;
use App\Services\Payroll\PayrollRunWarnings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Payroll runs (Unit 6): runs index (replaces the legacy per-branch payroll page),
 * run page, generate / regenerate / delete, and the draft → approved → finalized →
 * released steps. Spa-wide (consolidated runs, locked data model v3.1).
 *
 * Thin: every calculation and state change is PayrollRunService (Unit 4); reads are
 * PayrollRunReadModel. Routes (Unit 7): pages with `view payroll`, every POST/DELETE
 * with `edit payroll`, via branch.permission. Write actions re-check `edit payroll`
 * as a second line of defence (same check the middleware uses; RBAC is not changed).
 */
class PayrollController extends Controller
{
    use HandlesPayrollSetupForms;

    public function __construct(
        private readonly PayrollRunService $service,
        private readonly PayrollRunReadModel $read,
        private readonly PayrollRunWarnings $warnings,
    ) {
    }

    // =====================================================================
    // Runs index
    // =====================================================================

    public function index(Request $request): View
    {
        $spa = $this->currentSpa();
        $years = $this->read->yearOptions($spa);

        $year = (int) $request->query('year', now()->format('Y'));
        if (! in_array($year, $years, true)) {
            $year = (int) now()->format('Y');
        }
        $type = in_array($request->query('type'), [PayrollRun::TYPE_REGULAR, PayrollRun::TYPE_THIRTEENTH_MONTH], true) ? $request->query('type') : null;
        $status = array_key_exists((string) $request->query('status'), PayrollDisplay::STATUS) ? $request->query('status') : null;

        return view('hr.payroll.index', [
            'spa'       => $spa,
            'years'     => $years,
            'filters'   => ['year' => $year, 'type' => $type, 'status' => $status],
            'runs'      => $this->read->runsIndex($spa, $year, $type, $status),
            'summary'   => $this->read->indexSummary($spa, $year),
            'gaps'      => $this->read->setupGaps($spa),
            'suggested' => $this->suggestedPeriod($spa),
            'canEdit'   => $this->canEdit(),
        ]);
    }

    /** JSON preview for the "New run" modal — same period builder the engine uses. */
    public function preview(Request $request): JsonResponse
    {
        $spa = $this->currentSpa();
        $v = Validator::make($request->query(), $this->generateRules($request));
        if ($v->fails()) {
            return response()->json(['ok' => false, 'blocking' => $v->errors()->first(), 'notes' => [], 'period' => null, 'existing' => null]);
        }
        $d = $v->validated();

        $out = $d['run_type'] === PayrollRun::TYPE_REGULAR
            ? $this->read->previewRegular($spa, (int) $d['year'], (int) $d['month'], (int) $d['cutoff_no'])
            : $this->read->previewThirteenth($spa, (int) $d['year'], $d['pay_date']);

        if ($out['existing'] !== null) {
            $out['existing']['url'] = route('payroll.runs.show', $out['existing']['id']);
        }

        return response()->json($out);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeEdit();
        $spa = $this->currentSpa();

        $v = Validator::make($request->all(), $this->generateRules($request));
        if ($v->fails()) {
            return $this->backToModal($request, $v->errors()->all());
        }
        $d = $v->validated();

        try {
            $result = $d['run_type'] === PayrollRun::TYPE_REGULAR
                ? $this->service->generateRegular($spa, (int) $d['year'], (int) $d['month'], (int) $d['cutoff_no'], $this->user())
                : $this->service->generateThirteenthMonth($spa, (int) $d['year'], $d['pay_date'], $this->user());
        } catch (PayrollStateException|PayrollSetupException|InvalidArgumentException $e) {
            return $this->backToModal($request, [$e->getMessage()]);
        }

        $this->warnings->recordGeneration($result, $this->user());

        return redirect()->route('payroll.runs.show', $result->run)
            ->with('success', $this->generatedMessage($result->regenerated, $result->totals['payslips'], count($result->warnings)));
    }

    // =====================================================================
    // Run page
    // =====================================================================

    public function show(Request $request, PayrollRun $run): View
    {
        $spa = $this->currentSpa();
        $this->assertOwned($run, $spa);

        $cached = $this->warnings->get($run);
        $branchId = $request->integer('branch') ?: null;
        $register = $this->read->register($run, $branchId);
        if ($branchId !== null && ! array_key_exists($branchId, $register['branches'])) {
            $branchId = null;
            $register = $this->read->register($run, null);
        }

        $warnings = collect($cached['warnings'] ?? [])
            ->groupBy(fn (array $w) => PayrollDisplay::warningGroup($w['code']));

        return view('hr.payroll.runs.show', [
            'spa'          => $spa,
            'run'          => $run,
            'header'       => $this->read->runHeader($run, $cached),
            'register'     => $register,
            'branchId'     => $branchId,
            'cached'       => $cached,
            'warnings'     => $warnings,
            'summary'      => $this->read->contributionSummary($spa, $run, $branchId),
            'canEdit'      => $this->canEdit(),
            'manualCodes'  => PayrollRunService::MANUAL_CODES,
        ]);
    }

    /**
     * Every payslip of the run on one page, one per sheet (employee copies), optionally
     * with a "received by" block for cash payment (Book III, Rule VIII, Sec. 6: employees
     * sign the payroll). Read-only; `view payroll`.
     */
    public function print(Request $request, PayrollRun $run): View
    {
        $this->assertOwned($run, $this->currentSpa());

        $branches = $this->read->register($run, null)['branches'];
        $branchId = $request->integer('branch') ?: null;
        if ($branchId !== null && ! array_key_exists($branchId, $branches)) {
            $branchId = null;
        }

        return view('hr.payroll.runs.print', [
            'run'      => $run,
            'period'   => PayrollDisplay::periodLabel($run),
            'slips'    => $this->read->payslipDocuments($run, $branchId),
            'branches' => $branches,
            'branchId' => $branchId,
            'receipt'  => $request->boolean('receipt'),
            'isFinal'  => in_array($run->status, [PayrollRun::STATUS_FINALIZED, PayrollRun::STATUS_RELEASED], true),
        ]);
    }

    public function regenerate(PayrollRun $run): RedirectResponse
    {
        $this->authorizeEdit();
        $spa = $this->currentSpa();
        $this->assertOwned($run, $spa);

        try {
            $result = $run->run_type === PayrollRun::TYPE_REGULAR
                ? $this->service->generateRegular($spa, (int) $run->period_start->format('Y'), (int) $run->period_start->format('n'), (int) $run->cutoff_no, $this->user())
                : $this->service->generateThirteenthMonth($spa, (int) $run->period_start->format('Y'), $run->pay_date->toDateString(), $this->user());
        } catch (PayrollStateException|PayrollSetupException|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->warnings->recordGeneration($result, $this->user());

        return redirect()->route('payroll.runs.show', $result->run)
            ->with('success', $this->generatedMessage(true, $result->totals['payslips'], count($result->warnings)));
    }

    /**
     * Delete a DRAFT run. Unit 4 has no delete method; the locked model's rule (only
     * drafts can be deleted) is enforced by locking the row, re-checking, and the
     * PayrollRun deleting guard (Unit 1). Payslips and lines go by FK cascade.
     */
    public function destroy(PayrollRun $run): RedirectResponse
    {
        $this->authorizeEdit();
        $this->assertOwned($run, $this->currentSpa());

        try {
            DB::transaction(function () use ($run) {
                $fresh = PayrollRun::query()->whereKey($run->getKey())->lockForUpdate()->firstOrFail();
                if (! $fresh->canDelete()) {
                    throw new PayrollStateException("This run is {$fresh->status}; only draft runs can be deleted.");
                }
                $fresh->delete();
            });
        } catch (PayrollStateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('payroll.index', ['year' => $run->period_start->format('Y')])
            ->with('success', 'Draft run deleted. Its payslips and adjustment lines were removed.');
    }

    // =====================================================================
    // Status steps (PayrollRunService transitions)
    // =====================================================================

    /**
     * Approval is the review step: when the run has warnings, the approver must confirm
     * they reviewed them, and who did is stored on the run (payroll_runs.review) —
     * standard payroll control of a documented sign-off on exceptions before pay goes out.
     */
    public function approve(Request $request, PayrollRun $run): RedirectResponse
    {
        $this->authorizeEdit();
        $this->assertOwned($run, $this->currentSpa());

        if ($run->isDraft()) {
            if ($this->warnings->count($run) > 0 && ! $request->boolean('acknowledge_warnings')) {
                return back()->with('error', 'Confirm that you reviewed the warnings before approving.');
            }
            try {
                $this->warnings->recordReview($run, $this->user());
            } catch (PayrollStateException $e) {
                return back()->with('error', $e->getMessage());
            }
        }

        return $this->step($run, fn (PayrollRun $r, User $u) => $this->service->approve($r, $u), 'Run approved. It can now be finalized, or sent back to draft for changes.');
    }

    public function sendBack(PayrollRun $run): RedirectResponse
    {
        return $this->step($run, fn (PayrollRun $r, User $u) => $this->service->sendBackToDraft($r, $u), 'Run sent back to draft. The approval was cleared.');
    }

    public function finalize(PayrollRun $run): RedirectResponse
    {
        return $this->step($run, fn (PayrollRun $r, User $u) => $this->service->finalize($r, $u), 'Run finalized. The register is locked; later corrections go into a future run as adjustments.');
    }

    public function release(PayrollRun $run): RedirectResponse
    {
        return $this->step($run, fn (PayrollRun $r, User $u) => $this->service->release($r, $u), 'Payslips released. Staff can now see them under My Payslips.');
    }

    // =====================================================================
    // Internals
    // =====================================================================

    private function step(PayrollRun $run, callable $action, string $message): RedirectResponse
    {
        $this->authorizeEdit();
        $this->assertOwned($run, $this->currentSpa());

        try {
            $action($run, $this->user());
        } catch (PayrollStateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('payroll.runs.show', $run)->with('success', $message);
    }

    private function generateRules(Request $request): array
    {
        $regular = $request->input('run_type', $request->query('run_type')) === PayrollRun::TYPE_REGULAR;

        return [
            'run_type'  => ['required', Rule::in([PayrollRun::TYPE_REGULAR, PayrollRun::TYPE_THIRTEENTH_MONTH])],
            'year'      => ['required', 'integer', 'between:2000,2100'],
            'month'     => $regular ? ['required', 'integer', 'between:1,12'] : ['nullable'],
            'cutoff_no' => $regular ? ['required', 'integer', Rule::in([1, 2])] : ['nullable'],
            'pay_date'  => $regular ? ['nullable'] : ['required', 'date_format:Y-m-d'],
        ];
    }

    /** @param list<string> $messages */
    private function backToModal(Request $request, array $messages): RedirectResponse
    {
        return back()
            ->withErrors(['newRun' => $messages], 'newRun')
            ->withInput($request->only(['run_type', 'year', 'month', 'cutoff_no', 'pay_date']) + ['_modal' => 'newRunModal'])
            ->with('error', $messages[0] ?? 'Please check the form.');
    }

    private function generatedMessage(bool $regenerated, int $payslips, int $warnings): string
    {
        return ($regenerated ? 'Draft regenerated' : 'Draft run generated')
            ." with {$payslips} payslip(s)."
            .($warnings > 0 ? " {$warnings} warning(s) to review before approving." : '');
    }

    /**
     * The newest cutoff that has already ended: before/on the first-cutoff day it is
     * last month's cutoff 2, after it this month's cutoff 1. Just a default for the modal.
     *
     * @return array{year: int, month: int, cutoff_no: int, thirteenth_year: int, thirteenth_pay_date: string}
     */
    private function suggestedPeriod(Spa $spa): array
    {
        $today = now();
        $first = (int) $spa->payroll_first_cutoff_day;
        $ref = $today->day > $first ? $today->copy() : $today->copy()->subMonthNoOverflow();
        $cutoff = $today->day > $first ? 1 : 2;

        $deadline = (string) config('payroll.payment_timing.thirteenth_month_deadline');

        return [
            'year'                => (int) $ref->format('Y'),
            'month'               => (int) $ref->format('n'),
            'cutoff_no'           => $cutoff,
            'thirteenth_year'     => (int) $today->format('Y'),
            'thirteenth_pay_date' => $today->format('Y').'-'.$deadline,
        ];
    }

    private function assertOwned(PayrollRun $run, Spa $spa): void
    {
        abort_unless((int) $run->spa_id === (int) $spa->id, 404);
    }

    private function canEdit(): bool
    {
        return (bool) Auth::user()?->hasBranchPermission('edit payroll');
    }

    private function authorizeEdit(): void
    {
        abort_unless($this->canEdit(), 403);
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
