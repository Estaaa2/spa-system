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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'duration' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'service_type' => 'required|in:in_branch_only,in_branch_and_home',
            'description' => 'nullable|string',
        ]);

        Treatment::create([
            'spa_id' => auth()->user()->spa_id,
            'branch_id' => auth()->user()->branch_id,
            'name' => $validated['name'],
            'duration' => $validated['duration'],
            'price' => $validated['price'],
            'service_type' => $validated['service_type'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()
            ->route('services.index')
            ->with('success', 'Treatment created successfully.');
    }

    public function show(Treatment $treatment)
    {
        // Optionally ensure user can only access their own spa/branch
        if ($treatment->spa_id !== auth()->user()->spa_id) {
            abort(403);
        }

        return response()->json($treatment);
    }

    // Update an existing treatment
    public function update(Request $request, Treatment $treatment)
    {
        // Optionally ensure user can only edit their own spa/branch
        if ($treatment->spa_id !== auth()->user()->spa_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'duration' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'service_type' => 'required|in:in_branch_only,in_branch_and_home',
            'description' => 'nullable|string',
        ]);

        $treatment->update($validated);

        return redirect()
            ->route('services.index')
            ->with('success', 'Treatment updated successfully.');
    }

    // Delete a treatment
    public function destroy(Treatment $treatment)
    {
        // Optionally ensure user can only delete their own spa/branch
        if ($treatment->spa_id !== auth()->user()->spa_id) {
            abort(403);
        }

        $treatment->delete();

        return redirect()
            ->route('services.index')
            ->with('success', 'Treatment deleted successfully.');
    }

}
