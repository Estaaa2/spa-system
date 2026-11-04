<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Product;
use App\Models\Treatment;
use Illuminate\Support\Facades\Auth;

class ServiceController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $branchId = session('current_branch_id') ?? $user->branch_id;

        $treatments = Treatment::where('spa_id', $user->spa_id)
            ->when($branchId, function($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            })
            ->get();

        $packages = Package::where('spa_id', $user->spa_id)
            ->when($branchId, function($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            })
            ->with('treatments')
            ->get();

        $recipeProducts = Product::where('spa_id', $user->spa_id)
            ->where('is_active', true)
            ->whereNotNull('usage_unit')
            ->where('usage_unit', '!=', '')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'brand',
                'usage_unit',
            ]);

        return view('services.index', compact('treatments', 'packages', 'recipeProducts'));
    }
}
