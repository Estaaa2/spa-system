<?php

namespace App\Imports;

use App\Models\BranchProductStock;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ProductsImport implements OnEachRow, WithHeadingRow, WithValidation
{
    protected int $spaId;
    protected int $branchId;

    public function __construct()
    {
        $user = Auth::user();

        $this->spaId = $user->spa_id;
        $this->branchId = $user->currentBranchId();
    }

    public function onRow(Row $row)
    {
        $data = $row->toArray();

        DB::transaction(function () use ($data) {
            $product = Product::query()
                ->where('spa_id', $this->spaId)
                ->when(
                    !empty($data['sku']),
                    fn ($query) => $query->where('sku', $data['sku']),
                    fn ($query) => $query->where('name', $data['name'])
                )
                ->first();

            if (!$product) {
                $product = new Product();
                $product->spa_id = $this->spaId;
            }

            $product->fill([
                'sku' => $data['sku'] ?? null,
                'barcode' => $data['barcode'] ?? null,
                'name' => $data['name'],
                'brand' => $data['brand'] ?? null,
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? null,
                'inventory_type' => $data['inventory_type'] ?? 'backbar',
                'purchase_unit' => $data['purchase_unit'] ?? null,
                'usage_unit' => $data['usage_unit'],
                'unit' => $data['usage_unit'],
                'conversion_factor' => $data['conversion_factor'] ?? 1,
                'retail_price' => $data['retail_price'] ?? null,
                'acquisition_cost' => $data['acquisition_cost'] ?? null,
                'is_active' => true,
            ]);

            if (!$product->exists) {
                $product->stock_quantity = 0;
                $product->unit_value = 0;
                $product->expiration_date = null;
            }

            $product->save();

            BranchProductStock::firstOrCreate(
                [
                    'branch_id' => $this->branchId,
                    'product_id' => $product->id,
                ],
                [
                    'spa_id' => $this->spaId,
                    'on_hand_quantity' => 0,
                    'reorder_level' => $data['reorder_level'] ?? 0,
                    'minimum_stock' => $data['minimum_stock'] ?? null,
                    'maximum_stock' => $data['maximum_stock'] ?? null,
                ]
            )->update([
                'reorder_level' => $data['reorder_level'] ?? 0,
                'minimum_stock' => $data['minimum_stock'] ?? null,
                'maximum_stock' => $data['maximum_stock'] ?? null,
            ]);
        });
    }

    public function rules(): array
    {
        return [
            '*.sku' => ['nullable', 'string', 'max:255'],
            '*.barcode' => ['nullable', 'string', 'max:255'],
            '*.name' => ['required', 'string', 'max:255'],
            '*.brand' => ['nullable', 'string', 'max:255'],
            '*.description' => ['nullable', 'string'],
            '*.category' => ['nullable', 'string', 'max:255'],
            '*.inventory_type' => [
                'required',
                Rule::in(['retail', 'backbar', 'both']),
            ],
            '*.purchase_unit' => ['nullable', 'string', 'max:30'],
            '*.usage_unit' => [
                'required',
                Rule::in(['ml', 'L', 'g', 'kg', 'pcs']),
            ],
            '*.conversion_factor' => ['required', 'numeric', 'gt:0'],
            '*.retail_price' => ['nullable', 'numeric', 'min:0'],
            '*.acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            '*.reorder_level' => ['required', 'numeric', 'min:0'],
            '*.minimum_stock' => ['nullable', 'numeric', 'min:0'],
            '*.maximum_stock' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}