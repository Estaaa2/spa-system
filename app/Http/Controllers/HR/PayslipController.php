<?php

namespace App\Http\Controllers\HR;

use App\Exceptions\PayrollStateException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\HR\Concerns\HandlesPayrollSetupForms;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Models\Spa;
use App\Models\User;
use App\Services\Payroll\Display\PayslipDocument;
use App\Services\Payroll\PayrollRunReadModel;
use App\Services\Payroll\PayrollRunService;
use App\Services\Payroll\PayrollRunWarnings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Payslip view/print for HR and manual adjustment lines on draft runs (Unit 6).
 * Adjustments are the explicit way to handle SIL pay, final-pay extras and
 * corrections (ADJ_EARNING, ADJ_EARNING_NONTAX, ADJ_DEDUCTION); all validation of
 * code/label/amount/note and the recompute is PayrollRunService::addManualLine /
 * removeManualLine (Unit 4). Routes (Unit 7): view with `view payroll`, writes with
 * `edit payroll`.
 */
class PayslipController extends Controller
{
    use HandlesPayrollSetupForms;

    public function __construct(
        private readonly PayrollRunService $service,
        private readonly PayrollRunReadModel $read,
        private readonly PayrollRunWarnings $warnings,
    ) {
    }

    public function show(Payslip $payslip): View
    {
        $run = $this->ownedRun($payslip, $this->currentSpa());
        $parties = $this->read->payslipParties($payslip, $run);

        return view('hr.payroll.payslips.show', [
            'run'  => $run,
            'slip' => PayslipDocument::build($payslip->load('lines'), $run, $parties, $this->read->bookings($payslip->lines)),
            'back' => route('payroll.runs.show', $run).'#payslip-'.$payslip->id,
        ]);
    }

    public function storeLine(Request $request, Payslip $payslip): RedirectResponse
    {
        $this->authorizeEdit();
        $run = $this->ownedRun($payslip, $this->currentSpa());

        // Shape only; the service owns the business validation (codes, amount > 0, lengths).
        $v = Validator::make($request->all(), [
            'component_code' => ['required', 'string', Rule::in(PayrollRunService::MANUAL_CODES)],
            'label'          => ['required', 'string', 'max:100'],
            'amount'         => ['required', 'string', 'max:20'],
            'note'           => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $d = $v->validate();
            $settlement = $this->service->addManualLine(
                $payslip, $d['component_code'], $d['label'], trim($d['amount']), $d['note'] ?? null, $this->user(),
            );
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors(), 'adjustment')
                ->withInput($request->only(['component_code', 'label', 'amount', 'note']) + ['_modal' => 'adjustModal', '_record' => (string) $payslip->id])
                ->with('error', collect($e->errors())->flatten()->first() ?? 'Please check the adjustment.');
        } catch (PayrollStateException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->warnings->recordSettlement((int) $run->id, (int) $payslip->staff_id, $settlement->warnings, false);

        return redirect()->to(route('payroll.runs.show', $run).'#payslip-'.$payslip->id)
            ->with('expand', (int) $payslip->id)
            ->with('success', 'Adjustment added and the payslip recomputed.');
    }

    public function destroyLine(PayslipLine $line): RedirectResponse
    {
        $this->authorizeEdit();
        $payslip = Payslip::query()->findOrFail($line->payslip_id);
        $run = $this->ownedRun($payslip, $this->currentSpa());

        try {
            $settlement = $this->service->removeManualLine($line);
        } catch (PayrollStateException $e) {
            return back()->with('error', $e->getMessage());
        }

        $deleted = $settlement->payslip === null;
        $this->warnings->recordSettlement((int) $run->id, (int) $payslip->staff_id, $settlement->warnings, $deleted);

        return redirect()->to(route('payroll.runs.show', $run).($deleted ? '' : '#payslip-'.$payslip->id))
            ->with('expand', $deleted ? null : (int) $payslip->id)
            ->with('success', $deleted
                ? 'Adjustment removed. The payslip only held manual lines, so it was removed too.'
                : 'Adjustment removed and the payslip recomputed.');
    }

    // =====================================================================

    private function ownedRun(Payslip $payslip, Spa $spa): PayrollRun
    {
        $run = PayrollRun::query()->findOrFail($payslip->payroll_run_id);
        abort_unless((int) $run->spa_id === (int) $spa->id, 404);

        return $run;
    }

    private function authorizeEdit(): void
    {
        abort_unless((bool) Auth::user()?->hasBranchPermission('edit payroll'), 403);
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
