<?php

namespace App\Http\Controllers;
use App\Models\Treatment;
use Illuminate\Http\Request;

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
        ]);

        $branchId = session('current_branch_id') ?? auth()->user()->branch_id;

        if (!$branchId) {
            return back()->withErrors(['branch_id' => 'Please select a branch before adding a treatment.'])->withInput();
        }

        Treatment::create([
            'spa_id'       => auth()->user()->spa_id,
            'branch_id'    => $branchId,
            'name'         => $validated['name'],
            'duration'     => $validated['duration'],
            'price'        => $validated['price'],
            'service_type' => $validated['service_type'],
            'description'  => $validated['description'] ?? null,
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
            'name'         => 'required|string|max:255',
            'duration'     => 'required|integer|min:1',
            'price'        => 'required|numeric|min:0',
            'service_type' => 'required|in:in_branch_only,in_branch_and_home',
            'description'  => 'nullable|string',
        ]);

        $treatment->update($validated);

        return redirect()
            ->route('services.index')
            ->with('success', 'Treatment updated successfully.');
    }

    public function destroy(Treatment $treatment)
    {
        $treatment->delete();

        return redirect()
            ->route('services.index')
            ->with('success', 'Treatment deleted successfully.');
    }
}
