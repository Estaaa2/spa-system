<?php

namespace App\Http\Controllers;

use App\Models\Spa;

class LandingController extends Controller
{
    public function index()
    {
        $spas = Spa::with(['branches.profile'])
            ->where('business_tier', 'professional') // Only professional tier spas
            ->whereHas('branches.profile', function ($query) {
                $query->where('is_listed', 1); // Branch must be listed
            })
            ->get();

        // Pass all treatments grouped by branch for the booking modal
        $treatments = \App\Models\Treatment::all()->groupBy('branch_id');
        $packages = \App\Models\Package::all()->groupBy('branch_id');

        return view('welcome', compact('spas', 'treatments', 'packages'));
    }
}
