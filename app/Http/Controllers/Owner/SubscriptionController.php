<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    private const PLANS = [
        'basic',
        'premium',
        'business',
    ];

    public function index()
    {
        $spa = auth()->user()->spa;

        abort_unless($spa, 403);

        $subscription = $spa->subscriptions()
            ->where(function ($query) {
                $query
                    ->where('status', 'active')
                    ->orWhere(function ($legacy) {
                        $legacy
                            ->where('payment_status', 'paid')
                            ->where(function ($expiry) {
                                $expiry
                                    ->whereNull('expires_at')
                                    ->orWhere('expires_at', '>', now());
                            });
                    });
            })
            ->latest()
            ->first();

        $subscriptionHistory = $spa->subscriptions()
            ->where(function ($query) {
                $query
                    ->whereIn('status', [
                        'active',
                        'cancelled',
                        'expired',
                    ])
                    ->orWhere('payment_status', 'paid');
            })
            ->latest()
            ->get();

        return view('owner.subscription.index', [
            'spa' => $spa,
            'subscription' => $subscription,
            'subscriptionHistory' => $subscriptionHistory,
            'plans' => config('plans'),
        ]);
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'plan' => [
                'required',
                'string',
                'in:' . implode(',', self::PLANS),
            ],
            'billing_cycle' => [
                'required',
                'string',
                'in:monthly,yearly',
            ],
        ]);

        $owner = $request->user();
        $spa = $owner->spa;

        abort_unless($spa, 403);

        $planKey = $validated['plan'];
        $billingCycle = $validated['billing_cycle'];
        $plan = config("plans.{$planKey}");

        if (! is_array($plan)) {
            return back()->withErrors([
                'payment' => 'The selected plan is unavailable.',
            ]);
        }

        $priceKey = $billingCycle === 'yearly'
            ? 'yearly_price'
            : 'monthly_price';

        $amount = (float) ($plan[$priceKey] ?? 0);

        if ($amount <= 0) {
            return back()->withErrors([
                'payment' => 'The selected plan does not have a valid price.',
            ]);
        }

        $subscription = Subscription::create([
            'spa_id' => $spa->id,
            'business_tier' => $planKey,
            'amount' => $amount,
            'billing_cycle' => $billingCycle,
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);

        $ownerName = trim(
            ($owner->first_name ?? '') . ' ' . ($owner->last_name ?? '')
        );

        $amountInCentavos = (int) round($amount * 100);

        $response = Http::withBasicAuth(
            env('PAYMONGO_SECRET_KEY'),
            ''
        )->post(
            'https://api.paymongo.com/v1/checkout_sessions',
            [
                'data' => [
                    'attributes' => [
                        'line_items' => [
                            [
                                'currency' => 'PHP',
                                'amount' => $amountInCentavos,
                                'name' => ($plan['name'] ?? ucfirst($planKey))
                                    . ' Plan - '
                                    . ucfirst($billingCycle),
                                'quantity' => 1,
                            ],
                        ],

                        'payment_method_types' => [
                            'gcash',
                        ],

                        'billing' => [
                            'name' => $ownerName ?: null,
                            'email' => $owner->email ?? null,
                            'phone' => $owner->phone ?? null,
                        ],

                        'description' => sprintf(
                            '%s plan (%s) subscription for %s',
                            $plan['name'] ?? ucfirst($planKey),
                            $billingCycle,
                            $spa->name
                        ),

                        'metadata' => [
                            'subscription_id' => (string) $subscription->id,
                            'spa_id' => (string) $spa->id,
                            'plan' => $planKey,
                            'billing_cycle' => $billingCycle,
                        ],

                        'success_url' => route(
                            'owner.subscription.success'
                        ),

                        'cancel_url' => route(
                            'owner.subscription.cancel'
                        ),
                    ],
                ],
            ]
        );

        if ($response->failed()) {
            $subscription->update([
                'status' => 'cancelled',
                'payment_status' => 'failed',
            ]);

            Log::error('PayMongo subscription checkout failed', [
                'subscription_id' => $subscription->id,
                'response' => $response->json(),
            ]);

            return back()->withErrors([
                'payment' => 'Could not initiate payment. Please try again.',
            ]);
        }

        $checkout = $response->json();

        $checkoutId = data_get(
            $checkout,
            'data.id'
        );

        $checkoutUrl = data_get(
            $checkout,
            'data.attributes.checkout_url'
        );

        if (! $checkoutId || ! $checkoutUrl) {
            $subscription->update([
                'status' => 'cancelled',
                'payment_status' => 'failed',
            ]);

            Log::error(
                'PayMongo returned an incomplete checkout response',
                [
                    'subscription_id' => $subscription->id,
                    'response' => $checkout,
                ]
            );

            return back()->withErrors([
                'payment' => 'Could not initiate payment. Please try again.',
            ]);
        }

        $subscription->update([
            'paymongo_checkout_id' => $checkoutId,
        ]);

        session([
            'pending_subscription_id' => $subscription->id,
        ]);

        return redirect($checkoutUrl);
    }

    public function downloadReceipt(Subscription $subscription)
    {
        $spa = auth()->user()->spa;

        abort_unless(
            $spa && (int) $subscription->spa_id === (int) $spa->id,
            403
        );

        abort_unless(
            $subscription->payment_status === 'paid'
                || $subscription->status === 'active',
            404
        );

        $subscription->load('spa.owner');

        $pdf = Pdf::loadView('owner.subscription.receipt', [
            'subscription' => $subscription,
            'spa' => $subscription->spa,
        ])->setPaper('a4', 'portrait');

        return $pdf->download(
            'receipt-' .
            ($subscription->paymongo_checkout_id ?? $subscription->id) .
            '.pdf'
        );
    }

    public function success()
    {
        $spa = auth()->user()->spa;

        abort_unless($spa, 403);

        $subscription = Subscription::find(
            session('pending_subscription_id')
        );

        if (
            ! $subscription
            || (int) $subscription->spa_id !== (int) $spa->id
        ) {
            $subscription = $spa->subscriptions()
                ->latest()
                ->first();
        }

        session()->forget('pending_subscription_id');

        return view(
            'owner.subscription.success',
            compact('subscription')
        );
    }

    public function cancel()
    {
        return view('owner.subscription.cancel');
    }

    public function cancelSubscription()
    {
        $spa = auth()->user()->spa;

        abort_unless($spa, 403);

        $subscription = $spa->subscriptions()
            ->where(function ($query) {
                $query
                    ->where('status', 'active')
                    ->orWhere(function ($legacy) {
                        $legacy
                            ->where('payment_status', 'paid')
                            ->where(function ($expiry) {
                                $expiry
                                    ->whereNull('expires_at')
                                    ->orWhere('expires_at', '>', now());
                            });
                    });
            })
            ->latest()
            ->first();

        if (! $subscription) {
            return redirect()
                ->route('owner.subscription.index')
                ->with(
                    'error',
                    'There is no active subscription to cancel.'
                );
        }

        /*
         * Cancellation is not immediate.
         *
         * The subscription remains available until expires_at.
         * Do not change the spa tier here because the current plan
         * remains active until the billing period ends.
         */
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return redirect()
            ->route('owner.subscription.index')
            ->with(
                'success',
                'Subscription cancelled. Your current plan remains available until the end of the billing period.'
            );
    }
}
