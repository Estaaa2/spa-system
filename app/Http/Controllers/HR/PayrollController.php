<?php

namespace App\Http\Controllers\HR;

use App\Exceptions\PayrollSetupException;
use App\Exceptions\PayrollStateException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\HR\Concerns\AuthorizesPayroll;
use App\Http\Controllers\HR\Concerns\HandlesPayrollSetupForms;
use App\Models\PayrollRun;
use App\Models\Spa;
use App\Models\Staff;
use App\Models\User;
use App\Services\Payroll\Display\PayrollDisplay;
use App\Services\Payroll\PayrollRunReadModel;
use App\Services\Payroll\PayrollPermissions as P;
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
 * PayrollRunReadModel. Access (RBAC refactor): pages need `view payroll`; generate,
 * regenerate and delete need `prepare payroll runs`; approve and send back need
 * `approve payroll runs`; finalize and release need `release payroll runs`. A run
 * always covers every branch, so those actions also need a spa-wide role (owner,
 * HR Head, Finance Head). Branch-scoped viewers only see their own branch's rows.
 */
class PayrollController extends Controller
{
    use AuthorizesPayroll;
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

        $scope = $this->payrollScope();

        return view('hr.payroll.index', [
            'spa'         => $spa,
            'years'       => $years,
            'filters'     => ['year' => $year, 'type' => $type, 'status' => $status],
            'runs'        => $this->read->runsIndex($spa, $year, $type, $status, $scope),
            'summary'     => $this->read->indexSummary($spa, $year, $scope),
            'gaps'        => $this->read->setupGaps($spa),
            'suggested'   => $this->suggestedPeriod($spa),
            // New Run = generate, which covers every branch.
            'canEdit'     => $this->canSpaWide(P::PREPARE),
            'scopeBranch' => $scope === null ? null : \App\Models\Branch::withTrashed()->whereKey($scope)->value('name'),
            'ownerNotice' => $this->ownerNotice($spa),
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
        $this->authorizeSpaWide(P::PREPARE);
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
        $scope = $this->payrollScope();
        $branchId = $this->scopedBranchFilter($request->integer('branch') ?: null);
        $register = $this->read->register($run, $branchId);
        if ($scope === null && $branchId !== null && ! array_key_exists($branchId, $register['branches'])) {
            $branchId = null;
            $register = $this->read->register($run, null);
        }

        $warnings = collect($cached['warnings'] ?? []);
        if ($scope !== null) {
            // Branch-scoped users see run-level notes and warnings about their own
            // branch's staff only — warnings name people from every branch.
            $inScope = Staff::withTrashed()->where('spa_id', $spa->id)->where('branch_id', $scope)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $warnings = $warnings->filter(fn (array $w) => ($w['staff_id'] ?? null) === null || in_array((int) $w['staff_id'], $inScope, true))->values();
        }
        $warnings = $warnings->groupBy(fn (array $w) => PayrollDisplay::warningGroup($w['code']));

        return view('hr.payroll.runs.show', [
            'spa'          => $spa,
            'run'          => $run,
            'header'       => $this->read->runHeader($run, $cached),
            'register'     => $register,
            'branchId'     => $branchId,
            'cached'       => $cached,
            'warnings'     => $warnings,
            'summary'      => $this->read->contributionSummary($spa, $run, $branchId),
            'perm'         => [
                'prepare_run' => $this->canSpaWide(P::PREPARE),   // regenerate, delete
                'adjust'      => $this->canPayroll(P::PREPARE),   // rows shown are already in scope
                'approve'     => $this->canSpaWide(P::APPROVE),   // approve, send back
                'release'     => $this->canSpaWide(P::RELEASE),   // finalize, release
            ],
            'branchLocked' => $scope !== null,
            'preparedByMe' => $this->read->preparedBy($run, (int) $this->payrollUser()->id),
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

        $scope = $this->payrollScope();
        $branches = $this->read->register($run, null)['branches'];
        if ($scope !== null) {
            $branches = array_intersect_key($branches, [$scope => true]);
        }
        $branchId = $this->scopedBranchFilter($request->integer('branch') ?: null);
        if ($scope === null && $branchId !== null && ! array_key_exists($branchId, $branches)) {
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
        $this->authorizeSpaWide(P::PREPARE);
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
        $this->authorizeSpaWide(P::PREPARE);
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

        /** The run page shows every saved warning next to the Approve button; the approver is recorded on the run. */
    public function approve(PayrollRun $run): RedirectResponse
    {
        return $this->step($run, P::APPROVE, fn (PayrollRun $r, User $u) => $this->service->approve($r, $u), 'Run approved. It can now be finalized, or sent back to draft for changes.');
    }

    public function sendBack(PayrollRun $run): RedirectResponse
    {
        return $this->step($run, P::APPROVE, fn (PayrollRun $r, User $u) => $this->service->sendBackToDraft($r, $u), 'Run sent back to draft. The approval was cleared.');
    }

    public function finalize(PayrollRun $run): RedirectResponse
    {
        return $this->step($run, P::RELEASE, fn (PayrollRun $r, User $u) => $this->service->finalize($r, $u), 'Run finalized. The register is locked; later corrections go into a future run as adjustments.');
    }

    public function release(PayrollRun $run): RedirectResponse
    {
        return $this->step($run, P::RELEASE, fn (PayrollRun $r, User $u) => $this->service->release($r, $u), 'Payslips released. Staff can now see them under My Payslips.');
    }

    // =====================================================================
    // Internals
    // =====================================================================

    private function step(PayrollRun $run, string $permission, callable $action, string $message): RedirectResponse
    {
        $this->authorizeSpaWide($permission);
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

    /**
     * Owners are view-only for payroll by default. When the spa has nobody who can
     * prepare or approve a run, tell the owner how to keep payroll running
     * (self-grant on Roles & Permissions). Disappears once someone can.
     *
     * @return list<string>|null
     */
    private function ownerNotice(Spa $spa): ?array
    {
        $user = $this->payrollUser();
        if (! $user->hasRole('owner')) {
            return null;
        }

        $hasActive = fn (string $role) => Staff::query()
            ->where('spa_id', $spa->id)->where('employment_status', 'active')
            ->whereHas('user', fn ($q) => $q->role($role))
            ->exists();

        $missing = [];
        if (! $this->canPayroll(P::PREPARE) && ! $hasActive(P::ROLE_HR_HEAD)) {
            $missing[] = 'prepare pay runs (no HR Head)';
        }
        if (! $this->canPayroll(P::APPROVE) && ! $hasActive(P::ROLE_FINANCE_HEAD)) {
            $missing[] = 'approve pay runs (no Finance Head)';
        }
        if (! $this->canPayroll(P::RELEASE) && ! $hasActive(P::ROLE_FINANCE_HEAD)) {
            $missing[] = 'finalize and release pay runs (no Finance Head)';
        }

        return $missing === [] ? null : $missing;
    }

    private function user(): User
    {
        return $this->payrollUser();
    }
}
