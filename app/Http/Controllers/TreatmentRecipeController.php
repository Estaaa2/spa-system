<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Treatment;
use App\Models\TreatmentRecipeItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TreatmentRecipeController extends Controller
{
    public function show(Treatment $treatment)
    {
        $user = auth()->user();

        if ((int) $treatment->spa_id !== (int) $user->spa_id) {
            abort(403);
        }

        $treatment->load([
            'recipeItems.product:id,name,brand,usage_unit,is_active',
        ]);

        return response()->json([
            'treatment' => [
                'id' => $treatment->id,
                'name' => $treatment->name,
                'branch_id' => $treatment->branch_id,
            ],
            'items' => $treatment->recipeItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'product_brand' => $item->product?->brand,
                    'quantity' => $item->quantity,
                    'usage_unit' => $item->product?->usage_unit,
                    'is_active' => (bool) $item->product?->is_active,
                ];
            })->values(),
        ]);
    }

    public function update(Request $request,Treatment $treatment)
    {
        $user = auth()->user();

        if ((int) $treatment->spa_id !== (int) $user->spa_id) {
            abort(403);
        }

        if ((int) $treatment->branch_id !== (int) $user->currentBranchId()) {
            abort(403);
        }

        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')->where(function ($query) use ($user) {
                    $query
                        ->where('spa_id', $user->spa_id)
                        ->where('is_active', true)
                        ->whereNull('deleted_at');
                }),
            ],
            'items.*.quantity' => [
                'required',
                'numeric',
                'min:0.001',
                'max:20000',
            ],
        ], [
            'items.*.product_id.distinct' => 'The same product cannot be added to the recipe more than once.',
            'items.*.product_id.exists' => 'One or more selected products are unavailable for this spa.',
            'items.*.quantity.min' => 'Recipe quantities must be greater than zero.',
        ]);

        $items = collect($validated['items'] ?? [])->values();

        DB::transaction(function () use ($treatment, $items, $user) {
            $lockedTreatment = Treatment::query()
                ->whereKey($treatment->id)
                ->where('spa_id', $user->spa_id)
                ->where('branch_id', $user->currentBranchId())
                ->lockForUpdate()
                ->firstOrFail();

            $productIds = $items
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($productIds->isNotEmpty()) {
                $validProductCount = Product::query()
                    ->where('spa_id', $user->spa_id)
                    ->where('is_active', true)
                    ->whereIn('id', $productIds)
                    ->count();

                if ($validProductCount !== $productIds->count()) {
                    throw ValidationException::withMessages([
                        'items' => 'One or more selected products are no longer available.',
                    ]);
                }
            }

            TreatmentRecipeItem::query()
                ->where('treatment_id', $lockedTreatment->id)
                ->delete();

            foreach ($items as $item) {
                TreatmentRecipeItem::create([
                    'treatment_id' => $lockedTreatment->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Treatment recipe updated successfully.',
            ]);
        }

        return redirect()
            ->route('services.index')
            ->with('success', 'Treatment recipe updated successfully.');
    }
}
