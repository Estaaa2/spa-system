<?php

namespace App\Http\Controllers;

use App\Models\Spa;
use App\Models\Treatment;
use App\Models\Package;

class LandingController extends Controller
{
    public function index()
    {
        $spas = Spa::with([
            'branches' => function ($query) {
                $query->whereHas('profile', function ($q) {
                    $q->where('is_listed', 1);
                })->with([
                    'profile',
                    'treatments',
                    'packages',
                ]);
            },
        ])
        ->where('verification_status', 'verified')
        ->whereHas('branches.profile', function ($query) {
            $query->where('is_listed', 1);
        })
        ->get();

        $treatments = Treatment::all()->groupBy('branch_id');
        $packages   = Package::all()->groupBy('branch_id');

        return view('welcome', compact('spas', 'treatments', 'packages'));
    }
}
