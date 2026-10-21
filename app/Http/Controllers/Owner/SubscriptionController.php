<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\Subscription\SubscriptionBilling;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionBilling $billing,
    ) {}

    public function index()
    {
        $spa = auth()->user()->spa;

        abort_unless($spa, 403);

        $plans = collect(SubscriptionBilling::purchasablePlans())
            ->mapWithKeys(fn (string $key) => [$key => config("plans.{$key}")])
            ->filter(fn ($plan) => is_array($plan))
            ->all();

        // Price and dates for every plan and cycle, so the page can show
        // exactly what the owner will pay before going to PayMongo.
        $quotes = [];

        foreach (array_keys($plans) as $planKey) {
            foreach (SubscriptionBilling::CYCLES as $cycle) {
                $quotes[$planKey][$cycle] = $this->billing->quote($spa, $planKey, $cycle);
            }
        }

        $subscriptionHistory = $spa->subscriptions()
            ->where(function ($query) {
                $query
                    ->where('payment_status', 'paid')
                    ->orWhereIn('status', ['active', 'cancelled', 'expired']);
            })
            ->where('payment_status', '!=', 'expired')
            ->latest()
            ->get();

        return view('owner.subscription.index', [
            'spa' => $spa,
            // The paid period in effect now (null during the trial or grace).
            'subscription' => $spa->currentPeriod(),
            'scheduledPeriods' => $spa->scheduledPeriods(),
            'paidThroughAt' => $spa->paidThroughAt(),
            'subscriptionHistory' => $subscriptionHistory,
            'plans' => $plans,
            'quotes' => $quotes,
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $validated = $this->validatePlanChoice($request);

        $owner = $request->user();
        $spa = $owner->spa;

        abort_unless($spa, 403);

        $quote = $this->billing->quote($spa, $validated['plan'], $validated['billing_cycle']);

        if (! $quote->allowed) {
            return back()->withErrors(['payment' => $quote->message]);
        }

        $plan = config("plans.{$quote->plan}");
        $planName = $plan['name'] ?? ucfirst($quote->plan);

        $subscription = Subscription::create([
            'spa_id' => $spa->id,
            'business_tier' => $quote->plan,
            'kind' => $quote->kind,
            'amount' => $quote->amount,
            'credit_amount' => $quote->credit,
            'billing_cycle' => $quote->cycle,
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);

        $ownerName = trim(($owner->first_name ?? '') . ' ' . ($owner->last_name ?? ''));

        $itemName = sprintf('%s Plan - %s', $planName, ucfirst($quote->cycle));

        $description = match ($quote->kind) {
            'upgrade' => sprintf(
                'Upgrade to %s (%s) for %s. Unused Basic time credited: PHP %s.',
                $planName,
                $quote->cycle,
                $spa->name,
                number_format($quote->credit, 2)
            ),
            default => sprintf(
                '%s plan (%s) for %s, %s to %s.',
                $planName,
                $quote->cycle,
                $spa->name,
                $quote->startsAt->format('M d, Y'),
                $quote->expiresAt->format('M d, Y')
            ),
        };

        try {
            $response = Http::withBasicAuth((string) config('services.paymongo.secret_key'), '')
                ->timeout(15)
                ->acceptJson()
                ->post('https://api.paymongo.com/v1/checkout_sessions', [
                    'data' => [
                        'attributes' => [
                            'send_email_receipt' => true,
                            'show_description' => true,
                            'show_line_items' => true,
                            'description' => $description,
                            'line_items' => [
                                [
                                    'currency' => 'PHP',
                                    'amount' => (int) round($quote->amount * 100),
                                    'name' => $itemName,
                                    'quantity' => 1,
                                ],
                            ],
                            'payment_method_types' => config('subscription.payment_methods', ['gcash']),
                            'billing' => array_filter([
                                'name' => $ownerName ?: null,
                                'email' => $owner->email ?? null,
                                'phone' => $owner->phone ?? null,
                            ]),
                            'metadata' => [
                                'type' => 'subscription',
                                'subscription_id' => (string) $subscription->id,
                                'spa_id' => (string) $spa->id,
                                'plan' => $quote->plan,
                                'billing_cycle' => $quote->cycle,
                                'kind' => $quote->kind,
                            ],
                            'success_url' => route('owner.subscription.success'),
                            'cancel_url' => route('owner.subscription.cancel'),
                        ],
                    ],
                ]);
        } catch (ConnectionException $e) {
            $subscription->update(['status' => 'cancelled', 'payment_status' => 'failed']);

            Log::error('PayMongo subscription checkout: connection failed', [
                'subscription_id' => $subscription->id,
                'message' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'payment' => 'Unable to reach the payment gateway. Please check your connection and try again.',
            ]);
        }

        $checkoutId = $response->json('data.id');
        $checkoutUrl = $response->json('data.attributes.checkout_url');

        if ($response->failed() || ! $checkoutId || ! $checkoutUrl) {
            $subscription->update(['status' => 'cancelled', 'payment_status' => 'failed']);

            Log::error('PayMongo subscription checkout failed', [
                'subscription_id' => $subscription->id,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return back()->withErrors([
                'payment' => 'Could not start the payment. Please try again.',
            ]);
        }

        $subscription->update(['paymongo_checkout_id' => $checkoutId]);

        session(['pending_subscription_id' => $subscription->id]);

        return redirect()->away($checkoutUrl);
    }

    public function downloadReceipt(Subscription $subscription)
    {
        $spa = auth()->user()->spa;

        abort_unless($spa && (int) $subscription->spa_id === (int) $spa->id, 403);
        abort_unless($subscription->payment_status === 'paid', 404);

        $subscription->load('spa.owner');

        $pdf = Pdf::loadView('owner.subscription.receipt', [
            'subscription' => $subscription,
            'spa' => $subscription->spa,
        ])->setPaper('a4', 'portrait');

        return $pdf->download(
            'receipt-' . ($subscription->paymongo_checkout_id ?? $subscription->id) . '.pdf'
        );
    }

    public function success()
    {
        $spa = auth()->user()->spa;

        abort_unless($spa, 403);

        $subscription = Subscription::find(session('pending_subscription_id'));

        if (! $subscription || (int) $subscription->spa_id !== (int) $spa->id) {
            $subscription = $spa->subscriptions()->latest()->first();
        }

        // Kept in the session so "Refresh Status" can re-check the same row
        // while the webhook is still on its way.
        return view('owner.subscription.success', compact('subscription'));
    }

    public function cancel()
    {
        session()->forget('pending_subscription_id');

        return view('owner.subscription.cancel');
    }

    /**
     * Plans are prepaid and never renew on their own, so there is nothing
     * to cancel. Kept only so the old button keeps working until the
     * billing page is rebuilt (Phase 4), when this route is removed.
     *
     * @deprecated
     */
    public function cancelSubscription(): RedirectResponse
    {
        $spa = auth()->user()->spa;

        abort_unless($spa, 403);

        $end = $spa->paidThroughAt();

        return redirect()
            ->route('owner.subscription.index')
            ->with(
                'success',
                'Plans do not renew automatically, so there is nothing to cancel.'
                . ($end ? ' Your access runs until ' . $end->format('F d, Y') . ' unless you pay for another period.' : '')
            );
    }

    /** @return array{plan: string, billing_cycle: string} */
    private function validatePlanChoice(Request $request): array
    {
        return $request->validate([
            'plan' => ['required', 'string', 'in:' . implode(',', SubscriptionBilling::purchasablePlans())],
            'billing_cycle' => ['required', 'string', 'in:' . implode(',', SubscriptionBilling::CYCLES)],
        ]);
    }
}
