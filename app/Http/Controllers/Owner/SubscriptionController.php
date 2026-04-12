<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Mail\SubscriptionPaid;
use App\Models\Spa;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

        $subscription = Subscription::create([
            'spa_id'         => $spa->id,
            'business_tier'  => 'professional',
            'amount'         => 200.00,
            'payment_status' => 'pending',
        ]);

        $response = Http::withBasicAuth(env('PAYMONGO_SECRET_KEY'), '')
            ->post('https://api.paymongo.com/v1/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'line_items' => [[
                            'currency' => 'PHP',
                            'amount'   => 20000,
                            'name'     => 'Professional Tier Upgrade',
                            'quantity' => 1,
                        ]],
                        'payment_method_types' => ['gcash'],
                        'success_url' => route('owner.subscription.success'),
                        'cancel_url'  => route('owner.subscription.cancel'),
                    ],
                ],
            ]);

        $checkout    = $response->json();
        $checkoutId  = $checkout['data']['id'] ?? null;
        $checkoutUrl = $checkout['data']['attributes']['checkout_url'] ?? null;

        if ($checkoutId) {
            $subscription->update(['paymongo_checkout_id' => $checkoutId]);
        }

        if (! $checkoutUrl) {
            return back()->withErrors(['payment' => 'Could not initiate payment. Please try again.']);
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
        $rawPayload = $request->getContent();
        $sigHeader  = $request->header('Paymongo-Signature');

        if (! $this->verifyWebhookSignature($rawPayload, $sigHeader)) {
            Log::warning('PayMongo webhook: invalid signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawPayload, true);
        $type    = $payload['data']['attributes']['type'] ?? null;

        Log::info('PayMongo webhook received', ['type' => $type]);

        if ($type === 'checkout_session.payment.paid') {
            $checkoutId = $payload['data']['attributes']['data']['id'] ?? null;

            if ($checkoutId) {
                $subscription = Subscription::where('paymongo_checkout_id', $checkoutId)->first();

                if ($subscription) {
                    $subscription->update([
                        'payment_status' => 'paid',
                        'starts_at'      => now(),
                        'expires_at'     => now()->addMonth(),
                    ]);

                    // ✅ Eager load owner too for the email
                    $spa = Spa::with(['branches.profile', 'owner'])->find($subscription->spa_id);

                    if ($spa) {
                        // Upgrade tier
                        $spa->update(['business_tier' => 'professional']);

                        // ✅ Send confirmation email to owner
                        $ownerEmail = $spa->owner->email ?? null;
                        if ($ownerEmail) {
                            try {
                                Mail::to($ownerEmail)
                                    ->send(new SubscriptionPaid($spa, $subscription));
                                Log::info("Subscription email sent to {$ownerEmail}");
                            } catch (\Exception $e) {
                                // Don't fail the webhook if email fails
                                Log::error("Failed to send subscription email: " . $e->getMessage());
                            }
                        }

                        Log::info("Spa {$spa->id} upgraded to professional and branches listed");
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
            $subscription->update(['expires_at' => now()]);

            $spa->update(['business_tier' => 'basic']);
        }

        return redirect()->route('owner.subscription.index')
            ->with('success', 'Subscription cancelled.');
    }

    private function verifyWebhookSignature(string $payload, ?string $sigHeader): bool
    {
        if (! $sigHeader) return false;

        $secret = env('PAYMONGO_WEBHOOK_SECRET');

        preg_match('/t=(\d+)/', $sigHeader, $tMatch);
        preg_match('/te=([a-f0-9]+)/', $sigHeader, $teMatch);

        if (empty($tMatch[1]) || empty($teMatch[1])) return false;

        $computed = hash_hmac('sha256', $tMatch[1] . '.' . $payload, $secret);

        return hash_equals($computed, $teMatch[1]);
    }
}
