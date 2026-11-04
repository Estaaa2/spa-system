<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'spa_id',
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'status',
    ];

    public function spa()
    {
        return $this->belongsTo(Spa::class);
    }

    public function supplierProducts()
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'supplier_products')
            ->withPivot([
                'unit_cost',
                'lead_time_days',
                'minimum_order_quantity',
                'is_preferred',
                'is_active',
            ])
            ->withTimestamps();
    }
}
