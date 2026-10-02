<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    protected $fillable = [
        'spa_id',
        'branch_id',
        'purchase_order_id',
        'received_by',
        'delivery_reference',
        'received_at',
        'notes',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function spa()
    {
        return $this->belongsTo(Spa::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items()
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}
