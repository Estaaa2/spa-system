<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Staff;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // Total appointments
        $total = Booking::count();

        // Completed appointments
        $completed = Booking::where('status', 'completed')->count();

        // Today's appointments count
        $todayCount = Booking::whereDate('appointment_date', today())->count();

        // Pending appointments
        $pending = Booking::where('status', 'pending')->count();

        // Today's appointments
        $todayAppointments = Booking::whereDate('appointment_date', today())
            ->orderBy('appointment_time')
            ->with('therapist')
            ->get();

        // Top service today (without DB facade)
        $topServiceToday = Booking::whereDate('appointment_date', today())
            ->selectRaw('service_type, COUNT(*) as count')
            ->groupBy('service_type')
            ->orderByDesc('count')
            ->first();

        // Get therapists from Staff model
        $therapists = Staff::where('roles', 'therapist')
            ->where('status', 'active')
            ->withCount(['bookings as assigned_bookings_count' => function($query) {
                $query->whereDate('appointment_date', today())
                      ->whereIn('status', ['reserved', 'confirmed']);
            }])
            ->get();

        // Late appointments
        $lateAppointments = Booking::whereDate('appointment_date', today())
            ->where('status', 'confirmed')
            ->whereTime('appointment_time', '<', now()->format('H:i:s'))
            ->count();

        // No shows
        $noShows = Booking::whereDate('appointment_date', today())
            ->where('status', 'cancelled')
            ->count();

        // Overbooked therapists
        $overbookedSlots = $therapists->filter(function($therapist) {
            return $therapist->assigned_bookings_count > 8;
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
