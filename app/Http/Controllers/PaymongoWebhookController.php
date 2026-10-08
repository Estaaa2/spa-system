<?php

namespace App\Http\Controllers;

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

class PaymongoWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $rawPayload = $request->getContent();
        $sigHeader  = $request->header('Paymongo-Signature');

        if (! $this->verifyWebhookSignature($rawPayload, $sigHeader)) {
            Log::warning('PayMongo webhook: invalid signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawPayload, true);
        $type    = data_get($payload, 'data.attributes.type');

        Log::info('PayMongo webhook received', ['type' => $type]);

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
        $subscription = Subscription::where(
            'paymongo_checkout_id',
            $checkoutId
        )->first();

        if ($subscription) {
            // PayMongo may retry the same event. Do not process a paid
            // subscription a second time.
            if (
                $subscription->payment_status === 'paid' &&
                $subscription->status === 'active'
            ) {
                return response()->json([
                    'status' => 'subscription_already_processed',
                ], 200);
            }

            $billingCycle = in_array(
                $subscription->billing_cycle,
                ['monthly', 'yearly'],
                true
            )
                ? $subscription->billing_cycle
                : 'monthly';

            $planKey = in_array(
                $subscription->business_tier,
                ['basic', 'premium', 'business'],
                true
            )
                ? $subscription->business_tier
                : 'basic';

            $plan = config("plans.{$planKey}");

            if (! $plan) {
                Log::error('Subscription webhook has invalid plan', [
                    'subscription_id' => $subscription->id,
                    'plan' => $subscription->business_tier,
                ]);

                return response()->json([
                    'status' => 'invalid_subscription_plan',
                ], 422);
            }

            $payment = data_get(
                $payload,
                'data.attributes.data.attributes.payments.0'
            );

            $startsAt = now();

            // If this is a renewal while the existing subscription is still
            // active, extend from its current expiry instead of shortening it.
            $baseDate = $subscription->expires_at?->isFuture()
                ? $subscription->expires_at->copy()
                : $startsAt->copy();

            $expiresAt = $billingCycle === 'yearly'
                ? $baseDate->addYear()
                : $baseDate->addMonth();

            $subscription->update([
                'payment_status' => 'paid',
                'status' => 'active',
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'paymongo_payment_id' => data_get($payment, 'id'),
                'payment_method' => data_get($payment, 'attributes.source.type'),
            ]);

            $spa = Spa::with(['branches.profile', 'owner'])
                ->find($subscription->spa_id);

            if ($spa) {
                $spa->update([
                    'business_tier' => $planKey,
                ]);

                Log::info('Spa subscription activated', [
                    'spa_id' => $spa->id,
                    'subscription_id' => $subscription->id,
                    'plan' => $planKey,
                    'billing_cycle' => $billingCycle,
                    'expires_at' => $expiresAt->toDateTimeString(),
                ]);
            }

            return response()->json([
                'status' => 'subscription_processed',
            ], 200);
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

                        $booking = Booking::create([
                            'spa_id' => $reservation->spa_id,
                            'branch_id' => $reservation->branch_id,
                            'customer_user_id' => $reservation->user_id,
                            'created_by_user_id' => null,
                            'booking_source' => 'online',
                            'status' => 'reserved',
                            'payment_status' => 'partially_paid',
                            'amount_paid' => $reservation->downpayment_amount,
                            'total_amount' => $reservation->full_amount,
                            'balance_amount' => $reservation->full_amount - $reservation->downpayment_amount,
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

    private function verifyWebhookSignature(
        string $payload,
        ?string $sigHeader
    ): bool {
        if (! filled($sigHeader)) {
            return false;
        }

        $secret = env('PAYMONGO_WEBHOOK_SECRET');

        if (! filled($secret)) {
            Log::error('PayMongo webhook secret is not configured.');

            return false;
        }

        preg_match('/(?:^|,)t=([^,]+)/', $sigHeader, $timestampMatch);
        preg_match('/(?:^|,)te=([^,]+)/', $sigHeader, $testSignatureMatch);
        preg_match('/(?:^|,)li=([^,]+)/', $sigHeader, $liveSignatureMatch);

        $timestamp = $timestampMatch[1] ?? null;

        $signature = app()->environment('production')
            ? ($liveSignatureMatch[1] ?? null)
            : ($testSignatureMatch[1] ?? null);

        if (! filled($timestamp) || ! filled($signature)) {
            Log::warning('PayMongo webhook signature is incomplete.', [
                'environment' => app()->environment(),
            ]);

            return false;
        }

        $computed = hash_hmac(
            'sha256',
            $timestamp . '.' . $payload,
            $secret
        );

        return hash_equals(
            strtolower($computed),
            strtolower($signature)
        );
    }
}
