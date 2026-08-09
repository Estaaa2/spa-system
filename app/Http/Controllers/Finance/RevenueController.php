<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Booking;
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

        // ── Current period bookings (completed only for revenue) ────────────
        $bookings = Booking::where('spa_id', $spa->id)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('status', 'completed')
            ->whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()])
            ->latest('appointment_date')
            ->get();

        // ── Previous period bookings (for growth comparison) ────────────────
        $prevBookings = Booking::where('spa_id', $spa->id)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('status', 'completed')
            ->whereBetween('appointment_date', [$prevFrom->toDateString(), $prevTo->toDateString()])
            ->get();

        // ── Revenue metrics ─────────────────────────────────────────────────
        // Uses booking.total_amount — the actual charged amount per booking.
        // The old calcRevenue() looked up Treatment::price which ignored
        // custom pricing, discounts, and package pricing. This is the fix.
        $totalRevenue    = $bookings->sum('total_amount');
        $totalCollected  = $bookings->sum('amount_paid');
        $totalOutstanding = $bookings->sum('balance_amount');
        $prevRevenue     = $prevBookings->sum('total_amount');

        $revenueGrowth = $prevRevenue > 0
            ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1)
            : null;

        $completedCount = $bookings->count();
        $prevCount      = $prevBookings->count();
        $countGrowth    = $prevCount > 0
            ? round((($completedCount - $prevCount) / $prevCount) * 100, 1)
            : null;

        // ── Revenue by source ───────────────────────────────────────────────
        $onlineRevenue  = $bookings->where('booking_source', 'online')->sum('total_amount');
        $walkInRevenue  = $bookings->whereIn('booking_source', ['walk_in', 'staff', null, ''])->sum('total_amount');

        // ── Daily revenue for chart ─────────────────────────────────────────
        $dailyRevenue = Booking::where('spa_id', $spa->id)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('status', 'completed')
            ->whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('appointment_date as date, SUM(total_amount) as total, COUNT(*) as count')
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
            'totalCollected'   => $totalCollected,
            'totalOutstanding' => $totalOutstanding,
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