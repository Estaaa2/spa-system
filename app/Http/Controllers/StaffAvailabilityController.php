<?php

namespace App\Http\Controllers;

use App\Models\StaffAvailability;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StaffAvailabilityController extends Controller
{
    public function index(Request $request)
    {
        $branchId = auth()->user()->branch_id;
        $startOfWeek = Carbon::parse($request->week ?? now())->startOfWeek();

        $staff = User::where('branch_id', $branchId)->get();

        $availabilities = StaffAvailability::where('branch_id', $branchId)
            ->whereBetween('date', [
                $startOfWeek,
                $startOfWeek->copy()->endOfWeek()
            ])
            ->get()
            ->groupBy('user_id');

        return view('staff.availability.index', compact('staff', 'availabilities', 'startOfWeek'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
        ]);

        StaffAvailability::create([
            'user_id' => $request->user_id,
            'branch_id' => auth()->user()->branch_id,
            'date' => $request->date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'status' => 'available',
        ]);

        return back()->with('success', 'Availability set!');
    }
}
