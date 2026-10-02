<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'product_id',
        'product_batch_id',
        'received_quantity',
        'accepted_quantity',
        'rejected_quantity',
        'unit',
        'conversion_factor',
        'inventory_quantity',
        'batch_number',
        'manufactured_at',
        'expiration_date',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'received_quantity' => 'decimal:3',
        'accepted_quantity' => 'decimal:3',
        'rejected_quantity' => 'decimal:3',
        'conversion_factor' => 'decimal:3',
        'inventory_quantity' => 'decimal:3',
        'manufactured_at' => 'date',
        'expiration_date' => 'date',
    ];

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productBatch()
    {
        return $this->belongsTo(ProductBatch::class);
    }
}
