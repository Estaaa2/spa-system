<?php

namespace App\Http\Controllers\Insights;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Treatment;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\Request;

class DecisionSupportController extends Controller
{
    public function index(Request $request)
    {
        $user     = $request->user();
        $spaId    = $user->spa_id;
        $branchId = session('current_branch_id');

        // Default to current month
        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->input('to',   now()->format('Y-m-d'));

        // Prior period — same number of days, immediately before $from
        $periodDays = max((int) now()->parse($from)->diffInDays(now()->parse($to)) + 1, 1);
        $prevFrom   = now()->parse($from)->subDays($periodDays)->format('Y-m-d');
        $prevTo     = now()->parse($from)->subDay()->format('Y-m-d');

        $base = fn() => Booking::query()
            ->where('spa_id', $spaId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

        // Revenue uses booking.total_amount — the actual charged amount per booking.
        // NOT Treatment::price, which would ignore custom pricing/discounts.
        $currentBookings = $base()
            ->whereBetween('appointment_date', [$from, $to])
            ->get(['id', 'treatment', 'start_time', 'appointment_date',
                   'status', 'total_amount', 'therapist_id']);

        $prevBookings = $base()
            ->whereBetween('appointment_date', [$prevFrom, $prevTo])
            ->get(['id', 'treatment', 'status', 'total_amount', 'therapist_id']);

        // ── KPIs ──────────────────────────────────────────────────────────
        $totalCurrent  = $currentBookings->count();
        $totalPrev     = $prevBookings->count();
        $bookingGrowth = $totalPrev > 0
            ? round((($totalCurrent - $totalPrev) / $totalPrev) * 100, 1)
            : null;
        $avgPerDay     = round($totalCurrent / $periodDays, 1);

        $revenueCurrent = $currentBookings->sum('total_amount');
        $revenuePrev    = $prevBookings->sum('total_amount');
        $revenueGrowth  = $revenuePrev > 0
            ? round((($revenueCurrent - $revenuePrev) / $revenuePrev) * 100, 1)
            : null;

        $cancelledCount = $currentBookings->where('status', 'cancelled')->count();
        $cancelRate     = $totalCurrent > 0
            ? round(($cancelledCount / $totalCurrent) * 100, 1)
            : 0;

        // ── Service / Package counts ───────────────────────────────────────
        [$treatmentCounts, $packageCounts]         = $this->parseCounts($currentBookings);
        [$prevTreatmentCounts, $prevPackageCounts] = $this->parseCounts($prevBookings);

        $allTreatmentIds = array_unique(array_merge(
            array_keys($treatmentCounts), array_keys($prevTreatmentCounts)
        ));
        $allPackageIds = array_unique(array_merge(
            array_keys($packageCounts), array_keys($prevPackageCounts)
        ));

        // withoutGlobalScope because we're already filtering by IDs — the
        // spa_branch scope would try to re-filter by session branch which is
        // redundant and could drop records if session differs from booking branch.
        $treatments = Treatment::withoutGlobalScope('spa_branch')
            ->whereIn('id', $allTreatmentIds)->get(['id', 'name']);
        $packages   = Package::withoutGlobalScope('spa_branch')
            ->whereIn('id', $allPackageIds)->get(['id', 'name']);

        $popularServices = $treatments->map(fn($t) => [
            'label' => $t->name,
            'value' => $treatmentCounts[$t->id] ?? 0,
            'prev'  => $prevTreatmentCounts[$t->id] ?? 0,
        ])->sortByDesc('value')->values();

        $popularPackages = $packages->map(fn($p) => [
            'label' => $p->name,
            'value' => $packageCounts[$p->id] ?? 0,
            'prev'  => $prevPackageCounts[$p->id] ?? 0,
        ])->sortByDesc('value')->values();

        // ── Booking trend (daily) ──────────────────────────────────────────
        $bookingTrend = $base()
            ->selectRaw('appointment_date as date, COUNT(*) as total')
            ->whereBetween('appointment_date', [$from, $to])
            ->groupBy('appointment_date')
            ->orderBy('appointment_date')
            ->get()
            ->map(fn($r) => ['label' => (string) $r->date, 'value' => (int) $r->total]);

        // ── Peak hours ─────────────────────────────────────────────────────
        $peakHours = $base()
            ->selectRaw('HOUR(start_time) as hour, COUNT(*) as total')
            ->whereBetween('appointment_date', [$from, $to])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(fn($r) => [
                'label' => str_pad((string) $r->hour, 2, '0', STR_PAD_LEFT) . ':00',
                'value' => (int) $r->total,
            ]);

        // ── Day-of-week breakdown ──────────────────────────────────────────
        $dowRaw    = $base()
            ->selectRaw('DAYOFWEEK(appointment_date) as dow, COUNT(*) as total')
            ->whereBetween('appointment_date', [$from, $to])
            ->groupBy('dow')
            ->get()
            ->keyBy('dow');

        $dayLabels = [1 => 'Sun', 2 => 'Mon', 3 => 'Tue', 4 => 'Wed', 5 => 'Thu', 6 => 'Fri', 7 => 'Sat'];
        $dayData   = collect(range(1, 7))->map(fn($d) => [
            'label' => $dayLabels[$d],
            'value' => (int) ($dowRaw[$d]->total ?? 0),
        ])->values();

        // ── Therapist workload ─────────────────────────────────────────────
        // Booking.therapist_id → User.id (the Booking model uses belongsTo(User::class))
        $therapistGroups = $currentBookings
            ->whereNotNull('therapist_id')
            ->groupBy('therapist_id')
            ->map(fn($group) => $group->count());

        $therapistNames = collect();

        if ($therapistGroups->isNotEmpty()) {
            $therapistNames = User::whereIn('id', $therapistGroups->keys())
                ->get()
                ->mapWithKeys(fn($u) => [
                    $u->id => $u->first_name . ' ' . $u->last_name
                ]);
        }

        $staffUtilization = $therapistGroups->map(fn($count, $uid) => [
            'label' => $therapistNames[$uid] ?? "Therapist #{$uid}",
            'value' => $count,
        ])->sortByDesc('value')->values();

        // ── All packages for this branch (for "unbooked" insight) ─────────
        $allPackagesInBranch = Package::withoutGlobalScope('spa_branch')
            ->where('spa_id', $spaId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->pluck('name', 'id');

        // ── Insights engine ────────────────────────────────────────────────
        $insights = $this->generateInsights(
            bookingGrowth:    $bookingGrowth,
            totalCurrent:     $totalCurrent,
            cancelRate:       $cancelRate,
            peakHours:        $peakHours,
            popularServices:  $popularServices,
            dayData:          $dayData,
            packageCounts:    $packageCounts,
            revenueGrowth:    $revenueGrowth,
            allPackages:      $allPackagesInBranch,
            staffUtilization: $staffUtilization,
        );

        return view('insights.decision-support.index', [
            'kpis' => [
                'total'          => $totalCurrent,
                'prev_total'     => $totalPrev,
                'growth'         => $bookingGrowth,
                'avg_per_day'    => $avgPerDay,
                'period_days'    => $periodDays,
                'revenue'        => $revenueCurrent,
                'revenue_growth' => $revenueGrowth,
                'cancel_rate'    => $cancelRate,
            ],
            'popularServices'  => $popularServices,
            'popularPackages'  => $popularPackages,
            'peakHours'        => $peakHours,
            'bookingTrend'     => $bookingTrend,
            'dayData'          => $dayData,
            'staffUtilization' => $staffUtilization,
            'insights'         => $insights,
            'filters'          => ['from' => $from, 'to' => $to],
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

    private function generateInsights(
        ?float $bookingGrowth,
        int    $totalCurrent,
        float  $cancelRate,
        $peakHours,
        $popularServices,
        $dayData,
        array  $packageCounts,
        ?float $revenueGrowth,
        $allPackages,
        $staffUtilization,
    ): array {
        $insights = [];

        if ($totalCurrent === 0) {
            return [['type' => 'info', 'title' => 'No Booking Data',
                     'message' => 'No bookings found for the selected period. Try adjusting the date range.']];
        }

        if ($bookingGrowth !== null) {
            if ($bookingGrowth <= -20) {
                $insights[] = ['type' => 'danger', 'title' => 'Significant Booking Drop',
                    'message' => abs($bookingGrowth) . '% fewer bookings vs the prior period. Check for staff absences, holidays, or a new competitor nearby. A limited-time promo may help recover volume.'];
            } elseif ($bookingGrowth <= -10) {
                $insights[] = ['type' => 'warning', 'title' => 'Booking Volume Declining',
                    'message' => abs($bookingGrowth) . '% fewer bookings vs prior period. If this continues next week, consider a loyalty push or a reminder campaign to existing customers.'];
            } elseif ($bookingGrowth >= 20) {
                $insights[] = ['type' => 'success', 'title' => 'Strong Booking Growth',
                    'message' => $bookingGrowth . '% more bookings than the prior period. Verify that therapist availability can handle the increased load without quality degradation.'];
            }
        }

        if ($revenueGrowth !== null && $revenueGrowth <= -15) {
            $insights[] = ['type' => 'warning', 'title' => 'Revenue Declining',
                'message' => 'Revenue dropped ' . abs($revenueGrowth) . '% vs prior period. If booking counts are stable, customers may be shifting to lower-priced services. Review service mix.'];
        }

        if ($cancelRate >= 20) {
            $insights[] = ['type' => 'danger', 'title' => 'High Cancellation Rate',
                'message' => "{$cancelRate}% of bookings were cancelled. Consider requiring partial payment for online bookings and sending automated reminders 24h before appointments."];
        } elseif ($cancelRate >= 10) {
            $insights[] = ['type' => 'warning', 'title' => 'Elevated Cancellation Rate',
                'message' => "{$cancelRate}% cancellation rate. Automated SMS/email reminders typically reduce this by 30–40%."];
        }

        $maxHourly = $peakHours->max('value') ?? 0;
        if ($maxHourly > 0) {
            $under = $peakHours->filter(fn($h) => $h['value'] > 0 && $h['value'] < $maxHourly * 0.25);
            if ($under->count() >= 2) {
                $hours = $under->pluck('label')->join(', ');
                $insights[] = ['type' => 'info', 'title' => 'Underutilized Time Slots',
                    'message' => "Very low booking activity at: {$hours}. Consider off-peak discounts or schedule staff admin/training during these windows."];
            }
        }

        $declining = $popularServices->filter(fn($s) => $s['prev'] > 2 && $s['value'] < $s['prev'] * 0.6);
        if ($declining->count()) {
            $names = $declining->pluck('label')->join(', ');
            $first = $declining->first();
            $pct   = $first['prev'] > 0 ? round((1 - $first['value'] / $first['prev']) * 100) : 0;
            $insights[] = ['type' => 'warning', 'title' => 'Declining Service Demand',
                'message' => "Bookings for {$names} dropped ~{$pct}% vs prior period. Check pricing, staff specialization, or its placement on the booking page."];
        }

        $unbookedPkgs = $allPackages->except(array_keys($packageCounts));
        if ($unbookedPkgs->count()) {
            $names  = $unbookedPkgs->take(3)->values()->join(', ');
            $suffix = $unbookedPkgs->count() > 3 ? ' and ' . ($unbookedPkgs->count() - 3) . ' more' : '';
            $insights[] = ['type' => 'info', 'title' => 'Packages with Zero Bookings',
                'message' => "No bookings for: {$names}{$suffix} this period. Feature them on the booking page or offer a bundled promo rate."];
        }

        if ($staffUtilization->count() >= 2) {
            $maxLoad = $staffUtilization->max('value');
            $minLoad = $staffUtilization->min('value');
            if ($maxLoad > 0 && $minLoad < $maxLoad * 0.4) {
                $busiest  = $staffUtilization->firstWhere('value', $maxLoad)['label'];
                $lightest = $staffUtilization->firstWhere('value', $minLoad)['label'];
                $insights[] = ['type' => 'warning', 'title' => 'Uneven Therapist Workload',
                    'message' => "{$busiest} is handling significantly more bookings than {$lightest}. Redistribute assignments to prevent burnout and maintain consistent service quality."];
            }
        }

        $activeDays = $dayData->filter(fn($d) => $d['value'] > 0);
        if ($activeDays->count() >= 2) {
            $best  = $activeDays->sortByDesc('value')->first();
            $worst = $activeDays->sortBy('value')->first();
            if ($best['label'] !== $worst['label']) {
                $insights[] = ['type' => 'info', 'title' => 'Day-of-Week Pattern',
                    'message' => "{$best['label']} is the busiest day ({$best['value']} bookings). {$worst['label']} is the slowest — a good candidate for a weekday promo or staff scheduling flexibility."];
            }
        }

        if (empty($insights)) {
            $insights[] = ['type' => 'success', 'title' => 'Operations Look Healthy',
                'message' => 'No notable issues detected for this period. Bookings are stable with no significant drops or concerning patterns.'];
        }

        return $insights;
    }
}