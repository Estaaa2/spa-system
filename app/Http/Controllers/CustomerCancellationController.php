<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\Bookings\CancellationNotAllowed;
use App\Services\Bookings\CancellationNotifier;
use App\Services\Bookings\CustomerCancellation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer cancels their own online booking (customer cancellation, Nov 2026).
 * The rule and the money live in CustomerCancellation; this only checks who
 * is asking and turns the result into a message.
 */
class CustomerCancellationController extends Controller
{
    public function store(Request $request, Booking $booking, CustomerCancellation $rule, CancellationNotifier $notifier): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Someone else's booking: answer as if it doesn't exist.
        abort_unless((int) $booking->customer_user_id === (int) $request->user()->id, 404);

        try {
            $quote = $rule->cancel($booking, $request->user(), $data['reason'] ?? null);
        } catch (CancellationNotAllowed $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $notifier->cancelled($booking->fresh(), 'customer');

        return response()->json([
            'message'       => $this->message($quote),
            'quote'         => $quote,
            // So the booking form can say "free cancellation used" without a reload.
            'free_again_on' => $rule->freeUsedUntil((int) $request->user()->id)?->toIso8601String(),
        ]);
    }

    private function message(array $quote): string
    {
        $peso = fn (float $v) => '₱' . number_format($v, 2);

        if ($quote['refund'] > 0 && $quote['kept'] > 0) {
            return "Booking cancelled. {$peso($quote['refund'])} will be refunded to you; the {$peso($quote['kept'])} reservation fee is kept.";
        }

        if ($quote['refund'] > 0) {
            return "Booking cancelled. {$peso($quote['refund'])} will be refunded to you.";
        }

        return "Booking cancelled. The {$peso($quote['kept'])} reservation fee is kept.";
    }
}
