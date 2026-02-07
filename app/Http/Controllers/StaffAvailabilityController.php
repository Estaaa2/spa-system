<?php

namespace App\Http\Controllers;

use App\Models\StaffAvailability;
use App\Models\User;
use App\Models\OperatingHours;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StaffAvailabilityController extends Controller
{
    public function index(Request $request)
    {
        $branchId = auth()->user()->branch_id;

        $startOfWeek = Carbon::parse($request->week ?? now())->startOfWeek();
        $endOfWeek = $startOfWeek->copy()->endOfWeek();

        $staff = User::where('branch_id', $branchId)->get();
        
        $availabilities = StaffAvailability::where('branch_id', $branchId)
            ->whereBetween('date', [$startOfWeek, $endOfWeek])
            ->get()
            ->groupBy('user_id') // group by staff
            ->map(function ($group) {
                return $group->keyBy(function ($item) {
                    return $item->date->format('Y-m-d'); // key by date
                });
            });

        $operatingHours = OperatingHours::where('branch_id', $branchId)->get();

        $branchOperatingHours = [];
        foreach($staff as $s){
            foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $dayName){
                $day = $operatingHours->firstWhere('day_of_week', $dayName);
                $branchOperatingHours[$s->id][date('N', strtotime($dayName))] = [
                    'opening' => $day ? $day->opening_time : null,
                    'closing' => $day ? $day->closing_time : null,
                    'closed'  => $day ? $day->is_closed : true,
                ];
            }
        }

        // For week navigation links (optional lang naman, pero good to have).
        $prevWeek = $startOfWeek->copy()->subWeek()->format('Y-m-d');
        $nextWeek = $startOfWeek->copy()->addWeek()->format('Y-m-d');
        $weekDays = collect(range(0,6))->map(fn($i) => $startOfWeek->copy()->addDays($i));

        return view('staff.availability.index', compact(
            'staff', 
            'availabilities', 
            'startOfWeek', 
            'endOfWeek', 
            'prevWeek', 
            'nextWeek',
            'weekDays',
            'branchOperatingHours'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'status' => 'required|in:available,partial,unavailable',

            'start_time' => 'required_if:status,partial|nullable|date_format:H:i:s',
            'end_time'   => 'required_if:status,partial|nullable|date_format:H:i:s|after:start_time',
        ]);

        $branchId = auth()->user()->branch_id;

        if ($request->status === 'available') {
            StaffAvailability::where('user_id', $request->user_id)
                ->where('branch_id', $branchId)
                ->where('date', $request->date)
                ->delete();

            return back()->with('success', 'Availability saved (fully available by default).');
        }

        // For partial, keep start/end. For unavailable, force null.
        $start = $request->status === 'partial' ? $request->start_time : null;
        $end = $request->status === 'partial' ? $request->end_time : null;

        StaffAvailability::updateOrCreate(
            [
                'user_id' => $request->user_id,
                'branch_id' => $branchId,
                'date' => $request->date,
            ],
            [
                'status' => $request->status,
                'start_time' => $start,
                'end_time' => $end,
            ]
        );

        return back()->with('success', 'Availability set!');
    }
}
