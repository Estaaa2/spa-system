<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ProductsImport implements OnEachRow, WithHeadingRow, WithValidation
{
    public function onRow(Row $row)
    {
        $data = $row->toArray();

        $user = Auth::user();
        $branchId = method_exists($user, 'currentBranchId')
            ? $user->currentBranchId()
            : ($user->branch_id ?? null);

        Product::updateOrCreate(
            [
                'spa_id' => $user->spa_id,
                'branch_id' => $branchId,
                'name' => $data['name'],
            ],
            [
                'brand' => $data['brand'] ?? null,
                'stock_quantity' => $data['stock_quantity'] ?? 0,
                'unit' => $data['unit'] ?? null,
                'expiration_date' => !empty($data['expiration_date']) ? $data['expiration_date'] : null,
            ]
        );
    }

    public function rules(): array
    {
        return [
            '*.name' => ['required', 'string', 'max:255'],
            '*.brand' => ['nullable', 'string', 'max:255'],
            '*.stock_quantity' => ['required', 'numeric', 'min:0'],
            '*.unit' => ['nullable', 'numeric', 'min:0'],
            '*.expiration_date' => ['nullable', 'date'],
        ];
    }
}
