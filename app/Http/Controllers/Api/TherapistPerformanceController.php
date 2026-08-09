<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        // Get completed bookings
        $completedBookings = Booking::where('therapist_id', $therapistId)
            ->where('status', 'completed')
            ->whereNotNull('customer_user_id')
            ->with('rating')
            ->orderBy('appointment_date', 'desc')
            ->get()
            ->map(fn($b) => [
                'id' => $b->id,
                'date' => $b->appointment_date->format('Y-m-d'),
                'start_time' => $b->start_time,
                'end_time' => $b->end_time,
                'customer_name' => $b->customer_name,
                'treatment' => $b->treatment_label,
                'rating' => $b->rating?->rating,
            ]);

        // Calculate average rating
        $averageRating = Rating::where('therapist_id', $therapistId)->avg('rating') ?? 0;

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
        $completedServices = Booking::where('therapist_id', $therapistId)
            ->where('status', 'completed')
            ->count();

        // Determine satisfaction level
        if ($averageRating >= 4.5) {
            $satisfactionLevel = 'Excellent';
        } elseif ($averageRating >= 3.5) {
            $satisfactionLevel = 'Good';
        } elseif ($averageRating > 0) {
            $satisfactionLevel = 'Needs Improvement';
        } else {
            $satisfactionLevel = 'No ratings yet';
        }

        // Get monthly performance data
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
            ->with(['customer'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'comment' => $r->comment,
                'feedback' => $r->feedback,
                'customer_name' => $r->customer?->name ?? 'Anonymous',
                'created_at' => $r->created_at->toISOString(),
            ]);

        return response()->json([
            'average_rating' => round($averageRating, 1),
            'total_ratings' => $totalRatings,
            'completed_services' => $completedServices,
            'satisfaction_level' => $satisfactionLevel,
            'rating_distribution' => $ratingDistribution,
            'monthly_data' => $monthlyData,
            'recent_feedback' => $recentFeedback,
            'completed_bookings' => $completedBookings,
        ]);
    }
}