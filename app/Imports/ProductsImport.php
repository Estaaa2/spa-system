<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
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

        Product::updateOrCreate(
            [
                'spa_id' => $user->spa_id,
                'name'   => $data['name'],
            ],
            [
                'brand'           => $data['brand'] ?? null,
                'stock_quantity'  => $data['stock_quantity'] ?? 0,
                'unit_value'      => $data['unit_value'] ?? 0,
                'unit'            => $data['unit'] ?? 'ml',
                'expiration_date' => !empty($data['expiration_date']) ? $data['expiration_date'] : null,
            ]
        );
    }

    public function rules(): array
    {
        return [
            '*.name'            => ['required', 'string', 'max:255'],
            '*.brand'           => ['nullable', 'string', 'max:255'],
            '*.stock_quantity'  => ['required', 'integer', 'min:0'],
            '*.unit_value'      => ['nullable', 'integer', 'min:0'],
            '*.unit'            => ['nullable', Rule::in(['ml', 'L', 'g', 'kg', 'pcs'])],
            '*.expiration_date' => ['nullable', 'date'],
        ];
    }
}
