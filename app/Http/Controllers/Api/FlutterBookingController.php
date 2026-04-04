<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\OnlineReservationPayment;
use App\Models\OperatingHours;
use App\Models\Package;
use App\Models\Treatment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterBookingController extends Controller
{
    public function createBooking(Request $request)
    {
        $validated = $request->validate([
            'spa_id'             => 'required|exists:spas,id',
            'branch_id'          => 'required|exists:branches,id',
            'customer_name'      => 'required|string|max:255',
            'customer_email'     => 'required|email|max:255',
            'customer_phone'     => 'required|string|max:30',
            'treatment'          => 'required|string',
            'service_type'       => 'required|in:In-Branch,Home Service',
            'customer_address'   => 'nullable|string|max:1000',
            'appointment_date'   => 'required|date|after_or_equal:today',
            'start_time'         => 'required|string',
            'end_time'           => 'nullable|string',
            'total_amount'       => 'required|numeric',
            'downpayment_amount' => 'required|numeric',
        ]);

        $serviceTypeDb = $validated['service_type'] === 'In-Branch' ? 'in_branch' : 'in_home';

        // Validate operating hours
        $dayOfWeek = Carbon::parse($validated['appointment_date'])->format('l');
        $hours = OperatingHours::where('branch_id', $validated['branch_id'])
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (!$hours || $hours->is_closed) {
            return response()->json([
                'success' => false,
                'message' => 'The spa is closed on the selected day.'
            ], 422);
        }

        // Resolve treatment or package
        // ✅ Must store as "treatment_ID" or "package_ID" to match Booking model format
        $treatmentName   = $validated['treatment'];
        $bookableType    = 'treatment';
        $bookableId      = null;
        $durationMinutes = 60;

        $treatment = Treatment::withoutGlobalScopes()
            ->where('name', $treatmentName)
            ->where('spa_id', $validated['spa_id'])
            ->where('branch_id', $validated['branch_id'])
            ->first();

        if ($treatment) {
            $bookableId      = $treatment->id;
            $durationMinutes = $treatment->duration ?? 60;
        } else {
            $package = Package::withoutGlobalScopes()
                ->where('name', $treatmentName)
                ->where('spa_id', $validated['spa_id'])
                ->where('branch_id', $validated['branch_id'])
                ->first();

            if ($package) {
                $bookableId      = $package->id;
                $bookableType    = 'package';
                $durationMinutes = $package->duration ?? $package->total_duration ?? 60;
            }
        }

        // Check therapist availability
        $therapists = User::role('therapist')
            ->whereHas('staff', function ($q) use ($validated) {
                $q->where('spa_id', $validated['spa_id'])
                  ->where('branch_id', $validated['branch_id'])
                  ->where('employment_status', 'active');
            })
            ->get();

        $endTime = Carbon::parse($validated['start_time'])
            ->addMinutes($durationMinutes)
            ->format('H:i:s');

        if ($therapists->isNotEmpty()) {
            $busyIds = Booking::query()
                ->where('spa_id', $validated['spa_id'])
                ->where('branch_id', $validated['branch_id'])
                ->where('appointment_date', $validated['appointment_date'])
                ->whereIn('therapist_id', $therapists->pluck('id'))
                ->whereIn('status', ['reserved', 'pending', 'ongoing'])
                ->where(function ($q) use ($validated, $endTime) {
                    $q->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $validated['start_time']);
                })
                ->pluck('therapist_id')
                ->unique();

            $available = $therapists->reject(fn($t) => $busyIds->contains($t->id));

            if ($available->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No therapists available for the selected date and time.'
                ], 422);
            }
        }

        // Get or create user
        $user = Auth::user() ?? User::firstOrCreate(
            ['email' => $validated['customer_email']],
            [
                'first_name' => $validated['customer_name'],
                'last_name'  => '',
                'password'   => bcrypt(uniqid()),
            ]
        );

        // Create pending reservation
        // ✅ No end_time stored here — it's recalculated during booking creation
        $pending = DB::transaction(fn () => OnlineReservationPayment::create([
            'user_id'            => $user->id,
            'spa_id'             => $validated['spa_id'],
            'branch_id'          => $validated['branch_id'],
            'customer_name'      => $validated['customer_name'],
            'customer_email'     => $validated['customer_email'],
            'customer_phone'     => $validated['customer_phone'],
            'customer_address'   => $validated['customer_address'] ?? null,
            'bookable_id'        => $bookableId,
            'bookable_type'      => $bookableType,        // "treatment" or "package"
            'bookable_name'      => $treatmentName,
            'full_amount'        => $validated['total_amount'],
            'downpayment_amount' => $validated['downpayment_amount'],
            'service_type'       => $serviceTypeDb,
            'appointment_date'   => $validated['appointment_date'],
            'start_time'         => $validated['start_time'],
            'payment_status'     => 'pending',
            'reservation_status' => 'awaiting_payment',
            'booking_source'     => 'flutter_app',
        ]));

        // Build PayMongo success/cancel URLs through Laravel (not directly to Flutter)
        $successUrl = route('flutter.payment.success', ['reservation' => $pending->id]);
        $cancelUrl  = route('flutter.payment.cancel',  ['reservation' => $pending->id]);

        $secretKey = env('PAYMONGO_SECRET_KEY');
        $response  = Http::withBasicAuth($secretKey, '')
            ->acceptJson()
            ->post('https://api.paymongo.com/v1/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'billing' => [
                            'name'  => $pending->customer_name,
                            'email' => $pending->customer_email,
                            'phone' => $pending->customer_phone,
                        ],
                        'send_email_receipt'   => true,
                        'show_description'     => true,
                        'show_line_items'      => true,
                        'description'          => '20% reservation fee for spa appointment',
                        'line_items'           => [[
                            'currency'    => 'PHP',
                            'amount'      => (int) round($validated['downpayment_amount'] * 100),
                            'name'        => $pending->bookable_name . ' Reservation Fee',
                            'quantity'    => 1,
                            'description' => '20% downpayment for appointment reservation',
                        ]],
                        'payment_method_types' => ['gcash', 'paymaya', 'card'],
                        'success_url'          => $successUrl, // ✅ Through Laravel first
                        'cancel_url'           => $cancelUrl,  // ✅ Through Laravel first
                        'metadata'             => [
                            'reservation_id' => (string) $pending->id,
                            'spa_id'         => (string) $pending->spa_id,
                            'branch_id'      => (string) $pending->branch_id,
                            'source'         => 'flutter_app',
                        ],
                    ],
                ],
            ]);

        if (!$response->successful()) {
            $pending->update([
                'payment_status'     => 'failed',
                'reservation_status' => 'failed',
                'paymongo_payload'   => $response->json(),
            ]);

            Log::error('PayMongo checkout failed', [
                'response' => $response->json(),
                'status'   => $response->status(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create payment session. Please try again.'
            ], 500);
        }

        $checkoutData = $response->json('data');

        $pending->update([
            'paymongo_checkout_session_id' => data_get($checkoutData, 'id'),
            'paymongo_payload'             => $response->json(),
        ]);

        return response()->json([
            'success'            => true,
            'checkout_url'       => data_get($checkoutData, 'attributes.checkout_url'),
            'reservation_id'     => $pending->id,
            'downpayment_amount' => $validated['downpayment_amount'],
        ]);
    }

    /**
     * PayMongo redirects here after payment.
     * Webhook is the primary booking creator.
     * This is the fallback in case webhook fires after redirect.
     */
    public function paymentSuccess(Request $request)
    {
        $reservationId = $request->query('reservation');
        $flutterWebUrl = env('FLUTTER_WEB_URL', 'http://localhost:5000');

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
                $secretKey      = env('PAYMONGO_SECRET_KEY');
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
                                $this->createBookingFromReservation($reservation);
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
        $flutterWebUrl = env('FLUTTER_WEB_URL', 'http://localhost:5000');

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
    private function createBookingFromReservation(OnlineReservationPayment $reservation): void
    {
        DB::transaction(function () use ($reservation) {

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

            // Find least-busy available therapist (same logic as BookingController)
            $therapists = User::role('therapist')
                ->whereHas('staff', function ($q) use ($reservation) {
                    $q->where('spa_id', $reservation->spa_id)
                      ->where('branch_id', $reservation->branch_id)
                      ->where('employment_status', 'active');
                })
                ->orderBy('first_name')
                ->get();

            $busyIds = Booking::query()
                ->where('spa_id', $reservation->spa_id)
                ->where('branch_id', $reservation->branch_id)
                ->where('appointment_date', $reservation->appointment_date)
                ->whereIn('therapist_id', $therapists->pluck('id'))
                ->whereIn('status', ['reserved', 'pending', 'ongoing'])
                ->where(function ($q) use ($reservation, $endTime) {
                    $q->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $reservation->start_time);
                })
                ->pluck('therapist_id')
                ->unique();

            $assignedTherapist = $therapists
                ->reject(fn($t) => $busyIds->contains($t->id))
                ->first();

            if (!$assignedTherapist) {
                throw new \RuntimeException('No therapist available for this reservation.');
            }

            $booking = Booking::create([
                'spa_id'             => $reservation->spa_id,
                'branch_id'          => $reservation->branch_id,
                'customer_user_id'   => $reservation->user_id,      // ✅ correct column
                'created_by_user_id' => null,
                'booking_source'     => 'online',
                'status'             => 'reserved',
                'payment_status'     => 'partially_paid',
                'amount_paid'        => $reservation->downpayment_amount,
                'total_amount'       => $reservation->full_amount,
                'balance_amount'     => $reservation->full_amount - $reservation->downpayment_amount,
                'service_type'       => $reservation->service_type,
                'treatment'          => $treatmentCode,              // ✅ "treatment_5" format
                'customer_name'      => $reservation->customer_name,
                'customer_email'     => $reservation->customer_email,
                'customer_phone'     => $reservation->customer_phone,
                'customer_address'   => $reservation->customer_address,
                'appointment_date'   => $reservation->appointment_date,
                'start_time'         => $reservation->start_time,
                'end_time'           => $endTime,                    // ✅ recalculated
                'therapist_id'       => $assignedTherapist->id,
            ]);

            $reservation->update([
                'payment_status'     => 'paid',
                'reservation_status' => 'reserved',    // ✅ matches enum (not 'confirmed')
                'paid_at'            => now(),
                'booking_id'         => $booking->id,
            ]);
        });
    }
}
