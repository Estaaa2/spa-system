<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffAttendance;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function myAttendance(Request $request)
    {
        $user = $request->user();
        $date = $request->query('date', now()->toDateString());

        $staff = Staff::where('user_id', $user->id)->first();

        if (!$staff) {
            return response()->json([
                'message' => 'Staff profile not found.'
            ], 404);
        }

        // ✅ Only one query — removed the duplicate above
        $records = StaffAttendance::with('staff.user')
            ->where('staff_id', $staff->id)
            ->whereDate('date', $date)
            ->get()
            ->map(function ($record) {
                return [
                    'id'         => $record->id,
                    'date'       => $record->date,
                    'status'     => $record->status,
                    'remarks'    => $record->remarks,
                    'staff_name' => optional($record->staff->user)->name ?? 'Unknown',
                ];
            });

        $summary = [
            'total'   => $records->count(),
            'present' => $records->where('status', 'present')->count(),
            'absent'  => $records->where('status', 'absent')->count(),
            'late'    => $records->where('status', 'late')->count(),
        ];

        return response()->json([
            'attendance' => $records,
            'summary'    => $summary,
        ]);
    }

}
