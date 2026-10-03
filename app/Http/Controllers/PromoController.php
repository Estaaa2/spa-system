<?php

namespace App\Http\Controllers;

use App\Models\Promo;
use App\Models\Treatment;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        // Drop only the named branch scope. withoutGlobalScopes() (plural) also
        // removes SoftDeletes' scope, which listed deleted treatments and packages.
        $treatments = Treatment::withoutGlobalScope('spa_branch')
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->get();

        $packages = Package::withoutGlobalScope('spa_branch')
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->get();

        return view('services.promos.index', compact('promos', 'treatments', 'packages'));
    }

    /**
     * A treatment/package id is only acceptable if it belongs to this spa and
     * branch and has not been deleted. A bare `exists:treatments,id` accepts
     * soft-deleted rows and rows from other spas.
     */
    private function selectableRule(string $table): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists($table, 'id')
            ->where('spa_id', auth()->user()->spa_id)
            ->where('branch_id', session('current_branch_id') ?? auth()->user()->branch_id)
            ->whereNull('deleted_at');
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
            'treatment_ids.*'=> [$this->selectableRule('treatments')],
            'package_ids'    => ['nullable', 'array'],
            'package_ids.*'  => [$this->selectableRule('packages')],
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
            'treatment_ids.*'=> [$this->selectableRule('treatments')],
            'package_ids'    => ['nullable', 'array'],
            'package_ids.*'  => [$this->selectableRule('packages')],
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