<?php

namespace App\Http\Controllers;

use App\Models\Rating;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RatingController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
            'feedback' => 'nullable|string|max:1000',
        ]);

        $booking = Booking::findOrFail($validated['booking_id']);

        // Verify booking belongs to customer
        if ($booking->customer_user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Verify booking is completed
        if ($booking->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'You can only rate completed appointments'
            ], 422);
        }

        // Check if already rated
        $existing = Rating::where('booking_id', $booking->id)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You have already rated this appointment'
            ], 422);
        }

        // Create rating
        $rating = Rating::create([
            'booking_id' => $booking->id,
            'therapist_id' => $booking->therapist_id,
            'customer_id' => Auth::id(),
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'feedback' => $validated['feedback'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rating submitted successfully',
            'rating' => $rating
        ], 201);
    }
}
