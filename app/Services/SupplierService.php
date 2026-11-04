<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    public function attachProduct(Supplier $supplier,Product $product,array $data): SupplierProduct
    {
        if ((int) $supplier->spa_id !== (int) $product->spa_id) {
            throw ValidationException::withMessages([
                'product_id' => 'The selected product does not belong to the same spa as this supplier.',
            ]);
        }

        return DB::transaction(function () use ($supplier, $product, $data) {
            $exists = SupplierProduct::query()
                ->where('supplier_id', $supplier->id)
                ->where('product_id', $product->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'product_id' => 'This product is already assigned to the supplier.',
                ]);
            }

            if (!empty($data['is_preferred'])) {
                $this->clearPreferredSupplier(
                    $supplier->spa_id,
                    $product->id
                );
            }

            return SupplierProduct::create([
                'supplier_id' => $supplier->id,
                'product_id' => $product->id,
                'unit_cost' => $data['unit_cost'] ?? null,
                'lead_time_days' => $data['lead_time_days'] ?? null,
                'minimum_order_quantity' => $data['minimum_order_quantity'] ?? null,
                'is_preferred' => (bool) ($data['is_preferred'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);
        });
    }

    public function updateProduct(SupplierProduct $supplierProduct,array $data): SupplierProduct
    {
        return DB::transaction(function () use ($supplierProduct, $data) {
            $supplierProduct->loadMissing([
                'supplier',
                'product',
            ]);

            if (
                (int) $supplierProduct->supplier->spa_id
                !== (int) $supplierProduct->product->spa_id
            ) {
                throw ValidationException::withMessages([
                    'product_id' => 'The supplier and product belong to different spas.',
                ]);
            }

            if (!empty($data['is_preferred'])) {
                $this->clearPreferredSupplier(
                    $supplierProduct->supplier->spa_id,
                    $supplierProduct->product_id,
                    $supplierProduct->id
                );
            }

            $supplierProduct->update([
                'unit_cost' => $data['unit_cost'] ?? $supplierProduct->unit_cost,
                'lead_time_days' => $data['lead_time_days'] ?? $supplierProduct->lead_time_days,
                'minimum_order_quantity' => $data['minimum_order_quantity'] ?? $supplierProduct->minimum_order_quantity,
                'is_preferred' => array_key_exists('is_preferred', $data)
                    ? (bool) $data['is_preferred']
                    : $supplierProduct->is_preferred,
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : $supplierProduct->is_active,
            ]);

            return $supplierProduct->fresh();
        });
    }

    private function clearPreferredSupplier(
        int $spaId,
        int $productId,
        ?int $exceptId = null
    ): void {
        SupplierProduct::query()
            ->where('product_id', $productId)
            ->whereHas('supplier', fn ($query) => $query
                ->where('spa_id', $spaId))
            ->when($exceptId, fn ($query) => $query
                ->where('id', '!=', $exceptId))
            ->where('is_preferred', true)
            ->update([
                'is_preferred' => false,
            ]);
    }
}
