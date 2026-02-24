<?php

namespace App\Http\Controllers\Insights;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Treatment;
use App\Models\Package;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $spaId = $user->spa_id;
        $branchId = session('current_branch_id'); // ✅ matches your sidebar branch switcher

        // Filters (YYYY-MM-DD)
        $from = $request->input('from');
        $to   = $request->input('to');

        // Base query (scoped to spa + selected branch + optional date range)
        $base = Booking::query()
            ->where('spa_id', $spaId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($from && $to, fn ($q) => $q->whereBetween('appointment_date', [$from, $to]));

        // Booking Summary
        $totalBookings = (clone $base)->count();
        $reserved      = (clone $base)->where('status', 'reserved')->count();
        $confirmed     = (clone $base)->where('status', 'confirmed')->count();
        $completed     = (clone $base)->where('status', 'completed')->count();

        // Service Summary (based on bookings.treatment code)
        $treatmentCount = (clone $base)->where('treatment', 'like', 'treatment_%')->count();
        $packageCount   = (clone $base)->where('treatment', 'like', 'package_%')->count();

        // Staff Summary
        $assignedTherapist   = (clone $base)->whereNotNull('therapist_id')->count();
        $unassignedTherapist = (clone $base)->whereNull('therapist_id')->count();

        // Get bookings (only what we need) for revenue computation
        $bookingsForRevenue = (clone $base)->get(['treatment']);

        /**
         * Revenue Summary
         * - Booking.treatment stores: "treatment_12" OR "package_5"
         * - We compute revenue by looking up Treatment.price / Package.price.
         * - No N+1 queries (we bulk load prices).
         */
        $revenue = 0.0;
        $revenueTreatments = 0.0;
        $revenuePackages = 0.0;

        $treatmentIds = [];
        $packageIds = [];

        foreach ($bookingsForRevenue as $b) {
            if (!$b->treatment) continue;

            if (str_starts_with($b->treatment, 'treatment_')) {
                $treatmentIds[] = (int) str_replace('treatment_', '', $b->treatment);
            } elseif (str_starts_with($b->treatment, 'package_')) {
                $packageIds[] = (int) str_replace('package_', '', $b->treatment);
            }
        }

        $treatmentIds = array_values(array_unique($treatmentIds));
        $packageIds   = array_values(array_unique($packageIds));

        // Bulk load prices
        $treatmentPrices = $treatmentIds
            ? Treatment::whereIn('id', $treatmentIds)->pluck('price', 'id')  // [id => price]
            : collect();

        $packagePrices = $packageIds
            ? Package::whereIn('id', $packageIds)->pluck('price', 'id')
            : collect();

        // Sum revenue
        foreach ($bookingsForRevenue as $b) {
            if (!$b->treatment) continue;

            if (str_starts_with($b->treatment, 'treatment_')) {
                $id = (int) str_replace('treatment_', '', $b->treatment);
                $price = (float) ($treatmentPrices[$id] ?? 0);
                $revenueTreatments += $price;
                $revenue += $price;
            } elseif (str_starts_with($b->treatment, 'package_')) {
                $id = (int) str_replace('package_', '', $b->treatment);
                $price = (float) ($packagePrices[$id] ?? 0);
                $revenuePackages += $price;
                $revenue += $price;
            }
        }

        // GRAPH: Bookings per day
        $bookingsPerDay = (clone $base)
            ->selectRaw('appointment_date as d, COUNT(*) as total')
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->map(fn ($r) => [
                'label' => (string) $r->d,
                'value' => (int) $r->total,
            ]);

        return view('insights.reports.index', [
            'filters' => [
                'from' => $from,
                'to'   => $to,
            ],
            'summary' => [
                'totalBookings' => $totalBookings,
                'reserved' => $reserved,
                'confirmed' => $confirmed,
                'completed' => $completed,
                'treatmentCount' => $treatmentCount,
                'packageCount' => $packageCount,
                'assignedTherapist' => $assignedTherapist,
                'unassignedTherapist' => $unassignedTherapist,
            ],
            'revenue' => [
                'total' => $revenue,
                'treatments' => $revenueTreatments,
                'packages' => $revenuePackages,
            ],
            'bookingsPerDay' => $bookingsPerDay,
        ]);
    }
}
