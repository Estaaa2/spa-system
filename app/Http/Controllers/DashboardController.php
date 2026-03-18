<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->hasRole('therapist')) {
            return redirect()->route('appointments.index');
        }

        $currentBranchId = $user->currentBranchId();

        if (!$currentBranchId) {
            // Only redirect to setup if setup is NOT complete
            if (!$user->spa || !$user->spa->is_setup_complete) {
                return redirect()->route('setup.index');
            }

            // Setup is complete but no branch in session → redirect to branch switcher
            return redirect()->route('branches.index')
                ->with('warning', 'Please select a branch to continue.');
        }

        $spaId = $user->spa_id;

        $baseBookings = Booking::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $currentBranchId);

        $total = (clone $baseBookings)->count();

        $completed = (clone $baseBookings)
            ->where('status', 'completed')
            ->count();

        $todayCount = (clone $baseBookings)
            ->whereDate('appointment_date', today())
            ->count();

        $pending = (clone $baseBookings)
            ->whereIn('status', ['pending', 'reserved'])
            ->count();

        $todayAppointments = (clone $baseBookings)
            ->whereDate('appointment_date', today())
            ->orderBy('start_time')
            ->with('therapist')
            ->get();

        $topServiceToday = (clone $baseBookings)
            ->whereDate('appointment_date', today())
            ->selectRaw('service_type, COUNT(*) as count')
            ->groupBy('service_type')
            ->orderByDesc('count')
            ->first();

        $therapists = User::query()
            ->role('therapist')
            ->whereHas('staff', function ($q) use ($currentBranchId, $spaId) {
                $q->where('spa_id', $spaId)
                ->where('branch_id', $currentBranchId)
                ->where('employment_status', 'active');
            })
            ->select(['id', 'name', 'email'])
            ->withCount([
                'assignedBookings as assigned_bookings_count' => function ($q) use ($currentBranchId, $spaId) {
                    $q->where('spa_id', $spaId)
                    ->where('branch_id', $currentBranchId)
                    ->whereDate('appointment_date', today())
                    ->whereIn('status', ['reserved', 'confirmed']);
                }
            ])
            ->get();

        $lateAppointments = (clone $baseBookings)
            ->whereDate('appointment_date', today())
            ->where('status', 'confirmed')
            ->whereTime('start_time', '<', now()->format('H:i:s'))
            ->whereTime('end_time', '>', now()->format('H:i:s'))
            ->count();

        $noShows = (clone $baseBookings)
            ->whereDate('appointment_date', today())
            ->where('status', 'cancelled')
            ->count();

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
