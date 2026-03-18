<?php

namespace App\Exports;

use App\Models\Package;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PackagesExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Package::query()->get([
            'name',
            'total_duration',
            'price',
            'description',
        ]);
    }

    public function headings(): array
    {
        return [
            'name',
            'total_duration',
            'price',
            'description',
        ];
    }
}
