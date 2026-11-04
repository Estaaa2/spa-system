<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\Payroll\Display\PayslipDocument;
use App\Services\Payroll\PayrollRunReadModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * My Payslips (Unit 6) — staff self-service.
 *
 * Identity-based, not permission-gated: same idea as
 * AttendanceController::myStaffRecord() (Staff by user_id + spa_id), except that
 * inactive and soft-deleted staff records are included so a former or re-hired
 * employee still sees what was released to them. Only payslips of RELEASED runs are
 * visible; anyone else's payslip, or an unreleased one, is a 404 (not 403), so the
 * page never confirms that a payslip exists.
 */
class MyPayslipController extends Controller
{
    public function __construct(private readonly PayrollRunReadModel $read)
    {
    }

    public function index(Request $request): View
    {
        $user = Auth::user();
        $staffIds = $this->read->staffIdsFor($user);
        $years = $this->read->myYears($user, $staffIds);

        $year = (int) $request->query('year', now()->format('Y'));
        if (! in_array($year, $years, true)) {
            $year = (int) now()->format('Y');
        }

        return view('hr.payroll.my.index', [
            'hasStaff' => $staffIds !== [],
            'years'    => $years,
            'year'     => $year,
            'payslips' => $this->read->myPayslips($user, $staffIds, $year),
            'ytd'      => $this->read->myYearToDate($user, $staffIds, $year),
        ]);
    }

    public function show(Payslip $payslip): View
    {
        $user = Auth::user();
        $run = PayrollRun::query()->find($payslip->payroll_run_id);

        abort_unless(
            $run !== null
            && (int) $run->spa_id === (int) $user->spa_id
            && $run->status === PayrollRun::STATUS_RELEASED
            && in_array((int) $payslip->staff_id, $this->read->staffIdsFor($user), true),
            404
        );

        $parties = $this->read->payslipParties($payslip, $run);

        return view('hr.payroll.my.show', [
            'slip' => PayslipDocument::build($payslip->load('lines'), $run, $parties, $this->read->bookings($payslip->lines)),
        ]);
    }
}
