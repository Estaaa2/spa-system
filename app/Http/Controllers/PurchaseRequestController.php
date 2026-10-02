<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Services\PurchaseRequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PurchaseRequestController extends Controller
{
    public function __construct(
        protected PurchaseRequestService $purchaseRequestService
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $spaId = $user->spa_id;
        $branchId = $user->currentBranchId();

        abort_unless($spaId, 403);

        if (!$branchId) {
            return back()->with('error', 'Please select a branch first.');
        }

        $purchaseRequests = PurchaseRequest::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->with([
                'requester',
                'reviewer',
                'items.product',
            ])
            ->latest()
            ->paginate(10);

        $products = Product::query()
            ->where('spa_id', $spaId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $branch = Branch::query()
            ->where('spa_id', $spaId)
            ->findOrFail($branchId);

        return view('procurement.purchase-requests.index', compact(
            'purchaseRequests',
            'products',
            'branch'
        ));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $branchId = $user->currentBranchId();

        if (!$branchId) {
            return back()->with('error', 'Please select a branch first.');
        }

        $data = $request->validateWithBag('createPurchaseRequest', [
            'reason' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
            ],
            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ]);

        try {
            $this->purchaseRequestService->create(
                $user,
                $branchId,
                $data['items'],
                $data['reason'] ?? null
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $message = collect($errors)->flatten()->first();

            return back()
                ->withErrors(
                    $errors,
                    'createPurchaseRequest'
                )
                ->withInput()
                ->with('error', $message);
        }

        return back()->with(
            'success',
            'Purchase request submitted successfully.'
        );
    }

    public function approve(Request $request,PurchaseRequest $purchaseRequest)
    {
        $user = $request->user();

        $this->ensureAccessible($purchaseRequest,$user);

        $data = $request->validateWithBag('approvePurchaseRequest', [
            'purchase_request_id' => ['nullable', 'integer'],
            'review_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->purchaseRequestService->approve(
                $purchaseRequest,
                $user,
                $data['review_reason'] ?? null
            );
        } catch (ValidationException $exception) {
            return back()
                ->withErrors(
                    $exception->errors(),
                    'approvePurchaseRequest'
                )
                ->withInput();
        }

        return back()->with(
            'success',
            'Purchase request approved successfully.'
        );
    }

    public function reject(Request $request,PurchaseRequest $purchaseRequest)
    {
        $user = $request->user();

        $this->ensureAccessible($purchaseRequest,$user);

        $data = $request->validateWithBag('rejectPurchaseRequest', [
            'purchase_request_id' => ['nullable', 'integer'],
            'review_reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->purchaseRequestService->reject(
                $purchaseRequest,
                $user,
                $data['review_reason']
            );
        } catch (ValidationException $exception) {
            return back()
                ->withErrors(
                    $exception->errors(),
                    'rejectPurchaseRequest'
                )
                ->withInput();
        }

        return back()->with(
            'success',
            'Purchase request rejected.'
        );
    }

    private function ensureAccessible(PurchaseRequest $purchaseRequest,$user): void
    {
        abort_unless(
            (int) $purchaseRequest->spa_id === (int) $user->spa_id &&
            (int) $purchaseRequest->branch_id === (int) $user->currentBranchId(),
            403
        );
    }
}
