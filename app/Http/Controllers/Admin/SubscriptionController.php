<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    private const STATES = ['active', 'expired', 'unpaid'];

    public function index(Request $request)
    {
        $q     = trim((string) $request->get('q'));
        $state = in_array($request->get('state'), self::STATES, true) ? $request->get('state') : null;

        $subscriptions = $this->inState($state)
            // Removed spas keep their payment history, so load them too.
            ->with(['spa' => fn ($spa) => $spa->withTrashed()->with('owner')])
            ->when($q !== '', fn ($query) => $query->whereHas(
                'spa',
                fn ($spa) => $spa->withTrashed()->where('name', 'like', "%{$q}%")
            ))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'active'  => $this->inState('active')->count(),
            'expired' => $this->inState('expired')->count(),
            'unpaid'  => $this->inState('unpaid')->count(),
        ];

        return view('admin.subscriptions.index', [
            'subscriptions'    => $subscriptions,
            'q'                => $q,
            'state'            => $state,
            'states'           => self::STATES,
            'counts'           => $counts,
            'collectedTotal'   => Subscription::where('payment_status', 'paid')->sum('amount'),
            // starts_at is stamped by the PayMongo webhook when the payment is confirmed.
            'collectedMonth'   => Subscription::where('payment_status', 'paid')
                ->where('starts_at', '>=', now()->startOfMonth())
                ->sum('amount'),
            'expiringSoonCount' => $this->inState('active')
                ->where('expires_at', '<=', now()->addDays(7))
                ->count(),
        ]);
    }

    /**
     * active  = paid and not yet expired
     * expired = paid and past its expiry (includes owner-cancelled plans)
     * unpaid  = checkout started but never confirmed by PayMongo
     */
    private function inState(?string $state)
    {
        $query = Subscription::query();

        return match ($state) {
            'active'  => $query->where('payment_status', 'paid')->where('expires_at', '>', now()),
            'expired' => $query->where('payment_status', 'paid')->where('expires_at', '<=', now()),
            'unpaid'  => $query->where('payment_status', '!=', 'paid'),
            default   => $query,
        };
    }
}
