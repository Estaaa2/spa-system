<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorBillItem extends Model
{
    protected $fillable = [
        'vendor_bill_id',
        'purchase_order_item_id',
        'product_id',
        'billed_quantity',
        'unit',
        'unit_cost',
        'line_total',
    ];

    protected $casts = [
        'billed_quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function vendorBill()
    {
        return $this->belongsTo(VendorBill::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
