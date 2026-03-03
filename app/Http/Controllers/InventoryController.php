<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function products(Request $request)
    {
        $user = $request->user();
        $spaId = $user->spa_id; // adjust if your spa relation differs

        $products = Product::where('spa_id', $spaId)
            ->orderBy('name')
            ->paginate(10);

        return view('inventory.products', compact('products'));
    }

    public function deduct(Request $request, Product $product)
    {
        $user = $request->user();
        $spaId = $user->spa_id;

        abort_unless($product->spa_id === $spaId, 403);

        $data = $request->validate([
            'amount' => ['required','integer','min:1'],
        ]);

        $amount = (int) $data['amount'];

        DB::transaction(function () use ($product, $amount, $spaId, $user) {

            $p = Product::whereKey($product->id)->lockForUpdate()->first();

            if ($p->stock_quantity < $amount) {
                abort(422, 'Not enough stock to deduct.');
            }

            $p->decrement('stock_quantity', $amount);

            ProductLog::create([
                'spa_id'     => $spaId,
                'product_id' => $p->id,
                'user_id'    => $user->id,
                'description'=> "{$p->name} has been deduc ({$amount} stock)",
                'logged_at'  => now(),
            ]);
        });

        return back()->with('success', 'Stock deducted successfully.');
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $spaId = $user->spa_id; // adjust if you use $user->spa->id

        $data = $request->validate([
            'name'            => ['required','string','max:255'],
            'brand'           => ['nullable','string','max:255'],
            'stock_quantity'  => ['required','integer','min:0'],
            'unit' => ['nullable','integer','min:0'],
            'expiration_date' => ['nullable','date'],
        ]);

        Product::create([
            'spa_id'           => $spaId,
            'name'             => $data['name'],
            'brand'            => $data['brand'] ?? null,
            'stock_quantity'   => $data['stock_quantity'],
            'unit' => $data['unit'] ?? null,
            'expiration_date'  => $data['expiration_date'] ?? null,
        ]);

        return back()->with('success', 'Product added successfully.');
    }

    public function update(Request $request, Product $product)
    {
        $user = $request->user();
        $spaId = $user->spa_id;

        abort_unless($product->spa_id === $spaId, 403);

        $data = $request->validate([
            'name'            => ['required','string','max:255'],
            'brand'           => ['nullable','string','max:255'],
            'stock_quantity'  => ['required','integer','min:0'],
            'unit'            => ['nullable','integer','min:0'],
            'expiration_date' => ['nullable','date'],
        ]);

        $product->update([
            'name'            => $data['name'],
            'brand'           => $data['brand'] ?? null,
            'stock_quantity'  => $data['stock_quantity'],
            'unit'            => $data['unit'] ?? 0,
            'expiration_date' => $data['expiration_date'] ?? null,
        ]);

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Request $request, Product $product)
    {
        $user = $request->user();
        abort_unless($product->spa_id === $user->spa_id, 403);

        $productName = $product->name;

        ProductLog::create([
            'spa_id'     => $product->spa_id,
            'product_id' => $product->id,
            'user_id'    => $user->id,
            'description'=> "{$productName} has been deleted from inventory",
            'logged_at'  => now(),
        ]);

        $product->delete();

        return back()->with('success', 'Product deleted successfully.');
    }

    public function logs(Request $request)
    {
        $user = $request->user();
        $spaId = $user->spa_id;

        $logs = ProductLog::where('spa_id', $spaId)
            ->orderByDesc('logged_at')
            ->paginate(20);

        return view('inventory.logs', compact('logs'));
    }
}
