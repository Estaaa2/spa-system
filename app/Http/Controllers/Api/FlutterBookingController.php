<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\OnlineReservationPayment;
use App\Models\OperatingHours;
use App\Models\Package;
use App\Models\Treatment;
use App\Models\User;
use App\Models\Spa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterBookingController extends Controller
{
    // createBooking() removed (staff scheduling Phase 3, Q7 A): it had no route, and its own
    // availability check ignored leave and work schedules. Mobile checkout uses
    // POST /api/bookings/online/checkout (OnlineBookingCheckoutController::store).

    /**
     * PayMongo redirects here after payment.
     * Webhook is the primary booking creator.
     * This is the fallback in case webhook fires after redirect.
     */
    public function paymentSuccess(Request $request)
    {
        $reservationId = $request->query('reservation');
        $flutterWebUrl = config('services.flutter.web_url');

        if (!$reservationId) {
            return redirect($flutterWebUrl . '/#/payment-cancel');
        }

        $reservation = OnlineReservationPayment::find($reservationId);

        if (!$reservation) {
            return redirect($flutterWebUrl . '/#/payment-cancel?error=not_found');
        }

        // Only act if webhook hasn't already processed this
        if ($reservation->payment_status !== 'paid' || !$reservation->booking_id) {

            // Verify with PayMongo
            if ($reservation->paymongo_checkout_session_id) {
                $secretKey      = \App\Services\PayMongo::secretKey();
                $verifyResponse = Http::withBasicAuth($secretKey, '')
                    ->acceptJson()
                    ->get('https://api.paymongo.com/v1/checkout_sessions/' . $reservation->paymongo_checkout_session_id);

                if ($verifyResponse->successful()) {
                    $sessionStatus = data_get($verifyResponse->json(), 'data.attributes.status');
                    $paymentIntent = data_get($verifyResponse->json(), 'data.attributes.payment_intent');
                    $paymentStatus = data_get($paymentIntent, 'attributes.status');

                    if ($sessionStatus === 'completed' || $paymentStatus === 'succeeded') {
                        // ✅ Fallback: create booking if webhook hasn't done it yet
                        if (!$reservation->booking_id) {
                            try {
                                // Same confirmation email as the webhook sends; only when
                                // this request made the booking (not the webhook).
                                if ($created = $this->createBookingFromReservation($reservation)) {
                                    app(\App\Services\Bookings\BookingNotifier::class)->confirmed($created);
                                }
                            } catch (\Throwable $e) {
                                Log::error('Fallback booking creation failed in paymentSuccess', [
                                    'reservation_id' => $reservation->id,
                                    'message'        => $e->getMessage(),
                                ]);
                            }
                        } else {
                            // Webhook already created booking, just mark as paid
                            $reservation->update([
                                'payment_status'     => 'paid',
                                'reservation_status' => 'reserved',
                            ]);
                        }
                    }
                }
            }
        }

        return redirect($flutterWebUrl . '/#/payment-success?reservation=' . $reservationId);
    }

    public function paymentCancel(Request $request)
    {
        $reservationId = $request->query('reservation');
        $flutterWebUrl = config('services.flutter.web_url');

        if ($reservationId) {
            OnlineReservationPayment::where('id', $reservationId)
                ->where('payment_status', 'pending')
                ->update([
                    'reservation_status' => 'cancelled',
                    'payment_status'     => 'cancelled',
                ]);
        }

        return redirect($flutterWebUrl . '/#/payment-cancel?reservation=' . $reservationId);
    }

        /**
     * GET /api/bookings/online/status?reservation_id=
     * Same answer as checkBookingStatus(), but only for the logged-in
     * customer's own reservation.
     */
    public function reservationStatus(Request $request)
    {
        $request->validate(['reservation_id' => ['required', 'integer']]);

        $reservation = OnlineReservationPayment::where('id', $request->reservation_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $reservation) {
            return response()->json([
                'success' => false,
                'message' => 'Reservation not found.',
            ], 404);
        }

        return response()->json([
            'success'            => true,
            'payment_status'     => $reservation->payment_status,
            'reservation_status' => $reservation->reservation_status,
            'payment_option'     => $reservation->payment_option ?? 'downpayment',
            'amount_charged'     => (float) ($reservation->amount_charged ?? $reservation->downpayment_amount),
            'booking_id'         => $reservation->booking_id,
            'booking_created'    => ! is_null($reservation->booking_id),
        ]);
    }

    /**
     * GET /api/bookings/online/my-dates
     * Dates within the 30-day booking window on which the customer already
     * has an active booking. The app marks them with a dot on the calendar.
     */
    public function myBookedDates(Request $request)
    {
        $dates = Booking::withoutGlobalScopes()
            ->where('customer_user_id', $request->user()->id)
            ->whereIn('status', ['reserved', 'pending', 'ongoing'])
            ->whereBetween('appointment_date', [
                today()->toDateString(),
                today()->addDays(30)->toDateString(),
            ])
            ->pluck('appointment_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values();

        return response()->json(['dates' => $dates]);
    }

    public function cancelReservation(Request $request)
    {
        $request->validate(['reservation_id' => ['required', 'integer']]);

        $updated = OnlineReservationPayment::where('id', $request->reservation_id)
            ->where('user_id', $request->user()->id)
            ->where('payment_status', 'pending')
            ->update([
                'reservation_status' => 'cancelled',
                'payment_status'     => 'cancelled',
            ]);

        return response()->json([
            'success'   => true,
            'cancelled' => $updated > 0,
        ]);
    }

    public function checkBookingStatus(Request $request)
    {
        $request->validate([
            'reservation_id' => 'required|exists:online_reservation_payments,id',
        ]);

        $reservation = OnlineReservationPayment::find($request->reservation_id);

        return response()->json([
            'success'            => true,
            'payment_status'     => $reservation->payment_status,
            'reservation_status' => $reservation->reservation_status,
            'booking_id'         => $reservation->booking_id,
            'booking_created'    => !is_null($reservation->booking_id),
        ]);
    }

    /**
     * Creates a real Booking from a confirmed OnlineReservationPayment.
     * Mirrors exactly what PaymongoWebhookController does.
     * ✅ treatment field = "treatment_ID" or "package_ID" format
     * ✅ reservation_status uses enum: 'reserved' (not 'confirmed')
     * ✅ end_time recalculated from duration (no end_time on reservation table)
     */
    private function createBookingFromReservation(OnlineReservationPayment $reservation): ?Booking
    {
        return DB::transaction(function () use ($reservation) {
            // The webhook may have created the booking while PayMongo was being
            // checked: re-read the row under a lock and stop if it did.
            $reservation = OnlineReservationPayment::whereKey($reservation->id)->lockForUpdate()->first();
            if (! $reservation || $reservation->booking_id) {
                return null;
            }

            // Recalculate end_time from duration since it's not stored on reservation
            $durationMinutes = 60;

            if ($reservation->bookable_type === 'treatment') {
                $treatment       = Treatment::withoutGlobalScopes()->find($reservation->bookable_id);
                $durationMinutes = $treatment?->duration ?? 60;
            } elseif ($reservation->bookable_type === 'package') {
                $package         = Package::withoutGlobalScopes()->find($reservation->bookable_id);
                $durationMinutes = $package?->duration ?? $package?->total_duration ?? 60;
            }

            $endTime = Carbon::parse($reservation->start_time)
                ->addMinutes($durationMinutes)
                ->format('H:i:s');

            // ✅ treatment column must be "treatment_5" or "package_3" format
            $treatmentCode = $reservation->bookable_type . '_' . $reservation->bookable_id;

            // Shared rule with the webhook (TherapistAssigner): least busy, leave
            // and Schedule availability respected, verified only for home service.
            // Nobody free: booked without a therapist for the branch to assign (Q17).
            $assignedTherapist = app(\App\Services\Bookings\TherapistAssigner::class)->pick(
                (int) $reservation->spa_id,
                (int) $reservation->branch_id,
                Carbon::parse($reservation->appointment_date)->toDateString(),
                Carbon::parse($reservation->start_time)->format('H:i'),
                Carbon::parse($endTime)->format('H:i'),
                \App\Services\Bookings\TherapistAssigner::requiresVerified('online', $reservation->service_type)
            );

            $totalAmount = (float) $reservation->full_amount;
            $amountPaid  = (float) ($reservation->amount_charged ?? $reservation->downpayment_amount);
            $balance     = max(round($totalAmount - $amountPaid, 2), 0);

            $booking = Booking::create([
                'spa_id'             => $reservation->spa_id,
                'branch_id'          => $reservation->branch_id,
                'customer_user_id'   => $reservation->user_id,      // ✅ correct column
                'created_by_user_id' => null,
                'booking_source'     => 'online',
                'status'             => 'reserved',
                // Same amounts as the webhook (Q40): what PayMongo charged, so a
                // full payment is recorded as paid, not as a downpayment.
                'payment_status'     => $balance <= 0 ? 'paid' : 'partially_paid',
                'amount_paid'        => $amountPaid,
                'total_amount'       => $totalAmount,
                'balance_amount'     => $balance,
                'service_type'       => $reservation->service_type,
                'treatment'          => $treatmentCode,              // ✅ "treatment_5" format
                'customer_name'      => $reservation->customer_name,
                'customer_email'     => $reservation->customer_email,
                'customer_phone'     => $reservation->customer_phone,
                'customer_address'   => $reservation->customer_address,
                'appointment_date'   => $reservation->appointment_date,
                'start_time'         => $reservation->start_time,
                'end_time'           => $endTime,                    // ✅ recalculated
                'therapist_id'       => $assignedTherapist?->id,
            ]);

            $reservation->update([
                'payment_status'     => 'paid',
                'reservation_status' => 'reserved',    // ✅ matches enum (not 'confirmed')
                'paid_at'            => now(),
                'booking_id'         => $booking->id,
            ]);

            return $booking;
        });
    }
}
