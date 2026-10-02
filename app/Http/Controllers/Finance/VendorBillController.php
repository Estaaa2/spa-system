<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\ThreeWayMatchService;
use App\Models\User;
use App\Models\VendorBill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VendorBillController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $branchId = $user->currentBranchId();

        $vendorBills = VendorBill::query()
            ->where('spa_id', $user->spa_id)
            ->where('branch_id', $branchId)
            ->with([
                'supplier',
                'purchaseOrder',
                'creator',
                'items.product',
            ])
            ->latest()
            ->paginate(10);

        $billablePurchaseOrders = PurchaseOrder::query()
            ->where('spa_id', $user->spa_id)
            ->where('branch_id', $branchId)
            ->whereIn('status', [
                PurchaseOrder::STATUS_ISSUED,
                PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                PurchaseOrder::STATUS_RECEIVED,
            ])
            ->with([
                'supplier',
                'items.product',
            ])
            ->latest()
            ->get();

        return view('finance.vendor-bills.index', compact(
            'vendorBills',
            'billablePurchaseOrders'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $branchId = $user->currentBranchId();

        $validated = $request->validateWithBag('createVendorBill', [
            'purchase_order_id' => ['required', 'integer'],
            'invoice_number' => ['required', 'string', 'max:100'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => [
                'required',
                'integer',
                'distinct',
            ],
            'items.*.billed_quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'items.*.unit_cost' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ]);

        try {
            $vendorBill = DB::transaction(function () use (
                $validated,
                $user,
                $branchId
            ) {
                $purchaseOrder = PurchaseOrder::query()
                    ->where('id', $validated['purchase_order_id'])
                    ->where('spa_id', $user->spa_id)
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();

                if (!$purchaseOrder) {
                    throw ValidationException::withMessages([
                        'purchase_order_id' =>
                            'The selected purchase order does not belong to your current branch.',
                    ]);
                }

                if (!in_array($purchaseOrder->status, [
                    PurchaseOrder::STATUS_ISSUED,
                    PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                    PurchaseOrder::STATUS_RECEIVED,
                ], true)) {
                    throw ValidationException::withMessages([
                        'purchase_order_id' =>
                            'Only issued or received purchase orders can be billed.',
                    ]);
                }

                $invoiceNumber = trim($validated['invoice_number']);

                $duplicateInvoice = VendorBill::query()
                    ->where('spa_id', $user->spa_id)
                    ->where('supplier_id', $purchaseOrder->supplier_id)
                    ->where('invoice_number', $invoiceNumber)
                    ->exists();

                if ($duplicateInvoice) {
                    throw ValidationException::withMessages([
                        'invoice_number' =>
                            'This supplier invoice number has already been recorded.',
                    ]);
                }

                $submittedItems = collect($validated['items']);

                $purchaseOrderItems = PurchaseOrderItem::query()
                    ->where('purchase_order_id', $purchaseOrder->id)
                    ->whereIn(
                        'id',
                        $submittedItems->pluck('purchase_order_item_id')
                    )
                    ->with('product')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($purchaseOrderItems->count() !== $submittedItems->count()) {
                    throw ValidationException::withMessages([
                        'items' =>
                            'One or more billed items do not belong to the selected purchase order.',
                    ]);
                }

                $preparedItems = [];
                $subtotal = 0;

                foreach ($submittedItems as $submittedItem) {
                    $purchaseOrderItem = $purchaseOrderItems->get(
                        (int) $submittedItem['purchase_order_item_id']
                    );

                    if (!$purchaseOrderItem) {
                        throw ValidationException::withMessages([
                            'items' =>
                                'One or more billed items are invalid.',
                        ]);
                    }

                    $billedQuantity = round(
                        (float) $submittedItem['billed_quantity'],
                        3
                    );

                    $unitCost = round(
                        (float) $submittedItem['unit_cost'],
                        2
                    );

                    $lineTotal = round(
                        $billedQuantity * $unitCost,
                        2
                    );

                    $subtotal += $lineTotal;

                    $preparedItems[] = [
                        'purchase_order_item_id' => $purchaseOrderItem->id,
                        'product_id' => $purchaseOrderItem->product_id,
                        'billed_quantity' => $billedQuantity,
                        'unit' => $purchaseOrderItem->unit,
                        'unit_cost' => $unitCost,
                        'line_total' => $lineTotal,
                    ];
                }

                $subtotal = round($subtotal, 2);

                $vendorBill = VendorBill::create([
                    'spa_id' => $purchaseOrder->spa_id,
                    'branch_id' => $purchaseOrder->branch_id,
                    'supplier_id' => $purchaseOrder->supplier_id,
                    'purchase_order_id' => $purchaseOrder->id,
                    'created_by' => $user->id,
                    'invoice_number' => $invoiceNumber,
                    'invoice_date' => $validated['invoice_date'],
                    'due_date' => $validated['due_date'] ?? null,
                    'subtotal' => $subtotal,
                    'total_amount' => $subtotal,
                    'status' => VendorBill::STATUS_PENDING_MATCH,
                    'notes' => $validated['notes'] ?? null,
                ]);

                $vendorBill->items()->createMany($preparedItems);

                return $vendorBill;
            });
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $message = collect($errors)->flatten()->first();

            return back()
                ->withErrors($errors, 'createVendorBill')
                ->withInput()
                ->with('error', $message);
        }

        return redirect()
            ->route('vendor-bills.index')
            ->with(
                'success',
                'Vendor bill ' .
                $vendorBill->invoice_number .
                ' created and queued for 3-way matching.'
            );
    }

    public function update(Request $request,VendorBill $vendorBill): RedirectResponse
    {
        $user = Auth::user();

        $this->ensureAccessible($vendorBill,$user);

        $validated = $request->validateWithBag('editVendorBill', [
            'vendor_bill_id' => ['required', 'integer'],
            'invoice_number' => ['required', 'string', 'max:100'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => [
                'required',
                'integer',
                'distinct',
            ],
            'items.*.billed_quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'items.*.unit_cost' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ]);

        try {
            $vendorBill = DB::transaction(function () use (
                $validated,
                $vendorBill,
                $user
            ) {
                $vendorBill = VendorBill::query()
                    ->where('id', $vendorBill->id)
                    ->where('spa_id', $user->spa_id)
                    ->where('branch_id', $user->currentBranchId())
                    ->with('items')
                    ->lockForUpdate()
                    ->first();

                if (!$vendorBill) {
                    throw ValidationException::withMessages([
                        'vendor_bill' =>
                            'This vendor bill does not belong to your current branch.',
                    ]);
                }

                if (!in_array($vendorBill->status, [
                    VendorBill::STATUS_PENDING_MATCH,
                    VendorBill::STATUS_DISCREPANCY,
                ], true)) {
                    throw ValidationException::withMessages([
                        'status' =>
                            'Only a pending or discrepant vendor bill can be corrected.',
                    ]);
                }

                $invoiceNumber = trim($validated['invoice_number']);

                $duplicateInvoice = VendorBill::query()
                    ->where('spa_id', $vendorBill->spa_id)
                    ->where('supplier_id', $vendorBill->supplier_id)
                    ->where('invoice_number', $invoiceNumber)
                    ->where('id', '!=', $vendorBill->id)
                    ->exists();

                if ($duplicateInvoice) {
                    throw ValidationException::withMessages([
                        'invoice_number' =>
                            'This supplier invoice number has already been recorded.',
                    ]);
                }

                $submittedItems = collect($validated['items'])
                    ->keyBy(fn($item) => (int) $item['id']);

                if (
                    $submittedItems->count() !== $vendorBill->items->count() ||
                    $vendorBill->items->contains(
                        fn($item) => !$submittedItems->has((int) $item->id)
                    )
                ) {
                    throw ValidationException::withMessages([
                        'items' =>
                            'The corrected invoice items must match the original vendor bill items.',
                    ]);
                }

                $subtotal = 0;

                foreach ($vendorBill->items as $billItem) {
                    $submittedItem = $submittedItems->get(
                        (int) $billItem->id
                    );

                    $billedQuantity = round(
                        (float) $submittedItem['billed_quantity'],
                        3
                    );

                    $unitCost = round(
                        (float) $submittedItem['unit_cost'],
                        2
                    );

                    $lineTotal = round(
                        $billedQuantity * $unitCost,
                        2
                    );

                    $subtotal += $lineTotal;

                    $billItem->update([
                        'billed_quantity' => $billedQuantity,
                        'unit_cost' => $unitCost,
                        'line_total' => $lineTotal,
                    ]);
                }

                $subtotal = round($subtotal, 2);

                $vendorBill->update([
                    'invoice_number' => $invoiceNumber,
                    'invoice_date' => $validated['invoice_date'],
                    'due_date' => $validated['due_date'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'subtotal' => $subtotal,
                    'total_amount' => $subtotal,

                    'status' => VendorBill::STATUS_PENDING_MATCH,

                    'match_details' => null,
                    'matched_at' => null,
                ]);

                return $vendorBill->fresh([
                    'supplier',
                    'purchaseOrder',
                    'items.product',
                ]);
            });
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $message = collect($errors)->flatten()->first();

            return back()
                ->withErrors($errors, 'editVendorBill')
                ->withInput()
                ->with('error', $message);
        }

        return redirect()
            ->route('vendor-bills.index')
            ->with(
                'success',
                'Vendor bill corrected successfully. Run the 3-way match again to validate the changes.'
            );
    }

    public function match(
        VendorBill $vendorBill,
        ThreeWayMatchService $threeWayMatchService
    ): RedirectResponse {
        $user = Auth::user();

        $this->ensureAccessible($vendorBill,$user);

        try {
            $vendorBill = $threeWayMatchService->match(
                $vendorBill,
                $user
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $message = collect($errors)->flatten()->first();

            return redirect()
                ->route('vendor-bills.index')
                ->withErrors($errors, 'matchVendorBill')
                ->with('error', $message);
        }

        $message = $vendorBill->status === VendorBill::STATUS_MATCHED
            ? '3-way match completed. The purchase order, goods receipt, and vendor bill match.'
            : '3-way match completed with discrepancies. Review the match details.';

        return redirect()
            ->route('vendor-bills.index')
            ->with(
                $vendorBill->status === VendorBill::STATUS_MATCHED
                    ? 'success'
                    : 'error',
                $message
            );
    }

    private function ensureAccessible(VendorBill $vendorBill,User $user): void
    {
        abort_unless(
            $user->spa_id &&
            (int) $vendorBill->spa_id === (int) $user->spa_id &&
            (int) $vendorBill->branch_id === (int) $user->currentBranchId(),
            403
        );
    }

}
