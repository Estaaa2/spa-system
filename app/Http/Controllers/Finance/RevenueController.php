<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Finance\BookingRevenue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RevenueController extends Controller
{
    private function getSpaAndBranch(): array
    {
        $user = Auth::user();
        return [$user->spa, $user->currentBranchId()];
    }

    public function index(Request $request)
    {
        [$spa, $branchId] = $this->getSpaAndBranch();

        // Default: current month
        $from = $request->filled('from')
            ? Carbon::parse($request->from)->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->to)->endOfDay()
            : now()->endOfMonth();

        // Prior period for comparison (same length, directly before $from)
        $periodDays = max((int) $from->diffInDays($to) + 1, 1);
        $prevFrom   = $from->copy()->subDays($periodDays);
        $prevTo     = $from->copy()->subDay()->endOfDay();

        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        // ── Current period bookings: checked out (completed) only ───────────
        $bookings = BookingRevenue::serviceBookings($spa->id, $branchId, $fromDate, $toDate)
            ->latest('appointment_date')
            ->get();

        // ── Previous period bookings (for growth comparison) ────────────────
        $prevBookings = BookingRevenue::serviceBookings(
            $spa->id, $branchId, $prevFrom->toDateString(), $prevTo->toDateString()
        )->get();

        // ── Revenue metrics (booking lifecycle fix, Phase 4, D9/Q1-A) ───────
        // Service revenue = money actually collected on checked-out bookings.
        // Checkout requires the full balance, so for new checkouts this equals
        // total_amount; older partially paid rows can't inflate it.
        $totalRevenue   = (float) $bookings->sum('amount_paid');
        $prevRevenue    = (float) $prevBookings->sum('amount_paid');
        // The table still shows each row's charged total and any old balance.
        $totalCharged     = (float) $bookings->sum('total_amount');
        $totalOutstanding = (float) $bookings->sum('balance_amount');

        $revenueGrowth = $prevRevenue > 0
            ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1)
            : null;

        $completedCount = $bookings->count();
        $prevCount      = $prevBookings->count();
        $countGrowth    = $prevCount > 0
            ? round((($completedCount - $prevCount) / $prevCount) * 100, 1)
            : null;

        // Q2-A / Q3-A: money kept on no-show / cancelled bookings, shown on its
        // own, never added to service revenue. As recorded (refunds aren't tracked).
        $keptDeposits = BookingRevenue::keptDeposits($spa->id, $branchId, $fromDate, $toDate);

        // Q4-A: balance still owed on ended bookings nobody closed (any date).
        $owedOnOpen = BookingRevenue::owedOnOpen($spa->id, $branchId);

        // ── Revenue by source ───────────────────────────────────────────────
        $onlineRevenue  = (float) $bookings->where('booking_source', 'online')->sum('amount_paid');
        $walkInRevenue  = (float) $bookings->whereIn('booking_source', ['walk_in', 'staff', null, ''])->sum('amount_paid');

        // ── Daily revenue for chart ─────────────────────────────────────────
        $dailyRevenue = BookingRevenue::serviceBookings($spa->id, $branchId, $fromDate, $toDate)
            ->selectRaw('appointment_date as date, SUM(amount_paid) as total, COUNT(*) as count')
            ->groupBy('appointment_date')
            ->orderBy('appointment_date')
            ->get()
            ->map(fn($r) => [
                'label'   => (string) $r->date,
                'revenue' => round((float) $r->total, 2),
                'count'   => (int) $r->count,
            ]);

        return view('finance.revenue.index', [
            'bookings'         => $bookings,
            'from'             => $from,
            'to'               => $to,
            'periodDays'       => $periodDays,
            // KPIs
            'totalRevenue'     => $totalRevenue,
            'totalCharged'     => $totalCharged,
            'totalOutstanding' => $totalOutstanding,
            'keptDeposits'     => $keptDeposits,
            'owedOnOpen'       => $owedOnOpen,
            'revenueGrowth'    => $revenueGrowth,
            'completedCount'   => $completedCount,
            'countGrowth'      => $countGrowth,
            'onlineRevenue'    => $onlineRevenue,
            'walkInRevenue'    => $walkInRevenue,
            // Chart
            'dailyRevenue'     => $dailyRevenue,
        ]);
    }
}