<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PackagesSampleExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['name', 'total_duration', 'price', 'description'];
    }

    public function array(): array
    {
        return [
            ['Relax & Renew Package', 120, 1500, 'Full body massage plus facial treatment combo.'],
        ];
    }
}
