<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductsExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Product::query()->get([
            'name',
            'brand',
            'stock_quantity',
            'unit',
            'expiration_date',
        ]);
    }

    public function headings(): array
    {
        return [
            'name',
            'brand',
            'stock_quantity',
            'unit',
            'expiration_date',
        ];
    }
}
