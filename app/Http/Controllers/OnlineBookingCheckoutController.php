<?php

namespace App\Http\Controllers;

use App\Models\OnlineReservationPayment;
use App\Models\Package;
use App\Models\Treatment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class OnlineBookingCheckoutController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'spa_id' => ['required', 'exists:spas,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'treatment' => ['required', 'string'],
            'service_type' => ['required', 'in:in_branch,in_home'],
            'customer_address' => ['nullable', 'string', 'max:1000'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required'],
        ]);

        if ($validated['service_type'] === 'in_home' && blank($validated['customer_address'])) {
            throw ValidationException::withMessages([
                'customer_address' => 'Home address is required for home service bookings.',
            ]);
        }

        [$type, $id] = explode('_', $validated['treatment']);

        if ($type === 'treatment') {
            $item = Treatment::withoutGlobalScopes()
                ->where('id', $id)
                ->where('spa_id', $validated['spa_id'])
                ->where('branch_id', $validated['branch_id'])
                ->firstOrFail();

            $bookableType = 'treatment';
        } elseif ($type === 'package') {
            $item = Package::withoutGlobalScopes()
                ->where('id', $id)
                ->where('spa_id', $validated['spa_id'])
                ->where('branch_id', $validated['branch_id'])
                ->firstOrFail();

            $bookableType = 'package';
        } else {
            throw ValidationException::withMessages([
                'treatment' => 'Invalid service selected.',
            ]);
        }

        $fullAmount = (float) $item->price;
        if ($fullAmount <= 0) {
            throw ValidationException::withMessages([
                'treatment' => 'Selected service has invalid price.',
            ]);
        }

        $downpaymentAmount = round($fullAmount * 0.50, 2);

        $pending = DB::transaction(function () use ($validated, $item, $bookableType, $fullAmount, $downpaymentAmount) {
            return OnlineReservationPayment::create([
                'user_id' => Auth::id(),
                'spa_id' => $validated['spa_id'],
                'branch_id' => $validated['branch_id'],
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'],
                'customer_address' => $validated['customer_address'] ?? null,
                'bookable_id' => $item->id,
                'bookable_type' => $bookableType,
                'bookable_name' => $item->name,
                'full_amount' => $fullAmount,
                'downpayment_amount' => $downpaymentAmount,
                'service_type' => $validated['service_type'],
                'appointment_date' => $validated['appointment_date'],
                'start_time' => $validated['start_time'],
                'payment_status' => 'pending',
                'reservation_status' => 'awaiting_payment',
            ]);
        });

        $secretKey = env('PAYMONGO_SECRET_KEY');

        $response = Http::withBasicAuth($secretKey, '')
            ->acceptJson()
            ->post('https://api.paymongo.com/v1/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'billing' => [
                            'name' => $pending->customer_name,
                            'email' => $pending->customer_email,
                            'phone' => $pending->customer_phone,
                        ],
                        'send_email_receipt' => true,
                        'show_description' => true,
                        'show_line_items' => true,
                        'description' => '50% reservation fee for spa appointment',
                        'line_items' => [
                            [
                                'currency' => 'PHP',
                                'amount' => (int) round($downpaymentAmount * 100),
                                'name' => $pending->bookable_name . ' Reservation Fee',
                                'quantity' => 1,
                                'description' => '50% downpayment for appointment reservation',
                            ],
                        ],
                        'payment_method_types' => [
                            'gcash',
                            'paymaya',
                        ],
                        'success_url' => route('bookings.online.payment.success') . '?reservation=' . $pending->id,
                        'cancel_url' => route('bookings.online.payment.cancel') . '?reservation=' . $pending->id,
                        'metadata' => [
                            'reservation_id' => (string) $pending->id,
                            'spa_id' => (string) $pending->spa_id,
                            'branch_id' => (string) $pending->branch_id,
                            'bookable_type' => $pending->bookable_type,
                            'bookable_id' => (string) $pending->bookable_id,
                        ],
                    ],
                ],
            ]);

        if (!$response->successful()) {
            $pending->update([
                'payment_status' => 'failed',
                'reservation_status' => 'failed',
                'paymongo_payload' => $response->json(),
            ]);

            throw ValidationException::withMessages([
                'payment' => 'Unable to create PayMongo checkout session.',
            ]);
        }

        $checkoutData = $response->json('data');

        $pending->update([
            'paymongo_checkout_session_id' => data_get($checkoutData, 'id'),
            'paymongo_payload' => $response->json(),
        ]);

        return redirect()->away(data_get($checkoutData, 'attributes.checkout_url'));
    }

    public function success(Request $request)
    {
        return redirect('/')
            ->with('success', 'Payment received. Your reservation is being finalized.');
    }

    public function cancel(Request $request)
    {
        $reservationId = $request->query('reservation');

        if ($reservationId) {
            OnlineReservationPayment::where('id', $reservationId)
                ->where('payment_status', 'pending')
                ->update([
                    'reservation_status' => 'cancelled',
                    'payment_status' => 'cancelled',
                ]);
        }

        return redirect('/')
            ->with('error', 'Payment was cancelled. No reservation was created.');
    }
}