<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\OnlineReservationPayment;
use App\Models\OperatingHours;
use App\Models\Package;
use App\Models\Treatment;
use App\Models\Spa;
use App\Services\Bookings\TherapistAssigner;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class OnlineBookingCheckoutController extends Controller
{

    private const MAX_ADVANCE_DAYS = 30;

    /** Shown wherever home service has no verified therapist (decision Q35). */
    public const NO_VERIFIED_MESSAGE = 'Home service needs a verified therapist, and none is free at this time. Try another time, or choose In-Branch.';

    public function __construct(private TherapistAssigner $assigner)
    {
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'spa_id'           => ['required', 'exists:spas,id'],
            'branch_id'        => ['required', 'exists:branches,id'],
            // customer_name / customer_email may still be posted (web form, app)
            // but are ignored: they are copied from the signed-in account below,
            // so the booking always carries the account's verified email (Q12).
            'customer_phone'   => ['required', 'string', 'regex:/^09\d{9}$/'],
            'treatment'        => ['required', 'string'],
            'service_type'     => ['required', 'in:in_branch,in_home'],
            'customer_address' => ['nullable', 'string', 'max:1000'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:' . now()->addDays(self::MAX_ADVANCE_DAYS)->toDateString()],
            'start_time'       => ['required'],
            'payment_option'   => ['nullable', 'in:downpayment,full'],
        ]);

        $spa = Spa::find($validated['spa_id']);

        if (! $spa || ! $spa->hasFeature('online_reservation')) {
                return $this->fail(
                $request,
                'Online reservations are not available for this spa right now. Please contact the spa directly.'
            );
        }

        // The branch must belong to this spa and be approved to operate.
        $branch = Branch::where('id', $validated['branch_id'])
            ->where('spa_id', $spa->id)
            ->first();

        if (
            $spa->verification_status !== 'verified' ||
            ! $branch ||
            ! $branch->isOperational() ||
            ! $branch->isReadyForBookings()
        ) {
            return $this->fail(
                $request,
                'This branch is not accepting online reservations right now. Please contact the spa directly.'
            );
        }

        if ($validated['service_type'] === 'in_home' && blank($validated['customer_address'])) {
            return $this->fail($request, 'Home address is required for home service bookings.');
        }

        $customer = Auth::user();
        $validated['customer_name'] = trim($customer->first_name.' '.$customer->last_name);
        $validated['customer_email'] = $customer->email;

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

        // Same rule as staff booking: an in-branch-only treatment can't be booked as home service.
        if ($validated['service_type'] === 'in_home' && $bookableType === 'treatment' && $item->service_type === 'in_branch_only') {
            return $this->fail($request, 'This treatment is only available in-branch.');
        }

        $fullAmount = (float) $item->price;
        if ($fullAmount <= 0) {
            return $this->fail($request, 'Selected service has an invalid price.');
        }

        $promo = $item->promos()->withoutGlobalScopes()->activeToday()->first();
        if ($promo) {
            $fullAmount = $promo->discountedPrice($fullAmount);
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
        // PREVENT CUSTOMER DOUBLE-BOOKING (same customer, overlapping time)
        // =====================================================
        $customerOverlap = Booking::query()
            ->where('customer_user_id', Auth::id())
            ->where('appointment_date', $validated['appointment_date'])
            ->whereIn('status', ['reserved', 'pending', 'ongoing'])
            ->where(function ($q) use ($validated, $endTime) {
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $validated['start_time']);
            })
            ->exists();

        if ($customerOverlap) {
            return $this->fail($request, 'You already have an appointment that overlaps this time. Please choose a different time or cancel your existing booking first.');
        }

        // =====================================================
        // THERAPIST AVAILABILITY (shared rule, TherapistAssigner)
        // =====================================================
        // Only checks that someone can take it; the therapist is assigned when
        // the payment is confirmed (PaymongoWebhookController). Home service
        // needs an identity-verified therapist (D2).
        $isHome = $validated['service_type'] === 'in_home';
        $capacity = $this->assigner
            ->day((int) $validated['spa_id'], (int) $validated['branch_id'], $validated['appointment_date'])
            ->capacityBetween($start->format('H:i'), $endTime, $isHome);

        if ($capacity < 1) {
            return $this->fail($request, $isHome
                ? self::NO_VERIFIED_MESSAGE
                : 'All therapists are fully booked for the selected date and time. Please choose a different time slot.');
        }

        // =====================================================
        // CREATE PENDING RESERVATION & PAYMONGO CHECKOUT
        // =====================================================
        $downpaymentAmount = round($fullAmount * 0.20, 2);
        $paymentOption = $validated['payment_option'] ?? 'downpayment';
        $isFullPayment = $paymentOption === 'full';
        $amountCharged = $isFullPayment ? round($fullAmount, 2) : $downpaymentAmount;

        $pending = DB::transaction(function () use ($validated, $item, $bookableType, $fullAmount, $downpaymentAmount, $paymentOption, $amountCharged) {
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
                'payment_option'       => $paymentOption,
                'amount_charged'       => $amountCharged,
                'service_type'         => $validated['service_type'],
                'appointment_date'     => $validated['appointment_date'],
                'start_time'           => $validated['start_time'],
                'payment_status'       => 'pending',
                'reservation_status'   => 'awaiting_payment',
            ]);
        });

        $secretKey = \App\Services\PayMongo::secretKey();

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
                            'description'        => $isFullPayment
                                ? 'Full payment for spa appointment'
                                : '20% reservation fee for spa appointment',
                            'line_items'         => [
                                [
                                    'currency'    => 'PHP',
                                    'amount'      => (int) round($amountCharged * 100),
                                    'name'        => $pending->bookable_name . ($isFullPayment ? ' Full Payment' : ' Reservation Fee'),
                                    'quantity'    => 1,
                                    'description' => $isFullPayment
                                        ? 'Full payment for appointment reservation'
                                        : '20% downpayment for appointment reservation',
                                ],
                            ],
                            'payment_method_types' => \App\Services\PayMongo::paymentMethods(['gcash', 'paymaya']),
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
            return response()->json([
                'checkout_url'   => $checkoutUrl,
                // The mobile app uses this to check the payment. The web ignores it.
                'reservation_id' => $pending->id,
            ]);
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
            // Optional so older app builds keep working; they get in-branch counts
            // and checkout re-checks home service anyway.
            'service_type'     => ['nullable', 'in:in_branch,in_home'],
        ]);

        $isHome = ($validated['service_type'] ?? null) === 'in_home';

        $spa = Spa::find($validated['spa_id']);

        if (! $spa || ! $spa->hasFeature('online_reservation')) {
            return response()->json([
                'success' => false,
                'message' => 'Online reservations are not available for this spa right now. Please contact the spa directly.',
            ], 422);
        }

        // The branch must belong to this spa and be approved to operate.
        $branch = Branch::where('id', $validated['branch_id'])
            ->where('spa_id', $spa->id)
            ->first();

        if (
            $spa->verification_status !== 'verified' ||
            ! $branch ||
            ! $branch->isOperational() ||
            ! $branch->isReadyForBookings()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'This branch is not accepting online reservations right now. Please contact the spa directly.',
            ], 422);
        }

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

        // Who is free is decided by the shared rule (pool, bookings, leave,
        // Schedule availability, and verified-only for home service).
        $day = $this->assigner->day((int) $validated['spa_id'], (int) $validated['branch_id'], $validated['appointment_date']);

        // Same-customer windows — used only to grey out times the logged-in
        // customer already has booked (anywhere at this branch, on this
        // date), independent of therapist load.
        $customerBookingWindows = Auth::check()
            ? Booking::query()
                ->where('customer_user_id', Auth::id())
                ->where('appointment_date', $validated['appointment_date'])
                ->whereIn('status', ['reserved', 'pending', 'ongoing'])
                ->get(['start_time', 'end_time'])
                ->map(fn ($b) => [Carbon::parse($b->start_time), Carbon::parse($b->end_time)])
            : collect();

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
                $from = $slotStart->format('H:i');
                $to   = $slotEnd->format('H:i');
                $free = $day->capacityBetween($from, $to, $isHome);

                $customerBusy = $customerBookingWindows->contains(
                    fn ($w) => $slotStart->lt($w[1]) && $slotEnd->gt($w[0])
                );

                // Home service with nobody verified free, while someone unverified
                // is: say why, instead of a plain "fully booked" (Q35).
                $reason = match (true) {
                    $customerBusy => 'already_booked',
                    $free > 0     => null,
                    $isHome && $day->capacityBetween($from, $to) > 0 => 'no_verified_therapist',
                    default       => 'fully_booked',
                };

                $slots[] = [
                    'time'      => $slotStart->format('H:i'),
                    'available' => $free > 0 && ! $customerBusy,
                    'reason'    => $reason,
                    // How many more bookings fit. A count only, no names.
                    'free'      => $free,
                ];
            }

            $cursor->addMinutes(30);
        }

        return response()->json([
            'closed'       => false,
            'home_service_available' => $day->hasVerifiedMember(),
            'opening_time' => $hours->opening_time,
            'closing_time' => $hours->closing_time,
            'slots'        => $slots,
        ]);
    }

    /**
     * GET /bookings/online/available-days
     * Query: branch_id
     *
     * Returns every date a customer may book: from today up to the 30-day
     * limit, leaving out the days the branch is closed. The booking form's
     * calendar greys out any date that is not in this list.
     */
    public function availableDays(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        $branch = Branch::find($validated['branch_id']);

        $today   = Carbon::today();
        $lastDay = $today->copy()->addDays(self::MAX_ADVANCE_DAYS);

        $hoursByDay = OperatingHours::where('branch_id', $validated['branch_id'])
            ->get()
            ->keyBy('day_of_week');

        $dates = [];

        for ($date = $today->copy(); $date->lte($lastDay); $date->addDay()) {
            $hours = $hoursByDay->get($date->format('l'));

            if ($hours && ! $hours->is_closed) {
                $dates[] = $date->toDateString();
            }
        }

        return response()->json([
            'dates' => $dates,
            'first' => $today->toDateString(),
            'last'  => $lastDay->toDateString(),
            // Q35b: false when no therapist at this branch is identity-verified,
            // so the form turns the Home Service option off with a reason.
            'home_service_available' => $this->assigner->branchHasVerifiedProvider((int) $branch->spa_id, (int) $branch->id),
            'home_service_message'   => 'Home service isn\'t available at this branch right now, because none of its therapists is verified yet. Choose In-Branch, or try another branch.',
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
