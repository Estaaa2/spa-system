<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Treatment;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $currentBranchId = $user->currentBranchId();

        if (!$currentBranchId) {
            if (!$user->spa || !$user->spa->is_setup_complete) {
                return redirect()->route('setup.index');
            }
            return redirect()->route('branches.index')
                ->with('warning', 'Please select a branch to continue.');
        }

        $spaId = $user->spa_id;
        $today = now()->toDateString();

        $base      = fn() => Booking::query()->where('spa_id', $spaId)->where('branch_id', $currentBranchId);
        $todayBase = fn() => $base()->whereDate('appointment_date', $today);

        // ── Decide which data blocks to load based on permissions ─────────
        // This avoids running expensive queries for data the user can't see.
        $needsKpis = $user->can('view dashboard kpis')
                  || $user->can('view dashboard revenue')
                  || $user->can('view dashboard alerts');

        // ── KPI / shared counts ───────────────────────────────────────────
        $todayCount = $ongoingToday = $pendingToday = $reservedToday = null;
        $completedToday = $cancelledToday = $upcomingWeek = null;

        if ($needsKpis) {
            $todayCount     = $todayBase()->count();
            $ongoingToday   = $todayBase()->where('status', 'ongoing')->count();
            $pendingToday   = $todayBase()->where('status', 'pending')->count();
            $reservedToday  = $todayBase()->where('status', 'reserved')->count();
            $completedToday = $todayBase()->where('status', 'completed')->count();
            $cancelledToday = $todayBase()->where('status', 'cancelled')->count();
            $upcomingWeek   = $base()
                ->whereDate('appointment_date', '>', $today)
                ->whereDate('appointment_date', '<=', now()->addDays(7)->toDateString())
                ->whereIn('status', ['reserved', 'pending'])
                ->count();
        }

        // ── Revenue data ──────────────────────────────────────────────────
        $collectedToday = $onlineToday = $walkInToday = $topServiceLabel = null;

        if ($user->can('view dashboard revenue')) {
            $collectedToday = $todayBase()
                ->whereIn('status', ['ongoing', 'completed'])
                ->sum('amount_paid');

            $onlineToday = $todayBase()->where('booking_source', 'online')->count();
            $walkInToday = $todayBase()
                ->where(fn($q) => $q->where('booking_source', '!=', 'online')
                                    ->orWhereNull('booking_source'))
                ->count();

            $topRaw = $todayBase()
                ->select('treatment', DB::raw('COUNT(*) as count'))
                ->groupBy('treatment')
                ->orderByDesc('count')
                ->first();

            $topServiceLabel = $topRaw ? $this->resolveTreatmentLabel($topRaw->treatment) : null;
        }

        // ── Alert metrics ─────────────────────────────────────────────────
        $lateAppointments = $noShows = $overbookedTherapists = null;

        if ($user->can('view dashboard alerts')) {
            $lateAppointments = $todayBase()
                ->where('status', 'pending')
                ->whereTime('start_time', '<', now()->format('H:i:s'))
                ->count();

            // Reuse already-computed value if available, otherwise query
            $noShows = $cancelledToday ?? $todayBase()->where('status', 'cancelled')->count();

            $therapistIds = User::role('therapist')
                ->whereHas('staff', fn($q) => $q
                    ->where('spa_id', $spaId)
                    ->where('branch_id', $currentBranchId)
                    ->where('employment_status', 'active')
                )->pluck('id');

            $overbookedTherapists = (int) $todayBase()
                ->whereIn('therapist_id', $therapistIds)
                ->whereNotIn('status', ['cancelled'])
                ->select('therapist_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('therapist_id')
                ->havingRaw('cnt > 8')
                ->get()
                ->count();
        }

        // ── Full branch appointment timeline ──────────────────────────────
        $todayAppointments = collect();
        $nextAppointment   = null;

        if ($user->can('view dashboard timeline')) {
            $todayAppointments = $todayBase()
                ->with('therapist')
                ->orderBy('start_time')
                ->get()
                ->map(fn($b) => $this->decorateBooking($b));

            $nextAppointment = $base()
                ->whereDate('appointment_date', '>', $today)
                ->whereIn('status', ['reserved', 'pending'])
                ->orderBy('appointment_date')
                ->orderBy('start_time')
                ->with('therapist')
                ->first();

            if ($nextAppointment) {
                $nextAppointment = $this->decorateBooking($nextAppointment);
            }
        }

        // ── Therapist workload panel ──────────────────────────────────────
        $therapists = collect();

        if ($user->can('view dashboard therapist status')) {
            $therapists = User::role('therapist')
                ->whereHas('staff', fn($q) => $q
                    ->where('spa_id', $spaId)
                    ->where('branch_id', $currentBranchId)
                    ->where('employment_status', 'active')
                )
                ->select(['id', 'first_name', 'last_name', 'email'])
                ->withCount([
                    'assignedBookings as total_today' => fn($q) => $q
                        ->where('spa_id', $spaId)->where('branch_id', $currentBranchId)
                        ->whereDate('appointment_date', $today)
                        ->whereNotIn('status', ['cancelled']),
                    'assignedBookings as ongoing_count' => fn($q) => $q
                        ->where('spa_id', $spaId)->where('branch_id', $currentBranchId)
                        ->whereDate('appointment_date', $today)
                        ->where('status', 'ongoing'),
                    'assignedBookings as completed_count' => fn($q) => $q
                        ->where('spa_id', $spaId)->where('branch_id', $currentBranchId)
                        ->whereDate('appointment_date', $today)
                        ->where('status', 'completed'),
                    'assignedBookings as remaining_count' => fn($q) => $q
                        ->where('spa_id', $spaId)->where('branch_id', $currentBranchId)
                        ->whereDate('appointment_date', $today)
                        ->whereIn('status', ['reserved', 'pending']),
                ])
                ->get();
        }

        // ── Therapist personal view ("My Today") ──────────────────────────
        // Only for users with view dashboard my today (therapist role by default)
        $myTodayAppointments = collect();
        $myStats             = null;
        $myNextAppointment   = null;

        if ($user->can('view dashboard my today')) {
            $myBase = fn() => Booking::query()
                ->where('spa_id', $spaId)
                ->where('therapist_id', $user->id);

            $myTodayAppointments = $myBase()
                ->whereDate('appointment_date', $today)
                ->orderBy('start_time')
                ->get()
                ->map(fn($b) => $this->decorateBooking($b));

            $myStats = [
                'total'     => $myBase()->whereDate('appointment_date', $today)->count(),
                'ongoing'   => $myBase()->whereDate('appointment_date', $today)->where('status', 'ongoing')->count(),
                'completed' => $myBase()->whereDate('appointment_date', $today)->where('status', 'completed')->count(),
                'remaining' => $myBase()->whereDate('appointment_date', $today)->whereIn('status', ['reserved', 'pending'])->count(),
            ];

            $myNextAppointment = $myBase()
                ->whereDate('appointment_date', '>', $today)
                ->whereIn('status', ['reserved', 'pending'])
                ->orderBy('appointment_date')
                ->orderBy('start_time')
                ->first();

            if ($myNextAppointment) {
                $myNextAppointment = $this->decorateBooking($myNextAppointment);
            }
        }

        return view('dashboard', compact(
            // KPI data
            'todayCount', 'ongoingToday', 'pendingToday', 'reservedToday',
            'completedToday', 'cancelledToday', 'upcomingWeek',
            // Revenue data
            'collectedToday', 'onlineToday', 'walkInToday', 'topServiceLabel',
            // Alert data
            'lateAppointments', 'noShows', 'overbookedTherapists',
            // Timeline data
            'todayAppointments', 'nextAppointment',
            // Therapist panel data
            'therapists',
            // Personal schedule data (therapist role)
            'myTodayAppointments', 'myStats', 'myNextAppointment',
        ));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function decorateBooking(Booking $b): Booking
    {
        $b->treatment_display = $this->resolveTreatmentLabel($b->treatment ?? '');
        return $b;
    }

    private function resolveTreatmentLabel(string $selection): string
    {
        if (str_starts_with($selection, 'treatment_')) {
            $t = Treatment::withoutGlobalScopes()->find((int) str_replace('treatment_', '', $selection));
            return $t?->name ?? 'Unknown Treatment';
        }
        if (str_starts_with($selection, 'package_')) {
            $p = Package::withoutGlobalScopes()->find((int) str_replace('package_', '', $selection));
            return $p ? $p->name . ' (Package)' : 'Unknown Package';
        }
        return $selection ?: '—';
    }
}