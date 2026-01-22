<?php

namespace App\Http\Controllers;

use App\Models\Treatment;
use App\Models\Package;

class ServiceController extends Controller
{
    public function index()
    {
        $treatments = Treatment::all();
        $packages = Package::all();

        return view('services.index', compact('treatments', 'packages'));
    }
}
