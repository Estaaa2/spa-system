<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\Payroll\Display\PayslipDocument;
use App\Services\Payroll\PayrollRunReadModel;
use Illuminate\Http\Request;

/**
 * My Payslips for mobile: the web's HR\MyPayslipController, answering in JSON.
 * Same read model, same checks, same display strings; nothing is computed here.
 *
 * Only payslips of RELEASED runs in the user's spa that belong to one of the
 * user's staff records are visible. Anything else is a 404, like the web, so
 * the API never confirms that a payslip exists.
 */
class MyPayslipController extends Controller
{
    public function __construct(private readonly PayrollRunReadModel $read)
    {
    }

    /** GET /api/my-payslips?year=2026 */
    public function index(Request $request)
    {
        $user     = $request->user();
        $staffIds = $this->read->staffIdsFor($user);
        $years    = $this->read->myYears($user, $staffIds);

        $year = (int) $request->query('year', now()->format('Y'));
        if (! in_array($year, $years, true)) {
            $year = (int) now()->format('Y');
        }

        return response()->json([
            'has_staff' => $staffIds !== [],
            'years'     => array_values($years),
            'year'      => $year,
            'payslips'  => collect($this->read->myPayslips($user, $staffIds, $year))->values(),
            'ytd'       => $this->read->myYearToDate($user, $staffIds, $year),
        ]);
    }

    /** GET /api/my-payslips/{payslip} */
    public function show(Request $request, Payslip $payslip)
    {
        $user = $request->user();
        $run  = PayrollRun::query()->find($payslip->payroll_run_id);

        abort_unless(
            $run !== null
            && (int) $run->spa_id === (int) $user->spa_id
            && $run->status === PayrollRun::STATUS_RELEASED
            && in_array((int) $payslip->staff_id, $this->read->staffIdsFor($user), true),
            404
        );

        $parties = $this->read->payslipParties($payslip, $run);

        return response()->json(
            PayslipDocument::build($payslip->load('lines'), $run, $parties, $this->read->bookings($payslip->lines))
        );
    }
}
