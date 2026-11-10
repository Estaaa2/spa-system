<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\SupplierService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    /** Philippine mobile number, local format (same rule as bookings, profile and hiring). */
    public const PHONE_REGEX = '/^09\d{9}$/';

    /** Supplier-product term caps; the suppliers page reads these for its input max values. */
    public const MAX_UNIT_COST = 1000000;
    public const MAX_LEAD_TIME_DAYS = 365;
    public const MAX_ORDER_QUANTITY = 20000;

    public function index(Request $request)
    {
        $user = $request->user();
        $spaId = $user->spa_id;

        abort_unless($spaId, 403);

        $suppliers = Supplier::query()
            ->where('spa_id', $spaId)
            ->with([
                'supplierProducts.product',
            ])
            ->orderBy('name')
            ->paginate(10);

        $products = Product::query()
            ->where('spa_id', $spaId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('procurement.suppliers.index', compact(
            'suppliers',
            'products'
        ));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $spaId = $user->spa_id;

        abort_unless($spaId, 403);

        $data = $request->validateWithBag('createSupplier', [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('suppliers', 'name')
                    ->where(fn ($query) => $query->where('spa_id', $spaId)),
            ],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:' . self::PHONE_REGEX],
            'address' => ['nullable', 'string', 'max:1000'],
        ], $this->supplierMessages());

        Supplier::create([
            'spa_id' => $spaId,
            'name' => $data['name'],
            'contact_person' => $data['contact_person'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => 'active',
        ]);

        return back()->with('success', 'Supplier created successfully.');
    }

    public function update(Request $request,Supplier $supplier)
    {
        $user = $request->user();

        abort_unless(
            (int) $supplier->spa_id === (int) $user->spa_id,
            403
        );

        $data = $request->validateWithBag('editSupplier', [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('suppliers', 'name')
                    ->where(fn ($query) => $query->where('spa_id', $user->spa_id))
                    ->ignore($supplier->id),
            ],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:' . self::PHONE_REGEX],
            'address' => ['nullable', 'string', 'max:1000'],
        ], $this->supplierMessages());

        $supplier->update($data);

        return back()->with('success', 'Supplier updated successfully.');
    }

    public function updateStatus(Request $request,Supplier $supplier)
    {
        $user = $request->user();

        abort_unless(
            (int) $supplier->spa_id === (int) $user->spa_id,
            403
        );

        $data = $request->validateWithBag('supplierStatus', [
            'status' => [
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        $supplier->update([
            'status' => $data['status'],
        ]);

        if ($data['status'] === 'inactive') {
            SupplierProduct::query()
                ->where('supplier_id', $supplier->id)
                ->update([
                    'is_active' => false,
                    'is_preferred' => false,
                ]);
        }

        return back()->with(
            'success',
            $data['status'] === 'active'
                ? 'Supplier activated successfully.'
                : 'Supplier deactivated successfully.'
        );
    }

    public function attachProduct(
        Request $request,
        Supplier $supplier,
        SupplierService $supplierService
    ) {
        $user = $request->user();

        abort_unless(
            (int) $supplier->spa_id === (int) $user->spa_id,
            403
        );

        if ($supplier->status !== 'active') {
            return back()->with(
                'error',
                'Products cannot be assigned to an inactive supplier.'
            );
        }

        $data = $request->validateWithBag('supplierProduct', [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where(fn ($query) => $query
                        ->where('spa_id', $user->spa_id)
                        ->where('is_active', true)),
            ],
            ...$this->productTermRules(),
            'is_preferred' => ['nullable', 'boolean'],
        ], $this->productTermMessages());

        $product = Product::query()
            ->where('spa_id', $user->spa_id)
            ->findOrFail($data['product_id']);

        $supplierService->attachProduct(
            $supplier,
            $product,
            [
                'unit_cost' => $data['unit_cost'] ?? null,
                'lead_time_days' => $data['lead_time_days'] ?? null,
                'minimum_order_quantity' => $data['minimum_order_quantity'] ?? null,
                'is_preferred' => $request->boolean('is_preferred'),
                'is_active' => true,
            ]
        );

        return back()->with(
            'success',
            'Product assigned to supplier successfully.'
        );
    }

    public function updateProduct(
        Request $request,
        Supplier $supplier,
        SupplierProduct $supplierProduct,
        SupplierService $supplierService
    ) {
        $user = $request->user();

        abort_unless(
            (int) $supplier->spa_id === (int) $user->spa_id
            && (int) $supplierProduct->supplier_id === (int) $supplier->id,
            403
        );

        $data = $request->validateWithBag('editSupplierProduct', [
            ...$this->productTermRules(),
            'is_preferred' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], $this->productTermMessages());

        if (
            $supplier->status !== 'active'
            && (
                $request->boolean('is_active')
                || $request->boolean('is_preferred')
            )
        ) {
            return back()->with(
                'error',
                'An inactive supplier cannot have active or preferred products.'
            );
        }

        $supplierService->updateProduct(
            $supplierProduct,
            [
                'unit_cost' => $data['unit_cost'] ?? $supplierProduct->unit_cost,
                'lead_time_days' => $data['lead_time_days'] ?? $supplierProduct->lead_time_days,
                'minimum_order_quantity' => $data['minimum_order_quantity'] ?? $supplierProduct->minimum_order_quantity,
                'is_preferred' => $request->boolean('is_preferred'),
                'is_active' => $request->boolean('is_active'),
            ]
        );

        return back()->with(
            'success',
            'Supplier product updated successfully.'
        );
    }

    private function supplierMessages(): array
    {
        return [
            'phone.regex' => 'Enter an 11-digit mobile number that starts with 09, e.g. 09171234567.',
        ];
    }

    private function productTermRules(): array
    {
        return [
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:' . self::MAX_UNIT_COST, 'decimal:0,2'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:' . self::MAX_LEAD_TIME_DAYS],
            'minimum_order_quantity' => ['nullable', 'numeric', 'gt:0', 'max:' . self::MAX_ORDER_QUANTITY, 'decimal:0,3'],
        ];
    }

    private function productTermMessages(): array
    {
        return [
            'unit_cost.max' => 'Unit cost cannot be more than ₱' . number_format(self::MAX_UNIT_COST) . '.',
            'unit_cost.decimal' => 'Unit cost can have at most 2 decimal places.',
            'lead_time_days.max' => 'Lead time cannot be more than ' . self::MAX_LEAD_TIME_DAYS . ' days.',
            'minimum_order_quantity.max' => 'Minimum order quantity cannot be more than ' . number_format(self::MAX_ORDER_QUANTITY) . '.',
            'minimum_order_quantity.decimal' => 'Minimum order quantity can have at most 3 decimal places.',
        ];
    }
}
