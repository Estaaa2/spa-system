<?php

namespace App\Http\Controllers;
use App\Models\Treatment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TreatmentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'duration'     => 'required|integer|min:1',
            'price'        => 'required|numeric|min:0',
            'service_type' => 'required|in:in_branch_only,in_branch_and_home',
            'description'  => 'nullable|string',
            'image'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'image_2'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'cover'        => 'nullable|in:1,2',
        ]);

        $branchId = session('current_branch_id') ?? auth()->user()->branch_id;

        if (!$branchId) {
            return back()->withErrors(['branch_id' => 'Please select a branch before adding a treatment.'])->withInput();
        }

        [$imagePath, $imagePath2] = $this->resolveImages($request, 'treatments');

        Treatment::create([
            'spa_id'       => auth()->user()->spa_id,
            'branch_id'    => $branchId,
            'name'         => $validated['name'],
            'duration'     => $validated['duration'],
            'price'        => $validated['price'],
            'service_type' => $validated['service_type'],
            'description'  => $validated['description'] ?? null,
            'image_path'   => $imagePath,
            'image_path_2' => $imagePath2,
        ]);

        return redirect()
            ->route('services.index')
            ->with('success', 'Treatment created successfully.');
    }

    public function show(Treatment $treatment)
    {
        return response()->json($treatment);
    }

    public function update(Request $request, Treatment $treatment)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'duration'       => 'required|integer|min:1',
            'price'          => 'required|numeric|min:0',
            'service_type'   => 'required|in:in_branch_only,in_branch_and_home',
            'description'    => 'nullable|string',
            'image'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'image_2'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'remove_image'   => 'nullable|boolean',
            'remove_image_2' => 'nullable|boolean',
            'cover'          => 'nullable|in:1,2',
        ]);

        [$imagePath, $imagePath2] = $this->resolveImages(
            $request,
            'treatments',
            $treatment->image_path,
            $treatment->image_path_2
        );

        $treatment->update([
            'name'         => $validated['name'],
            'duration'     => $validated['duration'],
            'price'        => $validated['price'],
            'service_type' => $validated['service_type'],
            'description'  => $validated['description'] ?? null,
            'image_path'   => $imagePath,
            'image_path_2' => $imagePath2,
        ]);

        return redirect()
            ->route('services.index')
            ->with('success', 'Treatment updated successfully.');
    }

    public function destroy(Treatment $treatment)
    {
        foreach ([$treatment->image_path, $treatment->image_path_2] as $path) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
        }

        $treatment->delete();

        return redirect()
            ->route('services.index')
            ->with('success', 'Treatment deleted successfully.');
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
