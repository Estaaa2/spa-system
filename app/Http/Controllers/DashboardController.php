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

        // ── Decide which data blocks to load based on branch permissions ──
        // Uses hasBranchPermission() so branch-level overrides are respected.
        $needsKpis = $user->hasBranchPermission('view dashboard kpis')
                  || $user->hasBranchPermission('view dashboard revenue')
                  || $user->hasBranchPermission('view dashboard alerts');

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

        if ($user->hasBranchPermission('view dashboard revenue')) {
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

        if ($user->hasBranchPermission('view dashboard alerts')) {
            $lateAppointments = $todayBase()
                ->where('status', 'pending')
                ->whereTime('start_time', '<', now()->format('H:i:s'))
                ->count();

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

        if ($user->hasBranchPermission('view dashboard timeline')) {
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

        if ($user->hasBranchPermission('view dashboard therapist status')) {
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
        $myTodayAppointments = collect();
        $myStats             = null;
        $myNextAppointment   = null;

        if ($user->hasBranchPermission('view dashboard my today')) {
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
            'todayCount', 'ongoingToday', 'pendingToday', 'reservedToday',
            'completedToday', 'cancelledToday', 'upcomingWeek',
            'collectedToday', 'onlineToday', 'walkInToday', 'topServiceLabel',
            'lateAppointments', 'noShows', 'overbookedTherapists',
            'todayAppointments', 'nextAppointment',
            'therapists',
            'myTodayAppointments', 'myStats', 'myNextAppointment',
        ));
    }

    // ── Live polling endpoint ─────────────────────────────────────────────────
    // Called every 60 seconds by the dashboard JS polling loop.
    // Only returns the data blocks the current user has permission to see.

    public function liveData()
    {
        $user            = Auth::user();
        $currentBranchId = $user->currentBranchId();

        if (!$currentBranchId) {
            return response()->json(['error' => 'No branch selected.'], 422);
        }

        $spaId = $user->spa_id;
        $today = now()->toDateString();

        $base      = fn() => Booking::query()->where('spa_id', $spaId)->where('branch_id', $currentBranchId);
        $todayBase = fn() => $base()->whereDate('appointment_date', $today);

        $payload = ['server_time' => now()->toIso8601String()];

        // ── KPIs ─────────────────────────────────────────────────────────
        if ($user->hasBranchPermission('view dashboard kpis')
            || $user->hasBranchPermission('view dashboard revenue')
            || $user->hasBranchPermission('view dashboard alerts')) {

            $payload['kpis'] = [
                'today_count'    => $todayBase()->count(),
                'ongoing_today'  => $todayBase()->where('status', 'ongoing')->count(),
                'pending_today'  => $todayBase()->where('status', 'pending')->count(),
                'reserved_today' => $todayBase()->where('status', 'reserved')->count(),
                'completed_today'=> $todayBase()->where('status', 'completed')->count(),
                'cancelled_today'=> $todayBase()->where('status', 'cancelled')->count(),
                'upcoming_week'  => $base()
                    ->whereDate('appointment_date', '>', $today)
                    ->whereDate('appointment_date', '<=', now()->addDays(7)->toDateString())
                    ->whereIn('status', ['reserved', 'pending'])
                    ->count(),
            ];
        }

        // ── Revenue ───────────────────────────────────────────────────────
        if ($user->hasBranchPermission('view dashboard revenue')) {
            $topRaw = $todayBase()
                ->select('treatment', DB::raw('COUNT(*) as count'))
                ->groupBy('treatment')
                ->orderByDesc('count')
                ->first();

            $payload['revenue'] = [
                'collected_today'   => (float) $todayBase()->whereIn('status', ['ongoing', 'completed'])->sum('amount_paid'),
                'online_today'      => $todayBase()->where('booking_source', 'online')->count(),
                'walk_in_today'     => $todayBase()->where(fn($q) => $q->where('booking_source', '!=', 'online')->orWhereNull('booking_source'))->count(),
                'top_service_label' => $topRaw ? $this->resolveTreatmentLabel($topRaw->treatment) : null,
            ];
        }

        // ── Alerts ────────────────────────────────────────────────────────
        if ($user->hasBranchPermission('view dashboard alerts')) {
            $therapistIds = User::role('therapist')
                ->whereHas('staff', fn($q) => $q
                    ->where('spa_id', $spaId)
                    ->where('branch_id', $currentBranchId)
                    ->where('employment_status', 'active')
                )->pluck('id');

            $payload['alerts'] = [
                'late_appointments'    => $todayBase()->where('status', 'pending')->whereTime('start_time', '<', now()->format('H:i:s'))->count(),
                'no_shows'             => $todayBase()->where('status', 'cancelled')->count(),
                'overbooked_therapists'=> (int) $todayBase()
                    ->whereIn('therapist_id', $therapistIds)
                    ->whereNotIn('status', ['cancelled'])
                    ->select('therapist_id', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('therapist_id')
                    ->havingRaw('cnt > 8')
                    ->get()
                    ->count(),
            ];
        }

        // ── Timeline ──────────────────────────────────────────────────────
        if ($user->hasBranchPermission('view dashboard timeline')) {
            $todayAppointments = $todayBase()
                ->with('therapist')
                ->orderBy('start_time')
                ->get()
                ->map(fn($b) => $this->formatForLive($b));

            $next = $base()
                ->whereDate('appointment_date', '>', $today)
                ->whereIn('status', ['reserved', 'pending'])
                ->orderBy('appointment_date')
                ->orderBy('start_time')
                ->with('therapist')
                ->first();

            $payload['timeline'] = [
                'appointments'    => $todayAppointments,
                'next_appointment'=> $next ? $this->formatForLive($this->decorateBooking($next)) : null,
            ];
        }

        // ── Therapist status panel ────────────────────────────────────────
        if ($user->hasBranchPermission('view dashboard therapist status')) {
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
                        ->whereDate('appointment_date', $today)->whereNotIn('status', ['cancelled']),
                    'assignedBookings as ongoing_count' => fn($q) => $q
                        ->where('spa_id', $spaId)->where('branch_id', $currentBranchId)
                        ->whereDate('appointment_date', $today)->where('status', 'ongoing'),
                    'assignedBookings as completed_count' => fn($q) => $q
                        ->where('spa_id', $spaId)->where('branch_id', $currentBranchId)
                        ->whereDate('appointment_date', $today)->where('status', 'completed'),
                    'assignedBookings as remaining_count' => fn($q) => $q
                        ->where('spa_id', $spaId)->where('branch_id', $currentBranchId)
                        ->whereDate('appointment_date', $today)->whereIn('status', ['reserved', 'pending']),
                ])
                ->get()
                ->map(fn($t) => [
                    'id'              => $t->id,
                    'first_name'      => $t->first_name,
                    'last_name'       => $t->last_name,
                    'email'           => $t->email,
                    'total_today'     => (int) $t->total_today,
                    'ongoing_count'   => (int) $t->ongoing_count,
                    'completed_count' => (int) $t->completed_count,
                    'remaining_count' => (int) $t->remaining_count,
                ]);

            $payload['therapists'] = $therapists;
        }

        // ── My Today (therapist personal view) ────────────────────────────
        if ($user->hasBranchPermission('view dashboard my today')) {
            $myBase = fn() => Booking::query()
                ->where('spa_id', $spaId)
                ->where('therapist_id', $user->id);

            $myAppointments = $myBase()
                ->whereDate('appointment_date', $today)
                ->orderBy('start_time')
                ->get()
                ->map(fn($b) => $this->formatForLive($this->decorateBooking($b)));

            $myNext = $myBase()
                ->whereDate('appointment_date', '>', $today)
                ->whereIn('status', ['reserved', 'pending'])
                ->orderBy('appointment_date')
                ->orderBy('start_time')
                ->first();

            $payload['my_today'] = [
                'appointments'    => $myAppointments,
                'next_appointment'=> $myNext ? $this->formatForLive($this->decorateBooking($myNext)) : null,
                'stats' => [
                    'total'     => $myBase()->whereDate('appointment_date', $today)->count(),
                    'ongoing'   => $myBase()->whereDate('appointment_date', $today)->where('status', 'ongoing')->count(),
                    'completed' => $myBase()->whereDate('appointment_date', $today)->where('status', 'completed')->count(),
                    'remaining' => $myBase()->whereDate('appointment_date', $today)->whereIn('status', ['reserved', 'pending'])->count(),
                ],
            ];
        }

        return response()->json($payload);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function decorateBooking(Booking $b): Booking
    {
        $b->treatment_display = $this->resolveTreatmentLabel($b->treatment ?? '');
        return $b;
    }

    private function formatForLive(Booking $b): array
    {
        return [
            'id'              => $b->id,
            'customer_name'   => $b->customer_name    ?? 'Walk-in Customer',
            'customer_phone'  => $b->customer_phone   ?? '',
            'customer_address'=> $b->customer_address ?? '',
            'treatment_display'=> $b->treatment_display ?? '—',
            'service_type'    => $b->service_type     ?? '',
            'booking_source'  => $b->booking_source   ?? '',
            'status'          => $b->status,
            'start_time_fmt'  => $b->start_time ? \Carbon\Carbon::parse($b->start_time)->format('h:i') : '—',
            'start_ampm'      => $b->start_time ? \Carbon\Carbon::parse($b->start_time)->format('A') : '',
            'end_time_fmt'    => $b->end_time   ? \Carbon\Carbon::parse($b->end_time)->format('h:i A') : '—',
            'therapist_name'  => $b->therapist
                ? trim($b->therapist->first_name . ' ' . $b->therapist->last_name)
                : 'Unassigned',
            'appointment_date_fmt' => $b->appointment_date
                ? \Carbon\Carbon::parse($b->appointment_date)->format('D, M j')
                : '—',
            'appointment_date_at'  => $b->start_time
                ? \Carbon\Carbon::parse($b->start_time)->format('h:i A')
                : '—',
        ];
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