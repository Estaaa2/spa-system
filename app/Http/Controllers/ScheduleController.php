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
        $currentBranchId = session('current_branch_id') ?? auth()->user()->branch_id;

        // ── Week bounds ────────────────────────────────────────────────
        $weekParam   = $request->query('week');
        $startOfWeek = $weekParam
            ? Carbon::parse($weekParam)->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);

        $endOfWeek = $startOfWeek->copy()->addDays(6);

        $dayDates = [];
        foreach (range(0, 6) as $i) {
            $dayDates[$i] = $startOfWeek->copy()->addDays($i);
        }

        // ── Operating hours for each day of the week ───────────────────
        $operatingHours = [];
        $earliestOpen   = null;
        $latestClose    = null;

        foreach ($dayDates as $date) {
            $dayOfWeek = $date->format('l');
            $hours     = OperatingHours::where('branch_id', $currentBranchId)
                ->where('day_of_week', $dayOfWeek)
                ->first();

            $opening = null;
            $closing = null;
            $closed  = true; // default to closed if no record found

            if ($hours) {
                // Explicitly closed
                if ($hours->is_closed) {
                    $closed = true;
                } else {
                    $opening = $hours->opening_time ? substr($hours->opening_time, 0, 5) : null;
                    $closing = $hours->closing_time ? substr($hours->closing_time, 0, 5) : null;

                    // Treat missing or midnight times as closed
                    $isValidTime = fn($t) => $t && $t !== '00:00';

                    if ($isValidTime($opening) && $isValidTime($closing)) {
                        $closed = false;

                        $openCarbon  = Carbon::createFromFormat('H:i', $opening);
                        $closeCarbon = Carbon::createFromFormat('H:i', $closing);

                        if ($earliestOpen === null || $openCarbon->lt($earliestOpen)) {
                            $earliestOpen = $openCarbon->copy();
                        }
                        if ($latestClose === null || $closeCarbon->gt($latestClose)) {
                            $latestClose = $closeCarbon->copy();
                        }
                    } else {
                        // Has a record but times are invalid — treat as closed
                        $opening = null;
                        $closing = null;
                        $closed  = true;
                    }
                }
            }
            // If $hours is null (no record for this day) → stays closed = true

            $operatingHours[$date->toDateString()] = [
                'opening_time' => $opening,
                'closing_time' => $closing,
                'closed'       => $closed,
            ];
        }

        // ── Fallback if ALL days are closed (no hours configured yet) ──
        if ($earliestOpen === null) {
            $earliestOpen = Carbon::createFromTime(9, 0);
            $latestClose  = Carbon::createFromTime(18, 0);
        }

        // ── Build 30-min time slots from earliest open → latest close ──
        $timeSlotKeys = [];
        $slotCursor   = $earliestOpen->copy();

        while ($slotCursor->lte($latestClose)) {
            $timeSlotKeys[] = $slotCursor->format('H:i');
            $slotCursor->addMinutes(30);
        }

        // ── Fetch bookings for the week ────────────────────────────────
        $bookings = Booking::query()
            ->with('latestRescheduleRequest')
            ->where('branch_id', $currentBranchId)
            ->whereBetween('appointment_date', [
                $startOfWeek->toDateString(),
                $endOfWeek->toDateString(),
            ])
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get();

        // ── Build grid: dateKey → timeKey → [bookings] ─────────────────
        $grid = [];

        foreach ($bookings as $b) {
            $dateKey   = Carbon::parse($b->appointment_date)->toDateString();
            $startTime = Carbon::parse($b->start_time);
            $endTime   = Carbon::parse($b->end_time);

            // Safety: if end_time <= start_time (bad data), skip
            if ($endTime->lte($startTime)) {
                continue;
            }

            $slot = $startTime->copy();
            while ($slot->lt($endTime)) {
                $timeKey = $slot->format('H:i');
                // Only add to grid if this slot is actually in our visible range
                if (in_array($timeKey, $timeSlotKeys, true)) {
                    $grid[$dateKey][$timeKey][] = $b;
                }
                $slot->addMinutes(30);
            }

            $b->start_slot = $startTime->format('H:i');
        }

        // ── Labels for the Blade ───────────────────────────────────────
        $prevWeek = $startOfWeek->copy()->subWeek()->toDateString();
        $nextWeek = $startOfWeek->copy()->addWeek()->toDateString();

        return view('schedule', compact(
            'startOfWeek',
            'endOfWeek',
            'prevWeek',
            'nextWeek',
            'grid',
            'bookings',
            'timeSlotKeys',
            'operatingHours',
            'dayDates',
        ));
    }

    // ── JSON endpoint (optional realtime) ─────────────────────────────
    public function data(Request $request)
    {
        $currentBranchId = session('current_branch_id') ?? auth()->user()->branch_id;

        $weekParam   = $request->query('week');
        $startOfWeek = $weekParam
            ? Carbon::parse($weekParam)->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);

        $endOfWeek = $startOfWeek->copy()->addDays(6);

        $bookings = Booking::query()
            ->where('branch_id', $currentBranchId)
            ->whereBetween('appointment_date', [
                $startOfWeek->toDateString(),
                $endOfWeek->toDateString(),
            ])
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get([
                'id',
                'appointment_date',
                'start_time',
                'end_time',
                'status',
                'service_type',
                'treatment',
                'customer_name',
                'customer_phone',
                'therapist_id'
            ]);

        $grid = [];
        foreach ($bookings as $b) {
            $dateKey   = Carbon::parse($b->appointment_date)->toDateString();
            $startTime = Carbon::parse($b->start_time);
            $endTime   = Carbon::parse($b->end_time);

            if ($endTime->lte($startTime)) continue;

            $slot = $startTime->copy();
            while ($slot->lt($endTime)) {
                $grid[$dateKey][$slot->format('H:i')][] = [
                    'id'            => $b->id,
                    'status'        => $b->status,
                    'service_type'  => $b->service_type,
                    'treatment'     => $b->treatment,
                    'customer_name' => $b->customer_name,
                    'customer_phone' => $b->customer_phone,
                ];
                $slot->addMinutes(30);
            }
        }

        return response()->json([
            'startOfWeek' => $startOfWeek->toDateString(),
            'grid'        => $grid,
        ]);
    }
}
