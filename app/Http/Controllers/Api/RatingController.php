<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RatingController extends Controller
{
    public function show($bookingId)
    {
        // Only the customer who made the booking may read its rating.
        $booking = Booking::find($bookingId);

        if (! $booking || $booking->customer_user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $rating = Rating::where('booking_id', $bookingId)->first();

        if (!$rating) {
            return response()->json([
                'success' => true,
                'has_rating' => false,
                'rating' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'has_rating' => true,
            'rating' => [
                'value' => $rating->rating,
                'comment' => $rating->comment,
                'feedback' => $rating->feedback,
                'created_at' => $rating->created_at
            ]
        ]);
    }
}
