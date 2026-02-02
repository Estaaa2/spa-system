<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $currentBranchId = session('current_branch_id');

        // Week start (Monday)
        $weekParam = $request->query('week');
        $startOfWeek = $weekParam
            ? Carbon::parse($weekParam)->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);

        $endOfWeek = $startOfWeek->copy()->addDays(6);

        // ✅ Use 24-hour keys for matching
        $timeSlotKeys = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'];

        // Get bookings for the week (per branch)
        $bookings = Booking::query()
            ->where('branch_id', $currentBranchId)
            ->whereBetween('appointment_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get();

        // Build grid: [YYYY-MM-DD][HH:MM] => [bookings...]
        $grid = [];
        foreach ($bookings as $b) {
            $dateKey = Carbon::parse($b->appointment_date)->toDateString();
            $timeKey = Carbon::parse($b->start_time)->format('H:i');

            // Only place bookings that match your shown time slots
            if (!in_array($timeKey, $timeSlotKeys, true)) {
                continue;
            }

            $grid[$dateKey][$timeKey][] = $b;
        }

        $prevWeek = $startOfWeek->copy()->subWeek()->toDateString();
        $nextWeek = $startOfWeek->copy()->addWeek()->toDateString();

        return view('schedule', compact(
            'startOfWeek',
            'endOfWeek',
            'prevWeek',
            'nextWeek',
            'timeSlotKeys',
            'grid'
        ));
    }

    // Optional realtime endpoint (returns JSON for the current week)
    public function data(Request $request)
    {
        $currentBranchId = session('current_branch_id');

        $weekParam = $request->query('week');
        $startOfWeek = $weekParam
            ? Carbon::parse($weekParam)->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);

        $endOfWeek = $startOfWeek->copy()->addDays(6);

        $timeSlotKeys = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'];

        $bookings = Booking::query()
            ->where('branch_id', $currentBranchId)
            ->whereBetween('appointment_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get(['id','appointment_date','start_time','status','service_type','treatment','customer_name','customer_phone','therapist_id']);

        $grid = [];
        foreach ($bookings as $b) {
            $dateKey = Carbon::parse($b->appointment_date)->toDateString();
            $timeKey = Carbon::parse($b->start_time)->format('H:i');

            if (!in_array($timeKey, $timeSlotKeys, true)) continue;

            $grid[$dateKey][$timeKey][] = [
                'id' => $b->id,
                'status' => $b->status,
                'service_type' => $b->service_type,
                'treatment' => $b->treatment,
                'customer_name' => $b->customer_name,
                'customer_phone' => $b->customer_phone,
            ];
        }

        return response()->json([
            'startOfWeek' => $startOfWeek->toDateString(),
            'grid' => $grid,
        ]);
    }
}
