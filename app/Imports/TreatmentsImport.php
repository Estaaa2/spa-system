<?php

namespace App\Imports;

use App\Models\Treatment;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class TreatmentsImport implements OnEachRow, WithHeadingRow, WithValidation
{
    public function onRow(Row $row)
    {
        $data = $row->toArray();

        $user = Auth::user();
        $branchId = $user->currentBranchId();

        Treatment::updateOrCreate(
            [
                'spa_id' => $user->spa_id,
                'branch_id' => $branchId,
                'name' => $data['name'],
            ],
            [
                'duration' => $data['duration'],
                'price' => $data['price'],
                'service_type' => $data['service_type'],
                'description' => $data['description'] ?? null,
            ]
        );
    }

    public function rules(): array
    {
        return [
            '*.name' => ['required', 'string', 'max:255'],
            '*.duration' => ['required', 'numeric', 'min:1'],
            '*.price' => ['required', 'numeric', 'min:0'],
            '*.service_type' => ['required', 'in:in_branch_only,in_branch_and_home'],
            '*.description' => ['nullable', 'string'],
        ];
    }
}
