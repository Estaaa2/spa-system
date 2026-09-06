<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Mail\SubscriptionPaid;
use App\Models\Spa;
use App\Models\Subscription;
use Barryvdh\DomPDF\Facade\Pdf;
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

        // Auto-downgrade: if the latest paid subscription has passed its
        // expiry date but the spa's business_tier hasn't caught up yet
        // (e.g. owner never visited this page since expiry, or cron hasn't
        // run), correct it here so the badge/tier shown is always accurate.
        if (
            $subscription
            && $subscription->expires_at
            && $subscription->expires_at->isPast()
            && $spa->business_tier === 'professional'
        ) {
            $spa->update(['business_tier' => 'basic']);
            $spa->refresh();

            Log::info("Spa {$spa->id} auto-downgraded to basic on page load (subscription #{$subscription->id} expired {$subscription->expires_at})");
        }

        return view('owner.subscription.index', compact('spa', 'subscription'));
    }

    public function checkout()
    {
        $owner = auth()->user();
        $spa = $owner->spa;

        $subscription = Subscription::create([
            'spa_id'         => $spa->id,
            'business_tier'  => 'professional',
            'amount'         => 200.00,
            'payment_status' => 'pending',
        ]);

        $ownerName = trim(($owner->first_name ?? '') . ' ' . ($owner->last_name ?? ''));

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
                        'billing' => [
                            'name'  => $ownerName ?: null,
                            'email' => $owner->email ?? null,
                            'phone' => $owner->phone ?? null,
                        ],
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

        session(['pending_subscription_id' => $subscription->id]);
        return redirect($checkoutUrl);
    }

    public function downloadReceipt(Subscription $subscription)
    {
        abort_unless($subscription->spa_id === auth()->user()->spa->id, 403);
        abort_unless($subscription->payment_status === 'paid', 404);

        $spa = $subscription->spa()->with('owner')->first();

        $pdf = Pdf::loadView('owner.subscription.receipt', [
            'subscription' => $subscription,
            'spa'           => $spa,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('receipt-' . $subscription->paymongo_checkout_id . '.pdf');
    }

    public function success()
    {
        $subscription = Subscription::find(session('pending_subscription_id'));

        // Fallback if session got dropped somehow (e.g. different browser tab)
        if (! $subscription) {
            $subscription = Subscription::where('spa_id', auth()->user()->spa->id)
                ->latest()
                ->first();
        }

        session()->forget('pending_subscription_id');

        return view('owner.subscription.success', compact('subscription'));
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
                        'paymongo_payment_id' => $payload['data']['attributes']['data']['attributes']['payments'][0]['id'] ?? null,
                        'payment_method'      => $payload['data']['attributes']['data']['attributes']['payments'][0]['attributes']['source']['type'] ?? null,
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
