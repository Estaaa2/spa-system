<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(
        protected PurchaseOrderService $purchaseOrderService
    ) {
    }

    public function index(): View
    {
        $user = Auth::user();
        $branchId = $user->currentBranchId();

        $purchaseOrders = PurchaseOrder::query()
            ->where('spa_id', $user->spa_id)
            ->where('branch_id', $branchId)
            ->with([
                'purchaseRequest.requester',
                'supplier',
                'creator',
                'items.product',
                'statusHistory.changedBy',
            ])
            ->latest()
            ->paginate(10);

        $approvedRequests = PurchaseRequest::query()
            ->where('spa_id', $user->spa_id)
            ->where('branch_id', $branchId)
            ->where('status', PurchaseRequest::STATUS_APPROVED)
            ->whereDoesntHave('purchaseOrder')
            ->with([
                'requester',
                'items.product',
            ])
            ->latest()
            ->get();

        $suppliers = Supplier::query()
            ->where('spa_id', $user->spa_id)
            ->where('status', 'active')
            ->whereHas('products')
            ->orderBy('name')
            ->get();

        return view('procurement.purchase-orders.index', compact(
            'purchaseOrders',
            'approvedRequests',
            'suppliers'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validateWithBag('createPurchaseOrder', [
            'purchase_request_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $purchaseRequest = PurchaseRequest::findOrFail(
            $validated['purchase_request_id']
        );

        $this->ensurePurchaseRequestAccessible($purchaseRequest,$user);

        $supplier = Supplier::findOrFail(
            $validated['supplier_id']
        );

        try {
            $purchaseOrder = $this->purchaseOrderService->createFromPurchaseRequest(
                $purchaseRequest,
                $supplier,
                $user,
                $validated['notes'] ?? null,
                $validated['expected_delivery_date'] ?? null
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $message = collect($errors)->flatten()->first();

            return back()
                ->withErrors($errors, 'createPurchaseOrder')
                ->withInput()
                ->with('error', $message);
        }

        return redirect()
            ->route('procurement.purchase-orders.index')
            ->with(
                'success',
                'Purchase order #' .
                str_pad($purchaseOrder->id, 5, '0', STR_PAD_LEFT) .
                ' created successfully.'
            );
    }

    public function issue(Request $request,PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $user = Auth::user();

        $this->ensureAccessible($purchaseOrder,$user);

        $validated = $request->validateWithBag('issuePurchaseOrder', [
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->purchaseOrderService->issue(
                $purchaseOrder,
                $user,
                $validated['reason'] ?? null
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $message = collect($errors)->flatten()->first();

            return back()
                ->withErrors($errors, 'issuePurchaseOrder')
                ->with('error', $message);
        }

        return redirect()
            ->route('procurement.purchase-orders.index')
            ->with('success', 'Purchase order issued successfully.');
    }

    public function cancel(Request $request,PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $user = Auth::user();

        $this->ensureAccessible($purchaseOrder,$user);

        $validated = $request->validateWithBag('cancelPurchaseOrder', [
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->purchaseOrderService->cancel(
                $purchaseOrder,
                $user,
                $validated['reason']
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $message = collect($errors)->flatten()->first();

            return back()
                ->withErrors($errors, 'cancelPurchaseOrder')
                ->withInput()
                ->with('error', $message);
        }

        return redirect()
            ->route('procurement.purchase-orders.index')
            ->with('success', 'Purchase order cancelled successfully.');
    }

    private function ensureAccessible(PurchaseOrder $purchaseOrder,$user): void
    {
        abort_unless(
            (int) $purchaseOrder->spa_id === (int) $user->spa_id &&
            (int) $purchaseOrder->branch_id === (int) $user->currentBranchId(),
            403
        );
    }

    private function ensurePurchaseRequestAccessible(PurchaseRequest $purchaseRequest,$user): void
    {
        abort_unless(
            (int) $purchaseRequest->spa_id === (int) $user->spa_id &&
            (int) $purchaseRequest->branch_id === (int) $user->currentBranchId(),
            403
        );
    }
}
