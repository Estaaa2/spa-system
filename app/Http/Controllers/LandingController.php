<?php

namespace App\Http\Controllers;

use App\Models\Spa;

class LandingController extends Controller
{
    public function index()
    {
        $spas = Spa::with('branches')->get();

        // Pass all treatments grouped by branch for the booking modal
        $treatments = \App\Models\Treatment::all()->groupBy('branch_id');
        $packages = \App\Models\Package::all()->groupBy('branch_id');

        return view('welcome', compact('spas', 'treatments', 'packages'));
    }
}
