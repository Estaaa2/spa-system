<?php

namespace App\Exports;

use App\Models\Treatment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TreatmentsExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Treatment::query()->get([
            'name',
            'duration',
            'price',
            'service_type',
            'description',
        ]);
    }

    public function headings(): array
    {
        return [
            'name',
            'duration',
            'price',
            'service_type',
            'description',
        ];
    }
}
