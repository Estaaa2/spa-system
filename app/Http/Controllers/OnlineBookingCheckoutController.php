<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\LeaveRequest;
use App\Models\OnlineReservationPayment;
use App\Models\OperatingHours;
use App\Models\Package;
use App\Models\Treatment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
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
            'customer_phone'   => ['required', 'string', 'regex:/^09\d{9}$/'],
            'treatment'        => ['required', 'string'],
            'service_type'     => ['required', 'in:in_branch,in_home'],
            'customer_address' => ['nullable', 'string', 'max:1000'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time'       => ['required'],
        ]);

        if ($validated['service_type'] === 'in_home' && blank($validated['customer_address'])) {
            return $this->fail($request, 'Home address is required for home service bookings.');
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
            return $this->fail($request, 'Invalid service selected.');
        }

        $fullAmount = (float) $item->price;
        if ($fullAmount <= 0) {
            return $this->fail($request, 'Selected service has an invalid price.');
        }

        // =====================================================
        // OPERATING HOURS VALIDATION
        // =====================================================
        $dayOfWeek = Carbon::parse($validated['appointment_date'])->format('l');
        $hours = OperatingHours::where('branch_id', $validated['branch_id'])
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (!$hours || $hours->is_closed) {
            return $this->fail($request, 'The spa is closed on the selected day. Please choose another date.');
        }

        $start   = Carbon::parse($validated['start_time']);
        $opening = Carbon::parse($hours->opening_time);
        $closing = Carbon::parse($hours->closing_time);

        if ($start->lt($opening) || $start->gte($closing)) {
            return $this->fail($request, "Please select a time within spa operating hours: {$hours->opening_time} - {$hours->closing_time}");
        }

        $durationMinutes = $item->duration ?? ($item->total_duration ?? 60);
        $endTime         = $start->copy()->addMinutes($durationMinutes)->format('H:i');

        if (Carbon::parse($endTime)->gt($closing)) {
            return $this->fail($request, 'This service would end after closing hours. Please choose an earlier time.');
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
            return $this->fail($request, 'No therapists are available at this branch.');
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

        // ON-LEAVE: exclude therapists with approved leave covering this date.
        $onLeaveIds = LeaveRequest::approvedUserIdsOnDate(
            (int) $validated['spa_id'],
            (int) $validated['branch_id'],
            $validated['appointment_date']
        );

        $availableTherapists = $therapists->reject(
            fn($t) => $busyIds->contains($t->id) || in_array($t->id, $onLeaveIds)
        );

        if ($availableTherapists->isEmpty()) {
            return $this->fail($request, 'All therapists are fully booked for the selected date and time. Please choose a different time slot.');
        }

        // =====================================================
        // CREATE PENDING RESERVATION & PAYMONGO CHECKOUT
        // =====================================================
        $downpaymentAmount = round($fullAmount * 0.20, 2);

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

        //PAYMONGO API CALL
        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->timeout(15) // fail fast instead of hanging for 30s
                ->acceptJson()
                ->post('https://api.paymongo.com/v1/checkout_sessions', [
                    'data' => [
                        'attributes' => [
                            'send_email_receipt' => true,
                            'show_description'   => true,
                            'show_line_items'    => true,
                            'description'        => '20% reservation fee for spa appointment',
                            'line_items'         => [
                                [
                                    'currency'    => 'PHP',
                                    'amount'      => (int) round($downpaymentAmount * 100),
                                    'name'        => $pending->bookable_name . ' Reservation Fee',
                                    'quantity'    => 1,
                                    'description' => '20% downpayment for appointment reservation',
                                ],
                            ],
                            'payment_method_types' => ['gcash', 'paymaya'],
                            'billing' => [
                                'name'  => $pending->customer_name,
                                'email' => $pending->customer_email,
                                'phone' => $pending->customer_phone,
                            ],
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

        } catch (ConnectionException $e) {
            // DNS timeout or network failure reaching PayMongo
            $pending->update([
                'payment_status'     => 'failed',
                'reservation_status' => 'failed',
            ]);

            return $this->fail($request, 'Unable to connect to the payment gateway. Please check your connection and try again.');
        }

        if (!$response->successful()) {
            $pending->update([
                'payment_status'     => 'failed',
                'reservation_status' => 'failed',
                'paymongo_payload'   => $response->json(),
            ]);

            return $this->fail($request, 'Unable to create payment session. Please try again.');
        }

        $checkoutData = $response->json('data');

        $pending->update([
            'paymongo_checkout_session_id' => data_get($checkoutData, 'id'),
            'paymongo_payload'             => $response->json(),
        ]);

        $checkoutUrl = data_get($checkoutData, 'attributes.checkout_url');

        if ($request->expectsJson()) {
            return response()->json(['checkout_url' => $checkoutUrl]);
        }

        return redirect()->away($checkoutUrl);
    }

    /**
     * GET /bookings/online/available-slots
     * Query: spa_id, branch_id, treatment ("treatment_5" / "package_3"), appointment_date
     *
     * Read-only. Checks the same `bookings` table and the same
     * reserved/pending/ongoing statuses store() already checks — nothing new
     * is written, no schema change is required. This does not yet account for
     * other customers mid-checkout (that needs the hold/lock mechanism we
     * discussed separately) — it's the minimum needed to make the picker show
     * real availability instead of a blind time input.
     */
    public function availableSlots(Request $request)
    {
        $validated = $request->validate([
            'spa_id'           => ['required', 'exists:spas,id'],
            'branch_id'        => ['required', 'exists:branches,id'],
            'treatment'        => ['required', 'string'],
            'appointment_date' => ['required', 'date'],
        ]);

        [$type, $id] = array_pad(explode('_', $validated['treatment'], 2), 2, null);

        $durationMinutes = 60;
        if ($type === 'treatment') {
            $durationMinutes = Treatment::withoutGlobalScopes()->find($id)?->duration ?? 60;
        } elseif ($type === 'package') {
            $item = Package::withoutGlobalScopes()->find($id);
            $durationMinutes = $item?->total_duration ?? $item?->duration ?? 60;
        }

        $dayOfWeek = Carbon::parse($validated['appointment_date'])->format('l');
        $hours = OperatingHours::where('branch_id', $validated['branch_id'])
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (!$hours || $hours->is_closed) {
            return response()->json(['closed' => true, 'slots' => []]);
        }

        $opening = Carbon::parse($hours->opening_time);
        $closing = Carbon::parse($hours->closing_time);

        $therapistCount = User::role('therapist')
            ->whereHas('staff', fn ($q) => $q->where('spa_id', $validated['spa_id'])
                ->where('branch_id', $validated['branch_id'])
                ->where('employment_status', 'active'))
            ->count();

        // Same table, same statuses store() already checks — every candidate
        // slot below is evaluated independently against this full list, not
        // derived relative to the previous booking, so a wide-open block
        // doesn't get silently truncated to "only the next available slot".
        $bookingWindows = Booking::query()
            ->where('spa_id', $validated['spa_id'])
            ->where('branch_id', $validated['branch_id'])
            ->where('appointment_date', $validated['appointment_date'])
            ->whereIn('status', ['reserved', 'pending', 'ongoing'])
            ->get(['start_time', 'end_time'])
            ->map(fn ($b) => [Carbon::parse($b->start_time), Carbon::parse($b->end_time)]);

        $slots   = [];
        $cursor  = $opening->copy();
        $now     = now();
        $isToday = Carbon::parse($validated['appointment_date'])->isToday();

        while ($cursor->lt($closing)) {
            $slotStart = $cursor->copy();
            $slotEnd   = $slotStart->copy()->addMinutes($durationMinutes);

            if ($slotEnd->gt($closing)) {
                $slots[] = ['time' => $slotStart->format('H:i'), 'available' => false, 'reason' => 'past_closing'];
            } elseif ($isToday && $slotStart->lte($now)) {
                $slots[] = ['time' => $slotStart->format('H:i'), 'available' => false, 'reason' => 'past'];
            } else {
                // Every active therapist can only be in one non-overlapping
                // booking at a time (enforced when bookings are created), so
                // counting overlapping windows is equivalent to counting
                // distinct busy therapists — the same invariant store()
                // already relies on via $busyIds->unique().
                $overlapping = $bookingWindows->filter(
                    fn ($w) => $slotStart->lt($w[1]) && $slotEnd->gt($w[0])
                )->count();

                $available = ($therapistCount - $overlapping) > 0;
                $slots[] = [
                    'time'      => $slotStart->format('H:i'),
                    'available' => $available,
                    'reason'    => $available ? null : 'fully_booked',
                ];
            }

            $cursor->addMinutes(30);
        }

        return response()->json([
            'closed'       => false,
            'opening_time' => $hours->opening_time,
            'closing_time' => $hours->closing_time,
            'slots'        => $slots,
        ]);
    }

    /**
     * Return a validation-style error response.
     * JSON (422) for AJAX/fetch requests, classic redirect-back for normal form posts.
     */
    private function fail(Request $request, string $message, int $status = 422)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return back()->with('booking_error', $message)->withInput();
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