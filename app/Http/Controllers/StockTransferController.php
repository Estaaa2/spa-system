<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockTransferController extends Controller
{
    private const MAX_TRANSFER_QUANTITY = 20000;

    public function index(Request $request)
    {
        $user = $request->user();
        $spaId = $user->spa_id;
        $branchId = $user->currentBranchId();

        $transfers = StockTransfer::query()
            ->with([
                'sourceBranch',
                'destinationBranch',
                'product',
                'requestedBy',
                'completedBy',
                'items',
            ])
            ->where('spa_id', $spaId)
            ->when($branchId, function ($query) use ($branchId) {
                $query->where(function ($subQuery) use ($branchId) {
                    $subQuery
                        ->where('source_branch_id', $branchId)
                        ->orWhere('destination_branch_id', $branchId);
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->filled('product_id'), function ($query) use ($request) {
                $query->where('product_id', $request->product_id);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $branches = Branch::query()
            ->where('spa_id', $spaId)
            ->orderBy('name')
            ->get();

        $products = Product::query()
            ->where('spa_id', $spaId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('inventory.transfers', compact(
            'transfers',
            'branches',
            'products',
            'branchId'
        ));
    }

    public function store(
        Request $request,
        StockTransferService $stockTransferService
    ) {
        $user = $request->user();
        $spaId = $user->spa_id;
        $sourceBranchId = $user->currentBranchId();

        if (!$sourceBranchId) {
            throw ValidationException::withMessages([
                'source_branch_id' => 'A source branch is required before transferring stock.',
            ])->errorBag('stockTransfer');
        }

        $data = $request->validateWithBag('stockTransfer', [
            'destination_branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(
                    fn ($query) => $query->where('spa_id', $spaId)
                ),
            ],

            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(
                    fn ($query) => $query
                        ->where('spa_id', $spaId)
                        ->where('is_active', true)
                ),
            ],

            'quantity' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,3',
                'max:' . self::MAX_TRANSFER_QUANTITY,
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ], [
            'destination_branch_id.required' => 'Please select a destination branch.',
            'destination_branch_id.exists' => 'The selected destination branch is invalid.',

            'product_id.required' => 'Please select a product.',
            'product_id.exists' => 'The selected product is invalid or inactive.',

            'quantity.required' => 'Please enter the quantity to transfer.',
            'quantity.gt' => 'Transfer quantity must be greater than zero.',
            'quantity.decimal' => 'Transfer quantity can have a maximum of 3 decimal places.',
            'quantity.max' => 'A single transfer cannot exceed 20,000 units.',

            'notes.max' => 'Transfer notes cannot exceed 1,000 characters.',
        ]);

        if ((int) $data['destination_branch_id'] === (int) $sourceBranchId) {
            throw ValidationException::withMessages([
                'destination_branch_id' => 'The destination branch must be different from the source branch.',
            ])->errorBag('stockTransfer');
        }

        $transfer = $stockTransferService->createPendingTransfer(
            $user,
            (int) $sourceBranchId,
            (int) $data['destination_branch_id'],
            (int) $data['product_id'],
            (float) $data['quantity'],
            $data['notes'] ?? null
        );

        return redirect()
            ->route('inventory.transfers')
            ->with(
                'success',
                'Stock transfer created successfully and is ready for processing.'
            );
    }

    public function process(Request $request,StockTransfer $transfer,StockTransferService $stockTransferService)
    {
        $user = $request->user();

        if ((int) $transfer->spa_id !== (int) $user->spa_id) {
            abort(403);
        }

        if (
            (int) $transfer->source_branch_id !==
            (int) $user->currentBranchId()
        ) {
            abort(403);
        }

        $stockTransferService->processTransfer(
            $transfer,
            $user
        );

        return redirect()
            ->route('inventory.transfers')
            ->with(
                'success',
                'Stock transfer processed successfully.'
            );
    }

    public function cancel(Request $request, StockTransfer $transfer)
    {
        $user = $request->user();

        if (!$user->hasBranchPermission('cancel stock transfers')) {
            abort(403);
        }

        if ((int) $transfer->spa_id !== (int) $user->spa_id) {
            abort(403);
        }

        if (
            (int) $transfer->source_branch_id !==
            (int) $user->currentBranchId()
        ) {
            abort(403);
        }

        if ($transfer->status !== 'pending') {
            throw ValidationException::withMessages([
                'transfer' => 'Only pending stock transfers can be cancelled.',
            ])->errorBag('stockTransfer');
        }

        $transfer->update([
            'status' => 'cancelled',
        ]);

        return redirect()
            ->route('inventory.transfers')
            ->with('success', 'Stock transfer cancelled successfully.');
    }

}
