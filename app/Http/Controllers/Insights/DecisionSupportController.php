<?php

namespace App\Http\Controllers\Insights;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Treatment;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DecisionSupportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $spaId = $user->spa_id;
        $branchId = session('current_branch_id'); // ✅ you use session-based branch in sidebar

        // Filters
        $from = $request->input('from'); // YYYY-MM-DD
        $to = $request->input('to');     // YYYY-MM-DD

        $bookingsQuery = Booking::query()
            ->where('spa_id', $spaId);

        if ($branchId) {
            $bookingsQuery->where('branch_id', $branchId);
        }

        if ($from && $to) {
            $bookingsQuery->whereBetween('appointment_date', [$from, $to]);
        }

        $bookings = $bookingsQuery->get(['treatment', 'start_time', 'appointment_date']);

        /**
         * 1) Popular Services (Treatments)
         * bookings.treatment = "treatment_12"
         */
        $treatmentCounts = [];
        $packageCounts = [];

        foreach ($bookings as $b) {
            if (!$b->treatment) continue;

            if (str_starts_with($b->treatment, 'treatment_')) {
                $id = (int) str_replace('treatment_', '', $b->treatment);
                $treatmentCounts[$id] = ($treatmentCounts[$id] ?? 0) + 1;
            }

            if (str_starts_with($b->treatment, 'package_')) {
                $id = (int) str_replace('package_', '', $b->treatment);
                $packageCounts[$id] = ($packageCounts[$id] ?? 0) + 1;
            }
        }

        // Map IDs to names (and keep only existing records)
        $treatments = Treatment::whereIn('id', array_keys($treatmentCounts))->get(['id', 'name']);
        $packages = Package::whereIn('id', array_keys($packageCounts))->get(['id', 'name']);

        $popularServices = $treatments->map(function ($t) use ($treatmentCounts) {
            return [
                'label' => $t->name,
                'value' => $treatmentCounts[$t->id] ?? 0,
            ];
        })->sortByDesc('value')->values();

        $popularPackages = $packages->map(function ($p) use ($packageCounts) {
            return [
                'label' => $p->name,
                'value' => $packageCounts[$p->id] ?? 0,
            ];
        })->sortByDesc('value')->values();

        /**
         * 2) Peak Hours
         * If start_time is TIME or "HH:MM:SS", we can parse via SQL.
         */
        $peakHours = Booking::query()
            ->selectRaw('HOUR(start_time) as hour, COUNT(*) as total')
            ->where('spa_id', $spaId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($from && $to, fn ($q) => $q->whereBetween('appointment_date', [$from, $to]))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(fn ($r) => [
                'label' => str_pad((string)$r->hour, 2, '0', STR_PAD_LEFT) . ':00',
                'value' => (int) $r->total,
            ]);

        return view('insights.decision-support.index', [
            'popularServices' => $popularServices,
            'popularPackages' => $popularPackages,
            'peakHours' => $peakHours,
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }
}
