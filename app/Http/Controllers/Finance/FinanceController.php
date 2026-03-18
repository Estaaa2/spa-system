<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Treatment;
use App\Models\Package;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    private function getSpa()
    {
        return Auth::user()->spa;
    }

    public function dashboard()
    {
        $spa   = $this->getSpa();
        $now   = Carbon::now();
        $month = $now->month;
        $year  = $now->year;

        // ── All completed bookings for this spa ──
        $bookings = Booking::where('spa_id', $spa->id)
            ->where('status', 'completed')
            ->get();

        // ── Revenue helpers ──
        $totalRevenue   = $this->calcRevenue($bookings);
        $monthlyRevenue = $this->calcRevenue(
            $bookings->filter(fn($b) =>
                $b->appointment_date->month === $month &&
                $b->appointment_date->year  === $year
            )
        );

        // Last month for comparison
        $lastMonth = $now->copy()->subMonth();
        $lastMonthRevenue = $this->calcRevenue(
            $bookings->filter(fn($b) =>
                $b->appointment_date->month === $lastMonth->month &&
                $b->appointment_date->year  === $lastMonth->year
            )
        );

        $revenueGrowth = $lastMonthRevenue > 0
            ? round((($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : 0;

        // ── Monthly revenue for chart (last 6 months) ──
        $monthlyChart = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $now->copy()->subMonths($i);
            $rev = $this->calcRevenue(
                $bookings->filter(fn($b) =>
                    $b->appointment_date->month === (int)$m->month &&
                    $b->appointment_date->year  === (int)$m->year
                )
            );
            $monthlyChart[] = [
                'label'   => $m->format('M Y'),
                'revenue' => $rev,
            ];
        }

        // ── Subscription billing ──
        $subscription = Subscription::where('spa_id', $spa->id)
            ->where('payment_status', 'paid')
            ->latest()
            ->first();

        // ── Inventory value ──
        $inventoryValue = Product::where('spa_id', $spa->id)
            ->selectRaw('SUM(stock_quantity) as total')
            ->value('total') ?? 0;

        $inventoryCount = Product::where('spa_id', $spa->id)->count();

        // ── Top earning treatments ──
        $topTreatments = $bookings
            ->filter(fn($b) => str_starts_with($b->treatment, 'treatment_'))
            ->groupBy('treatment')
            ->map(function ($group) {
                $id        = (int) str_replace('treatment_', '', $group->first()->treatment);
                $treatment = Treatment::withoutGlobalScopes()->find($id);
                return [
                    'name'    => $treatment?->name ?? 'Unknown',
                    'count'   => $group->count(),
                    'revenue' => ($treatment?->price ?? 0) * $group->count(),
                ];
            })
            ->sortByDesc('revenue')
            ->take(5)
            ->values();

        // ── Top earning packages ──
        $topPackages = $bookings
            ->filter(fn($b) => str_starts_with($b->treatment, 'package_'))
            ->groupBy('treatment')
            ->map(function ($group) {
                $id      = (int) str_replace('package_', '', $group->first()->treatment);
                $package = Package::find($id);
                return [
                    'name'    => $package?->name ?? 'Unknown',
                    'count'   => $group->count(),
                    'revenue' => ($package?->price ?? 0) * $group->count(),
                ];
            })
            ->sortByDesc('revenue')
            ->take(5)
            ->values();

        // Merge and sort top earners
        $topEarners = $topTreatments->merge($topPackages)
            ->sortByDesc('revenue')
            ->take(5)
            ->values();

        return view('finance.dashboard', compact(
            'totalRevenue',
            'monthlyRevenue',
            'lastMonthRevenue',
            'revenueGrowth',
            'monthlyChart',
            'subscription',
            'inventoryValue',
            'inventoryCount',
            'topEarners',
            'spa',
        ));
    }

    private function calcRevenue($bookings): float
    {
        $total = 0;
        foreach ($bookings as $b) {
            if (str_starts_with($b->treatment, 'treatment_')) {
                $id = (int) str_replace('treatment_', '', $b->treatment);
                $treatment = Treatment::withoutGlobalScopes()->find($id);
                $total += $treatment?->price ?? 0;
            } elseif (str_starts_with($b->treatment, 'package_')) {
                $id = (int) str_replace('package_', '', $b->treatment);
                $package = Package::find($id);
                $total += $package?->price ?? 0;
            }
        }
        return $total;
    }
}
