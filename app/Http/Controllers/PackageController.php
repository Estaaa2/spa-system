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
            'image'                 => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'image_2'               => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'cover'                 => 'nullable|in:1,2',
        ]);

        [$imagePath, $imagePath2] = $this->resolveImages($request, 'packages');

        $package = Package::create([
            'spa_id'         => auth()->user()->spa_id,
            'branch_id'      => session('current_branch_id') ?? auth()->user()->branch_id,
            'name'           => $validated['name'],
            'total_duration' => $validated['duration'] ?? null,
            'price'          => $validated['price'],
            'description'    => $validated['description'] ?? null,
            'image_path'     => $imagePath,
            'image_path_2'   => $imagePath2,
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
            'image_url_2'          => $package->image_url_2,
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
            'image'                 => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'image_2'               => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'remove_image'          => 'nullable|boolean',
            'remove_image_2'        => 'nullable|boolean',
            'cover'                 => 'nullable|in:1,2',
        ]);

        [$imagePath, $imagePath2] = $this->resolveImages(
            $request,
            'packages',
            $package->image_path,
            $package->image_path_2
        );

        $package->update([
            'name'           => $validated['name'],
            'total_duration' => $validated['duration'],
            'price'          => $validated['price'],
            'description'    => $validated['description'] ?? null,
            'image_path'     => $imagePath,
            'image_path_2'   => $imagePath2,
        ]);

        $package->treatments()->sync($validated['included_treatments'] ?? []);

        return redirect()
            ->route('services.index')
            ->with('success', 'Package updated successfully!');
    }

    public function destroy(Package $package)
    {
        foreach ([$package->image_path, $package->image_path_2] as $path) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
        }

        $package->delete();

        return redirect()
            ->route('services.index')
            ->with('success', 'Package deleted successfully!');
    }

    /**
     * Works out the two image paths after an add or edit.
     * Each slot: a new upload replaces the old file; otherwise a ticked
     * "remove" box deletes it; otherwise it is kept as is.
     * cover=2 swaps the slots, and a lone second image moves up to slot 1,
     * so image_path is always the cover.
     */
    private function resolveImages(Request $request, string $folder, ?string $first = null, ?string $second = null): array
    {
        $disk = Storage::disk('public');

        if ($request->hasFile('image')) {
            if ($first) {
                $disk->delete($first);
            }
            $first = $request->file('image')->store($folder, 'public');
        } elseif ($request->boolean('remove_image') && $first) {
            $disk->delete($first);
            $first = null;
        }

        if ($request->hasFile('image_2')) {
            if ($second) {
                $disk->delete($second);
            }
            $second = $request->file('image_2')->store($folder, 'public');
        } elseif ($request->boolean('remove_image_2') && $second) {
            $disk->delete($second);
            $second = null;
        }

        if ($request->input('cover') === '2' && $first && $second) {
            [$first, $second] = [$second, $first];
        }

        if (!$first && $second) {
            $first = $second;
            $second = null;
        }

        return [$first, $second];
    }
}
