<?php

namespace App\Http\Controllers\Insights;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Treatment;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $user     = $request->user();
        $spaId    = $user->spa_id;
        $branchId = session('current_branch_id');

        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->input('to',   now()->format('Y-m-d'));

        $base = fn() => Booking::query()
            ->where('spa_id', $spaId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('appointment_date', [$from, $to]);

        // ── 1. Booking Status Breakdown ────────────────────────────────────
        $statusCounts = $base()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $allStatuses   = ['reserved', 'pending', 'ongoing', 'completed', 'cancelled'];
        $statusSummary = collect($allStatuses)->mapWithKeys(fn($s) => [$s => (int) ($statusCounts[$s] ?? 0)]);
        $totalBookings = $statusSummary->sum();

        // ── 2. Revenue (use actual booking.total_amount — NOT Treatment::price)
        // total_amount = what was charged; amount_paid = what was collected
        // This correctly reflects real transactions including any custom pricing.
        $revenueData = $base()
            ->selectRaw('
                SUM(total_amount) as gross,
                SUM(amount_paid)  as collected,
                SUM(balance_amount) as outstanding
            ')
            ->first();

        $grossRevenue   = (float) ($revenueData->gross ?? 0);
        $collected      = (float) ($revenueData->collected ?? 0);
        $outstanding    = (float) ($revenueData->outstanding ?? 0);

        // Revenue split by treatment vs package
        $allBookings = $base()->get(['treatment', 'total_amount']);
        [$treatRevenue, $pkgRevenue] = $this->splitRevenue($allBookings);

        // ── 3. Service Usage ───────────────────────────────────────────────
        [$treatmentCounts, $packageCounts] = $this->parseCounts($allBookings);

        $treatmentIds = array_keys($treatmentCounts);
        $packageIds   = array_keys($packageCounts);

        $treatments = Treatment::withoutGlobalScope('spa_branch')
            ->whereIn('id', $treatmentIds)->get(['id', 'name', 'price']);
        $packages = Package::withoutGlobalScope('spa_branch')
            ->whereIn('id', $packageIds)->get(['id', 'name', 'price']);

        $serviceRows = $treatments->map(fn($t) => [
            'type'     => 'Treatment',
            'name'     => $t->name,
            'count'    => $treatmentCounts[$t->id] ?? 0,
            'unit_price'=> (float) $t->price,
        ])->merge(
            $packages->map(fn($p) => [
                'type'     => 'Package',
                'name'     => $p->name,
                'count'    => $packageCounts[$p->id] ?? 0,
                'unit_price'=> (float) $p->price,
            ])
        )->sortByDesc('count')->values();

        // ── 4. Booking Source Breakdown ────────────────────────────────────
        $sourceCounts = $base()
            ->selectRaw("COALESCE(NULLIF(booking_source,''), 'staff') as source, COUNT(*) as total")
            ->groupBy('source')
            ->pluck('total', 'source');

        // ── 5. Staff Assignment Stats ──────────────────────────────────────
        $assignedCount   = $base()->whereNotNull('therapist_id')->count();
        $unassignedCount = $base()->whereNull('therapist_id')->count();

        // Per-therapist stats
        $therapistStats = $base()
            ->selectRaw('therapist_id, COUNT(*) as bookings, SUM(total_amount) as revenue')
            ->whereNotNull('therapist_id')
            ->groupBy('therapist_id')
            ->get();

        $therapistNames = collect();

        if ($therapistStats->isNotEmpty()) {
            $therapistNames = User::whereIn('id', $therapistStats->pluck('therapist_id'))
                ->get()
                ->mapWithKeys(fn($u) => [
                    $u->id => $u->full_name
                ]);
        }

        $therapistRows = $therapistStats->map(fn($r) => [
            'name'     => $therapistNames[$r->therapist_id] ?? "Therapist #{$r->therapist_id}",
            'bookings' => (int) $r->bookings,
            'revenue'  => (float) $r->revenue,
        ])->sortByDesc('bookings')->values();

        // ── 6. Daily Bookings Chart ────────────────────────────────────────
        $bookingsPerDay = $base()
            ->selectRaw('appointment_date as d, COUNT(*) as total')
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->map(fn($r) => ['label' => (string) $r->d, 'value' => (int) $r->total]);

        // ── 7. Daily Revenue Chart ─────────────────────────────────────────
        $revenuePerDay = $base()
            ->selectRaw('appointment_date as d, SUM(total_amount) as total')
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->map(fn($r) => ['label' => (string) $r->d, 'value' => round((float) $r->total, 2)]);

        return view('insights.reports.index', [
            'filters' => ['from' => $from, 'to' => $to],

            // Booking summary
            'totalBookings'  => $totalBookings,
            'statusSummary'  => $statusSummary,

            // Revenue
            'grossRevenue'   => $grossRevenue,
            'collected'      => $collected,
            'outstanding'    => $outstanding,
            'treatRevenue'   => $treatRevenue,
            'pkgRevenue'     => $pkgRevenue,

            // Source
            'sourceCounts'   => $sourceCounts,

            // Services
            'serviceRows'    => $serviceRows,

            // Staff
            'assignedCount'   => $assignedCount,
            'unassignedCount' => $unassignedCount,
            'therapistRows'   => $therapistRows,

            // Charts
            'bookingsPerDay'  => $bookingsPerDay,
            'revenuePerDay'   => $revenuePerDay,
        ]);
    }

    private function parseCounts($bookings): array
    {
        $tc = [];
        $pc = [];
        foreach ($bookings as $b) {
            if (!$b->treatment) continue;
            if (str_starts_with($b->treatment, 'treatment_')) {
                $id = (int) str_replace('treatment_', '', $b->treatment);
                $tc[$id] = ($tc[$id] ?? 0) + 1;
            } elseif (str_starts_with($b->treatment, 'package_')) {
                $id = (int) str_replace('package_', '', $b->treatment);
                $pc[$id] = ($pc[$id] ?? 0) + 1;
            }
        }
        return [$tc, $pc];
    }

    private function splitRevenue($bookings): array
    {
        $treatRev = 0.0;
        $pkgRev   = 0.0;
        foreach ($bookings as $b) {
            if (!$b->treatment) continue;
            if (str_starts_with($b->treatment, 'treatment_')) {
                $treatRev += (float) $b->total_amount;
            } elseif (str_starts_with($b->treatment, 'package_')) {
                $pkgRev += (float) $b->total_amount;
            }
        }
        return [$treatRev, $pkgRev];
    }
}