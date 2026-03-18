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
        // ── Stat Cards ──
        $totalSpas = Spa::count();

        $totalUsers = User::whereDoesntHave('roles', function ($q) {
            $q->where('name', 'admin');
        })->count();

        $activeSubscriptions = Subscription::where('payment_status', 'paid')
            ->where('expires_at', '>', now())
            ->count();

        $subscriptionRevenue = Subscription::where('payment_status', 'paid')
            ->sum('amount');

        // ── Recently Registered Spas ──
        $recentSpas = Spa::with(['owner', 'subscriptions' => function ($q) {
            $q->where('payment_status', 'paid')
              ->where('expires_at', '>', now())
              ->latest();
        }])
        ->latest()
        ->take(8)
        ->get();

        // ── Subscription breakdown for chart ──
        $professionalCount = Spa::where('business_tier', 'professional')->count();
        $basicCount        = Spa::where('business_tier', 'basic')->count();

        return view('admin.dashboard', compact(
            'totalSpas',
            'totalUsers',
            'activeSubscriptions',
            'subscriptionRevenue',
            'recentSpas',
            'professionalCount',
            'basicCount',
        ));
    }
}
