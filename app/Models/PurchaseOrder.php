<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'spa_id',
        'branch_id',
        'purchase_request_id',
        'supplier_id',
        'created_by',
        'status',
        'notes',
        'issued_at',
        'expected_delivery_date',
        'cancelled_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expected_delivery_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function spa()
    {
        return $this->belongsTo(Spa::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(PurchaseOrderStatusHistory::class);
    }
}
