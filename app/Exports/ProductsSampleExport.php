<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductsSampleExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return new Collection([
            [
                'OIL-LAV-001',
                '',
                'Lavender Massage Oil',
                'ZenCare',
                'Lavender massage oil used for spa treatments.',
                'Massage Oils',
                'backbar',
                'bottle',
                'ml',
                1000,
                '',
                250,
                1000,
                500,
                10000,
            ],
        ]);
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