<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        
        $currentBranchId = session('current_branch_id');

        // If branch not set, show safe empty dashboard (prevents wrong/global data)
        if (! $currentBranchId) {
            return view('dashboard', [
                'total' => 0,
                'completed' => 0,
                'todayCount' => 0,
                'pending' => 0,
                'todayAppointments' => collect(),
                'topServiceToday' => null,
                'therapists' => collect(),
                'lateAppointments' => 0,
                'noShows' => 0,
                'overbookedSlots' => 0,
            ]);
        }

        // Base query: everything scoped to current branch
        $baseBookings = Booking::query()->where('branch_id', $currentBranchId);

        // Total appointments (per branch)
        $total = (clone $baseBookings)->count();

        // Completed appointments (per branch)
        $completed = (clone $baseBookings)->where('status', 'completed')->count();

        // Today's appointments count (per branch)
        $todayCount = (clone $baseBookings)->whereDate('appointment_date', today())->count();

        // Pending appointments (per branch)
        $pending = (clone $baseBookings)->whereIn('status', ['pending', 'reserved'])->count();

        // Today's appointments list (per branch)
        $todayAppointments = (clone $baseBookings)
            ->whereDate('appointment_date', today())
            ->orderBy('start_time')
            ->with('therapist') // Booking::therapist should be belongsTo(User::class, 'therapist_id')
            ->get();

        // Top service today (per branch)
        $topServiceToday = (clone $baseBookings)
            ->whereDate('appointment_date', today())
            ->selectRaw('service_type, COUNT(*) as count')
            ->groupBy('service_type')
            ->orderByDesc('count')
            ->first();

        /**
         * ✅ THERAPIST AVAILABILITY (PER BRANCH)
         * We fetch from USERS because bookings.therapist_id references users.id.
         * We also ensure the therapist belongs to this branch using staff.branch_id.
         */
        $therapists = User::query()
            ->role('therapist') // Spatie role
            ->whereHas('staff', function ($q) use ($currentBranchId) {
                $q->where('branch_id', $currentBranchId)
                  ->where('employment_status', 'active');
            })
            ->select(['id', 'name', 'email'])
            ->withCount([
                'assignedBookings as assigned_bookings_count' => function ($q) use ($currentBranchId) {
                    $q->where('branch_id', $currentBranchId)
                      ->whereDate('appointment_date', today())
                      // include statuses you consider "counting for today"
                      ->whereIn('status', ['reserved', 'confirmed']);
                }
            ])
            ->get();

        // Late appointments (per branch)
        $lateAppointments = (clone $baseBookings)
            ->whereDate('appointment_date', today())
            ->where('status', 'confirmed')
            ->whereTime('start_time', '<', now())
            ->whereTime('end_time', '>', now())
            ->count();

        // No shows (per branch)
        $noShows = (clone $baseBookings)
            ->whereDate('appointment_date', today())
            ->where('status', 'cancelled')
            ->count();

        // Overbooked therapists (rule: more than 8 today)
        $overbookedSlots = $therapists->filter(function ($therapist) {
            return ($therapist->assigned_bookings_count ?? 0) > 8;
        })->count();

        return view('dashboard', compact(
            'total',
            'completed',
            'todayCount',
            'pending',
            'todayAppointments',
            'topServiceToday',
            'therapists',
            'lateAppointments',
            'noShows',
            'overbookedSlots'
        ));
    }
}
