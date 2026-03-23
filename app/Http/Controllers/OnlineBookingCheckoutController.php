<?php

namespace App\Http\Controllers;

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
use Illuminate\Validation\ValidationException;

class OnlineBookingCheckoutController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'spa_id'           => ['required', 'exists:spas,id'],
            'branch_id'        => ['required', 'exists:branches,id'],
            'customer_name'    => ['required', 'string', 'max:255'],
            'customer_email'   => ['required', 'email', 'max:255'],
            'customer_phone'   => ['required', 'string', 'max:30'],
            'treatment'        => ['required', 'string'],
            'service_type'     => ['required', 'in:in_branch,in_home'],
            'customer_address' => ['nullable', 'string', 'max:1000'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time'       => ['required'],
        ]);

        if ($validated['service_type'] === 'in_home' && blank($validated['customer_address'])) {
            return back()
                ->with('booking_error', 'Home address is required for home service bookings.')
                ->withInput();
        }

        // Resolve treatment or package
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
            return back()
                ->with('booking_error', 'Invalid service selected.')
                ->withInput();
        }

        $fullAmount = (float) $item->price;
        if ($fullAmount <= 0) {
            return back()
                ->with('booking_error', 'Selected service has an invalid price.')
                ->withInput();
        }

        // =====================================================
        // OPERATING HOURS VALIDATION
        // =====================================================
        $dayOfWeek = Carbon::parse($validated['appointment_date'])->format('l');
        $hours = OperatingHours::where('branch_id', $validated['branch_id'])
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (!$hours || $hours->is_closed) {
            return back()
                ->with('booking_error', 'The spa is closed on the selected day. Please choose another date.')
                ->withInput();
        }

        $start   = Carbon::parse($validated['start_time']);
        $opening = Carbon::parse($hours->opening_time);
        $closing = Carbon::parse($hours->closing_time);

        if ($start->lt($opening) || $start->gte($closing)) {
            return back()
                ->with('booking_error', "Please select a time within spa operating hours: {$hours->opening_time} - {$hours->closing_time}")
                ->withInput();
        }

        $durationMinutes = $item->duration ?? ($item->total_duration ?? 60);
        $endTime         = $start->copy()->addMinutes($durationMinutes)->format('H:i');

        if (Carbon::parse($endTime)->gt($closing)) {
            return back()
                ->with('booking_error', 'This service would end after closing hours. Please choose an earlier time.')
                ->withInput();
        }

        // =====================================================
        // THERAPIST AVAILABILITY VALIDATION
        // =====================================================
        $therapists = User::role('therapist')
            ->whereHas('staff', function ($q) use ($validated) {
                $q->where('spa_id', $validated['spa_id'])
                  ->where('branch_id', $validated['branch_id'])
                  ->where('employment_status', 'active');
            })
            ->get();

        if ($therapists->isEmpty()) {
            return back()
                ->with('booking_error', 'No therapists are available at this branch.')
                ->withInput();
        }

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

        $availableTherapists = $therapists->reject(fn($t) => $busyIds->contains($t->id));

        if ($availableTherapists->isEmpty()) {
            return back()
                ->with('booking_error', 'All therapists are fully booked for the selected date and time. Please choose a different time slot.')
                ->withInput();
        }

        // =====================================================
        // CREATE PENDING RESERVATION & PAYMONGO CHECKOUT
        // =====================================================
        $downpaymentAmount = round($fullAmount * 0.50, 2);

        $pending = DB::transaction(function () use ($validated, $item, $bookableType, $fullAmount, $downpaymentAmount) {
            return OnlineReservationPayment::create([
                'user_id'              => Auth::id(),
                'spa_id'               => $validated['spa_id'],
                'branch_id'            => $validated['branch_id'],
                'customer_name'        => $validated['customer_name'],
                'customer_email'       => $validated['customer_email'],
                'customer_phone'       => $validated['customer_phone'],
                'customer_address'     => $validated['customer_address'] ?? null,
                'bookable_id'          => $item->id,
                'bookable_type'        => $bookableType,
                'bookable_name'        => $item->name,
                'full_amount'          => $fullAmount,
                'downpayment_amount'   => $downpaymentAmount,
                'service_type'         => $validated['service_type'],
                'appointment_date'     => $validated['appointment_date'],
                'start_time'           => $validated['start_time'],
                'payment_status'       => 'pending',
                'reservation_status'   => 'awaiting_payment',
            ]);
        });

        $secretKey = env('PAYMONGO_SECRET_KEY');

        $response = Http::withBasicAuth($secretKey, '')
            ->acceptJson()
            ->post('https://api.paymongo.com/v1/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'billing' => [
                            'name'  => $pending->customer_name,
                            'email' => $pending->customer_email,
                            'phone' => $pending->customer_phone,
                        ],
                        'send_email_receipt' => true,
                        'show_description'   => true,
                        'show_line_items'    => true,
                        'description'        => '50% reservation fee for spa appointment',
                        'line_items'         => [
                            [
                                'currency'    => 'PHP',
                                'amount'      => (int) round($downpaymentAmount * 100),
                                'name'        => $pending->bookable_name . ' Reservation Fee',
                                'quantity'    => 1,
                                'description' => '50% downpayment for appointment reservation',
                            ],
                        ],
                        'payment_method_types' => ['gcash', 'paymaya'],
                        'success_url' => route('bookings.online.payment.success') . '?reservation=' . $pending->id,
                        'cancel_url'  => route('bookings.online.payment.cancel') . '?reservation=' . $pending->id,
                        'metadata'    => [
                            'reservation_id' => (string) $pending->id,
                            'spa_id'         => (string) $pending->spa_id,
                            'branch_id'      => (string) $pending->branch_id,
                            'bookable_type'  => $pending->bookable_type,
                            'bookable_id'    => (string) $pending->bookable_id,
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

            return back()
                ->with('booking_error', 'Unable to create payment session. Please try again.')
                ->withInput();
        }

        $checkoutData = $response->json('data');

        $pending->update([
            'paymongo_checkout_session_id' => data_get($checkoutData, 'id'),
            'paymongo_payload'             => $response->json(),
        ]);

        return redirect()->away(data_get($checkoutData, 'attributes.checkout_url'));
    }

    public function success(Request $request)
    {
        $reservationId = $request->query('reservation');

        if ($reservationId) {
            $reservation = OnlineReservationPayment::where('id', $reservationId)
                ->where('user_id', Auth::id())
                ->first();

            if ($reservation && $reservation->booking_id) {
                return redirect('/')
                    ->with('success', 'Payment confirmed! Your reservation is now showing in My Appointments.');
            }
        }

        return redirect('/')
            ->with('success', 'Payment received. Your reservation will appear in My Appointments shortly.');
    }

    public function cancel(Request $request)
    {
        $reservationId = $request->query('reservation');

        if ($reservationId) {
            OnlineReservationPayment::where('id', $reservationId)
                ->where('payment_status', 'pending')
                ->update([
                    'reservation_status' => 'cancelled',
                    'payment_status'     => 'cancelled',
                ]);
        }

        return redirect('/')
            ->with('error', 'Payment was cancelled. No reservation was created.');
    }
}
