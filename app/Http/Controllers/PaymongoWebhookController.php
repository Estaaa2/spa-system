<?php

namespace App\Http\Controllers;

use App\Mail\SubscriptionPaid;
use App\Models\Booking;
use App\Models\LeaveRequest;
use App\Models\OnlineReservationPayment;
use App\Models\Spa;
use App\Models\Subscription;
use App\Models\Package;
use App\Models\Treatment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\Subscription\PlanLimitEnforcer;
use App\Services\Subscription\SubscriptionBilling;

class PaymongoWebhookController extends Controller
{
    public function __construct(
        private readonly SubscriptionBilling $billing,
    ) {}

    public function handle(Request $request)
    {
        $rawPayload = $request->getContent();
        $sigHeader  = $request->header('Paymongo-Signature');
        $payload    = json_decode($rawPayload, true);

        if (! is_array($payload)) {
            Log::warning('PayMongo webhook: body is not valid JSON');
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // PayMongo signs test events in "te" and live events in "li". Pick
        // by the event itself, not by APP_ENV, so test keys on the live
        // server (and live keys anywhere) both verify correctly.
        $isLive = (bool) data_get($payload, 'data.attributes.livemode', false);

        if (! $this->verifyWebhookSignature($rawPayload, $sigHeader, $isLive)) {
            Log::warning('PayMongo webhook: invalid signature', ['livemode' => $isLive]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $type = data_get($payload, 'data.attributes.type');

        Log::info('PayMongo webhook received', ['type' => $type, 'livemode' => $isLive]);

        if ($type !== 'checkout_session.payment.paid') {
            return response()->json(['status' => 'ignored'], 200);
        }

        $checkoutId = data_get($payload, 'data.attributes.data.id');

        if (! $checkoutId) {
            Log::warning('PayMongo webhook missing checkout ID');
            return response()->json(['status' => 'missing_checkout_id'], 200);
        }

        // =========================
        // 1) SUBSCRIPTION PAYMENTS
        // =========================
        $subscription = Subscription::where('paymongo_checkout_id', $checkoutId)->first();

        if ($subscription) {
            $payment = data_get($payload, 'data.attributes.data.attributes.payments.0');

            try {
                // Idempotent: a retried event for a paid row changes nothing.
                // Rows already marked "expired" by the cleanup command are
                // still activated, because the owner did pay.
                $activated = $this->billing->activate(
                    $subscription,
                    data_get($payment, 'id'),
                    data_get($payment, 'attributes.source.type')
                );
            } catch (\Throwable $e) {
                Log::error('Subscription webhook activation failed', [
                    'subscription_id' => $subscription->id,
                    'checkout_id' => $checkoutId,
                    'message' => $e->getMessage(),
                ]);

                // 500 makes PayMongo retry the event later.
                return response()->json(['status' => 'error'], 500);
            }

            if (! $activated) {
                return response()->json(['status' => 'subscription_already_processed'], 200);
            }

            Log::info('Spa subscription activated', [
                'spa_id' => $subscription->spa_id,
                'subscription_id' => $subscription->id,
                'kind' => $subscription->kind,
                'plan' => $subscription->business_tier,
                'starts_at' => $subscription->starts_at?->toDateTimeString(),
                'expires_at' => $subscription->expires_at?->toDateTimeString(),
            ]);

            $this->applyPlanLimits($subscription);
            $this->sendPaidEmail($subscription);

            return response()->json(['status' => 'subscription_processed'], 200);
        }

        // =========================
        // 2) ONLINE RESERVATIONS
        // =========================
        $reservation = OnlineReservationPayment::where('paymongo_checkout_session_id', $checkoutId)->first();

        if ($reservation) {
            Log::info('Reservation found for checkout', [
                'reservation_id' => $reservation->id,
                'checkout_id' => $checkoutId,
            ]);

            if (!($reservation->payment_status === 'paid' && $reservation->booking_id)) {
                try {
                    DB::transaction(function () use ($reservation, $payload) {
                        $treatmentCode = $reservation->bookable_type . '_' . $reservation->bookable_id;

                        $durationMinutes = 60;

                        if ($reservation->bookable_type === 'treatment') {
                            $treatment = Treatment::withoutGlobalScopes()->find($reservation->bookable_id);
                            $durationMinutes = $treatment?->duration ?? 60;
                        } elseif ($reservation->bookable_type === 'package') {
                            $package = Package::withoutGlobalScopes()->find($reservation->bookable_id);
                            $durationMinutes = $package?->duration ?? $package?->total_duration ?? 60;
                        }

                        $endTime = Carbon::parse($reservation->start_time)
                            ->addMinutes($durationMinutes)
                            ->format('H:i:s');

                        $therapists = User::role('therapist')
                        ->whereHas('staff', function ($q) use ($reservation) {
                            $q->where('spa_id', $reservation->spa_id)
                                ->where('branch_id', $reservation->branch_id)
                                ->where('employment_status', 'active');
                        })
                        ->orderBy('first_name')  // ← use your actual column name
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

                        // ON-LEAVE: exclude therapists with approved leave covering
                        // the reservation's date — a slot held for 15 minutes could
                        // otherwise still confirm into a therapist who went on leave
                        // in the meantime.
                        $onLeaveIds = LeaveRequest::approvedUserIdsOnDate(
                            (int) $reservation->spa_id,
                            (int) $reservation->branch_id,
                            (string) $reservation->appointment_date
                        );

                        $recommendedTherapist = $therapists
                            ->reject(fn ($therapist) => $busyIds->contains($therapist->id) || in_array($therapist->id, $onLeaveIds))
                            ->first();

                        if (! $recommendedTherapist) {
                            throw new \RuntimeException('No therapist available for paid reservation.');
                        }
                        $totalAmount = (float) $reservation->full_amount;
                        $amountPaid  = (float) ($reservation->amount_charged ?? $reservation->downpayment_amount);
                        $balance     = max(round($totalAmount - $amountPaid, 2), 0);

                        $booking = Booking::create([
                            'spa_id' => $reservation->spa_id,
                            'branch_id' => $reservation->branch_id,
                            'customer_user_id' => $reservation->user_id,
                            'created_by_user_id' => null,
                            'booking_source' => 'online',
                            'status' => 'reserved',
                            'payment_status' => $balance <= 0 ? 'paid' : 'partially_paid',
                            'amount_paid' => $amountPaid,
                            'total_amount' => $totalAmount,
                            'balance_amount' => $balance,
                            'service_type' => $reservation->service_type,
                            'treatment' => $treatmentCode,
                            'customer_name' => $reservation->customer_name,
                            'customer_email' => $reservation->customer_email,
                            'customer_phone' => $reservation->customer_phone,
                            'customer_address' => $reservation->customer_address,
                            'appointment_date' => $reservation->appointment_date,
                            'start_time' => $reservation->start_time,
                            'end_time' => $endTime,
                            'therapist_id' => $recommendedTherapist->id,
                        ]);

                        $reservation->update([
                            'payment_status' => 'paid',
                            'reservation_status' => 'reserved',
                            'paid_at' => now(),
                            'booking_id' => $booking->id,
                            'paymongo_payload' => $payload,
                            'paymongo_payment_intent_id' => data_get($payload, 'data.attributes.data.attributes.payments.0.attributes.payment_intent_id'),
                            'paymongo_payment_id' => data_get($payload, 'data.attributes.data.attributes.payments.0.id'),
                            'payment_reference' => data_get($payload, 'data.attributes.data.attributes.payments.0.attributes.reference_number'),
                        ]);
                    });
                } catch (\Throwable $e) {
                    Log::error('Reservation webhook transaction failed', [
                        'reservation_id' => $reservation->id,
                        'checkout_id' => $checkoutId,
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    return response()->json(['status' => 'error'], 500);
                }
            }

            return response()->json(['status' => 'reservation_processed'], 200);
        }

        Log::warning('No subscription or reservation found for checkout ID', [
            'checkout_id' => $checkoutId,
        ]);

        return response()->json(['status' => 'not_found'], 200);
    }

    /**
     * Pauses or restores staff for the spa's plan right away (an upgrade
     * brings paused staff back). Failure must not fail the webhook; the
     * hourly subscriptions:enforce-limits run catches up.
     */
    private function applyPlanLimits(Subscription $subscription): void
    {
        try {
            $spa = Spa::find($subscription->spa_id);

            if ($spa) {
                app(PlanLimitEnforcer::class)->sync($spa);
            }
        } catch (\Throwable $e) {
            Log::error('Plan limit sync after payment failed', [
                'subscription_id' => $subscription->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sends the confirmation email. A mail failure must not fail the
     * webhook, or PayMongo would retry an already-activated payment.
     */
    private function sendPaidEmail(Subscription $subscription): void
    {
        $spa = Spa::withTrashed()->with('owner')->find($subscription->spa_id);
        $email = $spa?->owner?->email;

        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new SubscriptionPaid($spa, $subscription));
        } catch (\Throwable $e) {
            Log::error('Subscription paid email failed', [
                'subscription_id' => $subscription->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function verifyWebhookSignature(
        string $payload,
        ?string $sigHeader,
        bool $isLive
    ): bool {
        if (! filled($sigHeader)) {
            return false;
        }

        // Each PayMongo webhook has its own secret, so the live webhook's
        // secret replaces the test one when the site goes live.
        $secret = (string) config('services.paymongo.webhook_secret');

        if (! filled($secret)) {
            Log::error('PayMongo webhook secret is not configured.');

            return false;
        }

        preg_match('/(?:^|,)t=([^,]+)/', $sigHeader, $timestampMatch);
        preg_match('/(?:^|,)te=([^,]*)/', $sigHeader, $testSignatureMatch);
        preg_match('/(?:^|,)li=([^,]*)/', $sigHeader, $liveSignatureMatch);

        $timestamp = $timestampMatch[1] ?? null;

        $signature = $isLive
            ? ($liveSignatureMatch[1] ?? null)
            : ($testSignatureMatch[1] ?? null);

        if (! filled($timestamp) || ! filled($signature)) {
            Log::warning('PayMongo webhook signature is incomplete.', ['livemode' => $isLive]);

            return false;
        }

        $computed = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        return hash_equals(strtolower($computed), strtolower($signature));
    }
}
