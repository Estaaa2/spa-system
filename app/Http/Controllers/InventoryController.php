<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function products(Request $request)
    {
        $user = $request->user();
        $spaId = $user->spa_id;

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
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => 'Not enough stock to deduct.',
                ]);
            }

            $p->decrement('stock_quantity', $amount);

            ProductLog::create([
                'spa_id'     => $spaId,
                'product_id' => $p->id,
                'user_id'    => $user->id,
                'description'=> "{$p->name} has been deducted ({$amount} stock)",
                'logged_at'  => now(),
            ]);
        });

        return back()->with('success', 'Stock deducted successfully.');
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $spaId = $user->spa_id;

        $data = $request->validate([
            'name'            => ['required','string','max:255'],
            'brand'           => ['nullable','string','max:255'],
            'stock_quantity'  => ['required','integer','min:0'],
            'unit_value'      => ['nullable','integer','min:0'],
            'unit'            => ['nullable','string','max:20'],
            'expiration_date' => ['nullable','date'],
        ]);

        $product = Product::create([
            'spa_id'           => $spaId,
            'name'             => $data['name'],
            'brand'            => $data['brand'] ?? null,
            'stock_quantity'   => $data['stock_quantity'],
            'unit_value'       => $data['unit_value'] ?? 0,
            'unit'             => $data['unit'] ?? 'ml',
            'expiration_date'  => $data['expiration_date'] ?? null,
        ]);

        // Log the creation
        ProductLog::create([
            'spa_id'     => $spaId,
            'product_id' => $product->id,
            'user_id'    => $user->id,
            'description'=> "{$product->name} has been added to inventory ({$product->stock_quantity} stock)",
            'logged_at'  => now(),
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
            'unit_value'      => ['nullable','integer','min:0'],
            'unit'            => ['nullable','string','max:20'],
            'expiration_date' => ['nullable','date'],
        ]);

        $oldName = $product->name;
        $oldStock = $product->stock_quantity;

        $product->update([
            'name'            => $data['name'],
            'brand'           => $data['brand'] ?? null,
            'stock_quantity'  => $data['stock_quantity'],
            'unit_value'      => $data['unit_value'] ?? 0,
            'unit'            => $data['unit'] ?? 'ml',
            'expiration_date' => $data['expiration_date'] ?? null,
        ]);

        // Log the update
        $changes = [];
        if ($oldName !== $product->name) {
            $changes[] = "name changed from '{$oldName}' to '{$product->name}'";
        }
        if ($oldStock !== $product->stock_quantity) {
            $changes[] = "stock updated from {$oldStock} to {$product->stock_quantity}";
        }

        $description = $product->name . " updated";
        if (!empty($changes)) {
            $description .= " (" . implode(', ', $changes) . ")";
        }

        ProductLog::create([
            'spa_id'     => $spaId,
            'product_id' => $product->id,
            'user_id'    => $user->id,
            'description'=> $description,
            'logged_at'  => now(),
        ]);

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Request $request, Product $product)
    {
        $user = $request->user();
        $spaId = $user->spa_id;

        abort_unless($product->spa_id === $spaId, 403);

        $productName = $product->name;

        ProductLog::create([
            'spa_id'     => $spaId,
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

        // Verify spa_id exists
        if (!$spaId) {
            return back()->with('error', 'No spa associated with your account.');
        }

        $query = ProductLog::where('spa_id', $spaId);

        // Filter by month
        if ($request->filled('month')) {
            $month = $request->month;
            $year = substr($month, 0, 4);
            $monthNum = substr($month, 5, 2);

            $query->whereYear('logged_at', $year)
                ->whereMonth('logged_at', $monthNum);
        }

        $logs = $query->orderByDesc('logged_at')
            ->paginate(20);

        return view('inventory.logs', compact('logs'));
    }

    public function exportLogsPdf(Request $request)
    {
        $user = $request->user();
        $spaId = $user->spa_id;

        // Verify spa_id exists
        if (!$spaId) {
            return back()->with('error', 'No spa associated with your account.');
        }

        $spa = $user->spa; // Using the relationship from User model

        // Get the current branch using the method from User model
        $branchId = $user->currentBranchId();
        $branch = null;

        if ($branchId) {
            $branch = \App\Models\Branch::find($branchId);
        }

        // If no branch found, try to get any branch for this spa
        if (!$branch) {
            $branch = \App\Models\Branch::where('spa_id', $spaId)->first();
        }

        $query = ProductLog::where('spa_id', $spaId);

        $selectedMonth = null;
        if ($request->filled('month')) {
            $month = $request->month;
            $year = substr($month, 0, 4);
            $monthNum = substr($month, 5, 2);

            $query->whereYear('logged_at', $year)
                ->whereMonth('logged_at', $monthNum);

            $selectedMonth = \Carbon\Carbon::createFromDate($year, $monthNum, 1)
                ->format('F Y');
        }

        $logs = $query->orderByDesc('logged_at')->get();

        $data = [
            'logs' => $logs,
            'selectedMonth' => $selectedMonth,
            'branchName' => $branch?->name ?? 'All Branches',
            'spaName' => $spa?->name ?? 'N/A',
            'generatedAt' => now()->format('F d, Y h:i A'),
            'totalLogs' => $logs->count(),
        ];

        $pdf = Pdf::loadView('inventory.logs-pdf', $data);
        $pdf->setPaper('A4', 'landscape');

        $filename = 'inventory-logs-' . ($selectedMonth ? str_replace(' ', '-', $selectedMonth) : 'all') . '.pdf';

        return $pdf->download($filename);
    }
}
