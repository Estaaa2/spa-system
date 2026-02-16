<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\OperatingHours;
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

        $dayDates = [];
        foreach (range(0,6) as $i) {
            $dayDates[$i] = $startOfWeek->copy()->addDays($i);
        }

        $timeSlotKeys = [];
        $start = Carbon::createFromTime(9,0);
        $end = Carbon::createFromTime(16,0);

        while($start <= $end) {
            $timeSlotKeys[] = $start->format('H:i');
            $start->addMinutes(30);  // 30-min resolution
        }

        // Get bookings for the week (per branch)
        $bookings = Booking::query()
            ->where('branch_id', $currentBranchId)
            ->whereBetween('appointment_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get();

        $slotResolution = 30; // minutes per row
        $dayStart = Carbon::createFromTime(7,0); // timetable start time

        foreach ($bookings as $b) {
            $start = Carbon::parse($b->start_time);
            $end = Carbon::parse($b->end_time);

            $rowStart = $dayStart->diffInMinutes($start) / $slotResolution + 1;
            $rowEnd = $dayStart->diffInMinutes($end) / $slotResolution + 1;

            $b->gridRow = "$rowStart / $rowEnd";
        }

        $grid = [];

        foreach ($bookings as $b) {
            $dateKey = Carbon::parse($b->appointment_date)->toDateString();
            $startTime = Carbon::parse($b->start_time);
            $endTime = Carbon::parse($b->end_time);

            $occupiedSlots = [];
            $slot = $startTime->copy();
            
            while($slot < $endTime) {
                $occupiedSlots[] = $slot->format('H:i');
                $slot->addMinutes(30);
            }

            foreach ($occupiedSlots as $timeKey) {
                $grid[$dateKey][$timeKey][] = $b;
            }

            // Optional: store start_time separately for front-end check
            $b->start_slot = $startTime->format('H:i');
        }

        $operatingHours = [];
        foreach ($dayDates as $date) {
            $dayOfWeek = $date->format('l'); // "Monday", "Tuesday", ...
            $hours = OperatingHours::where('branch_id', $currentBranchId)
                        ->where('day_of_week', $dayOfWeek)
                        ->first();

            // Defaults
            $opening = null;
            $closing = null;
            $closed = true;

            if ($hours) {
                // If explicit flag set, honor it
                if ($hours->is_closed) {
                    $opening = null;
                    $closing = null;
                    $closed = true;
                } else {
                    // Ensure we only treat a valid opening/closing as open
                    $opening = $hours->opening_time ? substr($hours->opening_time, 0, 5) : null;
                    $closing = $hours->closing_time ? substr($hours->closing_time, 0, 5) : null;

                    // Treat "00:00" / "00:00:00" as closed (some seeds use that)
                    if ($opening === '00:00' || $closing === '00:00' || !$opening || !$closing) {
                        $opening = null;
                        $closing = null;
                        $closed = true;
                    } else {
                        $closed = false;
                    }
                }
            } else {
                // No operating_hours row -> treat as closed by default
                $opening = null;
                $closing = null;
                $closed = true;
            }

            $operatingHours[$date->toDateString()] = [
                'opening_time' => $opening,
                'closing_time' => $closing,
                'closed' => $closed,
            ];
        }

        $prevWeek = $startOfWeek->copy()->subWeek()->toDateString();
        $nextWeek = $startOfWeek->copy()->addWeek()->toDateString();

        return view('schedule', compact(
            'startOfWeek',
            'endOfWeek',
            'prevWeek',
            'nextWeek',
            'grid',
            'bookings',
            'slotResolution',
            'timeSlotKeys',
            'operatingHours'
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
