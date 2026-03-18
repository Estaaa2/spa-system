<?php

namespace App\Http\Controllers;

use App\Models\Spa;

class LandingController extends Controller
{
    public function index()
    {
        $spas = Spa::with([
            'branches.profile',
            'branches.treatments',
            'branches.packages',
        ])
            ->where('business_tier', 'professional')
            ->whereHas('branches.profile', function ($query) {
                $query->where('is_listed', 1);
            })
            // ✅ ADD THIS — only spas with a non-expired paid subscription
            ->whereHas('subscriptions', function ($query) {
                $query->where('payment_status', 'paid')
                    ->where('expires_at', '>', now());
            })
            ->get();

        $treatments = \App\Models\Treatment::all()->groupBy('branch_id');
        $packages   = \App\Models\Package::all()->groupBy('branch_id');

        return view('welcome', compact('spas', 'treatments', 'packages'));
    }
}
