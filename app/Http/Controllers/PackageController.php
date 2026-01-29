<?php

namespace App\Http\Controllers;
use App\Models\Package;
use App\Models\Treatment;

use Illuminate\Http\Request;

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Treatment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PackageController extends Controller
{
    public function index()
    {
        $packages = Package::with('treatments')->get();
        $treatments = Treatment::all(); // for modal or create
        return view('services.index', compact('packages', 'treatments'));
    }

    public function create()
    {
        $treatments = Treatment::all();
        return view('packages.create', compact('treatments'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'duration' => 'nullable|integer|min:5',
            'price' => 'required|numeric|min:0',
            'included_treatments' => 'nullable|array',
            'description' => 'nullable|string',
        ]);

        $package = Package::create([
            'spa_id' => Auth::user()->spa_id,  // assuming spa_id is on user
            'branch_id' => Auth::user()->branch_id, // assuming branch_id is on user
            'name' => $request->name,
            'total_duration' => $request->duration,
            'price' => $request->price,
            'description' => $request->description,
        ]);

        if ($request->filled('included_treatments')) {
            $package->treatments()->attach($request->included_treatments);
        }

        return redirect()->route('services.index')->with('success', 'Package created successfully!');
    }

    public function show(Package $package)
    {
        return response()->json([
            'id' => $package->id,
            'name' => $package->name,
            'duration' => $package->total_duration,
            'price' => $package->price,
            'description' => $package->description,
            'included_treatments' => $package->included_treatments ?? [],
        ]);
    }

    public function update(Request $request, Package $package)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'duration' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'included_treatments' => 'nullable|array',
            'included_treatments.*' => 'exists:treatments,id',
        ]);

        $package->update($data);
        $package->treatments()->sync($request->included_treatments ?? []);

        return redirect()->back()->with('success', 'Package updated successfully!');
    }

    public function destroy(Package $package)
    {
        $package->delete();
        return redirect()->route('services.index')->with('success', 'Package deleted successfully!');
    }
}
