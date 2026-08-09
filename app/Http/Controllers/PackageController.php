<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'image'                 => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('packages', 'public');
        }

        $package = Package::create([
            'spa_id'         => auth()->user()->spa_id,
            'branch_id'      => session('current_branch_id') ?? auth()->user()->branch_id,
            'name'           => $validated['name'],
            'total_duration' => $validated['duration'] ?? null,
            'price'          => $validated['price'],
            'description'    => $validated['description'] ?? null,
            'image_path'     => $imagePath,
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
            'image_url'            => $package->image_url,
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
            'image'                 => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $imagePath = $package->image_path;
        if ($request->hasFile('image')) {
            if ($package->image_path) {
                Storage::disk('public')->delete($package->image_path);
            }
            $imagePath = $request->file('image')->store('packages', 'public');
        }

        $package->update([
            'name'           => $validated['name'],
            'total_duration' => $validated['duration'],
            'price'          => $validated['price'],
            'description'    => $validated['description'] ?? null,
            'image_path'     => $imagePath,
        ]);

        $package->treatments()->sync($validated['included_treatments'] ?? []);

        return redirect()
            ->route('services.index')
            ->with('success', 'Package updated successfully!');
    }

    public function destroy(Package $package)
    {
        if ($package->image_path) {
            Storage::disk('public')->delete($package->image_path);
        }

        $package->delete();

        return redirect()
            ->route('services.index')
            ->with('success', 'Package deleted successfully!');
    }
}
