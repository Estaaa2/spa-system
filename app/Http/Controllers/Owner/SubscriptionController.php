<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Spa;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SubscriptionController extends Controller
{
    public function index()
    {
        $spa = auth()->user()->spa;

        $subscription = Subscription::where('spa_id', $spa->id)
            ->where('payment_status', 'paid')
            ->latest()
            ->first();

        return view('owner.subscription.index', compact('spa', 'subscription'));
    }

    public function checkout()
    {
        $spa = auth()->user()->spa;

        // Create a subscription record in DB first
        $subscription = Subscription::create([
            'spa_id' => $spa->id,
            'business_tier' => 'professional',
            'amount' => 200.00,
            'payment_status' => 'pending',
        ]);

        $response = Http::withBasicAuth(env('PAYMONGO_SECRET_KEY'), '')
            ->post('https://api.paymongo.com/v1/checkout_sessions', [
                "data" => [
                    "attributes" => [
                        "line_items" => [
                            [
                                "currency" => "PHP",
                                "amount" => 20000, // PayMongo uses cents
                                "name" => "Professional Tier Upgrade",
                                "quantity" => 1
                            ]
                        ],
                        "payment_method_types" => ["gcash"],
                        "success_url" => route('owner.subscription.success'),
                        "cancel_url" => route('owner.subscription.cancel')
                    ]
                ]
            ]);

        $checkout = $response->json();
        $checkoutId = $checkout['data']['id'] ?? null; // PayMongo checkout ID
        $checkoutUrl = $checkout['data']['attributes']['checkout_url'] ?? null;

        // Save PayMongo checkout ID to DB
        if ($checkoutId) {
            $subscription->paymongo_checkout_id = $checkoutId;
            $subscription->save();
        }

        return redirect($checkoutUrl);
    }

    public function success()
    {
        return view('owner.subscription.success');
    }

    public function cancel()
    {
        return view('owner.subscription.cancel');
    }

    public function webhook(Request $request)
    {
        $payload = $request->all();

        if (isset($payload['data']['attributes']['type']) && $payload['data']['attributes']['type'] === 'checkout_session.payment.paid') {

            $checkoutId = $payload['data']['attributes']['data']['id'] ?? null;

            if ($checkoutId) {

                $subscription = Subscription::where('paymongo_checkout_id', $checkoutId)->first();

                if ($subscription) {
                    $subscription->update([
                        'payment_status' => 'paid',
                        'starts_at' => now(),
                        'expires_at' => now()->addMonth(),
                    ]);

                    $spa = Spa::find($subscription->spa_id);

                    if ($spa) {
                        $spa->update(['business_tier' => 'professional']);
                    }
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    public function cancelSubscription()
    {
        $spa = auth()->user()->spa;

        $subscription = Subscription::where('spa_id', $spa->id)
            ->where('payment_status', 'paid')
            ->latest()
            ->first();

        if ($subscription) {
            $subscription->expires_at = now(); 
            $subscription->save();

            $spa->business_tier = 'basic';
            $spa->save();
        }

        return redirect()->route('owner.subscription.index')
            ->with('success', 'Subscription cancelled.');
    }
}
