<?php

namespace App\Http\Controllers;
use App\Models\Package;
use App\Models\Treatment;

use Illuminate\Http\Request;

class PackageController extends Controller
{
    // PackageController.php
public function create()
{
    $treatments = Treatment::all(); // Get all treatments for selection
    return view('packages.create', compact('treatments'));
}

public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'duration' => 'required|integer',
        'price' => 'required|numeric',
        'included_treatments' => 'nullable|array',
        'description' => 'nullable|string',
    ]);

    Package::create($request->all());

    return redirect()->route('services.index')
        ->with('success', 'Package created successfully.');
}
}
