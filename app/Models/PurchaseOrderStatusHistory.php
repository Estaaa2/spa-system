<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderStatusHistory extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'from_status',
        'to_status',
        'changed_by',
        'reason',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
