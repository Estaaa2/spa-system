<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Services\GoodsReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function __construct(
        protected GoodsReceiptService $goodsReceiptService
    ) {
    }

    public function index(): View
    {
        $user = Auth::user();
        $branchId = $user->currentBranchId();

        $goodsReceipts = GoodsReceipt::query()
            ->where('spa_id', $user->spa_id)
            ->where('branch_id', $branchId)
            ->with([
                'purchaseOrder.supplier',
                'receiver',
                'items.product',
                'items.productBatch',
                'items.purchaseOrderItem',
            ])
            ->latest('received_at')
            ->latest('id')
            ->paginate(10);

        $receivablePurchaseOrders = PurchaseOrder::query()
            ->where('spa_id', $user->spa_id)
            ->where('branch_id', $branchId)
            ->whereIn('status', [
                PurchaseOrder::STATUS_ISSUED,
                PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            ])
            ->with([
                'supplier',
                'items.product',
                'items.goodsReceiptItems',
                'goodsReceipts.items',
            ])
            ->latest()
            ->get();

        return view('inventory.goods-receipts.index', compact(
            'goodsReceipts',
            'receivablePurchaseOrders'
        ));
    }

    public function store(Request $request,PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $user = Auth::user();

        $this->ensureAccessible($purchaseOrder,$user);

        $validated = $request->validateWithBag('receiveGoods', [
            'delivery_reference' => ['nullable', 'string', 'max:255'],
            'received_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],

            'items.*.purchase_order_item_id' => [
                'required',
                'integer',
            ],

            'items.*.received_quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'items.*.accepted_quantity' => [
                'required',
                'numeric',
                'min:0',
            ],

            'items.*.rejected_quantity' => [
                'required',
                'numeric',
                'min:0',
            ],

            'items.*.batch_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'items.*.unit_cost' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'items.*.manufactured_at' => [
                'nullable',
                'date',
            ],

            'items.*.expiration_date' => [
                'nullable',
                'date',
            ],

            'items.*.rejection_reason' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'items.*.notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        try {
            $goodsReceipt = $this->goodsReceiptService->receive(
                $purchaseOrder,
                $user,
                $validated['items'],
                $validated['delivery_reference'] ?? null,
                $validated['received_at'] ?? null,
                $validated['notes'] ?? null
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $message = collect($errors)->flatten()->first();

            return back()
                ->withErrors($errors, 'receiveGoods')
                ->withInput()
                ->with('error', $message);
        }

        return redirect()
            ->route('inventory.goods-receipts.index')
            ->with(
                'success',
                'Goods receipt #' .
                str_pad($goodsReceipt->id, 5, '0', STR_PAD_LEFT) .
                ' posted successfully.'
            );
    }

    private function ensureAccessible(PurchaseOrder $purchaseOrder,$user): void
    {
        abort_unless(
            (int) $purchaseOrder->spa_id === (int) $user->spa_id &&
            (int) $purchaseOrder->branch_id === (int) $user->currentBranchId(),
            403
        );
    }
}
