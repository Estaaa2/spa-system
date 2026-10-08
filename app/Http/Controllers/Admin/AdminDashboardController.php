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
        $totalSpas = Spa::count();

        $basicCount = Spa::where('business_tier', 'basic')->count();

        $premiumCount = Spa::where(function ($query) {
            $query
                ->where('business_tier', 'premium')
                ->orWhere('business_tier', 'professional');
        })->count();

        $businessCount = Spa::where('business_tier', 'business')->count();

        $totalUsers = User::whereDoesntHave(
            'roles',
            fn ($query) => $query->where('name', 'admin')
        )->count();

        $activeSubscriptions = Subscription::query()
            ->where('payment_status', 'paid')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->count();

        $subscriptionRevenue = Subscription::query()
            ->where('payment_status', 'paid')
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
            'subscriptions' => function ($query) {
                $query
                    ->where('payment_status', 'paid')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '>', now())
                    ->latest();
            },
        ])
            ->withCount('branches')
            ->latest()
            ->take(8)
            ->get();

        return view('admin.dashboard', compact(
            'totalSpas',
            'basicCount',
            'premiumCount',
            'businessCount',
            'totalUsers',
            'activeSubscriptions',
            'subscriptionRevenue',
            'pendingCount',
            'pendingSpas',
            'recentSpas',
        ));
    }
}
