<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Rating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TherapistPerformanceController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $therapistId = $user->id;

        // Use 'rating' (singular) - this will work now
        $completedBookings = Booking::where('therapist_id', $therapistId)
            ->where('status', 'completed')
            ->whereNotNull('customer_user_id')
            ->with('rating')  // This matches your relationship name
            ->orderBy('appointment_date', 'desc')
            ->paginate(10);

        // Calculate average rating
        $averageRating = Rating::where('therapist_id', $therapistId)
            ->avg('rating') ?? 0;

        // Get rating distribution
        $ratingDistribution = [
            5 => Rating::where('therapist_id', $therapistId)->where('rating', 5)->count(),
            4 => Rating::where('therapist_id', $therapistId)->where('rating', 4)->count(),
            3 => Rating::where('therapist_id', $therapistId)->where('rating', 3)->count(),
            2 => Rating::where('therapist_id', $therapistId)->where('rating', 2)->count(),
            1 => Rating::where('therapist_id', $therapistId)->where('rating', 1)->count(),
        ];

        // Get total ratings count
        $totalRatings = Rating::where('therapist_id', $therapistId)->count();

        // Get monthly performance data for chart
        $monthlyData = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            $monthlyRatings = Rating::where('therapist_id', $therapistId)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->avg('rating') ?? 0;

            $monthlyData[] = [
                'month' => $month->format('M Y'),
                'rating' => round($monthlyRatings, 1),
                'bookings' => Booking::where('therapist_id', $therapistId)
                    ->whereBetween('appointment_date', [$monthStart, $monthEnd])
                    ->where('status', 'completed')
                    ->count(),
            ];
        }

        // Get recent feedback
        $recentFeedback = Rating::where('therapist_id', $therapistId)
            ->with(['booking', 'customer'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('therapist.performance', compact(
            'completedBookings',
            'averageRating',
            'ratingDistribution',
            'totalRatings',
            'monthlyData',
            'recentFeedback'
        ));
    }
}
