<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TreatmentsSampleExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['name', 'duration', 'price', 'service_type', 'description'];
    }

    public function array(): array
    {
        return [
            ['Swedish Massage', 60, 800, 'in_branch_only', 'Relaxing full-body massage using light to medium pressure.'],
        ];
    }
}
