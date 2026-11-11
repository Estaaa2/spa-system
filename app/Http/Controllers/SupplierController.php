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
    /**
     * Philippine phone, local format, digits only. Suppliers are businesses and
     * often only have a landline, so both are accepted:
     *   mobile   09XXXXXXXXX  (11 digits, same rule as bookings, profile, hiring)
     *   landline 0 + area code + number, 10 digits (e.g. 0461234567, 0281234567)
     */
    public const PHONE_REGEX = '/^(09\d{9}|0[2-8]\d{8})$/';

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
                // A field left blank clears the saved value (it used to keep the old one).
                'unit_cost' => $data['unit_cost'] ?? null,
                'lead_time_days' => $data['lead_time_days'] ?? null,
                'minimum_order_quantity' => $data['minimum_order_quantity'] ?? null,
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
            'phone.regex' => 'Enter a mobile number (09171234567) or a landline with area code (0461234567), digits only.',
        ];
    }

    private function productTermRules(): array
    {
        return [
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:' . config('limits.money'), 'decimal:0,2'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:' . config('limits.lead_time_days')],
            'minimum_order_quantity' => ['nullable', 'numeric', 'gt:0', 'max:' . config('limits.quantity'), 'decimal:0,3'],
        ];
    }

    private function productTermMessages(): array
    {
        return [
            'unit_cost.max' => 'Unit cost cannot be more than ₱' . number_format(config('limits.money')) . '.',
            'unit_cost.decimal' => 'Unit cost can have at most 2 decimal places.',
            'lead_time_days.max' => 'Lead time cannot be more than ' . config('limits.lead_time_days') . ' days.',
            'minimum_order_quantity.max' => 'Minimum order quantity cannot be more than ' . number_format(config('limits.quantity')) . '.',
            'minimum_order_quantity.decimal' => 'Minimum order quantity can have at most 3 decimal places.',
        ];
    }
}
