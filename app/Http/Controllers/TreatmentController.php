<?php

namespace App\Http\Controllers;
use App\Models\Treatment;   

use Illuminate\Http\Request;

class TreatmentController extends Controller
{
public function create()
{
    return view('treatments.create');
}

public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'duration' => 'required|integer',
        'price' => 'required|numeric',
        'service_type' => 'required|string',
        'description' => 'nullable|string',
    ]);

    Treatment::create($request->all());

    return redirect()->route('services.index')
        ->with('success', 'Treatment created successfully.');
}
}
