<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'product_id',
        'unit_cost',
        'lead_time_days',
        'minimum_order_quantity',
        'is_preferred',
        'is_active',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'minimum_order_quantity' => 'decimal:3',
        'is_preferred' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
