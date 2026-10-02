<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'spa_id',
        'sku',
        'barcode',
        'name',
        'brand',
        'description',
        'category',
        'inventory_type',
        'stock_quantity',
        'unit_value',
        'unit',
        'purchase_unit',
        'usage_unit',
        'conversion_factor',
        'retail_price',
        'acquisition_cost',
        'expiration_date',
        'is_active',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'conversion_factor' => 'decimal:3',
        'retail_price' => 'decimal:2',
        'acquisition_cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function spa()
    {
        return $this->belongsTo(Spa::class);
    }

    public function branchStocks()
    {
        return $this->hasMany(BranchProductStock::class);
    }

    public function stockTransfers()
    {
        return $this->hasMany(StockTransfer::class);
    }

    public function supplierProducts()
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'supplier_products')
            ->withPivot([
                'unit_cost',
                'lead_time_days',
                'minimum_order_quantity',
                'is_preferred',
                'is_active',
            ])
            ->withTimestamps();
    }

    public function purchaseRequestItems()
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function treatmentRecipeItems()
    {
        return $this->hasMany(TreatmentRecipeItem::class);
    }

    public function batches()
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function logs()
    {
        return $this->hasMany(ProductLog::class);
    }
}
