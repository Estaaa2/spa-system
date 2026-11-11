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
            'discount_value' => $this->discountValueRules($request),
            'start_date'     => ['required', 'date'],
            'end_date'       => ['required', 'date', 'after_or_equal:start_date'],
            'treatment_ids'  => ['nullable', 'array'],
            'treatment_ids.*'=> [$this->selectableRule('treatments')],
            'package_ids'    => ['nullable', 'array'],
            'package_ids.*'  => [$this->selectableRule('packages')],
        ], $this->discountValueMessages());


        if ($error = $this->selectionError($validated)) {
            return back()->withErrors($error)->withInput();
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
            'discount_value' => $this->discountValueRules($request),
            'start_date'     => ['required', 'date'],
            'end_date'       => ['required', 'date', 'after_or_equal:start_date'],
            'is_active'      => ['boolean'],
            'treatment_ids'  => ['nullable', 'array'],
            'treatment_ids.*'=> [$this->selectableRule('treatments')],
            'package_ids'    => ['nullable', 'array'],
            'package_ids.*'  => [$this->selectableRule('packages')],
        ], $this->discountValueMessages());

        if ($error = $this->selectionError($validated)) {
            return back()->withErrors($error)->withInput();
        }

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

    /**
     * A percent discount stops at 100; a fixed-amount discount stops at the
     * highest price a package may have (config/limits.php), and selectionError()
     * also keeps it below the cheapest selected item. Update used to skip the
     * 100% check entirely.
     */
    private function discountValueRules(Request $request): array
    {
        $max = $request->input('discount_type') === 'percent'
            ? 100
            : config('limits.promo_fixed_discount');

        return ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:' . $max];
    }

    private function discountValueMessages(): array
    {
        return [
            'discount_value.max' => request('discount_type') === 'percent'
                ? 'Percentage discount cannot exceed 100%.'
                : 'Fixed discount cannot be more than ₱' . number_format(config('limits.promo_fixed_discount')) . '.',
            'discount_value.gt' => 'Discount value must be greater than zero.',
        ];
    }

    /**
     * Checks that need the selected items: at least one must be picked (update
     * used to skip this), and a fixed discount must be lower than the price of
     * every selected item. Promo::priceFor() floors at ₱0, so ₱500 off a ₱300
     * treatment would otherwise make it free.
     */
    private function selectionError(array $validated): ?array
    {
        $treatmentIds = $validated['treatment_ids'] ?? [];
        $packageIds   = $validated['package_ids'] ?? [];

        if (empty($treatmentIds) && empty($packageIds)) {
            return ['treatment_ids' => 'Select at least one treatment or package.'];
        }

        if ($validated['discount_type'] !== 'fixed') {
            return null;
        }

        // Ids were already limited to this spa and branch by selectableRule().
        $cheapest = Treatment::withoutGlobalScope('spa_branch')->whereIn('id', $treatmentIds)->get(['name', 'price'])
            ->concat(Package::withoutGlobalScope('spa_branch')->whereIn('id', $packageIds)->get(['name', 'price']))
            ->sortBy(fn ($item) => (float) $item->price)
            ->first();

        if ($cheapest && (float) $validated['discount_value'] >= (float) $cheapest->price) {
            return ['discount_value' => 'A fixed discount must be lower than the price of every selected item. "'
                . $cheapest->name . '" costs ₱' . number_format((float) $cheapest->price, 2) . '.'];
        }

        return null;
    }
}
