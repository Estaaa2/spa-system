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
        $totalSpas         = Spa::count();
        $professionalCount = Spa::where('business_tier', 'professional')->count();
        $basicCount        = Spa::where('business_tier', 'basic')->count();

        $totalUsers = User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'admin'))->count();

        $activeSubscriptions = Subscription::where('payment_status', 'paid')
            ->where('expires_at', '>', now())
            ->count();

        $subscriptionRevenue = Subscription::where('payment_status', 'paid')->sum('amount');

        // Spas waiting on an admin decision — same condition as the Registered Spas page.
        $pendingCount = Spa::where('verification_status', 'pending')->count();
        $pendingSpas  = Spa::with('owner')
            ->where('verification_status', 'pending')
            ->latest()
            ->take(5)
            ->get();

        $recentSpas = Spa::with(['owner', 'subscriptions' => function ($q) {
            $q->where('payment_status', 'paid')
              ->where('expires_at', '>', now())
              ->latest();
        }])
        ->withCount('branches')
        ->latest()
        ->take(8)
        ->get();

        return view('admin.dashboard', compact(
            'totalSpas',
            'professionalCount',
            'basicCount',
            'totalUsers',
            'activeSubscriptions',
            'subscriptionRevenue',
            'pendingCount',
            'pendingSpas',
            'recentSpas',
        ));
    }
}
