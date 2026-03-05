<?php

namespace App\Http\Controllers;

use App\Models\Spa;

class LandingController extends Controller
{
    public function index()
    {
        // Only get spas that have at least one branch listed
        $spas = Spa::with(['branches', 'branches.profile'])
            ->get()
            ->filter(function ($spa) {
                return $spa->branches->contains(function ($branch) {
                    return $branch->profile?->is_listed ?? false;
                });
            });

        // Pass all treatments grouped by branch for the booking modal
        $treatments = \App\Models\Treatment::all()->groupBy('branch_id');
        $packages = \App\Models\Package::all()->groupBy('branch_id');

        return view('welcome', compact('spas', 'treatments', 'packages'));
    }
}
