<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Bookings\ArrivalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/appointments/{booking}/arrival — the assigned therapist enters the
 * customer's 4-digit arrival code from the app. Same rules as the web
 * (App\Services\Bookings\ArrivalService): assigned therapist only, on the day
 * only, 5 wrong codes lock it. Confirming without a code stays on the web
 * (branch staff with "edit appointments").
 *
 * 422 errors.arrival_code: wrong code (says how many tries are left), locked,
 * not today, already confirmed, not the assigned therapist.
 */
class ArrivalController extends Controller
{
    public function __construct(private ArrivalService $arrivals)
    {
    }

    public function store(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'arrival_code' => ['required', 'string', 'max:10'],
        ], [
            'arrival_code.required' => 'Enter the 4-digit code the customer tells you.',
        ]);

        $user = $request->user();

        // Another spa's booking: don't reveal that it exists.
        abort_unless((int) $booking->spa_id === (int) $user->spa_id, 404);

        $booking = $this->arrivals->confirmWithCode($booking, $user, $validated['arrival_code']);

        return response()->json([
            'message' => 'Arrival confirmed for '.($booking->customer_name ?: 'the customer').'.',
            'arrival' => $this->arrivals->stateFor($booking, $user),
        ]);
    }
}
