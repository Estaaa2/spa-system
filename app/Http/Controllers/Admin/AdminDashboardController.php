<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Spa;
use App\Models\Subscription;
use App\Models\User;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $paidRows = fn ($rows) => $rows->where('payment_status', 'paid');

        // Plans are worked out from paid periods and the trial, not from
        // spas.business_tier (only a display copy). Each spa needs its paid
        // rows anyway, so they are loaded once and counted here.
        $states = Spa::query()
            ->with(['subscriptions' => $paidRows])
            ->get()
            ->map(fn (Spa $spa) => [
                'state' => $spa->billingState(),
                'plan' => $spa->currentPlan(),
            ]);

        $totalSpas = $states->count();
        $isPaidOn = fn (string $plan) => fn ($row) => $row['state'] === 'active' && $row['plan'] === $plan;

        $basicCount = $states->filter($isPaidOn('basic'))->count();
        $premiumCount = $states->filter($isPaidOn('premium'))->count();
        $trialCount = $states->where('state', 'trial')->count();
        $graceCount = $states->where('state', 'grace')->count();
        $noPlanCount = $states->whereIn('state', ['locked', 'new'])->count();

        $totalUsers = User::whereDoesntHave(
            'roles',
            fn ($query) => $query->where('name', 'admin')
        )->count();

        // Spas with a paid period running now.
        $activeSubscriptions = $basicCount + $premiumCount;

        // Payments of the current PayMongo mode only (test money is not revenue once live).
        $subscriptionRevenue = Subscription::query()
            ->where('payment_status', 'paid')
            ->where('livemode', \App\Support\PayMongo::isLive())
            ->sum('amount');

        $pendingCount = Spa::where(
            'verification_status',
            'pending'
        )->count();

        $pendingSpas = Spa::with('owner')
            ->where('verification_status', 'pending')
            ->latest()
            ->take(5)
            ->get();

        $recentSpas = Spa::with([
            'owner',
            'subscriptions' => $paidRows,
        ])
            ->withCount('branches')
            ->latest()
            ->take(8)
            ->get();

        return view('admin.dashboard', compact(
            'totalSpas',
            'basicCount',
            'premiumCount',
            'trialCount',
            'graceCount',
            'noPlanCount',
            'totalUsers',
            'activeSubscriptions',
            'subscriptionRevenue',
            'pendingCount',
            'pendingSpas',
            'recentSpas',
        ));
    }
}
