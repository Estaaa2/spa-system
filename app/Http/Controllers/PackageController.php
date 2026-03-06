<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'duration'              => 'nullable|integer|min:5',
            'price'                 => 'required|numeric|min:0',
            'included_treatments'   => 'nullable|array',
            'included_treatments.*' => 'exists:treatments,id',
            'description'           => 'nullable|string',
        ]);

        $package = Package::create([
            'spa_id'         => auth()->user()->spa_id,
            'branch_id'      => session('current_branch_id') ?? auth()->user()->branch_id,
            'name'           => $validated['name'],
            'total_duration' => $validated['duration'] ?? null,
            'price'          => $validated['price'],
            'description'    => $validated['description'] ?? null,
        ]);

        if (!empty($validated['included_treatments'])) {
            $package->treatments()->sync($validated['included_treatments']);
        }

        return redirect()
            ->route('services.index')
            ->with('success', 'Package created successfully!');
    }

    public function show(Package $package)
    {
        return response()->json([
            'id'                   => $package->id,
            'name'                 => $package->name,
            'duration'             => $package->total_duration,
            'price'                => $package->price,
            'description'          => $package->description,
            'included_treatments'  => $package->treatments->pluck('id'),
        ]);
    }

    public function update(Request $request, Package $package)
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'duration'              => 'required|integer|min:1',
            'price'                 => 'required|numeric|min:0',
            'description'           => 'nullable|string',
            'included_treatments'   => 'nullable|array',
            'included_treatments.*' => 'exists:treatments,id',
        ]);

        $package->update([
            'name'           => $validated['name'],
            'total_duration' => $validated['duration'],
            'price'          => $validated['price'],
            'description'    => $validated['description'] ?? null,
        ]);

        $package->treatments()->sync($validated['included_treatments'] ?? []);

        return redirect()
            ->route('services.index')
            ->with('success', 'Package updated successfully!');
    }

    public function destroy(Package $package)
    {
        $package->delete();

        return redirect()
            ->route('services.index')
            ->with('success', 'Package deleted successfully!');
    }
}
