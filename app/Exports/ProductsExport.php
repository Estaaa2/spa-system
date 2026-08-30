<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductsExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Product::where('spa_id', Auth::user()->spa_id)
            ->get([
                'name',
                'brand',
                'stock_quantity',
                'unit_value',
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
            'unit_value',
            'unit',
            'expiration_date',
        ];
    }
}
