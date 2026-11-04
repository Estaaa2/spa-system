<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductsExport implements FromCollection, WithHeadings, WithMapping
{
    protected int $spaId;
    protected int $branchId;

    public function __construct()
    {
        $user = Auth::user();

        $this->spaId = $user->spa_id;
        $this->branchId = $user->currentBranchId();
    }

    public function collection()
    {
        return Product::query()
            ->where('spa_id', $this->spaId)
            ->whereHas('branchStocks', function ($query) {
                $query->where('branch_id', $this->branchId);
            })
            ->with([
                'branchStocks' => function ($query) {
                    $query->where('branch_id', $this->branchId);
                },
            ])
            ->orderBy('name')
            ->get();
    }

    public function map($product): array
    {
        $stock = $product->branchStocks->first();

        return [
            $product->sku,
            $product->barcode,
            $product->name,
            $product->brand,
            $product->description,
            $product->category,
            $product->inventory_type,
            $product->purchase_unit,
            $product->usage_unit,
            $product->conversion_factor,
            $product->retail_price,
            $product->acquisition_cost,
            $stock?->reorder_level ?? 0,
            $stock?->minimum_stock,
            $stock?->maximum_stock,
        ];
    }

    public function headings(): array
    {
        return [
            'sku',
            'barcode',
            'name',
            'brand',
            'description',
            'category',
            'inventory_type',
            'purchase_unit',
            'usage_unit',
            'conversion_factor',
            'retail_price',
            'acquisition_cost',
            'reorder_level',
            'minimum_stock',
            'maximum_stock',
        ];
    }
}