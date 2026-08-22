<?php

namespace App\Http\Controllers;

use App\Models\Promo;
use App\Models\Treatment;
use App\Models\Package;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    public function index(Request $request)
    {
        $spaId    = auth()->user()->spa_id;
        $branchId = session('current_branch_id') ?? auth()->user()->branch_id;

        $promos = Promo::with(['treatments', 'packages'])
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->latest()
            ->get();

        $treatments = Treatment::withoutGlobalScopes()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->get();

        $packages = Package::withoutGlobalScopes()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->get();

        return view('services.promos.index', compact('promos', 'treatments', 'packages'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'discount_type'  => ['required', 'in:percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'start_date'     => ['required', 'date'],
            'end_date'       => ['required', 'date', 'after_or_equal:start_date'],
            'treatment_ids'  => ['nullable', 'array'],
            'treatment_ids.*'=> ['exists:treatments,id'],
            'package_ids'    => ['nullable', 'array'],
            'package_ids.*'  => ['exists:packages,id'],
        ]);

        if ($validated['discount_type'] === 'percent' && $validated['discount_value'] > 100) {
            return back()->withErrors(['discount_value' => 'Percentage discount cannot exceed 100%.']);
        }

        if (empty($validated['treatment_ids']) && empty($validated['package_ids'])) {
            return back()->withErrors(['treatment_ids' => 'Select at least one treatment or package.']);
        }

        $promo = Promo::create([
            'spa_id'    => auth()->user()->spa_id,
            'branch_id' => session('current_branch_id') ?? auth()->user()->branch_id,
            'name'           => $validated['name'],
            'discount_type'  => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'start_date'     => $validated['start_date'],
            'end_date'       => $validated['end_date'],
            'is_active'      => true,
        ]);

        $promo->treatments()->sync($validated['treatment_ids'] ?? []);
        $promo->packages()->sync($validated['package_ids'] ?? []);

        return back()->with('success', 'Promo created successfully.');
    }

    public function update(Request $request, Promo $promo)
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'discount_type'  => ['required', 'in:percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'start_date'     => ['required', 'date'],
            'end_date'       => ['required', 'date', 'after_or_equal:start_date'],
            'is_active'      => ['boolean'],
            'treatment_ids'  => ['nullable', 'array'],
            'package_ids'    => ['nullable', 'array'],
        ]);

        $promo->update($validated);
        $promo->treatments()->sync($validated['treatment_ids'] ?? []);
        $promo->packages()->sync($validated['package_ids'] ?? []);

        return back()->with('success', 'Promo updated successfully.');
    }

    public function destroy(Promo $promo)
    {
        $promo->delete(); // pivot rows cascade via FK
        return back()->with('success', 'Promo deleted.');
    }
}
