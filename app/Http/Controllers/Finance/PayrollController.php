<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\Staff;
use App\Models\StaffAttendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    private function getSpaAndBranch()
    {
        $user     = Auth::user();
        $spa      = $user->spa;
        $branchId = $user->currentBranchId();
        return [$spa, $branchId];
    }

    public function index()
    {
        [$spa, $branchId] = $this->getSpaAndBranch();

        $payrolls = Payroll::with('staff.user')
            ->where('spa_id', $spa->id)
            ->where('branch_id', $branchId)
            ->latest()
            ->get();

        $staffList = Staff::with('user')
            ->where('spa_id', $spa->id)
            ->where('branch_id', $branchId)
            ->where('employment_status', 'active')
            ->get();

        return view('finance.payroll.index', compact('payrolls', 'staffList'));
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
        ]);

        [$spa, $branchId] = $this->getSpaAndBranch();

        $start = Carbon::parse($validated['period_start']);
        $end   = Carbon::parse($validated['period_end']);
        $label = $start->format('M d') . ' – ' . $end->format('M d, Y');

        $staffList = Staff::with(['user', 'attendance'])
            ->where('spa_id', $spa->id)
            ->where('branch_id', $branchId)
            ->where('employment_status', 'active')
            ->get();

        DB::transaction(function () use ($staffList, $spa, $branchId, $start, $end, $label, $validated) {
            foreach ($staffList as $staff) {
                $attendance = StaffAttendance::where('staff_id', $staff->id)
                    ->whereBetween('date', [$start, $end])
                    ->get();

                $daysPresent = $attendance->where('status', 'present')->count();
                $daysAbsent  = $attendance->where('status', 'absent')->count();
                $daysLate    = $attendance->where('status', 'late')->count();

                $dailyRate = $staff->daily_rate ?? 0;
                $absentDeduction = $daysAbsent * $dailyRate;
                $lateDeduction = $daysLate * ($dailyRate * 0.5);

                $commission = $staff->bookings()
                    ->whereBetween('appointment_date', [$start, $end])
                    ->where('status', 'completed')
                    ->count() * 50;

                $totalPay = $staff->basic_salary
                    - $absentDeduction
                    - $lateDeduction
                    + $commission;

                Payroll::updateOrCreate(
                    [
                        'staff_id'     => $staff->id,
                        'period_start' => $validated['period_start'],
                        'period_end'   => $validated['period_end'],
                    ],
                    [
                        'spa_id'           => $spa->id,
                        'branch_id'        => $branchId,
                        'period_label'     => $label,
                        'basic_salary'     => $staff->basic_salary,
                        'days_present'     => $daysPresent,
                        'days_absent'      => $daysAbsent,
                        'days_late'        => $daysLate,
                        'absent_deduction' => $absentDeduction,
                        'late_deduction'   => $lateDeduction,
                        'commission'       => $commission,
                        'total_pay'        => max(0, $totalPay),
                        'status'           => 'draft',
                    ]
                );
            }
        });

        return back()->with('success', 'Payroll generated for ' . $label);
    }

    public function finalize(Payroll $payroll)
    {
        $payroll->update(['status' => 'finalized']);

        return back()->with('success', 'Payroll finalized.');
    }
}