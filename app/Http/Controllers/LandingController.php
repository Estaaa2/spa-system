<?php

namespace App\Http\Controllers;

use App\Models\Spa;
use App\Models\Treatment;
use App\Models\Package;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function index(Request $request)
    {
        $city = trim($request->input('city', ''));

        $baseQuery = fn() => Spa::with([
            'branches' => function ($query) use ($city) {
                $query->whereHas('profile', function ($q) {
                    $q->where('is_listed', 1);
                })
                ->when($city, function ($q) use ($city) {
                    $q->where('location', 'LIKE', "%{$city}%");
                })
                ->with(['profile', 'treatments', 'packages']);
            },
            'subscriptions',
        ])
        ->where('verification_status', 'verified')
        ->whereHas('branches', function ($q) use ($city) {
            $q->whereHas('profile', fn($p) => $p->where('is_listed', 1));
            if ($city) {
                $q->where('location', 'LIKE', "%{$city}%");
            }
        });

        $allSpas   = $baseQuery()->get();
        $spas      = $allSpas->filter(fn($spa) => $spa->isProfessional());
        $basicSpas = $allSpas->filter(fn($spa) => !$spa->isProfessional());

        $treatments = Treatment::all()->groupBy('branch_id');
        $packages   = Package::all()->groupBy('branch_id');

        return view('welcome', compact('spas', 'basicSpas', 'treatments', 'packages', 'city'));
    }
}
