<?php

namespace App\Http\Controllers\Insights;

use App\Services\Finance\BookingRevenue;
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

        $revenueBase = fn() => $base()->where('status', 'completed');

        // ── 1. Booking Summary ─────────────────────────────────────────────
        // Counts, all statuses — unchanged.
        $statusCounts = $base()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $allStatuses   = ['reserved', 'pending', 'ongoing', 'completed', 'cancelled', Booking::NO_SHOW];
        $statusSummary = collect($allStatuses)->mapWithKeys(fn($s) => [$s => (int) ($statusCounts[$s] ?? 0)]);
        $totalBookings = $statusSummary->sum();

        // ── 2. Revenue ─────────────────────────────────────────────────────
        // Sums, completed-only — changed from all-statuses to match Finance\RevenueController's revenue figures, which are explicitly ->where('status', 'completed').
        // Booking lifecycle fix, Phase 4 (shared rules in BookingRevenue):
        //   service revenue = money collected on checked-out bookings (D9/Q1-A)
        //   kept deposits   = paid on no-show / cancelled, shown apart (Q2-A/Q3-A)
        //   owed on open    = balance on ended bookings nobody closed (Q4-A)
        $grossRevenue = (float) $revenueBase()->sum('amount_paid');
        $keptDeposits = BookingRevenue::keptDeposits((int) $spaId, $branchId ? (int) $branchId : null, $from, $to);
        $owedOnOpen   = BookingRevenue::owedOnOpen((int) $spaId, $branchId ? (int) $branchId : null);

        $allBookings = $base()->get(['treatment', 'total_amount']);

        $revenueBookings = $revenueBase()->get(['treatment', 'amount_paid']);
        [$treatRevenue, $pkgRevenue] = $this->splitRevenue($revenueBookings);

        // ── 3. Service Breakdown ─────────────────────────────────────────────
        // Counts, all statuses — not a revenue figure, unchanged.
        [$treatmentCounts, $packageCounts] = $this->parseCounts($allBookings);

        $treatmentIds = array_keys($treatmentCounts);
        $packageIds   = array_keys($packageCounts);

        $treatments = Treatment::withoutGlobalScope('spa_branch')
            ->whereIn('id', $treatmentIds)->get(['id', 'name', 'price']);
        $packages = Package::withoutGlobalScope('spa_branch')
            ->whereIn('id', $packageIds)->get(['id', 'name', 'price']);

        $serviceRows = $treatments->toBase()->map(fn($t) => [
            'type'     => 'Treatment',
            'name'     => $t->name,
            'count'    => $treatmentCounts[$t->id] ?? 0,
            'unit_price'=> (float) $t->price,
        ])->merge(
            $packages->toBase()->map(fn($p) => [
                'type'     => 'Package',
                'name'     => $p->name,
                'count'    => $packageCounts[$p->id] ?? 0,
                'unit_price'=> (float) $p->price,
            ])
        )->sortByDesc('count')->values();

        // ── 4. Booking Source Breakdown ────────────────────────────────────
        // Counts, all statuses — not a revenue figure, unchanged.
        $sourceCounts = $base()
            ->selectRaw("COALESCE(NULLIF(booking_source,''), 'staff') as source, COUNT(*) as total")
            ->groupBy('source')
            ->pluck('total', 'source');

        // ── 5. Staff Assignment Stats ──────────────────────────────────────
        // Assignment coverage — counts, all statuses, unchanged.
        $assignedCount   = $base()->whereNotNull('therapist_id')->count();
        $unassignedCount = $base()->whereNull('therapist_id')->count();

        // ── 6. Therapist Stats ─────────────────────────────────────────────
        // Counts and revenue, completed-only — changed from all-statuses to match Finance\RevenueController's revenue figures, 
        // which are explicitly ->where('status', 'completed').
        $therapistStats = $revenueBase()
            ->selectRaw('therapist_id, COUNT(*) as bookings, SUM(amount_paid) as revenue')
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

        // ── 7. Charts ───────────────────────────────────────────────────────
        // Bookings per day and revenue per day — counts and sums, completed-only for revenue, unchanged.
        $bookingsPerDay = $base()
            ->selectRaw('appointment_date as d, COUNT(*) as total')
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->map(fn($r) => ['label' => (string) $r->d, 'value' => (int) $r->total]);

        // Revenue per day — counts and sums, completed-only for revenue, unchanged.
        $revenuePerDay = $revenueBase()
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
            'keptDeposits'   => $keptDeposits,
            'owedOnOpen'     => $owedOnOpen,
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
                $treatRev += (float) $b->amount_paid;
            } elseif (str_starts_with($b->treatment, 'package_')) {
                $pkgRev += (float) $b->amount_paid;
            }
        }
        return [$treatRev, $pkgRev];
    }
}