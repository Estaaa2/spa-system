<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RevenueController extends Controller
{
    protected function calcRevenue($bookings): float
    {
        $total = 0;

        foreach ($bookings as $booking) {
            if (str_starts_with($booking->treatment, 'treatment_')) {
                $id = (int) str_replace('treatment_', '', $booking->treatment);
                $treatment = Treatment::withoutGlobalScopes()->find($id);
                $total += $treatment?->price ?? 0;
            } elseif (str_starts_with($booking->treatment, 'package_')) {
                $id = (int) str_replace('package_', '', $booking->treatment);
                $package = Package::find($id);
                $total += $package?->price ?? 0;
            }
        }

        return $total;
    }

    private function getSpaAndBranch()
    {
        $user     = Auth::user();
        $spa      = $user->spa;
        $branchId = $user->currentBranchId();
        return [$spa, $branchId];
    }
    
    public function index(Request $request)
    {
        [$spa, $branchId] = $this->getSpaAndBranch();

        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : now()->startOfMonth();
        $to   = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : now()->endOfMonth();

        $bookings = Booking::where('spa_id', $spa->id)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'completed')
            ->whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()])
            ->latest('appointment_date')
            ->get();

        $totalRevenue = $this->calcRevenue($bookings);

        return view('finance.revenue.index', compact('bookings', 'totalRevenue', 'from', 'to'));
    }
}
