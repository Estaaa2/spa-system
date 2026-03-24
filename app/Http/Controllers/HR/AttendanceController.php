<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR\Concerns\ResolvesSpaBranchContext;
use App\Models\Staff;
use App\Models\StaffAttendance;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use ResolvesSpaBranchContext;

    public function index(Request $request)
    {
        [$spa, $branchId] = $this->getSpaAndBranch();

        $date = $request->date ? Carbon::parse($request->date) : today();

        $staffList = Staff::with(['user', 'attendance' => function ($q) use ($date) {
            $q->whereDate('date', $date);
        }])
            ->where('spa_id', $spa->id)
            ->where('branch_id', $branchId)
            ->where('employment_status', 'active')
            ->get();

        return view('hr.attendance.index', compact('staffList', 'date'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'attendance' => 'required|array',
            'attendance.*.staff_id' => 'required|exists:staff,id',
            'attendance.*.status' => 'required|in:present,absent,late',
            'attendance.*.remarks' => 'nullable|string',
            'date' => 'required|date',
        ]);

        [$spa, $branchId] = $this->getSpaAndBranch();

        foreach ($validated['attendance'] as $record) {
            StaffAttendance::updateOrCreate(
                [
                    'staff_id' => $record['staff_id'],
                    'date' => $validated['date'],
                ],
                [
                    'spa_id' => $spa->id,
                    'branch_id' => $branchId,
                    'status' => $record['status'],
                    'remarks' => $record['remarks'] ?? null,
                ]
            );
        }

        return back()->with('success', 'Attendance saved successfully.');
    }
}