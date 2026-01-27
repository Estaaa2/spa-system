<?php
// app/Http/Controllers/ScheduleController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Staff;
use App\Models\StaffAvailability;
use App\Models\User;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        // Get the requested week or default to current week
        $week = $request->input('week');

        if ($week) {
            $startOfWeek = Carbon::parse($week)->startOfWeek();
        } else {
            $startOfWeek = Carbon::now()->startOfWeek();
        }

        // Calculate end of week
        $endOfWeek = $startOfWeek->copy()->endOfWeek();

        // Get staff with their availabilities for the week
        $staff = Staff::with(['user.roles', 'branch'])
            ->whereHas('user')
            ->where('employment_status', 'active')
            ->get();

        // Get availabilities for the week
        $availabilities = StaffAvailability::whereBetween('date', [$startOfWeek, $endOfWeek])
            ->get()
            ->groupBy('user_id');

        return view('schedule', [
            'startOfWeek' => $startOfWeek,
            'endOfWeek' => $endOfWeek,
            'staff' => $staff,
            'availabilities' => $availabilities,
        ]);
    }

    public function create()
    {
        $staff = Staff::with('user')->where('employment_status', 'active')->get();
        return view('schedules.create', compact('staff'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'status' => 'required|in:available,unavailable',
        ]);

        StaffAvailability::create([
            'user_id' => $request->user_id,
            'branch_id' => auth()->user()->branch_id,
            'date' => $request->date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'status' => $request->status,
        ]);

        return redirect()->route('schedule.index')->with('success', 'Schedule added successfully');
    }

    public function show($id)
    {
        $availability = StaffAvailability::with('user', 'branch')->findOrFail($id);
        return view('schedules.show', compact('availability'));
    }
}
