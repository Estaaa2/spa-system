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

        // ── Week bounds ──────────────────────────────────────────────────────
        $weekParam   = $request->query('week');
        $startOfWeek = $weekParam
            ? Carbon::parse($weekParam)->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);

        $endOfWeek = $startOfWeek->copy()->addDays(6);

        $dayDates = [];
        foreach (range(0, 6) as $i) {
            $dayDates[$i] = $startOfWeek->copy()->addDays($i);
        }

        // ── Operating hours — determine visible time range ───────────────────
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
            $closed  = true;

            if ($hours) {
                if ($hours->is_closed) {
                    $closed = true;
                } else {
                    $opening = $hours->opening_time ? substr($hours->opening_time, 0, 5) : null;
                    $closing = $hours->closing_time ? substr($hours->closing_time, 0, 5) : null;

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
                        $opening = null;
                        $closing = null;
                        $closed  = true;
                    }
                }
            }

            $operatingHours[$date->toDateString()] = [
                'opening_time' => $opening,
                'closing_time' => $closing,
                'closed'       => $closed,
            ];
        }

        // Fallback when no hours are configured at all
        if ($earliestOpen === null) {
            $earliestOpen = Carbon::createFromTime(9, 0);
            $latestClose  = Carbon::createFromTime(18, 0);
        }

        // ── Pixel layout constants ───────────────────────────────────────────
        // 2 px per minute = 120 px per hour (matches Google Calendar density).
        $pxPerMinute       = 2;
        $rangeStartMinutes = $earliestOpen->hour * 60 + $earliestOpen->minute;
        $rangeEndMinutes   = $latestClose->hour  * 60 + $latestClose->minute;
        $totalMinutes      = max($rangeEndMinutes - $rangeStartMinutes, 60);
        $totalHeight       = $totalMinutes * $pxPerMinute;

        // ── Time labels (every 30 min; isHour drives visual weight in Blade) ─
        $timeLabels  = [];
        $labelCursor = $rangeStartMinutes;

        while ($labelCursor <= $rangeEndMinutes) {
            $h = intdiv($labelCursor, 60);
            $m = $labelCursor % 60;

            $timeLabels[] = [
                'topPx'     => ($labelCursor - $rangeStartMinutes) * $pxPerMinute,
                'isHour'    => $m === 0,
                'labelFull' => Carbon::createFromTime($h, $m)->format('g A'), // "9 AM"
            ];

            $labelCursor += 30;
        }

        // ── Fetch bookings for the week ──────────────────────────────────────
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

        // ── Attach pixel positions to each booking ───────────────────────────
        // Bookings that start before open or end after close are clamped
        // (rather than dropped), so they still render at the boundary.
        foreach ($bookings as $b) {
            $startC = Carbon::parse($b->start_time);
            $endC   = Carbon::parse($b->end_time);

            if ($endC->lte($startC)) {
                $b->sched_skip = true;
                continue;
            }

            $startMin = $startC->hour * 60 + $startC->minute;
            $endMin   = $endC->hour   * 60 + $endC->minute;

            $clampedStart = max($startMin, $rangeStartMinutes);
            $clampedEnd   = min($endMin,   $rangeEndMinutes);

            if ($clampedEnd <= $clampedStart) {
                $b->sched_skip = true;
                continue;
            }

            $b->sched_skip      = false;
            $b->start_min       = $startMin;   // unclipped — used by overlap algorithm
            $b->end_min         = $endMin;
            $b->sched_top_px    = ($clampedStart - $rangeStartMinutes) * $pxPerMinute;
            $b->sched_height_px = max(($clampedEnd - $clampedStart) * $pxPerMinute, 24);
        }

        // ── Group by date, assign side-by-side overlap columns ───────────────
        // Without this, two simultaneous bookings would stack invisibly on top
        // of each other. The greedy algorithm puts them in separate columns.
        $bookingsByDate = [];

        foreach ($dayDates as $date) {
            $dateKey  = $date->toDateString();
            $dayItems = $bookings
                ->filter(fn($b) =>
                    !($b->sched_skip ?? true) &&
                    Carbon::parse($b->appointment_date)->toDateString() === $dateKey
                )
                ->sortBy('start_min')
                ->values()
                ->all();

            $bookingsByDate[$dateKey] = $this->assignOverlapColumns($dayItems);
        }

        $prevWeek = $startOfWeek->copy()->subWeek()->toDateString();
        $nextWeek = $startOfWeek->copy()->addWeek()->toDateString();

        return view('schedule', compact(
            'startOfWeek',
            'endOfWeek',
            'prevWeek',
            'nextWeek',
            'dayDates',
            'operatingHours',
            'pxPerMinute',
            'rangeStartMinutes',
            'rangeEndMinutes',
            'totalHeight',
            'timeLabels',
            'bookingsByDate',
        ));
    }

    // ── Greedy interval-graph colouring ──────────────────────────────────────
    // Assigns each booking an `overlap_column` (0-indexed) and `overlap_total`
    // so Blade can compute left% and width% for side-by-side rendering.
    private function assignOverlapColumns(array $bookings): array
    {
        $columns = [];

        foreach ($bookings as $booking) {
            $placed = false;

            foreach ($columns as $colIdx => &$col) {
                $conflict = false;
                foreach ($col as $existing) {
                    if ($booking->start_min < $existing->end_min
                        && $booking->end_min  > $existing->start_min) {
                        $conflict = true;
                        break;
                    }
                }
                if (!$conflict) {
                    $col[]                   = $booking;
                    $booking->overlap_column = $colIdx;
                    $placed                  = true;
                    break;
                }
            }
            unset($col);

            if (!$placed) {
                $columns[]               = [$booking];
                $booking->overlap_column = count($columns) - 1;
            }
        }

        $totalCols = max(count($columns), 1);
        foreach ($bookings as $booking) {
            $booking->overlap_total = $totalCols;
        }

        return $bookings;
    }

    // ── JSON endpoint (realtime / AJAX) ──────────────────────────────────────
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
                'id', 'appointment_date', 'start_time', 'end_time',
                'status', 'service_type', 'treatment',
                'customer_name', 'customer_phone', 'therapist_id',
            ]);

        $byDate = [];
        foreach ($bookings as $b) {
            $dateKey = Carbon::parse($b->appointment_date)->toDateString();
            $start   = Carbon::parse($b->start_time);
            $end     = Carbon::parse($b->end_time);

            $byDate[$dateKey][] = [
                'id'            => $b->id,
                'status'        => $b->status,
                'service_type'  => $b->service_type,
                'treatment'     => $b->treatment,
                'customer_name' => $b->customer_name,
                'start_min'     => $start->hour * 60 + $start->minute,
                'end_min'       => $end->hour   * 60 + $end->minute,
            ];
        }

        return response()->json([
            'startOfWeek' => $startOfWeek->toDateString(),
            'byDate'      => $byDate,
        ]);
    }
}