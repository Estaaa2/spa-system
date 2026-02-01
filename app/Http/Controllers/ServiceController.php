<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Treatment;
use Illuminate\Support\Facades\Auth;

class ServiceController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $treatments = Treatment::where('spa_id', $user->spa_id)
            ->where('branch_id', $user->branch_id)
            ->get();

        $packages = Package::where('spa_id', $user->spa_id)
            ->where('branch_id', $user->branch_id)
            ->get();

        return view('services.index', compact('treatments', 'packages'));
    }
}

