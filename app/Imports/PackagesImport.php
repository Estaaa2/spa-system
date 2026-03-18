<?php

namespace App\Imports;

use App\Models\Package;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class PackagesImport implements OnEachRow, WithHeadingRow, WithValidation
{
    public function onRow(Row $row)
    {
        $data = $row->toArray();

        $user = Auth::user();
        $branchId = $user->currentBranchId();

        Package::updateOrCreate(
            [
                'spa_id' => $user->spa_id,
                'branch_id' => $branchId,
                'name' => $data['name'],
            ],
            [
                'total_duration' => $data['total_duration'] ?? null,
                'price' => $data['price'],
                'description' => $data['description'] ?? null,
            ]
        );
    }

    public function rules(): array
    {
        return [
            '*.name' => ['required', 'string', 'max:255'],
            '*.total_duration' => ['nullable', 'numeric', 'min:1'],
            '*.price' => ['required', 'numeric', 'min:0'],
            '*.description' => ['nullable', 'string'],
        ];
    }
}
